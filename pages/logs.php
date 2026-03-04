<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/classes/User.php';
require_once ROOT_PATH . '/includes/header.php';
require_once ROOT_PATH . '/classes/DeviceLogs.php';

if ($_SESSION['role'] !== ROLE_ADMIN) {
    redirect('/pages/dashboard.php?error=no_permission');
}

$deviceLogs = new DeviceLogs();

$filters = [
    'year' => $_GET['year'] ?? '',
    'device_type' => $_GET['device_type'] ?? '',
    'log_type' => $_GET['log_type'] ?? '',
    'device_uid' => $_GET['device_uid'] ?? '',
    'message' => $_GET['message'] ?? '',
    'datetime_from' => $_GET['datetime_from'] ?? '',
    'datetime_to' => $_GET['datetime_to'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'time_from' => $_GET['time_from'] ?? '',
    'time_to' => $_GET['time_to'] ?? ''
];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

$allowedLimits = [10, 25, 50, 100, 500];
if (!in_array($limit, $allowedLimits)) {
    $limit = 50;
}

try {
    $deviceLogs = new DeviceLogs();
    
    $logs = $deviceLogs->getLogs($filters, $limit, $offset);
    $totalLogs = $deviceLogs->getLogsCount($filters);
    $totalPages = ceil($totalLogs / $limit);
    
    $deviceTypes = $deviceLogs->getUniqueDeviceTypes();
    $deviceUIDs = $deviceLogs->getUniqueDeviceUIDs();
    $logTypes = $deviceLogs->getLogTypes();
    $logYears = $deviceLogs->getLogYears();
    
} catch (Exception $e) {
    $error = "Ошибка при работе с базой данных: " . $e->getMessage();
    $logs = [];
    $totalLogs = 0;
    $totalPages = 0;
    $deviceTypes = [];
    $deviceUIDs = [];
    $logTypes = ['CRITICAL', 'WARNING', 'INFO', 'DEBUG'];
    $logYears = [];
}

function translateDeviceType($deviceType) {
    $translations = [
        'Humidity' => 'AGF SoilProbe',
        'Weather Forecast' => 'Прогноз',
        'Weather Station' => 'AGF MeteoPoint'
    ];
    
    return $translations[$deviceType] ?? $deviceType;
}

function translateLogType($logType) {
    $translations = [
        'CRITICAL' => 'CRITICAL',
        'WARNING' => 'WARNING',
        'INFO' => 'INFO',
        'DEBUG' => 'DEBUG'
    ];
    
    return $translations[$logType] ?? $logType;
}

function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<style>
.compact-filters {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.compact-filters .columns {
    margin-bottom: 0;
}

.compact-filters .form-group {
    margin-bottom: 10px;
}

.compact-filters .form-group:last-child {
    margin-bottom: 0;
}

.filter-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-controls .form-select {
    min-width: 150px;
}

.filter-buttons {
    margin-left: auto;
    display: flex;
    gap: 8px;
}

.logs-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e7e9ed;
    border-radius: 8px;
    background: white;
}

.logs-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    margin: 0;
    background: white;
}

.logs-table thead {
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.logs-table th {
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e7e9ed;
    border-right: 1px solid #e7e9ed;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logs-table th:last-child {
    border-right: none;
}

.logs-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f1f3f4;
    border-right: 1px solid #f1f3f4;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.logs-table td:last-child {
    border-right: none;
}

.logs-table tbody tr:hover {
    background-color: #f8f9fa;
}

.logs-table tbody tr:nth-child(even) {
    background-color: #fafbfc;
}

.logs-table tbody tr:nth-child(even):hover {
    background-color: #f8f9fa;
}

.col-date { width: 140px; min-width: 140px; max-width: 140px; }
.col-device-type { width: 140px; min-width: 140px; max-width: 140px; }
.col-device-uid { width: 150px; min-width: 150px; max-width: 150px; }
.col-message { width: auto; min-width: 250px; }
.col-log-type { width: 120px; min-width: 120px; max-width: 120px; }

.message-cell {
    max-height: 60px;
    overflow: hidden;
    line-height: 1.4;
    position: relative;
}

.message-text {
    display: block;
    word-break: break-word;
    overflow-wrap: break-word;
}

