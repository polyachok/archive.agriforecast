<?php

require_once ROOT_PATH . '/includes/db.php';

class Organization {
    private $db;
    private $current_year;
    
    public function __construct($year = null) {
        $this->db = DB::getInstance()->getConnection();
        $this->current_year = $year; 
    }
    
    private function blockOrganizationDevices($organization_id) {
        $stmt = $this->db->prepare("
            SELECT id FROM devices 
            WHERE organization_id = ?
        ");
        $stmt->execute([$organization_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $device = new Device();
        foreach ($devices as $dev) {
            $device->blockDevice($dev['id']);
        }
    }
    
    private function unblockOrganizationDevices($organization_id, $restore_services = false) {
        $org = $this->getOrganization($organization_id);
        if (!$org) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM devices 
            WHERE organization_id = ? AND is_blocked = 1 AND is_deleted = 0
        ");
        $stmt->execute([$organization_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $device = new Device();
        foreach ($devices as $dev) {
            $device->unblockDevice($dev['id']);

            if ($restore_services) {
                $device->updateDeviceServices(
                    $dev['id'],
                    $org['is_forecast_enabled'],
                    $org['is_realdata_enabled'],
                    $org['is_analytics_enabled'],
                    $org['is_calculations_enabled']
                );
            }
        }
        
        return true;
    }
    
    private function updateDevicesServices($organization_id, $forecast_enabled, $realdata_enabled, $analytics_enabled, $calculations_enabled) {
        $stmt = $this->db->prepare("
            SELECT id FROM devices 
            WHERE organization_id = ? AND is_blocked = 0 AND is_deleted = 0
        ");
        $stmt->execute([$organization_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $device = new Device();
        foreach ($devices as $dev) {
            $device->updateDeviceServices(
                $dev['id'],
                $forecast_enabled,
                $realdata_enabled,
                $analytics_enabled,
                $calculations_enabled
            );
        }
        
        return true;
    }
    
    public function getOrganization($id) {
        $stmt = $this->db->prepare("
            SELECT * FROM organizations 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllOrganizations($include_deleted = false) {
        $sql = "SELECT * FROM organizations";
        if (!$include_deleted) {
            $sql .= " WHERE is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDealers($include_deleted = false) {
        $sql = "SELECT * FROM organizations WHERE type = 'dealer'";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getClients($dealer_id = null, $include_deleted = false) {
        $sql = "SELECT * FROM organizations WHERE type = 'client'";
        $params = [];
        
        if ($dealer_id !== null) {
            $sql .= " AND dealer_id = ?";
            $params[] = $dealer_id;
        }
        
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getFreeClients($include_deleted = false) {
        $sql = "SELECT * FROM organizations WHERE type = 'client' AND dealer_id IS NULL";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getOrganizationUsers($organization_id, $include_deleted = false) {
        $sql = "SELECT * FROM users WHERE organization_id = ?";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getOrganizationDevices($organization_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM devices 
            WHERE organization_id = ?
        ");
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getOrganizationType($id) {
        $stmt = $this->db->prepare("
            SELECT type FROM organizations 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['type'] : null;
    }
    
    public function isDealer($id) {
        return $this->getOrganizationType($id) === 'dealer';
    }
    
    public function isClient($id) {
        return $this->getOrganizationType($id) === 'client';
    }
    
    public function getDealerClients($dealer_id, $include_deleted = false) {
        $sql = "SELECT * FROM organizations WHERE dealer_id = ?";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dealer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getClientDealer($client_id) {
        $stmt = $this->db->prepare("
            SELECT d.* FROM organizations c
            JOIN organizations d ON c.dealer_id = d.id
            WHERE c.id = ? AND c.type = 'client'
        ");
        $stmt->execute([$client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function inheritServicesToDevice($organization_id, $device_id) {
        $org = $this->getOrganization($organization_id);
        if (!$org) {
            return false;
        }
        
        $device = new Device();
        return $device->updateDeviceServices(
            $device_id, 
            $org['is_forecast_enabled'], 
            $org['is_realdata_enabled'], 
            $org['is_analytics_enabled'], 
            $org['is_calculations_enabled']
        );
    }

    public function getDeletedOrganizations() {
        $sql = "SELECT * FROM organizations WHERE is_deleted = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedDealers() {
        $sql = "SELECT * FROM organizations WHERE type = 'dealer' AND is_deleted = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedClients($dealer_id = null) {
        $sql = "SELECT * FROM organizations WHERE type = 'client' AND is_deleted = 1";
        $params = [];
        
        if ($dealer_id !== null) {
            $sql .= " AND dealer_id = ?";
            $params[] = $dealer_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
