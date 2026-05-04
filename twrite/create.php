<?php
$url_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array(basename($url_path), ['create.php'])) {
    header('Location: /');
    exit();
}

// Если есть активная сессия редактирования
if (!empty($_SESSION['edit'])) {
    // temp=1 означает что edit форма уже была показана и сабмит прошёл (или юзер ушёл)
    // Но НЕ сбрасываем если это POST от edit формы!
    if (!empty($_SESSION['temp']) && !isset($_POST['publish'])) {
        unset($_SESSION['edit'], $_SESSION['temp']);
    } else {
        include("edit.php");
        exit();
    }
}

// ── ОБРАБОТКА POST ───────────────────────────────────────────────────────────
if (isset($_POST['publish'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        header('Location: /');
        exit();
    }
    unset($_SESSION['csrf_token']);

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

    function generateSlug(string $link): string {
        $translit = strtr($link, [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y',
            'э'=>'e','ю'=>'yu','я'=>'ya','ь'=>'','ъ'=>'',' '=>'-',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh',
            'З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N',
            'О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
            'Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ы'=>'Y','Э'=>'E',
            'Ю'=>'Yu','Я'=>'Ya'
        ]);
        $translit = mb_substr($translit, 0, 30, "UTF-8");
        $date     = date('s-i-G-d-m-Y');
        $slug     = preg_replace('/[^a-zA-Z0-9\-]/', '', $translit . '-' . $date);
        $slug     = preg_replace('/-+/', '-', $slug);
        return strtolower($slug);
    }

    $link      = md5(generateSlug($title));
    $pass_hash = !empty($pass) ? password_hash($pass, PASSWORD_BCRYPT) : '';
    $date_now  = date('Y-m-d H:i:s');
    $ch_delete    = isset($_POST['delete'])    ? '1' : '';
    $ch_access_ip = isset($_POST['access_ip']) ? $user_ip : '';

    // hash привязан к сессии — только создатель может редактировать
    $hash = hash('sha256', session_id() . $date_now . $user_ip . bin2hex(random_bytes(8)));
    $_SESSION['hash'] = $hash;

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
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Prepare error: " . mysqli_error($link_db));
    }

    header('Location: /' . $link);
    exit();
}

// ── Генерируем CSRF токен ────────────────────────────────────────────────────
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
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
<form id="formText" method="post">
    <div class="b_input" id="b_input" contenteditable="true" data-name="title" data-placeholder="Title"></div>
    <input type="hidden" name="title" id="titleInput">
    <div class="a_input" id="a_input" contenteditable="true" data-name="author" data-placeholder="Author"></div>
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
    <div contenteditable="true" id="editable" class="editable" data-name="editable" data-placeholder="Your text..."></div>
    <input type="hidden" name="editable" id="editableInput">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

    <div class="spoiler">
        <div class="spoiler-header" onclick="toggleSpoiler(this)">
            <i class="fa-solid fa-sort-down"></i>
        </div>
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
                <input type="password" name="password" maxlength="128" placeholder="password" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div><button class="btns" name="publish" id="publish" type="submit"><i class="fa-regular fa-paper-plane"></i> Publish</button></div>
</form>
</div>
<script src="/script.js"></script>
</body>
</html>
