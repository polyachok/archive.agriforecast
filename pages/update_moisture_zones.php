<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Проверка прав пользователя
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$device_id = (int)$_POST['device_id'];
$yellow = (float)$_POST['yellow_zone_start'];
$green = (float)$_POST['green_zone_start'];
$blue = (float)$_POST['blue_zone_start'];

// Проверка допустимости значений
if ($yellow < 0 || $green < $yellow || $blue < $green) {
    echo json_encode(['success' => false, 'error' => 'Invalid zone values']);
    exit();
}

// Проверка прав на устройство
$device = new Device();
$device_info = $device->getDevice($device_id);

if (!$device_info) {
    echo json_encode(['success' => false, 'error' => 'Device not found']);
    exit();
}

// Проверка прав пользователя на изменение устройства
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role == ROLE_USER && $device_info['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'No permission']);
    exit();
} elseif ($role == ROLE_DEALER) {
    $user = new User();
    $device_user = $user->getUser($device_info['user_id']);
    if ($device_user['created_by'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No permission']);
        exit();
    }
}

// Обновление значений
if ($device->updateMoistureZones($device_id, $yellow, $green, $blue)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}