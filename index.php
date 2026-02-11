
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <title>Agriforecast</title>
    <link rel="stylesheet" href="assets/css/spectre.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .bg-layer {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
            transition: opacity 1s ease;
            background-size: auto 100%;
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
            opacity: 1;
        }
        .bg-layer.hidden {
            opacity: 0;
        }
        .logo {
            z-index: 100;
            width: 20%;
        }
        .container {
            position: relative;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="bg-layer" id="bg1"></div>
    <div class="bg-layer hidden" id="bg2"></div>   
    <div class="container" style="padding-top: 35px; max-width: 1300px;">
        <img src="assets/img/logo.png" alt="Логотип" class="logo">
        <a href="login.php" class="btn btn-lg btn-default" style="float: right;   width: 250px;
    border-radius: 15px;">Войти</a>
    </div>
    <script>
    fetch('assets/bg_img/images.php')
        .then(res => res.json())
        .then(bgImages => {
            if (!bgImages.length) return;
            let current = 0;
            let next = 1;
            const bgDivs = [document.getElementById('bg1'), document.getElementById('bg2')];
            bgDivs[0].style.backgroundImage = `url('${bgImages[0]}')`;
            bgDivs[1].style.backgroundImage = `url('${bgImages[1 % bgImages.length]}')`;
            let visible = 0;
            setInterval(() => {
                current = (current + 1) % bgImages.length;
                next = (current + 1) % bgImages.length;
                bgDivs[1 - visible].style.backgroundImage = `url('${bgImages[current]}')`;
                bgDivs[visible].classList.add('hidden');
                bgDivs[1 - visible].classList.remove('hidden');
                visible = 1 - visible;
            }, 5000);
        });
    </script>
</body>
</html>
