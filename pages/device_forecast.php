<?php

define('ROOT_PATH', dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', 1); 

require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/User.php';

define('MIN_PRECIPITATION_THRESHOLD', 0.16);

$current_year = $_SESSION['year'];
$user = new User();
$device = new Device();

$user_period = $device->getDevicePeriod($current_year,$_GET['device_id']);
print_r(getMonthsBetweenRu($user_period['start'], $user_period['finish']));

sscanf($user_period['start'], '%d-%d-%d', $yStart, $mStart, $dStart);
sscanf($user_period['finish'], '%d-%d-%d', $yFinish, $mFinish, $dFinish);
$current_date = $user_period['start'];
$dCurrent = $dStart;
$mCurrent = $mStart;
$yCurrent = $yStart;

if (!isset($_GET['device_id'])) {
   // header("Location: devices.php?error=no_device");
    //exit();
}

$device_id = (int)$_GET['device_id'];
$device_years = $device->getArchiveYear($device_id);
$device_info = $device->getDevice($device_id);
if($device_info){
    $latitude = '';
    $longitude = '';
    if($device_info['coordinates']){
        $coordinates = explode(',', $device_info['coordinates']);
        $latitude = trim($coordinates[0]);  
        $longitude = trim($coordinates[1]); 
    }

    $yellow_zone_start = isset($device_info['yellow_zone_start']) ? $device_info['yellow_zone_start'] : 25;
    $green_zone_start = isset($device_info['green_zone_start']) ? $device_info['green_zone_start'] : 50;
    $blue_zone_start = isset($device_info['blue_zone_start']) ? $device_info['blue_zone_start'] : 75;
    $humidity_count = isset($device_info['humidity_count']) ? (int)$device_info['humidity_count'] : 3; // Default to 3 if not set

    if ($_SESSION['role'] == ROLE_USER && $device_info['organization_id'] != $_SESSION['organization_id']) {
    // header("Location: devices.php?error=no_permission");
    // exit();
    } elseif ($_SESSION['role'] == ROLE_DEALER) {
        if ($device_info['organization_id'] != $_SESSION['organization_id']) {
        //    header("Location: devices.php?error=no_permission");
        //    exit();
        }
    }

    $show_forecast_data = true;
    $show_soil_tab = (bool)$device_info['is_realdata_enabled'];
    $show_forecast_tab = (bool)$device_info['is_forecast_enabled'];

    if (!$show_soil_tab && !$show_forecast_tab) {
    //  header("Location: devices.php?error=no_data_available");
    //  exit();
    }

    $soil_data = [];
    $forecast_values = [];
    
    if ($show_soil_tab) {
        $soil_data = $device->getSoilData($device_id, $current_date);  
        
        $unique_data = [];
        foreach ($soil_data as $record) {
            $key = $record['Date'] . ' ' . $record['Time'];
            if (!isset($unique_data[$key])) {
                $unique_data[$key] = $record;
            }
        }
        $soil_data = array_values($unique_data);

        $now = new DateTime();
        $overviewStartDate = DateTime::createFromFormat('Y-m-d', $current_date)->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');
        $detailedStartDate = ( DateTime::createFromFormat('Y-m-d', $current_date))->add(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        if (!empty($soil_data)) {            
            $soil_data = array_filter($soil_data, function($record) use ($overviewStartDate) {
                $recordDate = $record['Date'] . ' ' . $record['Time'];
                return strtotime($recordDate) <= strtotime($overviewStartDate);
            });
             
            usort($soil_data, function($a, $b) {
                $timeA = strtotime($a['Date'] . ' ' . $a['Time']);
                $timeB = strtotime($b['Date'] . ' ' . $b['Time']);
                return $timeA - $timeB;
            });
        }
    }

    if ($show_forecast_tab) {        
        $yesterday = DateTime::createFromFormat('Y-m-d', $current_date)
        ?->modify('-1 day')
        ?->setTime(0, 0, 0)
        ?->format('Y-m-d H:i:s');
        $current_date = DateTime::createFromFormat('Y-m-d', $current_date)->format('Y-m-d');
        $forecast_values = $device->getForecastValuesFromDate($device_id, $current_date, $yesterday);
        if(empty($forecast_values)){
            $device_forecast_period = $device->getForecastValuesPeriod($device_id);            
        }
    }

    $soil_param_names = [
        'humidity_5cm' => 'Влажность 5 см (%)',
        'humidity_15cm' => 'Влажность 15 см (%)',
        'humidity_25cm' => 'Влажность 25 см (%)',
        'humidity_35cm' => 'Влажность 35 см (%)',
        'humidity_45cm' => 'Влажность 45 см (%)',
        'humidity_55cm' => 'Влажность 55 см (%)',
        'humidity_accumulative' => 'Осадки (мм)',
        'temp_5cm' => 'Температура 5 см (°C)',
        'temp_15cm' => 'Температура 15 см (°C)',
        'temp_25cm' => 'Температура 25 см (°C)',
        'temp_35cm' => 'Температура 35 см (°C)',
        'temp_45cm' => 'Температура 45 см (°C)',
        'temp_55cm' => 'Температура 55 см (°C)'
    ];

    $soil_colors = [
        'humidity_5cm' => 'rgba(54, 162, 235, 0.7)',
        'humidity_15cm' => 'rgba(0, 123, 255, 0.7)',
        'humidity_25cm' => 'rgba(0, 86, 179, 0.7)',
        'humidity_35cm' => 'rgba(0, 56, 117, 0.7)',
        'humidity_45cm' => 'rgba(0, 36, 87, 0.7)',
        'humidity_55cm' => 'rgba(0, 20, 50, 0.7)',
        'humidity_accumulative' => 'rgba(135, 206, 235, 0.6)',
        'temp_5cm' => 'rgba(255, 99, 132, 0.7)',
        'temp_15cm' => 'rgba(220, 53, 69, 0.7)',
        'temp_25cm' => 'rgba(200, 35, 51, 0.7)',
        'temp_35cm' => 'rgba(178, 24, 43, 0.7)',
        'temp_45cm' => 'rgba(150, 10, 30, 0.7)',
        'temp_55cm' => 'rgba(120, 0, 20, 0.7)'
    ];

    $soil_chart_data = [];
   
    if (!empty($soil_data)) {

        function getClientTimezone() {
            if (isset($_SERVER['HTTP_CLIENT_TIMEZONE'])) {
                return (int)$_SERVER['HTTP_CLIENT_TIMEZONE'];
            }
            return 3;
        }

        function interpolateSoilData($soil_data) {
            if (empty($soil_data) || count($soil_data) < 2) {
                return $soil_data;
            }

            $timezoneOffset = getClientTimezone();
            
            foreach ($soil_data as &$record) {
                $timestamp = strtotime($record['Date'] . ' ' . $record['Time']);
                $timestamp += $timezoneOffset * 3600;
                $record['Date'] = date('Y-m-d', $timestamp);
                $record['Time'] = date('H:i:s', $timestamp);
            }
            unset($record);
            
            usort($soil_data, function($a, $b) {
                return strtotime($a['Date'] . ' ' . $a['Time']) - strtotime($b['Date'] . ' ' . $b['Time']);
            });
            
            $interpolated_data = [];
            $expected_interval = 3600; 
            
            for ($i = 0; $i < count($soil_data); $i++) {
                $current_record = $soil_data[$i];
                $interpolated_data[] = $current_record;
                
                if ($i < count($soil_data) - 1) {
                    $current_time = strtotime($current_record['Date'] . ' ' . $current_record['Time']);
                    $next_time = strtotime($soil_data[$i + 1]['Date'] . ' ' . $soil_data[$i + 1]['Time']);
                    $time_diff = $next_time - $current_time;
                    
                    if ($time_diff > $expected_interval * 1.5) {
                        $missing_hours = round($time_diff / $expected_interval) - 1;
                        
                        for ($h = 1; $h <= $missing_hours; $h++) {
                            $interpolated_time = $current_time + ($h * $expected_interval);
                            $interpolated_record = [];
                            
                            foreach ($current_record as $key => $value) {
                                if ($key === 'Date') {
                                    $interpolated_record[$key] = date('Y-m-d', $interpolated_time);
                                } elseif ($key === 'Time') {
                                    $interpolated_record[$key] = date('H:i:s', $interpolated_time);
                                } elseif (is_numeric($value) && isset($soil_data[$i + 1][$key])) {
                                    $ratio = $h / ($missing_hours + 1);
                                    $interpolated_value = $value + ($soil_data[$i + 1][$key] - $value) * $ratio;
                                    $interpolated_record[$key] = max(0, $interpolated_value);
                                } else {
                                    $interpolated_record[$key] = $value;
                                }
                            }
                            
                            $interpolated_record['_interpolated'] = true;
                            $interpolated_data[] = $interpolated_record;
                        }
                    }
                }
            }
            
            return $interpolated_data;
        }
        
        $soil_data = interpolateSoilData($soil_data);
        $depth_params = [
            '5cm' => ['offset' => 83.3, 'color_offset' => 0],
            '15cm' => ['offset' => 66.6, 'color_offset' => 2],
            '25cm' => ['offset' => 50, 'color_offset' => 4],
            '35cm' => ['offset' => 33.3, 'color_offset' => 6],
            '45cm' => ['offset' => 16.6, 'color_offset' => 8],
            '55cm' => ['offset' => 0, 'color_offset' => 10],
            'accumulativecm' => ['offset' => 0, 'color_offset' => 10]
        ];

        $param_extremes = [];
        foreach ($soil_data as $record) {
            foreach ($soil_param_names as $param => $name) {
                if (isset($record[$param]) && is_numeric($record[$param])) {
                    $value = (float)$record[$param];
                    $param_type = strpos($param, 'humidity') !== false ? 'humidity' : 'temp';
                    $depth = str_replace(['humidity_', 'temp_', 'cm'], '', $param) . 'cm';
                    
                    if (!isset($param_extremes[$depth][$param_type])) {
                        $param_extremes[$depth][$param_type] = ['min' => $value, 'max' => $value];
                    } else {
                        if ($value < $param_extremes[$depth][$param_type]['min']) {
                            $param_extremes[$depth][$param_type]['min'] = $value;
                        }
                        if ($value > $param_extremes[$depth][$param_type]['max']) {
                            $param_extremes[$depth][$param_type]['max'] = $value;
                        }
                    }
                }
            }
        }

        $precipitation_data_index = [];
        foreach ($soil_data as $record) {
            if (isset($record['humidity_accumulative']) && is_numeric($record['humidity_accumulative'])) {
                $datetime = $record['Date'] . ' ' . $record['Time'];
                $precipitation_data_index[$datetime] = (float)$record['humidity_accumulative'];
            }
        }

        foreach ($soil_param_names as $param => $name) {
            $is_humidity = strpos($param, 'humidity') !== false;
            $param_type = $is_humidity ? 'humidity' : 'temp';
            $depth = str_replace(['humidity_', 'temp_', 'cm'], '', $param) . 'cm';
            
            if (!isset($depth_params[$depth])) {
                continue;
            }
            
            $depth_setting = $depth_params[$depth];
            
            foreach ($soil_data as $record) {                
                
                $datetime = $record['Date'] . ' ' . $record['Time'];
                $is_interpolated = isset($record['_interpolated']) && $record['_interpolated'];
                
                if ($param === 'humidity_accumulative') {
                    $original_value = isset($record[$param]) && is_numeric($record[$param]) ? (float)$record[$param] : 0;
                    
                    $soil_chart_data[$param][] = [
                        'x' => $datetime,
                        'y' => $original_value,
                        'original' => $original_value,
                        'depth' => 'precipitation',
                        'type' => 'precipitation',
                        'color_index' => 0,
                        'has_data' => !$is_interpolated,
                        'interpolated' => $is_interpolated
                    ];
                } else {
                    if (isset($record[$param]) && is_numeric($record[$param])) {
                        $original_value = (float)$record[$param];
                        
                        if (isset($param_extremes[$depth][$param_type])) {
                            $extremes = $param_extremes[$depth][$param_type];
                            
                            $range = max(5, $extremes['max'] - $extremes['min']);
                            $normalized_value = (($original_value - $extremes['min']) / $range) * 15;
                            
                            $param_offset = $is_humidity ? 0 : 3;
                            $normalized_value += $depth_setting['offset'] + $param_offset;
                            
                            $soil_chart_data[$param][] = [
                                'x' => $datetime,
                                'y' => $normalized_value,
                                'original' => $original_value,
                                'depth' => str_replace('cm', '', $depth),
                                'type' => $param_type,
                                'color_index' => $depth_setting['color_offset'] + ($is_humidity ? 0 : 1),
                                'has_data' => !$is_interpolated,
                                'interpolated' => $is_interpolated
                            ];
                        }
                    }
                }
            }
        }
        
        foreach ($soil_chart_data as &$data) {
            if (!empty($data)) {
                usort($data, function($a, $b) {
                    return strtotime($a['x']) - strtotime($b['x']);
                });
            }
        }
        unset($data);
        
        echo "<script>window.precipitationDataIndex = " . json_encode($precipitation_data_index) . ";</script>";
        echo "<script>window.deviceHumidityCount = " . json_encode($humidity_count) . ";</script>";
    }

    $parameter_names = [
        '2t' => 'Температура воздуха (°C)',
        '2r' => 'Относительная влажность (%)',
        'tp' => 'Количество осадков (мм)',
        'crain' => 'Дождь',
        'dd_10m' => 'Направление ветра',
        'dswrf' => 'Солнечное излучение (Вт/м²)',
        'sp_10m' => 'Скорость ветра (м/с)',
        'tcc' => 'Облачность (%)',
        'vis' => 'Видимость (м)',
    ];

    function degreesToCompass($degrees) {
        $directions = ['С','ССВ','СВ','ВСВ','В','ВЮВ','ЮВ','ЮЮВ','Ю','ЮЮЗ','ЮЗ','ЗЮЗ','З','ЗСЗ','СЗ','ССЗ'];
        $index = round(($degrees % 360) / 22.5);
        return $directions[($index % 16)];
    }

    function generateColor($param) {
        $colors = [
            '2t' => 'rgba(255, 99, 132, 1)',
            '2r' => 'rgba(54, 162, 235, 1)',
            'tp' => 'rgb(51, 96, 173)',
            'crain' => 'rgba(173, 216, 230, 0.35)',
            'dd_10m' => 'rgba(255, 159, 64, 1)',
            'dswrf' => 'rgba(255, 206, 86, 1)',
            'sp_10m' => 'rgba(75, 192, 192, 1)',
            'tcc' => 'rgba(166, 166, 166, 0.15)',
            'vis' => 'rgba(153, 102, 255, 1)'
        ];
        return $colors[$param] ?? 'rgba('.rand(0,255).','.rand(0,255).','.rand(0,255).',1)';
    }

    $chart_data = [];
    $timestamps = [];
    $param_max_values = [];
    $param_coefficients = [];
    $min_date = '';
    $max_date = '';

    if (!empty($forecast_values)) {
        $param_values = [];
        foreach ($forecast_values as $fv) {
            $param = $fv['parameter'];
            $value = $fv['value'];
            
            if ($param === 'dswrf') {
                $value = $value / 3600;
            }
            
            if (!isset($param_values[$param])) {
                $param_values[$param] = [];
            }
            $param_values[$param][] = $value;
        }


        if (isset($param_values['tp'])) {
            $tp_values = $param_values['tp'];
            $max_tp_diff = 0;
            $prev_tp = null;
            
            foreach ($tp_values as $tp) {
                if ($prev_tp !== null) {
                    $diff = $tp - $prev_tp;
                    if ($diff > $max_tp_diff) {
                        $max_tp_diff = $diff;
                    }
                }
                $prev_tp = $tp;
            }
            
            $param_max_values['tp'] = $max_tp_diff > 0 ? $max_tp_diff : 1;
        }

        foreach ($param_values as $param => $values) {
            if ($param === 'tp') continue; 
            
            $max_value = max($values);
            $param_max_values[$param] = $max_value > 0 ? $max_value : 1;
        }
        foreach ($param_max_values as $param => $max_value) {
            $param_coefficients[$param] = 99.9 / $max_value;
        }

        foreach ($forecast_values as $fv) {
            $timestamp = strtotime($fv['ref_time']);
            $timestamps[] = $timestamp;
        }
        
        if (!empty($timestamps)) {
            $min_timestamp = min($timestamps);
            $max_timestamp = max($timestamps);
            $min_date = date('Y-m-d\TH:i:s', $min_timestamp);
            $max_date = date('Y-m-d\TH:i:s', $max_timestamp);
        }

        $main_parameters = ['2t', '2r', 'tp'];

        foreach ($forecast_values as $fv) {
            $timestamp = strtotime($fv['ref_time']);
            $time_label = date('Y-m-d H:i', $timestamp);
            if (!in_array($time_label, $timestamps)) {
                $timestamps[] = $time_label;
            }
        }

        $crain_fv = array_filter($forecast_values, fn($fv) => $fv['parameter'] === 'crain');
        usort($crain_fv, function($a, $b) {
            return strtotime($a['ref_time']) <=> strtotime($b['ref_time']);
        });
        $intervals = [];
        $interval_start = null;
        $interval = [];

        foreach ($crain_fv as $fv) {
            $val = $fv['value'];
            $label = date('Y-m-d H:i', strtotime($fv['ref_time']));
            if ($val == 1) {
                if ($interval_start === null) {
                    $interval_start = $label;
                    $interval = [$label];
                } else {
                    $interval[] = $label;
                }
            } else {
                if (!empty($interval)) {
                    $intervals[] = $interval;
                    $interval = [];
                    $interval_start = null;
                }
            }
        }
        if (!empty($interval)) {
            $intervals[] = $interval;
        }

        $crain_bar_data = [];
        foreach ($intervals as $interval_group) {
            foreach ($interval_group as $xl) {
                $crain_bar_data[] = [
                    'x' => $xl,
                    'y' => 99.9,
                    'original' => 1
                ];
            }
        }

        foreach ($parameter_names as $param => $name) {
            if ($param === 'tp') {
                $chart_type = 'bar';
                $data = [];
                $prev_value = null;
                
                $sorted_vals = array_filter($forecast_values, fn($fv) => $fv['parameter'] === 'tp');
                usort($sorted_vals, function($a, $b) {
                    return strtotime($a['ref_time']) <=> strtotime($b['ref_time']);
                });
                
                foreach ($sorted_vals as $fv) {
                    $value = $fv['value'];
                    $timestamp = strtotime($fv['ref_time']);
                    $time_label = date('Y-m-d H:i', $timestamp);
                    
                    $precip_value = 0;
                    if ($prev_value !== null) {
                        $diff = $value - $prev_value;
                        $precip_value = $diff > 0 ? $diff : 0;
                    }
                    
                    $normalized_value = $precip_value * ($param_coefficients[$param] ?? 1);

                    $data[] = [
                        'x' => $time_label,
                        'y' => $normalized_value,
                        'original' => $precip_value
                    ];
                    
                    $prev_value = $value;
                }
                
                $chart_data[$param] = [
                    'label' => $parameter_names[$param] ?? $param,
                    'data' => $data,
                    'borderColor' => generateColor($param),
                    'backgroundColor' => generateColor($param),
                    'hidden' => !in_array($param, $main_parameters),
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                    'coefficient' => 1,
                    'type' => $chart_type,
                    'borderWidth' => 0,
                    'barPercentage' => 1.0,
                    'categoryPercentage' => 1.0,
                    'order' => 2,
                    'skipNull' => true,
                    'precipitation' => true
                ];
                continue;
            }
            if ($param == 'crain') {
                $chart_type = 'bar';
                $data = $crain_bar_data;
                $chart_data[$param] = [
                    'label' => $parameter_names[$param] ?? $param,
                    'data' => $data,
                    'borderColor' => generateColor($param),
                    'backgroundColor' => generateColor($param),
                    'hidden' => !in_array($param, $main_parameters),
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                    'coefficient' => 1,
                    'max_value' => 1,
                    'type' => $chart_type,
                    'borderWidth' => 0,
                    'borderRadius' => 0,
                    'barPercentage' => 1.0,
                    'categoryPercentage' => 1.0,
                ];
            } else {
                $vals = array_filter($forecast_values, fn($fv) => $fv['parameter'] === $param);
                if (empty($vals)) continue;
                
                $chart_type = 'line';
                $data = [];
                foreach ($vals as $fv) {
                    $value = $fv['value'];
                    if ($param === 'dswrf') {
                        $value = $value / 3600;
                    }
                    
                    $timestamp = strtotime($fv['ref_time']);
                    $time_label = date('Y-m-d H:i', $timestamp);
                    $normalized_value = $value * $param_coefficients[$param];
                    $data[] = [
                        'x' => $time_label,
                        'y' => $normalized_value,
                        'original' => $value
                    ];
                }
                
                $fill = false;
                $backgroundColor = generateColor($param);
                if ($param === 'tcc') {
                    $fill = true;
                    $bgColor = generateColor($param);
                    $backgroundColor = $bgColor;
                }
                
                $chart_data[$param] = [
                    'label' => $parameter_names[$param] ?? $param,
                    'data' => $data,
                    'borderColor' => generateColor($param),
                    'backgroundColor' => $backgroundColor,
                    'hidden' => !in_array($param, $main_parameters),
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                    'coefficient' => $param_coefficients[$param],
                    'max_value' => $param_max_values[$param] ?? 1,
                    'type' => $chart_type,
                    'fill' => $fill
                ];
            }
        }

        sort($timestamps);
    }

    function getWind($degrees) {
        if ($degrees === null || !is_numeric($degrees)) {
            return '-'; 
        }

        $degrees = $degrees % 360;
        if ($degrees < 0) {
            $degrees += 360;
        }

        $directions = [
            'С ' => [337.5, 22.5],
            'СВ ' => [22.5, 67.5],
            'В ' => [67.5, 112.5],
            'ЮВ ' => [112.5, 157.5],
            'Ю ' => [157.5, 202.5],
            'ЮЗ ' => [202.5, 247.5],
            'З ' => [247.5, 292.5],
            'СЗ ' => [292.5, 337.5]
        ];

        foreach ($directions as $abbr => $range) {
            if ($degrees >= $range[0] && $degrees < $range[1]) {
                return $abbr;
            }
        }

        return 'С'; // На случай, если градусы = 360 (но %360 обработает это)
    }

    function formatDateFromRusDate($day, $short = false){
        $date = new DateTime($day);
        $today = new DateTime();
        $tomorrow = new DateTime('+1 day');

        $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        
        if($short){
            $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        }else{
            $days = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
        }

        // Проверяем, является ли дата сегодняшним днем
        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $dayName = 'Сегодня';
        } 
        // Проверяем, является ли дата завтрашним днем
        elseif ($date->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
            $dayName = 'Завтра';
        } 
        // Для остальных дней используем обычное название дня недели
        else {
            $dayOfWeekIndex = (int)$date->format('N') - 1;
            $dayName = $days[$dayOfWeekIndex];
        }

        $dayNumber = $date->format('d');
        $monthName = $months[(int)$date->format('m') - 1];

        $formattedDate = $dayName . ', ' . $dayNumber . ' ' . $monthName;
        return $formattedDate;
    }

    function formatDateFromShortRusDate($day){
        $date = new DateTime($day);
        $today = new DateTime();
        $tomorrow = new DateTime('+1 day');
        
        $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        // Проверяем, является ли дата сегодняшним днем
        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $dayName = 'Сегодня';
        } 
        // Проверяем, является ли дата завтрашним днем
        elseif ($date->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
            $dayName = 'Завтра';
        } 
        // Для остальных дней используем обычное название дня недели
        else {
            $dayOfWeekIndex = (int)$date->format('N') - 1;
            $dayName = $days[$dayOfWeekIndex];
        }

        $dayNumber = $date->format('d');

        $formattedDate = [ 'name' => $dayName, 'number' => $dayNumber];
        return $formattedDate;
    }
}

