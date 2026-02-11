<?php
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/includes/functions.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = new User();
    if ($user->login($_POST['username'], $_POST['password'])) {
       header("Location: /pages/dashboard.php");
       exit();
    } else {
        $_SESSION['error_message'] = 'Неверное имя пользователя или пароль';
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="assets/css/spectre.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        body {
            background-color: #f5f5f5;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e1e1e1;
        }
        
        .login-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
            color: #555;
        }
        
        .form-input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            border-color: #5755d9;
            outline: none;
        }
        
        .btn {
            width: 100%;
            font-weight: 500;
            border-radius: 15px;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 4px;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideIn 0.3s, fadeOut 0.5s 2.5s forwards;
        }
        
        .toast-error {
            background-color: #ff6b6b;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Вход в систему</h1>
            <p>Пожалуйста, введите свои учетные данные</p>
        </div>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="username">Имя пользователя</label>
                <input class="form-input" type="text" id="username" name="username" required placeholder="Введите имя пользователя">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <input class="form-input" type="password" id="password" name="password" required placeholder="Введите пароль">
            </div>
            
            <button type="submit" class="btn btn-primary">Войти</button>
            <a href="/">Вернуться назад</a>
        </form>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.createElement('div');
            toast.className = 'toast toast-error';
            toast.textContent = '<?php echo $_SESSION['error_message']; ?>';
            document.body.appendChild(toast);
            
            fetch('clear_error.php')
                .then(response => response.json())
                .then(data => {})
                .catch(error => console.error('Error:', error));
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        });
    </script>
    <?php 
        unset($_SESSION['error_message']);
    endif; ?>
</body>
</html>
