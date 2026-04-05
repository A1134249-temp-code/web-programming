<?php
    if (isset($_POST['username']) && $_POST['password']){
        $dName = "admin";
        $dPWD = "1234";

        $iName = $_POST['username'];
        $iPWD = $_POST['password'];
        if ($iName == $dName && $iPWD == $dPWD) {
            header("Location: form.php");
        } else {
            echo "登入失敗！帳號或密碼錯誤。<br>";
            echo "系統將在 3 秒後自動跳轉回登入頁面...";
            
            header("Refresh: 3; url=login.php");
        }
        
    }
?>