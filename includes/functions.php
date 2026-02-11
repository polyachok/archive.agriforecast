<?php

function redirect($url) {
    header("Location: $url");
    exit();
}

function displayError() {
    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error']);
        echo '<div class="toast toast-error">';
        switch ($error) {
            case 'no_permission':
                echo 'У вас нет прав для этого действия';
                break;
            case 'login_failed':
                echo 'Неверное имя пользователя или пароль';
                break;
            default:
                echo 'Произошла ошибка';
        }
        echo '</div>';
    }
}

function displayMessages() {
    if (isset($_GET['success'])) {
        $message = htmlspecialchars($_GET['success']);
        echo '<div class="toast toast-success">';
        switch ($message) {
            case 'user_created':
                echo 'Пользователь успешно создан';
                break;
            default:
                echo 'Операция выполнена успешно';
        }
        echo '</div>';
    }
    
    displayError(); 
}

function getRoleName($role) {
    switch ($role) {
        case ROLE_ADMIN: return 'Администратор';
        case ROLE_DEALER: return 'Дилер';
        case ROLE_USER: return 'Пользователь';
        default: return 'Неизвестно';
    }
}
