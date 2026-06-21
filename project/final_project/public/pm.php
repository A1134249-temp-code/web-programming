<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/project_service.php';
require_once __DIR__ . '/../includes/sort.php';
require_once __DIR__ . '/../config/database.php';

require_role(['pm']);

$pdo = getPDO();
$user = auth_user();
$userId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_project') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        flash_set('error', 'CSRF 驗證失敗。');
    } else {
        $name = trim((string)($_POST['project_name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($name === '') {
            flash_set('error', '請填寫專案名稱。');
        } else {
            try {
                $rid = pm_submit_project_request($pdo, $userId, $name, $desc !== '' ? $desc : null);
                flash_set('success', '已送出申請 #' . $rid . '，等待 Admin 審核。');
            } catch (Throwable $e) {
                error_log('[PM] request: ' . $e->getMessage());
                flash_set('error', '申請失敗，請稍後再試。');
            }
        }
    }
    redirect('pm.php');
}

$projects = fetch_pm_projects($pdo, $userId);
$projectSort = (string)($_GET['project_sort'] ?? '');
if ($projectSort !== '') {
    usort($projects, static function (array $a, array $b) use ($projectSort): int {
        if ($projectSort === 'name') {
            return strcmp((string)$a['name'], (string)$b['name']);
        }
        return (int)$a['id'] <=> (int)$b['id'];
    });
}

$stmt = $pdo->prepare('
    SELECT id, project_name, status, created_at, reviewed_at
    FROM project_requests
    WHERE pm_id = :uid
    ORDER BY id DESC
    LIMIT 20
');
$stmt->execute([':uid' => $userId]);
$myRequests = $stmt->fetchAll();
$requestSort = (string)($_GET['request_sort'] ?? '');
if ($requestSort === 'name') {
    usort($myRequests, static fn(array $a, array $b): int => strcmp((string)$a['project_name'], (string)$b['project_name']));
}

layout_start('PM 管理', $user);
flash_render();
?>
<div class="panel">
  <h2>申請新專案</h2>
  <p class="muted">送出後由 Admin 審核；核准後會自動建立專案並將您加入為負責 PM。</p>
  <form method="post" action="<?= e(url('pm.php')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <input type="hidden" name="action" value="request_project" />
    <label>專案名稱</label>
    <input name="project_name" required maxlength="120" />
    <label>描述</label>
    <textarea name="description"></textarea>
    <div class="form-actions"><button type="submit">送出申請</button></div>
  </form>
</div>

<div class="panel">
  <h2>我的申請紀錄</h2>
  <?php render_sort_form('pm.php', ['' => '預設（ID）', 'name' => '專案名稱'], $requestSort, ['project_sort' => $projectSort], 'request_sort'); ?>
  <table>
    <thead><tr><th>ID</th><th>名稱</th><th>狀態</th><th>申請時間</th></tr></thead>
    <tbody>
    <?php foreach ($myRequests as $r): ?>
      <tr>
        <td><?= e((string)$r['id']) ?></td>
        <td><?= e((string)$r['project_name']) ?></td>
        <td><?= e((string)$r['status']) ?></td>
        <td><?= e((string)$r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>我負責的專案</h2>
  <?php render_sort_form('pm.php', project_list_sort_options(), $projectSort, [], 'project_sort'); ?>
  <?php if (!$projects): ?>
    <p>尚無專案。請向 Admin 申請新專案，或由 Admin 建立專案並指派您為 PM。</p>
  <?php endif; ?>
  <table>
    <thead><tr><th>ID</th><th>名稱</th><th>進度</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
      <?php $stats = fetch_project_bug_stats($pdo, (int)$p['id']); ?>
      <tr>
        <td><?= e((string)$p['id']) ?></td>
        <td><?= e((string)$p['name']) ?></td>
        <td class="muted">待 <?= e((string)$stats['待處理']) ?> / 中 <?= e((string)$stats['處理中']) ?> / 完 <?= e((string)$stats['已解決']) ?> / 駁 <?= e((string)$stats['已駁回']) ?></td>
        <td><a href="<?= e(url('pm_project.php?id=' . (int)$p['id'])) ?>">成員管理</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_end(); ?>
