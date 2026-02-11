<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Organization.php';
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/classes/Device.php';

if ($_SESSION['role'] != ROLE_ADMIN) {
    header("Location: /pages/dashboard.php?error=no_permission");
    exit();
}

$current_year = $_SESSION['year'];
$user = new User($current_year);

if (isset($_GET['year']) && !empty($_GET['year'])) {
    if($user->changeUserPeriod($_SESSION['username'], $_GET['year'])){
        $current_year = $_SESSION['year'];
        $user = new User($current_year);
    }    
}

$organization = new Organization($current_year);
$device = new Device($current_year);


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];
    
    try {
        if (isset($_POST['delete'])) {
            $org_id = (int)$_POST['organization_id'];
            
            if ($organization->deleteOrganization($org_id)) {
                $response = ['success' => true, 'message' => 'Организация удалена'];
            } else {
                throw new Exception('Ошибка при удалении организации');
            }
        } 
        elseif (isset($_POST['restore'])) {
            $org_id = (int)$_POST['organization_id'];
            
            if ($organization->restoreOrganization($org_id)) {
                $response = ['success' => true, 'message' => 'Организация восстановлена'];
            } else {
                throw new Exception('Ошибка при восстановлении организации');
            }
        }
        elseif (isset($_POST['edit'])) {
            $id = (int)$_POST['id'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $dealer_id = $_POST['dealer_id'] ? (int)$_POST['dealer_id'] : null;
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            
            $services = [
                'forecast' => isset($_POST['services']) && isset($_POST['services']['forecast']) && $_POST['services']['forecast'] === 'on',
                'realdata' => isset($_POST['services']) && isset($_POST['services']['realdata']) && $_POST['services']['realdata'] === 'on',
                'analytics' => isset($_POST['services']) && isset($_POST['services']['analytics']) && $_POST['services']['analytics'] === 'on',
                'calculations' => isset($_POST['services']) && isset($_POST['services']['calculations']) && $_POST['services']['calculations'] === 'on'
            ];
            
            if ($type == 'dealer' && $dealer_id == $id) {
                throw new Exception('Организация не может быть дилером самой себя');
            }
            
            if ($organization->updateOrganization($id, $name, $type, $dealer_id, $email, $phone, $address, $services)) {
                $devices = $device->getOrganizationDevices($id);
                foreach ($devices as $dev) {
                    $device->updateDeviceServices(
                        $dev['id'],
                        $services['forecast'] ? 1 : 0,
                        $services['realdata'] ? 1 : 0,
                        $services['analytics'] ? 1 : 0,
                        $services['calculations'] ? 1 : 0
                    );
                }
                
                $response = ['success' => true, 'message' => 'Организация обновлена'];
            } else {
                throw new Exception('Ошибка при обновлении организации');
            }
        }
        elseif (isset($_POST['create'])) {
            $name = $_POST['name'];
            $type = $_POST['type'];
            $dealer_id = $_POST['dealer_id'] ? (int)$_POST['dealer_id'] : null;
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            
            $services = [
                'forecast' => isset($_POST['services']) && isset($_POST['services']['forecast']) && $_POST['services']['forecast'] === 'on',
                'realdata' => isset($_POST['services']) && isset($_POST['services']['realdata']) && $_POST['services']['realdata'] === 'on',
                'analytics' => isset($_POST['services']) && isset($_POST['services']['analytics']) && $_POST['services']['analytics'] === 'on',
                'calculations' => isset($_POST['services']) && isset($_POST['services']['calculations']) && $_POST['services']['calculations'] === 'on'
            ];
            
            if ($organization->createOrganization($name, $type, $dealer_id, $email, $phone, $address, $_SESSION['user_id'], $services)) {
                $response = ['success' => true, 'message' => 'Организация создана'];
            } else {
                throw new Exception('Ошибка при создании организации');
            }
        }
        elseif (isset($_POST['toggle_block'])) {
            $org_id = (int)$_POST['organization_id'];
            $block = (bool)$_POST['block'];
            
            if ($organization->blockOrganization($org_id, $block)) {
                $response = ['success' => true, 'message' => $block ? 'Организация заблокирована' : 'Организация разблокирована'];
            } else {
                throw new Exception('Ошибка при изменении статуса блокировки');
            }
        }
        elseif (isset($_POST['assign_dealer'])) {
            $client_id = (int)$_POST['client_id'];
            $dealer_id = $_POST['dealer_id'] ? (int)$_POST['dealer_id'] : null;
            
            if ($dealer_id) {
                if ($organization->assignClientToDealer($client_id, $dealer_id)) {
                    $response = ['success' => true, 'message' => 'Клиент привязан к дилеру'];
                } else {
                    throw new Exception('Ошибка при привязке клиента к дилеру');
                }
            } else {
                if ($organization->removeClientFromDealer($client_id)) {
                    $response = ['success' => true, 'message' => 'Клиент отвязан от дилера'];
                } else {
                    throw new Exception('Ошибка при отвязке клиента от дилера');
                }
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}


$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1;
if ($show_deleted) {
    $organizations = $organization->getDeletedOrganizations();
    $dealers = $organization->getDeletedDealers();
} else {
    $organizations = $organization->getAllOrganizations(false);
    $dealers = $organization->getDealers(false);
}

include ROOT_PATH . '/includes/header.php';
?>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
<div class="column col-9 col-xs-12">  
    <h2>Управление организациями</h2>
    <?php displayMessages(); ?>
    
    <div class="form-group">
        <label class="form-switch">
            <input type="checkbox" id="showDeletedToggle" <?= $show_deleted ? 'checked' : '' ?>>
            <i class="form-icon"></i> Показывать только удаленные организации
        </label>
    </div>
    
   <!-- <button class="btn btn-primary" onclick="openCreateModal()" style="margin-bottom: 15px;">Добавить организацию</button>-->
    
    <ul class="tab tab-block">
        <li class="tab-item active">
            <a href="#tab-all" data-tab="all">Все организации</a>
        </li>
        <li class="tab-item">
            <a href="#tab-dealers" data-tab="dealers">Дилеры</a>
        </li>
        <li class="tab-item">
            <a href="#tab-clients" data-tab="clients">Клиенты</a>
        </li>
    </ul>
    
    <div id="tab-all" class="tab-content active">
        <table id="organizationsTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Дилер</th>
                    <th>Статус</th>
                  <!--  <th>Действия</th>-->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organizations as $org): 
                    $dealer = null;
                    if ($org['dealer_id']) {
                        $dealer = $organization->getOrganization($org['dealer_id']);
                    }
                    $status_class = $org['is_deleted'] ? 'text-error' : ($org['is_blocked'] ? 'text-warning' : 'text-success');
                    $status_text = $org['is_deleted'] ? 'Удалена' : ($org['is_blocked'] ? 'Заблокирована' : 'Активна');
                ?>
                <tr class="<?= $org['is_deleted'] ? 'bg-gray' : '' ?>">
                    <td><?= htmlspecialchars($org['name']) ?></td>
                    <td><?= $org['type'] == 'dealer' ? 'Дилер' : 'Клиент' ?></td>
                    <td><?= $dealer ? htmlspecialchars($dealer['name']) : 'Нет' ?></td>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
                    <td>
                        <div class="btn-group">
                            <?php if (!$org['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($org), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($org['type'] == 'client' && !$org['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openAssignDealerModal(<?= $org['id'] ?>, <?= $org['dealer_id'] ? $org['dealer_id'] : 'null' ?>)">
                                    <i class="fas fa-link"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($org['is_blocked']): ?>
                                <button class="btn btn-sm btn-success" onclick="toggleBlockOrganization(<?= $org['id'] ?>, false)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-warning" onclick="toggleBlockOrganization(<?= $org['id'] ?>, true)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-error" onclick="deleteOrganization(<?= $org['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" onclick="restoreOrganization(<?= $org['id'] ?>)">
                                    <i class="fas fa-undo"></i> Восстановить
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div id="tab-dealers" class="tab-content" style="display: none;">
        <table id="dealersTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Кол-во клиентов</th>
                    <th>Статус</th>
                 <!--   <th>Действия</th>-->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dealers as $dealer): 
                    $clients_count = count($organization->getDealerClients($dealer['id']));
                    $status_class = $dealer['is_deleted'] ? 'text-error' : ($dealer['is_blocked'] ? 'text-warning' : 'text-success');
                    $status_text = $dealer['is_deleted'] ? 'Удалена' : ($dealer['is_blocked'] ? 'Заблокирована' : 'Активна');
                ?>
                <tr class="<?= $dealer['is_deleted'] ? 'bg-gray' : '' ?>">
                    <td><?= htmlspecialchars($dealer['name']) ?></td>
                    <td><?= $clients_count ?></td>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
                  <!--  <td>
                        <div class="btn-group">
                            <?php if (!$dealer['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($dealer), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($dealer['is_blocked']): ?>
                                <button class="btn btn-sm btn-success" onclick="toggleBlockOrganization(<?= $dealer['id'] ?>, false)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-warning" onclick="toggleBlockOrganization(<?= $dealer['id'] ?>, true)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-error" onclick="deleteOrganization(<?= $dealer['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <button class="btn btn-sm" onclick="viewClients(<?= $dealer['id'] ?>)">
                                    <i class="fas fa-users"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" onclick="restoreOrganization(<?= $dealer['id'] ?>)">
                                    <i class="fas fa-undo"></i> Восстановить
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>-->
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div id="tab-clients" class="tab-content" style="display: none;">
        <table id="clientsTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Дилер</th>
                    <th>Статус</th>
                  <!--  <th>Действия</th>-->
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($show_deleted) {
                    $clients = $organization->getDeletedClients();
                } else {
                    $clients = $organization->getClients(null, false);
                }
                foreach ($clients as $client): 
                    $dealer = null;
                    if ($client['dealer_id']) {
                        $dealer = $organization->getOrganization($client['dealer_id']);
                    }
                    $status_class = $client['is_deleted'] ? 'text-error' : ($client['is_blocked'] ? 'text-warning' : 'text-success');
                    $status_text = $client['is_deleted'] ? 'Удалена' : ($client['is_blocked'] ? 'Заблокирована' : 'Активна');
                ?>
                <tr class="<?= $client['is_deleted'] ? 'bg-gray' : '' ?>">
                    <td><?= htmlspecialchars($client['name']) ?></td>
                    <td><?= $dealer ? htmlspecialchars($dealer['name']) : 'Нет' ?></td>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
               <!--     <td>
                        <div class="btn-group">
                            <?php if (!$client['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($client['is_blocked']): ?>
                                <button class="btn btn-sm btn-success" onclick="toggleBlockOrganization(<?= $client['id'] ?>, false)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-warning" onclick="toggleBlockOrganization(<?= $client['id'] ?>, true)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-error" onclick="deleteOrganization(<?= $client['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <button class="btn btn-sm" onclick="openAssignDealerModal(<?= $client['id'] ?>, <?= $client['dealer_id'] ? $client['dealer_id'] : 'null' ?>)">
                                    <i class="fas fa-link"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" onclick="restoreOrganization(<?= $client['id'] ?>)">
                                    <i class="fas fa-undo"></i> Восстановить
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>-->
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal" id="createModal">
    <div class="modal-overlay" onclick="closeCreateModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeCreateModal()"></button>
            <h3>Создать новую организацию</h3>
        </div>
        <div class="modal-body">
            <form id="createForm">
                <div class="form-group">
                    <label class="form-label" for="create_name">Название</label>
                    <input class="form-input" type="text" id="create_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="create_type">Тип организации</label>
                    <select class="form-select" id="create_type" name="type" onchange="toggleDealerSelect()">
                        <option value="dealer">Дилер</option>
                        <option value="client">Клиент</option>
                    </select>
                </div>
                
                <div class="form-group" id="create_dealer_group" style="display: none;">
                    <label class="form-label" for="create_dealer_id">Дилер</label>
                    <select class="form-select" id="create_dealer_id" name="dealer_id">
                        <option value="">Нет (работает напрямую)</option>
                        <?php foreach ($dealers as $d): 
                            if ($d['is_deleted'] || $d['is_blocked']) continue;
                        ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="create_email">Email</label>
                    <input class="form-input" type="email" id="create_email" name="email">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="create_phone">Телефон</label>
                    <input class="form-input" type="text" id="create_phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="create_address">Адрес</label>
                    <textarea class="form-input" id="create_address" name="address" rows="3"></textarea>
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
            <h3>Редактировать организацию</h3>
        </div>
        <div class="modal-body">
            <form id="editForm">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_name">Название</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_type">Тип организации</label>
                    <select class="form-select" id="edit_type" name="type" onchange="toggleEditDealerSelect()">
                        <option value="dealer">Дилер</option>
                        <option value="client">Клиент</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit_dealer_group" style="display: none;">
                    <label class="form-label" for="edit_dealer_id">Дилер</label>
                    <select class="form-select" id="edit_dealer_id" name="dealer_id">
                        <option value="">Нет (работает напрямую)</option>
                        <?php foreach ($dealers as $d): 
                            if ($d['is_deleted'] || $d['is_blocked']) continue;
                        ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_email">Email</label>
                    <input class="form-input" type="email" id="edit_email" name="email">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_phone">Телефон</label>
                    <input class="form-input" type="text" id="edit_phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_address">Адрес</label>
                    <textarea class="form-input" id="edit_address" name="address" rows="3"></textarea>
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
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitEditForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeEditModal()">Отмена</button>
        </div>
    </div>
</div>

<div class="modal" id="assignDealerModal">
    <div class="modal-overlay" onclick="closeAssignDealerModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeAssignDealerModal()"></button>
            <h3>Назначить дилера</h3>
        </div>
        <div class="modal-body">
            <form id="assignDealerForm">
                <input type="hidden" id="assign_client_id" name="client_id">
                
                <div class="form-group">
                    <label class="form-label" for="assign_dealer_id">Дилер</label>
                    <select class="form-select" id="assign_dealer_id" name="dealer_id">
                        <option value="">Нет (работает напрямую)</option>
                        <?php foreach ($dealers as $d): 
                            if ($d['is_deleted'] || $d['is_blocked']) continue;
                        ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitAssignDealerForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeAssignDealerModal()">Отмена</button>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.tab-item a').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            document.querySelectorAll('.tab-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            this.parentElement.classList.add('active');
            
            const tabId = this.getAttribute('data-tab');
            const tabContent = document.getElementById('tab-' + tabId);
            tabContent.classList.add('active');
            tabContent.style.display = 'block';
            
            if (tabId === 'all' && $.fn.DataTable.isDataTable('#organizationsTable')) {
                $('#organizationsTable').DataTable().columns.adjust();
            } else if (tabId === 'dealers' && $.fn.DataTable.isDataTable('#dealersTable')) {
                $('#dealersTable').DataTable().columns.adjust();
            } else if (tabId === 'clients' && $.fn.DataTable.isDataTable('#clientsTable')) {
                $('#clientsTable').DataTable().columns.adjust();
            }
        });
    });
    
    document.getElementById('showDeletedToggle').addEventListener('change', function() {
        window.location.href = 'organizations.php?show_deleted=' + (this.checked ? '1' : '0');
    });
    
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        toggleDealerSelect();
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }
    
    function toggleDealerSelect() {
        const typeSelect = document.getElementById('create_type');
        const dealerGroup = document.getElementById('create_dealer_group');
        
        if (typeSelect.value === 'client') {
            dealerGroup.style.display = 'block';
        } else {
            dealerGroup.style.display = 'none';
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
    
    function openEditModal(organization) {
        if (typeof organization === 'string') {
            try {
                organization = JSON.parse(organization);
            } catch (e) {
                console.error('Ошибка при разборе JSON:', e);
                showMessage('error', 'Ошибка при открытии формы редактирования');
                return;
            }
        }
        
        document.getElementById('edit_id').value = organization.id;
        document.getElementById('edit_name').value = organization.name;
        document.getElementById('edit_type').value = organization.type;
        document.getElementById('edit_email').value = organization.email || '';
        document.getElementById('edit_phone').value = organization.phone || '';
        document.getElementById('edit_address').value = organization.address || '';
        
        document.getElementById('edit_forecast_enabled').checked = organization.is_forecast_enabled == 1;
        document.getElementById('edit_realdata_enabled').checked = organization.is_realdata_enabled == 1;
        document.getElementById('edit_analytics_enabled').checked = organization.is_analytics_enabled == 1;
        document.getElementById('edit_calculations_enabled').checked = organization.is_calculations_enabled == 1;
        
        if (organization.type === 'client') {
            document.getElementById('edit_dealer_group').style.display = 'block';
            document.getElementById('edit_dealer_id').value = organization.dealer_id || '';
        } else {
            document.getElementById('edit_dealer_group').style.display = 'none';
        }
        
        document.getElementById('editModal').classList.add('active');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }
    
    function toggleEditDealerSelect() {
        const typeSelect = document.getElementById('edit_type');
        const dealerGroup = document.getElementById('edit_dealer_group');
        
        if (typeSelect.value === 'client') {
            dealerGroup.style.display = 'block';
        } else {
            dealerGroup.style.display = 'none';
            document.getElementById('edit_dealer_id').value = '';
        }
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
    
    function openAssignDealerModal(clientId, dealerId) {
        document.getElementById('assign_client_id').value = clientId;
        document.getElementById('assign_dealer_id').value = dealerId || '';
        document.getElementById('assignDealerModal').classList.add('active');
    }
    
    function closeAssignDealerModal() {
        document.getElementById('assignDealerModal').classList.remove('active');
    }
    
    function submitAssignDealerForm() {
        const form = document.getElementById('assignDealerForm');
        const formData = new FormData(form);
        formData.append('assign_dealer', '1');
        
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
                closeAssignDealerModal();
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
    
    function deleteOrganization(organizationId) {
        if (!confirm('Вы уверены, что хотите удалить эту организацию? Все пользователи будут заблокированы, а клиенты освобождены от привязки.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('organization_id', organizationId);
        
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
    
    function restoreOrganization(organizationId) {
        if (!confirm('Вы уверены, что хотите восстановить эту организацию?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('restore', '1');
        formData.append('organization_id', organizationId);
        
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
    
    function toggleBlockOrganization(organizationId, block) {
        const action = block ? 'заблокировать' : 'разблокировать';
        if (!confirm(`Вы уверены, что хотите ${action} эту организацию?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('toggle_block', '1');
        formData.append('organization_id', organizationId);
        formData.append('block', block ? '1' : '0');
        
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
    
    function viewClients(dealerId) {
        window.location.href = 'dealer_clients.php?dealer_id=' + dealerId;
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
    
    $(document).ready(function() {
        $('#organizationsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]]
        });
        
        $('#dealersTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]]
        });
        
        $('#clientsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]]
        });
    });
</script>
