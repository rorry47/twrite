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

$is_owner  = is_post_owner($row_page);
$is_locked = !empty($row_page['pass']);
$pass_ok   = $is_locked
             && !empty($_SESSION['pass_' . $row_page['id']])
             ;

// ── ОБРАБОТКА POST (до рендера!) ────────────────────────────────────────────

// Кнопка Edit
if (isset($_POST['edit']) && $is_owner) {
    if (csrf_valid()) {
        header('Location: /?edit=' . rawurlencode($row_page['link']));
        exit();
    }
}

// Форма пароля
if (isset($_POST['send'])) {
    if (csrf_valid()) {
        $entered = mb_substr($_POST['pass'] ?? '', 0, 128, "UTF-8");
        $attempt_key = 'pass_attempts_' . (int)$row_page['id'];
        $attempts = $_SESSION[$attempt_key] ?? ['count' => 0, 'since' => time()];
        if (!is_array($attempts) || time() - (int)($attempts['since'] ?? 0) >= 900) {
            $attempts = ['count' => 0, 'since' => time()];
        }
        if ((int)$attempts['count'] >= 5) {
            http_response_code(429);
            header('Retry-After: ' . max(1, 900 - (time() - (int)$attempts['since'])));
            exit('Too many attempts. Try again later.');
        }
        if ($is_locked && password_verify($entered, $row_page['pass'])) {
            $_SESSION['pass_' . $row_page['id']] = true;
            unset($_SESSION[$attempt_key]);
        } else {
            $attempts['count']++;
            $_SESSION[$attempt_key] = $attempts;
        }
    }
    header('Location: /' . $url);
    exit();
}

// Генерируем CSRF токены для форм на странице
$csrf_token_edit = $_SESSION['csrf_token'];
$csrf_token_pass = $_SESSION['csrf_token'];

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
    <link href="/css/twrite.css?v=15" rel="stylesheet" />
    <script>
    (function(){
        var s = localStorage.getItem('theme');
        if (s === 'dark' || (!s && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
    </script>
<script>
(function () {
    // A successful publish redirects here with this one-time marker.
    var params = new URLSearchParams(window.location.search);
    var saved = params.get('draft_saved');
    if (saved === 'create' || /^edit_[a-f0-9]{32}$/.test(saved || '')) {
        try { sessionStorage.removeItem('twrite_draft_' + saved); } catch (e) {}
        params.delete('draft_saved');
        var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        window.history.replaceState(null, '', clean);
    }
})();
</script>
</head>
<body>
<header class="site-header"><a class="brand" href="/"><span class="brand-mark"><?php echo htmlspecialchars(mb_substr($name_project, 0, 1, "UTF-8"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span><?php echo htmlspecialchars($name_project, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></a><span class="header-right">Write. Share. Simple.</span></header>
<button type="button" id="copy-post-link" class="copy-post-link" aria-label="Copy publication link" title="Copy publication link"><i class="fa-regular fa-copy"></i><span>Copy link</span></button>
<script>
document.getElementById('copy-post-link').addEventListener('click', async function () {
    var button = this;
    var url = window.location.origin + window.location.pathname;
    try {
        await navigator.clipboard.writeText(url);
        button.querySelector('span').textContent = 'Copied!';
        window.setTimeout(function () { button.querySelector('span').textContent = 'Copy link'; }, 1800);
    } catch (e) {
        window.prompt('Copy publication link:', url);
    }
});
</script>


<?php if ($is_locked && !$pass_ok): ?>
<div class="content">
    <div class="locked-page"><div class="lock-icon"><i class="fa-solid fa-lock"></i></div><h1>Protected publication</h1><p>Enter the password to read this post.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pass; ?>">
        <input type="password" name="pass" placeholder="Password" autocomplete="current-password">
        <button class="btns" name="send" type="submit"><i class="fa-solid fa-unlock"></i> Unlock publication</button>
    </form></div>
</div>
<footer class="site-footer">twrite · A simple space for your words</footer>
</body>
</html>
<?php
    exit();
endif;
?>

<div class="content">
    <div class="reading-page"><div class="editor-eyebrow">Published note</div>
    <div class="b_input" id="b_input"><?php echo htmlspecialchars($row_page['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (!empty($row_page['author'])): ?>
    <div class="a_input" id="a_input"><i class="fa-solid fa-at"></i> <?php echo htmlspecialchars($row_page['author'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="data"><i class="fa-regular fa-calendar-days"></i> <?php echo htmlspecialchars(substr($row_page['date'], 0, 10)); ?></div>
    <div class="reader-reading-time" id="reader-reading-time" aria-label="Estimated reading time"></div>
    <div id="editable" class="editable"><?php echo twrite_sanitize_html($row_page['text']); ?></div>

    <?php if ($is_owner): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_edit; ?>">
        <button class="btns" name="edit" type="submit"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
    </form>
    <?php endif; ?>
    </div>
</div>

<script src="/script.js?v=16"></script>
<footer class="site-footer">twrite · A simple space for your words</footer>
</body>
</html>
