<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['403.php'])) {
    header('Location: /');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access denied | <?php echo htmlspecialchars($name_project); ?></title>
    <link rel="icon" href="/logo.png"/>
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/general.css" rel="stylesheet" />
    <script>
    (function(){
        var s = localStorage.getItem('theme');
        if (s === 'dark' || (!s && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
    </script>
</head>
<body>
<div class="content">
    <center>
        <h1 class="error_1">403</h1>
        <h1 class="error_2">Access denied</h1>
        <a href="/"><button class="btns"><i class="fa-solid fa-paper-plane"></i> Create new page</button></a>
    </center>
</div>
<script src="/script.js"></script>
</body>
</html>
