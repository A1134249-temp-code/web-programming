<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/sort.php';
require_once __DIR__ . '/../config/database.php';

auth_require_login();
deny_admin_from_project_features();

$user = auth_user();
$userId = (int)($user['id'] ?? 0);
$pdo = getPDO();

$q = trim((string)($_GET['q'] ?? ''));
$projectId = (int)($_GET['project_id'] ?? 0);
$sort = (string)($_GET['sort'] ?? '');

$sql = '
    SELECT b.id, b.project_id, b.title, b.status, b.severity, b.tags, b.created_at,
           p.name AS project_name, u.username AS reporter_username
    FROM bugs b
    INNER JOIN projects p ON p.id = b.project_id AND p.is_archived = 0
    INNER JOIN users u ON u.id = b.reporter_id
    INNER JOIN project_members pm ON pm.project_id = b.project_id AND pm.user_id = :uid
';
$params = [':uid' => $userId];
$where = [];

if ($projectId > 0) {
    $where[] = 'b.project_id = :pid';
    $params[':pid'] = $projectId;
}

if ($q !== '') {
    $where[] = '(b.title LIKE :q1 OR b.description LIKE :q2 OR b.tags LIKE :q3)';
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY ' . bug_list_sort_clause($sort) . ' LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hiddenSortFields = [];
if ($q !== '') {
    $hiddenSortFields['q'] = $q;
}
if ($projectId > 0) {
    $hiddenSortFields['project_id'] = (string)$projectId;
}

layout_start('Bug 搜尋', $user);
?>
<div class="panel">
  <form method="get" action="<?= e(url('bugs.php')) ?>">
    <label>關鍵字（title / description / tags）</label>
    <input name="q" value="<?= e($q) ?>" placeholder="例如：XSS、SQL、timeout" />
    <?php if ($projectId > 0): ?>
      <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>" />
    <?php endif; ?>
    <?php if ($sort !== ''): ?>
      <input type="hidden" name="sort" value="<?= e($sort) ?>" />
    <?php endif; ?>
    <div class="form-actions"><button type="submit">搜尋</button></div>
  </form>
  <p class="muted">僅搜尋您所屬專案內的 Bug，最多 200 筆。</p>
</div>

<div class="panel">
  <?php render_sort_form('bugs.php', bug_list_sort_options(), $sort, $hiddenSortFields); ?>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>專案</th><th>標題</th><th>狀態</th><th>緊急程度</th><th>回報者</th><th>建立時間</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['id']) ?></td>
        <td><?= e((string)$r['project_name']) ?></td>
        <td><a href="<?= e(url('bug_view.php?id=' . (int)$r['id'])) ?>"><?= e((string)$r['title']) ?></a></td>
        <td><?= e((string)$r['status']) ?></td>
        <td><?= e((string)($r['severity'] ?? '')) ?></td>
        <td><?= e((string)$r['reporter_username']) ?></td>
        <td><?= e((string)$r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_end(); ?>
