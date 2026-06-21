<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/profile_service.php';
require_once __DIR__ . '/../config/database.php';

auth_require_login();

$user = auth_user();
$userId = (int)($user['id'] ?? 0);
$pdo = getPDO();
$required = isset($_GET['required']);
$forcePassword = $required || auth_password_expired($userId);

$stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$row = $stmt->fetch();
$currentEmail = is_array($row) ? (string)($row['email'] ?? '') : (string)($user['email'] ?? '');

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗。';
        $messageType = 'error';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newEmail = (string)($_POST['email'] ?? '');
        $newPassword = trim((string)($_POST['new_password'] ?? ''));
        $newPassword2 = trim((string)($_POST['new_password2'] ?? ''));

        if ($forcePassword && $newPassword === '') {
            $message = '密碼已過期，請設定新密碼。';
            $messageType = 'error';
        } elseif ($newPassword !== '' && $newPassword !== $newPassword2) {
            $message = '兩次新密碼不一致。';
            $messageType = 'error';
        } else {
            $res = profile_update(
                $userId,
                $currentPassword,
                $newEmail,
                $newPassword !== '' ? $newPassword : null
            );
            if (($res['ok'] ?? false) === true) {
                if (!empty($res['logout'])) {
                    auth_logout();
                    flash_set('success', (string)($res['message'] ?? '密碼已更新，請重新登入。'));
                    redirect('login.php');
                }
                profile_sync_session_email($userId, trim(mb_strtolower($newEmail)));
                flash_set('success', (string)($res['message'] ?? '已更新。'));
                redirect('profile.php');
            }
            $message = (string)($res['message'] ?? '更新失敗。');
            $messageType = 'error';
        }
    }
}

layout_start('個人資料', $user);
flash_render();
?>
<div class="panel">
  <h2>個人資料維護</h2>
  <?php if ($forcePassword): ?>
    <div class="msg error">您的密碼已超過有效期限（<?= e((string)password_expiry_days()) ?> 天），請先修改密碼後才能使用其他功能。</div>
  <?php endif; ?>
  <?php if ($message): ?><div class="msg <?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>

  <p class="muted">帳號：<strong><?= e((string)($user['username'] ?? '')) ?></strong>　角色：<strong><?= e((string)($user['role'] ?? '')) ?></strong></p>

  <form method="post" action="<?= e(url('profile.php' . ($required ? '?required=1' : ''))) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($currentEmail) ?>" required />

    <label for="current_password">目前密碼（必填，以驗證身分）</label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required />

    <label for="new_password">新密碼<?= $forcePassword ? '（必填）' : '（選填，至少 8 碼）' ?></label>
    <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" <?= $forcePassword ? 'required' : '' ?> />

    <label for="new_password2">確認新密碼</label>
    <input id="new_password2" name="new_password2" type="password" minlength="8" autocomplete="new-password" <?= $forcePassword ? 'required' : '' ?> />

    <div class="form-actions"><button type="submit">儲存變更</button></div>
  </form>
</div>
<?php layout_end(); ?>
