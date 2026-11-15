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
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_strict_mode', '1');
session_start();

// Stable per-form CSRF token, shared across tabs without overwriting.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_valid(): bool {
    return isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}
function owner_cookie_name(int $id): string { return 'tw_owner_' . $id; }
function is_post_owner(array $post): bool {
    $token = $_COOKIE[owner_cookie_name((int)$post['id'])] ?? '';
    if (is_string($token) && $token !== '' && hash_equals($post['hash'], hash('sha256', $token))) return true;
    // Legacy owner sessions are accepted only for posts created before the migration.
    return !empty($_SESSION['hash']) && hash_equals($post['hash'], $_SESSION['hash']);
}
function remember_post_owner(int $id, string $token): void {
    setcookie(owner_cookie_name($id), $token, [
        'expires' => time() + 31536000, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Strict'
    ]);
}


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

// Load credentials from a private .env file; never expose it over HTTP.
$env_file = __DIR__ . '/.env';
if (!is_file($env_file) || !is_readable($env_file)) {
    error_log('Missing or unreadable twrite/.env');
    http_response_code(503);
    exit('Service configuration unavailable.');
}
$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
if (!is_array($env)) {
    error_log('Invalid twrite/.env format');
    http_response_code(503);
    exit('Service configuration unavailable.');
}
foreach (['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'] as $key) {
    if (!isset($env[$key]) || !is_string($env[$key])) {
        error_log('Missing environment setting: ' . $key);
        http_response_code(503);
        exit('Service configuration unavailable.');
    }
}
$port = $env['DB_PORT'] ?? '3306';
if (!ctype_digit((string)$port) || (int)$port < 1 || (int)$port > 65535) {
    error_log('Invalid DB_PORT in twrite/.env');
    http_response_code(503);
    exit('Service configuration unavailable.');
}
$link_db = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME'], (int)$port);
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
    if (isset($_GET['edit']) && is_string($_GET['edit'])) {
        include('edit.php');
    } else {
        include("create.php");
    }
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
