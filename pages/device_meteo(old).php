<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/User.php';

$device = new Device();
if (!isset($_GET['device_id'])) {
    header("Location: devices.php?error=no_device");
    exit();
}

$device_id = (int)$_GET['device_id'];
$device_info = $device->getDevice($device_id);

if ($_SESSION['role'] == ROLE_USER && $device_info['organization_id'] != $_SESSION['organization_id']) {
    header("Location: devices.php?error=no_permission");
    exit();
} elseif ($_SESSION['role'] == ROLE_DEALER) {
    if ($device_info['organization_id'] != $_SESSION['organization_id']) {
        // Дилер может видеть только свои устройства
    }
}

$show_meteo_tab = (bool)$device_info['is_realdata_enabled'];
$show_forecast_tab = (bool)$device_info['is_forecast_enabled'];

if (!$show_meteo_tab && !$show_forecast_tab) {
    header("Location: devices.php?error=no_data_available");
    exit();
}

function getClientTimezone() {
    if (isset($_SERVER['HTTP_CLIENT_TIMEZONE'])) {
        return (int)$_SERVER['HTTP_CLIENT_TIMEZONE'];
    }
    return 3;
}

$meteo_data = [];
$forecast_values = [];

function interpolateTimeSeries($series) {
    $keys = array_keys($series);
    $values = array_values($series);
    $n = count($values);

    $known_indices = [];
    for ($i = 0; $i < $n; $i++) {
        if ($values[$i] !== null) {
            $known_indices[] = $i;
        }
    }

    if (empty($known_indices)) {
        return $series;
    }

    for ($i = 0; $i < $n; $i++) {
        if ($values[$i] !== null) continue;
        
        $prev_index = null;
        for ($j = $i-1; $j >=0; $j--) {
            if ($values[$j] !== null) {
                $prev_index = $j;
                break;
            }
        }
        
        $next_index = null;
        for ($j = $i+1; $j < $n; $j++) {
            if ($values[$j] !== null) {
                $next_index = $j;
                break;
            }
        }
        
        if ($prev_index !== null && $next_index !== null) {
            $prev_value = $values[$prev_index];
            $next_value = $values[$next_index];
            $prev_time = strtotime($keys[$prev_index]);
            $next_time = strtotime($keys[$next_index]);
            $current_time = strtotime($keys[$i]);
            $factor = ($current_time - $prev_time) / ($next_time - $prev_time);
            $values[$i] = $prev_value + ($next_value - $prev_value) * $factor;
        } else if ($prev_index !== null) {
            $values[$i] = $values[$prev_index];
        } else if ($next_index !== null) {
            $values[$i] = $values[$next_index];
        }
    }
    
    return array_combine($keys, $values);
}

