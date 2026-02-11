<?php
define('ROOT_PATH', '/var/www/html');
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/classes/DeviceGroup.php';
require_once ROOT_PATH . '/classes/Device.php';

$deviceGroup = new DeviceGroup();
$device = new Device();

$groups = $deviceGroup->getAllGroups();
?>

<table id="groupsTable" class="display" style="width:100%">
    <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Границы области</th>
            <th>Кол-во приборов</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groups as $group): 
            $devicesCount = $device->countDevicesInGroup($group['id']);
        ?>
        <tr>
            <td><?= htmlspecialchars($group['id']) ?></td>
            <td><?= htmlspecialchars($group['name']) ?></td>
            <td>
                Широта: <?= htmlspecialchars($group['min_lat']) ?> - <?= htmlspecialchars($group['max_lat']) ?><br>
                Долгота: <?= htmlspecialchars($group['min_lng']) ?> - <?= htmlspecialchars($group['max_lng']) ?>
            </td>
            <td><?= $devicesCount ?></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="openEditModal({
                        id: <?= $group['id'] ?>,
                        name: '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>',
                        min_lat: '<?= htmlspecialchars($group['min_lat'], ENT_QUOTES) ?>',
                        max_lat: '<?= htmlspecialchars($group['max_lat'], ENT_QUOTES) ?>',
                        min_lng: '<?= htmlspecialchars($group['min_lng'], ENT_QUOTES) ?>',
                        max_lng: '<?= htmlspecialchars($group['max_lng'], ENT_QUOTES) ?>'
                    })">
                        <i class="fas fa-edit"></i> Изменить
                    </button>
                    <button class="btn btn-error btn-sm" onclick="deleteGroup(<?= $group['id'] ?>)">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
