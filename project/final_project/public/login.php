<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

auth_init();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (auth_user() !== null) {
    redirect('index.php');
}

$message = null;
$messageType = 'info';
$showRegisterLink = registration_allowed();

if (isset($_GET['registered'])) {
    $message = '註冊成功，請登入。';
    $messageType = 'success';
}

if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if (is_array($flash)) {
        $message = (string)($flash['message'] ?? $message);
        $messageType = (string)($flash['type'] ?? $messageType);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗，請重試。';
        $messageType = 'error';
    } else {
        try {
            $inputUsername = trim((string)($_POST['username'] ?? ''));
            $res = auth_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            $message = $res['message'] ?? null;
            $messageType = ($res['ok'] ?? false) ? 'success' : 'error';
            if (($res['ok'] ?? false) === true) {
                if ($inputUsername === 'testAdmin') {
                    flash_set('info', '已使用測試帳號登入，請執行系統重設。');
                    redirect('admin_reset.php');
                }
                $userId = (int)($res['user_id'] ?? 0);
                if ($userId > 0 && auth_password_expired($userId)) {
                    flash_set('error', '您的密碼已超過有效期限，請立即修改。');
                    redirect('profile.php?required=1');
                }
                redirect('index.php');
            }
        } catch (Throwable $e) {
            error_log('[AUTH] ' . $e->getMessage());
            $message = '系統忙碌中，請稍後再試。';
            $messageType = 'error';
        }
    }
}

layout_start('登入', null);
?>
<div class="auth-box">
  <div class="panel">
    <h2>登入</h2>

    <?php if (is_string($message) && $message !== ''): ?>
      <div class="msg <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <label for="username">帳號</label>
      <input id="username" name="username" type="text" autocomplete="username" required />
      <label for="password">密碼</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required />
      <div class="form-actions">
        <button type="submit">登入</button>
      </div>
    </form>

    <p class="muted" style="margin-top:12px;">
      <a href="<?= e(url('forgot_password.php')) ?>">忘記密碼</a>
      <?php if ($showRegisterLink): ?>
        · 還沒有帳號？<a href="<?= e(url('register.php')) ?>">前往註冊</a>
      <?php endif; ?>
    </p>
  </div>
</div>
<?php layout_end(); ?>
