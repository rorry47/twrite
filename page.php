<?php
$p_page_block = array(basename(__FILE__));
@$url_path = parse_url(@$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array(basename($url_path), $p_page_block)) {
    header('Location: /');
    exit();
}

$result_page= mysqli_query($link_db, 'SELECT * FROM content WHERE `link`="' . $url . '" LIMIT 1');
$row_page = $result_page->fetch_assoc();

if (!empty($row_page['ip'])) {
    if ($row_page['ip'] !== $user_ip) {
    include('403.php');
    exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 

if (!empty($row_page['pass'])) {
    if ($row_page['pass'] !== @$_SESSION['pass']) {
       echo "**********";
    } else {
        echo  $row_page['name']; 
    }
} else {
    echo  $row_page['name']; 
}
?> | <?php echo $name_project; ?></title>
    <link rel="icon" href="/logo.png"/>
    <link href="/css/fontawesome.css" rel="stylesheet" />
    <link href="/css/all.css" rel="stylesheet" />
    <link href="/css/all.min.css" rel="stylesheet" />
    <link href="/css/general.css" rel="stylesheet" />
<script type="text/javascript">
window.sessionStorage.clear();
</script>


</head>
<body>



<?php 
if (!empty($row_page['pass'])) {
    if($row_page['pass'] != @$_SESSION['pass']) {
    echo '<div class="content">
    <form method="post">
    <input type="password" name="pass" placeholder="pAsWoRd">
    <button class="btns" name="send" type="submit"><i class="fa-solid fa-unlock"></i> Unlock</button>
    </form>
    </div>
    </body>
    </html>
    ';
    if (isset($_POST['send'])) {
        $pass = mb_substr($_POST['pass'], 0, 128, "UTF-8");
        $pass = md5($pass);
        if ($pass == $row_page['pass']) {
            $_SESSION['pass'] = $pass;
            header('Location: /' . $url);
            exit();
        } else {
            header('Location: /' . $url);
            exit();
        }

    }

    exit();

    }
}
?>

<div class="content">
    <div class="b_input" id="b_input"><?php echo $row_page['name']; ?></div>
    <?php if (!empty($row_page['author'])) { ?>
    <div class="a_input" id="a_input"><i class="fa-solid fa-at"></i> <?php echo $row_page['author']; ?></div>
    <?php } ?>
    <div class="data"><i class="fa-regular fa-calendar-days"></i> <?php echo substr($row_page['date'], 0, 10); ?></div>
    <div id="editable" class="editable"><?php echo htmlspecialchars_decode($row_page['text']); ?></div>

    <?php if (@$_SESSION['hash'] == $row_page['hash']) { ?>
    <form method="post">
        <button class="btns" name="edit" type="submit"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
    </form>
    <?php } ?>

</div>
</body>

</html>
<?php
if (isset($_POST['edit'])) {
    if (@$_SESSION['hash'] == $row_page['hash']) {
        $_SESSION['edit'] = $row_page['id'];
        header('Location: /');
        exit();
    }
}
?>