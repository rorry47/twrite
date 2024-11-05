<?php
$p_page_block = array(basename(__FILE__));
@$url_path = parse_url(@$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array(basename($url_path), $p_page_block)) {
    header('Location: /');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access denied | <?php echo $name_project; ?></title>
    <link rel="icon" href="/logo.png"/>
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/other.css" rel="stylesheet" />
</head>
<body>

<div class="content">
<center>

    <h1 class="error_1">403</h1>
    <h1 class="error_2">Access denied</h1>
    <a href="/"><button class="btns"><i class="fa-solid fa-paper-plane"></i> Create new page</button></a>
</center>
</div>
</body>
</html>