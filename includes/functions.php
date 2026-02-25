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

function getMonthsBetweenRu($startDate, $endDate) {
    $monthsRu = [
        '01' => 'Январь',
        '02' => 'Февраль',
        '03' => 'Март',
        '04' => 'Апрель',
        '05' => 'Май',
        '06' => 'Июнь',
        '07' => 'Июль',
        '08' => 'Август',
        '09' => 'Сентябрь',
        '10' => 'Октябрь',
        '11' => 'Ноябрь',
        '12' => 'Декабрь',
    ];

    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);

    $start->modify('first day of this month');
    $end->modify('first day of this month');

    $result = [];
    $current = clone $start;

    while ($current <= $end) {
        $ym = $current->format('Y-m');
        $m  = $current->format('m');
        $y  = $current->format('Y');

        $label = $monthsRu[$m] . ' ' . $y;
        $result[$ym] = $label;

        $current->modify('+1 month');
    }

    return $result;
}
