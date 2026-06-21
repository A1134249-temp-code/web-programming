<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../config/database.php';

// 只有 admin 可以進入此頁面
require_role(['admin']);

$pdo = getPDO();
$user = auth_user();
$adminId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        flash_set('error', 'CSRF 驗證失敗。');
        redirect('admin_reset.php');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'factory_reset') {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("TRUNCATE TABLE bugs;");
            $pdo->exec("TRUNCATE TABLE projects;");
            $pdo->exec("TRUNCATE TABLE project_members;");
            $pdo->exec("TRUNCATE TABLE project_requests;");
            $pdo->exec("TRUNCATE TABLE action_logs;");
            $pdo->exec("TRUNCATE TABLE notifications;");
            $pdo->exec("TRUNCATE TABLE password_resets;");
            $pdo->exec("TRUNCATE TABLE users;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $pdo->exec("
                INSERT INTO system_settings (setting_key, setting_value) VALUES
                ('password_expiry_days', '90'),
                ('allow_registration', '1')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
            ");

            $testAdmin = [
                'username' => 'testAdmin',
                'email'    => 'testadmin@example.com',
                'password' => ''
            ];

            $testAdminPath = __DIR__ . '/../config/test_admin.php';
            if (is_file($testAdminPath)) {
                $loaded = require $testAdminPath;
                if (is_array($loaded)) {
                    $testAdmin = array_merge($testAdmin, $loaded);
                }
            }

            if ($testAdmin['username'] !== '' && $testAdmin['email'] !== '' && $testAdmin['password'] !== '') {
                $hash = password_hash($testAdmin['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('
                    INSERT INTO users (username, email, password, role)
                    VALUES (:username, :email, :password, \'admin\')
                ');
                $stmt->execute([
                    ':username' => $testAdmin['username'],
                    ':email'    => $testAdmin['email'],
                    ':password' => $hash,
                ]);
            }
            
            auth_logout(); 
            auth_init();
            flash_set('success', '系統已徹底清空！請重新建立管理員帳號。');
            redirect('setup_admin.php'); // 直接引導去新頁面

        } catch (Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            error_log('[FACTORY_RESET] ' . $e->getMessage());
            flash_set('error', '重設失敗，請查看系統日誌。');
        }
        
        redirect('admin_reset.php');
    }
}

layout_start('系統重設 (危險操作)', $user);
flash_render();
?>
<div class="panel" style="border: 1px solid #ef4444;">
  <h2 style="color: #ef4444;">危險操作區：系統出廠重設</h2>
  <p class="muted">
    執行此操作將會：<br>
    1. 清空所有專案、Bug、申請單與系統日誌。<br>
    2. 刪除所有使用者帳號，並將 <strong>您 (目前登入的 Admin)</strong> 強制登出。<br>
    注意：此操作無法復原，請確認後再執行！
  </p>

  <form method="post" action="<?= e(url('admin_reset.php')) ?>" onsubmit="return confirm('這將會清空所有專案、Bug 與所有使用者資料，確定要執行嗎？');">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <input type="hidden" name="action" value="factory_reset" />
    
    <button type="submit" style="background-color: #ef4444; color: white;">執行系統初始化</button>
    <a class="btn" href="<?= e(url('admin.php')) ?>" style="margin-left: 12px;">取消並返回</a>
  </form>
</div>
<?php layout_end(); ?>