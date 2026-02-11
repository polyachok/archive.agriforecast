<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';

$device = new Device();
$device_id = (int)$_GET['device_id'];
$soil_data = $device->getSoilData($device_id);

$soil_param_names = [
    'humidity_5cm' => 'Влажность 5 см (%)',
    'humidity_15cm' => 'Влажность 15 см (%)',
    'humidity_25cm' => 'Влажность 25 см (%)',
    'humidity_35cm' => 'Влажность 35 см (%)',
    'temp_5cm' => 'Температура 5 см (°C)',
    'temp_15cm' => 'Температура 15 см (°C)',
    'temp_25cm' => 'Температура 25 см (°C)',
    'temp_35cm' => 'Температура 35 см (°C)'
];

$soil_colors = [
    'humidity_5cm' => 'rgba(54, 162, 235, 1)',
    'humidity_15cm' => 'rgba(0, 123, 255, 1)',
    'humidity_25cm' => 'rgba(0, 86, 179, 1)',
    'humidity_35cm' => 'rgba(0, 56, 117, 1)',
    'temp_5cm' => 'rgba(255, 99, 132, 1)',
    'temp_15cm' => 'rgba(220, 53, 69, 1)',
    'temp_25cm' => 'rgba(200, 35, 51, 1)',
    'temp_35cm' => 'rgba(178, 24, 43, 1)'
];

$soil_chart_data = [];

