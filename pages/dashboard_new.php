<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/Organization.php';

$user = new User();
$current_user = $user->getUser($_SESSION['user_id']);
$user_role = $_SESSION['role'];
$device = new Device($current_year);
$organization = new Organization($current_year);
$devices = [];
$filters = [
    'organization' => $_GET['organization'] ?? ''    
];

if ($_SESSION['role'] == ROLE_ADMIN){
    $organizations = $organization->getAllOrganizations();   
    if($filters['organization'] != '' && $filters['organization'] != 0){  
       if($organization->isDealer($filters['organization'])){
            $devicesDealer = $device->getDealerDevices($filters['organization'],$current_user['device_order']);
            $devicesOrg = $device->getOrganizationDevices($filters['organization'],$current_user['device_order']); 
            $devices = array_merge($devicesDealer, $devicesOrg);                           
       }else{
            $devices = $device->getOrganizationDevices($filters['organization'],$current_user['device_order']);                           
       }
    }else if($filters['organization'] == 0){
        $devices = $device->getAllDevices($current_user['device_order']);
    } else{
        $devices = $device->getAllDevices($current_user['device_order']);
    }                        
} elseif ($_SESSION['role'] == ROLE_DEALER){
    $dealer_org = $user->getUserOrganization($_SESSION['user_id']);
   
    if ($dealer_org) {
        $devicesDealer = $device->getDealerDevices($dealer_org['id'], $current_user['device_order']);
    }
    $organizations = $organization->getDealerClients($dealer_org['id']);  
    if($filters['organization'] != '' && $filters['organization'] != 0){   
        $devicesOrg = $device->getOrganizationDevices($filters['organization'],$current_user['device_order']);
        $devices = array_merge($devicesDealer,$devicesOrg);  
    }else if($filters['organization'] == 0){
        $devices = $devicesDealer;
    }  
    else{
        $devices = $devicesDealer; 
    }  
} else {
    $devices = $device->getUserDevices($_SESSION['user_id'], $current_user['device_order']);
}       

function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function checkForecast($device){
    if (!$device['is_forecast_enabled']) {
        return 'Нет данных прогноза.';
    }
    if (empty($device['coordinates'])) {
        return 'Не указаны координаты прибора.';
    }
}
?>

