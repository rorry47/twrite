<?php
$p_page_block = array(basename(__FILE__));
@$url_path = parse_url(@$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array(basename($url_path), $p_page_block)) {
    header('Location: /');
    exit();
}
$_SESSION['temp'] = '1';
$result_edit_page= mysqli_query($link_db, 'SELECT * FROM content WHERE `id`="' . $_SESSION['edit'] . '" LIMIT 1');
$edit_page = $result_edit_page->fetch_assoc();
if ($edit_page['hash'] !== $_SESSION['hash']) {
        session_start();  
        $_SESSION = [];  
        session_unset(); 
        session_destroy();  
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        header('Location: /');
        exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit | <?php echo $name_project; ?></title>
    <link rel="icon" href="/logo.png"/>
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/edit.css" rel="stylesheet" />
</head>
<body>
<div class="content">
<form id="formText" method="post">
    <div class="b_input" id="b_input" contenteditable="true" data-name="title" data-placeholder="Title"><?php echo $edit_page['name']; ?></div>
    <input type="hidden" name="title" id="titleInput">
    <div class="a_input" id="a_input" contenteditable="true" data-name="author" data-placeholder="Author"><?php echo $edit_page['author']; ?></div>
    <input type="hidden" name="author" id="authorInput">
    <div id="toolbar">
        <button type="button" onclick="document.execCommand('bold', false, '');"><i class="fa-solid fa-bold"></i></button>
        <button type="button" onclick="document.execCommand('italic', false, '');"><i class="fa-solid fa-italic"></i></button>
        <button type="button" onclick="document.execCommand('underline', false, '');"><i class="fa-solid fa-underline"></i></button>
        <button type="button" onclick="document.execCommand('strikeThrough', false, '');"><i class="fa-solid fa-strikethrough"></i></button>
        <select type="button" onchange="changeFontSize(this.value)">
            <option value="15px">15px</option>
            <option value="18px">18px</option>
            <option value="20px">20px</option>
            <option value="25px">25px</option>
            <option value="30px">30px</option>
        </select>
        <button type="button" onclick="insertLink()"><i class="fa-solid fa-link"></i></button>
        <button type="button" onclick="formatAsCode()"><i class="fa-solid fa-code"></i></button>
    </div>
    <div contenteditable="true" id="editable" class="editable" data-name="editable" data-placeholder="Your text..."><?php echo htmlspecialchars_decode($edit_page['text']); ?></div>
    <input type="hidden" name="editable" id="editableInput">
<div class="spoiler">
    <div class="spoiler-header" onclick="toggleSpoiler(this)">
        <i class="fa-solid fa-sort-down"></i>
    </div>
    <div class="spoiler-content">
        <div>
            <input id="delete" name="delete" type="checkbox"  <?php if (!empty($edit_page['del'])) { echo "checked";} ?>>
            <label for="delete"><i class="fa-regular fa-trash-can"></i> Delete after 24 hourse</label>
        </div>
        <div>
            <input id="access_ip" name="access_ip" type="checkbox" <?php if (!empty($edit_page['ip'])) { echo "checked";} ?>>
            <label for="access_ip"><i class="fa-solid fa-lock"></i> Access only for you IP:<code class="ip"><?php echo $user_ip;?></code></label>
        </div>
        <div>
            <i class="fa-solid fa-key"></i> Close with password: <br /><input type="text" name="password" maxlength="128" placeholder="pass" <?php if (!empty($edit_page['pass'])) { echo 'value="' . $edit_page['pass'] . '"';} ?>> 
        </div>
    </div>
</div>

<div><button class="btns" name="publish" id="publish" type="submit"><i class="fa-regular fa-paper-plane"></i> Publish</button></div>
</form>

</div>

<script type="text/javascript" src="/script.js"></script>
</body>
</html>



<?php
if (isset($_POST['publish'])) {
$title = mb_substr(preg_replace("/[^a-zA-ZА-Яа-я0-9\s]/u", '', htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8')), 0, 100, "UTF-8");
$author = mb_substr(preg_replace("/[^a-zA-ZА-Яа-я0-9\s]/u", '', htmlspecialchars($_POST['author'], ENT_QUOTES, 'UTF-8')), 0, 60, "UTF-8");
$text = mb_substr(nl2br(htmlspecialchars($_POST['editable'], ENT_QUOTES, 'UTF-8')), 0, 32000, "UTF-8");
function formatImages($text) {
    $text = htmlspecialchars_decode($text, ENT_QUOTES);
    $pattern = '/(?<!<img src=["\'])(https?:\/\/\S+\.(?:jpg|jpeg|png|gif))(?!["\']>)/i';
    $replacement = '<img src="$1" alt="Image">';
    $formattedText = preg_replace($pattern, $replacement, $text);
    $formattedText = htmlspecialchars($formattedText, ENT_QUOTES, 'UTF-8');
    return $formattedText;
}
$text = formatImages($text);
$pass = mb_substr(htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8'), 0, 128, "UTF-8");

if (empty($title)) {
    echo "title ";
    header('Location: /');
    exit();
}
if (empty($text)) {
    echo "txt";
    header('Location: /');
    exit();
}


    function generateSlug($link) {
    $translit = strtr($link, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ь' => '', 'ъ' => '', ' ' => '-', 'А' => 'A',
        'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F',
        'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ы' => 'Y', 'Э' => 'E',
        'Ю' => 'Yu', 'Я' => 'Ya'
    ]);

    $translit = mb_substr($translit, 0, 30, "UTF-8");
    $date = date('s-i-G-d-m-Y');
    $slug = $translit . '-' . $date;
    $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug); 
    return strtolower($slug);
}

$link = md5(generateSlug($title));



if (empty($author)) {
    $author = '';
}



if (empty($pass)) {
    $pass = '';
} else {
    if ($edit_page['pass'] == md5($pass)) {
        $pass = $edit_page['pass'];
    } else {
        $pass = md5($pass);
    }
}

$file_data_texiti = date('Y-m-d H:i:s');



$ch_delete = filter_input(INPUT_POST, 'delete', FILTER_SANITIZE_STRING);
if($ch_delete) {
    $ch_delete = "1";
} else {
    $ch_delete = NULL;
}

$ch_access_ip = filter_input(INPUT_POST, 'access_ip', FILTER_SANITIZE_STRING);
if($ch_access_ip) {
    $ch_access_ip = $user_ip;
} else {
   $ch_access_ip = NULL;
}



//GEN HASH
$hash = hash('sha256', $file_data_texiti . $user_ip);
$_SESSION['hash'] = $hash;



if (!empty($text)) {
    $add_free_access = "UPDATE `content` 
                SET `name` = '" . $title . "',
                    `link` = '" . $edit_page['link'] . "',
                    `author` = '" . $author . "',
                    `text` = '" . $text . "',
                    `date` = '" . $file_data_texiti . "',
                    `pass` = '" . $pass . "',
                    `ip` = '" . $ch_access_ip . "',
                    `del` = '" . $ch_delete . "',
                    `hash` = '" . $hash . "'
                WHERE `id` = '" . $edit_page['id'] . "'";
    if (mysqli_query($link_db, $add_free_access)) {
        echo "SUCCESS";
    } else {
        echo "ERROR";
    }
}


    $_SESSION['edit'] = [];
    $_SESSION['temp'] = [];
    header('Location: /' . $edit_page['link']);
    exit();


}

