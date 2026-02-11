<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/User.php';

$user = new User();
$current_user = $user->getUser($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (password_verify($_POST['current_password'], $current_user['password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $user->getConnection()->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $success = "Пароль успешно изменен";
            } else {
                $error = "Ошибка при изменении пароля";
            }
        } else {
            $error = "Новый пароль и подтверждение не совпадают";
        }
    } else {
        $error = "Текущий пароль неверен";
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $device_order = $_POST['device_order'] ?? 1;
    if ($user->updateUser(
        $_SESSION['user_id'],
        $current_user['username'],
        $_POST['email'] ?? $current_user['email'],
        $current_user['role'],
        $current_user['organization_id'],
        $_POST['name'] ?? $current_user['name'],
        $device_order
    )) {
        $success = "Профиль успешно обновлен";
    } else {
        $error = "Ошибка при обновлении профиля";
    }
}
?>

<?php include ROOT_PATH . '/includes/header.php'; ?>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>

    <div class="column col-9 col-xs-12">
        <div class="panel">
            <div class="panel-header">
                <h3>Настройки профиля</h3>
            </div>
            <div class="panel-body">
                <?php if (isset($error)): ?>
                <div class="toast toast-error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                <div class="toast toast-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" action="profile.php">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Имя компании</label>
                        <input class="form-input" type="text" name="name" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Сортировка станций</label>
                        <select class="form-select" name="device_order" id="device_order">
                            <option value="1" <?php echo ($current_user['device_order'] ?? 1) == 1 ? 'selected' : ''; ?>>По умолчанию</option>
                            <option value="2" <?php echo ($current_user['device_order'] ?? 1) == 2 ? 'selected' : ''; ?>>Проблемные станции</option>
                            <option value="3" <?php echo ($current_user['device_order'] ?? 1) == 3 ? 'selected' : ''; ?>>По ID станции</option>
                        </select>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Смена пароля</h3>
            </div>
            <div class="panel-body">
                <?php if (isset($error)): ?>
                <div class="toast toast-error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                <div class="toast toast-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" action="profile.php">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Текущий пароль</label>
                        <input class="form-input" type="password" id="current_password" name="current_password"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Новый пароль</label>
                        <input class="form-input" type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Подтвердите новый пароль</label>
                        <input class="form-input" type="password" id="confirm_password" name="confirm_password"
                            required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Изменить пароль</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/main.js"></script>
</body>

</html>