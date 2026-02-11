<?php

require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    if (basename($_SERVER['PHP_SELF']) != 'login.php') {
        redirect('/login.php');
    }
} else {
    if (basename($_SERVER['PHP_SELF']) == 'login.php') {
        redirect('/');
    }
}

function checkPermission($required_role) {
    if ($_SESSION['role'] > $required_role) {
        redirect('?error=no_permission');
    }
}
