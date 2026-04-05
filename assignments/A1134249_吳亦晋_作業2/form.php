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

    <form action = "result.php" method = "post" name = "information">
        <fieldset>
            <legend>個人資料</legend>
            <label for="name">姓名：</label> 
            <input type="text" name="uName" required>
            
            <label for="birth">生日：</label> 
            <input type="date" name="uBirth">
            
            <label>性別:</label>
            <input type="radio" name="uGender" value="male"> <label for="male">男</label>
            <input type="radio" name="uGender" value="female"> <label for="female">女</label>
        </fieldset>

        <fieldset>
            <legend>聯絡方式</legend>
            <label for="tel">電話:</label> <input type="tel" name="uNumber">
            <label for="email">Email:</label> <input type="email" name="uEmail">
        </fieldset>

        <fieldset>
            <legend>選擇營隊</legend>
            <input type="radio" name="uCamp" value="campA"> <label for="cA">營隊A</label><br>
            <input type="radio" name="uCamp" value="campB"> <label for="cB">營隊B</label><br>
            <input type="radio" name="uCamp" value="campC"> <label for="cC">營隊C</label>
        </fieldset>

        <fieldset>
            <legend>參加場次</legend>
            <input type="radio" name="uSession" value="sessionA"> <label for="s1">場次1</label><br>
            <input type="radio" name="uSession" value="sessionB"> <label for="s2">場次2</label><br>
            <input type="radio" name="uSession" value="sessionC"> <label for="s3">場次3</label>
        </fieldset>

        <fieldset>
            <legend>其他資訊</legend>
            <label>飲食習慣：</label>
            <input type="radio" name="diet" value="meat"> <label for="meat">葷食</label>
            <input type="radio" name="diet" value="vege"> <label for="vege">素食</label>
            <br><br>
            <label for="note">備註（如過敏史）：</label><br>
            <textarea name="note" rows="4" cols="30"></textarea>
        </fieldset>

        <button type="submit">送出報名</button>
        <button type="reset">重設表單</button>
    </form>
</body>
</html>