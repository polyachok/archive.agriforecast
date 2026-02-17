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
$device = new Device();
$organization = new Organization();
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
            height: 170px;
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
                        if($device['device_type'] == 'VP'):  
                            $device_years = $dev->getArchiveYear($device['id']);
                           
                ?>
                            <div class="column col-4 col-xs-12">                      
                                <div class="sensor-widget status-inactive" 
                                data-type="VP">                                
                                    <div class="left-panel">
                                        <div>
                                            <p class="sensor-name"><?php echo htmlspecialchars($device['name']); ?></p>
                                        </div>
                                        <div>
                                        <select class="form-select year-selector" data-device-id="<?= $device['id'] ?>" name="year">                                       
                                            <option value="0" selected>Выберите год</option>
                                            <?php foreach ($device_years[0] as $item):?>                                            
                                                <option value="<?= safeHtml($item) ?>">
                                                    <?=safeHtml($item)?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select> 
                                        </div>
                                        <div>
                                            <p class="sensor-status"></p>
                                        </div>
                                    </div>
                                    <div class="right-panel">
                                    </div>
                                </div>
                            </div>
                        <?php elseif($device['device_type'] == 'M'):   
                           /* $soil_data = $dev->getLastMeteoData($device['id']);   
                            $forecastValuesDay = $dev->getForecastValuesDay($device['id']);
                            $forecastValueWeek = $dev->getForecastValuesWeek($device['id']);
                            $forecastValueNow = $dev->getForecastValuesNow($device['id']);
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
                            }      */             
                        ?>      
                               
                        <?php endif;?>
                    <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>    
    <script src="/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {            
            document.querySelectorAll('.year-selector').forEach(select => {
                select.addEventListener('change', function () {
                    const deviceId = this.dataset.deviceId;
                    const year = this.value;
                    if (year && year !== '0') {
                        window.location.href = `/pages/device_forecast.php?device_id=${deviceId}&year=${year}`;
                    }
                });
            });
        });
    </script>
    
</body>
</html>