function getMonthsBetweenRu($startDate, $endDate) {
    $monthsRu = [
        '01' => 'Январь',
        '02' => 'Февраль',
        '03' => 'Март',
        '04' => 'Апрель',
        '05' => 'Май',
        '06' => 'Июнь',
        '07' => 'Июль',
        '08' => 'Август',
        '09' => 'Сентябрь',
        '10' => 'Октябрь',
        '11' => 'Ноябрь',
        '12' => 'Декабрь',
    ];

    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);

    $start->modify('first day of this month');
    $end->modify('first day of this month');

    $result = [];
    $current = clone $start;

    while ($current <= $end) {
        $ym = $current->format('Y-m');          // ключ: '2025-04'
        $m  = $current->format('m');            // '04'
        $y  = $current->format('Y');            // '2025'

        $label = $monthsRu[$m] . ' ' . $y;      // 'Апрель 2025'
        $result[$ym] = $label;

        $current->modify('+1 month');
    }

    return $result;
}


include ROOT_PATH . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.0.1"></script>
<script src="https://api-maps.yandex.ru/v3/?apikey=de391e7f-f29b-4166-abd5-63717adafe6e&lang=ru_RU" type="text/javascript"></script>
<script>
    const startDate = new Date(<?=$yStart?>, <?=$mStart - 1?>, <?=$dStart?>);   //месяцы с 0 начинаются
    const endDate = new Date(<?=$yFinish?>, <?=$mFinish - 1?>, <?=$dFinish?>);  
    const defaultDate = new Date(<?=$yCurrent?>, <?=$mCurrent - 1?>, <?=$dCurrent?>);  
        new Pikaday({
            field: document.getElementById('datepicker'),
            minDate: startDate,
            maxDate: endDate,
            format: 'DD.MM.YYYY', 
            defaultDate: defaultDate,
            setDefaultDate: true,
            firstDay: 1,
            yearRange: [2020, 2030],
            onSelect: function(selectedDate) {
                if (selectedDate) {
                    const formatted = moment(selectedDate).format('YYYY-MM-DD');
                    const url = new URL(window.location);
                    url.searchParams.set('date', formatted);
                    window.location.href = url.href;
                } 
            },
            i18n: {
            months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            weekdays: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
            weekdaysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']
            }
        });
