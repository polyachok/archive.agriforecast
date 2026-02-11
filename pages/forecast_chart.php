<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';

$device = new Device();
$device_id = (int)$_GET['device_id'];

$yesterday = date('Y-m-d 00:00:00', strtotime('-45 day'));
$forecast_values = $device->getForecastValuesFromDate($device_id, $yesterday);

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
    foreach ($forecast_values as $fv) {
        $param = $fv['parameter'];
        $value = $fv['value'];
        
        if ($param === 'dswrf') {
            $value = $value / 3600;
        }
        
        if (!isset($param_max_values[$param])) {
            $param_max_values[$param] = $value;
        } else {
            $param_max_values[$param] = max($param_max_values[$param], $value);
        }
    }

    foreach ($param_max_values as $param => $max_value) {
        $param_coefficients[$param] = ($max_value != 0) ? 99.9 / $max_value : 1;
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
?>

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
        <input type="checkbox" id="chartToggle">
        <i class="form-icon"></i> Показать детальный прогноз
    </label>
</div>

<div class="card mb-4 overview-card">
    <div class="card-header">
        <h4>Общий просмотр</h4>
    </div>
    <div class="card-body">
        <canvas id="overviewChart" height="400"></canvas>
    </div>
</div>

<div class="card detailed-card" style="display: none;">
    <div class="card-header">
        <h4>Детальный просмотр</h4>
    </div>
    <div class="card-body" style="overflow-x: auto;">
        <div style="min-width: 4000px;">
            <canvas id="detailedChart" height="400" style="width: 100%; max-height: 400px; min-width: 4000px;"></canvas>
        </div>
    </div>
</div>
<script>
window.initForecastCharts = function() {
    console.log('Initializing forecast charts...');
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    if (window.appCharts.forecast?.overview) window.appCharts.forecast.overview.destroy();
    if (window.appCharts.forecast?.detailed) window.appCharts.forecast.detailed.destroy();

    const now = new Date();
    const nowISO = now.toISOString();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString();

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        if (context.dataset.label === 'Дождь') return null;
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        const originalValue = context.raw.original ?? context.parsed.y;
                        
                        if (context.dataset.label.startsWith('Дождь')) {
                            label += originalValue == 1 ? 'Да' : 'Нет';
                        } else if (context.dataset.label.startsWith('Направление ветра')) {
                            label += window.degreesToCompass(originalValue);
                        } else if (context.dataset.label.startsWith('Видимость')) {
                            label += (originalValue / 1000).toFixed(2) + ' м';
                        } else {
                            label += originalValue.toFixed(2);
                        }
                        return label;
                    }
                }
            },
            legend: { display: false },
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
                        label: { content: 'Сейчас', enabled: true, position: 'top' }
                    }
                }
            }
        },
        scales: {
            x: {
                type: 'time',
                time: { tooltipFormat: 'dd.MM.yyyy HH:mm' },
                title: { display: true, text: 'Время' },
                min: '<?= $min_date ?>',
                max: '<?= $max_date ?>'
            },
            y: {
                type: 'linear',
                title: { display: true, text: 'Значения' },
                min: 0, max: 100
            }
        }
    };

    const overviewCtx = document.getElementById('overviewChart')?.getContext('2d');
    const detailedCtx = document.getElementById('detailedChart')?.getContext('2d');
    
    if (overviewCtx && detailedCtx) {
        window.appCharts.forecast = {
            overview: new Chart(overviewCtx, {
                type: 'line',
                data: {
                    datasets: [
                        <?php foreach ($chart_data as $param => $dataset): ?>
                        {
                            label: '<?= addslashes($dataset['label']) ?>',
                            data: <?= json_encode($dataset['data']) ?>,
                            borderColor: '<?= $dataset['borderColor'] ?>',
                            backgroundColor: '<?= $dataset['backgroundColor'] ?>',
                            hidden: <?= $dataset['hidden'] ? 'true' : 'false' ?>,
                            yAxisID: 'y',
                            tension: 0.4,
                            borderWidth: <?= $dataset['type'] === 'bar' ? 0 : 1 ?>,
                            pointRadius: <?= $param === 'crain' ? 0 : 2 ?>,
                            type: '<?= $dataset['type'] ?>'
                        },
                        <?php endforeach; ?>
                    ]
                },
                options: commonOptions
            }),
            detailed: new Chart(detailedCtx, {
                type: 'line',
                data: {
                    datasets: [
                        <?php foreach ($chart_data as $param => $dataset): ?>
                        {
                            label: '<?= addslashes($dataset['label']) ?>',
                            data: <?= json_encode($dataset['data']) ?>,
                            borderColor: '<?= $dataset['borderColor'] ?>',
                            backgroundColor: '<?= $dataset['backgroundColor'] ?>',
                            hidden: <?= $dataset['hidden'] ? 'true' : 'false' ?>,
                            yAxisID: 'y',
                            tension: 0.4,
                            borderWidth: <?= $dataset['type'] === 'bar' ? 0 : 2 ?>,
                            pointRadius: <?= $param === 'crain' ? 0 : 4 ?>,
                            type: '<?= $dataset['type'] ?>'
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
                            time: { ...commonOptions.scales.x.time, unit: 'hour' },
                            ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 }
                        }
                    }
                }
            })
        };
        
        console.log('Forecast charts initialized successfully');
    } else {
        console.error('Canvas elements not found');
    }
};

if (!window.isAjaxLoad) {
    document.addEventListener('DOMContentLoaded', window.initForecastCharts);
}
</script>
<?php endif; ?>