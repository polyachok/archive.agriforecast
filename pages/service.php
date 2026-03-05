<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/includes/header.php';
require_once ROOT_PATH . '/classes/ServiceData.php';

if ($_SESSION['role'] !== ROLE_ADMIN) {
    redirect('/pages/dashboard.php?error=no_permission');
}

$serviceData = new ServiceData();

$year = $_GET['year'] ?? '';
$deviceType = $_GET['device_type'] ?? '';
$deviceId = $_GET['device_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

try {
    $availableYears = $serviceData->getAvailableYears();
} catch (Exception $e) {
    $error = "Ошибка при получении списка лет: " . $e->getMessage();
    $availableYears = [];
}

$soilSenseDevices = [];
$meteoSenseDevices = [];
if ($year && $deviceType) {
    try {
        if ($deviceType === 'soil') {
            $soilSenseDevices = $serviceData->getSoilSenseDevicesForYear($year);
        } elseif ($deviceType === 'meteo') {
            $meteoSenseDevices = $serviceData->getMeteoSenseDevicesForYear($year);
        }
    } catch (Exception $e) {
        if (empty($error)) { // Show only the first error
            $error = "Ошибка при получении списка устройств: " . $e->getMessage();
        }
    }
}


// Убедимся, что startDateTime и endDateTime всегда содержат полное время
$startDateTime = $startDate ? $startDate . ' 00:00:00' : '';
$endDateTime = $endDate ? $endDate . ' 23:59:59' : '';

$chartData = [];
$chartLabels = [];
$chartValues = [];
$chartColors = [];
$deviceInfo = null;
$humidityStats = [];
$meteoStats = [];

$showHumidityStats = false;
$showMeteoStats = false;
$showChart = false;

if ($deviceType && $deviceId && $startDate && $endDate) {
    try {
        $deviceInfo = $serviceData->getDeviceInfo($deviceId);
        
        if (!$deviceInfo) {
            throw new Exception("Устройство с ID $deviceId не найдено");
        }
        
        $expectedType = $deviceType === 'soil' ? 'VP' : 'M';
        if ($deviceInfo['device_type'] !== $expectedType) {
            throw new Exception("Тип устройства не соответствует выбранному фильтру");
        }

        if (!$serviceData->checkDeviceDataExists($deviceId, $deviceType)) {
            $warning = "Для устройства {$deviceInfo['name']} (ID: $deviceId) нет данных в базе forecast";
        }
        
        if ($deviceType === 'soil') {
            $data = $serviceData->getSoilSenseData($deviceId, $startDateTime, $endDateTime);
            $intervals = $serviceData->getTimeIntervals($startDateTime, $endDateTime, 'soil');
            $chartData = $serviceData->mergeIntervalsWithData($intervals, $data);
            
            try {
                $humidityStats = $serviceData->generateHumidityStatistics($deviceId, $startDateTime, $endDateTime);
                if (!empty($humidityStats)) {
                    $showHumidityStats = true;
                }
            } catch (Exception $e) {
                $statsError = "Ошибка при генерации статистики влажности: " . $e->getMessage();
            }
            
        } else if ($deviceType === 'meteo') {
            $data = $serviceData->getMeteoSenseData($deviceId, $startDateTime, $endDateTime);
            $intervals = $serviceData->getTimeIntervals($startDateTime, $endDateTime, 'meteo'); 
            $chartData = $serviceData->mergeIntervalsWithData($intervals, $data);
            
            try {
                $meteoStats = $serviceData->generateMeteoStatistics($deviceId, $startDateTime, $endDateTime);
                if (!empty($meteoStats)) {
                    $showMeteoStats = true;
                }
            } catch (Exception $e) {
                $statsError = "Ошибка при генерации статистики метеостанции: " . $e->getMessage();
            }
        }
        
        foreach ($chartData as $item) {
            $timeSlot = $item['hour_slot'] ?? $item['time_slot'];
            $formattedTime = date('d.m H:i', strtotime($timeSlot));
            
            $chartLabels[] = date('c', strtotime($timeSlot));
            
            if ($item['has_data']) {
                $chartValues[] = 1; 
                $chartColors[] = $item['is_interpolated'] ? 'rgba(255, 99, 132, 0.8)' : 'rgba(75, 192, 192, 0.8)';
            } else {
                $chartValues[] = 0; 
                $chartColors[] = 'rgba(200, 200, 200, 0.2)';
            }
        }
        
        if (!empty($chartData)) {
            $showChart = true;
        }
        
    } catch (Exception $e) {
        $error = "Ошибка при получении данных: " . $e->getMessage();
        $chartData = [];
    }
}

function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function getRussianMonth($monthNum) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    return $months[(int)$monthNum];
}
?>

