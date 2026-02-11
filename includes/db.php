<?php

require_once 'config.php';

class DB {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME, 
            DB_USER, 
            DB_PASS
        );
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new DB();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
/*
CREATE DATABASE portal_db;
USE portal_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role TINYINT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    coordinates VARCHAR(100),
    created_by INT NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Создание администратора по умолчанию
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 1);
*/
