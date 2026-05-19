<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>郵件寄送系統</title>
</head>
    <body>
        <h1>郵件寄送系統設定</h1>
        
        <form action="save.php" method="POST" enctype="multipart/form-data">
            
            <h3>1. 新增收件者到資料庫</h3>
            <label>手動輸入 Gmail (可輸入多筆，使用逗號或換行分隔): <br>
                <textarea name="new_emails" rows="4" cols="50" placeholder="a@gmail.com, b@gmail.com&#10;c@gmail.com"></textarea>
            </label>

            <h3>2. 寄送對象設定</h3>
            <label>寄送模式: 
                <select name="mode">
                    <option value="all">全站寄送</option>
                    <option value="random">隨機寄送</option>
                </select>
            </label><br><br>
            <label>若選隨機，請輸入筆數: <input type="number" name="random_count" value="3"></label>
            <hr>

            <h3>3. 郵件內容與附件</h3>
            <label>主旨: <input type="text" name="subject" required></label><br><br>
            <label>內容: <br><textarea name="body" rows="5" cols="40" required></textarea></label><br><br>
            <label>附件 (可多選): <input type="file" name="attachments[]" multiple></label>
            <hr>

            <h3>4. 寄送間隔設定 (秒)</h3>
            <label>最短間隔: <input type="number" name="interval_min" value="2" min="1"></label>
            <label>最長間隔: <input type="number" name="interval_max" value="5" min="1"></label>
            <br><br>

            <button type="submit">儲存並開始寄送</button>
        </form>
    </body>
</html>