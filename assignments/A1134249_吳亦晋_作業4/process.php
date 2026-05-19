<?php

    session_start();
    require 'config.php';

    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    ob_implicit_flush(true);
    ob_end_flush();

    if (!isset($_SESSION['mail_settings'])){

        die("找不到設定資料，請回到首頁重新設定。<br><a href='index.php'>回到首頁</a>");

    }

    $settings = $_SESSION['mail_settings'];
    echo "<h2>系統正在處理您的郵件...</h2>";
    echo "<div style='background:#f4f4f4; padding:15px; border:1px solid #ccc; max-height:400px; overflow-y:auto;'>";

    //Step 1 連接資料庫
    $link = @mysqli_connect(
        $db_host,       //MySQL主機名稱
        $db_user,       //資料庫使用者名稱
        $db_pass,       //密碼
        $db_name);      //預設資料庫名稱

    if ($settings['mode'] === 'random' && $settings['random_count'] > 0){

        $count = (int)$settings['random_count'];
        $sql = "SELECT email FROM emails ORDER BY RAND() LIMIT $count";

    }else{

        $sql = "SELECT email FROM emails";

    }

    $result = mysqli_query($link, $sql);
    $targets = [];

    while ($row = mysqli_fetch_assoc($result)){

        $targets[] = $row['email'];

    }

    mysqli_free_result($result);
    mysqli_close($link);

    if (count($targets) === 0){

        die("錯誤：找不到任何收件者名單。<br><a href='index.php'>回到首頁</a>");

    }

    $mail = new PHPMailer(true);

    try{

        $mail->isSMTP();
        $mail->Timeout   = 10;
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom($smtp_user, '測試寄件系統');
        
        $mail->isHTML(true);
        $mail->Subject    = $settings['subject'];
        $mail->Body       = nl2br(htmlspecialchars($settings['body']));

        if (!empty($settings['attachments'])){

            foreach ($settings['attachments'] as $filePath){

                $mail->addAttachment($filePath);

            }

        }

    }catch (Exception $e){

        die("郵件伺服器設定錯誤: {$mail->ErrorInfo}");

    }

    $total = count($targets);

    foreach ($targets as $index => $email){

        $current = $index + 1;
        
        try{

            $mail->clearAddresses();
            $mail->addAddress($email);
            $mail->send();
            
            echo "[{$current}/{$total}] 成功寄送至：{$email}<br>";

        }catch (Exception $e){

            echo "[{$current}/{$total}] <span style='color:red;'>失敗：{$email} (錯誤: {$mail->ErrorInfo})</span><br>";

        }
        
        flush();

        if ($current < $total) {

            $min = !empty($settings['interval_min']) ? (int)$settings['interval_min'] : 1;
            $max = !empty($settings['interval_max']) ? (int)$settings['interval_max'] : 3;
            
            if ($max < $min) { $max = $min; }

            $sleepTime = rand($min, $max);
            
            echo "等待 {$sleepTime} 秒...<br>";
            flush();
            sleep($sleepTime);

        }

    }

    echo "</div>";
    echo "<h3>所有郵件處理完畢！</h3>";

    if (!empty($settings['attachments'])){

        foreach ($settings['attachments'] as $filePath){

            if (file_exists($filePath)){

                unlink($filePath);

            }
        }
    }

    unset($_SESSION['mail_settings']);

    echo "<br><button onclick=\"window.location.href='index.php'\" style='padding:10px 20px; font-size:16px;'>回到首頁</button>";

?>