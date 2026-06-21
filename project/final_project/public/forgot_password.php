<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/password_reset.php';

auth_init();
if (auth_user() !== null) {
    redirect('index.php');
}

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗。';
        $messageType = 'error';
    } else {
        $res = password_reset_request((string)($_POST['email'] ?? ''));
        $message = $res['message'] ?? '已處理。';
        $messageType = 'success';
    }
}

layout_start('忘記密碼', null);
?>
<div class="auth-box">
  <div class="panel">
    <h2>忘記密碼</h2>
    <p class="muted">輸入註冊 Email，系統將寄送重設連結（本地 XAMPP 若未設定 mail，請查看 error_log 或改用資料庫 token 測試）。</p>

    <?php if ($message): ?>
      <div class="msg <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('forgot_password.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required autocomplete="email" />
      <div class="form-actions"><button type="submit">寄送重設連結</button></div>
    </form>

    <p class="muted" style="margin-top:12px;"><a href="<?= e(url('login.php')) ?>">返回登入</a></p>
  </div>
</div>
<?php layout_end(); ?>
