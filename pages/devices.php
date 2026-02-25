<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';

if ($_SESSION['role'] != ROLE_ADMIN) {
    header('Location: /login.php');
    exit();
}

require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/classes/Organization.php';

$device = new Device();
$user = new User();
$organization = new Organization();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];
    
    try {
        
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

$devices = $device->getAllDevices();

$organizations_list = $organization->getAllOrganizations(false);

$all_devices = $device->getAllDevices();
$meteostations = array_filter($all_devices, function($d) {
    return $d['device_type'] == 'M';
});

include ROOT_PATH . '/includes/header.php';
?>
<style>
    #map { height: 400px; width: 100%; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
    .coordinates-container { display: flex; gap: 10px; }
    .coordinates-container input { flex: 1; }
    .map-container { position: relative; }
    .map-search { 
        position: absolute; top: 10px; left: 10px; z-index: 1000; width: 300px; 
    }
    .map-search input {
        width: 100%; padding: 8px; border-radius: 3px; border: 1px solid #ddd; box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    #createMap, #editMap {
        height: 250px;
    }
    
    .unassigned-device {
        background-color: #ffebee;
    }
    
    .service-toggle {
        margin-right: 10px;
    }
    
    .deleted-device {
        background-color: #ffebee;
    }

    .blocked-device {
        background-color: #ffcdd2;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 3px;
        align-items: center;
        min-width: 40px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        padding: 6px;
        border-radius: 4px;
        border: 1px solid;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 12px;
    }

    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .action-btn i {
        margin: 0;
    }

    .btn-edit {
        background-color: #5755d9;
        border-color: #5755d9;
        color: white;
    }

    .btn-edit:hover {
        background-color: #4b48d6;
        border-color: #4b48d6;
        color: white;
    }

    .btn-delete {
        background-color: #e85600;
        border-color: #e85600;
        color: white;
    }

    .btn-delete:hover {
        background-color: #d64500;
        border-color: #d64500;
        color: white;
    }

    .btn-service {
        background-color: #32b643;
        border-color: #32b643;
        color: white;
    }

    .btn-service:hover {
        background-color: #28a138;
        border-color: #28a138;
        color: white;
    }

    .btn-forecast {
        background-color: #ffb700;
        border-color: #ffb700;
        color: white;
    }

    .btn-forecast:hover {
        background-color: #e6a500;
        border-color: #e6a500;
        color: white;
    }

    .btn-meteo {
        background-color: #667eea;
        border-color: #667eea;
        color: white;
    }

    .btn-meteo:hover {
        background-color: #5a6fd8;
        border-color: #5a6fd8;
        color: white;
    }

    .btn-restore {
        background-color: #32b643;
        border-color: #32b643;
        color: white;
    }

    .btn-restore:hover {
        background-color: #28a138;
        border-color: #28a138;
        color: white;
    }

    /* Адаптивность для мобильных устройств */
    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            min-width: auto;
        }
        
        .action-btn {
            width: 28px;
            height: 28px;
            font-size: 10px;
        }
    }
