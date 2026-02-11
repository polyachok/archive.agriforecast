<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/Device.php';
require_once ROOT_PATH . '/classes/User.php';

$device = new Device();
$user = new User();

if ($_SESSION['role'] == ROLE_ADMIN) {
    $devices = $device->getAllDevices();
} elseif ($_SESSION['role'] == ROLE_DEALER) {
    $devices = $device->getDealerDevices($_SESSION['user_id']);
} else {
    $devices = $device->getUserDevices($_SESSION['user_id']);
}
?>

<style>
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .btn-service {
        background-color: #5755d9;
        border-color: #5755d9;
        color: white;
    }
    
    .btn-service:hover {
        background-color: #4b48d6;
        border-color: #4b48d6;
        color: white;
    }
    
    @media (max-width: 768px) {
        .btn-text {
            display: none;
        }
        
        .btn-icon {
            display: inline-block;
            margin: 0;
        }
        
        .btn-sm {
            padding: 6px 8px;
            min-width: 32px;
        }
    }
    
    @media (min-width: 769px) {
        .btn-icon {
            display: none;
        }
    }
</style>

<table id="devicesTable" class="display" style="width:100%">
    <thead>
        <tr>
            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
            <th>ID</th>
            <?php endif; ?>
            <th>Название</th>
            <th>Тип</th>
            <th>Статус</th>
            <th class="hide-xs">Координаты</th>
            <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
            <th>Имя</th>
            <?php endif; ?>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($devices as $d): 
            $device_user = $d['user_id'] ? $user->getUser($d['user_id']) : null;
        ?>
        <tr>
            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                <td><?= htmlspecialchars($d['device_id']) ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($d['name']) ?></td>
            <td><?= htmlspecialchars($d['device_type']) ?></td>
            <td>
                <?php if (isset($d['is_blocked']) && $d['is_blocked']): ?>
                    <span class="label label-error">Заблокировано</span>
                <?php elseif (isset($d['is_deleted']) && $d['is_deleted']): ?>
                    <span class="label label-warning">Удалено</span>
                <?php else: ?>
                    <span class="label label-success">Активно</span>
                <?php endif; ?>
            </td>
            <td class="hide-xs" data-order="<?= $d['coordinates'] ?>"><?= $d['coordinates'] ? htmlspecialchars($d['coordinates']) : 'Не указаны' ?></td>
            <?php if ($_SESSION['role'] <= ROLE_DEALER): ?>
            <td><?= $device_user ? $device_user['name'] : 'Не назначен' ?></td>
            <?php endif; ?>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="openEditModal({
                        id: <?= $d['id'] ?>,
                        device_id: '<?= htmlspecialchars($d['device_id'], ENT_QUOTES) ?>',
                        name: '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>',
                        device_type: '<?= htmlspecialchars($d['device_type'], ENT_QUOTES) ?>',
                        coordinates: <?= $d['coordinates'] ? '['.htmlspecialchars($d['coordinates'], ENT_QUOTES).']' : 'null' ?>,
                        user_id: <?= $d['user_id'] ?: 'null' ?>,
                        current_user: '<?= $device_user ? htmlspecialchars($device_user['username']) : 'Не назначен' ?>'
                    })">
                        <i class="fas fa-edit btn-icon"></i>
                        <span class="btn-text">Изменить</span>
                    </button>
                    
                    <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                    <button class="btn btn-error btn-sm" onclick="deleteDevice(<?= $d['id'] ?>)">
                        <i class="fas fa-trash-alt btn-icon"></i>
                        <span class="btn-text">Удалить</span>
                    </button>
                    
                    <button class="btn btn-service btn-sm" 
                            onclick="openServiceMenu('<?= htmlspecialchars($d['device_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($d['device_type'], ENT_QUOTES) ?>')"
                            title="Сервисное меню за последние сутки">
                        <i class="fas fa-tools btn-icon"></i>
                        <span class="btn-text">Сервис</span>
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-sm btn-primary" onclick="showDeviceForecast(<?= $d['id'] ?>)">
                        <i class="icon icon-share"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>

function openServiceMenu(deviceId, deviceType) {

    let serviceDeviceType = '';
    switch(deviceType) {
        case 'VP':
            serviceDeviceType = 'soil';
            break;
        case 'M':
            serviceDeviceType = 'meteo';
            break;
        default:
            alert('Неподдерживаемый тип устройства для сервисного меню: ' + deviceType);
            return;
    }
    
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    const formatDate = (date) => {
        return date.getFullYear() + '-' + 
               String(date.getMonth() + 1).padStart(2, '0') + '-' + 
               String(date.getDate()).padStart(2, '0');
    };
    
    const startDate = formatDate(yesterday);
    const endDate = formatDate(today);

    const offsetMinutes = -today.getTimezoneOffset();
    const timezoneOffset = offsetMinutes / 60;

    const params = new URLSearchParams({
        device_type: serviceDeviceType,
        device_id: deviceId,
        start_date: startDate,
        end_date: endDate,
        timezone_offset: timezoneOffset
    });
    
    const serviceUrl = '/pages/service.php?' + params.toString();
    window.open(serviceUrl, '_blank');
}
</script>
