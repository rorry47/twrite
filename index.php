<?php
error_reporting("ALL");
session_cache_limiter('private');
session_cache_expire(0);
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_lifetime', 1800);
session_start();
mb_internal_encoding("UTF-8");
mb_http_output( "UTF-8" );
mb_language( "uni" );
mb_regex_encoding( "UTF-8" );
ob_start( "mb_output_handler" );
header('Content-Type: text/html; charset=utf-8');

$name_project = "twrite"; //name
$p_page_block = array(basename(__FILE__));
@$url_path = parse_url(@$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array(basename($url_path), $p_page_block)) {
    header('Location: /');
    exit();
}


//DATABASES [MySQL]
$host = '';         // host 
$user = '';     // user 
$pass = '';      // password
$db_name = '';  // name 
@$link_db = mysqli_connect($host, $user, $pass, $db_name,3306);
//DATABASES

$url = $_SERVER['REQUEST_URI'];
$url = preg_replace('/[^a-zA-Z0-9\-]/', '', $url);

$delete_early_data = "DELETE FROM `content` WHERE `date` < NOW() - INTERVAL 1 DAY AND `del` = 1";
if (mysqli_query($link_db, $delete_early_data)) {} 
$delete_old_data = "DELETE FROM `content` WHERE `date` < NOW() - INTERVAL 1 YEAR";
if (mysqli_query($link_db, $delete_old_data)) {} 


function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
$user_ip = getUserIP();




if (empty($url)) {
    include("create.php");
} else {

if (!$link_db) {
    die("Error connect to database: " . mysqli_connect_error());
}

$query = "SELECT COUNT(*) FROM content WHERE link = ?";
$stmt = mysqli_prepare($link_db, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $url);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($count > 0) {
        include("page.php");
    } else {
        include("404.php");
    }
} else {
    echo "Error request: " . mysqli_error($link_db);
}

mysqli_close($link_db);
}

?>
