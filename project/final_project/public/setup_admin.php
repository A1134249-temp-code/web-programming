<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount > 1) {
    auth_init();
    flash_set('error', '系統已初始化，禁止重複建立管理員。');
    redirect('login.php');
}

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗，請重新整理頁面後再試。';
        $messageType = 'error';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($username === '' || $email === '' || mb_strlen($password) < 10) {
            $message = '請填寫所有欄位，且密碼至少需要 10 碼。';
            $messageType = 'error';
        } elseif ($password !== $passwordConfirm) {
            $message = '兩次輸入的密碼不一致，請重新輸入。';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email 格式不正確。';
            $messageType = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('
                    INSERT INTO users (username, email, password, role)
                    VALUES (:username, :email, :password, \'admin\')
                ');
                $stmt->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':password' => $hash,
                ]);

                // 建立成功後，清除舊狀態並引導去登入
                auth_logout();
                auth_init();
                flash_set('success', '創始管理員帳號建立成功！請使用新帳號登入。');
                redirect('login.php');

            } catch (Throwable $e) {
                error_log('[SETUP_ADMIN] ' . $e->getMessage());
                $message = '建立失敗，帳號或 Email 可能已被佔用。';
                $messageType = 'error';
            }
        }
    }
}

layout_start('建立創始管理員', null);
?>
<div class="auth-box">
  <div class="panel">
    <h2>建立創始管理員</h2>

    <p class="muted" style="margin-bottom: 16px;">
      偵測到系統目前無任何管理員。<br>請設定您的第一個 admin 帳號：
    </p>

    <?php if (is_string($message) && $message !== ''): ?>
      <div class="msg <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('setup_admin.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      
      <label for="username">管理員帳號</label>
      <input id="username" name="username" type="text" autocomplete="username" required />
      
      <label for="email">管理員 Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required />
      
      <label for="password">密碼（至少 10 碼）</label>
      <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required />
      
      <label for="password_confirm">確認密碼</label>
      <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="10" required />
      
      <div class="form-actions">
        <button type="submit">建立並初始化系統</button>
      </div>
    </form>
  </div>
</div>
<?php layout_end(); ?>