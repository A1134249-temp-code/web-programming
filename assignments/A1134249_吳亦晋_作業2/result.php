<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_POST)) {
    echo "<p>沒有接收到任何輸入資料，將在 3 秒後進入表單頁面。</p>";
    header("Refresh: 3; url=form.php");
}

echo "<h1>報名結果確認</h1>";
echo "<ul>";

$hasData = false; 

foreach ($_POST as $key => $value) {
    if (!empty($value)) {

        $hasData = true;
        $displayKey = $key;
        $displayValue = $value;

        switch ($key) {
            case 'uName':
                $displayKey = "姓名";
                break;
            case 'uBirth':
                $displayKey = "生日";
                break;
            case 'uGender':
                $displayKey = "性別";
                $displayValue = ($value === 'male') ? "男" : "女";
                break;
            case 'uNumber':
                $displayKey = "聯絡電話";
                break;
            case 'uEmail':
                $displayKey = "電子郵件";
                break;
            case 'uCamp':
                $displayKey = "報名營隊";
                $displayValue = str_replace('camp', '營隊 ', $value);
                break;
            case 'uSession':
                $displayKey = "參加場次";
                $displayValue = str_replace('session', '場次 ', $value);
                break;
            case 'diet':
                $displayKey = "飲食習慣";
                $displayValue = ($value === 'meat') ? "葷食" : "素食";
                break;
            case 'note':
                $displayKey = "備註事項";
                break;
            default:
                break;
        }

        echo "<li><strong>$displayKey:</strong> " . $displayValue . "</li>";
    }
}
echo "</ul>";

if (!$hasData) {
    echo "<p>沒有填寫任何有效資訊。</p>";
}

echo "<br><a href='form.php'>回上一頁修改</a>";
?>