<style>
.chart-container {
    position: relative;
    height: 400px;
    width: 100%;
    margin-top: 20px;
    background-color: #fff;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.chart-wrapper {
    min-width: 100%;
    width: auto;
    height: 100%;
}

.chart-legend {
    display: flex;
    justify-content: center;
    margin-top: 15px;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 14px;
}

.legend-color {
    width: 16px;
    height: 16px;
    margin-right: 8px;
    border-radius: 3px;
}

.green-color {
    background-color: rgba(75, 192, 192, 0.8);
}

.red-color {
    background-color: rgba(255, 99, 132, 0.8);
}

.gray-color {
    background-color: rgba(200, 200, 200, 0.2);
}

.device-selector {
    margin-bottom: 20px;
}

.date-range {
    margin-bottom: 20px;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    min-width: 200px;
}

@media (max-width: 768px) {
    .form-group {
        flex-basis: 100%;
    }
}

.chart-info {
    text-align: center;
    margin: 20px 0;
    color: #666;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.chart-subtitle {
    font-size: 14px;
    color: #888;
}

.no-data-message {
    text-align: center;
    padding: 40px;
    color: #888;
    font-style: italic;
}

.device-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: none;
}

.device-info h6 {
    margin: 0 0 10px 0;
    color: #333;
}

.device-info .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.device-info .info-label {
    font-weight: 600;
    color: #666;
}

.device-info .info-value {
    color: #333;
}

.humidity-stats, .meteo-stats {
    background: #fff;
    border-radius: 8px;
    padding: 0;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    display: none; 
}

.stats-header {
    background: #fff;
    padding: 20px;
    border-bottom: none;
}

.stats-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.stats-logo {
    flex-shrink: 0;
}

.stats-logo img {
    height: 130px; 
    width: auto;
}

.stats-company-info {
    text-align: right;
    font-size: 12px;
    line-height: 1.4;
    color: #009EFF;
    font-family: Tahoma, sans-serif;
}

.stats-company-info div {
    margin-bottom: 2px;
}

.stats-title {
    text-align: center;
    margin: 20px 0;
}

.stats-title h3 {
    font-size: 16px;
    font-weight: bold;
    margin: 0;
    color: #333;
    text-transform: uppercase;
}

.stats-title .device-type {
    font-size: 14px;
    margin-top: 5px;
    color: #666;
}

.stats-footer-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    font-size: 12px;
    color: #333;
}

.stats-table-container {
    padding: 20px;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
}

.stats-table th,
.stats-table td {
    padding: 12px;
    text-align: left;
    border-bottom: none;
}

.stats-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.stats-table tr:hover {
    background-color: #f8f9fa;
}

.stats-table td:first-child {
    font-weight: 500;
    color: #495057;
}

.stats-table td:last-child {
    font-weight: 600;
    color: #333;
}

.stats-signature {
    padding: 20px;
    border-top: none;
    background: #fff;
}

.stats-signature-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 30px;
}