</script>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    <div class="column col-9 col-xs-12">
        <h3 style="margin-bottom: 0;">Данные устройства <?= $device_info ? htmlspecialchars($device_info['name']) : '' ?></h3>
        <button class="btn btn-link" onclick="window.location.href = '/pages/dashboard.php'">
            <i class="fas fa-arrow-left"></i> Назад к приборам
        </button>
        <?php if($device_info):?>
            <div class="tab-container">
                <div class="tab-controls">
                    <?php if ($show_soil_tab): ?>
                    <button
                        class="tab-btn <?= $show_soil_tab && !$show_forecast_tab ? 'active' : ($show_soil_tab ? 'active' : '') ?>"
                        data-tab="soil">Данные почвы</button>
                    <?php endif; ?>
                    <?php if ($show_soil_tab): ?>
                    <button class="tab-btn" data-tab="moisture">Влагозапас</button>
                    <?php endif; ?>
                    <?php if ($show_forecast_tab): ?>
                        <button class="tab-btn " data-tab="forecast-data">Прогноз</button>
                        <button class="tab-btn <?= (!$show_forecast_data && $show_forecast_tab) ? 'active' : '' ?>" data-tab="forecast">Графики прогноза</button>                   
                    <?php endif; ?>
                </div>

                <?php if ($show_soil_tab): ?>
                <div class="tab-content <?= $show_soil_tab && !$show_forecast_tab ? 'active' : ($show_soil_tab ? 'active' : '') ?>"
                    id="soil-tab">
                    <?php if (empty($soil_data)): ?>
                    <div class="alert alert-info">
                        Данные о температуре и влажности почвы отсутствуют для этого устройства.
                    </div>
                    <?php else: ?>

                    <div class="depth-controls mb-4">
                        <div class="depth-buttons">
                            <button class="depth-btn active" data-depth="5">5 см</button>
                            <button class="depth-btn active" data-depth="15">15 см</button>
                            <button class="depth-btn active" data-depth="25">25 см</button>
                            <button class="depth-btn active" data-depth="35">35 см</button>
                            <button class="depth-btn active" data-depth="45">45 см</button>
                            <button class="depth-btn active" data-depth="55">55 см</button>
                            <label class="form-switch">
                                <input type="checkbox" id="soilParamTypeToggle">
                                <i class="form-icon"></i> Показать температуру
                            </label>
                            <label class="form-switch">
                                <input type="checkbox" id="soilChartToggle"
                                    <?= $show_soil_tab && !empty($soil_data) ? '' : 'checked' ?>>
                                <i class="form-icon"></i> Показать общий просмотр
                            </label>
                        </div>
                    </div>

                    <div class="card mb-4 overview-card" style="display: none;">
                        <div class="card-header">
                            <h4>Общий просмотр (30 дней)</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="soilOverviewChart" height="496"></canvas>
                        </div>
                    </div>

                    <div class="card detailed-card">
                        <div class="card-header">
                            <h4>Детальный просмотр (7 дней)</h4>
                        </div>
                        <div class="card-body" style="overflow-x: auto;">
                            <div style="min-width: 4000px;">
                                <canvas id="soilDetailedChart" height="496"
                                    style="width: 100%; max-height: 496px; min-width: 4000px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_soil_tab): ?>
                <div class="tab-content" id="moisture-tab">
                    <?php if (empty($soil_data)): ?>
                    <div class="alert alert-info">
                        Данные о влагозапасе отсутствуют для этого устройства.
                    </div>
                    <?php else: ?>
                    <div class="depth-controls mb-4">
                        <label class="form-switch">
                            <input type="checkbox" id="moistureChartToggle">
                            <i class="form-icon"></i> Показать общий просмотр
                        </label>

                        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                        <div class="zone-controls"
                            style="display: flex; gap: 10px; margin-top: 0; align-items: center; flex-wrap: wrap;">
                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider blue-slider"
                                    id="blueZoneSlider" value="<?= $blue_zone_start ?>" style="width: 100%;">
                                <input type="number" id="blueZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(54, 162, 235, 0.2); margin-top: 5px;"
                                    title="Нижняя граница синей зоны" value="<?= htmlspecialchars($blue_zone_start) ?>">
                            </div>
                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider green-slider"
                                    id="greenZoneSlider" value="<?= $green_zone_start ?>" style="width: 100%;">
                                <input type="number" id="greenZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(75, 192, 192, 0.2); margin-top: 5px;"
                                    title="Нижняя граница зеленой зоны" value="<?= htmlspecialchars($green_zone_start) ?>">
                            </div>


                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider yellow-slider"
                                    id="yellowZoneSlider" value="<?= $yellow_zone_start ?>" style="width: 100%;">
                                <input type="number" id="yellowZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(255, 206, 86, 0.2); margin-top: 5px;"
                                    title="Нижняя граница желтой зоны" value="<?= htmlspecialchars($yellow_zone_start) ?>">
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="zone-controls"
                            style="display: none; gap: 10px; margin-top: 0; align-items: center; flex-wrap: wrap;">
                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider blue-slider"
                                    id="blueZoneSlider" value="<?= $blue_zone_start ?>" style="width: 100%;">
                                <input type="number" id="blueZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(54, 162, 235, 0.2); margin-top: 5px;"
                                    title="Нижняя граница синей зоны" value="<?= htmlspecialchars($blue_zone_start) ?>">
                            </div>
                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider green-slider"
                                    id="greenZoneSlider" value="<?= $green_zone_start ?>" style="width: 100%;">
                                <input type="number" id="greenZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(75, 192, 192, 0.2); margin-top: 5px;"
                                    title="Нижняя граница зеленой зоны" value="<?= htmlspecialchars($green_zone_start) ?>">
                            </div>


                            <div class="zone-slider-group" style="flex: 1; min-width: 150px;">
                                <input type="range" min="0" max="300" step="1" class="slider yellow-slider"
                                    id="yellowZoneSlider" value="<?= $yellow_zone_start ?>" style="width: 100%;">
                                <input type="number" id="yellowZoneStart" class="form-input"
                                    style="width: 100%; background-color: rgba(255, 206, 86, 0.2); margin-top: 5px;"
                                    title="Нижняя граница желтой зоны" value="<?= htmlspecialchars($yellow_zone_start) ?>">
                            </div>
                        </div>   
                        <?php endif; ?>
                    </div>

                    <div class="card mb-4 overview-card" style="display: none;">
                        <div class="card-header">
                            <h4>Общий просмотр (30 дней)</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="moistureOverviewChart" height="496"></canvas>
                        </div>
                    </div>

                    <div class="card detailed-card">
                        <div class="card-header">
                            <h4>Детальный просмотр (7 дней)</h4>
                        </div>
                        <div class="card-body" style="overflow-x: auto;">
                            <div style="min-width: 4000px;">
                                <canvas id="moistureDetailedChart" height="496"
                                    style="width: 100%; max-height: 496px; min-width: 4000px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($show_forecast_data): ?>
                    <div class="tab-content" id="forecast-data-tab">
                    <?php if (empty($forecast_values)): ?>
                    <div class="alert alert-info">
                        Данные прогноза на выбранную дату отсутствуют.</br>
                        Для этого устройства доступны данные прогноза с <strong><?=$device_forecast_period['device_period'];?></strong>! 
                    </div>
                    <?php else: ?>               
                    <!-- Виджеты прогноза-->
                    <div class="card mb-4 overview-card">
                        <div class="card-header">
                            <h4></h4>
                        </div>
                        <div class="card-body">
                            <div id="map" style="height: 200px"></div>
                            <?php         
                                          
                                $last_meteo_data = $device->getLastMeteoData($device_info['id'], $current_date);
                                $forecastValueNow = $device->getForecastValuesNow($device_info['id'], $current_date);
                                $forecastValueAllDay = $device->getForecastValuesAllDay($device_info['id'], $current_date);
                                $forecastValueWeekFromTime = $device->getForecastValuesWeekFromTime($device_info['id'], $current_date);
                                //echo'<pre>';
                                print_r($forecastValueWeekFromTime);
                               // echo '</pre>';
                                if(!empty($forecastValueNow['parameters'])){
                                    if(!empty($forecastValueNow['parameters']['2t'])){
                                        $current_temp = round($forecastValueNow['parameters']['2t']);
                                    }else{
                                        $current_temp = '-';
                                    }
                                    if($forecastValueNow['parameters']['crain'] > 0){
                                        $humidity = $device->getRainIcon($forecastValueNow['parameters']['crain']);
                                    }else{
                                        $humidity = $device->getCloudIcon($forecastValueNow['parameters']['tcc']);
                                    } 
                                }
                                
                            ?>
                            <!-- Прогноз сутки -->
                            <div class="weather-widget">
                                <!-- Левый блок - текущая температура -->
                                <div class="temperature-block">
                                    <span class="current-temp"><?php echo htmlspecialchars($current_temp);?></span>
                                    <?php echo $current_temp != '' ? '<span class="current-temp-unit">°C</span>' : '-'; ?> 
                                </div>
                                <!-- Средний блок - иконка погоды -->
                                <div class="weather-icon-block">
                                    <div class="weather-icon"><?php echo $humidity;?></div>
                                </div>
                                <!-- Правый блок - прогноз по часам на сутки -->
                                <div class="forecast-block">
                                    <div class="forecast-container">
                                        <button class="scroll-arrow scroll-arrow-left" id="scrollLeft">◀</button>
                                        <div class="hourly-forecast-wrapper">
                                            <div class="hourly-forecast" id="hourlyForecast">
                                                <?php foreach($forecastValueAllDay as $hour): ?>
                                                <div class="hour-forecast">
                                                    <div class="hour-time"><?php echo $hour['ref_time']?></div>
                                                    <div class="hour-temp-block">
                                                        <span class="hour-temp"><?php echo round($hour['parameters']['2t'])?></span><span class="hour-temp-unit">°C</span>
                                                    </div>
                                                    <div class="hour-icon">
                                                        <?php
                                                            $params = $hour['parameters'] ?? [];
                                                            echo ($params['crain'] ?? 0) > 0 
                                                                ? $device->getRainIcon($params['crain']) 
                                                                : $device->getCloudIcon($params['tcc'] ?? null);
                                                        ?>
                                                    </div>
                                                </div>  
                                                <?php endforeach; ?>                                      
                                            </div>
                                        </div>
                                        <button class="scroll-arrow scroll-arrow-right" id="scrollRight">▶</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Прогноз 10 дней общий-->
                            <div class="weather-container">
                                <div class="forecast-header">
                                    Прогноз на 10 дней
                                </div>
                                
                                <div class="forecast-content">
                                    <div class="days-container">
                                        <?php 
                                            $forecastTenDayNight = $device->getForecastValuesWeekFromDayNight($device_info['id'], $current_date);
                                            //print_r($forecastTenDayNight);
                                            foreach($forecastTenDayNight as $day):
                                                $paramDay = $day['day']['parameters'];
                                                $paramNight = $day['night']['parameters'];
                                                $date = formatDateFromShortRusDate($day['date']);
                                                $dateId = new DateTime($day['date']);
                                                $dateId = $dateId->format('m.d');
                                        ?>                                    
                                            <div class="day-card" data-target="<?php echo $dateId;?>">
                                                <div class="day-name"><?php echo $date['name'];?></div>
                                                <div class="day-date"><?php echo $date['number'];?></div>
                                                <div class="weather-icon-day">
                                            <?php
                                                if($paramDay['crain'] > 0){
                                                    echo $device->getRainIcon($paramDay['crain']);
                                                }else{
                                                    echo $device->getCloudIcon($paramDay['tcc']);                                               
                                                }   
                                            ?>                           
                                                </div>
                                                <!-- <div class="temperature">22°</div>-->
                                            </div>
                                            
                                        <?php endforeach;?>
                                    </div>
                                    <div style="height: 150px;">
                                    <canvas id="chart-container"></canvas>
                                    </div>
                                
                                </div>
                            </div>
                            <!-- Прогноз 10 дней по дню-->
                            <?php forEach($forecastValueWeekFromTime as $day):
                            echo '<pre>'; 
                            print_r($day);
                            echo '</pre>';
                            
                            $date = formatDateFromRusDate($day['date']);
                            $dateId = new DateTime($day['date']);
                            $dateId = $dateId->format('m.d');
                            ?>   
                                <div class="weather-widget-day" id='<?php echo $dateId; ?>'>
                                    <!-- Левый блок — СТАТИЧНЫЙ -->
                                    <div class="weather-block left-block">
                                        <div class="block-title-left"><?php echo $date; ?></div>
                                        <div class="weather-rows">
                                            <?php foreach ($day['items'] as $paramKey => $paramData):
                                                if ($paramKey !== '2t') continue; // Работаем только с температурой для левого блока
                                                foreach ($paramData['times'] as $targetTime => $timeData):
                                                    $crainValue = $day['items']['crain']['times'][$targetTime]['value'] ?? 0;
                                                    $tccValue = $day['items']['tcc']['times'][$targetTime]['value'] ?? null;
                                            ?>
                                                <div class="weather-row">
                                                    <div class="time-period"><?php echo $targetTime; ?></div>
                                                    <div class="day-temp-block">
                                                        <span class="temperature">
                                                            <?php echo $timeData['value'] !== null ? round($timeData['value']) : '—'; ?>
                                                        </span>
                                                        <span class="day-temp-unit">°C</span>
                                                    </div>
                                                    <div class="weather-icon-left">
                                                        <?php
                                                            echo $crainValue > 0
                                                                ? $device->getRainIcon($crainValue)
                                                                : $device->getCloudIcon($tccValue);
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php break; // достаточно одного прохода, т.к. только '2t' нужен ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                <!-- Правые блоки — УНИВЕРСАЛЬНЫЕ -->
                                    <?php foreach ($day['items'] as $paramKey => $paramData):
                                        // Пропускаем параметры, которые уже отображены в левом блоке
                                        if (in_array($paramKey, ['2t', 'crain', 'tcc'])) continue;
                                    ?>
                                    <div class="weather-block">
                                        <div class="block-title"><?= htmlspecialchars($paramData['name']) ?></div>
                                        <div class="right-block-content">
                                            <?php foreach ($paramData['times'] as $timeKey => $timeData): 
                                               
                                                ?>
                                                <div class="data-item">
                                                    <?php if ($paramData['type'] === 'wind'): ?>
                                                        <?= htmlspecialchars($timeData['speed']) ?> <?= getWind($timeData['direction']) ?> 
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($timeData['display']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                        </div>         
                <?php endforeach;?>
                            <!----->
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_forecast_tab): ?>
                <div class="tab-content <?= $show_forecast_tab && !$show_soil_tab ? 'active' : '' ?>" id="forecast-tab">
                    <?php if (empty($forecast_values)): ?>
                    <div class="alert alert-info">
                    Данные прогноза на выбранную дату отсутствуют.</br>
                    Для этого устройства доступны данные прогноза с <strong><?=$device_forecast_period['device_period'];?></strong>! 
                    </div>
                    <?php else: ?>
                    <div class="chart-controls mb-4">
                        <div class="parameter-toggles">
                            <?php foreach ($parameter_names as $param => $name): ?>
                            <?php if (isset($chart_data[$param])): ?>
                            <label class="parameter-toggle <?= !$chart_data[$param]['hidden'] ? 'active' : '' ?>"
                                data-param="<?= $param ?>">
                                <input type="checkbox" <?= !$chart_data[$param]['hidden'] ? 'checked' : '' ?>>
                                <span class="toggle-indicator"
                                    style="background: <?= $chart_data[$param]['borderColor'] ?>"></span>
                                <span class="toggle-label"><?= htmlspecialchars($name) ?></span>
                            </label>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-switch">
                            <input type="checkbox" id="forecastChartToggle">
                            <i class="form-icon"></i> Показать детальный прогноз
                        </label>
                    </div>
                    <div class="card mb-4 overview-card">
                        <div class="card-header">
                            <h4>Общий просмотр</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="forecastOverviewChart" height="496"></canvas>
                        </div>
                    </div>

                    <div class="card detailed-card" style="display: none;">
                        <div class="card-header">
                            <h4>Детальный просмотр</h4>
                        </div>
                        <div class="card-body" style="overflow-x: auto;">
                            <div style="min-width: 4000px;">
                                <canvas id="forecastDetailedChart" height="496"
                                    style="width: 100%; max-height: 496px; min-width: 4000px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <button id="backToTop" title="Наверх"><i class="fas fa-arrow-up"></i></button>

            </div>
        <?php else: ?>
            <div class="alert alert-info">
                        Данное устройство не доступно для данного периода.
                    </div>
        <?php endif; ?>        
    <script>        
        const precipitationShiftPlugin = {
            id: 'precipitationShiftPlugin',
            beforeDatasetDraw(chart, args) {
                const { meta } = args;
                if (meta.type !== 'bar') return;
                
                const dataset = chart.data.datasets[meta.index];
                if (!dataset.precipitation) return;

                meta.data.forEach((bar) => {
                    if (!bar._precipShiftApplied) {
                        bar.x += bar.width / 2;
                        bar._precipShiftApplied = true;
                        bar._precipOriginalX = bar.x;
                    } else {
                        bar.x = bar._precipOriginalX;
                    }
                });
            },
            beforeUpdate(chart) {
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    if (dataset.precipitation) {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach(bar => {
                            if (bar._precipShiftApplied) {
                                bar._precipOriginalX = bar.x;
                            }
                        });
                    }
                });
            },
            afterUpdate(chart) {
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    if (dataset.precipitation) {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach(bar => {
                            if (bar._precipShiftApplied && bar._precipOriginalX) {
                                bar.x = bar._precipOriginalX;
                            }
                        });
                    }
                });
            }
        };

        Chart.register(precipitationShiftPlugin);

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                if (tabId === 'moisture') {
                    setTimeout(() => {
                        const moistureContainerForTab = document.querySelector(
                            '#moisture-tab .detailed-card .card-body');
                        if (moistureContainerForTab) {
                            moistureContainerForTab.scrollLeft = moistureContainerForTab
                                .scrollWidth;
                        }
                    }, 300);
                }
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });

                document.getElementById(tabId + '-tab').classList.add('active');
                this.classList.add('active');
                // Dispatch a resize event to trigger layout recalculation for components in the new tab
                window.dispatchEvent(new Event('resize'));
            });
        });

        <?php if ($show_soil_tab && !empty($soil_data)): ?>
        const depthColors = [
            ['rgba(0, 20, 50, 0.7)', 'rgba(255, 99, 132, 0.7)'],
            ['rgba(0, 20, 50, 0.7)', 'rgba(220, 53, 69, 0.7)'],
            ['rgba(0, 20, 50, 0.7)', 'rgba(200, 35, 51, 0.7)'],
            ['rgba(0, 20, 50, 0.7)', 'rgba(178, 24, 43, 0.7)'],
            ['rgba(0, 20, 50, 0.7)', 'rgba(150, 10, 30, 0.7)'],
            ['rgba(0, 20, 50, 0.7)', 'rgba(120, 0, 20, 0.7)']
        ];

        const normalizedSoilData = {};
        <?php foreach ($soil_param_names as $param => $name): ?>            
        <?php if (isset($soil_chart_data[$param]) && !empty($soil_chart_data[$param])): ?>
        <?php
                if ($param === 'humidity_accumulative') {
                    $depth = 'precipitation';
                    $param_type = 'precipitation';
                    $is_accumulative = true;
                } else {
                    $depth = str_replace(['humidity_', 'temp_', 'cm'], '', $param) . 'cm';
                    $param_type = strpos($param, 'temp_') !== false ? 'temp' : 'humidity';
                    $is_accumulative = false;
                }
                ?>
        normalizedSoilData['<?= $param ?>'] = {
            depth: '<?= $depth ?>',
            type: '<?= $param_type ?>',
            name: '<?= $name ?>',
            data: <?= json_encode($soil_chart_data[$param]) ?>,
            minValue: <?= !empty($soil_chart_data[$param]) ? min(array_column($soil_chart_data[$param], 'original')) : 0 ?>,
            maxValue: <?= !empty($soil_chart_data[$param]) ? max(array_column($soil_chart_data[$param], 'original')) : 0 ?>,
            isAccumulative: <?= $is_accumulative ? 'true' : 'false' ?>
        };
        <?php endif; ?>
        <?php endforeach; ?>

        function createPrecipitationDataset() {
            if (!normalizedSoilData['humidity_accumulative']) return null;

            const precipData = normalizedSoilData['humidity_accumulative'];
            if (!precipData.data || precipData.data.length === 0) return null;

            const allTimePoints = new Set();
            Object.values(normalizedSoilData).forEach(paramData => {
                if (paramData.data && Array.isArray(paramData.data)) {
                    paramData.data.forEach(point => {
                        allTimePoints.add(point.x);
                    });
                }
            });

            const precipMap = new Map();
            let maxPrecipValue = 0;

            for (let i = 1; i < precipData.data.length; i++) {
                const current = precipData.data[i];
                const prev = precipData.data[i - 1];

                if (current.original > prev.original) {
                    let precipAmount = current.original - prev.original;
                    precipAmount = precipAmount < <?= MIN_PRECIPITATION_THRESHOLD ?> ? 0 : precipAmount;
                    precipMap.set(current.x, precipAmount);
                    maxPrecipValue = Math.max(maxPrecipValue, precipAmount);
                }
            }

            const precipNormalizationCoeff = maxPrecipValue > 0 ? 99.9 / maxPrecipValue : 1;

            const fullPrecipData = Array.from(allTimePoints).sort((a, b) => new Date(a) - new Date(b)).map(
                timePoint => {
                    const precipValue = precipMap.get(timePoint) || 0;
                    return {
                        x: timePoint,
                        y: precipValue * precipNormalizationCoeff,
                        original: precipValue
                    };
                });

            return {
                label: precipData.name,
                data: fullPrecipData,
                type: 'bar',
                backgroundColor: 'rgba(135, 206, 235, 0.7)',
                borderColor: 'rgba(135, 206, 235, 0.7)',
                borderWidth: 0,
                yAxisID: 'y',
                hidden: false,
                borderWidth: 0,
                barPercentage: 1.0,
                categoryPercentage: 1.0,
                order: 2,
                barThickness: 'flex',
                precipitation: true
            };
        }

        function calculateTotalMoisture() {
            const moistureDepths = ['5cm', '15cm', '25cm', '35cm', '45cm', '55cm'];
            const result = [];
            const timeMap = new Map();

            moistureDepths.forEach(depth => {
                const param = 'humidity_' + depth;
                if (normalizedSoilData[param] && normalizedSoilData[param].data) {
                    normalizedSoilData[param].data.forEach(point => {
                        if (!timeMap.has(point.x)) {
                            timeMap.set(point.x, {
                                x: point.x,
                                sum: 0,
                                count: 0,
                                has_data: point.has_data,
                                interpolated: point.interpolated
                            });
                        }
                        timeMap.get(point.x).sum += point.original;
                        timeMap.get(point.x).count++;
                    });
                }
            });

            timeMap.forEach((value, key) => {
                if (value.count > 0) {
                    result.push({
                        x: key,
                        y: value.sum,
                        original: value.sum,
                        has_data: value.has_data,
                        interpolated: value.interpolated
                    });
                }
            });

            result.sort((a, b) => new Date(a.x) - new Date(b.x));

            return result;
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('ru', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            let activeDepths = ['5', '15', '25', '35', '45', '55'];
            let currentShowType = 'humidity';

            document.querySelectorAll('.depth-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const depth = this.getAttribute('data-depth');
                    const isActive = this.classList.contains('active');

                    if (isActive) {
                        this.classList.remove('active');
                        activeDepths = activeDepths.filter(d => d !== depth);
                    } else {
                        this.classList.add('active');
                        activeDepths.push(depth);
                        activeDepths.sort((a, b) => parseInt(a) - parseInt(b));
                    }

                    updateCharts();
                });
            });

            function recalculateNormalization(activeParams) {
                const allDepths = new Set();

                activeParams.forEach(p => {
                    if (normalizedSoilData[p] && !normalizedSoilData[p].isAccumulative) {
                        const depth = normalizedSoilData[p].depth.replace('cm', '');
                        if (activeDepths.includes(depth)) {
                            allDepths.add(depth);
                        }
                    }
                });

                const sortedDepths = Array.from(allDepths).sort((a, b) => parseInt(a) - parseInt(b));
                const zoneHeight = sortedDepths.length > 0 ? 100 / sortedDepths.length : 100;

                activeParams.forEach(param => {
                    const paramData = normalizedSoilData[param];                    
                    if (!paramData || paramData.isAccumulative) return;

                    const depth = paramData.depth.replace('cm', '');
                    if (!activeDepths.includes(depth)) return;

                    const depthIndex = sortedDepths.indexOf(depth);
                    if (depthIndex === -1) return;

                    const zoneMin = 100 - (depthIndex + 1) * zoneHeight;
                    const zoneMax = 100 - depthIndex * zoneHeight;

                    if (paramData.data && Array.isArray(paramData.data)) {
                        paramData.data.forEach(item => {
                            const range = Math.max(paramData.maxValue - paramData.minValue, 1);
                            const normalizedValue = ((item.original - paramData.minValue) /
                                range) * (zoneMax - zoneMin - 10) + zoneMin + 5;
                            item.y = normalizedValue;
                        });
                    }
                });
            }

            function createDatasets(showType, activeParams) {
                recalculateNormalization(activeParams);

                const datasets = [];

                const precipDataset = createPrecipitationDataset();
                if (precipDataset) {
                    datasets.push(precipDataset);
                }

                const visibleDepths = new Set();

                Object.entries(normalizedSoilData)
                    .filter(([param, paramData]) =>
                        paramData &&
                        paramData.type === showType &&
                        activeParams.includes(param) &&
                        !paramData.isAccumulative &&
                        activeDepths.includes(paramData.depth.replace('cm', ''))
                    )
                    .forEach(([param, paramData]) => {
                        const depthIndex = ['5cm', '15cm', '25cm', '35cm', '45cm', '55cm'].indexOf(paramData
                            .depth);
                        if (depthIndex === -1) return;

                        const isHumidity = paramData.type === 'humidity';
                        const colorIndex = isHumidity ? 0 : 1;

                        visibleDepths.add(paramData.depth);

                        datasets.push({
                            label: paramData.name,
                            data: paramData.data || [],
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            tension: 0.4,
                            yAxisID: 'y',
                            hidden: false,
                            borderColor: depthColors[depthIndex] ? depthColors[depthIndex][
                                colorIndex
                            ] : 'rgba(0,0,0,0.7)',
                            backgroundColor: depthColors[depthIndex] ? depthColors[depthIndex][
                                colorIndex
                            ] : 'rgba(0,0,0,0.7)',
                            borderDash: [],
                            fill: false,
                            order: 1,
                            cubicInterpolationMode: 'monotone'
                        });
                    });

                return {
                    datasets,
                    visibleDepths
                };
            }

            function getChartOptions(isDetailed, activeParams) {
                const now = new Date('<?=$current_date?>');
                const overviewEnd = new Date('<?=$current_date?>');
                overviewEnd.setDate(now.getDate() + 30);
                const detailedEnd = new Date('<?=$current_date?>');
                detailedEnd.setDate(now.getDate() + 7);
                const depthParams = activeParams.filter(p => normalizedSoilData[p] && !normalizedSoilData[p]
                    .isAccumulative);
                const visibleDepths = [...new Set(depthParams.map(p => {
                    const depth = normalizedSoilData[p].depth.replace('cm', '');
                    return activeDepths.includes(depth) ? depth : null;
                }).filter(d => d !== null))].sort((a, b) => parseInt(a) - parseInt(b));

                const zoneHeight = visibleDepths.length > 0 ? 100 / visibleDepths.length : 100;

                const tickPositions = [];
                const tickLabels = {};

                visibleDepths.forEach((depth, index) => {
                    const centerPos = 100 - (index + 0.5) * zoneHeight;
                    tickPositions.push(centerPos);
                    tickLabels[centerPos] = `${depth} см`;
                });

                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        shiftPlugin: { enabled: true },
                        fixedPositionPlugin : { enabled: true },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Осадки (мм)') {
                                        const value = context.raw.original.toFixed(2);
                                        return `Осадки: ${value < <?= MIN_PRECIPITATION_THRESHOLD ?> ? '0' : value} мм`;
                                    }

                                    if (context.raw && context.raw.original !== undefined) {
                                        return `${context.dataset.label}: ${context.raw.original.toFixed(2)}`;
                                    }
                                    return `${context.dataset.label}: ${context.parsed.y.toFixed(2)}`;
                                }
                            }
                        },
                        legend: {
                            display: false
                        },
                        zoom: isDetailed ? {
                            zoom: {
                                wheel: {
                                    enabled: false
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'xy'
                            },
                            pan: {
                                enabled: true,
                                mode: 'xy'
                            }
                        } : undefined
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Глубина',
                                font: {
                                    weight: 'bold',
                                    size: 12
                                }
                            },
                            grid: {
                                color: function(context) {
                                    if (context.tick.value % zoneHeight === 0) {
                                        return 'rgb(0, 0, 0)';
                                    }
                                    return 'rgb(0, 0, 0)';
                                },
                                lineWidth: function(context) {
                                    return context.tick.value % zoneHeight === 0 ? 2 : 1;
                                },
                                drawTicks: false
                            },
                            min: 0,
                            max: 100,
                            ticks: {
                                callback: function(value, index) {
                                    if (index < tickPositions.length) {
                                        return tickLabels[tickPositions[index]];
                                    }
                                    return '';
                                },
                                stepSize: zoneHeight,
                                autoSkip: false,
                                count: visibleDepths.length,
                                major: {
                                    enabled: true
                                },
                                font: {
                                    weight: 'bold',
                                    size: 12
                                },
                                maxRotation: 0,
                                padding: 0
                            },
                            afterTickToLabelConversion: function(scaleInstance) {
                                scaleInstance.ticks.forEach((tick, index) => {
                                    if (index < tickPositions.length) {
                                        tick.value = tickPositions[index];
                                        tick.label = tickLabels[tickPositions[index]];
                                        tick.major = true;
                                    } else {
                                        tick.label = '';
                                    }
                                });
                            },
                            grid: {
                                color: function(context) {
                                    for (let i = 0; i <= visibleDepths.length; i++) {
                                        const zonePosition = 100 - i * zoneHeight;
                                        if (Math.abs(context.tick.value - zonePosition) < 1) {
                                            return 'rgba(0, 0, 0, 0.1)';
                                        }
                                    }
                                    return 'rgba(0, 0, 0, 0.02)';
                                },
                                drawTicks: false
                            },
                            position: 'left',
                            afterFit: function(scale) {
                                scale.width = 80;
                            }
                        },
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'HH:mm dd.MM.yyyy',
                                displayFormats: {
                                    hour: isDetailed ? 'HH:mm\ndd.MM' : 'HH:mm',
                                    day: 'dd.MM.yyyy'
                                },
                                unit: isDetailed ? 'hour' : undefined
                            },
                            min: now,
                            max: isDetailed ? detailedEnd : overviewEnd,
                            title: {
                                display: true,
                                text: 'Время'
                            },
                            offset: false,
                            grid: {
                                offset: false 
                            },
                            ticks: {
                                align: 'center'
                            },
                            ticks: isDetailed ? {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45,
                                callback: function(value) {
                                    const date = new Date(value);
                                    const hours = date.getHours().toString().padStart(2, '0');
                                    const minutes = date.getMinutes().toString().padStart(2, '0');
                                    const day = date.getDate();
                                    const month = date.toLocaleString('ru', {
                                        month: 'short'
                                    });
                                    return `${hours}:${minutes}\n${day} ${month}`;
                                }
                            } : {}
                        }
                    },
                    elements: {
                        line: {
                            fill: false,
                            tension: 0.4
                        },
                        point: {
                            radius: 0
                        }
                    },
                    layout: {
                        padding: {
                            left: 0,
                            right: 0,
                            top: 0,
                            bottom: 0
                        }
                    }
                };
            }

            let currentVisibleParams = Object.keys(normalizedSoilData).filter(p =>
                normalizedSoilData[p] && (normalizedSoilData[p].type === 'humidity' || normalizedSoilData[p]
                    .isAccumulative)
            );

            const overviewCtx = document.getElementById('soilOverviewChart').getContext('2d');
            const detailedCtx = document.getElementById('soilDetailedChart').getContext('2d');

            let overviewChart = new Chart(overviewCtx, {
                type: 'line',
                data: {
                    datasets: []
                },
                options: getChartOptions(false, currentVisibleParams),
                plugins: [precipitationShiftPlugin] 
            });

            let detailedChart = new Chart(detailedCtx, {
                type: 'line',
                data: {
                    datasets: []
                },
                options: getChartOptions(true, currentVisibleParams),
                plugins: [precipitationShiftPlugin]
            });

            function updateCharts() {
                const activeParams = Object.keys(normalizedSoilData).filter(p =>
                    normalizedSoilData[p] && (normalizedSoilData[p].type === currentShowType ||
                        normalizedSoilData[p].isAccumulative)
                );

                const { datasets } = createDatasets(currentShowType, activeParams);

                overviewChart.options = getChartOptions(false, activeParams);
                detailedChart.options = getChartOptions(true, activeParams);

                overviewChart.data.datasets = datasets;
                detailedChart.data.datasets = datasets;
                [overviewChart, detailedChart].forEach(chart => {
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        if (dataset.precipitation) {
                            const meta = chart.getDatasetMeta(datasetIndex);
                            if (meta && meta.data) {
                                meta.data.forEach(bar => {
                                    bar._precipShiftApplied = false;
                                    delete bar._precipOriginalX;
                                });
                            }
                        }
                    });
                });

                overviewChart.update('none');
                detailedChart.update('none');

                if (document.querySelector('#soil-tab .detailed-card').style.display !== 'none') {
                    const container = document.querySelector('#soil-tab .detailed-card .card-body');
                    container.scrollLeft = container.scrollWidth;
                }
            }

            // Initial chart setup with proper precipitation positioning
            updateCharts();
            
            // Force initial precipitation positioning after charts are rendered
            setTimeout(() => {
                [overviewChart, detailedChart].forEach(chart => {
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        if (dataset.precipitation) {
                            const meta = chart.getDatasetMeta(datasetIndex);
                            if (meta && meta.data) {
                                meta.data.forEach(bar => {
                                    if (!bar._precipShiftApplied && bar.width) {
                                        bar.x += bar.width / 2;
                                        bar._precipShiftApplied = true;
                                        bar._precipOriginalX = bar.x;
                                    }
                                });
                            }
                        }
                    });
                    chart.update('none');
                });
            }, 100);

            document.getElementById('soilParamTypeToggle').addEventListener('change', function(e) {
                currentShowType = e.target.checked ? 'temp' : 'humidity';
                updateCharts();
            });

            document.getElementById('soilChartToggle').addEventListener('change', function(e) {
                const showDetailed = !e.target.checked;
                document.querySelector('#soil-tab .overview-card').style.display = showDetailed ?
                    'none' : 'block';
                document.querySelector('#soil-tab .detailed-card').style.display = showDetailed ?
                    'block' : 'none';

                if (showDetailed) {
                    const container = document.querySelector('#soil-tab .detailed-card .card-body');
                    container.scrollLeft = container.scrollWidth;
                    updateCharts();
                } else {
                    const container = document.querySelector('#soil-tab .overview-card .card-body');
                    container.scrollLeft = 0;
                    setTimeout(() => {
                        updateCharts();
                    }, 100);
                }
                
            });

            const detailedContainer = document.querySelector('#soil-tab .detailed-card .card-body');
            let isMouseOverChart = false;
            detailedContainer.addEventListener('mouseenter', () => isMouseOverChart = true);
            detailedContainer.addEventListener('mouseleave', () => isMouseOverChart = false);
            detailedContainer.addEventListener('wheel', function(e) {
                if (!isMouseOverChart) return;
                e.preventDefault();
                this.scrollLeft += e.deltaY > 0 ? 100 : -100;
            });

            const totalMoistureData = calculateTotalMoisture();
            const moistureOverviewCtx = document.getElementById('moistureOverviewChart').getContext('2d');
            const moistureDetailedCtx = document.getElementById('moistureDetailedChart').getContext('2d');

            function getMoistureChartOptions(isDetailed, yellow, green, blue) {
                const now = new Date('<?=$current_date?>');
                const overviewEnd = new Date('<?=$current_date?>');
                overviewEnd.setDate(now.getDate() + 30);
                const detailedEnd = new Date('<?=$current_date?>');
                detailedEnd.setDate(now.getDate() + 7);

                const humidityCount = window.deviceHumidityCount || 3;
                const moistureData = createMoistureDatasets();
                const actualMaxMoisture = moistureData.length > 0 && moistureData[0].data.length > 0 ? 
                    Math.max(...moistureData[0].data.map(d => d.original)) : (humidityCount * 100);
                
                // Calculate Y scale so that actualMaxMoisture represents 90% of the scale
                const maxYValue = actualMaxMoisture / 0.9;

                let maxPrecip = 0;
                const precipData = normalizedSoilData['humidity_accumulative']?.data || [];
                for (let i = 1; i < precipData.length; i++) {
                    const diff = precipData[i].original - precipData[i - 1].original;
                    if (diff > maxPrecip && diff >= <?= MIN_PRECIPITATION_THRESHOLD ?>) {
                        maxPrecip = diff;
                    }
                }

                const zone1End = yellow;
                const zone2End = green;
                const zone3End = blue;

                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    animation: {
                        duration: isDetailed ? 5 : 150,
                        onComplete: function(animation) {
                            if (isDetailed) {
                                setTimeout(() => {
                                    this.update('none');
                                }, 1000000);
                            }
                        }
                    },
                    plugins: {
                        shiftPlugin: { enabled: true },
                        fixedPositionPlugin : { enabled: true },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Осадки (мм)') {
                                        return `Осадки: ${context.raw.original.toFixed(2)} мм`;
                                    }
                                    return `${context.dataset.label}: ${context.raw.original.toFixed(2)}`;
                                },
                                title: function(tooltipItems) {
                                    const date = new Date(tooltipItems[0].raw.x);
                                    return date.toLocaleString('ru', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        day: 'numeric',
                                        month: 'short'
                                    }).replace(',', '');
                                }
                            }
                        },
                        legend: {
                            display: false
                        },
                        annotation: {
                            annotations: {
                                zone1: {
                                    type: 'box',
                                    xMin: now,
                                    xMax: isDetailed ? detailedEnd : overviewEnd,
                                    yMin: 0,
                                    yMax: zone1End,
                                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                    borderWidth: 0
                                },
                                zone2: {
                                    type: 'box',
                                    xMin: now,
                                    xMax: isDetailed ? detailedEnd : overviewEnd,
                                    yMin: zone1End,
                                    yMax: zone2End,
                                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                                    borderWidth: 0
                                },
                                zone3: {
                                    type: 'box',
                                    xMin: now,
                                    xMax: isDetailed ? detailedEnd : overviewEnd,
                                    yMin: zone2End,
                                    yMax: zone3End,
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderWidth: 0
                                },
                                zone4: {
                                    type: 'box',
                                    xMin: now,
                                    xMax: isDetailed ? detailedEnd : overviewEnd,
                                    yMin: zone3End,
                                    yMax: maxYValue,
                                    backgroundColor: 'rgba(54, 162, 235, 0.4)',
                                    borderWidth: 0
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Значение',
                                font: {
                                    weight: 'bold',
                                    size: 12
                                }
                            },
                            min: 0,
                            max: maxYValue
                        },
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'HH:mm dd.MM.yyyy',
                                displayFormats: {
                                    hour: 'HH:mm',
                                    day: 'dd.MM.yyyy'
                                },
                                locale: 'ru',
                                unit: isDetailed ? 'hour' : undefined
                            },
                            min: now,
                            max: isDetailed ? detailedEnd : overviewEnd,
                            offset: false,
                            grid: {
                                offset: false 
                            },
                            ticks: {
                                align: 'center'
                            },
                            title: {
                                display: true,
                                text: 'Время'
                            },
                            ticks: isDetailed ? {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45,
                                callback: function(value) {
                                    const date = new Date(value);
                                    const hours = date.getHours().toString().padStart(2, '0');
                                    const minutes = date.getMinutes().toString().padStart(2, '0');
                                    const day = date.getDate();
                                    const month = date.toLocaleString('ru', {
                                        month: 'short'
                                    });
                                    return `${hours}:${minutes}\n${day} ${month}`;
                                }
                            } : {}
                        }
                    },
                    elements: {
                        line: {
                            fill: false,
                            tension: 0.4
                        },
                        point: {
                            radius: 0,
                            hoverRadius: 5
                        }
                    }
                };
            }

            function createMoistureDatasets() {
                const allMoistureDepths = ['5cm', '15cm', '25cm', '35cm', '45cm', '55cm'];
                const humidityCount = window.deviceHumidityCount || 3;
                const moistureDepthsToSum = allMoistureDepths.slice(0, humidityCount);

                const result = [];
                const timeMap = new Map();

                moistureDepthsToSum.forEach(depth => {
                    const param = 'humidity_' + depth;
                    if (normalizedSoilData[param] && normalizedSoilData[param].data) {
                        normalizedSoilData[param].data.forEach(point => {
                            if (!timeMap.has(point.x)) {
                                timeMap.set(point.x, {
                                    x: point.x,
                                    sum: 0,
                                    count: 0,
                                    has_data: point.has_data,
                                    interpolated: point.interpolated
                                });
                            }
                            timeMap.get(point.x).sum += point.original;
                            timeMap.get(point.x).count++;
                        });
                    }
                });

                timeMap.forEach((value, key) => {
                    if (value.count > 0) {
                        result.push({
                            x: key,
                            y: value.sum,
                            original: value.sum,
                            has_data: value.has_data,
                            interpolated: value.interpolated
                        });
                    }
                });

                result.sort((a, b) => new Date(a.x) - new Date(b.x));

                let moistureLabel = 'Суммарная влажность';
                if (moistureDepthsToSum.length === 1) {
                    moistureLabel = `Суммарная влажность ${parseInt(moistureDepthsToSum[0])} см`;
                } else if (moistureDepthsToSum.length > 1) {
                    const firstDepth = parseInt(moistureDepthsToSum[0]);
                    const lastDepth = parseInt(moistureDepthsToSum[moistureDepthsToSum.length - 1]);
                    moistureLabel = `Суммарная влажность ${firstDepth}-${lastDepth} см`;
                }

                const precipMap = new Map();
                const accumulativeData = normalizedSoilData['humidity_accumulative']?.data || [];

                for (let i = 1; i < accumulativeData.length; i++) {
                    const prev = accumulativeData[i - 1];
                    const current = accumulativeData[i];
                    const diff = current.original - prev.original;
                    const precipValue = diff < <?= MIN_PRECIPITATION_THRESHOLD ?> ? 0 : diff;

                    precipMap.set(current.x, precipValue);
                }

                const precipDataForMoisture = result.map(moisturePoint => {
                    const precipValue = precipMap.get(moisturePoint.x) || 0;
                    return {
                        x: moisturePoint.x,
                        y: precipValue,
                        original: precipValue
                    };
                });

                const maxMoisture = result.length > 0 ? Math.max(...result.map(d => d.original)) : (humidityCount * 100);
                const moistureScaleMax = maxMoisture / 0.9; 
                
                const maxPrecip = Math.max(1, ...precipDataForMoisture.map(d => d.original));
            
                const normalizationFactor = maxPrecip > 0 ? moistureScaleMax / maxPrecip : 1;

                return [{
                        label: moistureLabel,
                        data: result,
                        borderColor: 'rgba(0, 100, 200, 0.8)',
                        backgroundColor: 'rgba(0, 100, 200, 0.3)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Осадки (мм)',
                        data: precipDataForMoisture.map(d => ({
                            ...d,
                            y: d.original * normalizationFactor
                        })),
                        type: 'bar',
                        backgroundColor: 'rgba(135, 206, 235, 0.7)',
                        borderColor: 'rgba(135, 206, 235, 0.7)',
                        borderWidth: 0,
                        yAxisID: 'y',
                        barPercentage: 1.0,
                        categoryPercentage: 1.0,
                        order: 2,
                        precipitation: true,
                        skipNull: true
                    }
                ];
            }

            function updateChartZones(chart, yellow, green, blue, finalMaxY) {
                if (!chart.options.plugins.annotation) {
                    chart.options.plugins.annotation = {
                        annotations: {}
                    };
                }

                const now = new Date();
                const isDetailed = chart === moistureDetailedChart;
                const startDate = isDetailed ?
                    new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000) :
                    new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);

                chart.options.plugins.annotation.annotations = {
                    zone1: {
                        type: 'box',
                        xMin: startDate,
                        xMax: now,
                        yMin: 0,
                        yMax: yellow,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 0
                    },
                    zone2: {
                        type: 'box',
                        xMin: startDate,
                        xMax: now,
                        yMin: yellow,
                        yMax: green,
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        borderWidth: 0
                    },
                    zone3: {
                        type: 'box',
                        xMin: startDate,
                        xMax: now,
                        yMin: green,
                        yMax: blue,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 0
                    },
                    zone4: {
                        type: 'box',
                        xMin: startDate,
                        xMax: now,
                        yMin: blue,
                        yMax: finalMaxY,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 0
                    }
                };
            }

            function updateMoistureCharts() {
                const finalMaxY = validateBlueZone();

                const yellow = parseFloat(document.getElementById('yellowZoneStart').value) || 25;
                const green = parseFloat(document.getElementById('greenZoneStart').value) || 50;
                const blue = parseFloat(document.getElementById('blueZoneStart').value) || 75;
                const newDatasets = createMoistureDatasets();
                
                moistureOverviewChart.data.datasets = newDatasets;
                moistureDetailedChart.data.datasets = newDatasets;

                updateChartZones(moistureOverviewChart, yellow, green, blue, finalMaxY);
                updateChartZones(moistureDetailedChart, yellow, green, blue, finalMaxY);

                [moistureOverviewChart, moistureDetailedChart].forEach(chart => {
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        if (dataset.precipitation) {
                            const meta = chart.getDatasetMeta(datasetIndex);
                            meta.data.forEach(bar => {
                                bar._precipShiftApplied = false;
                            });
                        }
                    });
                });

                moistureOverviewChart.update('none');
                moistureDetailedChart.update('none');

                if (document.querySelector('#moisture-tab .detailed-card').style.display !== 'none') {
                    const container = document.querySelector('#moisture-tab .detailed-card .card-body');
                    container.scrollLeft = container.scrollWidth;
                }
            }

            setTimeout(() => {
                [moistureOverviewChart, moistureDetailedChart].forEach(chart => {
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        if (dataset.precipitation) {
                            const meta = moistureOverviewChart.getDatasetMeta(datasetIndex);
                            if (meta && meta.data) {
                                meta.data.forEach(bar => {
                                    if (!bar._precipShiftApplied && bar.width) {
                                        bar.x += bar.width / 2;
                                        bar._precipShiftApplied = true;
                                        bar._precipOriginalX = bar.x;
                                    }
                                });
                            }
                        }
                    });
                    chart.update('none');
                });
            }, 50);

            function validateBlueZone() {
                const totalMoistureData = calculateTotalMoisture();

                const humidityCount = window.deviceHumidityCount || 3;
                const maxMoisture = totalMoistureData.length > 0 ? Math.max(...totalMoistureData.map(d => d.original)) : (humidityCount * 100);
                
                const maxYValue = maxMoisture / 0.9;
                const finalMaxY = Math.ceil(maxYValue);

                const blueInput = document.getElementById('blueZoneStart');
                const blueSlider = document.getElementById('blueZoneSlider');

                let blueValue = parseInt(blueInput.value) || finalMaxY;

                if (blueValue > finalMaxY) {
                    blueValue = finalMaxY;
                    blueInput.value = blueValue;
                    blueSlider.value = blueValue;
                }
                blueInput.setAttribute('max', finalMaxY);
                blueSlider.setAttribute('max', finalMaxY);

                return finalMaxY;
            }

            function enforceMaxOnInput(inputElement, maxValue) {
                inputElement.addEventListener('input', function() {
                    let value = parseInt(this.value);
                    if (isNaN(value)) value = 0;
                    if (value > maxValue) {
                        this.value = maxValue;
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const blueInput = document.getElementById('blueZoneStart');
                const blueSlider = document.getElementById('blueZoneSlider');
                const initialMaxY = validateBlueZone();

                enforceMaxOnInput(blueInput, initialMaxY);
                enforceMaxOnInput(blueSlider, initialMaxY);
                setInterval(validateBlueZone, 5000);
            });

            document.getElementById('yellowZoneStart').addEventListener('input', updateMoistureCharts);
            document.getElementById('greenZoneStart').addEventListener('input', updateMoistureCharts);
            document.getElementById('blueZoneStart').addEventListener('input', updateMoistureCharts);
            document.getElementById('yellowZoneSlider').addEventListener('input', function() {
                document.getElementById('yellowZoneStart').value = this.value;
                updateMoistureCharts();
            });
            document.getElementById('greenZoneSlider').addEventListener('input', function() {
                document.getElementById('greenZoneStart').value = this.value;
                updateMoistureCharts();
            });
            document.getElementById('blueZoneSlider').addEventListener('input', function() {
                document.getElementById('blueZoneStart').value = this.value;
                updateMoistureCharts();
            });

            document.getElementById('yellowZoneStart').addEventListener('input', function() {
                document.getElementById('yellowZoneSlider').value = this.value;
                updateMoistureCharts();
            });
            document.getElementById('greenZoneStart').addEventListener('input', function() {
                document.getElementById('greenZoneSlider').value = this.value;
                updateMoistureCharts();
            });
            document.getElementById('blueZoneStart').addEventListener('input', function() {
                document.getElementById('blueZoneSlider').value = this.value;
                updateMoistureCharts();
            });

            document.getElementById('yellowZoneSlider').addEventListener('change', sendMoistureZonesToServer);
            document.getElementById('greenZoneSlider').addEventListener('change', sendMoistureZonesToServer);
            document.getElementById('blueZoneSlider').addEventListener('change', sendMoistureZonesToServer);

            document.getElementById('yellowZoneStart').addEventListener('change', sendMoistureZonesToServer);
            document.getElementById('greenZoneStart').addEventListener('change', sendMoistureZonesToServer);
            document.getElementById('blueZoneStart').addEventListener('change', sendMoistureZonesToServer);

            function validateZones() {
                let yellow = parseInt(document.getElementById('yellowZoneStart').value) || 0;
                let green = parseInt(document.getElementById('greenZoneStart').value) || 50;
                let blue = parseInt(document.getElementById('blueZoneStart').value) || 75;

                if (yellow >= green) {
                    yellow = green - 1;
                    if (yellow < 0) yellow = 0;
                    document.getElementById('yellowZoneStart').value = yellow;
                    document.getElementById('yellowZoneSlider').value = yellow;
                }

                if (green <= yellow) {
                    green = yellow + 1;
                    document.getElementById('greenZoneStart').value = green;
                    document.getElementById('greenZoneSlider').value = green;
                }
                if (green >= blue) {
                    green = blue - 1;
                    document.getElementById('greenZoneStart').value = green;
                    document.getElementById('greenZoneSlider').value = green;
                }

                if (blue <= green) {
                    blue = green + 1;
                    document.getElementById('blueZoneStart').value = blue;
                    document.getElementById('blueZoneSlider').value = blue;
                }
                if (blue > 100) {
                    document.getElementById('blueZoneStart').value = blue;
                    document.getElementById('blueZoneSlider').value = blue;
                }

                updateMoistureCharts();
            }

            document.querySelectorAll('#moisture-tab input[type="range"], #moisture-tab input[type="number"]')
                .forEach(input => {
                    input.addEventListener('input', function() {
                        validateZones();
                    });
                });

            function updateMoistureZones(deviceId, yellow, green, blue) {
                const formData = new FormData();
                formData.append('device_id', deviceId);
                formData.append('yellow_zone_start', yellow);
                formData.append('green_zone_start', green);
                formData.append('blue_zone_start', blue);

                fetch('update_moisture_zones.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Zones updated successfully');
                        } else {
                            console.error('Failed to update zones:', data.error);
                            document.getElementById('yellowZoneStart').value = prevYellow;
                            document.getElementById('greenZoneStart').value = prevGreen;
                            document.getElementById('blueZoneStart').value = prevBlue;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('yellowZoneStart').value = prevYellow;
                        document.getElementById('greenZoneStart').value = prevGreen;
                        document.getElementById('blueZoneStart').value = prevBlue;
                    });
            }

            function sendMoistureZonesToServer() {
                const yellow = parseFloat(document.getElementById('yellowZoneStart').value) || 25;
                const green = parseFloat(document.getElementById('greenZoneStart').value) || 50;
                const blue = parseFloat(document.getElementById('blueZoneStart').value) || 75;

                updateMoistureZones(deviceId, yellow, green, blue, function(success) {
                    if (success) {
                        prevYellow = yellow;
                        prevGreen = green;
                        prevBlue = blue;
                    } else {
                        document.getElementById('yellowZoneStart').value = prevYellow;
                        document.getElementById('yellowZoneSlider').value = prevYellow;
                        document.getElementById('greenZoneStart').value = prevGreen;
                        document.getElementById('greenZoneSlider').value = prevGreen;
                        document.getElementById('blueZoneStart').value = prevBlue;
                        document.getElementById('blueZoneSlider').value = prevBlue;
                        updateMoistureCharts();
                    }
                });
            }

            let prevYellow = <?= $yellow_zone_start ?>;
            let prevGreen = <?= $green_zone_start ?>;
            let prevBlue = <?= $blue_zone_start ?>;
            const deviceId = <?= $device_id ?>;

            document.getElementById('yellowZoneStart').addEventListener('change', function() {
                const newValue = parseFloat(this.value) || 25;
                updateMoistureZones(deviceId, newValue, prevGreen, prevBlue);
                prevYellow = newValue;
            });

            document.getElementById('greenZoneStart').addEventListener('change', function() {
                const newValue = parseFloat(this.value) || 50;
                updateMoistureZones(deviceId, prevYellow, newValue, prevBlue);
                prevGreen = newValue;
            });

            document.getElementById('blueZoneStart').addEventListener('change', function() {
                const newValue = parseFloat(this.value) || 75;
                updateMoistureZones(deviceId, prevYellow, prevGreen, newValue);
                prevBlue = newValue;
            });

            const initialYellowZone = <?= $yellow_zone_start ?>;
            const initialGreenZone = <?= $green_zone_start ?>;
            const initialBlueZone = <?= $blue_zone_start ?>;

            const moistureOverviewChart = new Chart(moistureOverviewCtx, {
                type: 'line',
                data: {
                    datasets: createMoistureDatasets()
                },
                options: getMoistureChartOptions(false, initialYellowZone, initialGreenZone, initialBlueZone),
                plugins: [precipitationShiftPlugin]
            });

            // Force precipitation positioning for overview chart
            setTimeout(() => {
                moistureOverviewChart.data.datasets.forEach((dataset, datasetIndex) => {
                    if (dataset.precipitation) {
                        const meta = moistureOverviewChart.getDatasetMeta(datasetIndex);
                        if (meta && meta.data) {
                            meta.data.forEach(bar => {
                                if (!bar._precipShiftApplied && bar.width) {
                                    bar.x += bar.width / 2;
                                    bar._precipShiftApplied = true;
                                    bar._precipOriginalX = bar.x;
                                }
                            });
                        }
                    }
                });
                moistureOverviewChart.update('none');
            }, 100);

            const moistureDetailedChart = new Chart(moistureDetailedCtx, {
                type: 'line',
                data: {
                    datasets: createMoistureDatasets()
                },
                options: getMoistureChartOptions(true, initialYellowZone, initialGreenZone, initialBlueZone),
                plugins: [precipitationShiftPlugin]
            });

            document.getElementById('moistureChartToggle').addEventListener('change', function(e) {
                const showOverview = e.target.checked;
            
                updateMoistureCharts();
                
                document.querySelector('#moisture-tab .overview-card').style.display = showOverview ?
                    'block' : 'none';
                document.querySelector('#moisture-tab .detailed-card').style.display = showOverview ?
                    'none' : 'block';

                if (!showOverview) {
                    setTimeout(() => {
                        const container = document.querySelector('#moisture-tab .detailed-card .card-body');
                        container.scrollLeft = container.scrollWidth;
                    }, 50);
                } else {

                    setTimeout(() => {
                        const container = document.querySelector('#moisture-tab .overview-card .card-body');
                        if (container) {
                            container.scrollLeft = 0;
                        }
                        updateMoistureCharts();
                    }, 100);
                }
            });

            const moistureContainer = document.querySelector('#moisture-tab .detailed-card .card-body');
            moistureContainer.scrollLeft = moistureContainer.scrollWidth;

            let isMouseOverMoistureChart = false;
            moistureContainer.addEventListener('mouseenter', () => isMouseOverMoistureChart = true);
            moistureContainer.addEventListener('mouseleave', () => isMouseOverMoistureChart = false);
            moistureContainer.addEventListener('wheel', function(e) {
                if (!isMouseOverMoistureChart) return;
                e.preventDefault();
                this.scrollLeft += e.deltaY > 0 ? 100 : -100;
            });

            moistureDetailedChart.update();

        });
        <?php endif; ?>

        <?php if ($show_forecast_tab && !empty($forecast_values)): ?>

        function degreesToCompass(degrees) {
            const directions = ['С', 'ССВ', 'СВ', 'ВСВ', 'В', 'ВЮВ', 'ЮВ', 'ЮЮВ', 'Ю', 'ЮЮЗ', 'ЮЗ', 'ЗЮЗ', 'З', 'ЗСЗ',
                'СЗ', 'ССЗ'
            ];
            const index = Math.round((degrees % 360) / 22.5);
            return directions[(index % 16)];
        }

        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const nowISO = now.toISOString();
            const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString();

            const fixedMinY = 0;
            const fixedMaxY = 100;
            const yPadding = 0;

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Количество осадков (мм)') {
                                    return `${context.dataset.label}: ${context.raw.original.toFixed(2)} мм`;
                                }
                                if (context.dataset.label === 'Дождь') return null;
                                let label = context.dataset.label || '';
                                if (label) label += ': ';

                                const originalValue = context.raw.original !== undefined ?
                                    context.raw.original : context.parsed.y;

                                if (originalValue !== null) {
                                    if (context.dataset.label.startsWith('Дождь')) {
                                        label += originalValue == 1 ? 'Да' : 'Нет';
                                    } else if (context.dataset.label.startsWith('Направление ветра')) {
                                        const direction = degreesToCompass(originalValue);
                                        label += direction;
                                    } else if (context.dataset.label.startsWith('Видимость')) {
                                        const visibilityInMeters = originalValue / 1000;
                                        label += visibilityInMeters.toFixed(2) + ' м';
                                    } else {
                                        label += originalValue.toFixed(2);
                                    }
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    zoom: {
                        pan: {
                            enabled: false
                        },
                        zoom: {
                            wheel: {
                                enabled: false
                            },
                            pinch: {
                                enabled: false
                            }
                        }
                    },
                    annotation: {
                        annotations: {
                            pastBackground: {
                                type: 'box',
                                xMin: '<?= date('Y-m-d\T00:00:00', strtotime('-1 day')) ?>',
                                xMax: nowISO,
                                backgroundColor: 'rgba(144, 238, 144, 0.2)',
                                borderWidth: 0
                            },
                            currentTimeLine: {
                                type: 'line',
                                xMin: nowISO,
                                xMax: nowISO,
                                borderColor: 'rgb(166, 193, 164)',
                                borderWidth: 1,
                                label: {
                                    content: 'Сейчас',
                                    enabled: true,
                                    position: 'top'
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        tooltipFormat: 'dd.MM.yyyy HH:mm',
                        displayFormats: {
                            hour: 'HH:mm',
                            day: 'dd.MM.yyyy'
                        },
                        locale: 'ru',
                        title: {
                            display: true,
                            text: 'Время'
                        },
                        min: '<?= $min_date ?>',
                        max: '<?= $max_date ?>',
                        ticks: {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45,
                                callback: function(value) {
                                    const date = new Date(value);
                                    const hours = date.getHours().toString().padStart(2, '0');
                                    const minutes = date.getMinutes().toString().padStart(2, '0');
                                    const day = date.getDate();
                                    const month = date.toLocaleString('ru', {
                                        month: 'short'
                                    });
                                    return `${day} ${month}`;
                                }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Значения'
                        },
                        min: fixedMinY - yPadding,
                        max: fixedMaxY + yPadding,
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(0);
                            }
                        }
                    }
                }
            };

            const overviewConfig = {
                type: 'line',
                data: {
                    datasets: [
                        <?php foreach ($chart_data as $param => $dataset): ?> {
                            label: '<?= addslashes($dataset['label']) ?>',
                            data: <?= json_encode($dataset['data']) ?>,
                            borderColor: '<?= $dataset['borderColor'] ?>',
                            backgroundColor: '<?= $dataset['backgroundColor'] ?>',
                            hidden: <?= $dataset['hidden'] ? 'true' : 'false' ?>,
                            yAxisID: 'y',
                            tension: <?= $dataset['tension'] ?>,
                            borderWidth: <?= isset($dataset['borderWidth']) ? $dataset['borderWidth'] : 1 ?>,
                            pointRadius: <?= $param == 'crain' ? '0' : '2' ?>,
                            pointHoverRadius: <?= $param == 'crain' ? '0' : '4' ?>,
                            type: '<?= $dataset['type'] ?>',
                            <?php if ($param == 'crain'): ?>
                            borderRadius: <?= $dataset['borderRadius'] ?>,
                            barPercentage: <?= $dataset['barPercentage'] ?>,
                            categoryPercentage: <?= $dataset['categoryPercentage'] ?>
                            <?php endif; ?>
                            <?php if (isset($dataset['fill']) && $dataset['fill']): ?>
                            fill: true
                            <?php endif; ?>
                        },
                        <?php endforeach; ?>
                    ]
                },
                options: commonOptions
            };

            const detailedConfig = {
                type: 'line',
                data: {
                    datasets: [
                        <?php foreach ($chart_data as $param => $dataset): ?> {
                            label: '<?= addslashes($dataset['label']) ?>',
                            data: <?= json_encode($dataset['data']) ?>,
                            borderColor: '<?= $dataset['borderColor'] ?>',
                            backgroundColor: '<?= $dataset['backgroundColor'] ?>',
                            hidden: <?= $dataset['hidden'] ? 'true' : 'false' ?>,
                            yAxisID: 'y',
                            tension: <?= $dataset['tension'] ?>,
                            borderWidth: <?= isset($dataset['borderWidth']) ? $dataset['borderWidth'] : 2 ?>,
                            pointRadius: <?= $param == 'crain' ? '0' : '4' ?>,
                            pointHoverRadius: <?= $param == 'crain' ? '0' : '6' ?>,
                            type: '<?= $dataset['type'] ?>',
                            <?php if ($param == 'crain'): ?>
                            borderRadius: <?= $dataset['borderRadius'] ?>,
                            barPercentage: <?= $dataset['barPercentage'] ?>,
                            categoryPercentage: <?= $dataset['categoryPercentage'] ?>
                            <?php endif; ?>
                            <?php if (isset($dataset['fill']) && $dataset['fill']): ?>
                            fill: true
                            <?php endif; ?>
                        },
                        <?php endforeach; ?>
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        x: {
                            ...commonOptions.scales.x,
                            time: {
                                ...commonOptions.scales.x.time,
                                unit: 'hour',
                                displayFormats: {
                                    hour: 'HH:mm',
                                    day: 'dd.MM.yyyy'
                                }
                            },
                            ticks: {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            };

            const overviewCtx = document.getElementById('forecastOverviewChart').getContext('2d');
            const overviewChart = new Chart(overviewCtx, overviewConfig);

            const detailedCtx = document.getElementById('forecastDetailedChart').getContext('2d');
            const detailedChart = new Chart(detailedCtx, detailedConfig);

            const detailedChartContainer = document.querySelector('#forecast-tab .detailed-card .card-body');
            let isMouseOverChart = false;

            detailedChartContainer.addEventListener('mouseenter', function() {
                isMouseOverChart = true;
            });

            detailedChartContainer.addEventListener('mouseleave', function() {
                isMouseOverChart = false;
            });

            detailedChartContainer.addEventListener('wheel', function(e) {
                if (!isMouseOverChart) return;

                e.preventDefault();
                const scrollAmount = e.deltaY > 0 ? 100 : -100;

                this.scrollLeft += scrollAmount;
            });

            document.querySelectorAll('#forecast-tab .parameter-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    if (e.target.tagName === 'INPUT') return;

                    const checkbox = this.querySelector('.toggle-label').textContent.trim();

                    const wasActive = this.classList.contains('active');
                    const datasetLabel = this.querySelector('.toggle-label').textContent.trim();

                    checkbox.checked = !wasActive;
                    this.classList.toggle('active', !wasActive);

                    [overviewChart, detailedChart].forEach(chart => {
                        const dataset = chart.data.datasets.find(ds => ds.label
                            .startsWith(datasetLabel));
                        if (dataset) {
                            dataset.hidden = wasActive;
                        }
                        chart.update();
                    });
                });
            });
        });

        document.getElementById('forecastChartToggle').addEventListener('change', function(e) {
            const isDetailed = e.target.checked;
            document.querySelector('#forecast-tab .overview-card').style.display = isDetailed ? 'none' :
                'block';
            document.querySelector('#forecast-tab .detailed-card').style.display = isDetailed ? 'block' :
                'none';
        });

        <?php endif; ?>
    </script>

    <style>
        .tab-container {}

        .tab-controls {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            border-bottom-color: #4CAF50;
            color: #4CAF50;
            font-weight: bold;
        }

        .tab-btn:hover:not(.active) {
            border-bottom-color: #ccc;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .depth-controls {
            margin-bottom: 20px;
            display: flex;
            align-content: center;
            flex-direction: row;
            align-items: center;
            height: 60px;
        }

        .control-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .depth-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .depth-btn {
            border: 2px solid #5755d9;
            background-color: #fff;
            color: #313131;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 60px;
            text-align: center;
            height: 36px;
        }

        .depth-btn:hover {
            background-color: #f0f8f0;
        }

        .depth-btn.active {
            background-color: #5755d9;
            color: white;
            box-shadow: 0 2px 4px rgb(87 85 217 / 20%);
        }

        .depth-btn.active:hover {
            background-color: #5755d9;
        }

        .card .card-body,
        .card .card-footer,
        .card .card-header {
            padding: 12px;
            padding-bottom: 0;
        }

        .btn-toggle {
            margin: 2px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            color: #333;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-toggle.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .btn-toggle:hover {
            background-color: #e9ecef;
        }

        .chart-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .card-body {
            padding: 20px;
            position: relative;
        }

        #detailedChart,
        #soilDetailedChart,
        #forecastDetailedChart {
            min-width: 4000px;
            height: 500px;
        }

        .parameter-toggles {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .parameter-toggle {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px 6px 6px;
            border-radius: 16px;
            background: #f5f7fa;
            border: 1px solid #e1e5eb;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            user-select: none;
            height: 32px;
            box-sizing: border-box;
        }

        .parameter-toggle:hover {
            background: #ebeff5;
            border-color: #d1d9e6;
        }

        .parameter-toggle.active {
            background: #f0f7ff;
            border-color: #cce0ff;
            box-shadow: 0 0 0 1px #cce0ff;
        }

        .parameter-toggle input {
            display: none;
        }

        .toggle-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            margin-right: 8px;
            position: relative;
            flex-shrink: 0;
        }

        .toggle-indicator::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .parameter-toggle.active .toggle-indicator::after {
            opacity: 1;
        }

        .toggle-label {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        @media (max-width: 768px) {
            .depth-buttons {
                gap: 6px;
            }

            .depth-btn {
                padding: 6px 12px;
                font-size: 12px;
                min-width: 50px;
            }

            .parameter-toggles {
                gap: 6px;
            }

            .parameter-toggle {
                padding: 4px 8px 4px 4px;
                font-size: 12px;
                height: 28px;
            }

            .toggle-indicator {
                width: 14px;
                height: 14px;
                margin-right: 6px;
            }

            .toggle-label {
                max-width: 120px;
            }

            .tab-btn {
                padding: 8px 12px;
                font-size: 14px;
            }
        }

        .chart-zone-boundary {
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background-color: rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .chart-zone-label {
            position: absolute;
            right: 10px;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.9);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            border: 1px solid #eee;
            z-index: 11;
        }

        #soil-tab .overview-card {
            display: none;
        }

        #soil-tab .detailed-card {
            display: block;
        }

        #moisture-tab .overview-card {
            display: none;
        }

        #moisture-tab .detailed-card {
            display: block;
        }

        .chart-zone-label {
            position: absolute;
            right: 10px;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
        }

        .zone-1-label {
            top: 12.5%;
            color: #1a5276;
        }

        .zone-2-label {
            top: 37.5%;
            color: #0e6251;
        }

        .zone-3-label {
            top: 62.5%;
            color: #7d6608;
        }

        .zone-4-label {
            top: 87.5%;
            color: #78281f;
        }

        .zone-controls .form-group {
            margin-bottom: 0;
        }

        .zone-controls label {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }

        .zone-controls .form-input {
            height: 32px;
            padding: 5px 10px;
            font-size: 14px;
        }

        .zone-controls input {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            padding: 5px 8px;
            text-align: center;
            font-size: 13px;
        }

        .zone-controls input::placeholder {
            color: #555;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .depth-controls>div {
                flex-direction: column;
                align-items: flex-start;
            }

            .zone-controls {
                margin-top: 10px;
            }
        }

        .slider {
            position: static;
        }
    </style>
    <!-- Map -->
    <script>
        let mapContainer = document.getElementById('map');
        if(mapContainer){
            initMap();

        async function initMap() {
            if (typeof ymaps3 === 'undefined') {
                console.warn('Yandex Maps API не загружен');
                return;
            }

            await ymaps3.ready;

            const {YMap, YMapDefaultSchemeLayer, YMapMarker, YMapDefaultFeaturesLayer} = ymaps3;

            // Инициализируем карту
            const map = new YMap(
                // Передаём ссылку на HTMLElement контейнера
                document.getElementById('map'),

                // Передаём параметры инициализации карты
                {
                    location: {
                        // Координаты центра карты
                       // center: [55.835966, 37.555171],
                        center: [<?=$longitude;?>, <?=$latitude;?>],

                        // Уровень масштабирования
                        zoom: 15
                    }
                }
            );            

            // Добавляем слой для отображения схематической карты
            map.addChild(new YMapDefaultSchemeLayer());

            // Добавляем слой для объектов (маркеры, линии и т.д.)
            map.addChild(new YMapDefaultFeaturesLayer());

            // Создаем элемент маркера
            const markerElement = document.createElement('div');
            markerElement.style.width = '30px';
            markerElement.style.height = '30px';
            markerElement.style.background = 'red';
            markerElement.style.borderRadius = '50%';
            markerElement.style.border = '2px solid white';
            markerElement.style.boxShadow = '0 0 10px rgba(0,0,0,0.5)';

            // Создаем маркер
            const marker = new YMapMarker(
                {
                    coordinates: [<?=$longitude;?>, <?=$latitude;?>], 
                },
                markerElement
            );

            // Добавляем маркер на карту
            map.addChild(marker);
        }
        }
        
    </script>
    <!-- Виджет прогноза -->
    <script>
       window.addEventListener('load', function () {
            const hourlyForecast = document.getElementById('hourlyForecast');
            if (!hourlyForecast) return;

            const scrollLeftBtn = document.getElementById('scrollLeft');
            const scrollRightBtn = document.getElementById('scrollRight');

            function getScrollStep() {
                const firstItem = hourlyForecast.firstElementChild;
                if (!firstItem) return 0;
                const itemWidth = firstItem.offsetWidth;
                const gap = 10;
                const visibleItems = Math.floor(hourlyForecast.clientWidth / (itemWidth + gap));
                return (itemWidth + gap) * (visibleItems > 1 ? visibleItems - 1 : 1);
            }

            function updateScrollButtons() {
                const maxScroll = hourlyForecast.scrollWidth - hourlyForecast.clientWidth;
                const currentScroll = hourlyForecast.scrollLeft;

                scrollLeftBtn.disabled = currentScroll <= 0;
                scrollRightBtn.disabled = currentScroll >= maxScroll - 5;
            }

            scrollLeftBtn.addEventListener('click', function () {
                const step = getScrollStep();
                hourlyForecast.scrollBy({ left: -step, behavior: 'smooth' });
            });

            scrollRightBtn.addEventListener('click', function () {
                const step = getScrollStep();
                hourlyForecast.scrollBy({ left: step, behavior: 'smooth' });
            });

            hourlyForecast.addEventListener('scroll', updateScrollButtons);
            window.addEventListener('resize', updateScrollButtons);

            setTimeout(updateScrollButtons, 250);
       });
    </script>
    <style>
      .weather-widget {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.9);
            padding: 15px;
            border-radius: 10px;
            gap: 15px;
        }

        .temperature-block {
            display: flex;
            align-items: center;
            padding: 0 0 0 10px;
            flex-shrink: 0;
        }

        .current-temp {
            font-size: 5em;
            font-weight: bold;
            margin: 0;
        }

        .current-temp-unit {
            margin-bottom: 36px;
            font-size: 1.5em;
            font-weight: 700;
        }

        .weather-icon-block {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .weather-icon {
            font-size: 4em;
        }

        .forecast-block {
            flex-grow: 1;
            min-width: 0;
            padding: 0;
        }

        .forecast-container {
            display: flex;
            align-items: center;
            width: 100%;
        }

        .hourly-forecast-wrapper {
            flex-grow: 1;
            overflow: hidden;
        }

        .hourly-forecast {
            display: flex;
            gap: 10px;
            padding: 5px 0;
            overflow-x: auto;
            scroll-behavior: smooth;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .hourly-forecast::-webkit-scrollbar {
            display: none;
        }

        .hour-forecast {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
            padding: 10px 5px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            text-align: center;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .hour-time {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .hour-icon {
            font-size: 20px;
        }

        .hour-temp-block {
            display: flex;
            align-items: center;
        }

        .hour-temp {
            font-size: 12px;
        }

        .hour-temp-unit {
            margin-bottom: 6px;
            font-size: 7px;
        }

        .scroll-arrow {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0 10px;
            z-index: 2;
        }

        .scroll-arrow:disabled {
            opacity: 0.2;
            cursor: default;
        }

        .weather-widget-day.active {
            background-color: #f0f8ff;
            border-left: 4px solid #007bff;
            transition: background-color 0.3s;
        }

        @media (max-width: 768px) {
            .weather-widget {
                flex-direction: column;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <!-- прогноз на 10 дней общий--> 
    <script>       
        const dayCards = document.querySelectorAll('.day-card');
        let tempDay = []
        let tempNight = []
        <?php 
            if(isset($forecastTenDayNight)):
            foreach($forecastTenDayNight as $day):?>
                tempDay.push(<?=round($day['day']['parameters']['2t'])?>)
                tempNight.push(<?=round($day['night']['parameters']['2t'])?>)
        <?php endforeach;?>  
        <?php endif;?>
        dayCards.forEach(card => {
            // Получаем ID дня, например, из data-атрибута (см. ниже, как его добавить)
            const targetId = card.getAttribute('data-target');
            if (!targetId) return;

            card.addEventListener('click', function(e) {
                e.preventDefault();
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    document.querySelectorAll('.weather-widget-day').forEach(el => {
                        el.classList.remove('active');
                    });
                    targetElement.classList.add('active');
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        // Функция для создания графика температуры
        function createTemperatureChart() {
            let chartContainer = document.getElementById('chart-container');
            if (chartContainer){
                const ctx = document.getElementById('chart-container').getContext('2d');
            const myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['*', '*', '*', '*', '*', '*', '*', '*', '*', '*'],
                    datasets: [{
                        label: 'День',
                        data: tempDay,
                        tension: 0.4,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        pointRadius: 3,        // Размер точек
                        pointHoverRadius: 4,    // Размер точек при наведении
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: (value, context) => value,
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            color: 'black',
                            offset: 2
                        }
                    },
                    {
                    label: 'Ночь',
                    data: tempNight, // новые данные
                    tension: 0.4,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)', // красный фон
                    borderColor: 'rgba(255, 99, 132, 1)',       // красная линия
                    borderWidth: 1,
                    pointRadius: 3,
                    pointHoverRadius: 4,
                    datalabels: {
                        align: 'start',       // 👈 привязка к ВЕРХНЕЙ границе
                        anchor: 'center',     // центрируем по оси X
                        clamp: true,          // не выходить за границы графика
                        formatter: (value) => value,
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        color: 'black',
                        offset: 2             // небольшой отступ от края
                    }
                }
                ]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },                       
                    },
                    scales: {
                        y: {
                            display: false,
                            min: Math.min(...tempNight) - 3,
                            max: Math.max(...tempDay) + 3,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            display: false,
                            ticks: {
                                padding: 1
                            }
                        }
                    },
                    layout: {
                        padding: {
                            left: 40,
                            right: 20,
                            top: 30,     // ✅ даём побольше места сверху
                            bottom: 30   // ✅ и снизу
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                },
                plugins: [ChartDataLabels] // Не забудь добавить плагин
            });

            }
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            createTemperatureChart();
        });

        // Добавляем интерактивность
        document.addEventListener('click', (e) => {
            if (e.target.closest('.day-card')) {
                const card = e.target.closest('.day-card');
                const cards = document.querySelectorAll('.day-card');                
            }
        });
    </script>
    <style>
        .weather-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            /*margin-top: 20px;*/
            padding: 10px 10px 0 10px;
            /*box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);*/
        }

        .forecast-header {
            color: #2d3436;
            font-size: 16px;
            font-weight: 700;
            margin-left: 10px;
        }

        .forecast-header h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .forecast-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .days-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 10px 10px 10px;
        }

        .day-card {
            background: white;
            border-radius: 10px;
            padding: 15px 10px;
            text-align: center;
            color: black;
            width: 100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .day-name {
            font-size: 12px;
            font-weight: bold;
        }

        .day-date {
            font-size: 12px;
            opacity: 0.9;
        }

        .weather-icon-day {
            margin: 0 auto 5px;
            font-size: 20px;
        }

        .temperature {
            font-size: 20px;
            font-weight: bold;
        }

        .chart-container {
            position: relative;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px 10px;
        }

        .chart-line {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.3);
        }

        .chart-points {
            position: relative;
            height: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .chart-point {
            position: relative;
            width: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .point-dot {
            width: 12px;
            height: 12px;
            background: #ff6b6b;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .point-dot:hover {
            transform: scale(1.2);
            background: #ff4757;
        }

        .point-line {
            position: absolute;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.3);
            transition: height 0.5s ease;
        }

        .point-temperature {
            color: white;
            font-size: 12px;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            margin-top: 5px;
        }

        .temp-scale {
            position: absolute;
            left: 10px;
            top: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .days-container {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .day-card {
                min-width: 80px;
                padding: 10px 5px;
            }
            
            .day-name {
                font-size: 12px;
            }
            
            .day-date {
                font-size: 10px;
            }
            
            .weather-icon {
                width: 30px;
                height: 30px;
                font-size: 20px;
            }
            
            .temperature {
                font-size: 14px;
            }
        }
    </style>
    <!-- прогноз на 10 дней по дню -->
    <style>
        .weather-widget-day {
            display: flex;
            border-radius: 15px;
            margin-top: 20px;
            padding: 15px;
            background-color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .weather-block {
           /* flex: 1;*/
           /* min-width: 150px;*/
            padding: 10px;
            width: 100%;
        }
        
        .block-title {
            font-weight: bold;
            text-align: center;
            font-size: 10px;
            margin-top: 7px;
        }

        .block-title-left {
            color: #2d3436;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        /* Левый блок с flex-строками */
        .left-block {
            min-width: 240px;
        }

        .day-temp-block {
            display: flex;
           /* align-items: center;*/
           margin-left: 20px;
        }

        .hour-temp-unit {
            margin-bottom: 6px;
            font-size: 7px;
        }
        
        .weather-rows {
            display: flex;
            flex-direction: column;
        }
        
        .weather-row {
            font-size: 10px;
            display: flex;
            /*justify-content: space-between;*/
            align-items: center;
        }
        
        .weather-row:last-child {
            border-bottom: none;
        }
        
        .time-period {
            font-size: 12px;
            font-weight: bold;
            /*flex: 1;*/
            text-align: left;
        }
        
        .temperature {
            font-size: 12px;
            flex: 1;
            text-align: center;
        }
        
        .weather-icon-left {
            /*flex: 1;*/
            text-align: center;
            font-size: 20px;
            margin-left: 6px
        }
        
        .description {
            flex: 1;           
        }
        
        /* Правые блоки */
        .right-block-content {
            display: flex;
            flex-direction: column;
            margin-top: 14px;
            gap: 12px;
        }
        
        .data-item {
            font-size: 12px;
            /* padding: 5px 0; */
            text-align: center;
        }
    </style> 
    <!-- Кнопка возврата на верх--> 
    <style>
        #backToTop {
            line-height: 50px; /* чтобы иконка была по центру */
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background-color: #007bff42;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 9999;
            outline: none;
        }

        #backToTop.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        #backToTop:hover {
            background-color: #1c7de782;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
    </style> 
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const btn = document.getElementById("backToTop");

            // Показываем кнопку, как только пользователь прокрутил хотя бы на 1 пиксель
            window.addEventListener("scroll", function() {
                if (window.scrollY > 1) {
                    btn.classList.add("show");
                } else {
                    btn.classList.remove("show");
                }
            });

            // Плавная прокрутка наверх при клике
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });
        });
    </script>

    </div>
</div>