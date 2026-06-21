<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

auth_init();

if (auth_user() !== null) {
    redirect('index.php');
}

if (!registration_allowed()) {
    flash_set('error', '目前不開放註冊。');
    redirect('login.php');
}

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗，請重試。';
        $messageType = 'error';
    } else {
        try {
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($p1 !== $p2) {
                $message = '兩次密碼不一致。';
                $messageType = 'error';
            } else {
            $res = auth_register(
                (string)($_POST['username'] ?? ''),
                (string)($_POST['email'] ?? ''),
                $p1
            );
            if (($res['ok'] ?? false) === true) {
                redirect('login.php?registered=1');
            }
            $message = $res['message'] ?? '註冊失敗。';
            $messageType = 'error';
            }
        } catch (Throwable $e) {
            error_log('[AUTH] ' . $e->getMessage());
            $message = '系統忙碌中，請稍後再試。';
            $messageType = 'error';
        }
    }
}

layout_start('註冊', null);
?>
<div class="auth-box">
  <div class="panel">
    <h2>註冊（member）</h2>
    <p class="muted">註冊後角色固定為 member；admin / pm 由系統管理員指派。</p>

    <?php if (is_string($message) && $message !== ''): ?>
      <div class="msg <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <label for="username">帳號</label>
      <input id="username" name="username" type="text" autocomplete="username" required />
      <label for="email">Email（必填，用於通知與重設密碼）</label>
      <input id="email" name="email" type="email" autocomplete="email" required />
      <label for="password">密碼（至少 8 碼）</label>
      <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required />
      <label for="password2">確認密碼</label>
      <input id="password2" name="password2" type="password" minlength="8" autocomplete="new-password" required />
      <div class="form-actions">
        <button type="submit">建立帳號</button>
      </div>
    </form>

    <p class="muted" style="margin-top:12px;">
      已有帳號？<a href="<?= e(url('login.php')) ?>">返回登入</a>
    </p>
  </div>
</div>
<?php layout_end(); ?>
