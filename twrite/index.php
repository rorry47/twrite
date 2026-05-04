<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

session_cache_limiter('private');
session_cache_expire(0);
ini_set('session.cookie_secure',   $is_https ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_lifetime', 1800);
ini_set('session.use_strict_mode', '1');
session_start();

mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");
mb_language("uni");
mb_regex_encoding("UTF-8");
ob_start("mb_output_handler");
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

$name_project = "twrite"; //name

$p_page_block = ['index.php', 'create.php', 'edit.php', 'page.php', '403.php', '404.php'];
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), $p_page_block)) {
    header('Location: /');
    exit();
}

$host = '';         // host 
$user = '';     // user 
$pass = '';      // password
$db_name = '';  // name 

$link_db = mysqli_connect($host, $user, $pass, $db_name, 3306);
if (!$link_db) {
    error_log("DB connect error: " . mysqli_connect_error());
    die("Database connection error. Please try later.");
}
mysqli_set_charset($link_db, 'utf8mb4');

mysqli_query($link_db, "DELETE FROM `content` WHERE `date` < NOW() - INTERVAL 1 DAY AND `del` = 1");
mysqli_query($link_db, "DELETE FROM `content` WHERE `date` < NOW() - INTERVAL 1 YEAR");

function getUserIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
$user_ip = getUserIP();

$url = preg_replace('/[^a-zA-Z0-9\-]/', '', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (empty($url)) {
    include("create.php");
} else {
    $stmt = mysqli_prepare($link_db, "SELECT COUNT(*) FROM content WHERE link = ?");
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
        error_log("DB prepare error: " . mysqli_error($link_db));
        echo "Database error.";
    }

    mysqli_close($link_db);
}
?>
