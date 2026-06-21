<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/password_reset.php';

auth_init();
if (auth_user() !== null) {
    redirect('index.php');
}

$rawToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetRecord = $rawToken !== '' ? password_reset_validate_token($rawToken) : null;

$message = null;
$messageType = 'error';

if ($resetRecord === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    layout_start('重設密碼', null);
    echo '<div class="panel">';
    echo '<p>連結無效或已過期。</p>';
    echo '<p class="muted">若從 Gmail 開啟仍失敗，請複製信件中完整 URL 到瀏覽器，或重新申請重設連結。</p>';
    echo '<p><a href="' . e(url('forgot_password.php')) . '">重新申請</a></p>';
    echo '</div>';
    layout_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($csrf) ? $csrf : null)) {
        $message = 'CSRF 驗證失敗。';
    } else {
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $message = '兩次密碼不一致。';
        } else {
            $res = password_reset_apply($rawToken, $p1);
            if (($res['ok'] ?? false) === true) {
                flash_set('success', $res['message'] ?? '密碼已更新。');
                redirect('login.php');
            }
            $message = $res['message'] ?? '重設失敗。';
            $resetRecord = password_reset_validate_token($rawToken);
        }
    }
}

$formAction = url('reset_password.php') . '?token=' . rawurlencode($rawToken);

layout_start('重設密碼', null);
?>
<div class="auth-box">
  <div class="panel">
    <h2>設定新密碼</h2>
    <?php if ($message): ?><div class="msg error"><?= e($message) ?></div><?php endif; ?>
    <?php if ($resetRecord !== null): ?>
    <form method="post" action="<?= e($formAction) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="token" value="<?= e($rawToken) ?>" />
      <label>新密碼（至少 8 碼）</label>
      <input name="password" type="password" minlength="8" required />
      <label>確認密碼</label>
      <input name="password2" type="password" minlength="8" required />
      <div class="form-actions"><button type="submit">更新密碼</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php layout_end(); ?>
