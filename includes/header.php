<?php
require_once __DIR__ . '/auth.php';

$current_year = $_SESSION['year'];
$user = new User($current_year);



$current_user = $user->getUser($_SESSION['user_id']);
$user_period = $user->getUserPeriod($_SESSION['username']);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Портал управления</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/spectre.min.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
    <link rel="stylesheet" href="https://unpkg.com/spectre.css/dist/spectre-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css">
    <style>
        :root {
            --primary-color: #5755d9;
            --dark-color: #3d3b8e;
        }
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
            padding: 20px;
            border-radius: 20px;
        }
        .sidebar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
            padding: 20px;
            border-radius: 20px;
        }
        .container {
            max-width: 1400px;
        }
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .table {
            background-color: white;
        }
        .btn {
            border-radius: 15px;
        }
        .btn-action {
            padding: 0.3rem 0.6rem;
            font-size: 0.85rem;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        

        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: auto;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .mobile-sidebar.active {
            transform: translateX(0);
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }
        @media (min-width: 768px) {
            .mobile-sidebar, .overlay {
                display: none !important;
            }
        }

        /* Отменяем стандартное поведение Spectre для navbar */
        .navbar {
            display: flex;
            justify-content: flex-start; /* всё выравнивается по левому краю */
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem; /* небольшой отступ между элементами */
        }

        /* Левая секция (логотип + меню) */
        .navbar .navbar-section:first-child {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Средняя секция (выбор года + datepicker) — теперь тоже слева */
        .navbar .navbar-section:nth-child(2) {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        /* Правая секция (профиль) — прижимаем к правому краю */
        .navbar .navbar-section:last-child {
            margin-left: auto; /* отталкивает всё остальное влево */
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="container">
    <header class="navbar">
        <section class="navbar-section">
            <a class="navbar-brand mr-2" id="mobileMenu">
                <i class="fas fa-bars show-xs"></i>
            </a>
            <a href="/pages/dashboard.php" class="navbar-brand mr-2">
                <i class="fas fa-tachometer-alt"></i><span class="hide-xs">Портал управления</span>
            </a>
        </section>
        <section class="navbar-section">
            <span class="mr-2 my-2 text-nowrap">Выберите период:</span>
            <select class="form-select" id="device_order" style="width:35%;"
                 onchange="updateYear(this.value)"
            >
                <?php foreach($user_period as $year): ?>    
                <option value="<?=$year['year']?>" <?=$current_year == $year['year'] ? 'selected' : '' ?>><?=$year['year']?></option>
                <?php endforeach;?>
            </select>
            <input class="form-select" type="text" id="datepicker" readonly placeholder="ДД.ММ.ГГГГ">
        </section>

        <section class="navbar-section">
            <div class="dropdown dropdown-right">
                <a class="btn btn-link dropdown-toggle" tabindex="0">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($current_user['username']) ?>
                </a>
                <ul class="menu">
                    <li class="menu-item"><a href="/pages/profile.php"><i class="fas fa-user"></i> Профиль</a></li>
                    <li class="menu-item"><a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>
                </ul>
            </div>
        </section>
    </header>

    <div class="overlay" id="overlay"></div>
    <div class="mobile-sidebar" id="mobileSidebar">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <script>
        $(document).ready(function() {
            $('#mobileMenu').click(function() {
                $('#mobileSidebar').toggleClass('active');
                $('#overlay').toggle();
            });
            
            $('#overlay').click(function() {
                $('#mobileSidebar').removeClass('active');
                $('#overlay').hide();
            });
        });

        
        
        

        function updateYear(year) {
            const url = new URL(window.location);
            url.searchParams.set('year', year);
            window.location.href = url.href;
        }
    </script>
