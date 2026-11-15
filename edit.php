<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['edit.php'])) {
    header('Location: /');
    exit();
}

// Explicit post-specific editing route; no shared edit/temp session state.
$edit_slug = $_GET['edit'] ?? '';
if (!is_string($edit_slug) || !preg_match('/^[a-f0-9]{32}$/', $edit_slug)) {
    http_response_code(404); exit('Post not found');
}
$stmt = mysqli_prepare($link_db, "SELECT * FROM content WHERE link = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $edit_slug);
mysqli_stmt_execute($stmt);
$edit_page = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$edit_page || !is_post_owner($edit_page)) {
    http_response_code(403); exit('Editing access unavailable for this browser');
}
$csrf_token_edit = $_SESSION['csrf_token'];

// ── ОБРАБОТКА POST ───────────────────────────────────────────────────────────
if (isset($_POST['publish'])) {
    if (!csrf_valid()) {
        header('Location: /');
        exit();
    }

    $title  = mb_substr(strip_tags(trim($_POST['title']  ?? '')), 0, 100, "UTF-8");
    $author = mb_substr(strip_tags(trim($_POST['author'] ?? '')), 0,  60, "UTF-8");
    $text   = mb_substr($_POST['editable'] ?? '', 0, 100000, "UTF-8");
    $pass   = mb_substr(trim($_POST['password'] ?? ''), 0, 128, "UTF-8");

    $text = twrite_sanitize_html($text);

    if (empty($title) || empty(trim(strip_tags($text)))) {
        header('Location: /?edit=' . rawurlencode($edit_slug) . '&validation_error=1', true, 303);
        exit();
    }

    if (!empty($pass)) {
        $pass_hash = (!empty($edit_page['pass']) && password_verify($pass, $edit_page['pass']))
            ? $edit_page['pass']
            : password_hash($pass, PASSWORD_BCRYPT);
    } else {
        $pass_hash = $edit_page['pass'];
    }

    $ch_delete    = isset($_POST['delete'])    ? '1' : '';
    $ch_access_ip = isset($_POST['access_ip']) ? ($edit_page['ip'] ?: $user_ip) : '';
    $orig_hash    = $edit_page['hash'];
    $post_id      = (int)$edit_page['id'];

    $stmt = mysqli_prepare($link_db,
        "UPDATE `content` SET
            `name`   = ?, `author` = ?, `text` = ?,
            `pass`   = ?, `ip`     = ?, `del`  = ?
         WHERE `id` = ? AND `hash` = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssssssis',
            $title, $author, $text,
            $pass_hash, $ch_access_ip, $ch_delete,
            $post_id, $orig_hash
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Update error: " . mysqli_stmt_error($stmt));
            http_response_code(500); exit('Unable to save changes');
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Prepare error: " . mysqli_error($link_db));
        http_response_code(500); exit('Unable to save changes');
    }

    unset($_SESSION['pass_' . $post_id]);
    header('Location: /' . $edit_page['link'] . '?draft_saved=edit_' . $edit_slug);
    exit();
}

// ── Генерируем CSRF токен ────────────────────────────────────────────────────
$csrf_token_edit = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit | <?php echo htmlspecialchars($name_project); ?></title>
    <link rel="icon" href="/logo.png"/>
    <meta itemprop="name" content="<?php echo htmlspecialchars($name_project); ?>">
    <meta name="description" content="Simple service for publishing text posts.">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($name_project); ?>" />
    <meta name="twitter:description" content="Simple service for publishing text posts.">
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/twrite.css?v=4" rel="stylesheet" />
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
<header class="site-header"><a class="brand" href="/"><span class="brand-mark">t</span>twrite</a><span class="header-right">Write. Share. Simple.</span></header>
<div class="content">
<form id="formText" method="post" action="/?edit=<?php echo rawurlencode($edit_page['link']); ?>">
    <div class="editor-eyebrow">Editing your post</div>
    <div class="b_input" id="b_input" contenteditable="true" data-name="title" data-placeholder="Title"><?php echo htmlspecialchars($edit_page['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <input type="hidden" name="title" id="titleInput">
    <div class="a_input" id="a_input" contenteditable="true" data-name="author" data-placeholder="Author"><?php echo htmlspecialchars($edit_page['author'], ENT_QUOTES, 'UTF-8'); ?></div>
    <input type="hidden" name="author" id="authorInput">
    <div class="editor-divider"></div>
    <div id="toolbar">
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
        <button type="button" onclick="insertLink()"><i class="fa-solid fa-link"></i></button>
        <button type="button" onclick="formatAsCode()"><i class="fa-solid fa-code"></i></button>
    </div>
    <div contenteditable="true" id="editable" class="editable" data-name="editable" data-placeholder="Your text..." data-edit-mode="true"><?php echo $edit_page['text']; ?></div>
    <input type="hidden" name="editable" id="editableInput">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_edit; ?>">

    <div class="spoiler">
        <div class="spoiler-header" onclick="toggleSpoiler(this)"><span><i class="fa-solid fa-sliders"></i> Publication settings</span><i class="fa-solid fa-chevron-down"></i></div>
        <div class="spoiler-content">
            <div>
                <input id="delete" name="delete" type="checkbox" <?php if (!empty($edit_page['del'])) echo 'checked'; ?>>
                <label for="delete"><i class="fa-regular fa-trash-can"></i> Delete after 24 hours</label>
            </div>
            <div>
                <input id="access_ip" name="access_ip" type="checkbox" <?php if (!empty($edit_page['ip'])) echo 'checked'; ?>>
                <label for="access_ip"><i class="fa-solid fa-lock"></i> Access only for your IP: <code class="ip"><?php echo htmlspecialchars($user_ip); ?></code></label>
            </div>
            <div>
                <i class="fa-solid fa-key"></i> Close with password: <br>
                <div class="password-field"><input id="publication-password" type="password" name="password" maxlength="128" placeholder="leave blank to keep current" autocomplete="new-password"><button type="button" class="generate-password" aria-label="Generate secure password" title="Generate secure password"><i class="fa-solid fa-wand-magic-sparkles"></i><span>Generate</span></button></div>
            </div>
        </div>
    </div>

    <div class="editor-actions"><span class="editor-hint"><i class="fa-solid fa-shield-halved"></i> Your draft is saved in this tab</span><button class="btns" name="publish" id="publish" type="submit"><i class="fa-regular fa-paper-plane"></i> Save</button></div>
</form>
</div>
<script src="/script.js?v=5"></script>
<footer class="site-footer">twrite · A simple space for your words</footer>
</body>
</html>
