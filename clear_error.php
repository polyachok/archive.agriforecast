<?php
session_start();
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
