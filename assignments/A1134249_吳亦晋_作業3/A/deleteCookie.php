<?php

    session_start();
    setcookie("loginUser", "", time() - 3600);
    $url = isset($_SESSION["login"]) ? $_SESSION["login"] : "login.php";
    header("Location: " . $url);
    exit;

?>