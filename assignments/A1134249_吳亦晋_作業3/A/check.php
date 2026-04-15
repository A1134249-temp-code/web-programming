<?php
    session_start();

    if (isset($_POST["uName"]) && isset($_POST["uPWD"])){

        $aName = "admin";
        $aPWD = "123456";

        $tName = "teacher";
        $tPWD = "12345";

        $sName = "student";
        $sPWD = "1234";

        $iName = $_POST["uName"];
        $iPWD = $_POST["uPWD"];

        $date = strtotime("+1 hours", time());

        if ($iName === $aName && $iPWD === $aPWD){

            $_SESSION["login"] = "admin.php";
            setcookie("loginUser", $iName, $date);
            header("Refresh: 0; url=admin.php");

        }else if ($iName == $tName && $iPWD == $tPWD){

            $_SESSION["login"] = "teacher.php";
            setcookie("loginUser", $iName, $date);
            header("Refresh: 0; url=teacher.php");
            
        }else if ($iName == $sName && $iPWD == $sPWD){

            $_SESSION["login"] = "student.php";
            setcookie("loginUser", $iName, $date);
            header("Refresh: 0; url=student.php");
            
        }else{

            echo "帳號或密碼輸入錯誤, 將在三秒後回到登入頁面......";
            header("Refresh: 3; url=login.php");
            exit();
        }
    }
?>