<?php include ROOT_PATH . '/includes/header.php'; ?>
    <style>        
        .sensor-widget {
            /*width: 410px;
            height: 180px;*/
            border-radius: 10px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .left-panel {
            width: 70%;
            padding: 10px 10px 10px 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;           
        }
        .right-panel {
            width: 30%;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            padding: 12px 0 0 0;            
        }
        .sensor-name,
        .sensor-date,
        .sensor-value,
        .sensor-status {
            margin: 4px 0;
            line-height: 1.1;
        }
        .sensor-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
            color: #6f6b6b;
            line-height: 1.2;
        }
        .sensor-date {
            font-size: 12px;
           /* color: rgba(255, 255, 255, 0.8);*/
            margin-bottom: 8px;
            line-height: 1.2;
        }
        .sensor-value-name {
            margin: 4px 0;
            color: #6f6b6b;
            line-height: 1.2;
            font-size: 12px;
        }
        .sensor-value {
            margin: 4px 0;
            color: #6f6b6b;
            line-height: 1.2;
            font-size: 16px;
        }
        .sensor-status {
            font-size: 1em;
            font-weight: bold;
            color: #6f6b6b;
            margin-top: 8px;
            line-height: 1.2;
        }
        /* === КРУГОВАЯ ДИАГРАММА: SVG ВНУТРИ === */
        .gauge-container {
            position: relative;
            width: 60px;
            height: 60px;
        }
        .gauge-svg {
            position: absolute;
            top: 0px;
            left: 3px;
            width: 94%;
            height: 100%;
        }
        .gauge-marks {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 3;
        }
        .gauge-mark {
            position: absolute;
            width: 2px;
            height: 3px;
            background: #5d5c5c;
            left: 50%;
            top: 0;
            transform-origin: 50% 30px;
            transform: translateX(-50%);
        }
        .gauge-value {
            position: absolute;
            top: 50%;
            left: 53%;
            transform: translate(-50%, -50%);
            color: #6f6b6b;
            font-weight: bold;
            font-size: 14px;
            z-index: 2;
        }
        /* === ТЕРМОМЕТР === */
        .thermometer-container {
            position: relative;
            width: 40px;
            height: 100px;
        }
        .thermometer {
            width: 7px;
            height: 50px;
            border: 2px solid #6e7c7b;
            border-radius: 10px;
            position: absolute;
            left: 15px;
            bottom: 24px;
            overflow: hidden;
        }
        .thermometer-bulb {
            width: 17px;
            height: 17px;
            border: 2px solid #6e7c7b;
            border-radius: 50%;
            position: absolute;
            bottom: 11px;
            left: 10px;
            background-color: #6e7c7b;
        }
        .thermometer-fill {
            width: 100%;
            background: #6e7c7b;
            position: absolute;
            bottom: 0;
            transition: height 0.5s;
        }
        .thermometer-value {
            position: absolute;
           /* right: -19px;*/
            margin-left: 26px;
            top: 21px;
            color: #6f6b6b;
            font-weight: bold;
            font-size: 15px;
        }

        /* === ЦВЕТА СТАТУСОВ === */
       /* .status-normal { background-color: #4CAF50; }
        .status-normal .left-panel { background-color: #4CAF50; }
        .status-normal .right-panel { background-color: #33aa39; }*/
        .status-normal .left-panel { background-color: rgba(75, 192, 192, 0.2); }
        .status-normal .right-panel { background-color: rgba(75, 192, 192, 0.4); }

        /*.status-warning { background-color: #FFC107; }
        .status-warning .left-panel { background-color: #FFC107; }
        .status-warning .right-panel { background-color: #f3b807; }*/

        .status-warning .left-panel { background-color: rgba(255, 206, 86, 0.2); }
        .status-warning .right-panel { background-color: rgba(255, 206, 86, 0.4); }       

        /*.status-danger { background-color: #F44336; }*/
        .status-error .left-panel { background-color: rgba(241, 30, 10, 0.70); }
        .status-error .right-panel { background-color: rgba(241, 30, 10, 0.85); }
        .status-error .sensor-name { color: white; }
        .status-error .sensor-date { color: white; }
        .status-error .sensor-value { color: white; }
        .status-error .sensor-value-name { color: white; }
        .status-error .sensor-status { color: white; }

        .status-error .thermometer-value { color: white; }
        .status-error .thermometer {border: 2px solid white;}
        .status-error .thermometer-bulb {border: 2px solid white; background: white;}
        .status-error .gauge-mark {background: white;}
        .status-error .gauge-value {color: white;}
        
        .status-danger .left-panel { background-color: rgba(235, 69, 54, 0.2); }
        .status-danger .right-panel { background-color:rgba(235, 69, 54, 0.4); }

        /*.status-info { background-color: #2196F3; }
        .status-info .left-panel { background-color: #2196F3; }
        .status-info .right-panel { background-color: #1976D2; }*/

        .status-info .left-panel { background-color: rgba(54, 162, 235, 0.2); }
        .status-info .right-panel { background-color: rgba(54, 162, 235, 0.4); }

        /*.status-inactive { background-color: #9E9E9E; }
        .status-inactive .left-panel { background-color: #9E9E9E; }
        .status-inactive .right-panel { background-color: #757575; }*/

        .status-inactive .left-panel { background-color: rgba(195, 199, 201, 0.2); }
        .status-inactive .right-panel { background-color: rgba(195, 199, 201, 0.2); }        

        .meteo-sensor-widget {
            border-radius: 10px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
            margin-bottom: 20px;
            cursor: pointer;
            height: 90%;
            background-color: rgba(54, 162, 235, 0.2);
        }

        /* Левая панель */
        .meteo-left-panel {
            width: 85%;
            display: flex;
            flex-direction: column;
        }

        /* Верхняя секция (горизонтальная) */
        .meteo-top-section {
            display: flex;
            height: 25%;
        }

        .meteo-left-top {
            display: flex;
            flex-direction: column;
            padding: 10px 0 0 10px;
        }

        .meteo-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .meteo-date {
            font-size: 12px;
            color: #666;
            margin: 0;
            line-height: 0.7;
        }

        .meteo-right-top {
            width: 55%;
            display: flex;
            padding: 0 0 0 0;
        }

        .temp-container {
            display: flex;
            flex-direction: row;
            width: 50%;
            /* align-content: stretch; */
            justify-content: flex-end;
            align-items: flex-start;
        }

        .temp-bottom {
            display: flex;
            justify-content: center;
        }

        .current-temp {
            font-size: 37px;
            font-weight: bold;            
        }
        .temp-unit {
            margin-top: 10px;
            font-weight: 700;
        }

        .temp-left {
            font-size: 11px;
            font-weight: 700;
        }

        .temp-right {
            font-size: 11px;
            font-weight: 700;
            margin-left: 5px;
        }

        .temp-extra {
            font-size: 10px;
            font-weight: 700;
        }

        .weather-icon-big {
            font-size: 38px;
            margin-left: 3px;           
        }
        .meteo-middle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 30%;
        }
        .meteo-center-section {
            display: flex;
            padding-left: 10px;
            flex-direction: column;
        }

        /* Нижняя секция (5 дней прогноза) */
        .meteo-bottom-section {
            display: flex;
            justify-content: space-between;
            height: 30%;
            padding-left: 10px;
            border-top: 1px solid #8d9091;
        }

        .daily-forecast-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-around;
            width: 18%;
        }

        .forecast-date {
            font-size: 12px;
            text-align: center;
            color: #555;
        }

        .forecast-icon {
            font-size: 20px;  
        }

        .forecast-temp {
            font-size: 14px;
            font-weight: bold;
        }

        /* Правая панель */
        .meteo-right-panel {
            width: 30%;
            display: flex;
            flex-direction: column;
            padding: 10px;
            border-left: 1px solid #8d9091;
        }

        .time-forecast-item {
            display: flex;
            height: 25%;   
        }

        .time-item-left {
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .time-item-right {
            width: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
        }

        .forecast-time {
            font-size: 10px;
            color: #666;
        }

        .forecast-icon-small {
            font-size: 15px;  
            margin-left: 6px;  
        }

        .weather-no-data {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            font-size: 12px;
        }
    </style>
    <div class="columns">
        <div class="column col-3 hide-xs">
            <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
        </div>
        
        <div class="column col-9 col-xs-12">
            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>    
                <form method="GET" class="compact-filters">
                    <div class="columns">
                        <div class="column col-4">
                            <div class="form-group">
                                <label class="form-label" for="organization">Организации:</label>
                                <select class="form-select" id="organization" name="organization" onchange="this.form.submit()">                                       
                                    <option value="0" <?= $filters['organization'] == 0 ? 'selected' : '' ?> selected>Все</option>
                                    <?php foreach ($organizations as $org):?>                                            
                                        <option value="<?= safeHtml($org['id']) ?>"
                                        <?= $filters['organization'] == $org['id'] ? 'selected' : '' ?>>
                                            <?= safeHtml($org['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>                                    
                            </div>
                        </div>                            
                    </div>                        
                </form>
            <?php elseif ($_SESSION['role'] == ROLE_DEALER):?>
                <form method="GET" class="compact-filters">
                    <div class="columns">
                        <div class="column col-4">
                            <div class="form-group">
                                <label class="form-label" for="organization">Организации:</label>
                                <select class="form-select" id="organization" name="organization" onchange="this.form.submit()">
                                    <option value="">Выберите организацию:</option>  
                                    <option value="0" <?= $filters['organization'] == 0 ? 'selected' : '' ?>>Все приборы</option>
                                    <?php foreach ($organizations as $org):?>                                            
                                        <option value="<?= safeHtml($org['id']) ?>"
                                        <?= $filters['organization'] == $org['id'] ? 'selected' : '' ?>>
                                            <?= safeHtml($org['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>                                    
                            </div>
                        </div>                                 
                    </div>                        
                </form>
            <?php else :?>
            <?php endif;?>            
            <div class="columns">
                <?php            
                    $dev = new Device();
                    if($current_user['device_order'] == 2) {                            
                        $devicesWithError = [];
                        $devicesWithoutError = []; 
                        $deviceInActive = [];
                        foreach ($devices as $device){                                
                            if($device['device_type'] == 'VP') {                                  
                                $soil_data = $dev->getPeriodSoilData($device['id'], $current_date); 
                                $last_data = $soil_data[0];                                     
                                $error = checkDeviceError($last_data);
                                if(isYesterday($last_data['Date'])){                                            
                                    $deviceInActive[] = $device;
                                }else{
                                    if($error){                                       
                                        if(isYesterday($last_data['Date'])){                                            
                                            $deviceInActive[] = $device;
                                        }else{
                                            $devicesWithError[] = $device;
                                        }
                                        
                                    }else{
                                        $devicesWithoutError[] = $device;  
                                    }
                                }
                                
                                $devices = array_merge($devicesWithError, $deviceInActive, $devicesWithoutError);   
                            }   
                        }
                    }         
                    foreach ($devices as $device): 
                        print_r($device);          
                        if($device['device_type'] == 'VP'):                            
                            $soil_data = $dev->getPeriodSoilData($device['id'], $current_date); 
                            $timezone = $_COOKIE['user_timezone'] ?? 'UTC';
                            $average_humidity = '';
                            $average_temp = '';
                            $zone_status = '';
                            $formattedDate = '-';               
                            $formattedTime = '-';
                            $last_data = null;
                            $deviceError = '';
                            if(!empty($soil_data)){
                                $last_data = $soil_data[0]; 
                                $humidity_count = $device['humidity_count'] ?? 0;
                                $average_humidity = calculateAverage($last_data, $humidity_count, 'humidity_');
                                $average_temp = calculateAverage($last_data, $humidity_count, 'temp_');
                                $zone_humidity = calculateSum($last_data, $humidity_count, 'humidity_');
                                $zone_status = getStatusClass($zone_humidity, $device);     
                                $deviceError = checkDeviceError($last_data); 
                                $dateTime = new DateTime("{$last_data['Date']} {$last_data['Time']}", new DateTimeZone('UTC'));
                                $formattedDate = $dateTime->format('d.m.y');                
                                $formattedTime = $dateTime->format('H:i'); 
                            }
                ?>
                            <div class="column col-4 col-xs-12">                      
                                <div class="sensor-widget status-inactive" 
                                data-type="VP"
                                data-humidity="<?php echo htmlspecialchars($average_humidity)?>" 
                                data-temperature="<?php echo htmlspecialchars($average_temp)?>" 
                                data-status="<?php echo htmlspecialchars($zone_status)?>" 
                                data-device="<?php echo htmlspecialchars(json_encode($device)) ?>"
                                data-last="<?php echo htmlspecialchars(json_encode($last_data)) ?>"
                                data-status-text=""
                                data-error="<?php echo htmlspecialchars(json_encode($deviceError)) ?>">
                                    <div class="left-panel">
                                        <div>
                                            <p class="sensor-name"><?php echo htmlspecialchars($device['name']); ?></p>
                                            <p class="sensor-date">Данные от: <?php echo htmlspecialchars($formattedDate);?> в <?php echo htmlspecialchars($formattedTime); ?></p>
                                        </div>
                                        <div>
                                            <p class="sensor-value-name">Влажн. почвы: <span class="sensor-value humidity-value"></span></p>
                                            <p class="sensor-value-name" style="display:none">Темп. почвы: <span class="sensor-value temp-value"></span></p>
                                        </div>
                                        <div>
                                            <p class="sensor-status"></p>
                                        </div>
                                    </div>
                                    <div class="right-panel">
                                        <!-- Круговая диаграмма -->
                                        <div class="gauge-container">
                                            <svg class="gauge-svg" viewBox="0 0 60 60">                                      
                                                <circle
                                                    cx="30"
                                                    cy="30"
                                                    r="26"
                                                    fill="none"
                                                    stroke="#6e7c7b"
                                                    stroke-width="3"
                                                    stroke-linecap="round"
                                                    stroke-dasharray="163.36"
                                                    stroke-dashoffset="163.36"
                                                    class="humidity-gauge"
                                                    transform="rotate(-90 30 30)"
                                                />
                                            </svg>
                                            <div class="gauge-marks"></div>
                                            <div class="gauge-value">65%</div>
                                        </div>
                                        <!-- Термометр -->
                                        <div class="thermometer-container">
                                            <div class="thermometer">
                                                <div class="thermometer-fill"></div>
                                            </div>
                                            <div class="thermometer-bulb"></div>
                                            <div class="thermometer-value">24°C</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif($device['device_type'] == 'M'):   
                            $soil_data = $dev->getLastMeteoData($device['id'], $current_date);   
                            $forecastValuesDay = $dev->getForecastValuesDay($device['id'], $current_date);
                            $forecastValueWeek = $dev->getForecastValuesWeek($device['id'], $current_date);
                            $forecastValueNow = $dev->getForecastValuesNow($device['id'], $current_date);
                            $timezone = $_COOKIE['user_timezone'] ?? 'UTC';  
                            $formattedDate = '';                
                            $formattedTime = '';
                            $humidity = '';
                            $temp = '';
                            $ref_time = '';
                            $wind = '';
                            $wind_speed = '';
                            $humidity_value = '';                     
                            if(!empty($soil_data)){   
                                $last_data = $soil_data[0];   
                                $ref_time = $last_data['ref_time'];                      
                                $dateTime = new DateTime($ref_time);
                                //$dateTime->setTimezone(new DateTimeZone($timezone));                        
                                $formattedDate = $dateTime->format('d.m.y') .' в ';                                                    
                                $formattedTime = $dateTime->format('H:i'); 
                                $temp = round($last_data['air_temperature']);
                                if(!empty($forecastValueNow['parameters'])){
                                    if($forecastValueNow['parameters']['crain'] > 0){
                                        $humidity = $dev->getRainIcon($forecastValueNow['parameters']['crain']);
                                    }else{
                                    $humidity = $dev->getCloudIcon($forecastValueNow['parameters']['tcc']);
                                    }                                    
                                }
                                
                                $wind = getWind($last_data['wind_direction']);
                                $wind_speed = $last_data['wind_speed'] .' м/с';
                                $humidity_value = isset($last_data['humidity']) ? round($last_data['humidity']) .' %' : '-';
                            }                   
                        ?>      
                            <div class="column col-4 col-xs-12">                      
                                <div class="meteo-sensor-widget status-info"  
                                    data-type="M"                     
                                    data-device="<?php echo htmlspecialchars(json_encode($device)) ?> "
                                    data-last="<?php echo htmlspecialchars(json_encode($soil_data)) ?> "
                                    data-time="<?php echo htmlspecialchars($ref_time) ?>"
                                >
                                    <div class="meteo-left-panel">
                                        <!-- Верхняя часть -->
                                        <div class="meteo-top-section">
                                            <!-- Левый верхний блок (имя и дата) -->
                                            <div class="meteo-left-top">
                                                <p class="meteo-name"><?php echo htmlspecialchars($device['name']); ?></p>
                                                <p class="meteo-date"><?php echo htmlspecialchars($formattedDate);?> <?php echo htmlspecialchars($formattedTime); ?></p>
                                            </div>                                                
                                        </div>  
                                        <div class="meteo-middle-row">
                                            <div class="meteo-center-section"> 
                                                <div class="temp-left">
                                                    💨 <?php echo htmlspecialchars($wind); ?> <?php echo htmlspecialchars($wind_speed); ?>
                                                </div>
                                                <div class="temp-right">
                                                    💧 <?php echo htmlspecialchars($humidity_value); ?>
                                                </div>                                                    
                                            </div>

                                            <!-- Правый верхний блок -->
                                            <div class="meteo-right-top">
                                                <div class="temp-container">
                                                    <span class="current-temp"><?php echo htmlspecialchars($temp); ?></span>
                                                    <?php echo $temp != '' ? '<span class="temp-unit">°C</span>' : '-'; ?>     
                                                </div>    
                                                <div class="weather-icon-big">
                                                    <?php echo $humidity;?>
                                                </div>
                                            </div>
                                        </div>                                         
                                        <!-- Нижняя часть -->
                                        <div class="meteo-bottom-section">                                                
                                            <?php if(count($forecastValueWeek, COUNT_RECURSIVE) > 10):?>
                                                <?php foreach($forecastValueWeek as $data):
                                                    if($data['crain'] > 0){
                                                        $tcc = $dev->getRainIcon($data['crain']);
                                                    }else{
                                                        $tcc = $dev->getCloudIcon($data['tcc']);
                                                        $temp = htmlspecialchars($data['2t']) . '°C';
                                                    }                                                      
                                                    ?>
                                                    <div class="daily-forecast-item">
                                                        <div class="forecast-date">
                                                            <?php getMonth($data['date']);?>
                                                        </div>
                                                        <div class="forecast-icon">
                                                            <?php echo $tcc;?>
                                                        </div>
                                                        <div class="forecast-temp">
                                                            <?php echo $temp;?>
                                                        </div>
                                                    </div>
                                                <?php endforeach;?>
                                                <?php else: ?>
                                                <div class="weather-no-data">
                                                    <p><?php echo checkForecast($device);?></p>
                                                </div>
                                                <?php endif; ?>  
                                        </div>
                                    </div>                                        
                                    <div class="meteo-right-panel">
                                        <?php if(!empty($forecastValuesDay)): ?>
                                            <?php 
                                            // Берем первые 4 элемента для отображения
                                            $items = array_slice($forecastValuesDay, 0, 4);
                                            foreach($items as $data):?>
                                                <div class="time-forecast-item">
                                                    <!-- Левая часть элемента -->
                                                    <div class="time-item-left">
                                                        <div class="forecast-time">
                                                            <?php 
                                                            if(isset($data['ref_time'])) {
                                                                $forecastDateTime = new DateTime($data['ref_time'], new DateTimeZone('UTC'));
                                                                echo $forecastDateTime->format('H:i');
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="forecast-icon-small">
                                                            <?php 
                                                            if($data['parameters']['crain'] > 0){
                                                                echo $dev->getRainIcon($data['parameters']['crain']);
                                                            }else{
                                                                $tcc = isset($data['parameters']['tcc']) ? $data['parameters']['tcc'] : null;
                                                                echo $dev->getCloudIcon($tcc);    
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Правая часть элемента -->
                                                    <div class="time-item-right">
                                                        <?php 
                                                        if(isset($data['parameters']['2t'])) {
                                                            echo round($data['parameters']['2t']) . '°C';
                                                        } else {
                                                            echo '-°C';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="weather-no-data">
                                                <p><?php echo checkForecast($device);?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>       
                        <?php endif;?>
                    <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
   
    <script src="/assets/js/main.js"></script>
    <script>
        function isYesterday(dateString) {           
            const targetDate = new Date(dateString);
            const currentDate = new Date();
            // Разница в миллисекундах
            const timeDiff = currentDate - targetDate;
            // 24 часа в миллисекундах
            const twentyFourHours = 24 * 60 * 60 * 1000;
            return timeDiff > twentyFourHours;
        }

        // Генерация рисок для всех гейджей
        document.querySelectorAll('.gauge-container').forEach(container => {
            const gaugeMarks = container.querySelector('.gauge-marks');
            for (let i = 0; i < 36; i++) {
                const mark = document.createElement('div');
                mark.className = 'gauge-mark';
                mark.style.transform = `rotate(${i * 10}deg)`;
                gaugeMarks.appendChild(mark);
            }
        });

        // Функция обновления одного виджета
        function updateWidget(widget) {
            if (widget.dataset.type == 'VP'){    
                let humidity = `${Math.ceil(parseFloat(widget.dataset.humidity))}%`;
                let humudityPercent = Math.ceil(parseFloat(widget.dataset.humidity));
                let temperature = `${parseFloat(widget.dataset.temperature)}°C`;
                let temperaturePercent = parseFloat(widget.dataset.temperature);
                let status = widget.dataset.status;
                let statusText = widget.dataset.statusText;
                let humidity_status;
                let status_text;
                let dataDate;
                if(widget.dataset.last != 'null'){
                    let widgetData = JSON.parse(widget.dataset.last)                    
                    dataDate = widgetData.Date + ' ' + widgetData.Time;                    
                }else{
                    status = 'inactive'; 
                }
                
                if (isYesterday(dataDate)) {
                   // status = 'inactive';           
                }
            
                if(status != 'inactive'){
                    if (widget.dataset.error == 'true'){
                        status = 'error';
                    }
                }

                switch (status) {
                    case 'normal':
                        humidity_status = 'в норме';
                        status_text = 'Полив не требуется';                
                        break;
                    case 'info':
                        humidity_status = 'высокая';
                        status_text = 'Почва переувлажнена';                
                        break;
                    case 'warning':
                        humidity_status = 'низкая';
                        status_text = 'Требуется скорый полив';                
                        break;
                    case 'danger':
                        humidity_status = 'критич';
                        status_text = 'Требуется срочный полив';                
                        break;
                    case 'inactive':
                        humidity_status = '-';
                        status_text = 'Устройство не активно';  
                        temperature = '-'; 
                        humidity = '-';
                        humudityPercent = 0;
                        temperaturePercent = 0;
                        break;
                    case 'error':
                        humidity_status = '✖';
                        status_text = 'Устройство не исправно';  
                        temperature = '✖'; 
                        humidity = '✖';
                        humudityPercent = 0;
                        temperaturePercent = 0;
                        break;    
                }
            
                // Обновляем текст
                widget.querySelector('.humidity-value').textContent = humidity_status;
                widget.querySelector('.temp-value').textContent = temperature;
                widget.querySelector('.gauge-value').textContent = humidity;
                widget.querySelector('.thermometer-value').textContent = temperature;
                widget.querySelector('.sensor-status').textContent = status_text;


                // Обновляем круговую диаграмму
                const circumference = 2 * Math.PI * 26;
                const offset = circumference * (1 - humudityPercent / 100);
                const gauge = widget.querySelector('.humidity-gauge');
                gauge.setAttribute('stroke-dashoffset', offset.toFixed(2));

                // Обновляем термометр
                const tempFill = widget.querySelector('.thermometer-fill');
                const fillHeight = (temperaturePercent / 50) * 50; // Макс. 50°C → 50px
                tempFill.style.height = `${fillHeight}px`;

                // Обновляем цвет статуса
                const statusClasses = ['status-normal', 'status-warning', 'status-danger', 'status-info', 'status-inactive', 'status-error'];
                
                widget.classList.remove(...statusClasses);
                widget.classList.add('status-' + (status || 'inactive'));
            }  else {

            }          
        }
        
        // Инициализация всех виджетов
        document.querySelectorAll('.sensor-widget').forEach(widget => {
            
            updateWidget(widget);
            widget.addEventListener('click', function(e) {
                // Получаем данные устройства
                let deviceData = JSON.parse(this.dataset.device);
                let lastData = JSON.parse(this.dataset.last);  
                window.open(`/pages/device_forecast.php?device_id=${deviceData.id}&year=<?=$current_year?>&date=<?=$current_date?>`, '_self');
                // Показываем модальное окно
               // showModal(deviceData, lastData);
            });
        });
        document.querySelectorAll('.meteo-sensor-widget').forEach(meteo => {
            meteo.addEventListener('click', function(e) {
                // Получаем данные устройства
                let deviceData = JSON.parse(this.dataset.device);
                let lastData = JSON.parse(this.dataset.last);  
                
                window.open(`/pages/device_meteo.php?device_id=${deviceData.id}&year=<?=$current_year?>&date=<?=$current_date?>`, '_self');
                // Показываем модальное окно
               // showModal(deviceData, lastData);
            });
            let ref_time = meteo.dataset.time;
           
            if (isYesterday(ref_time) || ref_time == '') {
                const statusClasses = ['status-normal', 'status-warning', 'status-danger', 'status-info', 'status-inactive', 'status-error'];
                meteo.style.backgroundColor = 'rgba(195, 199, 201, 1)';         
            }
           
        })
</script>

</body>
</html>