.stats-signature-left {
    font-size: 12px;
    color: #333;
    line-height: 1.4;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stats-signature-left img {
    height: 30px;
    width: auto;
}

.stats-signature-right {
    text-align: right;
    font-size: 12px;
    color: #333;
}

.signature-line {
    display: inline-block;
    width: 200px;
    border-bottom: 1px solid #333;
    margin-right: 10px;
}

.show-document-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 17px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 16px;
    transition: background-color 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.show-document-btn:hover {
    background: #218838;
}

.show-document-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.show-document-btn i {
    font-size: 16px;
}

.bottom-inline-logo {
    float: right;
    margin-top: 265px;
}

.pdf-document {
    max-width: 210mm;
    margin: 0 auto;
    background: white;
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
    padding: 20px;
}

.pdf-document .stats-header {
    border-bottom: none;
    margin-bottom: 20px;
}

.pdf-document .stats-table {
    border: 1px solid #333;
}

.pdf-document .stats-table th,
.pdf-document .stats-table td {
    border: 1px solid #333;
    padding: 8px;
}

.pdf-document .stats-table th {
    background-color: #f0f0f0;
}

.pdf-hidden {
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
    visibility: hidden !important;
    opacity: 0 !important;
    width: 210mm !important;
    background: white !important;
}

@media print {
    .stats-header {
        page-break-inside: avoid;
    }
    
    .stats-table {
        page-break-inside: avoid;
    }
    
    .stats-signature {
        page-break-inside: avoid;
    }
}

@media (max-width: 768px) {
    .stats-header-top {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .stats-company-info {
        text-align: center;
        margin-top: 15px;
    }
    
    .stats-footer-info {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .stats-signature-row {
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    
    .stats-signature-right {
        text-align: center;
    }
    
    .signature-line {
        width: 150px;
    }
}
</style>

<div class="columns">
    <div class="column col-3 col-md-12">
        <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    </div>
    
    <div class="column col-9 col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="fas fa-chart-line"></i> Активность приборов
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="toast toast-error">
                        <button class="btn btn-clear float-right"></button>
                        <?= safeHtml($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($warning)): ?>
                    <div class="toast toast-warning">
                        <button class="btn btn-clear float-right"></button>
                        <?= safeHtml($warning) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($statsError)): ?>
                    <div class="toast toast-error">
                        <button class="btn btn-clear float-right"></button>
                        <?= safeHtml($statsError) ?>
                    </div>
                <?php endif; ?>
                
                <form method="GET" action="/pages/service.php" id="service-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="year">Год</label>
                            <select class="form-select" id="year" name="year" onchange="this.form.submit()">
                                <option value="">Выберите год</option>
                                <?php foreach ($availableYears as $y): ?>
                                    <option value="<?= $y ?>" <?= (string)$y === $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="device_type">Тип устройства</label>
                            <select class="form-select" id="device_type" name="device_type" required onchange="this.form.submit()">
                                <option value="">Выберите тип</option>
                                <option value="soil" <?= $deviceType === 'soil' ? 'selected' : '' ?>>Влажность (SoilSense)</option>
                                <option value="meteo" <?= $deviceType === 'meteo' ? 'selected' : '' ?>>Метеостанция (MeteoSense)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="device_id">Устройство</label>
                            <select class="form-select" id="device_id" name="device_id" required>
                                <option value="">-</option>
                                <?php 
                                    $devicesToList = [];
                                    if ($deviceType === 'soil') {
                                        $devicesToList = $soilSenseDevices;
                                    } elseif ($deviceType === 'meteo') {
                                        $devicesToList = $meteoSenseDevices;
                                    }
                                    foreach ($devicesToList as $device): 
                                ?>
                                    <option value="<?= safeHtml($device['device_id']) ?>" <?= $deviceId === $device['device_id'] ? 'selected' : '' ?>>
                                        <?= safeHtml($device['name']) ?> (ID: <?= safeHtml($device['device_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="start_date">Дата начала</label>
                            <input type="date" class="form-input" id="start_date" name="start_date" 
                                   value="<?= safeHtml($startDate) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="end_date">Дата окончания</label>
                            <input type="date" class="form-input" id="end_date" name="end_date" 
                                   value="<?= safeHtml($endDate) ?>" required>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Показать данные
                            </button>
                        
                            <button type="button" class="show-document-btn" id="show-document-btn-humidity" onclick="generatePDF('humidity-stats')" style="display: none;">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button type="button" class="show-document-btn" id="show-document-btn-meteo" onclick="generatePDF('meteo-stats')" style="display: none;">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </div>
                    </div>
                </form>
                
                <div id="dynamic-content-area">
                    <?php if ($deviceInfo): ?>
                        <div class="device-info" id="device-info-block">
                            <h6><i class="fas fa-info-circle"></i> Информация об устройстве</h6>
                            <div class="info-row">
                                <span class="info-label">Название:</span>
                                <span class="info-value"><?= safeHtml($deviceInfo['name']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ID устройства:</span>
                                <span class="info-value"><?= safeHtml($deviceInfo['device_id']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Тип:</span>
                                <span class="info-value"><?= $deviceInfo['device_type'] === 'VP' ? 'Влажность почвы' : 'Метеостанция' ?></span>
                            </div>
                            <?php if ($deviceInfo['coordinates']): ?>
                            <div class="info-row">
                                <span class="info-label">Координаты:</span>
                                <span class="info-value"><?= safeHtml($deviceInfo['coordinates']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($deviceType === 'soil'): ?>
                        <div class="humidity-stats" id="humidity-stats" style="display: none;">
                            <div class="stats-header">
                                <div class="stats-header-top">
                                    <div class="stats-logo">
                                        <img src="https://agriforecast.ru/assets/img/logo.png" alt="AgriForecast Logo" crossorigin="anonymous">
                                    </div>
                                    <div class="stats-company-info">
                                        <div><strong>ООО «Цифровые технологии»</strong></div>
                                        <div>111024, Москва, ул. Кабельная 4-я, д. 2 стр. 1а</div>
                                        <div>Тел: +7 (993) 365-30-09</div>
                                        <div>Site: www.agriforecast.ru</div>
                                        <div>E-mail: agriforecast@yandex.ru</div>
                                    </div>
                                </div>
                                
                                <div class="stats-title">
                                    <h3>Справка</h3>
                                    <div class="device-type">
                                        по активности станции влажности почвы<br>
                                        AGF Soilprobe ID <?= safeHtml($deviceId) ?>
                                    </div>
                                </div>
                                
                                <div class="stats-footer-info">
                                    <div>Г. Москва</div>
                                    <div>«<?= date('d') ?>» <?= getRussianMonth(date('n')) ?> <?= date('Y') ?>г.</div>
                                </div>
                            </div>
                            
                            <div class="stats-table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>Наименование</th>
                                            <th>Значение</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($humidityStats as $stat): ?>
                                            <tr>
                                                <td><?= safeHtml($stat['name']) ?></td>
                                                <td><?= safeHtml($stat['value']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="stats-signature">
                                <div class="stats-signature-row">
                                    <div class="stats-signature-left">
                                        <span>Генеральный директор<br>ООО «Цифровые технологии»</span>
                                    </div>
                                    <div class="stats-signature-right">
                                        <span class="signature-line"></span> Кривошеин Е.Е.
                                    </div>
                                </div>
                                <div class="bottom-inline-logo">
                                    <img src="../assets/img/line_logo.jpg" style="max-width: 180px;" alt="Horizontal Company Logo" crossorigin="anonymous">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($deviceType === 'meteo'): ?>
                        <div class="meteo-stats" id="meteo-stats" style="display: none;">
                            <div class="stats-header">
                                <div class="stats-header-top">
                                    <div class="stats-logo">
                                        <img src="https://agriforecast.ru/assets/img/logo.png" alt="AgriForecast Logo" crossorigin="anonymous">
                                    </div>
                                    <div class="stats-company-info">
                                        <div><strong>ООО «Цифровые технологии»</strong></div>
                                        <div>111024, Москва, ул. Кабельная 4-я, д. 2 стр. 1а</div>
                                        <div>Тел: +7 (993) 365-30-09</div>
                                        <div>Site: www.agriforecast.ru</div>
                                        <div>E-mail: agriforecast@yandex.ru</div>
                                    </div>
                                </div>
                                
                                <div class="stats-title">
                                    <h3>Справка</h3>
                                    <div class="device-type">
                                        по активности метеорологической станции<br>
                                        AGF Meteopoint ID <?= safeHtml($deviceId) ?>
                                    </div>
                                </div>
                                
                                <div class="stats-footer-info">
                                    <div>Г. Москва</div>
                                    <div>«<?= date('d') ?>» <?= getRussianMonth(date('n')) ?> <?= date('Y') ?>г.</div>
                                </div>
                            </div>
                            
                            <div class="stats-table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>Наименование</th>
                                            <th>Значение</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($meteoStats as $stat): ?>
                                            <tr>
                                                <td><?= safeHtml($stat['name']) ?></td>
                                                <td><?= safeHtml($stat['value']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="stats-signature">
                                <div class="stats-signature-row">
                                    <div class="stats-signature-left">                                    
                                        <span>Генеральный директор<br>ООО «Цифровые технологии»</span>
                                    </div>
                                    <div class="stats-signature-right">
                                        <span class="signature-line"></span> Кривошеин Е.Е.
                                    </div>
                                </div>
                                <div class="bottom-inline-logo">
                                    <img src="../assets/img/line_logo.jpg" style="max-width: 180px;" alt="Horizontal Company Logo" crossorigin="anonymous">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($deviceType && $deviceId && $startDate && $endDate): ?>
                        <div class="chart-info" id="chart-info-block" style="display: none;">
                            <div class="chart-title" style="display: none;">
                                График наличия данных: <?= $deviceInfo ? safeHtml($deviceInfo['name']) : safeHtml($deviceId) ?>
                            </div>
                            <div class="chart-subtitle">
                                Период: <?= date('d.m.Y', strtotime($startDate)) ?> - <?= date('d.m.Y', strtotime($endDate)) ?>
                                | Интервал: <?= $deviceType === 'soil' ? '1 час' : '1 час (первая запись)' ?>
                            </div>
                        </div>
                        
                        <div class="chart-legend" id="chart-legend-block" style="display: none;">
                            <div class="legend-item">
                                <div class="legend-color green-color"></div>
                                <span>Данные есть</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color red-color"></div>
                                <span>Интерполированные данные</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color gray-color"></div>
                                <span>Нет данных</span>
                            </div>
                        </div>
                        
                        <div class="chart-container" id="chart-container-block" style="display: none;">
                            <?php if (empty($chartData)): ?>
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle"></i> Нет данных для отображения за выбранный период
                                </div>
                            <?php else: ?>
                                <div class="chart-wrapper">
                                    <canvas id="dataChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                         <div class="no-data-message">
                            <i class="fas fa-info-circle"></i> Выберите все параметры для отображения данных.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/1.2.1/chartjs-plugin-zoom.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function generatePDF(elementId) {
    const button = event.target;
    const originalText = button.innerHTML;

    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    const originalElement = document.getElementById(elementId);

    const clonedElement = originalElement.cloneNode(true);
    clonedElement.classList.add('pdf-document');
    clonedElement.style.display = 'block'; 
    clonedElement.classList.remove('humidity-stats', 'meteo-stats');
    
    const tempContainer = document.createElement('div');
    tempContainer.className = 'pdf-hidden';
    tempContainer.appendChild(clonedElement);
    document.body.appendChild(tempContainer);
    
    const images = tempContainer.querySelectorAll('img');
    const imagePromises = Array.from(images).map(img => {
        return new Promise((resolve) => {
            if (img.complete) {
                resolve();
            } else {
                img.onload = resolve;
                img.onerror = resolve;
            }
        });
    });
    
    Promise.all(imagePromises).then(() => {
        const now = new Date();
        const day = now.getDate().toString().padStart(2, '0');
        const month = (now.getMonth() + 1).toString().padStart(2, '0');
        const year = now.getFullYear().toString().slice(-2);
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');

        const formattedDateTime = `${day}${month}${year}_${hours}${minutes}${seconds}`;
        
        const currentDeviceId = "<?= safeHtml($deviceId) ?>";
        let filename = `spravka_${elementId.replace('-', '_')}`;
        if (currentDeviceId) {
            filename += `_id${currentDeviceId}`;
        }
        filename += `_${formattedDateTime}.pdf`;

        const opt = {
            margin: [5, 10, 10, 10],
            filename: filename,
            image: { 
                type: 'jpeg', 
                quality: 0.98 
            },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                allowTaint: true,
                letterRendering: true,
                width: 734,
                height: 1087,
                scrollX: 0,
                scrollY: 0
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            }
        };
        
        html2pdf().set(opt).from(clonedElement).save().then(() => {
            document.body.removeChild(tempContainer);
            button.innerHTML = originalText;
            button.disabled = false;
        }).catch(function(error) {
            console.error('Ошибка генерации PDF:', error);
            alert('Произошла ошибка при генерации PDF документа');
            document.body.removeChild(tempContainer);
            button.innerHTML = originalText;
            button.disabled = false;
        });
    });
}

function resetDisplay() {
    const elementsToHide = [
        document.getElementById('humidity-stats'),
        document.getElementById('meteo-stats'),
        document.getElementById('show-document-btn-humidity'),
        document.getElementById('show-document-btn-meteo'),
        document.getElementById('chart-info-block'),
        document.getElementById('chart-legend-block'),
        document.getElementById('chart-container-block')
    ];

    elementsToHide.forEach(el => {
        if (el) {
            el.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.getElementById('year');
    const typeSelect = document.getElementById('device_type');
    const deviceSelect = document.getElementById('device_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const submitButton = document.querySelector('button[type="submit"]');

    function setDateLimitsForYear() {
        const selectedYear = yearSelect.value;
        if (selectedYear) {
            startDateInput.min = `${selectedYear}-01-01`;
            startDateInput.max = `${selectedYear}-12-31`;
            endDateInput.min = `${selectedYear}-01-01`;
            endDateInput.max = `${selectedYear}-12-31`;
        } else {
            startDateInput.removeAttribute('min');
            startDateInput.removeAttribute('max');
            endDateInput.removeAttribute('min');
            endDateInput.removeAttribute('max');
        }
    }

    function toggleFilterStates() {
        const yearSelected = yearSelect.value !== '';
        const typeSelected = typeSelect.value !== '';
        
        typeSelect.disabled = !yearSelected;
        deviceSelect.disabled = !yearSelected || !typeSelected;
        startDateInput.disabled = !yearSelected;
        endDateInput.disabled = !yearSelected;
        
        if (!yearSelected) {
            typeSelect.value = '';
            deviceSelect.innerHTML = '<option value="">-</option>';
            startDateInput.value = '';
            endDateInput.value = '';
        }
        if (yearSelected && !typeSelected) {
             deviceSelect.innerHTML = '<option value="">-</option>';
        }
    }

    resetDisplay();
    toggleFilterStates();
    setDateLimitsForYear();

    <?php if ($showHumidityStats): ?>
        document.getElementById('show-document-btn-humidity').style.display = 'inline-flex';
    <?php endif; ?>

    <?php if ($showMeteoStats): ?>
        document.getElementById('show-document-btn-meteo').style.display = 'inline-flex';
    <?php endif; ?>

    <?php if ($showChart): ?>
        document.getElementById('chart-info-block').style.display = 'block';
        document.getElementById('chart-legend-block').style.display = 'flex';
        document.getElementById('chart-container-block').style.display = 'block';

        const ctx = document.getElementById('dataChart').getContext('2d');
        const isoLabels = <?= json_encode($chartLabels) ?>;
        const localLabels = isoLabels.map(isoString => {
            const date = new Date(isoString);
            return date.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }).replace(',', '');
        });

        const startDate = new Date('<?= $startDate ?>');
        const endDate = new Date('<?= $endDate ?>');
        const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));

        const baseWidthPerDay = 60;
        const chartWidth = daysDiff * baseWidthPerDay;
        document.querySelector('.chart-wrapper').style.width = chartWidth + 'px';
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: localLabels,
                datasets: [{
                    label: 'Наличие данных',
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: <?= json_encode($chartColors) ?>,
                    borderColor: <?= json_encode($chartColors) ?>,
                    borderWidth: 1
                }]
            },
            plugins: [ChartZoom],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                return value === 0 ? 'Нет' : 'Есть';
                            }
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 90,
                            minRotation: 0,
                            maxTicksLimit: 30
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const isoString = isoLabels[tooltipItems[0].dataIndex];
                                const date = new Date(isoString);
                                return date.toLocaleString('ru-RU', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    second: '2-digit'
                                });
                            },
                            label: function(context) {
                                const value = context.raw;
                                const color = context.dataset.backgroundColor[context.dataIndex];
                                
                                if (value === 0) {
                                    return 'Нет данных';
                                } else if (color.includes('255, 99, 132')) {
                                    return 'Интерполированные данные';
                                } else {
                                    return 'Данные есть';
                                }
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    zoom: {
                        pan: {
                            enabled: true,
                            mode: 'x',
                            threshold: 10,
                            onPan: function({chart}) {
                                const meta = chart.getDatasetMeta(0);
                                if (meta.data.length > 0) {
                                    const firstPoint = meta.data[0];
                                    const lastPoint = meta.data[meta.data.length - 1];
                                    const scale = chart.scales.x;

                                    if (scale.min <= 0) {
                                        scale.min = 0;
                                        chart.update();
                                    }
                                    if (scale.max >= meta.data.length - 1) {
                                        scale.max = meta.data.length - 1;
                                        chart.update();
                                    }
                                }
                            }
                        },
                        zoom: {
                            wheel: {
                                enabled: false 
                            },
                            pinch: {
                                enabled: false 
                            },
                            mode: 'x',
                            speed: 0.1
                        }
                    }
                }
            }
        });

        const chartContainer = document.querySelector('.chart-container');
        chartContainer.addEventListener('wheel', function(e) {
            e.preventDefault();
            const delta = Math.sign(e.deltaY) * 100;
            chartContainer.scrollLeft += delta;
        });
    <?php endif; ?>

    setTimeout(function() {
        $('.toast').fadeOut();
    }, 5000);
});
</script>
