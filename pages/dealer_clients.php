<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Organization.php';
require_once ROOT_PATH . '/classes/User.php';

if ($_SESSION['role'] != ROLE_ADMIN) {
    header("Location: /pages/dashboard.php?error=no_permission");
    exit();
}

if (!isset($_GET['dealer_id']) || !is_numeric($_GET['dealer_id'])) {
    header("Location: /pages/organizations.php?error=invalid_dealer");
    exit();
}

$dealer_id = (int)$_GET['dealer_id'];
$organization = new Organization();
$user = new User();

$dealer = $organization->getOrganization($dealer_id);
if (!$dealer || $dealer['type'] != 'dealer') {
    header("Location: /pages/organizations.php?error=invalid_dealer");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];
    
    try {
        if (isset($_POST['unlink_client'])) {
            $client_id = (int)$_POST['client_id'];
            
            if ($organization->removeClientFromDealer($client_id)) {
                $response = ['success' => true, 'message' => 'Клиент отвязан от дилера'];
            } else {
                throw new Exception('Ошибка при отвязке клиента от дилера');
            }
        }
        elseif (isset($_POST['link_client'])) {
            $client_id = (int)$_POST['client_id'];
            
            if ($organization->assignClientToDealer($client_id, $dealer_id)) {
                $response = ['success' => true, 'message' => 'Клиент привязан к дилеру'];
            } else {
                throw new Exception('Ошибка при привязке клиента к дилеру');
            }
        }
        elseif (isset($_POST['create_client'])) {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            
            if ($organization->createOrganization($name, 'client', $dealer_id, $email, $phone, $address, $_SESSION['user_id'])) {
                $response = ['success' => true, 'message' => 'Клиент создан и привязан к дилеру'];
            } else {
                throw new Exception('Ошибка при создании клиента');
            }
        }
        elseif (isset($_POST['toggle_block'])) {
            $client_id = (int)$_POST['client_id'];
            $block = (bool)$_POST['block'];
            
            if ($organization->blockOrganization($client_id, $block)) {
                $response = ['success' => true, 'message' => $block ? 'Клиент заблокирован' : 'Клиент разблокирован'];
            } else {
                throw new Exception('Ошибка при изменении статуса блокировки');
            }
        }
        elseif (isset($_POST['delete_client'])) {
            $client_id = (int)$_POST['client_id'];
            
            if ($organization->deleteOrganization($client_id)) {
                $response = ['success' => true, 'message' => 'Клиент удален'];
            } else {
                throw new Exception('Ошибка при удалении клиента');
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1;
$clients = $organization->getDealerClients($dealer_id, $show_deleted);

$free_clients = $organization->getFreeClients($show_deleted);

include ROOT_PATH . '/includes/header.php';
?>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
    <div class="column col-9 col-xs-12">  
        <h3>Клиенты дилера: <?= htmlspecialchars($dealer['name']) ?></h3>
        
        <button class="btn btn-link" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i> Назад к организациям
        </button>
        
        <?php displayMessages(); ?>
        
        <div class="form-group">
            <label class="form-switch">
                <input type="checkbox" id="showDeletedToggle" <?= $show_deleted ? 'checked' : '' ?>>
                <i class="form-icon"></i> Показать удаленных клиентов
            </label>
        </div>
        
        <div class="btn-group btn-group-block" style="margin-bottom: 15px;">
            <button class="btn btn-primary" onclick="openCreateClientModal()">
                <i class="fas fa-plus"></i> Создать клиента
            </button>
            <button class="btn btn-primary" onclick="openLinkClientModal()">
                <i class="fas fa-link"></i> Привязать клиента
            </button>
        </div>
        
        <div class="panel">
            <div class="panel-header">
                <h4>Информация о дилере</h4>
            </div>
            <div class="panel-body">
                <div class="columns">
                    <div class="column col-6">
                        <p><strong>Название:</strong> <?= htmlspecialchars($dealer['name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($dealer['email'] ?? 'Не указан') ?></p>
                    </div>
                    <div class="column col-6">
                        <p><strong>Телефон:</strong> <?= htmlspecialchars($dealer['phone'] ?? 'Не указан') ?></p>
                        <p><strong>Статус:</strong> 
                            <span class="<?= $dealer['is_deleted'] ? 'text-error' : ($dealer['is_blocked'] ? 'text-warning' : 'text-success') ?>">
                                <?= $dealer['is_deleted'] ? 'Удален' : ($dealer['is_blocked'] ? 'Заблокирован' : 'Активен') ?>
                            </span>
                        </p>
                    </div>
                </div>
                <?php if (!empty($dealer['address'])): ?>
                <p><strong>Адрес:</strong> <?= htmlspecialchars($dealer['address']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <h4>Клиенты дилера</h4>
        
        <?php if (empty($clients)): ?>
        <div class="empty">
            <div class="empty-icon">
                <i class="fas fa-users" style="font-size: 3rem;"></i>
            </div>
            <p class="empty-title h5">У этого дилера нет клиентов</p>
            <p class="empty-subtitle">Вы можете создать нового клиента или привязать существующего.</p>
            <div class="empty-action">
                <button class="btn btn-primary" onclick="openCreateClientModal()">Создать клиента</button>
                <button class="btn" onclick="openLinkClientModal()">Привязать клиента</button>
            </div>
        </div>
        <?php else: ?>
        <table id="clientsTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): 
                    $status_class = $client['is_deleted'] ? 'text-error' : ($client['is_blocked'] ? 'text-warning' : 'text-success');
                    $status_text = $client['is_deleted'] ? 'Удален' : ($client['is_blocked'] ? 'Заблокирован' : 'Активен');
                ?>
                <tr class="<?= $client['is_deleted'] ? 'bg-gray' : '' ?>">
                    <td><?= htmlspecialchars($client['name']) ?></td>
                    <td><?= htmlspecialchars($client['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($client['phone'] ?? '') ?></td>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
                    <td>
                        <div class="btn-group">
                            <?php if (!$client['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openEditClientModal(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($client['is_blocked']): ?>
                                <button class="btn btn-sm btn-success" onclick="toggleBlockClient(<?= $client['id'] ?>, false)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-warning" onclick="toggleBlockClient(<?= $client['id'] ?>, true)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-error" onclick="deleteClient(<?= $client['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <button class="btn btn-sm" onclick="unlinkClient(<?= $client['id'] ?>)">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-gray">Недоступно</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="createClientModal">
    <div class="modal-overlay" onclick="closeCreateClientModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeCreateClientModal()"></button>
            <h3>Создать нового клиента</h3>
        </div>
        <div class="modal-body">
            <form id="createClientForm">
                <div class="form-group">
                    <label class="form-label" for="create_name">Название</label>
                    <input class="form-input" type="text" id="create_name" name="name" required>
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
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitCreateClientForm()">Создать</button>
            <button class="btn btn-link" onclick="closeCreateClientModal()">Отмена</button>
        </div>
    </div>
</div>

<div class="modal" id="linkClientModal">
    <div class="modal-overlay" onclick="closeLinkClientModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeLinkClientModal()"></button>
            <h3>Привязать существующего клиента</h3>
        </div>
        <div class="modal-body">
            <form id="linkClientForm">
                <div class="form-group">
                    <label class="form-label" for="link_client_id">Выберите клиента</label>
                    <select class="form-select" id="link_client_id" name="client_id" required>
                        <option value="">Выберите клиента...</option>
                        <?php foreach ($free_clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitLinkClientForm()">Привязать</button>
            <button class="btn btn-link" onclick="closeLinkClientModal()">Отмена</button>
        </div>
    </div>
</div>

<div class="modal" id="editClientModal">
    <div class="modal-overlay" onclick="closeEditClientModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closeEditClientModal()"></button>
            <h3>Редактировать клиента</h3>
        </div>
        <div class="modal-body">
            <form id="editClientForm">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_name">Название</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
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
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitEditClientForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeEditClientModal()">Отмена</button>
        </div>
    </div>
</div>

<script>
    document.getElementById('showDeletedToggle').addEventListener('change', function() {
        window.location.href = 'dealer_clients.php?dealer_id=<?= $dealer_id ?>&show_deleted=' + (this.checked ? '1' : '0');
    });
    
    function openCreateClientModal() {
        document.getElementById('createClientModal').classList.add('active');
    }
    
    function closeCreateClientModal() {
        document.getElementById('createClientModal').classList.remove('active');
    }
    
    function submitCreateClientForm() {
        const form = document.getElementById('createClientForm');
        const formData = new FormData(form);
        formData.append('create_client', '1');
        
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
                closeCreateClientModal();
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
    
    function openLinkClientModal() {
        document.getElementById('linkClientModal').classList.add('active');
    }
    
    function closeLinkClientModal() {
        document.getElementById('linkClientModal').classList.remove('active');
    }
    
    function submitLinkClientForm() {
        const form = document.getElementById('linkClientForm');
        const formData = new FormData(form);
        formData.append('link_client', '1');
        
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
                closeLinkClientModal();
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
    
    function openEditClientModal(client) {
        if (typeof client === 'string') {
            try {
                client = JSON.parse(client);
            } catch (e) {
                console.error('Ошибка при разборе JSON:', e);
                showMessage('error', 'Ошибка при открытии формы редактирования');
                return;
            }
        }
        
        document.getElementById('edit_id').value = client.id;
        document.getElementById('edit_name').value = client.name;
        document.getElementById('edit_email').value = client.email || '';
        document.getElementById('edit_phone').value = client.phone || '';
        document.getElementById('edit_address').value = client.address || '';
        
        document.getElementById('editClientModal').classList.add('active');
    }
    
    function closeEditClientModal() {
        document.getElementById('editClientModal').classList.remove('active');
    }
    
    function submitEditClientForm() {
        const form = document.getElementById('editClientForm');
        const formData = new FormData(form);
        formData.append('edit', '1');
        
        fetch('/pages/organizations.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEditClientModal();
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
    
    function unlinkClient(clientId) {
        if (!confirm('Вы уверены, что хотите отвязать этого клиента от дилера?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('unlink_client', '1');
        formData.append('client_id', clientId);
        
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
    
    function toggleBlockClient(clientId, block) {
        const action = block ? 'заблокировать' : 'разблокировать';
        if (!confirm(`Вы уверены, что хотите ${action} этого клиента?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('toggle_block', '1');
        formData.append('client_id', clientId);
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
    
    function deleteClient(clientId) {
        if (!confirm('Вы уверены, что хотите удалить этого клиента? Все пользователи будут заблокированы.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('delete_client', '1');
        formData.append('client_id', clientId);
        
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
        if ($('#clientsTable').length) {
            $('#clientsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
                },
                "pageLength": 10,
                "order": [[0, "asc"]]
            });
        }
    });
</script>
