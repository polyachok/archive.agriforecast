<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/DeviceGroup.php';
require_once ROOT_PATH . '/classes/User.php';

$device = new Device();
$deviceGroup = new DeviceGroup();
$user = new User();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];
    
    try {
        if (isset($_POST['delete'])) {
            if ($deviceGroup->deleteGroup($_POST['group_id'])) {
                $response = ['success' => true, 'message' => 'Группа удалена'];
            } else {
                throw new Exception('Ошибка при удалении');
            }
        } 
        if (isset($_POST['edit'])) {
            try {
                $id = (int)$_POST['id'];
                $name = $_POST['name'];
                $min_lat = $_POST['min_lat'];
                $max_lat = $_POST['max_lat'];
                $min_lng = $_POST['min_lng'];
                $max_lng = $_POST['max_lng'];
                $devices = isset($_POST['devices']) ? array_unique($_POST['devices']) : [];
                
                if ($deviceGroup->updateGroup($id, $name, $min_lat, $max_lat, $min_lng, $max_lng, $devices)) {
                    $response = ['success' => true, 'message' => 'Группа обновлена'];
                } else {
                    throw new Exception('Ошибка при обновлении');
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
        }
        elseif (isset($_POST['create'])) {
            $name = $_POST['name'];
            $min_lat = $_POST['min_lat'];
            $max_lat = $_POST['max_lat'];
            $min_lng = $_POST['min_lng'];
            $max_lng = $_POST['max_lng'];
            $devices = isset($_POST['devices']) ? $_POST['devices'] : [];
            
            if ($deviceGroup->createGroup($name, $min_lat, $max_lat, $min_lng, $max_lng, $devices)) {
                $response = ['success' => true, 'message' => 'Группа создана'];
            } else {
                throw new Exception('Ошибка при создании');
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

$groups = $deviceGroup->getAllGroups();
$allDevices = $device->getAllDevices();
?>

<?php include ROOT_PATH . '/includes/header.php'; ?>
<style>
    #map { height: 400px; width: 100%; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
    .coordinates-container { display: flex; gap: 10px; margin-bottom: 10px; }
    .coordinates-container .form-group { flex: 1; }
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
    
    @media (max-width: 768px) {
        .coordinates-container {
            flex-direction: column;
        }
        
        .btn-group-block {
            flex-direction: column;
            gap: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 6px;
            font-size: 0.8rem;
        }
    }
    
    .modal-container {
        width: 90%;
        max-width: 600px;
        margin: 20px auto;
    }
    
    @media (max-width: 480px) {
        .modal-container {
            width: 95%;
            margin: 10px auto;
        }
        
        #createMap, #editMap {
            height: 200px;
        }
    }
    
    .devices-select {
        height: 150px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 4px;
    }
    
    .devices-select label {
        display: block;
        margin-bottom: 5px;
    }
</style>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
    <div class="column col-9 col-xs-12">  
        <h2>Управление группами приборов</h2>
        <?php displayMessages(); ?>
        
        <button class="btn btn-primary" onclick="openCreateModal()" style="margin-bottom: 15px;">Добавить группу</button>
        
        <div id="groupsTableContainer">
        </div>
    </div>
</div>

<div class="modal" id="createModal">
    <div class="modal-overlay" onclick="closeCreateModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeCreateModal()"></button>
            <h3>Создать новую группу</h3>
        </div>
        <div class="modal-body">
            <form id="createForm">
                <div class="form-group">
                    <label class="form-label" for="create_name">Название группы</label>
                    <input class="form-input" type="text" id="create_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Границы области</label>
                    <div class="coordinates-container">
                        <div class="form-group">
                            <label class="form-label" for="create_min_lat">Минимальная широта</label>
                            <input class="form-input" type="text" id="create_min_lat" name="min_lat" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="create_max_lat">Максимальная широта</label>
                            <input class="form-input" type="text" id="create_max_lat" name="max_lat" required>
                        </div>
                    </div>
                    <div class="coordinates-container">
                        <div class="form-group">
                            <label class="form-label" for="create_min_lng">Минимальная долгота</label>
                            <input class="form-input" type="text" id="create_min_lng" name="min_lng" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="create_max_lng">Максимальная долгота</label>
                            <input class="form-input" type="text" id="create_max_lng" name="max_lng" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Приборы в группе</label>
                    <div class="devices-select" id="create_devices_select">
                        <?php 
                        $availableDevices = array_filter($allDevices, function($d) {
                            return empty($d['group_id']);
                        });
                        
                        foreach ($availableDevices as $d): 
                        ?>
                            <label>
                                <input type="checkbox" name="devices[]" value="<?= $d['id'] ?>">
                                <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['device_id']) ?>)
                            </label>
                        <?php endforeach; ?>
                        
                        <?php if (empty($availableDevices)): ?>
                            <p>Нет доступных приборов для добавления в группу</p>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitCreateForm()">Создать</button>
            <button class="btn btn-link" onclick="closeCreateModal()">Отмена</button>
        </div>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeEditModal()"></button>
            <h3>Редактировать группу</h3>
        </div>
        <div class="modal-body">
            <form id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label" for="edit_name">Название группы</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Границы области</label>
                    <div class="coordinates-container">
                        <div class="form-group">
                            <label class="form-label" for="edit_min_lat">Минимальная широта</label>
                            <input class="form-input" type="text" id="edit_min_lat" name="min_lat" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_max_lat">Максимальная широта</label>
                            <input class="form-input" type="text" id="edit_max_lat" name="max_lat" required>
                        </div>
                    </div>
                    <div class="coordinates-container">
                        <div class="form-group">
                            <label class="form-label" for="edit_min_lng">Минимальная долгота</label>
                            <input class="form-input" type="text" id="edit_min_lng" name="min_lng" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_max_lng">Максимальная долгота</label>
                            <input class="form-input" type="text" id="edit_max_lng" name="max_lng" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Приборы в группе</label>
                    <div class="devices-select" id="edit_devices_select">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitEditForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeEditModal()">Отмена</button>
        </div>
    </div>
</div>

<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
<script src="/assets/js/main.js"></script>
<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        document.getElementById('createForm').reset();
        updateCreateDevicesList();
    }

    function updateCreateDevicesList() {
        fetch('get_devices_for_group.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('create_devices_select');
                container.innerHTML = '';
                
                data.devices.forEach(device => {
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'devices[]';
                    checkbox.value = device.id;
                    
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(' ' + device.name + ' (' + device.device_id + ')'));
                    container.appendChild(label);
                });
                
                if (data.devices.length === 0) {
                    container.innerHTML = '<p>Нет доступных приборов для добавления в группу</p>';
                }
            });
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }
    
    function openEditModal(group) {
        document.getElementById('edit_id').value = group.id;
        document.getElementById('edit_name').value = group.name;
        document.getElementById('edit_min_lat').value = group.min_lat;
        document.getElementById('edit_max_lat').value = group.max_lat;
        document.getElementById('edit_min_lng').value = group.min_lng;
        document.getElementById('edit_max_lng').value = group.max_lng;
        
        const devicesContainer = document.getElementById('edit_devices_select');
        devicesContainer.innerHTML = '';
        
        fetch('get_devices_for_group.php?group_id=' + group.id)
            .then(response => response.json())
            .then(data => {
                data.devices.forEach(device => {
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'devices[]';
                    checkbox.value = device.id;
                    if (device.in_group) {
                        checkbox.checked = true;
                    }
                    
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(' ' + device.name + ' (' + device.device_id + ')'));
                    devicesContainer.appendChild(label);
                });
                
                if (data.devices.length === 0) {
                    devicesContainer.innerHTML = '<p>Нет доступных приборов</p>';
                }
            });
        
        document.getElementById('editModal').classList.add('active');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
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
                loadGroupsTable();
                showMessage('success', data.message);
                updateCreateDevicesList();
            } else {
                showMessage('error', data.message);
                if (data.message.includes("уже находятся в других группах")) {
                    updateCreateDevicesList();
                }
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function submitEditForm() {
        const form = document.getElementById('editForm');
        const formData = new FormData();

        formData.append('id', document.getElementById('edit_id').value);
        formData.append('name', document.getElementById('edit_name').value);
        formData.append('min_lat', document.getElementById('edit_min_lat').value);
        formData.append('max_lat', document.getElementById('edit_max_lat').value);
        formData.append('min_lng', document.getElementById('edit_min_lng').value);
        formData.append('max_lng', document.getElementById('edit_max_lng').value);
        formData.append('edit', '1');

        const checkboxes = document.querySelectorAll('#edit_devices_select input[type="checkbox"]:checked');
        checkboxes.forEach(checkbox => {
            formData.append('devices[]', checkbox.value);
        });
        
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
                loadGroupsTable();
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message);
                console.error('Ошибка обновления:', data);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
            console.error('Ошибка сети:', error);
        });
    }
    
    function deleteGroup(groupId) {
        if (!confirm('Удалить группу?')) return;
        
        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('group_id', groupId);
        
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
                loadGroupsTable();
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function loadGroupsTable() {
        fetch('devices_groups_table.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('groupsTableContainer').innerHTML = html;
                
                if ($.fn.DataTable.isDataTable('#groupsTable')) {
                    $('#groupsTable').DataTable().destroy();
                }
                
                $('#groupsTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
                    },
                    "pageLength": 10,
                    "dom": '<"top"f>rt<"bottom"lip><"clear">',
                    "responsive": true
                });
            })
            .catch(error => {
                console.error('Ошибка загрузки таблицы:', error);
            });
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

    loadGroupsTable();
</script>
