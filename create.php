<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['create.php'])) {
    header('Location: /');
    exit();
}

// ── ОБРАБОТКА POST ───────────────────────────────────────────────────────────
if (isset($_POST['publish'])) {
    if (!csrf_valid()) {
        header('Location: /');
        exit();
    }

    // Lightweight per-session publication throttle (supplement with edge/IP limits).
    if (time() - (int)($_SESSION['last_publish_at'] ?? 0) < 10) {
        http_response_code(429);
        header('Retry-After: 10');
        exit('Please wait before publishing again.');
    }

    $title  = mb_substr(strip_tags(trim($_POST['title']  ?? '')), 0, 100, "UTF-8");
    $author = mb_substr(strip_tags(trim($_POST['author'] ?? '')), 0,  60, "UTF-8");
    $text   = mb_substr($_POST['editable'] ?? '', 0, 100000, "UTF-8");
    $pass   = mb_substr(trim($_POST['password'] ?? ''), 0, 128, "UTF-8");

    $text = twrite_sanitize_html($text);

    if (empty($title) || empty(trim(strip_tags($text)))) {
        header('Location: /?validation_error=1', true, 303);
        exit();
    }

    $link      = bin2hex(random_bytes(16));
    $pass_hash = !empty($pass) ? password_hash($pass, PASSWORD_BCRYPT) : '';
    $date_now  = date('Y-m-d H:i:s');
    $ch_delete    = isset($_POST['delete'])    ? '1' : '';
    $ch_access_ip = isset($_POST['access_ip']) ? $user_ip : '';

    // hash привязан к сессии — только создатель может редактировать
    $owner_token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $owner_token);

    $stmt = mysqli_prepare($link_db,
        "INSERT INTO `content` (`id`,`name`,`link`,`author`,`text`,`date`,`pass`,`ip`,`del`,`hash`)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssssssss',
            $title, $link, $author, $text, $date_now, $pass_hash, $ch_access_ip, $ch_delete, $hash
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Insert error: " . mysqli_stmt_error($stmt));
            http_response_code(500);
            exit('Unable to publish. Please try again.');
        }
        remember_post_owner((int)mysqli_insert_id($link_db), $owner_token);
        $_SESSION['last_publish_at'] = time();
        mysqli_stmt_close($stmt);
    } else {
        error_log("Prepare error: " . mysqli_error($link_db));
        http_response_code(500);
        exit('Unable to publish. Please try again.');
    }

    header('Location: /' . $link . '?draft_saved=create');
    exit();
}

