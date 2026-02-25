<?php
require_once ROOT_PATH . '/includes/db.php';

class Device {
    private $db;
    
    public function __construct($year = null) {
        $this->db = DB::getInstance()->getConnection();
        if ($year) {
            // Если год передан, можно сохранить его для дальнейшего использования
        }
    }

    public function getForecastYears() {
        $stmt = $this->db->prepare("SELECT DISTINCT `year` FROM `device_forecast_value` ORDER BY `year` DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    private function getSoilDB() {
        try {
            return new PDO(
                "mysql:host=" . SOIL_DB_HOST . ";dbname=" . SOIL_DB_NAME,
                SOIL_DB_USER,
                SOIL_DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            error_log("Connection to soil DB failed: " . $e->getMessage());
            throw $e; 
        }
    }
    
    public function getDevice($id) {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getOrganizationDevices($organization_id, $order = 1, $include_deleted = false) {
        switch ($order) {
            case 3:
                $order = 'id';
                break;
            case 1:
                $order = 'name';   
                break;         
            default:
                $order = 'id';
                break;
        }
        $sql = "SELECT * FROM devices WHERE organization_id = ?";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $sql .= " ORDER BY ".$order;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDealerDevices($dealer_id, $order = 1, $include_deleted = false) {
        switch ($order) {
            case 3:
                $order = 'id';
                break;
            case 1:
                $order = 'name';  
                break;          
            default:
                $order = 'id';
                break;
        }
        $sql = "
            SELECT d.* FROM devices d
            LEFT JOIN organizations o ON d.organization_id = o.id
            WHERE (o.id = ? OR o.dealer_id = ?)
        ";
        if (!$include_deleted) {
            $sql .= " AND d.is_deleted = 0";
        }
        $sql .= " ORDER BY ".$order;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dealer_id, $dealer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }    
    
    public function getAllDevices($order = 1, $include_deleted = false) {
        switch ($order) {
            case 3:
                $order = 'id';
                break;
            case 1:
                $order = 'name';   
                break;        
            default:
                $order = 'id';
                break;
        }
        $sql = "SELECT * FROM devices ";
        if (!$include_deleted) {
            $sql .= " WHERE is_deleted = 0";
        }       
        $sql .= " ORDER BY ".$order;
        $stmt = $this->db->prepare($sql);       
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUnassignedDevices($include_deleted = false) {
        $sql = "SELECT * FROM devices WHERE organization_id IS NULL";
        if (!$include_deleted) {
            $sql .= " AND is_deleted = 0";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }    
    
    public function getDevicesByGroup($group_id) {
        $stmt = $this->db->prepare("
            SELECT d.* FROM devices d
            JOIN device_group_mappings dgm ON d.id = dgm.device_id
            WHERE dgm.group_id = ?
        ");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countDevicesInGroup($group_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM device_group_mappings WHERE group_id = ?");
        $stmt->execute([$group_id]);
        return $stmt->fetchColumn();
    }
    
    public function getDeviceGroup($device_id) {
        $stmt = $this->db->prepare("
            SELECT g.* FROM device_groups g
            JOIN device_group_mappings dgm ON g.id = dgm.group_id
            WHERE dgm.device_id = ?
        ");
        $stmt->execute([$device_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getDevicesNotInGroup() {
        $stmt = $this->db->prepare("
            SELECT d.* FROM devices d
            LEFT JOIN device_group_mappings dgm ON d.id = dgm.device_id
            WHERE dgm.device_id IS NULL
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getForecastValuesPeriod($device_id) {
        $stmt = $this->db->prepare("
           SELECT DATE_FORMAT(MIN(ref_time), '%d.%m.%Y') AS device_period FROM devices_forecast_values 
           WHERE device_id = ? 
        ");
        $stmt->execute([$device_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getForecastValuesFromDate($device_id, $current_date, $yesterday) {
        $current_date =  DateTime::createFromFormat('Y-m-d', $current_date)->format('Y-m-d 23:00:00');
        $stmt = $this->db->prepare("
            SELECT * FROM devices_forecast_values 
            WHERE device_id = ? 
            AND ref_time >= ?
            AND ref_time <= ? 
            ORDER BY ref_time ASC, predict_minutes ASC
        ");
        $stmt->execute([$device_id, $yesterday, $current_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLinkedMeteostation($device_id) {
        $stmt = $this->db->prepare("
            SELECT m.* FROM devices d
            JOIN devices m ON d.linked_meteostation_id = m.id
            WHERE d.id = ?
        ");
        $stmt->execute([$device_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getDevicesLinkedToMeteostation($meteostation_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM devices 
            WHERE linked_meteostation_id = ?
        ");
        $stmt->execute([$meteostation_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedDevices() {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE is_deleted = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedDevicesByUser($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE user_id = ? AND is_deleted = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeletedDevicesByOrganization($organization_id) {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE organization_id = ? AND is_deleted = 1");
        $stmt->execute([$organization_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserDevices($user_id, $order = 1, $include_deleted = false) {
        $user = new User();
        $user_org = $user->getUserOrganization($user_id);
        switch ($order) {
            case 3:
                $order = 'id';
                break;
            case 1:
                $order = 'name';  
                break;          
            default:
                $order = 'id';
                break;
        }
        if ($user_org) {
            return $this->getOrganizationDevices($user_org['id'], $include_deleted);
        } else {
            $sql = "SELECT * FROM devices WHERE created_by = ?";
            if (!$include_deleted) {
                $sql .= " AND is_deleted = 0";
            }
            $sql .= " ORDER BY ".$order;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getDevicePeriod($year, $id){
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql = "SELECT 
                    DATE_FORMAT(MIN(DateReal), '%Y-%m-%d') AS start,
                    DATE_FORMAT(MAX(DateReal), '%Y-%m-%d') AS finish
                FROM forecast.SoilSense
                WHERE YEAR(DateReal) = ? 
                AND UID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $device['device_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }

    public function getMeteoDevicePeriod($device_id = null){
        $sql = "SELECT MIN(DateReal) as start, MAX(DateReal) as finish FROM forecast.MeteoSense";
        $params = [];
        if($device_id != null){
            $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ? ");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device && !empty($device['device_id'])) {
                $sql .= " WHERE station_id = ?";
                $params[] = $device['device_id'];
            }
        }
       
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }

    public function getSoilData($internal_id, $date) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        
        $uid = $device['device_id'];

        try {
            $soil_db = $this->getSoilDB();

            $query = "SELECT 
                        Date,
                        Time,
                        (COALESCE(C215, 0) + COALESCE(C230, 0) + COALESCE(C245, 0) + COALESCE(C260, 0)) / 4 AS humidity_accumulative,
                        (COALESCE(C2115, 0) + COALESCE(C2130, 0) + COALESCE(C2145, 0) + COALESCE(C2160, 0)) / 4 AS humidity_5cm,
                        (COALESCE(C2215, 0) + COALESCE(C2230, 0) + COALESCE(C2245, 0) + COALESCE(C2260, 0)) / 4 AS humidity_15cm,
                        (COALESCE(C2315, 0) + COALESCE(C2330, 0) + COALESCE(C2345, 0) + COALESCE(C2360, 0)) / 4 AS humidity_25cm,
                        (COALESCE(C2415, 0) + COALESCE(C2430, 0) + COALESCE(C2445, 0) + COALESCE(C2460, 0)) / 4 AS humidity_35cm,
                        (COALESCE(C2515, 0) + COALESCE(C2530, 0) + COALESCE(C2545, 0) + COALESCE(C2560, 0)) / 4 AS humidity_45cm,
                        (COALESCE(C2615, 0) + COALESCE(C2630, 0) + COALESCE(C2645, 0) + COALESCE(C2660, 0)) / 4 AS humidity_55cm,

                        (COALESCE(C2715, 0) + COALESCE(C2730, 0) + COALESCE(C2745, 0) + COALESCE(C2760, 0)) / 4 AS temp_5cm,
                        (COALESCE(C2815, 0) + COALESCE(C2830, 0) + COALESCE(C2845, 0) + COALESCE(C2860, 0)) / 4 AS temp_15cm,
                        (COALESCE(C2915, 0) + COALESCE(C2930, 0) + COALESCE(C2945, 0) + COALESCE(C2960, 0)) / 4 AS temp_25cm,
                        (COALESCE(C3015, 0) + COALESCE(C3030, 0) + COALESCE(C3045, 0) + COALESCE(C3060, 0)) / 4 AS temp_35cm,
                        (COALESCE(C3115, 0) + COALESCE(C3130, 0) + COALESCE(C3145, 0) + COALESCE(C3160, 0)) / 4 AS temp_45cm,
                        (COALESCE(C3215, 0) + COALESCE(C3230, 0) + COALESCE(C3245, 0) + COALESCE(C3260, 0)) / 4 AS temp_55cm
                    FROM SoilSense
                    WHERE UID = ?
                    AND Date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)
                    ORDER BY Date, Time";                    
            $stmt = $soil_db->prepare($query);
            $stmt->execute([$uid, $date, $date]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: ".$e->getMessage());
            return [];
        }
    }

    public function getMeteoData($internal_id, $date) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        
        $uid = $device['device_id'];

        try {
            $meteo_db = $this->getSoilDB();
            $endDate = DateTime::createFromFormat('Y-d-m', $date)->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s');

            $query = "SELECT 
                        record_time AS ref_time,
                        prec_d AS precipitation,
                        soil_temp_d AS soil_temperature,
                        wet_leaf_d AS wet_leaf,
                        batt_d AS battery_voltage,
                        rssi_d AS signal_strength,
                        unknown5_d AS wind_speed,
                        unknown6_d AS wind_direction,
                        unknown8_d AS air_temperature,
                        unknown9_d AS humidity,
                        unknown10_d AS dew_point,
                        unknown12_d AS solar_radiation
                    FROM MeteoSense
                    WHERE station_id = ?
                    AND record_time >= ?
                    AND record_time <= ?
                    ORDER BY record_time";

            $stmt = $meteo_db->prepare($query);
            $stmt->execute([$uid, $date, $endDate]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Meteo database error: ".$e->getMessage());
            return [];
        }
    }

    public function getLastSoilData($internal_id) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        
        $uid = $device['device_id'];
       
        try {
            $soil_db = $this->getSoilDB();
            $query = "SELECT 
                        Date,
                        Time,
                        C2160 AS humidity_5cm,
                        C2260 AS humidity_15cm,
                        C2360 AS humidity_25cm,
                        C2460 AS humidity_35cm,
                        C2560 AS humidity_45cm,
                        C2660 AS humidity_55cm,

                        C2760 AS temp_5cm,
                        C2860 AS temp_15cm,
                        C2960 AS temp_25cm,
                        C3060 AS temp_35cm,
                        C3160 AS temp_45cm,
                        C3260 AS temp_55cm
                    FROM SoilSense
                    WHERE UID = ?
                    ORDER BY Date DESC, Time DESC
                    LIMIT 1";

            $stmt = $soil_db->prepare($query);
            $stmt->execute([$uid]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: ".$e->getMessage());
            return [];
        }
    }

    public function getPeriodSoilData($internal_id, $date) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        $dateReal = DateTime::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        $uid = $device['device_id'];
        
        try {
            $soil_db = $this->getSoilDB();
            $query = "SELECT 
                        Date,
                        Time,
                        C2160 AS humidity_5cm,
                        C2260 AS humidity_15cm,
                        C2360 AS humidity_25cm,
                        C2460 AS humidity_35cm,
                        C2560 AS humidity_45cm,
                        C2660 AS humidity_55cm,

                        C2760 AS temp_5cm,
                        C2860 AS temp_15cm,
                        C2960 AS temp_25cm,
                        C3060 AS temp_35cm,
                        C3160 AS temp_45cm,
                        C3260 AS temp_55cm
                    FROM SoilSense
                    WHERE UID = ?
                    AND DateReal = ?
                    ORDER By Time DESC
                    LIMIT 1";
            $stmt = $soil_db->prepare($query);
            $stmt->execute([$uid, $dateReal]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: ".$e->getMessage());
            return [];
        }
    }

    public function getLastMeteoData($internal_id, $date) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        $dateReal = DateTime::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        $uid = $device['device_id'];        
        try {
            $meteo_db = $this->getSoilDB();

            $query = "SELECT 
                        record_time AS ref_time,
                        prec_d AS precipitation,
                        soil_temp_d AS soil_temperature,
                        wet_leaf_d AS wet_leaf,
                        batt_d AS battery_voltage,
                        rssi_d AS signal_strength,
                        unknown5_d AS wind_speed,
                        unknown6_d AS wind_direction,
                        unknown8_d AS air_temperature,
                        unknown9_d AS humidity,
                        unknown10_d AS dew_point,
                        unknown12_d AS solar_radiation
                    FROM MeteoSense
                    WHERE station_id = ?
                    AND DateReal = ?
                    ORDER BY record_time DESC
                    LIMIT 1";

            $stmt = $meteo_db->prepare($query);
            $stmt->execute([$uid, $dateReal]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Meteo database error: ".$e->getMessage());
            return [];
        }
    }

    public function getForecastValuesDay($device_id, $date) {
        $today = DateTime::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        $params = $this->getParams('time_grid');  
        $times = $params;
        $dates = array_map(fn($time) => "'{$today} {$time}'", $times);
        $datesString = implode(', ', $dates);       
        $stmt = $this->db->prepare("
            SELECT * FROM devices_forecast_values 
            WHERE device_id = ? 
            AND parameter in('2t', 'tcc', 'crain') 
            AND ref_time IN($datesString);
        ");
        $stmt->execute([$device_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $groupedData = [];

        foreach ($data as $item) {
            $time = $item['ref_time'];
            
            if (!isset($groupedData[$time])) {
                $groupedData[$time] = [
                    'ref_time' => $time,
                    'parameters' => []
                ];
            }            
            $groupedData[$time]['parameters'][$item['parameter']] = $item['value'];
        }
        return $groupedData;
    }

    public function getForecastValuesAllDay($device_id, $dateStr) {
        $stmt = $this->db->prepare("SELECT `coordinates` FROM `devices` WHERE id = ?");
        $stmt->execute([$device_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$res || empty($res['coordinates'])) {
            return null;
        }
        
        [$lat, $lng] = explode(',', $res['coordinates']);
        $offset = $this->getUTCHoursOffset((float)$lat, (float)$lng);
        
        $localDateTime = DateTime::createFromFormat('Y-m-d', $dateStr);
        
        
        $utcNow = $localDateTime->setTime(12, 0, 0);        
       
        $start = clone $utcNow;
        $end = (clone $utcNow)->modify('+1 day');
    
        $stmt = $this->db->prepare("
            SELECT TIME(ref_time) as time_only, DATE(ref_time) as date_only, parameter, value 
            FROM devices_forecast_values 
            WHERE device_id = ? 
            AND parameter IN ('2t', 'tcc', 'crain') 
            AND ref_time >= ? AND ref_time < ?
        ");
        $stmt->execute([$device_id, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $groupedData = [];
        foreach ($data as $item) {
            // Ключ: полная дата + время (чтобы избежать коллизий)
            $timeKey = $item['date_only'] . ' ' . substr($item['time_only'], 0, 5);
    
            if (!isset($groupedData[$timeKey])) {
                $groupedData[$timeKey] = [
                    'ref_date' => $item['date_only'],
                    'ref_time' => substr($item['time_only'], 0, 5),
                    'parameters' => []
                ];
            }
            $groupedData[$timeKey]['parameters'][$item['parameter']] = $item['value'];
        }
    
        return $groupedData;
    }

    public function getForecastValuesWeek($device_id, $date) {
        $today = DateTime::createFromFormat('Y-m-d', $date);
        $result = [];
        
        // Генерируем даты на 5 дней вперед (с завтрашнего дня)
        for ($i = 1; $i <= 5; $i++) {
            $currentDate = clone $today;
            $currentDate->add(new DateInterval("P{$i}D"));
            $dateStr = $currentDate->format('Y-m-d');
            
            // Оптимизированный запрос с группировкой в SQL
            $stmt = $this->db->prepare("
                SELECT 
                    parameter,
                    AVG(value) as avg_value
                FROM devices_forecast_values 
                WHERE device_id = ? 
                AND parameter IN('2t', 'tcc', 'crain') 
                AND DATE(ref_time) = ?
                GROUP BY parameter
            ");
            $stmt->execute([$device_id, $dateStr]);
            $dayData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Если есть данные хотя бы по одному параметру
            if (!empty($dayData)) {
                $dailyAverages = ['date' => $dateStr];
                
                // Заполняем средние значения
                foreach ($dayData as $item) {
                    $dailyAverages[$item['parameter']] = round($item['avg_value']);
                }
                
                // Устанавливаем null для отсутствующих параметров
                $requiredParams = ['2t', 'tcc', 'crain'];
                foreach ($requiredParams as $param) {
                    if (!isset($dailyAverages[$param])) {
                        $dailyAverages[$param] = null;
                    }
                }
                
                $result[] = $dailyAverages;
            }
        }
        
        return $result;
    }

    public function getForecastValuesNow($device_id, $dateStr) {
        $stmt = $this->db->prepare("SELECT `coordinates` FROM `devices` WHERE id = ?");
        $stmt->execute([$device_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$res || empty($res['coordinates'])) {
            return null;
        }
        
        [$lat, $lng] = explode(',', $res['coordinates']);
        $offset = $this->getUTCHoursOffset((float)$lat, (float)$lng);
        $localDateTime = DateTime::createFromFormat('Y-m-d', $dateStr);         
        $utcDateTimeStr = $localDateTime->format('Y-m-d 12:00:00');
       
        $stmt = $this->db->prepare("
            SELECT * FROM devices_forecast_values 
            WHERE device_id = ? 
            AND parameter IN ('2t', 'tcc', 'crain') 
            AND ref_time = ?
        ");
        $stmt->execute([$device_id, $utcDateTimeStr]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [
            'ref_time' => $utcDateTimeStr,
            'parameters' => []
        ];
       
        foreach ($data as $item) {
            $result['parameters'][$item['parameter']] = $item['value'];
        }
       
        return $result;
    }

    function getUTCHoursOffset($lat, $lng) {
        // Проверяем основные регионы России по координатам
        $regions = [
            // UTC+2
            'kaliningrad' => function($lat, $lng) { 
                return $lat >= 54 && $lat <= 55 && $lng >= 19 && $lng <= 22; 
            },
            
            // UTC+3 - Москва и центральные регионы
            'moscow' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 60 && $lng >= 30 && $lng <= 45) ||
                       ($lat >= 53 && $lat <= 56 && $lng >= 35 && $lng <= 40);
            },
            
            // UTC+4 - Поволжье, Урал
            'samara' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 55 && $lng >= 45 && $lng <= 55) ||
                       ($lat >= 56 && $lat <= 60 && $lng >= 50 && $lng <= 60);
            },
            
            // UTC+5 - Урал
            'ekaterinburg' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 60 && $lng >= 55 && $lng <= 70);
            },
            
            // UTC+6 - Западная Сибирь
            'omsk' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 60 && $lng >= 65 && $lng <= 80);
            },
            
            // UTC+7 - Восточная Сибирь
            'krasnoyarsk' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 70 && $lng >= 80 && $lng <= 100);
            },
            
            // UTC+8 - Иркутская область
            'irkutsk' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 65 && $lng >= 100 && $lng <= 115);
            },
            
            // UTC+9 - Дальний Восток
            'yakutsk' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 70 && $lng >= 115 && $lng <= 130);
            },
            
            // UTC+10 - Дальний Восток
            'vladivostok' => function($lat, $lng) { 
                return ($lat >= 42 && $lat <= 50 && $lng >= 130 && $lng <= 140);
            },
            
            // UTC+11 - Камчатка, Магадан
            'magadan' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 70 && $lng >= 140 && $lng <= 160);
            },
            
            // UTC+12 - Камчатка
            'kamchatka' => function($lat, $lng) { 
                return ($lat >= 50 && $lat <= 70 && $lng >= 160) ||
                       ($lat >= 50 && $lat <= 70 && $lng <= -160);
            }
        ];
        
        // Проверяем каждый регион
        foreach ($regions as $region => $checkFunction) {
            if ($checkFunction($lat, $lng)) {
                switch ($region) {
                    case 'kaliningrad': return 2;
                    case 'moscow': return 3;
                    case 'samara': return 4;
                    case 'ekaterinburg': return 5;
                    case 'omsk': return 6;
                    case 'krasnoyarsk': return 7;
                    case 'irkutsk': return 8;
                    case 'yakutsk': return 9;
                    case 'vladivostok': return 10;
                    case 'magadan': return 11;
                    case 'kamchatka': return 12;
                }
            }
        }
        
        // Если не попали ни в один регион, используем приблизительный метод
        if ($lat >= 41 && $lat <= 82 && $lng >= 19) {
            // Определяем по долготе
            if ($lng <= 31) return 2;
            if ($lng <= 48) return 3;
            if ($lng <= 55) return 4;
            if ($lng <= 65) return 5;
            if ($lng <= 75) return 6;
            if ($lng <= 85) return 7;
            if ($lng <= 95) return 8;
            if ($lng <= 105) return 9;
            if ($lng <= 120) return 10;
            if ($lng <= 135) return 11;
            return 12;
        }
        
        return null; // Не Россия
    }

    public function getForecastValuesWeekFromTime($device_id, $date) {
        $today = DateTime::createFromFormat('Y-m-d', $date);
        $endDate = DateTime::createFromFormat('Y-m-d', $date)->modify('+9 days');
        $targetTimes = $this->getParams('time_grid');               
        $startDateTime = $today->format('Y-m-d 00:00:00');
        $endDateTime = $endDate->format('Y-m-d 23:59:59');       
        //return $endDateTime;
        // 1. Определяем список запрашиваемых параметров
        $required_params = ['2t', 'tcc', 'crain'];
        $forecastParamSettings = $this->getParams('forecast_parameters_for_day');
        
        $additional_params = [];
        if ($forecastParamSettings && is_array($forecastParamSettings)) {
            // Убираем обязательные параметры из настроек, если они там есть
            $additional_params = array_values(array_diff($forecastParamSettings, $required_params));
        }
        // Объединяем обязательные и дополнительные параметры
        $requested_params = array_merge($required_params, $additional_params);
        // 2. Получаем данные из БД
        $placeholders = str_repeat('?,', count($requested_params) - 1) . '?';
        $stmt = $this->db->prepare("
            SELECT * FROM devices_forecast_values 
            WHERE device_id = ? 
            AND parameter IN ($placeholders) 
            AND ref_time BETWEEN ? AND ?
            ORDER BY ref_time;
        ");
        
        $bindParams = array_merge([$device_id], $requested_params, [$startDateTime, $endDateTime]);
        $stmt->execute($bindParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Если данных нет, возвращаем пустой результат
        if (empty($data)) {
            // Можно также вернуть структуру с датами, но без items
            // или просто пустой массив. Выберем пустой массив для простоты.
            return []; 
        }
        // 3. Группируем по дате и времени
        $allDataByDate = [];
        foreach ($data as $item) {
            $refTime = $item['ref_time'];
            $date = date('Y-m-d', strtotime($refTime));
            $time = date('H:i', strtotime($refTime));

            if (!isset($allDataByDate[$date])) {
                $allDataByDate[$date] = [];                
            }
            if (!isset($allDataByDate[$date][$time])) {
                $allDataByDate[$date][$time] = [];
            }
            $allDataByDate[$date][$time][$item['parameter']] = $item['value'];
        }
    
        // 4. Определяем, какие параметры реально присутствуют в данных
        $actual_parameters = [];
        foreach ($data as $item) {
            if (!in_array($item['parameter'], $actual_parameters) && $item['value'] !== null) {
                 $actual_parameters[] = $item['parameter'];
            }
        }
        // Убедимся, что обязательные параметры всегда проверяются, 
        // даже если по ним все значения null (в этом случае они не попадут в финальный результат)
        $parameters_to_check = array_unique(array_merge($required_params, $requested_params, $actual_parameters));
        
        // 5. Определяем полный список возможных параметров для отображения
        $allDisplayParams = [
            '2t'      => ['name' => 'Температура воздуха', 'unit' => '°C',      'type' => 'simple'],
            'tcc'     => ['name' => 'Облачность',         'unit' => '%',       'type' => 'simple'],
            'crain'   => ['name' => 'Дождь',              'unit' => '',        'type' => 'simple'],
            '2r'      => ['name' => 'Влажность',          'unit' => '%',       'type' => '2r'],
            'tp'      => ['name' => 'Осадки мм',          'unit' => 'мм',      'type' => 'tp'],
            'dswrf'   => ['name' => 'Солн. излучение кВтч/м²', 'unit' => 'вт/м²', 'type' => 'dswrf'],
            'vis'     => ['name' => 'Видимость км',       'unit' => 'м',       'type' => 'vis'],
            'wind'    => ['name' => 'Ветер м/с',          'unit' => '',        'type' => 'wind'],
            // sp_10m и dd_10m обрабатываются внутри типа 'wind', 
            // но добавим их в список для проверки наличия данных
            'sp_10m'  => ['name' => 'Скорость ветра (для wind)', 'unit' => 'м/с', 'type' => 'auxiliary'],
            'dd_10m'  => ['name' => 'Направление ветра (для wind)', 'unit' => '°', 'type' => 'auxiliary'],
        ];
    
        // 6. Строим финальный список параметров для отображения, 
        // включая только те, по которым есть данные
        $displayParams = [];
        $has_wind_data = in_array('sp_10m', $actual_parameters) || in_array('dd_10m', $actual_parameters);
        
        foreach ($allDisplayParams as $paramKey => $paramConfig) {
            // Для вспомогательных параметров (wind components) не добавляем их напрямую
            if ($paramConfig['type'] === 'auxiliary') {
                continue;
            }
            
            // Для параметра 'wind' проверяем наличие sp_10m ИЛИ dd_10m
            if ($paramKey === 'wind') {
                if ($has_wind_data) {
                    $displayParams[$paramKey] = $paramConfig;
                }
                continue;
            }
            
            // Для остальных параметров проверяем, есть ли они в списке параметров с данными
            if (in_array($paramKey, $actual_parameters)) {
                $displayParams[$paramKey] = $paramConfig;
            }
        }
    
        // 7. Формируем результат
        $result = [];
        $currentDate = $today->format('Y-m-d');
    
        for ($i = 0; $i < 10; $i++) {
            $dateKey = $currentDate;
    
            $dayResult = [
                'date' => $dateKey,
                'items' => [] // ← Единый массив для всех параметров
            ];
            // Обрабатываем только те параметры, которые есть в displayParams
            foreach ($displayParams as $paramKey => $paramConfig) {
                $itemData = [
                    'key' => $paramKey,
                    'name' => $paramConfig['name'],
                    'unit' => $paramConfig['unit'],
                    'type' => $paramConfig['type'],
                    'times' => []
                ];
    
                foreach ($targetTimes as $targetTime) {
                    $foundTime = null;
                    $fullParameters = null;
    
                    // Ищем точное или ближайшее доступное время
                    if (isset($allDataByDate[$dateKey][$targetTime])) {
                        $foundTime = $targetTime;
                        $fullParameters = $allDataByDate[$dateKey][$targetTime];
                    } else {
                        $targetTimestamp = strtotime("$dateKey $targetTime");
                        if (isset($allDataByDate[$dateKey])) {
                            $availableTimes = array_keys($allDataByDate[$dateKey]);
                            foreach ($availableTimes as $availableTime) {
                                $availableTimestamp = strtotime("$dateKey $availableTime");
                                if ($availableTimestamp >= $targetTimestamp) {
                                    $foundTime = $availableTime;
                                    $fullParameters = $allDataByDate[$dateKey][$availableTime];
                                    break;
                                }
                            }
                        }
                        
                    }  
                    // Обработка разных типов параметров
                    if ($paramConfig['type'] === 'simple' || 
                        $paramConfig['type'] === '2r' || 
                        $paramConfig['type'] === 'vis' || 
                        $paramConfig['type'] === 'dswrf' || 
                        $paramConfig['type'] === 'tp') {                        
                       
                        $value = null;
                        if ($fullParameters !== null && isset($fullParameters[$paramKey])) {
                            $value = $fullParameters[$paramKey];
                        }
                        
                        if ($value !== null) {
                            // Обработка специфичных типов
                            if ($paramConfig['type'] === 'vis') {
                                $display = round($value / 1000 / 1000, 2);
                            } elseif ($paramConfig['type'] === 'dswrf') {
                                $display = round($value / 1000, 2);
                            } elseif ($paramConfig['type'] === 'tp') {
                                $display = round($value, 2);
                            } elseif ($paramConfig['type'] === '2r') {
                                $display = $value . ($paramConfig['unit'] ? ' ' . $paramConfig['unit'] : '');
                            } else {
                                $display = $value;
                            }
                        } else {
                            $display = '—';
                        }
                        
                        $itemData['times'][$targetTime] = [
                            'value' => $value,
                            'display' => $display
                        ];
                    }
                    // Обработка ветра
                    elseif ($paramConfig['type'] === 'wind') {
                        $speed = (isset($fullParameters['sp_10m']) && $fullParameters['sp_10m'] !== null) 
                            ? round($fullParameters['sp_10m']) 
                            : null;
                        $direction = $fullParameters['dd_10m'] ?? null;
    
                        $parts = [];
                        if ($speed !== null) $parts[] = "{$speed} м/с";
                        if ($direction !== null) $parts[] = "{$direction}°";
                        $display = !empty($parts) ? implode(', ', $parts) : '—';
                        
                        $itemData['times'][$targetTime] = [
                            'speed' => $speed,
                            'direction' => $direction,
                            'display' => $display
                        ];
                    }
                }
    
                $dayResult['items'][$paramKey] = $itemData;
            }
            
            $result[$dateKey] = $dayResult;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        return $result;
    }

    public function getForecastValuesWeekFromDayNight($device_id, $date) {
        $today = DateTime::createFromFormat('Y-m-d', $date);
        $startDateTime = $today->format('Y-m-d H:i:s');     
        $today->modify('+9 days');
        $endDateTime = $today->format('Y-m-d 23:59:59');        
        // Выбираем все данные за 10 дней
        $stmt = $this->db->prepare("
            SELECT * FROM devices_forecast_values 
            WHERE device_id = ? 
            AND parameter IN ('2t', 'tcc', 'crain') 
            AND ref_time >= ? AND ref_time <= ?
            ORDER BY ref_time;
        ");
        $stmt->execute([$device_id, $startDateTime, $endDateTime]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $groupedData = [];
    
        foreach ($data as $item) {
            $refTime = strtotime($item['ref_time']);
            $date = date('Y-m-d', $refTime);
            $hour = (int)date('H', $refTime);
            $parameter = $item['parameter'];
            $value = (float)$item['value'];
    
            // Определяем период: день или ночь            
            $periodSettings = $this->getParams('day_night_settings');
            $dayStart = isset($periodSettings['day_start']) ? $periodSettings['day_start'] : 9;
            $dayEnd = isset($periodSettings['day_end']) ? $periodSettings['day_end'] : 19;
            $dayStart = $this->normalizeTimeValue($dayStart);
            $dayEnd = $this->normalizeTimeValue($dayEnd);

            $period = ($hour >= $dayStart && $hour < $dayEnd) ? 'day' : 'night';
    
            // Если ночь — нас интересует только tcc
            if ($period === 'night' && $parameter !== '2t') {
                continue;
            }
    
            // Инициализируем день, если ещё не создан
            if (!isset($groupedData[$date])) {
                $groupedData[$date] = [
                    'date' => $date,
                    'day' => [
                        'parameters' => [
                            '2t' => [],
                            'tcc' => [],
                            'crain' => []
                        ]
                    ],
                    'night' => [
                        'parameters' => [
                            '2t' => []
                        ]
                    ]
                ];
            }
    
            // Добавляем значение в соответствующий массив для усреднения
            if ($period === 'day') {
                if (isset($groupedData[$date]['day']['parameters'][$parameter])) {
                    $groupedData[$date]['day']['parameters'][$parameter][] = $value;
                }
            } else { // night
                $groupedData[$date]['night']['parameters']['2t'][] = $value;
            }
        }
    
        // Теперь вычисляем средние значения
        foreach ($groupedData as $date => &$dayData) {
            // Обрабатываем дневной период
            foreach ($dayData['day']['parameters'] as $param => &$values) {
                $dayData['day']['parameters'][$param] = !empty($values) ? array_sum($values) / count($values) : null;
            }
    
            // Обрабатываем ночной период — только tcc
            $tccValues = $dayData['night']['parameters']['2t'];
            $dayData['night']['parameters']['2t'] = !empty($tccValues) ? array_sum($tccValues) / count($tccValues) : null;
    
            // Опционально: удаляем пустые периоды, если нужно
            // Например, если ночи ещё нет (для последнего дня), или данных нет
        }
    
        return $groupedData;
    }

    public function getCloudIcon($tcc) {
        if (empty($tcc) || $tcc < 0) {
            return '☀️'; 
        }    
        
        // Получаем настройки из базы данных
        $cloudSettings = $this->getParams('cloud_icon_settings');
       
        // Если настройки не найдены, используем значения по умолчанию
        if (!$cloudSettings || !is_array($cloudSettings)) {
            $settings = [
                'clear_sky' => 10,
                'partly_cloudy' => 50,
                'mostly_cloudy' => 80,
                'overcast' => 100
            ];
        } else {
            $settings = $cloudSettings;
        }
        
        $tcc = (int)$tcc;    
        
        if ($tcc <= $settings['clear_sky']) return '☀️';
        if ($tcc <= $settings['partly_cloudy']) return '🌤️';
        if ($tcc <= $settings['mostly_cloudy']) return '🌥️';
        if ($tcc <= $settings['overcast']) return '☁️';
        
        return '☀️'; // для значений > overcast
    }

    public function getRainIcon($rain) {
        if (empty($rain) || $rain < 0) {
            return '-'; 
        }
        
        // Получаем настройки из базы данных
        $rainSettings = $this->getParams('rain_icon_settings');
        
        // Если настройки не найдены, используем значения по умолчанию
        if (!$rainSettings || !is_array($rainSettings)) {
            $settings = [
                'no_rain_max' => 3,      // < 3
                'light_rain_max' => 5    // <= 5
            ];
        } else {
            $settings = $rainSettings;
        }
        
        $rain = (int)$rain * 24;
        
        if ($rain < $settings['no_rain_max']) return '☁️';
        if ($rain <= $settings['light_rain_max']) return '🌦️';
        return '🌧️'; // > light_rain_max
    }

    public function getParams($param){
        if (is_array($param)) {
            $columns = implode(', ', $param);
        } else {
            $columns = $param;
        }
        $sql = "SELECT " . $columns . " FROM global_settings";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Если запрашивался один параметр, возвращаем сразу его значение
        if (!is_array($param) && isset($data[0][$param])) {
            $value = $data[0][$param];
            if (is_string($value) && $this->isJson($value)) {
                return json_decode($value, true);
            }
            return $value;
        }
        
        // Если запрашивался массив параметров, возвращаем ассоциативный массив
        if (is_array($param) && !empty($data)) {
            $result = [];
            foreach ($param as $column) {
                if (isset($data[0][$column])) {
                    $value = $data[0][$column];
                    if (is_string($value) && $this->isJson($value)) {
                        $result[$column] = json_decode($value, true);
                    } else {
                        $result[$column] = $value;
                    }
                }
            }
            return $result;
        }
        
        // Для множественных записей возвращаем оригинальный массив
        return $data;
    }
    
    // Вспомогательная функция для проверки, является ли строка JSON
    private function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    // Вспомогательная функция для нормализации времени
    private function normalizeTimeValue($timeValue) {
        if (is_string($timeValue)) {
            // Если пришло время в формате "09:00" или "9:00", извлекаем часы
            if (strpos($timeValue, ':') !== false) {
                $parts = explode(':', $timeValue);
                return (int)$parts[0];
            }
            return (int)$timeValue;
        }
        return (int)$timeValue;
    }
    
    public function getArchiveYear($internal_id) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        
        $uid = $device['device_id'];

        try {
            $soil_db = $this->getSoilDB();

            $query = "SELECT DISTINCT YEAR(Date) as year FROM `soilsense` WHERE UID = ? ORDER BY year";                    
            $stmt = $soil_db->prepare($query);
            $stmt->execute([$uid]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: ".$e->getMessage());
            return [];
        }
    }

    public function getMeteoArchiveYear($internal_id) {
        $stmt = $this->db->prepare("SELECT `device_id` FROM `devices` WHERE id = ?");
        $stmt->execute([$internal_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['device_id'])) {
            error_log("Device UID not found for ID: $internal_id");
            return [];
        }
        
        $uid = $device['device_id'];

        try {
            $soil_db = $this->getSoilDB();

            $query = "SELECT DISTINCT YEAR(record_time) as year FROM `MeteoSense` WHERE station_id = ? ORDER BY year";                    
            $stmt = $soil_db->prepare($query);
            $stmt->execute([$uid]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error: ".$e->getMessage());
            return [];
        }
    }

}