if (!empty($soil_data)) {
    $depth_params = [
        '5cm' => ['offset' => 75, 'color_offset' => 0],
        '15cm' => ['offset' => 50, 'color_offset' => 2],
        '25cm' => ['offset' => 25, 'color_offset' => 4],
        '35cm' => ['offset' => 0, 'color_offset' => 6]
    ];

    $param_extremes = [];
    foreach ($soil_data as $record) {
        foreach ($soil_param_names as $param => $name) {
            if (isset($record[$param])) {
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

    foreach ($soil_param_names as $param => $name) {
        $is_humidity = strpos($param, 'humidity') !== false;
        $param_type = $is_humidity ? 'humidity' : 'temp';
        $depth = str_replace(['humidity_', 'temp_', 'cm'], '', $param) . 'cm';
        $depth_setting = $depth_params[$depth];
        
        foreach ($soil_data as $record) {
            if (isset($record[$param])) {
                $datetime = $record['Date'] . ' ' . $record['Time'];
                $original_value = (float)$record[$param];
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
                    'color_index' => $depth_setting['color_offset'] + ($is_humidity ? 0 : 1)
                ];
            }
        }
    }
    
    foreach ($soil_chart_data as &$data) {
        usort($data, function($a, $b) {
            return strtotime($a['x']) - strtotime($b['x']);
        });
    }
    unset($data);
}
?>

<?php if (empty($soil_data)): ?>
<div class="alert alert-info">
    Данные о температуре и влажности почвы отсутствуют для этого устройства.
</div>
<?php else: ?>
<div class="chart-controls mb-4">
    <div class="parameter-toggles">
        <?php foreach ($soil_param_names as $param => $name): ?>
            <?php if (isset($soil_chart_data[$param])): ?>
            <label class="parameter-toggle active" data-param="<?= $param ?>">
                <input type="checkbox" checked>
                <span class="toggle-indicator" style="background: <?= $soil_colors[$param] ?>"></span>
                <span class="toggle-label"><?= htmlspecialchars($name) ?></span>
            </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="form-group mb-4">
    <label class="form-switch">
        <input type="checkbox" id="soilChartToggle">
        <i class="form-icon"></i> Показать детальный просмотр
    </label>
</div>

<div class="card mb-4 overview-card">
    <div class="card-header">
        <h4>Общий просмотр</h4>
    </div>
    <div class="card-body">
        <canvas id="soilOverviewChart" height="400"></canvas>
    </div>
</div>

<div class="card detailed-card" style="display: none;">
    <div class="card-header">
        <h4>Детальный просмотр</h4>
    </div>
    <div class="card-body" style="overflow-x: auto;">
        <div style="min-width: 4000px;">
            <canvas id="soilDetailedChart" height="400" style="width: 100%; max-height: 400px; min-width: 4000px;"></canvas>
        </div>
    </div>
</div>

<script>
window.initSoilCharts = function() {
    console.log('Initializing soil charts...');
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    if (window.appCharts.soil?.overview) window.appCharts.soil.overview.destroy();
    if (window.appCharts.soil?.detailed) window.appCharts.soil.detailed.destroy();

    const depthColors = [
        ['rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)'],
        ['rgba(0, 123, 255, 1)', 'rgba(220, 53, 69, 1)'],
        ['rgba(0, 86, 179, 1)', 'rgba(200, 35, 51, 1)'],
        ['rgba(0, 56, 117, 1)', 'rgba(178, 24, 43, 1)']
    ];

    const soilDatasets = [
        <?php foreach ($soil_param_names as $param => $name): ?>
            <?php if (isset($soil_chart_data[$param])): ?>
            {
                label: '<?= addslashes($name) ?>',
                data: <?= json_encode($soil_chart_data[$param]) ?>,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                tension: 0.4,
                yAxisID: 'y',
                hidden: false
            },
            <?php endif; ?>
        <?php endforeach; ?>
    ].map(dataset => {
        const paramType = dataset.data[0]?.type;
        const depthIndex = Math.floor(dataset.data[0]?.color_index / 2);
        const isHumidity = paramType === 'humidity';
        const colorIndex = dataset.data[0]?.color_index % 2;
        
        return {
            ...dataset,
            borderColor: depthColors[depthIndex][colorIndex],
            backgroundColor: depthColors[depthIndex][colorIndex],
            borderDash: isHumidity ? [] : [5, 5]
        };
    });

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const original = context.raw.original;
                        const unit = context.raw.type === 'humidity' ? '%' : '°C';
                        return `${label}: ${original.toFixed(2)}${unit}`;
                    }
                }
            },
            legend: { display: false }
        },
        scales: {
            y: {
                title: { display: true, text: 'Глубина и параметры' },
                min: -5, max: 85,
                ticks: {
                    callback: function(value) {
                        const depthLabels = {
                            72: '5 см (влажность)', 69: '5 см (температура)',
                            47: '15 см (влажность)', 44: '15 см (температура)',
                            22: '25 см (влажность)', 19: '25 см (температура)',
                            5: '35 см (влажность)', 0: '35 см (температура)'
                        };
                        return depthLabels[value] || '';
                    },
                    font: { weight: 'bold' }
                },
                grid: {
                    color: function(context) {
                        const dividerValues = [72, 69, 47, 44, 22, 19, 0, -3];
                        return dividerValues.includes(context.tick.value) 
                            ? 'rgba(0, 0, 0, 0.1)' 
                            : 'rgba(0, 0, 0, 0.02)';
                    }
                }
            }
        }
    };

    const overviewCtx = document.getElementById('soilOverviewChart')?.getContext('2d');
    const detailedCtx = document.getElementById('soilDetailedChart')?.getContext('2d');
    
    if (overviewCtx && detailedCtx) {
        window.appCharts.soil = {
            overview: new Chart(overviewCtx, {
                type: 'line',
                data: { datasets: soilDatasets },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'dd.MM.yyyy HH:mm',
                                displayFormats: { hour: 'HH:mm', day: 'dd.MM.yyyy' }
                            },
                            title: { display: true, text: 'Время' }
                        }
                    }
                }
            }),
            detailed: new Chart(detailedCtx, {
                type: 'line',
                data: { 
                    datasets: soilDatasets.map(ds => ({
                        ...ds,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 8
                    }))
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat: 'dd.MM.yyyy HH:mm',
                                unit: 'hour',
                                displayFormats: { hour: 'HH:mm', day: 'dd.MM.yyyy' }
                            },
                            title: { display: true, text: 'Время' },
                            ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 }
                        }
                    },
                    plugins: {
                        zoom: {
                            zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'xy' },
                            pan: { enabled: true, mode: 'xy' }
                        }
                    }
                }
            })
        };
        
        console.log('Soil charts initialized successfully');
    } else {
        console.error('Canvas elements not found');
    }
};

if (!window.isAjaxLoad) {
    document.addEventListener('DOMContentLoaded', window.initSoilCharts);
}
</script>
<?php endif; ?>