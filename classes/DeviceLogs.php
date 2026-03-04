<?php

class DeviceLogs {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=forecast", 
                DB_USER, 
                DB_PASS
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Ошибка подключения к базе данных forecast: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT * FROM device_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['device_type'])) {
            $sql .= " AND device_type = ?";
            $params[] = $filters['device_type'];
        }
        
        if (!empty($filters['log_type'])) {
            $sql .= " AND log_type = ?";
            $params[] = $filters['log_type'];
        }
        
        if (!empty($filters['device_uid'])) {
            $sql .= " AND device_uid LIKE ?";
            $params[] = '%' . $filters['device_uid'] . '%';
        }
        
        if (!empty($filters['message'])) {
            $sql .= " AND message LIKE ?";
            $params[] = '%' . $filters['message'] . '%';
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(created_at) = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['datetime_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['datetime_from'];
        } elseif (!empty($filters['date_from'])) {
            $timeFrom = !empty($filters['time_from']) ? $filters['time_from'] : '00:00:00';
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' ' . $timeFrom;
        }
        
        if (!empty($filters['datetime_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['datetime_to'];
        } elseif (!empty($filters['date_to'])) {
            $timeTo = !empty($filters['time_to']) ? $filters['time_to'] : '23:59:59';
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' ' . $timeTo;
        }
        
        $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getLogsCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM device_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['device_type'])) {
            $sql .= " AND device_type = ?";
            $params[] = $filters['device_type'];
        }
        
        if (!empty($filters['log_type'])) {
            $sql .= " AND log_type = ?";
            $params[] = $filters['log_type'];
        }
        
        if (!empty($filters['device_uid'])) {
            $sql .= " AND device_uid LIKE ?";
            $params[] = '%' . $filters['device_uid'] . '%';
        }
        
        if (!empty($filters['message'])) {
            $sql .= " AND message LIKE ?";
            $params[] = '%' . $filters['message'] . '%';
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(created_at) = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['datetime_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['datetime_from'];
        } elseif (!empty($filters['date_from'])) {
            $timeFrom = !empty($filters['time_from']) ? $filters['time_from'] : '00:00:00';
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' ' . $timeFrom;
        }

        if (!empty($filters['datetime_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['datetime_to'];
        } elseif (!empty($filters['date_to'])) {
            $timeTo = !empty($filters['time_to']) ? $filters['time_to'] : '23:59:59';
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' ' . $timeTo;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getUniqueDeviceTypes() {
        $sql = "SELECT DISTINCT device_type FROM device_logs ORDER BY device_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getUniqueDeviceUIDs() {
        $sql = "SELECT DISTINCT device_uid FROM device_logs WHERE device_uid IS NOT NULL ORDER BY device_uid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getLogTypes() {
        return ['CRITICAL', 'WARNING', 'INFO', 'DEBUG'];
    }

    public function getLogYears() {
        $sql = "SELECT DISTINCT YEAR(created_at) as log_year FROM device_logs WHERE created_at IS NOT NULL ORDER BY log_year DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
