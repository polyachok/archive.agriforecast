<?php
$dir = __DIR__;
$files = [];
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
foreach (scandir($dir) as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $files[] = "assets/bg_img/" . $file;
    }
}
header('Content-Type: application/json');
echo json_encode($files);