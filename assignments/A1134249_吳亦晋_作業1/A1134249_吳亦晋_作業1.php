<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>夏令營報名表單</title>
</head>
<body>

    <h1>夏令營報名表單</h1>
    <p>請填寫以下資訊完成報名：</p>
    <hr>

    <form action = "" method = "post" name = "information">
        <fieldset>
            <legend>個人資料</legend>
            <label>姓名：</label> <input name = "yourname">
            <label>年齡：</label> <input type = "date" name="yourage">
            <label>性別：</label>
            <input type = "radio" name = "gender" value = "male"> 男
            <input type = "radio" name = "gender" value = "female"> 女
        </fieldset>
        <fieldset>
            <legend>聯絡方式</legend>
            <label>電話：</label> <input type = "tel" name = "yournumber">
            <label>Email：</label> <input type = "email" name = "youremail">
        </fieldset>
        <fieldset>
            <legend>選擇營隊</legend>
            <input type = "radio" name = "camp" value = "campA"> 營隊A <br>
            <input type = "radio" name = "camp" value = "campB"> 營隊B <br>
            <input type = "radio" name = "camp" value = "campC"> 營隊C <br>
        </fieldset>
        <fieldset>
            <legend>參加場次</legend>
            <input type = "radio" name = "session" value = "sessionA"> 場次1 <br>
            <input type = "radio" name = "session" value = "sessionB"> 場次2 <br>
            <input type = "radio" name = "session" value = "sessionC"> 場次3 <br>
        </fieldset>
        <fieldset>
            <legend>其他資訊</legend>
            <label>飲食習慣：</label>
            <input type = "radio" name = "diet" value = "meat"> 葷食
            <input type = "radio" name = "diet" value = "vege"> 素食
            <br>
            <label>備註（如過敏史）：</label><br>
            <textarea name = "note" rows = "4" cols = "30"></textarea>
        </fieldset>
    </form>
</body>
</html>