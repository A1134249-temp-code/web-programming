<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../includes/charts.php';
require_once __DIR__ . '/../includes/sort.php';
require_once __DIR__ . '/../config/database.php';

require_role(['pm']);

$pdo = getPDO();
$user = auth_user();
$pmId = (int)($user['id'] ?? 0);
$projectId = (int)($_GET['id'] ?? 0);

if ($projectId <= 0 || !pm_can_manage_project($pdo, $pmId, $projectId)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, description, pm_user_id FROM projects WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$leadPmId = (int)($project['pm_user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        flash_set('error', 'CSRF 驗證失敗。');
        redirect('pm_project.php?id=' . $projectId);
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_member') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0 && $uid !== $pmId) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (:pid, :uid)');
            $stmt->execute([':pid' => $projectId, ':uid' => $uid]);
            log_action($pmId, "PM added user #{$uid} to project #{$projectId}");
            flash_set('success', '已加入成員。');
        }
    } elseif ($action === 'remove_member') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $leadPmId) {
            flash_set('error', '無法移除專案負責 PM。');
        } elseif ($uid > 0 && $uid !== $pmId) {
            $stmt = $pdo->prepare('DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid');
            $stmt->execute([':pid' => $projectId, ':uid' => $uid]);
            log_action($pmId, "PM removed user #{$uid} from project #{$projectId}");
            flash_set('success', '已移除成員。');
        }
    }

    redirect('pm_project.php?id=' . $projectId);
}

$stats = fetch_project_bug_stats($pdo, $projectId);

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.role
    FROM project_members pm
    INNER JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = :pid
    ORDER BY u.username ASC
');
$stmt->execute([':pid' => $projectId]);
$members = $stmt->fetchAll();

$candidateUsers = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('member','pm') ORDER BY username ASC")->fetchAll();

$memberSort = (string)($_GET['sort'] ?? '');
if ($memberSort === 'role') {
    usort($members, static fn(array $a, array $b): int => strcmp((string)$a['role'], (string)$b['role']) ?: strcmp((string)$a['username'], (string)$b['username']));
}

$extraHead = [charts_google_loader_tag(), charts_init_script()];
layout_start('PM 專案：' . (string)$project['name'], $user, $extraHead);
flash_render();
?>
<div class="panel">
  <h2>專案統計圖表</h2>
  <?php charts_render_project_dashboard($pdo, [$projectId], 'proj' . $projectId); ?>
</div>

<div class="panel">
  <h2><?= e((string)$project['name']) ?></h2>
  <p class="muted"><?= e((string)($project['description'] ?? '')) ?></p>
  <p>
    <a class="btn" href="<?= e(url('export_project_bugs.php?project_id=' . $projectId)) ?>">匯出當前專案 Bug 完整清單 (Excel)</a>
    <a class="btn" href="<?= e(url('export_project_members.php?project_id=' . $projectId)) ?>">匯出專案內使用者名單 (Excel)</a>
  </p>
  <div class="stats-grid">
    <div class="stat-box"><strong><?= e((string)$stats['待處理']) ?></strong>待處理</div>
    <div class="stat-box"><strong><?= e((string)$stats['處理中']) ?></strong>處理中</div>
    <div class="stat-box"><strong><?= e((string)$stats['已解決']) ?></strong>已解決</div>
    <div class="stat-box"><strong><?= e((string)$stats['total']) ?></strong>總計</div>
  </div>
  <p style="margin-top:12px;"><a href="<?= e(url('bugs.php?project_id=' . $projectId)) ?>">查看此專案 Bugs</a></p>
</div>

<div class="panel">
  <h2>專案成員</h2>
  <?php render_sort_form('pm_project.php', ['' => '預設（帳號）', 'role' => '角色'], $memberSort, ['id' => (string)$projectId], 'sort'); ?>
  <table>
    <thead><tr><th>帳號</th><th>角色</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($members as $m): ?>
      <tr>
        <td><?= e((string)$m['username']) ?><?= (int)$m['id'] === $leadPmId ? ' <span class="muted">（負責 PM）</span>' : '' ?></td>
        <td><?= e((string)$m['role']) ?></td>
        <td>
          <?php if ((int)$m['id'] === $leadPmId): ?>
            <span class="muted">—</span>
          <?php elseif ((int)$m['id'] !== $pmId): ?>
            <form class="inline-form" method="post" action="<?= e(url('pm_project.php?id=' . $projectId)) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="remove_member" />
              <input type="hidden" name="user_id" value="<?= e((string)$m['id']) ?>" />
              <button type="submit">踢出</button>
            </form>
          <?php else: ?>
            <span class="muted">（您）</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>加入成員</h2>
  <form method="post" action="<?= e(url('pm_project.php?id=' . $projectId)) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <input type="hidden" name="action" value="add_member" />
    <label>選擇使用者</label>
    <select name="user_id" required>
      <option value="">— 選擇 —</option>
      <?php foreach ($candidateUsers as $cu): ?>
        <option value="<?= e((string)$cu['id']) ?>"><?= e((string)$cu['username']) ?> (<?= e((string)$cu['role']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <div class="form-actions"><button type="submit">加入專案</button></div>
  </form>
</div>

<p><a href="<?= e(url('pm.php')) ?>">← 返回 PM 列表</a></p>
<?php layout_end(); ?>
