<?php

require_once ROOT_PATH . '/includes/db.php';

class DeviceGroup {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }
    
    public function createGroup($name, $min_lat, $max_lat, $min_lng, $max_lng, $devices = []) {
        $this->db->beginTransaction();
        
        try {
            if (!empty($devices)) {
                $placeholders = implode(',', array_fill(0, count($devices), '?'));
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM device_group_mappings 
                    WHERE device_id IN ($placeholders)
                ");
                $stmt->execute($devices);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    throw new Exception("Некоторые устройства уже находятся в других группах");
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO device_groups (name, min_lat, max_lat, min_lng, max_lng)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $min_lat, $max_lat, $min_lng, $max_lng]);
            $group_id = $this->db->lastInsertId();
            
            if (!empty($devices)) {
                $stmt = $this->db->prepare("INSERT INTO device_group_mappings (device_id, group_id) VALUES (?, ?)");
                foreach ($devices as $device_id) {
                    $stmt->execute([$device_id, $group_id]);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating device group: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateGroup($id, $name, $min_lat, $max_lat, $min_lng, $max_lng, $devices = []) {
        $this->db->beginTransaction();
        
        try {
            if (!empty($devices)) {
                $stmt = $this->db->prepare("
                    SELECT device_id FROM device_group_mappings 
                    WHERE group_id = ?
                ");
                $stmt->execute([$id]);
                $existingDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $newDevices = array_diff($devices, $existingDevices);
                
                if (!empty($newDevices)) {
                    $placeholders = implode(',', array_fill(0, count($newDevices), '?'));
                    $stmt = $this->db->prepare("
                        SELECT COUNT(*) FROM device_group_mappings 
                        WHERE device_id IN ($placeholders) AND group_id != ?
                    ");
                    $params = array_merge($newDevices, [$id]);
                    $stmt->execute($params);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        throw new Exception("Некоторые устройства уже находятся в других группах");
                    }
                }
            }

            $stmt = $this->db->prepare("
                UPDATE device_groups 
                SET name = ?, min_lat = ?, max_lat = ?, min_lng = ?, max_lng = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $min_lat, $max_lat, $min_lng, $max_lng, $id]);
            
            $this->db->prepare("DELETE FROM device_group_mappings WHERE group_id = ?")->execute([$id]);
            
            if (!empty($devices)) {
                $stmt = $this->db->prepare("INSERT INTO device_group_mappings (device_id, group_id) VALUES (?, ?)");
                foreach ($devices as $device_id) {
                    if (!$stmt->execute([$device_id, $id])) {
                        throw new Exception("Ошибка при добавлении устройства $device_id в группу");
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating device group: " . $e->getMessage());
            throw $e; 
        }
    }
    
    public function deleteGroup($id) {
        $this->db->beginTransaction();
        
        try {
            $this->db->prepare("DELETE FROM device_group_mappings WHERE group_id = ?")->execute([$id]);

            $stmt = $this->db->prepare("DELETE FROM device_groups WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error deleting device group: " . $e->getMessage());
            return false;
        }
    }
    
    public function getGroup($id) {
        $stmt = $this->db->prepare("SELECT * FROM device_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllGroups() {
        $stmt = $this->db->prepare("
            SELECT g.*, COUNT(dgm.device_id) as devices_count 
            FROM device_groups g
            LEFT JOIN device_group_mappings dgm ON g.id = dgm.group_id
            GROUP BY g.id
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDevicesForGroupForm($group_id = null) {
        $devices = [];

        $stmt = $this->db->prepare("
            SELECT d.id, d.name, d.device_id, 
                   CASE WHEN dgm.group_id = ? THEN 1 ELSE 0 END as in_group
            FROM devices d
            LEFT JOIN device_group_mappings dgm ON d.id = dgm.device_id
            WHERE dgm.device_id IS NULL OR dgm.group_id = ?
        ");
        $stmt->execute([$group_id, $group_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $devices;
    }


}
