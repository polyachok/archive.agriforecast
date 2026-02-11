<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/User.php';

$user = new User();
$current_role = $_SESSION['role'];

if ($current_role === ROLE_ADMIN) {
    $users = $user->getAllUsers();
    $show_creator_column = true;
} else {
    $users = $user->getUsersByCreator($_SESSION['user_id']);
    $show_creator_column = false;
}
?>

<style>
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    @media (max-width: 768px) {
        #usersTable th:nth-child(4),
        #usersTable td:nth-child(4) {
            display: none;
        }
    }
    @media (max-width: 768px) {
        .btn-text {
            display: none;
        }
        
        .btn-icon {
            display: inline-block;
            margin: 0;
        }
        
        .btn-sm {
            padding: 6px 8px;
            min-width: 32px;
        }
    }
    
    @media (min-width: 769px) {
        .btn-icon {
            display: none;
        }
    }
</style>

<table id="usersTable" class="display" style="width:100%">
    <thead>
        <tr>
            <th>Логин</th>
            <th>Email</th>
            <th>Имя компании</th>
            <?php if ($current_role === ROLE_ADMIN): ?>
            <th>Роль</th>
            <?php endif; ?>
            <th>Прогноз</th>
            <th style="min-width: 110px;">Действия</th>
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
            $creator = $u['created_by'] ? $user->getUser($u['created_by']) : null;
        ?>
        <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
            <?php if ($current_role === ROLE_ADMIN): ?>
            <td><?= $roleText ?></td>
            <?php endif; ?>
            <td><?= $u['forecast_enabled'] ? 'Да' : 'Нет' ?></td>
            <td>
    <?php if ($u['id'] == 1): ?>
        <span class="text-gray">Недоступно</span>
    <?php elseif ($_SESSION['user_id'] == 1 && $u['role'] == ROLE_ADMIN && $u['id'] != 1): ?>
        <div class="action-buttons">
            <button class="btn btn-primary btn-sm" onclick="openEditModal({
                id: <?= $u['id'] ?>,
                username: '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>',
                name: '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>',
                email: '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>',
                forecast_enabled : '<?= htmlspecialchars($u['forecast_enabled'], ENT_QUOTES) ?>',
                role: <?= $u['role'] ?>,
                created_by: <?= $u['created_by'] ?? 'null' ?>
            })">
                <i class="fas fa-edit btn-icon"></i>
                <span class="btn-text">Изменить</span>
            </button>
            <button class="btn btn-sm password-btn" data-user-id="<?= $u['id'] ?>">
                <i class="fas fa-key btn-icon"></i>
                <span class="btn-text">Пароль</span>
            </button>
            <button class="btn btn-error btn-sm" onclick="deleteUser(<?= $u['id'] ?>)">
                <i class="fas fa-trash-alt btn-icon"></i>
                <span class="btn-text">Удалить</span>
            </button>
        </div>
    <?php elseif ($_SESSION['role'] == ROLE_ADMIN && $u['role'] == ROLE_ADMIN && $u['id'] != $_SESSION['user_id'] && $u['id'] != 1): ?>
    <div class="action-buttons">
        <button class="btn btn-sm password-btn" data-user-id="<?= $u['id'] ?>">
            <i class="fas fa-key btn-icon"></i>
            <span class="btn-text">Пароль</span>
        </button>
    </div>
    <?php elseif ($_SESSION['role'] == ROLE_DEALER): ?>
    <div class="action-buttons">
        <button class="btn btn-sm password-btn" data-user-id="<?= $u['id'] ?>">
            <i class="fas fa-key btn-icon"></i>
            <span class="btn-text">Пароль</span>
        </button>
    </div>
    <?php elseif ($u['id'] != 1 || $_SESSION['user_id'] == 1): ?>
        <div class="action-buttons">
            <button class="btn btn-primary btn-sm" onclick="openEditModal({
                id: <?= $u['id'] ?>,
                username: '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>',
                name: '<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>',
                email: '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>',
                forecast_enabled : '<?= htmlspecialchars($u['forecast_enabled'], ENT_QUOTES) ?>',
                role: <?= $u['role'] ?>,
                created_by: <?= $u['created_by'] ?? 'null' ?>
            })">
                <i class="fas fa-edit btn-icon"></i>
                <span class="btn-text">Изменить</span>
            </button>
            <button class="btn btn-sm password-btn" data-user-id="<?= $u['id'] ?>">
                <i class="fas fa-key btn-icon"></i>
                <span class="btn-text">Пароль</span>
            </button>
            <?php if ($_SESSION['role'] != ROLE_USER && $u['id'] != 1): ?>
            <button class="btn btn-error btn-sm" onclick="deleteUser(<?= $u['id'] ?>)">
                <i class="fas fa-trash-alt btn-icon"></i>
                <span class="btn-text">Удалить</span>
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <span class="text-gray">Недоступно</span>
    <?php endif; ?>
</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