</style>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
    <div class="column col-9 col-xs-12">  
        <h2>Управление устройствами</h2>
        <?php displayMessages(); ?>      
        <table id="devicesTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Организация</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filtered_devices = [];
                foreach ($devices as $d) {                    
                    $filtered_devices[] = $d;                  
                }
                foreach ($filtered_devices as $d):
                    $device_org = null;
                    if ($d['organization_id']) {
                        $device_org = $organization->getOrganization($d['organization_id']);
                    }
                    
                    $row_class = $d['organization_id'] ? '' : 'unassigned-device';
                    
                    $linked_meteostation = null;
                    if ($d['linked_meteostation_id']) {
                        $linked_meteostation = $device->getDevice($d['linked_meteostation_id']);
                    }
                ?>
                <tr class="<?= $row_class ?> <?= isset($d['is_deleted']) && $d['is_deleted'] ? 'deleted-device' : '' ?> <?= isset($d['is_blocked']) && $d['is_blocked'] ? 'blocked-device' : '' ?>">
                    <td><?= htmlspecialchars($d['device_id']) ?></td>
                    <td><?= htmlspecialchars($d['name']) ?></td>
                    <td><?= $d['device_type'] == 'M' ? 'Метеостанция' : ($d['device_type'] == 'VP' ? 'Влажность почвы' : 'Другое') ?></td>
                    
                    <td><?= $device_org ? htmlspecialchars($device_org['name']) : 'Не назначена' ?></td>
                    
                    <td>
                        <div class="action-buttons">
                            <?php if (!isset($d['is_deleted']) || !$d['is_deleted']): ?>                            
                           
                           <!-- <button class="action-btn btn-service btn btn-sm" 
                                    title="Сервисное меню за последние 7 суток"
                                    onclick="openServiceMenu('<?= htmlspecialchars($d['device_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($d['device_type'], ENT_QUOTES) ?>')">
                                <i class="fas fa-tools"></i>
                            </button>-->

                            <?php if ($d['device_type'] == 'VP'): ?>
                            <button class="action-btn btn-forecast btn btn-sm" 
                                    title="Показать прогноз влажности"
                                    onclick="window.location.href='device_forecast.php?device_id=<?= $d['id'] ?>'">
                                <i class="fas fa-chart-line"></i>
                            </button>
                            <?php elseif ($d['device_type'] == 'M'): ?>
                            <button class="action-btn btn-meteo btn btn-sm" 
                                    title="Показать метеоданные"
                                    onclick="window.location.href='device_meteo.php?device_id=<?= $d['id'] ?>'">
                                <i class="fas fa-cloud-sun"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php else: ?>
                           <!-- <button class="action-btn btn-restore btn btn-sm" 
                                    title="Восстановить устройство"
                                    onclick="restoreDevice(<?= $d['id'] ?>)">
                                <i class="fas fa-undo"></i>
                            </button>-->
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>