if ($show_meteo_tab) {
    $timezone_offset = getClientTimezone() * 3600; 
    
    $raw_meteo_data = $device->getMeteoData($device_id);
    if (!empty($raw_meteo_data)) {
        foreach ($raw_meteo_data as &$record) {
            $record['ref_time'] = date('Y-m-d H:i:s', strtotime($record['ref_time']) + $timezone_offset);
            if (isset($record['signal_strength'])) {
                $record['original_signal_strength'] = $record['signal_strength'];
                $record['signal_strength'] = 100 + (float)$record['signal_strength'];
            }
        }
        unset($record);
        
        usort($raw_meteo_data, function($a, $b) {
            return strtotime($a['ref_time']) - strtotime($b['ref_time']);
        });
        
        $hourly_groups = [];
        foreach ($raw_meteo_data as $record) {
            $hour = date('Y-m-d H:00:00', strtotime($record['ref_time']));
            
            if (!isset($hourly_groups[$hour])) {
                $hourly_groups[$hour] = [];
            }
            $hourly_groups[$hour][] = $record;
        }
        
        $hourly_averaged = [];
        foreach ($hourly_groups as $hour => $group) {
            $averaged = ['ref_time' => $hour];
            $count = count($group);
            $params = array_keys($group[0]);

            foreach ($params as $param) {
                if ($param === 'ref_time') continue;

                if ($param === 'signal_strength') {
                    $values = array_filter(array_column($group, $param), function($v) {
                        return $v !== null;
                    });
                    
                    if (empty($values)) {
                        $averaged[$param] = null;
                        continue;
                    }
                    
                    $averaged[$param] = array_sum($values) / count($values);
                    continue;
                }
                
                $values = array_filter(array_column($group, $param), function($v) {
                    return $v !== null;
                });
                
                if (empty($values)) {
                    $averaged[$param] = null;
                    continue;
                }
            
                $averaged[$param] = array_sum($values) / count($values);
            }
            $hourly_averaged[$hour] = $averaged;
        }
        
        $first_hour = min(array_keys($hourly_groups));
        $last_hour = max(array_keys($hourly_groups));
        $all_hours = [];
        $current = strtotime($first_hour);
        $end = strtotime($last_hour);
        while ($current <= $end) {
            $all_hours[] = date('Y-m-d H:00:00', $current);
            $current = strtotime('+1 hour', $current);
        }
        
        $all_params = [];
        foreach ($hourly_averaged as $data) {
            foreach ($data as $param => $value) {
                if ($param !== 'ref_time' && !in_array($param, $all_params)) {
                    $all_params[] = $param;
                }
            }
        }
        
        $full_data = [];
        foreach ($all_hours as $hour) {
            $record = ['ref_time' => $hour];
            foreach ($all_params as $param) {
                $record[$param] = null;
            }
            $full_data[$hour] = $record;
        }
        
        foreach ($hourly_averaged as $hour => $data) {
            foreach ($data as $param => $value) {
                if ($param !== 'ref_time') {
                    $full_data[$hour][$param] = $value;
                }
            }
        }

        foreach ($all_params as $param) {
            $param_series = [];
            foreach ($full_data as $hour => $record) {
                $param_series[$hour] = $record[$param];
            }
            
            $interpolated = interpolateTimeSeries($param_series);
            
            foreach ($interpolated as $hour => $value) {
                $full_data[$hour][$param] = $value;
                if ($value !== $full_data[$hour][$param]) {
                    $full_data[$hour]['_interpolated'] = true;
                }
            }
        }

        $precipitation_diffs = [];
        $prev_precip = null;
        foreach ($full_data as $hour => $record) {
            $current_precip = $record['precipitation'] ?? null;
            if ($prev_precip !== null && $current_precip !== null) {
                $diff = $current_precip - $prev_precip;
                $precipitation_diffs[$hour] = $diff > 0 ? $diff : 0;
            } else {
                $precipitation_diffs[$hour] = 0;
            }
            $prev_precip = $current_precip;
        }

        $processed_meteo_data = [];
        foreach ($all_hours as $hour) {
            $record = $full_data[$hour];
            $record['_interpolated'] = $record['_interpolated'] ?? false;
            $processed_meteo_data[] = $record;
        }
        
        $meteo_data = $processed_meteo_data;
    }
}

if ($show_forecast_tab) {
    $yesterday = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $forecast_values = $device->getForecastValuesFromDate($device_id, $yesterday);
}

$meteo_parameter_names = [
    'air_temperature' => 'Температура воздуха (°C)',
    'humidity' => 'Относительная влажность (%)',
    'precipitation' => 'Осадки (мм)',
    'wind_speed' => 'Скорость ветра (м/с)',
    'wind_direction' => 'Направление ветра',
    'solar_radiation' => 'Солнечная радиация (Вт/м²)',
    'soil_temperature' => 'Температура почвы (°C)',
    'dew_point' => 'Точка росы (°C)',
    'wet_leaf' => 'Влажность листа',
    'battery_voltage' => 'Напряжение батареи (В)',
    'signal_strength' => 'Сила сигнала RSSI (dBm)',
];

