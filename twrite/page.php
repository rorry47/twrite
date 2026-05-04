<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['page.php'])) {
    header('Location: /');
    exit();
}

$stmt = mysqli_prepare($link_db, "SELECT * FROM `content` WHERE `link` = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $url);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$row_page = $result->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$row_page) {
    include('404.php');
    exit();
}

if (!empty($row_page['ip']) && $row_page['ip'] !== $user_ip) {
    include('403.php');
    exit();
}

$is_owner  = !empty($_SESSION['hash']) && $row_page['hash'] === $_SESSION['hash'];
$is_locked = !empty($row_page['pass']);
$pass_ok   = $is_locked
             && !empty($_SESSION['pass_' . $row_page['id']])
             && password_verify($_SESSION['pass_' . $row_page['id']], $row_page['pass']);

// ── ОБРАБОТКА POST (до рендера!) ────────────────────────────────────────────

// Кнопка Edit
if (isset($_POST['edit']) && $is_owner) {
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token_edit'] ?? '', $_POST['csrf_token'])) {
        unset($_SESSION['csrf_token_edit']);
        $_SESSION['edit'] = $row_page['id'];
        unset($_SESSION['temp']);
        header('Location: /');
        exit();
    }
}

// Форма пароля
if (isset($_POST['send'])) {
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token_pass'] ?? '', $_POST['csrf_token'])) {
        unset($_SESSION['csrf_token_pass']);
        $entered = mb_substr($_POST['pass'] ?? '', 0, 128, "UTF-8");
        if (password_verify($entered, $row_page['pass'])) {
            $_SESSION['pass_' . $row_page['id']] = $entered;
        }
    }
    header('Location: /' . $url);
    exit();
}

// Генерируем CSRF токены для форм на странице
$csrf_token_edit = bin2hex(random_bytes(32));
$_SESSION['csrf_token_edit'] = $csrf_token_edit;
$csrf_token_pass = bin2hex(random_bytes(32));
$_SESSION['csrf_token_pass'] = $csrf_token_pass;

function render_meta(string $title, string $description, string $project): void {
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $d = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($project, ENT_QUOTES, 'UTF-8');
    echo "<title>{$t} | {$p}</title>\n";
    echo "<meta itemprop=\"name\" content=\"{$t}\">\n";
    echo "<meta itemprop=\"description\" content=\"{$d}\">\n";
    echo "<meta name=\"twitter:card\" content=\"summary\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$t}\">\n";
    echo "<meta name=\"twitter:description\" content=\"{$d}\">\n";
}

$page_name = $row_page['name'];
$page_desc = strip_tags(mb_substr($row_page['text'], 0, 100, "UTF-8")) . "...";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if (($is_locked && !$pass_ok) || (!empty($row_page['ip']) && $row_page['ip'] !== $user_ip)) {
        render_meta("*************", "*************", $name_project);
    } else {
        render_meta($page_name, $page_desc, $name_project);
    }
    ?>
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

<?php if ($is_locked && !$pass_ok): ?>
<div class="content">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pass; ?>">
        <input type="password" name="pass" placeholder="Password" autocomplete="current-password">
        <button class="btns" name="send" type="submit"><i class="fa-solid fa-unlock"></i> Unlock</button>
    </form>
</div>
</body>
</html>
<?php
    exit();
endif;
?>

<div class="content">
    <div class="b_input" id="b_input"><?php echo htmlspecialchars($row_page['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (!empty($row_page['author'])): ?>
    <div class="a_input" id="a_input"><i class="fa-solid fa-at"></i> <?php echo htmlspecialchars($row_page['author'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="data"><i class="fa-regular fa-calendar-days"></i> <?php echo htmlspecialchars(substr($row_page['date'], 0, 10)); ?></div>
    <div id="editable" class="editable"><?php echo $row_page['text']; ?></div>

    <?php if ($is_owner): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_edit; ?>">
        <button class="btns" name="edit" type="submit"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
    </form>
    <?php endif; ?>
</div>

<script src="/script.js"></script>
<script data-name="BMC-Widget" data-cfasync="false" src="https://cdnjs.buymeacoffee.com/1.0.0/widget.prod.min.js" data-id="nkotov" data-description="Support me on Buy me a coffee!" data-message="" data-color="#3386af" data-position="Right" data-x_margin="18" data-y_margin="18"></script>
</body>
</html>
