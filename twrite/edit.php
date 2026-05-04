<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['edit.php'])) {
    header('Location: /');
    exit();
}

// Проверяем что сессия редактирования валидна
if (empty($_SESSION['edit']) || empty($_SESSION['hash'])) {
    header('Location: /');
    exit();
}

// Получаем пост
$stmt = mysqli_prepare($link_db, "SELECT * FROM `content` WHERE `id` = ? LIMIT 1");
if (!$stmt) { header('Location: /'); exit(); }
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['edit']);
mysqli_stmt_execute($stmt);
$result    = mysqli_stmt_get_result($stmt);
$edit_page = $result->fetch_assoc();
mysqli_stmt_close($stmt);

// Проверяем что hash совпадает — только создатель поста может редактировать
if (!$edit_page || $edit_page['hash'] !== $_SESSION['hash']) {
    unset($_SESSION['edit'], $_SESSION['temp'], $_SESSION['hash']);
    header('Location: /');
    exit();
}

// Помечаем что форма была показана
$_SESSION['temp'] = '1';

// ── ОБРАБОТКА POST ───────────────────────────────────────────────────────────
if (isset($_POST['publish'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_edit'] ?? '', $_POST['csrf_token'])) {
        header('Location: /');
        exit();
    }
    unset($_SESSION['csrf_token_edit']);

    $title  = mb_substr(strip_tags(trim($_POST['title']  ?? '')), 0, 100, "UTF-8");
    $author = mb_substr(strip_tags(trim($_POST['author'] ?? '')), 0,  60, "UTF-8");
    $text   = mb_substr($_POST['editable'] ?? '', 0, 100000, "UTF-8");
    $pass   = mb_substr(trim($_POST['password'] ?? ''), 0, 128, "UTF-8");

    $allowed_tags = '<b><i><u><s><a><br><p><pre><code><img><font>';
    $text = strip_tags($text, $allowed_tags);
    $text = preg_replace_callback(
        '/<img\s+src="([^"]+)"[^>]*>/i',
        function($m) {
            $src = filter_var($m[1], FILTER_SANITIZE_URL);
            return preg_match('/^https?:\/\//i', $src)
                ? '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="Image" style="max-width:100%;height:auto;">'
                : '';
        },
        $text
    );

    if (empty($title) || empty(trim(strip_tags($text)))) {
        header('Location: /');
        exit();
    }

    if (!empty($pass)) {
        $pass_hash = (!empty($edit_page['pass']) && password_verify($pass, $edit_page['pass']))
            ? $edit_page['pass']
            : password_hash($pass, PASSWORD_BCRYPT);
    } else {
        $pass_hash = '';
    }

    $date_now     = date('Y-m-d H:i:s');
    $ch_delete    = isset($_POST['delete'])    ? '1' : '';
    $ch_access_ip = isset($_POST['access_ip']) ? $user_ip : '';
    $orig_hash    = $edit_page['hash'];
    $post_id      = (int)$edit_page['id'];

    $stmt = mysqli_prepare($link_db,
        "UPDATE `content` SET
            `name`   = ?, `author` = ?, `text` = ?, `date` = ?,
            `pass`   = ?, `ip`     = ?, `del`  = ?
         WHERE `id` = ? AND `hash` = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssssssss',
            $title, $author, $text, $date_now,
            $pass_hash, $ch_access_ip, $ch_delete,
            $post_id, $orig_hash
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Update error: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Prepare error: " . mysqli_error($link_db));
    }

    // Сохраняем hash в сессии — юзер остаётся владельцем после редактирования
    // unset только edit и temp, hash оставляем
    unset($_SESSION['edit'], $_SESSION['temp']);
    header('Location: /' . $edit_page['link']);
    exit();
}

// ── Генерируем CSRF токен ────────────────────────────────────────────────────
$csrf_token_edit = bin2hex(random_bytes(32));
$_SESSION['csrf_token_edit'] = $csrf_token_edit;
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
    <link href="/css/edit.css" rel="stylesheet" />
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
<form id="formText" method="post" action="/">
    <div class="b_input" id="b_input" contenteditable="true" data-name="title" data-placeholder="Title"><?php echo htmlspecialchars($edit_page['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <input type="hidden" name="title" id="titleInput">
    <div class="a_input" id="a_input" contenteditable="true" data-name="author" data-placeholder="Author"><?php echo htmlspecialchars($edit_page['author'], ENT_QUOTES, 'UTF-8'); ?></div>
    <input type="hidden" name="author" id="authorInput">
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
        <div class="spoiler-header" onclick="toggleSpoiler(this)">
            <i class="fa-solid fa-sort-down"></i>
        </div>
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
                <input type="password" name="password" maxlength="128" placeholder="leave blank to keep current" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div><button class="btns" name="publish" id="publish" type="submit"><i class="fa-regular fa-paper-plane"></i> Save</button></div>
</form>
</div>
<script src="/script.js"></script>
</body>
</html>
