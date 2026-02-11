<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/DeviceGroup.php';

$deviceGroup = new DeviceGroup();

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$devices = $deviceGroup->getDevicesForGroupForm($group_id);

header('Content-Type: application/json');
echo json_encode(['devices' => $devices]);
