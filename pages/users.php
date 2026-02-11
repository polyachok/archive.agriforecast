<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/classes/Organization.php';

$current_year = $_SESSION['year'];

$user = new User($current_year);
if (isset($_GET['year']) && !empty($_GET['year'])) {
    if($user->changeUserPeriod($_SESSION['username'], $_GET['year'])){
        $current_year = $_SESSION['year'];
        $user = new User($current_year);
    }    
}
$organization = new Organization($current_year);
$current_role = $_SESSION['role'];

if ($current_role > ROLE_DEALER) {
    header("Location: /pages/dashboard.php?error=no_permission");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];
    
    try {
        if (isset($_POST['delete'])) {
            if ($_POST['user_id'] == 1) {
                throw new Exception('Нельзя удалить глобального администратора');
            }

            if ($_SESSION['role'] == ROLE_DEALER) {
                throw new Exception('Дилеры не могут удалять пользователей');
            }
            
            if ($_SESSION['user_id'] != 1) {
                $user_to_delete = $user->getUser($_POST['user_id']);
                if ($user_to_delete['role'] == ROLE_ADMIN) {
                    throw new Exception('Только глобальный администратор может удалять других администраторов');
                }
            }
            
            $user_to_delete = $user->getUser($_POST['user_id']);
            
            if ($current_role === ROLE_DEALER) {
                $user_org = $user->getUserOrganization($user_to_delete['id']);
                $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
                
                if (!$user_org || !$dealer_org || $user_org['dealer_id'] != $dealer_org['id']) {
                    throw new Exception('Нет прав на удаление');
                }
            }
            
            if ($user->deleteUser($_POST['user_id'])) {
                $response = ['success' => true, 'message' => 'Пользователь удален'];
            } else {
                throw new Exception('Ошибка при удалении');
            }
        } 
        elseif (isset($_POST['restore'])) {
            if ($user->restoreUser($_POST['user_id'])) {
                $response = ['success' => true, 'message' => 'Пользователь восстановлен'];
            } else {
                throw new Exception('Ошибка при восстановлении');
            }
        }
        elseif (isset($_POST['toggle_block'])) {
            $user_id = (int)$_POST['user_id'];
            $block = (bool)$_POST['block'];
            
            if ($user_id == 1) {
                throw new Exception('Нельзя заблокировать глобального администратора');
            }
            
            if ($_SESSION['role'] == ROLE_DEALER) {
                throw new Exception('Дилеры не могут блокировать/разблокировать пользователей');
            }
            
            if ($_SESSION['user_id'] != 1) {
                $user_to_block = $user->getUser($user_id);
                if ($user_to_block['role'] == ROLE_ADMIN) {
                    throw new Exception('Только глобальный администратор может блокировать/разблокировать других администраторов');
                }
            }
            
            if ($current_role === ROLE_DEALER) {
                $user_org = $user->getUserOrganization($user_id);
                $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
                
                if (!$user_org || !$dealer_org || $user_org['dealer_id'] != $dealer_org['id']) {
                    throw new Exception('Нет прав на блокировку');
                }
            }
            
            if ($user->blockUser($user_id, $block)) {
                $response = ['success' => true, 'message' => $block ? 'Пользователь заблокирован' : 'Пользователь разблокирован'];
            } else {
                throw new Exception('Ошибка при изменении статуса блокировки');
            }
        }
        elseif (isset($_POST['edit'])) {
            $user_to_edit = $user->getUser($_POST['user_id']);

            if ($_POST['user_id'] == 1 && $_SESSION['user_id'] != 1) {
                throw new Exception('Нельзя редактировать глобального администратора');
            }
            
            if ($_SESSION['role'] == ROLE_DEALER) {
                throw new Exception('Дилеры могут только менять пароли пользователей');
            }
            
            if ($_SESSION['user_id'] != 1 && $user_to_edit['role'] == ROLE_ADMIN) {
                throw new Exception('Только глобальный администратор может редактировать других администраторов');
            }
            
            if ($current_role === ROLE_DEALER) {
                $user_org = $user->getUserOrganization($user_to_edit['id']);
                $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
                
                if (!$user_org || !$dealer_org || $user_org['dealer_id'] != $dealer_org['id']) {
                    throw new Exception('Нет прав на редактирование');
                }
            }
            
            if ($user_to_edit['role'] == ROLE_ADMIN && $_POST['role'] != ROLE_ADMIN) {
                if ($_SESSION['user_id'] != 1) {
                    throw new Exception('Только глобальный администратор может понижать других администраторов');
                }
                
                if ($_POST['user_id'] == 1) {
                    throw new Exception('Глобальный администратор не может понизить свою роль');
                }
            }
            
            if ($_POST['role'] == ROLE_ADMIN && $current_role !== ROLE_ADMIN) {
                throw new Exception('Только администратор может назначать роль администратора');
            }
            
            $organization_id = isset($_POST['organization_id']) ? ($_POST['organization_id'] ? (int)$_POST['organization_id'] : null) : $user_to_edit['organization_id'];
            $name = $_POST['name'] ?? null;
            
            if ($user->updateUser($_POST['user_id'], $_POST['username'], $_POST['email'], $_POST['role'], $organization_id, $name)) {
                $response = ['success' => true, 'message' => 'Пользователь обновлен'];
            } else {
                throw new Exception('Ошибка при обновлении');
            }
        }
        elseif (isset($_POST['change_password'])) {

            if ($current_role === ROLE_DEALER) {
                $user_to_change = $user->getUser($_POST['user_id']);
                $user_org = $user->getUserOrganization($user_to_change['id']);
                $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
                
                if (!$user_org || !$dealer_org || $user_org['dealer_id'] != $dealer_org['id']) {
                    throw new Exception('Нет прав на смену пароля');
                }
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception('Пароли не совпадают');
            }
            
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $user->getConnection()->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $_POST['user_id']])) {
                $response = ['success' => true, 'message' => 'Пароль изменен'];
            } else {
                throw new Exception('Ошибка при смене пароля');
            }
        }
        elseif (isset($_POST['create'])) {
            $role = (int)$_POST['role'];

            if ($_SESSION['role'] == ROLE_DEALER) {
                throw new Exception('Дилеры не могут создавать пользователей');
            }
            
            if ($role === ROLE_ADMIN && $current_role !== ROLE_ADMIN) {
                throw new Exception('Только администратор может создавать администраторов');
            }
            
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? null;
            $organization_id = isset($_POST['organization_id']) ? ($_POST['organization_id'] ? (int)$_POST['organization_id'] : null) : null;
            
            if ($current_role === ROLE_DEALER && $organization_id) {
                $org = $organization->getOrganization($organization_id);
                $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
                
                if (!$org || !$dealer_org || $org['dealer_id'] != $dealer_org['id']) {
                    throw new Exception('Нет прав на создание пользователя в этой организации');
                }
            }
            
            if (!preg_match('/^[A-Za-z0-9!@#$%^&*()_+\-=\[\]{};\'":\\\\|,.<>\/?]+$/', $username)) {
                throw new Exception('Логин может содержать только латинские буквы и цифры');
            }

            if (!preg_match('/^[A-Za-z0-9!@#$%^&*()_+\-=\[\]{};\'":\\\\|,.<>\/?]+$/', $password)) {
                throw new Exception('Пароль может содержать только латинские буквы, цифры и символы');
            }
            
            try {
                if ($user->register($username, $password, $email, $role, $organization_id, $_SESSION['user_id'], $name)) {
                    $response = ['success' => true, 'message' => 'Пользователь создан'];
                } else {
                    throw new Exception('Ошибка при создании пользователя');
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 && strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                    if (strpos($e->getMessage(), "'username'") !== false) {
                        throw new Exception('Пользователь с таким логином уже существует');
                    } elseif (strpos($e->getMessage(), "'email'") !== false) {
                        throw new Exception('Пользователь с таким email уже существует');
                    } else {
                        throw new Exception('Пользователь с такими данными уже существует');
                    }
                }
                throw $e;
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1;

if ($current_role === ROLE_ADMIN) {
    if ($show_deleted) {
        $users = $user->getDeletedUsers();
    } else {
        $users = $user->getAllUsers(false);
    }
    $show_creator_column = true;
} else {
    $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
    if ($dealer_org) {
        if ($show_deleted) {

            $dealer_users = $user->getDeletedUsersByOrganization($dealer_org['id']);
            $client_orgs = $organization->getDealerClients($dealer_org['id'], false);
            
            $users = $dealer_users;
            foreach ($client_orgs as $client_org) {
                $client_users = $user->getDeletedUsersByOrganization($client_org['id']);
                $users = array_merge($users, $client_users);
            }
        } else {
            $dealer_users = $user->getUsersByOrganization($dealer_org['id'], false);
            $client_orgs = $organization->getDealerClients($dealer_org['id'], false);
            
            $users = $dealer_users;
            foreach ($client_orgs as $client_org) {
                $client_users = $user->getUsersByOrganization($client_org['id'], false);
                $users = array_merge($users, $client_users);
            }
        }
    } else {
        if ($show_deleted) {
            $users = $user->getDeletedUsersByCreator($_SESSION['user_id']);
        } else {
            $users = $user->getUsersByCreator($_SESSION['user_id'], false);
        }
    }
    $show_creator_column = false;
}

if ($current_role === ROLE_ADMIN) {
    $organizations_list = $organization->getAllOrganizations(false);
} else {
    $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
    if ($dealer_org) {
        $dealer_clients = $organization->getDealerClients($dealer_org['id'], false);
        $organizations_list = array_merge([$dealer_org], $dealer_clients);
    } else {
        $organizations_list = [];
    }
}

include ROOT_PATH . '/includes/header.php';
?>
<style>
    .deleted-user {
        background-color: #ffebee;
    }
    .blocked-user {
        background-color: #fff8e1;
    }
</style>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
    <div class="column col-9 col-xs-12">  
        <?php displayMessages(); ?>
        <h2><?= $current_role === ROLE_ADMIN ? 'Все пользователи' : ($current_role === ROLE_DEALER ? 'Мои клиенты' : 'Пользователи') ?></h2>
        
        <div class="form-group">
            <label class="form-switch">
                <input type="checkbox" id="showDeletedToggle" <?= $show_deleted ? 'checked' : '' ?>>
                <i class="form-icon"></i> Показывать только удаленных пользователей
            </label>
        </div>
        
       <!-- <button class="btn btn-primary" onclick="openCreateModal()" style="margin-bottom: 15px;">Добавить пользователя</button>-->
        
        <table id="usersTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Организация</th>
                    <th>Статус</th>
                   <!-- <th>Действия</th>-->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $roleMap = [
                        1 => 'Администратор',
                        2 => 'Дилер',
                        3 => 'Пользователь'
                    ];
                    
                    $roleText = $roleMap[$u['role']] ?? 'Неизвестная роль';
                    $user_org = null;
                    $org_name = 'Нет';
                    
                    if ($u['organization_id']) {
                        $user_org = $organization->getOrganization($u['organization_id']);
                        if ($user_org) {
                            $org_name = htmlspecialchars($user_org['name']);
                            if ($user_org['is_deleted']) {
                                $org_name .= ' (удалена)';
                            }
                        }
                    }
                    
                    $row_class = '';
                    $status_text = 'Активен';
                    $status_class = 'text-success';
                    
                    if ($u['is_deleted']) {
                        $row_class = 'deleted-user';
                        $status_text = 'Удален';
                        $status_class = 'text-error';
                    } elseif ($u['is_blocked']) {
                        $row_class = 'blocked-user';
                        $status_text = 'Заблокирован';
                        $status_class = 'text-warning';
                    }
                ?>
                <tr class="<?= $row_class ?>">
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
                    <td><?= $roleText ?></td>
                    <td class="<?= $user_org && $user_org['is_deleted'] ? 'text-error' : ($user_org && $user_org['is_blocked'] ? 'text-warning' : '') ?>">
                        <?= htmlspecialchars($user_org ? $user_org['name'] : 'Нет') ?>
                    </td>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
                   <!-- <td>
                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                        <span class="text-gray">Недоступно</span>
                    <?php elseif ($_SESSION['user_id'] == 1): ?>
                        
                        <div class="btn-group">
                            <?php if (!$u['is_deleted']): ?>
                                <button class="btn btn-sm" onclick="openEditModal({
                                    id: <?= $u['id'] ?>,
                                    username: '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>',
                                    name: '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>',
                                    email: '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>',
                                    role: <?= $u['role'] ?>,
                                    organization_id: <?= $u['organization_id'] ? $u['organization_id'] : 'null' ?>
                                })">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button class="btn btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>)">
                                    <i class="fas fa-key"></i>
                                </button>
                                
                                <?php if ($u['is_blocked']): ?>
                                <button class="btn btn-sm btn-success" onclick="toggleBlockUser(<?= $u['id'] ?>, false)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-warning" onclick="toggleBlockUser(<?= $u['id'] ?>, true)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-error" onclick="deleteUser(<?= $u['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" onclick="restoreUser(<?= $u['id'] ?>)">
                                    <i class="fas fa-undo"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($_SESSION['role'] == ROLE_ADMIN): ?>
                        <?php if ($u['id'] == 1): ?>
                            <div class="btn-group">
                                <button class="btn btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>)">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        <?php elseif ($u['role'] == ROLE_ADMIN && $u['id'] != $_SESSION['user_id']): ?>
                            <div class="btn-group">
                                <button class="btn btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>)">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="btn-group">
                                <?php if (!$u['is_deleted']): ?>
                                    <button class="btn btn-sm" onclick="openEditModal({
                                        id: <?= $u['id'] ?>,
                                        username: '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>',
                                        name: '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>',
                                        email: '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>',
                                        role: <?= $u['role'] ?>,
                                        organization_id: <?= $u['organization_id'] ? $u['organization_id'] : 'null' ?>
                                    })">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    
                                    <?php if ($u['is_blocked']): ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleBlockUser(<?= $u['id'] ?>, false)">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-warning" onclick="toggleBlockUser(<?= $u['id'] ?>, true)">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-error" onclick="deleteUser(<?= $u['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="restoreUser(<?= $u['id'] ?>)">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($_SESSION['role'] == ROLE_DEALER): ?>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span class="text-gray">Недоступно</span>
                        <?php elseif ($u['role'] <= ROLE_ADMIN): ?>
                            <span class="text-gray">Недоступно</span>
                        <?php else: ?>
                            <div class="btn-group">
                                <button class="btn btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>)">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-gray">Недоступно</span>
                    <?php endif; ?>
                    </td>-->
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
            <h3>Создать нового пользователя</h3>
        </div>
        <div class="modal-body">
            <form id="createForm" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Логин</label>
                    <input class="form-input" type="text" name="username" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Имя</label>
                    <input class="form-input" type="text" name="name" id="name">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-input" type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <input class="form-input" type="password" name="password" required autocomplete="new-password">
                </div>
                
                <?php if ($current_role === ROLE_ADMIN): ?>
                <div class="form-group">
                    <label class="form-label" for="role">Роль</label>
                    <select class="form-select" name="role" id="role" onchange="toggleOrganizationSelect()">
                        <option value="<?= ROLE_ADMIN ?>">Администратор</option>
                        <option value="<?= ROLE_DEALER ?>">Дилер</option>
                        <option value="<?= ROLE_USER ?>">Пользователь</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="role" value="<?= ROLE_USER ?>">
                <?php endif; ?>

                <div class="form-group" id="organizationGroup">
                    <label class="form-label" for="organization_id">Организация</label>
                    <select class="form-select" name="organization_id" id="organization_id">
                        <option value="">Нет</option>
                        <?php foreach ($organizations_list as $org): ?>
                        <option value="<?= $org['id'] ?>" data-type="<?= $org['type'] ?>">
                            <?= htmlspecialchars($org['name']) ?> (<?= $org['type'] == 'dealer' ? 'Дилер' : 'Клиент' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
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
            <h3>Редактировать пользователя</h3>
        </div>
        <div class="modal-body">
            <form id="editForm" method="POST">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="form-group">
                    <label class="form-label" for="editUsername">Логин</label>
                    <input class="form-input" type="text" name="username" id="editUsername" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editName">Имя</label>
                    <input class="form-input" type="text" name="name" id="editName">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="editEmail">Email</label>
                    <input class="form-input" type="email" name="email" id="editEmail" required>
                </div>
                
                <?php if ($current_role === ROLE_ADMIN): ?>
                <div class="form-group">
                    <label class="form-label" for="editRole">Роль</label>
                    <select class="form-select" name="role" id="editRole" onchange="toggleEditOrganizationSelect()">
                        <option value="<?= ROLE_ADMIN ?>">Администратор</option>
                        <option value="<?= ROLE_DEALER ?>">Дилер</option>
                        <option value="<?= ROLE_USER ?>">Пользователь</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="role" value="<?= ROLE_USER ?>">
                <?php endif; ?>

                <div class="form-group" id="editOrganizationGroup">
                    <label class="form-label" for="editOrganizationId">Организация</label>
                    <select class="form-select" name="organization_id" id="editOrganizationId">
                        <option value="">Нет</option>
                        <?php foreach ($organizations_list as $org): ?>
                        <option value="<?= $org['id'] ?>" data-type="<?= $org['type'] ?>">
                            <?= htmlspecialchars($org['name']) ?> (<?= $org['type'] == 'dealer' ? 'Дилер' : 'Клиент' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitEditForm()">Сохранить</button>
            <button class="btn btn-link" onclick="closeEditModal()">Отмена</button>
        </div>
    </div>
</div>

<div class="modal" id="passwordModal">
    <div class="modal-overlay" onclick="closePasswordModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button class="btn btn-clear float-right" onclick="closePasswordModal()"></button>
            <h3>Изменить пароль</h3>
        </div>
        <div class="modal-body">
            <form id="passwordForm" method="POST">
                <input type="hidden" name="user_id" id="password_user_id">
                
                <div class="form-group">
                    <label class="form-label" for="new_password">Новый пароль</label>
                    <input class="form-input" type="password" name="new_password" id="new_password" required autocomplete="new-password">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Подтверждение пароля</label>
                    <input class="form-input" type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="submitPasswordForm()">Изменить</button>
            <button class="btn btn-link" onclick="closePasswordModal()">Отмена</button>
        </div>
    </div>
</div>

<script>
    document.getElementById('showDeletedToggle').addEventListener('change', function() {
        window.location.href = 'users.php?show_deleted=' + (this.checked ? '1' : '0');
    });
    
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        toggleOrganizationSelect();
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
    }
    
    function toggleOrganizationSelect() {
        const roleSelect = document.getElementById('role');
        const organizationGroup = document.getElementById('organizationGroup');
        
        if (roleSelect) {
            const selectedRole = parseInt(roleSelect.value);
            
            if (selectedRole === <?= ROLE_ADMIN ?>) {
                organizationGroup.style.display = 'none';
            } else {
                organizationGroup.style.display = 'block';
                
                const organizationSelect = document.getElementById('organization_id');
                const options = organizationSelect.options;
                
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const orgType = option.getAttribute('data-type');
                    
                    if (selectedRole === <?= ROLE_DEALER ?>) {
                        if (orgType === 'dealer' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    } else if (selectedRole === <?= ROLE_USER ?>) {
                        if (orgType === 'client' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                }
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
    
    function openEditModal(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editName').value = user.name || '';
        document.getElementById('editEmail').value = user.email;
        
        if (document.getElementById('editRole')) {
            document.getElementById('editRole').value = user.role;
        }
        
        if (document.getElementById('editOrganizationId')) {
            document.getElementById('editOrganizationId').value = user.organization_id || '';
        }
        
        if (document.getElementById('editRole')) {
            toggleEditOrganizationSelect();
        }
        
        document.getElementById('editModal').classList.add('active');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }
    
    function toggleEditOrganizationSelect() {
        const roleSelect = document.getElementById('editRole');
        const organizationGroup = document.getElementById('editOrganizationGroup');
        
        if (roleSelect) {
            const selectedRole = parseInt(roleSelect.value);
            
            if (selectedRole === <?= ROLE_ADMIN ?>) {
                organizationGroup.style.display = 'none';
            } else {
                organizationGroup.style.display = 'block';
            
                const organizationSelect = document.getElementById('editOrganizationId');
                const options = organizationSelect.options;
                
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const orgType = option.getAttribute('data-type');
                    
                    if (selectedRole === <?= ROLE_DEALER ?>) {
                        if (orgType === 'dealer' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    } else if (selectedRole === <?= ROLE_USER ?>) {
                        if (orgType === 'client' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                }
            }
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
    
    function openPasswordModal(userId) {
        document.getElementById('password_user_id').value = userId;
        document.getElementById('passwordModal').classList.add('active');
    }
    
    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
    }
    
    function submitPasswordForm() {
        const form = document.getElementById('passwordForm');
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            showMessage('error', 'Пароли не совпадают');
            return;
        }
        
        const formData = new FormData(form);
        formData.append('change_password', '1');
        
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
                closePasswordModal();
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    }
    
    function deleteUser(userId) {
        if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) return;
        
        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('user_id', userId);
        
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
    
    function restoreUser(userId) {
        if (!confirm('Вы уверены, что хотите восстановить этого пользователя?')) return;
        
        const formData = new FormData();
        formData.append('restore', '1');
        formData.append('user_id', userId);
        
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
    
    function toggleBlockUser(userId, block) {
        const action = block ? 'заблокировать' : 'разблокировать';
        if (!confirm(`Вы уверены, что хотите ${action} этого пользователя?`)) return;
        
        const formData = new FormData();
        formData.append('toggle_block', '1');
        formData.append('user_id', userId);
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
    
    function showMessage(type, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `toast toast-${type}`;
        messageDiv.textContent = text;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
    
    function updateDeletedToggle() {
        const table = $('#usersTable').DataTable();
        const hasDeletedUsers = table.rows('.deleted-user').data().length > 0;
        
        const toggleCheckbox = document.getElementById('showDeletedToggle');
        if (!hasDeletedUsers && toggleCheckbox.checked) {
            window.location.href = 'users.php?show_deleted=0';
        } else {
            toggleCheckbox.disabled = !hasDeletedUsers;
            if (toggleCheckbox.disabled) {
                toggleCheckbox.parentElement.classList.add('disabled');
                toggleCheckbox.parentElement.title = 'Нет удаленных пользователей';
            } else {
                toggleCheckbox.parentElement.classList.remove('disabled');
                toggleCheckbox.parentElement.title = '';
            }
        }
    }
    
    const style = document.createElement('style');
    style.textContent = `
        .form-switch.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-switch.disabled input {
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(style);
    
    $(document).ready(function() {
        $('#usersTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json"
            },
            "pageLength": 10,
            "order": [[0, "asc"]],
            "initComplete": function() {
                updateDeletedToggle();
            }
        });
    });
    
    const originalRestoreUser = restoreUser;
    restoreUser = function(userId) {
        if (!confirm('Вы уверены, что хотите восстановить этого пользователя?')) return;
        
        const formData = new FormData();
        formData.append('restore', '1');
        formData.append('user_id', userId);
        
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
                
                const table = $('#usersTable').DataTable();
                if (table.rows('.deleted-user').data().length <= 1) {

                    window.location.href = 'users.php?show_deleted=0';
                } else {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Ошибка сети');
        });
    };
</script>