<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
<script>
    let createMap, editMap;
    let createPlacemark, editPlacemark;
    
    ymaps.ready(function() {
        createMap = new ymaps.Map('createMap', {
            center: [55.76, 37.64],
            zoom: 10,
            controls: ['zoomControl', 'searchControl']
        });
        
        createPlacemark = new ymaps.Placemark([55.76, 37.64], {}, {
            draggable: true
        });
        
        createMap.geoObjects.add(createPlacemark);
        
        createMap.events.add('click', function(e) {
            const coords = e.get('coords');
            createPlacemark.geometry.setCoordinates(coords);
            document.getElementById('create_coordinates').value = coords.join(',');
        });
        
        createPlacemark.events.add('dragend', function() {
            const coords = createPlacemark.geometry.getCoordinates();
            document.getElementById('create_coordinates').value = coords.join(',');
        });
        
        editMap = new ymaps.Map('editMap', {
            center: [55.76, 37.64],
            zoom: 10,
            controls: ['zoomControl', 'searchControl']
        });
        
        editPlacemark = new ymaps.Placemark([55.76, 37.64], {}, {
            draggable: true
        });
        
        editMap.geoObjects.add(editPlacemark);
        
        editMap.events.add('click', function(e) {
            const coords = e.get('coords');
            editPlacemark.geometry.setCoordinates(coords);
            document.getElementById('edit_coordinates').value = coords.join(',');
        });
        
        editPlacemark.events.add('dragend', function() {
            const coords = editPlacemark.geometry.getCoordinates();
            document.getElementById('edit_coordinates').value = coords.join(',');
        });
    });
    
    function toggleHumidityCountField(prefix) {
        const deviceTypeSelect = document.getElementById(prefix + '_device_type');
        const humidityCountGroup = document.getElementById(prefix + '_humidity_count_group');
        
        if (deviceTypeSelect && humidityCountGroup) {
            if (deviceTypeSelect.value === 'VP') {
                humidityCountGroup.style.display = 'block';
            } else {
                humidityCountGroup.style.display = 'none';
            }
        }
    }
    
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        toggleMeteostationSelect('create');
        toggleHumidityCountField('create');
        
        setTimeout(() => {
            createMap.container.fitToViewport();
        }, 100);

        document.getElementById('create_contract_start_date').value = '';
        document.getElementById('create_contract_end_date').value = '';
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }
    
    function toggleMeteostationSelect(prefix) {
        const deviceTypeSelect = document.getElementById(prefix + '_device_type');
        const meteostationGroup = document.getElementById(prefix + '_meteostation_group');
        
        if (deviceTypeSelect && meteostationGroup) {
            if (deviceTypeSelect.value === 'M') {
                meteostationGroup.style.display = 'none';
                if (prefix === 'edit') {
                    document.getElementById('edit_meteostation_id').value = '';
                }
            } else {
                meteostationGroup.style.display = 'block';
            }
        }
    }
    
    function submitCreateForm() {
        const form = document.getElementById('createForm');
        const formData = new FormData(form);
        formData.append('create', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeCreateModal();
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function openEditModal(device) {
        document.getElementById('edit_id').value = device.id;
        document.getElementById('edit_device_id').value = device.device_id;
        document.getElementById('edit_name').value = device.name;
        document.getElementById('edit_device_type').value = device.device_type;
        document.getElementById('edit_humidity_count').value = device.humidity_count || 3;
        
        if (document.getElementById('edit_device_type_hidden')) {
            document.getElementById('edit_device_type_hidden').value = device.device_type;
        }
        document.getElementById('edit_coordinates').value = device.coordinates || '';
        
        if (device.coordinates) {
            const coords = device.coordinates.split(',').map(Number);
            editPlacemark.geometry.setCoordinates(coords);
            editMap.setCenter(coords);
        }
        
        toggleHumidityCountField('edit');
        
        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
        document.getElementById('edit_organization_id').value = device.organization_id || '';
        document.getElementById('edit_forecast_enabled').checked = device.is_forecast_enabled;
        document.getElementById('edit_realdata_enabled').checked = device.is_realdata_enabled;
        document.getElementById('edit_analytics_enabled').checked = device.is_analytics_enabled;
        document.getElementById('edit_calculations_enabled').checked = device.is_calculations_enabled;
        document.getElementById('edit_meteostation_id').value = device.linked_meteostation_id || '';
        toggleMeteostationSelect('edit');
        <?php endif; ?>

        document.getElementById('edit_contract_start_date').value = device.contract_start_date || '';
        document.getElementById('edit_contract_end_date').value = device.contract_end_date || '';
        
        document.getElementById('editModal').classList.add('active');
        
        setTimeout(() => {
            editMap.container.fitToViewport();
        }, 100);
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }
    
    function submitEditForm() {
        const form = document.getElementById('editForm');
        const formData = new FormData(form);
        formData.append('edit', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEditModal();
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function deleteDevice(deviceId) {
        if (!confirm('Вы уверены, что хотите удалить это устройство?')) return;
        
        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('device_id', deviceId);
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function showDeviceForecast(deviceId) {
        window.location.href = 'device_forecast.php?device_id=' + deviceId;
    }

    function restoreDevice(deviceId) {
        if (!confirm('Вы уверены, что хотите восстановить это устройство?')) return;
    
        const formData = new FormData();
        formData.append('restore_device', '1');
        formData.append('device_id', deviceId);
    
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function openServiceMenu(deviceId, deviceType) {
        let serviceDeviceType = '';
        switch(deviceType) {
            case 'VP':
                serviceDeviceType = 'soil';
                break;
            case 'M':
                serviceDeviceType = 'meteo';
                break;
            default:
                alert('Неподдерживаемый тип устройства для сервисного меню: ' + deviceType);
                return;
        }
        
        const today = new Date();
        const weekAgo = new Date(today);
        weekAgo.setDate(weekAgo.getDate() - 7);
        
        const formatDate = (date) => {
            return date.getFullYear() + '-' + 
                String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                String(date.getDate()).padStart(2, '0');
        };
        
        const startDate = formatDate(weekAgo);
        const endDate = formatDate(today);
        
        const offsetMinutes = -today.getTimezoneOffset();
        const timezoneOffset = offsetMinutes / 60;
        
        const params = new URLSearchParams({
            device_type: serviceDeviceType,
            device_id: deviceId,
            start_date: startDate,
            end_date: endDate,
            timezone_offset: timezoneOffset
        });
        
        const serviceUrl = '/pages/service.php?' + params.toString();
        
        window.open(serviceUrl, '_blank');
    }
    
    function showMessage(type, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `toast toast-${type}`;
        messageDiv.textContent = text;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
        document.getElementById('create_device_type').addEventListener('change', function() {
            toggleMeteostationSelect('create');
            toggleHumidityCountField('create');
        });
        
        document.getElementById('edit_device_type').addEventListener('change', function() {
            toggleMeteostationSelect('edit');
            toggleHumidityCountField('edit');
        });
        <?php endif; ?>

        <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
        document.getElementById('showDeletedToggle').addEventListener('change', function() {
            window.location.href = 'devices.php?show_deleted=' + (this.checked ? '1' : '0');
        });
        <?php endif; ?>

        $('#devicesTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]]
        });
    });
</script>
