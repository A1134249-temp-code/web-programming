<?php
    session_start();
    setcookie("loginUser", "" , time() - 3600);
    session_destroy();
    echo "使用者已登出, 將在三秒後回到登入頁面......";
    header("Refresh: 3; url=login.php");
    exit();
?>