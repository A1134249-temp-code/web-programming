<?php
    session_start();

    if (!isset($_SESSION["login"]) || $_SESSION["login"] !== "student.php"){

        echo "<h1>此頁面屬於 student <h1><br><br>";
    
        if (isset($_COOKIE["loginUser"])){

            echo "<h1>您的使用者為: " . $_COOKIE["loginUser"] . "</h1><br><br>";

        }

        echo "<h1>您不具備訪問權限, 將在三秒後回到登入頁面......</h1><br><br>";

        header("Refresh: 3; url=login.php");
        exit();
    }

    if(isset($_COOKIE["loginUser"])) {
        
        echo "<h2>歡迎回來，" . $_COOKIE["loginUser"] . "</h2><br><br>";
        echo '<a href="deleteCookie.php"><button type="button">刪除 Cookie 並刷新</button></a>';

    }else{

        echo "<h1>Cookie 已失效</h1><br><br>";
        
    }

    echo '<a href="logout.php"><button type="button">登出系統</button></a>';
?>