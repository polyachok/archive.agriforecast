<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $_SESSION['role'] ?? 0;
?>
<div class="sidebar">
    <ul class="nav">
        <li class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <a href="/pages/dashboard.php"><i class="fas fa-home"></i> Главная</a>
        </li>

        <?php if ($current_role == ROLE_ADMIN): ?>
      
        <li class="nav-item <?= $current_page == 'devices.php' ? 'active' : '' ?>">
            <a href="/pages/devices.php"><i class="fas fa-microchip"></i> Приборы</a>
        </li>
        <?php endif; ?>
       

        

        <?php if ($current_role == ROLE_ADMIN): ?>
        <li class="nav-item dropdown <?= ($current_page == 'logs.php' || $current_page == 'service.php') ? 'has-active' : '' ?>">
            <div class="dropdown">
                <a href="#" class="dropdown-toggle dropdown-right" tabindex="0">
                    <i class="fas fa-info-circle"></i> Информация <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="menu" style="min-width: 270px; border-radius: 15px; padding-bottom: 15px;">
                    <li class="nav-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
                        <a href="/pages/logs.php"><i class="fas fa-desktop"></i> Монитор событий</a>
                    </li>
                    <li class="nav-item <?= $current_page == 'service.php' ? 'active' : '' ?>">
                        <a href="/pages/service.php"><i class="fas fa-chart-line"></i> Активность приборов</a>
                    </li>
                </ul>
            </div>
        </li>
        <?php endif; ?>
    </ul>
</div>

<style>
.sidebar .nav-item.dropdown.has-active > .dropdown > .dropdown-toggle {
    font-weight: bold;
    color: #3b4351;
}

.sidebar .dropdown .menu {
    width: 100%;
    background-color: #fff;
}

.sidebar .dropdown .menu .nav-item a {
    padding-left: 2rem;
}

.sidebar .dropdown .menu .nav-item.active a {
    background-color: rgba(255, 255, 255, 0.1);
    border-left: 3px solid #007bff;
}

</style>