// ── Генерируем CSRF токен ────────────────────────────────────────────────────
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($name_project); ?></title>
    <meta itemprop="name" content="<?php echo htmlspecialchars($name_project); ?>">
    <meta name="description" content="Simple service for publishing text posts.">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($name_project); ?>" />
    <meta name="twitter:description" content="Simple service for publishing text posts.">
    <link rel="icon" href="/logo.png"/>
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/twrite.css?v=16" rel="stylesheet" />
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
<header class="site-header"><a class="brand" href="/"><span class="brand-mark"><?php echo htmlspecialchars(mb_substr($name_project, 0, 1, "UTF-8"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span><?php echo htmlspecialchars($name_project, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></a><span class="header-right">Write. Share. Simple.</span></header>
<div class="content">
<form id="formText" method="post">
    <div class="editor-eyebrow"> New publication</div>
    <div class="b_input" id="b_input" contenteditable="true" data-name="title" data-placeholder="Title"></div>
    <input type="hidden" name="title" id="titleInput">
    <div class="a_input" id="a_input" contenteditable="true" data-name="author" data-placeholder="Author"></div>
    <input type="hidden" name="author" id="authorInput">
    <div class="editor-divider"><button type="button" id="format-trigger" aria-controls="toolbar" aria-expanded="false" aria-label="Toggle formatting tools" title="Formatting tools"><i class="fa-solid fa-sliders" aria-hidden="true"></i></button></div>
    <div id="toolbar" aria-label="Text formatting tools">
        <button type="button" onclick="document.execCommand('bold', false, '');"><i class="fa-solid fa-bold"></i></button>
        <button type="button" onclick="document.execCommand('italic', false, '');"><i class="fa-solid fa-italic"></i></button>
        <button type="button" onclick="document.execCommand('underline', false, '');"><i class="fa-solid fa-underline"></i></button>
        <button type="button" onclick="document.execCommand('strikeThrough', false, '');"><i class="fa-solid fa-strikethrough"></i></button>
        <select onchange="changeFontSize(this.value)">
            <option value="15px">15px</option>
            <option value="18px">18px</option>
            <option value="20px">20px</option>
            <option value="25px">25px</option>
            <option value="30px">30px</option>
        </select>
        <select aria-label="Heading style" title="Heading style" onchange="formatHeading(this.value);this.selectedIndex=0">
            <option value="">Heading</option>
            <option value="h2">Heading 1</option>
            <option value="h3">Heading 2</option>
            <option value="h4">Heading 3</option>
            <option value="p">Paragraph</option>
        </select>
        <button type="button" id="insert-table-button" title="Insert table" aria-label="Insert table" aria-haspopup="true"><i class="fa-solid fa-table"></i></button>
        <button type="button" onclick="insertLink()"><i class="fa-solid fa-link"></i></button>
        <button type="button" onclick="formatAsCode()"><i class="fa-solid fa-code"></i></button>
    </div>
    <div contenteditable="true" id="editable" class="editable" data-name="editable" data-placeholder="Your text..."></div>
    <input type="hidden" name="editable" id="editableInput">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

    <div class="writing-stats"><span id="word-count">0 words</span><span class="stats-dot">·</span><span id="char-count">0 characters</span><span class="stats-dot">·</span><span id="reading-time">0 min read</span><button type="button" id="focus-toggle" class="focus-toggle" aria-pressed="false" title="Focus mode"><i class="fa-solid fa-expand"></i> Focus</button></div>
    <div class="spoiler">
        <div class="spoiler-header" onclick="toggleSpoiler(this)"><span><i class="fa-solid fa-sliders"></i> Publication settings</span><i class="fa-solid fa-chevron-down"></i></div>
        <div class="spoiler-content">
            <div>
                <input id="delete" name="delete" type="checkbox">
                <label for="delete"><i class="fa-regular fa-trash-can"></i> Delete after 24 hours</label>
            </div>
            <div>
                <input id="access_ip" name="access_ip" type="checkbox">
                <label for="access_ip"><i class="fa-solid fa-lock"></i> Access only for your IP: <code class="ip"><?php echo htmlspecialchars($user_ip); ?></code></label>
            </div>
            <div>
                <i class="fa-solid fa-key"></i> Close with password: <br>
                <div class="password-field"><input id="publication-password" type="password" name="password" maxlength="128" placeholder="password" autocomplete="new-password"><div class="password-controls"><button type="button" class="generate-password" aria-label="Generate new password" title="Generate new password"><i class="fa-solid fa-wand-magic-sparkles"></i></button><button type="button" class="copy-password" aria-label="Copy password" title="Copy password"><i class="fa-regular fa-copy"></i></button></div></div>
            </div>
        </div>
    </div>

    <div class="editor-actions"><span class="editor-hint"><i class="fa-solid fa-shield-halved"></i> Your draft is saved in this tab</span><button type="button" class="preview-button" id="preview-post"><i class="fa-regular fa-eye"></i> Preview</button><button class="btns" name="publish" id="publish" type="submit"><i class="fa-regular fa-paper-plane"></i> Publish</button></div>
<p class="shortcut-hint">Shortcuts: <kbd>Ctrl/Cmd</kbd> + <kbd>S</kbd> publish/save <span>·</span> <kbd>Ctrl/Cmd</kbd> + <kbd>B</kbd> bold <span>·</span> <kbd>Esc</kbd> exit focus</p>
</form>
<dialog id="preview-dialog" class="preview-dialog" aria-label="Publication preview"><div class="preview-top"><span>Preview</span><button type="button" id="close-preview" aria-label="Close preview"><i class="fa-solid fa-xmark"></i></button></div><article class="reading-page"><h1 id="preview-title"></h1><div id="preview-author"></div><div id="preview-body"></div></article></dialog>
</div>
<script src="/script.js?v=16"></script>
<footer class="site-footer">twrite · A simple space for your words</footer>
</body>
</html>