function generateMeteoColor($param) {
    $colors = [
        'air_temperature' => 'rgba(255, 99, 132, 1)',
        'humidity' => 'rgba(54, 162, 235, 1)',
        'precipitation' => 'rgba(135, 206, 235, 0.7)',
        'wind_speed' => 'rgba(75, 192, 192, 1)',
        'wind_direction' => 'rgba(255, 159, 64, 1)',
        'solar_radiation' => 'rgba(255, 215, 0, 0.7)',
        'soil_temperature' => 'rgba(220, 20, 60, 0.7)',
        'dew_point' => 'rgba(0, 128, 128, 0.7)',
        'wet_leaf' => 'rgba(34, 139, 34, 0.7)',
        'battery_voltage' => 'rgba(255, 140, 0, 0.7)',
        'signal_strength' => 'rgba(147, 112, 219, 0.7)',
    ];
    return $colors[$param] ?? 'rgba('.rand(0,255).','.rand(0,255).','.rand(0,255).',1)';
}

$meteo_chart_data = [];
$meteo_param_min_max = [];
$meteo_param_coefficients = [];

if (!empty($meteo_data)) {
    $main_meteo_parameters = ['air_temperature', 'humidity', 'precipitation'];
    
    foreach ($meteo_parameter_names as $param => $name) {
        $values = [];
        foreach ($meteo_data as $record) {
            if (isset($record[$param]) && $record[$param] !== null) {
                $value = (float)$record[$param];
                $values[] = $value;
            }
        }
        
        if (!empty($values)) {
            $max_value = max($values);
            $meteo_param_max_values[$param] = $max_value;
            $meteo_param_coefficients[$param] = ($max_value != 0) ? 99.9 / $max_value : 1;
        }
    }
    
    foreach ($meteo_parameter_names as $param => $name) {
        $data = [];
        
        foreach ($meteo_data as $record) {
            if (isset($record[$param]) && $record[$param] !== null) {
                $value = (float)$record[$param];
                $corrected_time = $record['ref_time'];
                $interpolated = $record['_interpolated'] ?? false;
                
                if ($param === 'precipitation') {
                    $value = $precipitation_diffs[$record['ref_time']] ?? 0;
                    $normalized_value = $value * ($meteo_param_coefficients[$param] ?? 1);
                    
                    $data[] = [
                        'x' => $corrected_time,
                        'y' => $normalized_value,
                        'original' => $value,
                        'interpolated' => $interpolated
                    ];
                }
                else {
                    if ($param === 'wind_direction') {
                        if ($value > 360) $value = fmod($value, 360);
                        elseif ($value < 0) $value = 360 - fmod(abs($value), 360);
                    }
                    
                    $normalized_value = $value * ($meteo_param_coefficients[$param] ?? 1);
                    $original_value = $value;
                    if ($param === 'signal_strength' && isset($record['original_signal_strength'])) {
                        $original_value = $record['original_signal_strength'];
                        $data[] = [
                            'x' => $corrected_time,
                            'y' => $normalized_value,
                            'original' => $original_value,
                            'interpolated' => $interpolated
                        ];
                    } else {
                    $data[] = [
                        'x' => $corrected_time,
                        'y' => $normalized_value,
                        'original' => $value,
                        'interpolated' => $interpolated
                    ];
                    }

                }
            }
        }
        
        if (!empty($data)) {
            if ($param === 'precipitation') {
                $meteo_chart_data['precipitation'] = [
                    'label' => $name,
                    'data' => $data,
                    'borderColor' => 'rgba(135, 206, 235, 0.3)',
                    'backgroundColor' => 'rgba(135, 206, 235, 0.3)',
                    'hidden' => !in_array('precipitation', $main_meteo_parameters),
                    'yAxisID' => 'y',
                    'tension' => 0,
                    'type' => 'bar',
                    'borderWidth' => 0,
                    'barPercentage' => 1.0,
                    'categoryPercentage' => 1.0,
                    'order' => 2,
                    'skipNull' => true,
                    'precipitation' => true
                ];
            }
            else {
                $meteo_chart_data[$param] = [
                    'label' => $name,
                    'data' => $data,
                    'borderColor' => generateMeteoColor($param),
                    'backgroundColor' => generateMeteoColor($param),
                    'hidden' => !in_array($param, $main_meteo_parameters),
                    'yAxisID' => 'y',
                    'tension' => 0,
                    'type' => 'line',
                    'pointRadius' => 2,
                    'pointHoverRadius' => 5
                ];
            }
        }
    }
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

include ROOT_PATH . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.0.1"></script>

<div class="columns">
    <div class="column col-3 hide-xs">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    <div class="column col-9 col-xs-12">
        <h3 style="margin-bottom: 0;">Данные устройства <?= htmlspecialchars($device_info['name']) ?></h3>
        <button class="btn btn-link" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i> Назад к приборам
        </button>

        <div class="tab-container">
            <div class="tab-controls">
                <?php if ($show_meteo_tab): ?>
                <button class="tab-btn <?= $show_meteo_tab ? 'active' : '' ?>" data-tab="meteo">Метеоданные</button>
                <?php endif; ?>
                <?php if ($show_forecast_tab): ?>
                <button class="tab-btn <?= !$show_meteo_tab && $show_forecast_tab ? 'active' : '' ?>" data-tab="forecast">Прогноз</button>
                <?php endif; ?>
            </div>

            <?php if ($show_meteo_tab): ?>
            <div class="tab-content <?= $show_meteo_tab ? 'active' : '' ?>" id="meteo-tab">
                <?php if (empty($meteo_data)): ?>
                <div class="alert alert-info">
                    Метеоданные отсутствуют для этого устройства.
                </div>
                <?php else: ?>
                <div class="chart-controls mb-4">
                    <div class="parameter-toggles">
                        <?php foreach ($meteo_parameter_names as $param => $name): ?>
                        <?php if (isset($meteo_chart_data[$param])): ?>
                        <label class="parameter-toggle <?= !$meteo_chart_data[$param]['hidden'] ? 'active' : '' ?>" data-param="<?= $param ?>">
                            <input type="checkbox" <?= !$meteo_chart_data[$param]['hidden'] ? 'checked' : '' ?>>
                            <span class="toggle-indicator" style="background: <?= $meteo_chart_data[$param]['borderColor'] ?>"></span>
                            <span class="toggle-label"><?= htmlspecialchars($name) ?></span>
                        </label>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label class="form-switch">
                        <input type="checkbox" id="meteoChartToggle">
                        <i class="form-icon"></i> Показать общий просмотр
                    </label>
                </div>
                <div class="card mb-4 overview-card" style="display: none;">
                    <div class="card-header">
                        <h4>Общий просмотр (30 дней)</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="meteoOverviewChart" height="496"></canvas>
                    </div>
                </div>

                <div class="card detailed-card">
                    <div class="card-header">
                        <h4>Детальный просмотр (7 дней)</h4>
                    </div>
                    <div class="card-body" style="overflow-x: auto;">
                        <div style="min-width: 4000px;">
                            <canvas id="meteoDetailedChart" height="496" style="width: 100%; max-height: 496px; min-width: 4000px;"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($show_forecast_tab): ?>
            <div class="tab-content <?= !$show_meteo_tab && $show_forecast_tab ? 'active' : '' ?>" id="forecast-tab">
                <?php if (empty($forecast_values)): ?>
                <div class="alert alert-info">
                    Данные прогноза отсутствуют для этого устройства.
                </div>
                <?php else: ?>
                <div class="chart-controls mb-4">
                    <div class="parameter-toggles">
                        <?php foreach ($parameter_names as $param => $name): ?>
                        <?php if (isset($chart_data[$param])): ?>
                        <label class="parameter-toggle <?= !$chart_data[$param]['hidden'] ? 'active' : '' ?>" data-param="<?= $param ?>">
                            <input type="checkbox" <?= !$chart_data[$param]['hidden'] ? 'checked' : '' ?>>
                            <span class="toggle-indicator" style="background: <?= $chart_data[$param]['borderColor'] ?>"></span>
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
                            <canvas id="forecastDetailedChart" height="496" style="width: 100%; max-height: 496px; min-width: 4000px;"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

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
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });

                document.getElementById(tabId + '-tab').classList.add('active');
                this.classList.add('active');
            });
        });

        <?php if ($show_meteo_tab && !empty($meteo_data)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const overviewStart = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
            const detailedStart = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

            const meteoOverviewCtx = document.getElementById('meteoOverviewChart').getContext('2d');
            const meteoDetailedCtx = document.getElementById('meteoDetailedChart').getContext('2d');

            const meteoOverviewChart = new Chart(meteoOverviewCtx, {
                type: 'line',
                data: {
                    datasets: Object.values(<?= json_encode($meteo_chart_data) ?>)
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    animation: {
                        duration: 0
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Осадки (мм)') {
                                        return `Осадки: ${context.raw.original.toFixed(2)} мм`;
                                    }
                                    
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    
                                    const originalValue = context.raw.original !== undefined ? 
                                        context.raw.original : context.parsed.y;
                                    
                                    if (originalValue !== null) {
                                        if (context.dataset.label.includes('Направление ветра')) {
                                            const direction = degreesToCompass(originalValue);
                                            label += direction;
                                        } else if (context.dataset.label.includes('Сила сигнала')) {
                                            let symbol = '';
                                            if (originalValue >= -79 && originalValue <= 0) {
                                                symbol = ' 🟢';
                                            } else if (originalValue >= -89 && originalValue < -79) {
                                                symbol = ' 🟡';
                                            } else if (originalValue >= -99 && originalValue < -89) {
                                                symbol = ' 🟠';
                                            } else if (originalValue < -99) {
                                                symbol = ' 🔴';
                                            }
                                            label += `${originalValue.toFixed(2)} dBm${symbol}`;
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
                            max: 100
                        },
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'HH:mm dd.MM.yyyy',
                                displayFormats: {
                                    hour: 'HH:mm',
                                    day: 'dd.MM.yyyy'
                                }
                            },
                            min: overviewStart,
                            max: now,
                            stacked: false,
                            offset: false,
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });

            const meteoDetailedChart = new Chart(meteoDetailedCtx, {
                type: 'line',
                data: {
                    datasets: Object.values(<?= json_encode($meteo_chart_data) ?>)
                },
                options: {
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
                                    if (context.dataset.label === 'Осадки (мм)') {
                                        return `Осадки: ${context.raw.original.toFixed(2)} мм`;
                                    }
                                    
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    
                                    const originalValue = context.raw.original !== undefined ? 
                                        context.raw.original : context.parsed.y;
                                    
                                    if (originalValue !== null) {
                                        if (context.dataset.label.includes('Направление ветра')) {
                                            const direction = degreesToCompass(originalValue);
                                            label += direction;
                                        } else if (context.dataset.label.includes('Сила сигнала')) {
                                            let symbol = '';
                                            if (originalValue >= -79 && originalValue <= 0) {
                                                symbol = ' 🟢';
                                            } else if (originalValue >= -89 && originalValue < -79) {
                                                symbol = ' 🟡';
                                            } else if (originalValue >= -99 && originalValue < -89) {
                                                symbol = ' 🟠';
                                            } else if (originalValue < -99) {
                                                symbol = ' 🔴';
                                            }
                                            label += `${originalValue.toFixed(2)} dBm${symbol}`;
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
                                enabled: true,
                                mode: 'x'
                            },
                            zoom: {
                                wheel: {
                                    enabled: false
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x'
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
                            max: 100
                        },
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'HH:mm dd.MM.yyyy',
                                displayFormats: {
                                    hour: 'HH:mm',
                                    day: 'dd.MM.yyyy'
                                },
                                unit: 'hour'
                            },
                            min: detailedStart,
                            max: now,
                            title: {
                                display: true,
                                text: 'Время'
                            },
                            ticks: {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });

            document.getElementById('meteoChartToggle').addEventListener('change', function(e) {
                const showOverview = e.target.checked;
                document.querySelector('#meteo-tab .overview-card').style.display = showOverview ? 'block' : 'none';
                document.querySelector('#meteo-tab .detailed-card').style.display = showOverview ? 'none' : 'block';

                if (showOverview) {
                    meteoOverviewChart.update();
                } else {
                    const container = document.querySelector('#meteo-tab .detailed-card .card-body');
                    container.scrollLeft = container.scrollWidth;
                    meteoDetailedChart.update();
                }
            });

            document.querySelectorAll('#meteo-tab .parameter-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    if (e.target.tagName === 'INPUT') return;

                    const checkbox = this.querySelector('input');
                    const wasActive = this.classList.contains('active');
                    const datasetLabel = this.querySelector('.toggle-label').textContent.trim();

                    checkbox.checked = !wasActive;
                    this.classList.toggle('active', !wasActive);

                    [meteoOverviewChart, meteoDetailedChart].forEach(chart => {
                        const dataset = chart.data.datasets.find(ds => ds.label === datasetLabel);
                        if (dataset) {
                            dataset.hidden = wasActive;
                            
                            if (dataset.label.includes('Осадки')) {
                                const meta = chart.getDatasetMeta(chart.data.datasets.indexOf(dataset));
                                meta.data.forEach(bar => {
                                    bar._precipShiftApplied = false;
                                });
                            }
                        }
                        chart.update('none');
                    });
                });
            });

            const detailedContainer = document.querySelector('#meteo-tab .detailed-card .card-body');
            let isMouseOverChart = false;
            detailedContainer.addEventListener('mouseenter', () => isMouseOverChart = true);
            detailedContainer.addEventListener('mouseleave', () => isMouseOverChart = false);
            detailedContainer.addEventListener('wheel', function(e) {
                if (!isMouseOverChart) return;
                e.preventDefault();
                this.scrollLeft += e.deltaY > 0 ? 100 : -100;
            });

            detailedContainer.scrollLeft = detailedContainer.scrollWidth;
        });
        <?php endif; ?>

        function degreesToCompass(degrees) {
            const directions = ['С', 'ССВ', 'СВ', 'ВСВ', 'В', 'ВЮВ', 'ЮВ', 'ЮЮВ', 'Ю', 'ЮЮЗ', 'ЮЗ', 'ЗЮЗ', 'З', 'ЗСЗ', 'СЗ', 'ССЗ'];
            const index = Math.round((degrees % 360) / 22.5);
            return directions[(index % 16)];
        }

        <?php if ($show_forecast_tab && !empty($forecast_values)): ?>

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
                        time: {
                            tooltipFormat: 'dd.MM.yyyy HH:mm'
                        },
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

                    const checkbox = this.querySelector('input');
                    const wasActive = this.classList.contains('active');
                    const datasetLabel = this.querySelector('.toggle-label').textContent.trim();

                    checkbox.checked = !wasActive;
                    this.classList.toggle('active', !wasActive);

                    [overviewChart, detailedChart].forEach(chart => {
                        const dataset = chart.data.datasets.find(ds => ds.label.startsWith(datasetLabel));
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
            document.querySelector('#forecast-tab .overview-card').style.display = isDetailed ? 'none' : 'block';
            document.querySelector('#forecast-tab .detailed-card').style.display = isDetailed ? 'block' : 'none';
        });

        <?php endif; ?>
        </script>

        <style>
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

        .chart-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;

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

        #detailedChart,
        #meteoDetailedChart,
        #forecastDetailedChart {
            min-width: 4000px;
            height: 500px;
        }

        .detailed-card .card-body {
            overscroll-behavior-x: contain;
            scroll-behavior: smooth;
        }

        .detailed-card .card-body::-webkit-scrollbar {
            height: 8px;
        }

        .detailed-card .card-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .detailed-card .card-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .detailed-card .card-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        #meteo-tab .overview-card {
            display: none;
        }

        #meteo-tab .detailed-card {
            display: block;
        }

        #forecast-tab .overview-card {
            display: block;
        }

        #forecast-tab .detailed-card {
            display: none;
        }

        .signal-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        </style>
    </div>
</div>