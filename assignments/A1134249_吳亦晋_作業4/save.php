<?php

    session_start();
    require 'config.php';

    //Step 1 連接資料庫
    $link = @mysqli_connect(
        $db_host,       //MySQL主機名稱
        $db_user,       //資料庫使用者名稱
        $db_pass,       //密碼
        $db_name);      //預設資料庫名稱

    //Step 2 撰寫語法
    $createTable = "CREATE TABLE IF NOT EXISTS emails (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE)";

    //Step 3 執行語法
    mysqli_query($link, $createTable);


    if (!empty($_POST['new_emails'])){

        $emailString = str_replace(["\r\n", "\r", "\n"], ',', $_POST['new_emails']);
        $emailArray = explode(',', $emailString);
        
        foreach ($emailArray as $email){

            $cleanEmail = trim($email);

            if (!empty($cleanEmail) && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)){

                $safeEmail = mysqli_real_escape_string($link, $cleanEmail);

                mysqli_query($link, "INSERT IGNORE INTO emails (email) VALUES ('$cleanEmail')");

            }

        }

    }

    $uploadedFiles = [];

    if (!empty($_FILES['attachments']['name'][0])){

        $uploadDir = 'uploads/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach ($_FILES['attachments']['name'] as $key => $name){

            $tmpName = $_FILES['attachments']['tmp_name'][$key];
            $dest = $uploadDir . basename($name);

            if (move_uploaded_file($tmpName, $dest)){

                $uploadedFiles[] = $dest;

            }

        }

    }

    $_SESSION['mail_settings'] = [
        'mode'         => $_POST['mode'],
        'random_count' => (int)$_POST['random_count'],
        'subject'      => $_POST['subject'],
        'body'         => $_POST['body'],
        'interval_min' => (int)$_POST['interval_min'],
        'interval_max' => (int)$_POST['interval_max'],
        'attachments'  => $uploadedFiles
    ];

    mysqli_close($link);
    header("Location: process.php");
    exit;

?>