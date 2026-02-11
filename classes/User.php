<?php

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/Organization.php';

class User {
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    public function canCreateUsers($creator_role) {
        if ($creator_role == ROLE_ADMIN) {
            return [ROLE_ADMIN, ROLE_DEALER, ROLE_USER];
        }
        elseif ($creator_role == ROLE_DEALER) {
            return [ROLE_USER];
        }
        return [];
    }
    
    public function getConnection() {
        return $this->db;
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE username = ? AND is_blocked = 0 AND is_deleted = 0 ORDER BY year DESC LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['organization_id']) {
                $stmt = $this->db->prepare("
                    SELECT is_blocked, is_deleted FROM organizations 
                    WHERE id = ?
                ");
                $stmt->execute([$user['organization_id']]);
                $org = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($org && ($org['is_blocked'] || $org['is_deleted'])) {
                    return false; 
                }
            } elseif ($user['role'] != ROLE_ADMIN) {
                return false;
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['organization_id'] = $user['organization_id'];
            $_SESSION['year'] = $user['year'];
            return true;
        }
        return false;
    }
    
    public function changeUserPeriod($name, $year){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND year = ?");
        $stmt->execute([$name, $year]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if(isset($user['id'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['organization_id'] = $user['organization_id'];
            $_SESSION['year'] = $user['year'];
            return true;
        }else{
            return false;
        }
    }

    public function getUser($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }    
    
    public function getUserPeriod($name){
        $stmt = $this->db->prepare("SELECT `year` FROM users WHERE username = ? ");
        $stmt->execute([$name]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByOrganization($organization_id, $include_deleted = false) {
        $sql = "SELECT * FROM users WHERE organization_id = ?";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUsersByCreator($created_by, $include_deleted = false) {
        $sql = "SELECT * FROM users WHERE created_by = ?";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$created_by]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllUsers($include_deleted = false) {
        $sql = "SELECT * FROM users WHERE `year` = $this->current_year";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllDealers($include_deleted = false) {
        $sql = "
            SELECT u.* FROM users u
            JOIN organizations o ON u.organization_id = o.id
            WHERE o.type = 'dealer' AND u.role = ?
        ";
        if (!$include_deleted) {
            $sql .= " AND u.is_deleted = 0 AND o.is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([ROLE_DEALER]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAdmins($include_deleted = false) {
        $sql = "SELECT * FROM users WHERE role = ? AND organization_id IS NULL";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([ROLE_ADMIN]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserOrganization($user_id) {
        $stmt = $this->db->prepare("
            SELECT o.* FROM users u
            JOIN organizations o ON u.organization_id = o.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function isUserAdmin($user_id) {
        $user = $this->getUser($user_id);
        return $user && $user['role'] == ROLE_ADMIN && !$user['organization_id'];
    }
    
    public function isUserDealer($user_id) {
        $user = $this->getUser($user_id);
        if (!$user || $user['role'] != ROLE_DEALER) {
            return false;
        }
        
        if ($user['organization_id']) {
            $org = new Organization();
            return $org->isDealer($user['organization_id']);
        }
        
        return false;
    }
    
    public function isUserClient($user_id) {
        $user = $this->getUser($user_id);
        if (!$user || $user['role'] != ROLE_USER) {
            return false;
        }
        
        if ($user['organization_id']) {
            $org = new Organization();
            return $org->isClient($user['organization_id']);
        }
        
        return false;
    }

    public function getDeletedUsers() {
        $sql = "SELECT * FROM users WHERE is_deleted = 1 ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedUsersByOrganization($organization_id) {
        $sql = "SELECT * FROM users WHERE organization_id = ? AND is_deleted = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedUsersByCreator($created_by) {
        $sql = "SELECT * FROM users WHERE created_by = ? AND is_deleted = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$created_by]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
