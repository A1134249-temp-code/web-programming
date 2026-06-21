<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/charts.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../config/database.php';

auth_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    $token = $_POST['csrf_token'] ?? null;
    if (csrf_verify(is_string($token) ? $token : null)) {
        auth_logout();
        redirect('login.php');
    }
}

auth_require_login();
auth_require_fresh_password();

$user = auth_user();
$role = (string)($user['role'] ?? '');
$userId = (int)($user['id'] ?? 0);
$pdo = getPDO();

$extraHead = [charts_google_loader_tag(), charts_init_script()];
layout_start('首頁', $user, $extraHead);
flash_render();
?>
<?php if (in_array($role, ['pm', 'member'], true)): ?>
<div class="panel">
  <h2>專案統計圖表</h2>
  <?php
    $projectIds = charts_user_project_ids($pdo, $userId);
    charts_render_project_dashboard($pdo, $projectIds, 'dash');
  ?>
</div>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<div class="panel">
  <h2>系統統計圖表</h2>
  <?php charts_render_admin_dashboard($pdo); ?>
</div>
<?php endif; ?>

<div class="panel">
  <h2>歡迎</h2>
  <p>使用者：<strong><?= e((string)($user['username'] ?? '')) ?></strong>　角色：<strong><?= e($role) ?></strong></p>

  <?php if ($role === 'admin'): ?>
    <p class="muted">系統管理員：管理使用者、專案封存、檢視稽核日誌。不介入專案內 Bug 與成員分配。</p>
    <p>
      <a class="btn" href="<?= e(url('admin.php')) ?>">使用者與專案管理</a>
      <a class="btn" href="<?= e(url('admin_logs.php')) ?>">action_logs 稽核</a>
    </p>
  <?php endif; ?>

  <?php if (in_array($role, ['pm', 'member'], true)): ?>
    <p>
      <a class="btn" href="<?= e(url('projects.php')) ?>">我的專案</a>
      <a class="btn" href="<?= e(url('bugs.php')) ?>">Bug 關鍵字搜尋</a>
      <a class="btn" href="<?= e(url('bug_new.php')) ?>">提報 Bug</a>
    </p>
  <?php endif; ?>

  <?php if ($role === 'pm'): ?>
    <p><a class="btn" href="<?= e(url('pm.php')) ?>">PM：成員管理與進度統計</a></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('index.php')) ?>" class="form-actions">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <input type="hidden" name="action" value="logout" />
    <button type="submit">登出</button>
  </form>
</div>
<?php layout_end(); ?>
