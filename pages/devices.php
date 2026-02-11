<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';
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
        if (isset($_POST['delete'])) {
            if ($_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может удалять устройства');
            }
            
            if ($device->deleteDevice($_POST['device_id'])) {
                $response = ['success' => true, 'message' => 'Устройство удалено'];
            } else {
                throw new Exception('Ошибка при удалении');
            }
        } 
        elseif (isset($_POST['edit'])) {
            $id = $_POST['id'];
            $device_info = $device->getDevice($id);
            
            if ($_SESSION['role'] == ROLE_USER) {
                $user_org = $user->getUserOrganization($_SESSION['user_id']);
                if (!$user_org || $device_info['organization_id'] != $user_org['id']) {
                    throw new Exception('Нет прав на редактирование этого устройства');
                }
            } elseif ($_SESSION['role'] == ROLE_DEALER) {
                $user_org = $user->getUserOrganization($_SESSION['user_id']);
                $device_org = $organization->getOrganization($device_info['organization_id']);
                
                if (!$user_org || !$device_org || 
                    ($device_org['id'] != $user_org['id'] && $device_org['dealer_id'] != $user_org['id'])) {
                    throw new Exception('Нет прав на редактирование этого устройства');
                }
            }
            
            if ($_POST['device_id'] != $device_info['device_id'] && $_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может изменять ID устройства');
            }

            if ($_POST['device_type'] != $device_info['device_type'] && $_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может изменять тип устройства');
            }
            
            $device_id = $_POST['device_id'];
            $name = $_POST['name'];
            $device_type = $_POST['device_type'];
            $coordinates = $_POST['coordinates'];
            
            if ($coordinates !== '' && !preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $coordinates)) {
                throw new Exception('Некорректные координаты');
            }
            
            $organization_id = null;
            if ($_SESSION['role'] == ROLE_ADMIN && isset($_POST['organization_id'])) {
                $organization_id = $_POST['organization_id'] ? (int)$_POST['organization_id'] : null;
            }

            $humidity_count = null;
            if ($device_type == 'VP' && isset($_POST['humidity_count'])) {
                $humidity_count = max(1, min(6, (int)$_POST['humidity_count']));
            }

            if ($_SESSION['role'] == ROLE_ADMIN && isset($_POST['services'])) {
                $services = $_POST['services'];
                $forecast_enabled = isset($services['forecast']) ? 1 : 0;
                $realdata_enabled = isset($services['realdata']) ? 1 : 0;
                $analytics_enabled = isset($services['analytics']) ? 1 : 0;
                $calculations_enabled = isset($services['calculations']) ? 1 : 0;
                
                $device->updateDeviceServices($id, $forecast_enabled, $realdata_enabled, $analytics_enabled, $calculations_enabled);
            }
            
            if ($_SESSION['role'] == ROLE_ADMIN && isset($_POST['meteostation_id'])) {
                $meteostation_id = $_POST['meteostation_id'] ? (int)$_POST['meteostation_id'] : null;
                
                if ($meteostation_id) {
                    $device->linkDeviceToMeteostation($id, $meteostation_id);
                } else {
                    $device->unlinkDeviceFromMeteostation($id);
                }
            }

            $contract_start_date = $_POST['contract_start_date'] ?: null;
            $contract_end_date = $_POST['contract_end_date'] ?: null;
            
            if ($device->updateDevice($id, $device_id, $name, $device_type, $coordinates, $organization_id, $humidity_count, $contract_start_date, $contract_end_date)) {
                $response = ['success' => true, 'message' => 'Устройство обновлено'];
            } else {
                throw new Exception('Ошибка при обновлении');
            }
        }
        elseif (isset($_POST['create'])) {

            if ($_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может создавать устройства');
            }
            
            $coordinates = $_POST['coordinates'];
            if ($coordinates !== '' && !preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $coordinates)) {
                throw new Exception('Некорректные координаты');
            }
            
            $organization_id = null;
            
            if ($_SESSION['role'] == ROLE_ADMIN) {
                $organization_id = isset($_POST['organization_id']) ? ($_POST['organization_id'] ? (int)$_POST['organization_id'] : null) : null;
            } elseif ($_SESSION['role'] == ROLE_DEALER) {
                $user_org = $user->getUserOrganization($_SESSION['user_id']);
                $organization_id = $user_org ? $user_org['id'] : null;
            } elseif ($_SESSION['role'] == ROLE_USER) {
                $user_org = $user->getUserOrganization($_SESSION['user_id']);
                $organization_id = $user_org ? $user_org['id'] : null;
            }
            
            $humidity_count = null;
            if ($_POST['device_type'] == 'VP' && isset($_POST['humidity_count'])) {
                $humidity_count = max(1, min(6, (int)$_POST['humidity_count']));
            }

            $contract_start_date = $_POST['contract_start_date'] ?: null;
            $contract_end_date = $_POST['contract_end_date'] ?: null;
            
            if ($device->createDevice($_POST['device_id'], $_POST['name'], $_POST['device_type'], $coordinates, $_SESSION['user_id'], $organization_id, $humidity_count, $contract_start_date, $contract_end_date)) {
                $response = ['success' => true, 'message' => 'Устройство создано'];
            } else {
                throw new Exception('Ошибка при создании');
            }
        }
        elseif (isset($_POST['toggle_service'])) {
            if ($_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может управлять сервисами устройств');
            }
            
            $device_id = (int)$_POST['device_id'];
            $service = $_POST['service'];
            $enabled = (bool)$_POST['enabled'];
            
            switch ($service) {
                case 'forecast':
                    $device->updateDeviceServices($device_id, $enabled, null, null, null);
                    break;
                case 'realdata':
                    $device->updateDeviceServices($device_id, null, $enabled, null, null);
                    break;
                case 'analytics':
                    $device->updateDeviceServices($device_id, null, null, $enabled, null);
                    break;
                case 'calculations':
                    $device->updateDeviceServices($device_id, null, null, null, $enabled);
                    break;
                default:
                    throw new Exception('Неизвестный тип сервиса');
            }
            
            $response = ['success' => true, 'message' => 'Статус сервиса изменен'];
        }
        elseif (isset($_POST['restore_device'])) {
            if ($_SESSION['role'] != ROLE_ADMIN) {
                throw new Exception('Только администратор может восстанавливать устройства');
            }
            
            $device_id = (int)$_POST['device_id'];
            
            if ($device->restoreDevice($device_id)) {
                $response = ['success' => true, 'message' => 'Устройство восстановлено'];
            } else {
                throw new Exception('Ошибка при восстановлении устройства');
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1;

if ($_SESSION['role'] == ROLE_ADMIN) {
    $devices = $device->getAllDevices($show_deleted);
} elseif ($_SESSION['role'] == ROLE_DEALER) {
    $user_org = $user->getUserOrganization($_SESSION['user_id']);
    if ($user_org) {
        $devices = $device->getDealerDevices($user_org['id'], $show_deleted);
    } else {
        $devices = [];
    }
} else {
    $user_org = $user->getUserOrganization($_SESSION['user_id']);
    if ($user_org) {
        $devices = $device->getOrganizationDevices($user_org['id'], $show_deleted);
    } else {
        $devices = [];
    }
}

$organizations_list = [];
if ($_SESSION['role'] == ROLE_ADMIN) {
    $organizations_list = $organization->getAllOrganizations(false);
}

$meteostations = [];
if ($_SESSION['role'] == ROLE_ADMIN) {
    $all_devices = $device->getAllDevices();
    $meteostations = array_filter($all_devices, function($d) {
        return $d['device_type'] == 'M';
    });
}

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
        
        <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
        <div class="form-group">
            <label class="form-switch">
                <input type="checkbox" id="showDeletedToggle" <?= $show_deleted ? 'checked' : '' ?>>
                <i class="form-icon"></i> Показывать только удаленные устройства
            </label>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
        <button class="btn btn-primary" onclick="openCreateModal()" style="margin-bottom: 15px;">Добавить устройство</button>
        <?php endif; ?>
        
        <table id="devicesTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Статус</th>
                    <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
                    <th>Организация</th>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                    <th>Сервисы</th>
                    <?php endif; ?>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filtered_devices = [];
                foreach ($devices as $d) {
                    if ($show_deleted && isset($d['is_deleted']) && $d['is_deleted']) {
                        $filtered_devices[] = $d;
                    } elseif (!$show_deleted && (!isset($d['is_deleted']) || !$d['is_deleted'])) {
                        $filtered_devices[] = $d;
                    }
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
                    <td>
                        <?php if (isset($d['is_blocked']) && $d['is_blocked']): ?>
                            <span class="label label-error">Заблокировано</span>
                        <?php elseif (isset($d['is_deleted']) && $d['is_deleted']): ?>
                            <span class="label label-warning">Удалено</span>
                        <?php else: ?>
                            <span class="label label-success">Активно</span>
                        <?php endif; ?>
                    </td>
                    
                    <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
                    <td><?= $device_org ? htmlspecialchars($device_org['name']) : 'Не назначена' ?></td>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                    <td>
                        <div class="service-toggles">
                            <label class="form-switch service-toggle">
                                <input type="checkbox" <?= $d['is_forecast_enabled'] ? 'checked' : '' ?> 
                                       onchange="toggleDeviceService(<?= $d['id'] ?>, 'forecast', this.checked)">
                                <i class="form-icon"></i> Прогноз
                            </label>
                            
                            <label class="form-switch service-toggle">
                                <input type="checkbox" <?= $d['is_realdata_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleDeviceService(<?= $d['id'] ?>, 'realdata', this.checked)">
                                <i class="form-icon"></i> Реальные данные
                            </label>
                            
                            <label class="form-switch service-toggle">
                                <input type="checkbox" <?= $d['is_analytics_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleDeviceService(<?= $d['id'] ?>, 'analytics', this.checked)">
                                <i class="form-icon"></i> Аналитика
                            </label>
                            
                            <label class="form-switch service-toggle">
                                <input type="checkbox" <?= $d['is_calculations_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleDeviceService(<?= $d['id'] ?>, 'calculations', this.checked)">
                                <i class="form-icon"></i> Расчеты
                            </label>
                        </div>
                        
                        <?php if ($d['device_type'] != 'M' && $linked_meteostation): ?>
                        <div class="linked-meteostation">
                            <small>Связанная метеостанция: <?= htmlspecialchars($linked_meteostation['name']) ?></small>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    
                    <td>
    <div class="action-buttons">
        <?php if (!isset($d['is_deleted']) || !$d['is_deleted']): ?>
        <button class="action-btn btn-edit btn btn-sm" 
                title="Редактировать устройство"
                onclick="openEditModal({
                    id: <?= $d['id'] ?>,
                    device_id: '<?= htmlspecialchars($d['device_id'], ENT_QUOTES) ?>',
                    name: '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>',
                    device_type: '<?= htmlspecialchars($d['device_type'], ENT_QUOTES) ?>',
                    coordinates: <?= $d['coordinates'] ? '\''.htmlspecialchars($d['coordinates'], ENT_QUOTES).'\'' : 'null' ?>,
                    organization_id: <?= $d['organization_id'] ?: 'null' ?>,
                    is_forecast_enabled: <?= $d['is_forecast_enabled'] ? 'true' : 'false' ?>,
                    is_realdata_enabled: <?= $d['is_realdata_enabled'] ? 'true' : 'false' ?>,
                    is_analytics_enabled: <?= $d['is_analytics_enabled'] ? 'true' : 'false' ?>,
                    is_calculations_enabled: <?= $d['is_calculations_enabled'] ? 'true' : 'false' ?>,
                    linked_meteostation_id: <?= $d['linked_meteostation_id'] ?: 'null' ?>,
                    humidity_count: <?= isset($d['humidity_count']) ? $d['humidity_count'] : 3 ?>,
                    contract_start_date: '<?= htmlspecialchars($d['contract_start_date'] ?? '', ENT_QUOTES) ?>',
                    contract_end_date: '<?= htmlspecialchars($d['contract_end_date'] ?? '', ENT_QUOTES) ?>'
                })">
            <i class="fas fa-edit"></i>
        </button>
        
        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
        <button class="action-btn btn-delete btn btn-sm" 
                title="Удалить устройство"
                onclick="deleteDevice(<?= $d['id'] ?>)">
            <i class="fas fa-trash"></i>
        </button>
        <button class="action-btn btn-service btn btn-sm" 
                title="Сервисное меню за последние 7 суток"
                onclick="openServiceMenu('<?= htmlspecialchars($d['device_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($d['device_type'], ENT_QUOTES) ?>')">
            <i class="fas fa-tools"></i>
        </button>
        <?php endif; ?>
        

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
        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
        <button class="action-btn btn-restore btn btn-sm" 
                title="Восстановить устройство"
                onclick="restoreDevice(<?= $d['id'] ?>)">
            <i class="fas fa-undo"></i>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="createModal">
    <div class="modal-overlay" onclick="closeCreateModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeCreateModal()"></button>
            <h3>Создать новое устройство</h3>
        </div>
        <div class="modal-body">
            <form id="createForm">
                <div class="form-group">
                    <label class="form-label" for="create_device_id">ID устройства</label>
                    <input class="form-input" type="text" id="create_device_id" name="device_id" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="create_name">Название</label>
                    <input class="form-input" type="text" id="create_name" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="create_device_type">Тип устройства</label>
                    <select class="form-select" id="create_device_type" name="device_type" onchange="toggleHumidityCountField('create')">
                        <option value="VP">Влажность почвы</option>
                        <option value="M">Метеостанция</option>
                        <option value="OTHER">Другое</option>
                    </select>
                </div>
                
                <div class="form-group" id="create_humidity_count_group">
                    <label class="form-label" for="create_humidity_count">Количество уровней влажности (1-6)</label>
                    <input class="form-input" type="number" id="create_humidity_count" name="humidity_count" min="1" max="6" value="3">
                    <small class="form-input-hint">Определяет количество уровней глубины для расчета влагозапаса (5см, 15см, 25см, 35см, 45см, 55см)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Координаты</label>
                    <div id="createMap"></div>
                    <input class="form-input" type="text" id="create_coordinates" name="coordinates" placeholder="55.7558,37.6173" style="margin-top: 10px;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_contract_start_date">Начало эксплуатации</label>
                    <input class="form-input" type="date" id="create_contract_start_date" name="contract_start_date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="create_contract_end_date">Окончание эксплуатации</label>
                    <input class="form-input" type="date" id="create_contract_end_date" name="contract_end_date">
                </div>
                
                <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                <div class="form-group">
                    <label class="form-label" for="create_organization_id">Организация</label>
                    <select class="form-select" id="create_organization_id" name="organization_id">
                        <option value="">Не назначена</option>
                        <?php foreach ($organizations_list as $org): ?>
                        <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['name']) ?> (<?= $org['type'] == 'dealer' ? 'Дилер' : 'Клиент' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Сервисы</label>
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="services[forecast]" checked>
                            <i class="form-icon"></i> Прогноз
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="services[realdata]" checked>
                            <i class="form-icon"></i> Реальные данные
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="services[analytics]">
                            <i class="form-icon"></i> Аналитика
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="services[calculations]">
                            <i class="form-icon"></i> Расчеты
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="create_meteostation_group" style="display: none;">
                    <label class="form-label" for="create_meteostation_id">Связанная метеостанция</label>
                    <select class="form-select" id="create_meteostation_id" name="meteostation_id">
                        <option value="">Нет</option>
                        <?php foreach ($meteostations as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['device_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
            <h3>Редактировать устройство</h3>
        </div>
        <div class="modal-body">
            <form id="editForm">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_device_id">ID устройства</label>
                    <input class="form-input" type="text" id="edit_device_id" name="device_id" required <?= $_SESSION['role'] != ROLE_ADMIN ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_name">Название</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_device_type">Тип устройства</label>
                    <select class="form-select" id="edit_device_type" name="device_type" <?= $_SESSION['role'] != ROLE_ADMIN ? 'disabled' : '' ?> onchange="toggleHumidityCountField('edit')">
                        <option value="VP">Влажность почвы</option>
                        <option value="M">Метеостанция</option>
                        <option value="OTHER">Другое</option>
                    </select>
                    <?php if ($_SESSION['role'] != ROLE_ADMIN): ?>
                    <input type="hidden" id="edit_device_type_hidden" name="device_type">
                    <?php endif; ?>
                </div>
                
                <div class="form-group" id="edit_humidity_count_group">
                    <label class="form-label" for="edit_humidity_count">Количество уровней влажности (1-6)</label>
                    <input class="form-input" type="number" id="edit_humidity_count" name="humidity_count" min="1" max="6" value="3">
                    <small class="form-input-hint">Определяет количество уровней глубины для расчета влагозапаса (5см, 15см, 25см, 35см, 45см, 55см)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Координаты</label>
                    <div id="editMap"></div>
                    <input class="form-input" type="text" id="edit_coordinates" name="coordinates" placeholder="55.7558,37.6173" style="margin-top: 10px;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_contract_start_date">Начало эксплуатации</label>
                    <input class="form-input" type="date" id="edit_contract_start_date" name="contract_start_date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_contract_end_date">Окончание эксплуатации</label>
                    <input class="form-input" type="date" id="edit_contract_end_date" name="contract_end_date">
                </div>
                
                <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                <div class="form-group">
                    <label class="form-label" for="edit_organization_id">Организация</label>
                    <select class="form-select" id="edit_organization_id" name="organization_id">
                        <option value="">Не назначена</option>
                        <?php foreach ($organizations_list as $org): ?>
                        <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['name']) ?> (<?= $org['type'] == 'dealer' ? 'Дилер' : 'Клиент' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Сервисы</label>
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" id="edit_forecast_enabled" name="services[forecast]">
                            <i class="form-icon"></i> Прогноз
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" id="edit_realdata_enabled" name="services[realdata]">
                            <i class="form-icon"></i> Реальные данные
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" id="edit_analytics_enabled" name="services[analytics]">
                            <i class="form-icon"></i> Аналитика
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" id="edit_calculations_enabled" name="services[calculations]">
                            <i class="form-icon"></i> Расчеты
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="edit_meteostation_group">
                    <label class="form-label" for="edit_meteostation_id">Связанная метеостанция</label>
                    <select class="form-select" id="edit_meteostation_id" name="meteostation_id">
                        <option value="">Нет</option>
                        <?php foreach ($meteostations as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['device_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitEditForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeEditModal()">Отмена</button>
        </div>
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
    
    function toggleDeviceService(deviceId, service, enabled) {
        const formData = new FormData();
        formData.append('toggle_service', '1');
        formData.append('device_id', deviceId);
        formData.append('service', service);
        formData.append('enabled', enabled ? '1' : '0');
        
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
            } else {
                showMessage('error', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
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
