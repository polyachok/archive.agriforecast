<?php

class ServiceData {
    private $forecastDb;
    private $portalDb;
    
    public function __construct() {
        try {
            $this->forecastDb = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=forecast", 
                DB_USER, 
                DB_PASS
            );
            $this->forecastDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Ошибка подключения к базе данных forecast: " . $e->getMessage());
        }
        
        try {
            $this->portalDb = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=portal_db", 
                DB_USER, 
                DB_PASS
            );
            $this->portalDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Ошибка подключения к базе данных portal_db: " . $e->getMessage());
        }
    }
    
    public function getForecastConnection() {
        return $this->forecastDb;
    }
    
    public function getPortalConnection() {
        return $this->portalDb;
    }
    
    public function getSoilSenseDevices() {
        $sql = "SELECT device_id, name, contract_start_date, contract_end_date FROM devices WHERE device_type = 'VP' AND is_deleted = 0 ORDER BY name";
        $stmt = $this->portalDb->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMeteoSenseDevices() {
        $sql = "SELECT device_id, name, contract_start_date, contract_end_date FROM devices WHERE device_type = 'M' AND is_deleted = 0 ORDER BY name";
        $stmt = $this->portalDb->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSoilSenseData($deviceId, $startDate, $endDate) {
        $sql = "SELECT 
                    DATE_FORMAT(CONCAT(Date, ' ', Time), '%Y-%m-%d %H:00:00') as hour_slot,
                    FlName,
                    COUNT(*) as count
                FROM 
                    SoilSense 
                WHERE 
                    UID = :deviceId 
                    AND CONCAT(Date, ' ', Time) BETWEEN :startDate AND :endDate
                GROUP BY 
                    hour_slot, 
                    FlName
                ORDER BY 
                    hour_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        
        foreach ($results as $row) {
            $hourSlot = $row['hour_slot'];
            $isInterpolated = strpos($row['FlName'], 'Interpolation') !== false;
            
            if (!isset($data[$hourSlot])) {
                $data[$hourSlot] = [
                    'timestamp' => strtotime($hourSlot),
                    'hour_slot' => $hourSlot,
                    'has_data' => true,
                    'is_interpolated' => $isInterpolated
                ];
            } else if (!$isInterpolated && $data[$hourSlot]['is_interpolated']) {
                $data[$hourSlot]['is_interpolated'] = false;
            }
        }
        
        usort($data, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        return array_values($data);
    }
    
    public function getMeteoSenseData($deviceId, $startDate, $endDate) {
        $sql = "SELECT 
                    DATE_FORMAT(t1.record_time, '%Y-%m-%d %H:%i:00') as time_slot,
                    t1.file_name
                FROM 
                    MeteoSense t1
                JOIN (
                    SELECT
                        station_id,
                        DATE_FORMAT(record_time, '%Y-%m-%d %H:00:00') as hour_start,
                        MIN(record_time) as first_record_in_hour
                    FROM
                        MeteoSense
                    WHERE
                        station_id = :deviceId
                        AND record_time BETWEEN :startDate AND :endDate
                    GROUP BY
                        station_id,
                        hour_start
                ) t2 ON t1.station_id = t2.station_id AND t1.record_time = t2.first_record_in_hour
                WHERE 
                    t1.station_id = :deviceId 
                    AND t1.record_time BETWEEN :startDate AND :endDate
                ORDER BY 
                    time_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        
        foreach ($results as $row) {
            $timeSlot = $row['time_slot'];
            $isInterpolated = strpos($row['file_name'], 'Interpolation') !== false;
            
            if (!isset($data[$timeSlot])) {
                $data[$timeSlot] = [
                    'timestamp' => strtotime($timeSlot),
                    'time_slot' => $timeSlot,
                    'has_data' => true,
                    'is_interpolated' => $isInterpolated
                ];
            } else if (!$isInterpolated && $data[$timeSlot]['is_interpolated']) {
                $data[$timeSlot]['is_interpolated'] = false;
            }
        }

        usort($data, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        return array_values($data);
    }

    public function getTimeIntervals($startDate, $endDate, $deviceType) {
        $utcTimezone = new DateTimeZone('UTC');
        $start = new DateTime($startDate, $utcTimezone);
        $end = new DateTime($endDate, $utcTimezone);
        $interval = new DateInterval('PT1H'); 
        
        $periods = [];
        $current = clone $start;
        
        while ($current <= $end) {
            $timeSlot = $current->format('Y-m-d H:i:00');
            $periods[$timeSlot] = [
                'timestamp' => $current->getTimestamp(),
                'time_slot' => $timeSlot,
                'has_data' => false,
                'is_interpolated' => false
            ];
            $current->add($interval);
        }
        
        return $periods;
    }
    
    public function mergeIntervalsWithData($intervals, $data) {
        foreach ($data as $item) {
            $timeSlot = $item['hour_slot'] ?? $item['time_slot'];
            if (isset($intervals[$timeSlot])) {
                $intervals[$timeSlot] = $item;
            }
        }
        
        uasort($intervals, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        return array_values($intervals);
    }
    
    public function checkDeviceDataExists($deviceId, $deviceType) {
        if ($deviceType === 'soil') {
            $sql = "SELECT COUNT(*) as count FROM SoilSense WHERE UID = ? LIMIT 1";
        } else {
            $sql = "SELECT COUNT(*) as count FROM MeteoSense WHERE station_id = ? LIMIT 1";
        }
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->execute([$deviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    public function getDeviceInfo($deviceId) {
        $sql = "SELECT * FROM devices WHERE device_id = ? AND is_deleted = 0";
        $stmt = $this->portalDb->prepare($sql);
        $stmt->execute([$deviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function generateHumidityStatistics($deviceId, $startDateTime, $endDateTime) {
        $deviceInfo = $this->getDeviceInfo($deviceId);
        if (!$deviceInfo || $deviceInfo['device_type'] !== 'VP') {
            throw new Exception("Устройство не найдено или не является прибором влажности");
        }
        
        $contractStartDate = isset($deviceInfo['contract_start_date']) 
            ? DateTime::createFromFormat('Y-m-d', $deviceInfo['contract_start_date'])->format('d.m.Y') 
            : '01.05.2025';
        
        $contractEndDate = isset($deviceInfo['contract_end_date']) 
            ? DateTime::createFromFormat('Y-m-d', $deviceInfo['contract_end_date'])->format('d.m.Y') 
            : '01.10.2025';
        
        $calculatedHours = $this->calculateContractHours($startDateTime, $endDateTime);
        
        $actualHours = $this->getActualWorkingHours($deviceId, $startDateTime, $endDateTime);
        
        $missingHours = max(0, $calculatedHours - $actualHours);
        
        $maxInterruption = $this->getMaxInterruptionDuration($deviceId, $startDateTime, $endDateTime);
        
        $interruptionCount = $this->getInterruptionCount($deviceId, $startDateTime, $endDateTime);
        
        $avgInterruption = $this->getAverageInterruptionDuration($deviceId, $startDateTime, $endDateTime);
        
        $stats = [
            [
                'name' => 'Станция находится в эксплуатации согласно договора',
                'value' => $contractStartDate
            ],
            [
                'name' => 'Конец сезонной эксплуатации в рамках договора',
                'value' => $contractEndDate
            ],
            [
                'name' => 'Период анализа активности',
                'value' => 'с ' . date('d.m.Y H:i', strtotime($startDateTime)) . ' по ' . date('d.m.Y H:i', strtotime($endDateTime))
            ],
            [
                'name' => 'Расчетное количество часов работы станции влажности почвы',
                'value' => $calculatedHours . ' ч.'
            ],
            [
                'name' => 'Фактическое количество часов работы станции влажности почвы',
                'value' => $actualHours . ' ч.'
            ],
            [
                'name' => 'Количество часов отсутствия данных от станции влажности почвы',
                'value' => $missingHours . ' ч.'
            ],
            [
                'name' => 'Максимальная продолжительность перерыва в передаче данных',
                'value' => $maxInterruption . ' ч.'
            ],
            [
                'name' => 'Количество перерывов в передаче данных от оборудования',
                'value' => $interruptionCount
            ],
            [
                'name' => 'Среднее значение длительности перерыва в передаче данных',
                'value' => $avgInterruption . ' ч.'
            ]
        ];

        return $stats;
    }
    
    public function generateMeteoStatistics($deviceId, $startDateTime, $endDateTime) {
        $deviceInfo = $this->getDeviceInfo($deviceId);
        if (!$deviceInfo || $deviceInfo['device_type'] !== 'M') {
            throw new Exception("Устройство не найдено или не является метеостанцией");
        }
        
        $contractStartDate = isset($deviceInfo['contract_start_date']) 
            ? DateTime::createFromFormat('Y-m-d', $deviceInfo['contract_start_date'])->format('d.m.Y') 
            : '01.05.2025';
        
        $contractEndDate = isset($deviceInfo['contract_end_date']) 
            ? DateTime::createFromFormat('Y-m-d', $deviceInfo['contract_end_date'])->format('d.m.Y') 
            : '30.04.2026';
        
        $calculatedHours = $this->calculateContractHours($startDateTime, $endDateTime);
        
        $actualHours = $this->getMeteoActualWorkingHours($deviceId, $startDateTime, $endDateTime);
        
        $missingHours = max(0, $calculatedHours - $actualHours);
        
        $maxInterruption = $this->getMeteoMaxInterruptionDuration($deviceId, $startDateTime, $endDateTime);
        
        $interruptionCount = $this->getMeteoInterruptionCount($deviceId, $startDateTime, $endDateTime);
        
        $avgInterruption = $this->getMeteoAverageInterruptionDuration($deviceId, $startDateTime, $endDateTime);
        
        $stats = [
            [
                'name' => 'Станция находится в эксплуатации согласно договора',
                'value' => $contractStartDate
            ],
            [
                'name' => 'Конец сезонной эксплуатации в рамках договора',
                'value' => $contractEndDate
            ],
            [
                'name' => 'Период анализа активности',
                'value' => 'с ' . date('d.m.Y H:i', strtotime($startDateTime)) . ' по ' . date('d.m.Y H:i', strtotime($endDateTime))
            ],
            [
                'name' => 'Расчетное количество часов работы метеостанции',
                'value' => $calculatedHours . ' ч.'
            ],
            [
                'name' => 'Фактическое количество часов работы метеостанции',
                'value' => $actualHours . ' ч.'
            ],
            [
                'name' => 'Количество часов отсутствия данных от метеостанции',
                'value' => $missingHours . ' ч.'
            ],
            [
                'name' => 'Максимальная продолжительность перерыва в передаче данных',
                'value' => $maxInterruption . ' ч.'
            ],
            [
                'name' => 'Количество перерывов в передаче данных от оборудования',
                'value' => $interruptionCount
            ],
            [
                'name' => 'Среднее значение длительности перерыва в передаче данных',
                'value' => $avgInterruption . ' ч.'
            ]
        ];

        return $stats;
    }
    
    private function calculateContractHours($startDateTime, $endDateTime) {
        $utcTimezone = new DateTimeZone('UTC');
        $start = new DateTime($startDateTime, $utcTimezone);
        $end = new DateTime($endDateTime, $utcTimezone);
    
        $totalSeconds = $end->getTimestamp() - $start->getTimestamp();
        return round($totalSeconds / 3600); 
    }

    private function getActualWorkingHours($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    COUNT(DISTINCT DATE_FORMAT(CONCAT(Date, ' ', Time), '%Y-%m-%d %H:00:00')) as hours_count
                FROM 
                    SoilSense 
                WHERE 
                    UID = :deviceId 
                    AND CONCAT(Date, ' ', Time) BETWEEN :startDate AND :endDate";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime);
        $stmt->bindParam(':endDate', $endDateTime);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['hours_count'];
    }
    
    private function getMeteoActualWorkingHours($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    COUNT(DISTINCT DATE_FORMAT(record_time, '%Y-%m-%d %H:00:00')) as hours_count
                FROM 
                    MeteoSense 
                WHERE 
                    station_id = :deviceId 
                    AND record_time BETWEEN :startDate AND :endDate";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime);
        $stmt->bindParam(':endDate', $endDateTime);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['hours_count'];
    }
    
    private function getMaxInterruptionDuration($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(CONCAT(Date, ' ', Time), '%Y-%m-%d %H:00:00') as hour_slot
                FROM 
                    SoilSense 
                WHERE 
                    UID = :deviceId 
                    AND CONCAT(Date, ' ', Time) BETWEEN :startDate AND :endDate
                ORDER BY 
                    hour_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime); 
        $stmt->bindParam(':endDate', $endDateTime); 
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $maxGap = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600 - 1; 
            
            if ($gap > $maxGap) {
                $maxGap = $gap;
            }
        }
        
        return max(0, $maxGap);
    }
    
    private function getMeteoMaxInterruptionDuration($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(record_time, '%Y-%m-%d %H:00:00') as time_slot
                FROM 
                    MeteoSense 
                WHERE 
                    station_id = :deviceId 
                    AND record_time BETWEEN :startDate AND :endDate
                ORDER BY 
                    time_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime);
        $stmt->bindParam(':endDate', $endDateTime); 
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $maxGap = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600 - 1; 
            
            if ($gap > $maxGap) {
                $maxGap = $gap;
            }
        }
        
        return max(0, round($maxGap, 2));
    }
    
    private function getInterruptionCount($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(CONCAT(Date, ' ', Time), '%Y-%m-%d %H:00:00') as hour_slot
                FROM 
                    SoilSense 
                WHERE 
                    UID = :deviceId 
                    AND CONCAT(Date, ' ', Time) BETWEEN :startDate AND :endDate
                ORDER BY 
                    hour_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime);
        $stmt->bindParam(':endDate', $endDateTime); 
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $interruptionCount = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600; 
            
            if ($gap > 1) {
                $interruptionCount++;
            }
        }
        
        return $interruptionCount;
    }
    
    private function getMeteoInterruptionCount($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(record_time, '%Y-%m-%d %H:00:00') as time_slot
                FROM 
                    MeteoSense 
                WHERE 
                    station_id = :deviceId 
                    AND record_time BETWEEN :startDate AND :endDate
                ORDER BY 
                    time_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime);
        $stmt->bindParam(':endDate', $endDateTime); 
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $interruptionCount = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600; 
            
            if ($gap > 1) {
                $interruptionCount++;
            }
        }
        
        return $interruptionCount;
    }
    
    private function getAverageInterruptionDuration($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(CONCAT(Date, ' ', Time), '%Y-%m-%d %H:00:00') as hour_slot
                FROM 
                    SoilSense 
                WHERE 
                    UID = :deviceId 
                    AND CONCAT(Date, ' ', Time) BETWEEN :startDate AND :endDate
                ORDER BY 
                    hour_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime); 
        $stmt->bindParam(':endDate', $endDateTime);
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $totalGap = 0;
        $interruptionCount = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600 - 1;
            
            if ($gap > 0) {
                $totalGap += $gap;
                $interruptionCount++;
            }
        }
        
        return $interruptionCount > 0 ? round($totalGap / $interruptionCount, 2) : 0;
    }
    
    private function getMeteoAverageInterruptionDuration($deviceId, $startDateTime, $endDateTime) {
        $sql = "SELECT 
                    DISTINCT DATE_FORMAT(record_time, '%Y-%m-%d %H:00:00') as time_slot
                FROM 
                    MeteoSense 
                WHERE 
                    station_id = :deviceId 
                    AND record_time BETWEEN :startDate AND :endDate
                ORDER BY 
                    time_slot";
        
        $stmt = $this->forecastDb->prepare($sql);
        $stmt->bindParam(':deviceId', $deviceId);
        $stmt->bindParam(':startDate', $startDateTime); 
        $stmt->bindParam(':endDate', $endDateTime);   
        $stmt->execute();
        
        $dataSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dataSlots) < 2) {
            return 0;
        }
        
        $totalGap = 0;
        $interruptionCount = 0;
        $utcTimezone = new DateTimeZone('UTC'); 
        
        for ($i = 1; $i < count($dataSlots); $i++) {
            $prevTime = new DateTime($dataSlots[$i-1], $utcTimezone);
            $currTime = new DateTime($dataSlots[$i], $utcTimezone);
            $gap = ($currTime->getTimestamp() - $prevTime->getTimestamp()) / 3600 - 1; 
            
            if ($gap > 0) {
                $totalGap += $gap;
                $interruptionCount++;
            }
        }
        
        return $interruptionCount > 0 ? round($totalGap / $interruptionCount, 2) : 0;
    }
}
