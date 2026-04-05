<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>登入頁面</title>
</head>
<body>

    <h1>系統登入</h1>
    <form action = "check.php" method = "post" name = "login">
        帳號：<input type="text" name="username" required><br><br>
        密碼：<input type="password" name="password" required><br><br>
        <button type="submit">登入</button>
    </form>
</body>
</html>