.label {
    display: inline-block;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 4px;
    text-transform: uppercase;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.info-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.records-info {
    color: #666;
    font-size: 14px;
}

.records-per-page {
    display: flex;
    align-items: center;
    gap: 8px;
}

.records-per-page select {
    min-width: 80px;
}

@media (max-width: 768px) {
    .col-date { width: 90px; min-width: 90px; max-width: 90px; }
    .col-device-type { width: 100px; min-width: 100px; max-width: 100px; }
    .col-device-uid { width: 100px; min-width: 100px; max-width: 100px; }
    .col-message { min-width: 150px; }
    .col-log-type { width: 80px; min-width: 80px; max-width: 80px; }
    
    .logs-table th,
    .logs-table td {
        padding: 8px 4px;
        font-size: 12px;
    }
    
    .label {
        font-size: 10px;
        padding: 1px 4px;
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-buttons {
        margin-left: 0;
        justify-content: center;
    }
    
    .info-panel {
        flex-direction: column;
        align-items: stretch;
    }
}

.loading-row td {
    text-align: center;
    padding: 40px;
    color: #666;
}

.empty-row td {
    text-align: center;
    padding: 40px;
    color: #999;
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
                    <i class="fas fa-desktop"></i> Монитор событий
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="toast toast-error">
                        <button class="btn btn-clear float-right"></button>
                        <?= safeHtml($error) ?>
                    </div>
                <?php endif; ?>

                <form method="GET" class="compact-filters">
                    <div class="columns">
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="device_type">Тип устройства</label>
                                <select class="form-select" id="device_type" name="device_type">
                                    <option value="">Все типы</option>
                                    <?php foreach ($deviceTypes as $type): ?>
                                        <option value="<?= safeHtml($type) ?>" 
                                                <?= $filters['device_type'] === $type ? 'selected' : '' ?>>
                                            <?= safeHtml(translateDeviceType($type)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="log_type">Тип лога</label>
                                <select class="form-select" id="log_type" name="log_type">
                                    <option value="">Все типы</option>
                                    <?php foreach ($logTypes as $type): ?>
                                        <option value="<?= safeHtml($type) ?>" 
                                                <?= $filters['log_type'] === $type ? 'selected' : '' ?>>
                                            <?= safeHtml(translateLogType($type)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="device_uid">UID</label>
                                <input type="text" class="form-input" id="device_uid" name="device_uid" 
                                       value="<?= safeHtml($filters['device_uid']) ?>" 
                                       placeholder="Поиск по UID">
                            </div>
                        </div>
                        
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="message">Сообщение</label>
                                <input type="text" class="form-input" id="message" name="message" 
                                       value="<?= safeHtml($filters['message']) ?>" 
                                       placeholder="Поиск в сообщениях">
                            </div>
                        </div>
                    </div>
                    
                    <div class="columns">
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="year">Год</label>
                                <select class="form-select" id="year" name="year">
                                    <option value="">Все годы</option>
                                    <?php foreach ($logYears as $year): ?>
                                        <option value="<?= safeHtml($year) ?>" 
                                                <?= ($filters['year'] ?? '') === (string)$year ? 'selected' : '' ?>>
                                            <?= safeHtml($year) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="datetime_from">Дата и время с</label>
                                <input type="datetime-local" class="form-input" id="datetime_from" name="datetime_from" 
                                       value="<?= safeHtml($filters['datetime_from']) ?>">
                            </div>
                        </div>
                        
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label" for="datetime_to">Дата и время по</label>
                                <input type="datetime-local" class="form-input" id="datetime_to" name="datetime_to" 
                                       value="<?= safeHtml($filters['datetime_to']) ?>">
                            </div>
                        </div>
                        
                        <div class="column col-3">
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="filter-controls">
                                    <select class="form-select" id="quick_filter" onchange="applyQuickFilter()">
                                        <option value="">Быстрый фильтр</option>
                                        <option value="today">Сегодня</option>
                                        <option value="last_hour">Последний час</option>
                                        <option value="last_6_hours">Последние 6 часов</option>
                                        <option value="last_24_hours">Последние 24 часа</option>
                                        <option value="last_week">Последняя неделя</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="columns">
                        <div class="column col-12">
                             <div class="form-group">
                                <div class="filter-buttons" style="justify-content: flex-end; width: 100%; margin-top: 10px;">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> Фильтр
                                    </button>
                                    <a href="/pages/logs.php" class="btn btn-link btn-sm">
                                        <i class="fas fa-times"></i> Сброс
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="limit" value="<?= $limit ?>">
                </form>

                <div class="info-panel">
                    <div class="records-info">
                        Найдено записей: <strong><?= $totalLogs ?></strong>
                        <?php if ($totalPages > 1): ?>
                            | Страница <strong><?= $page ?></strong> из <strong><?= $totalPages ?></strong>
                        <?php endif; ?>
                    </div>
                    
                    <div class="records-per-page">
                        <select class="form-select" id="records_limit" onchange="changeLimit()">
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        </select>
                    </div>
                </div>

                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th class="col-date">Дата создания</th>
                                <th class="col-device-type">Тип</th>
                                <th class="col-device-uid">UID</th>
                                <th class="col-message">Сообщение</th>
                                <th class="col-log-type"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <i class="fas fa-info-circle"></i> Логи не найдены
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="col-date">
                                            <?php 
                                            $createdAt = $log['created_at'] ?? '';
                                            if ($createdAt) {
                                                echo '<small>' . date('d.m.Y H:i:s', strtotime($createdAt)) . '</small>';
                                            } else {
                                                echo '<small class="text-gray">N/A</small>';
                                            }
                                            ?>
                                        </td>
                                        <td class="col-device-type">
                                            <span class="label <?= getDeviceTypeClass($log['device_type'] ?? '') ?>">
                                                <?= safeHtml(translateDeviceType($log['device_type'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td class="col-device-uid"><?= safeHtml($log['device_uid'] ?? 'N/A') ?></td>
                                        <td class="col-message">
                                                <?php 
                                                $message = $log['message'] ?? '';
                                                ?>
                                                <span class="message-text">
                                                    <?= safeHtml($message) ?>
                                                </span>
                                        </td>
                                        <td class="col-log-type">
                                            <span class="label <?= getLogTypeClass($log['log_type'] ?? '') ?>">
                                                <?= safeHtml(translateLogType($log['log_type'] ?? '')) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="text-center mt-2">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1, 'limit' => $limit])) ?>">Предыдущая</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $i, 'limit' => $limit])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1, 'limit' => $limit])) ?>">Следующая</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
function getLogTypeClass($logType) {
    switch ($logType) {
        case 'CRITICAL':
            return 'label-error';
        case 'WARNING':
            return 'label-warning';
        case 'INFO':
            return 'label-primary';
        case 'DEBUG':
            return 'label-secondary';
        default:
            return '';
    }
}

function getDeviceTypeClass($deviceType) {
    switch ($deviceType) {
        case 'Humidity':
            return 'label-success';
        case 'Weather Forecast':
            return 'label-primary';
        case 'Weather Station':
            return 'label-secondary';
        default:
            return '';
    }
}
?>

<script>
$(document).ready(function() {
    setTimeout(function() {
        $('.toast').fadeOut();
    }, 5000);

    // --- Year filter and form disabling logic ---
    const yearSelect = $('#year');
    // Select all form controls except the year selector itself and the reset link
    const otherFilters = $('.compact-filters').find('input, select, button').not(yearSelect);

    function toggleFiltersBasedOnYear() {
        const yearSelected = yearSelect.val() !== '';
        otherFilters.prop('disabled', !yearSelected);
    }
    
    function setDateLimitsForYear() {
        const selectedYear = yearSelect.val();
        const fromInput = $('#datetime_from');
        const toInput = $('#datetime_to');

        if (selectedYear) {
            fromInput.attr('min', `${selectedYear}-01-01T00:00`);
            fromInput.attr('max', `${selectedYear}-12-31T23:59`);
            toInput.attr('min', `${selectedYear}-01-01T00:00`);
            toInput.attr('max', `${selectedYear}-12-31T23:59`);

            const fromDate = new Date(fromInput.val());
            if (fromInput.val() && fromDate.getFullYear() != selectedYear) {
                fromInput.val('');
            }
            const toDate = new Date(toInput.val());
            if (toInput.val() && toDate.getFullYear() != selectedYear) {
                toInput.val('');
            }
        } else {
            fromInput.removeAttr('min');
            fromInput.removeAttr('max');
            toInput.removeAttr('min');
            toInput.removeAttr('max');
        }
    }

    yearSelect.on('change', function() {
        toggleFiltersBasedOnYear();
        setDateLimitsForYear();
        // Also clear the date fields when the year selection changes
        if(yearSelect.val() !== '') {
            $('#datetime_from').val('');
            $('#datetime_to').val('');
        }
    });
    
    // Set initial state on page load
    toggleFiltersBasedOnYear();
    setDateLimitsForYear(); 
    // --- End of Year filter logic ---

    $('#datetime_from, #datetime_to').on('change', function() {
        if ($(this).val()) {
            $('#date_from, #time_from, #date_to, #time_to').val('');
        }
    });
    
    $('#date_from, #time_from, #date_to, #time_to').on('change', function() {
        if ($('#date_from').val() || $('#time_from').val() || $('#date_to').val() || $('#time_to').val()) {
            $('#datetime_from, #datetime_to').val('');
        }
    });
});

function applyQuickFilter() {
    const period = $('#quick_filter').val();
    if (!period) return;
    
    const now = new Date();
    let fromDate = new Date();
    
    $('#datetime_from, #datetime_to, #date_from, #time_from, #date_to, #time_to').val('');
    $('#year').val(''); // Reset year filter
    
    switch(period) {
        case 'today':
            fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0);
            break;
        case 'last_hour':
            fromDate.setHours(now.getHours() - 1);
            break;
        case 'last_6_hours':
            fromDate.setHours(now.getHours() - 6);
            break;
        case 'last_24_hours':
            fromDate.setDate(now.getDate() - 1);
            break;
        case 'last_week':
            fromDate.setDate(now.getDate() - 7);
            break;
    }
    
    const formatDateTime = (date) => {
        return date.getFullYear() + '-' + 
               String(date.getMonth() + 1).padStart(2, '0') + '-' + 
               String(date.getDate()).padStart(2, '0') + 'T' + 
               String(date.getHours()).padStart(2, '0') + ':' + 
               String(date.getMinutes()).padStart(2, '0');
    };
    
    $('#datetime_from').val(formatDateTime(fromDate));
    $('#datetime_to').val(formatDateTime(now));
    
    $('#quick_filter').val('');
    
    $('form').submit();
}

function changeLimit() {
    const newLimit = $('#records_limit').val();
    const url = new URL(window.location);
    url.searchParams.set('limit', newLimit);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>
