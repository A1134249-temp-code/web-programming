<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/sort.php';
require_once __DIR__ . '/../config/database.php';

auth_require_login();
deny_admin_from_project_features();

$user = auth_user();
$userId = (int)($user['id'] ?? 0);
$pdo = getPDO();
$sort = (string)($_GET['sort'] ?? '');

$orderBy = project_list_sort_clause($sort);
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.description
    FROM projects p
    INNER JOIN project_members pm ON pm.project_id = p.id
    WHERE pm.user_id = :uid AND p.is_archived = 0
    ORDER BY {$orderBy}
");
$stmt->execute([':uid' => $userId]);
$projects = $stmt->fetchAll();

layout_start('我的專案', $user);
?>
<?php if (!$projects): ?>
<div class="panel">
  <p>您尚未加入任何專案。請聯絡 PM 將您加入專案。</p>
</div>
<?php else: ?>
<div class="panel">
  <?php render_sort_form('projects.php', project_list_sort_options(), $sort); ?>
</div>
<?php endif; ?>

<?php foreach ($projects as $p): ?>
<div class="panel">
  <h2>#<?= e((string)$p['id']) ?> <?= e((string)$p['name']) ?></h2>
  <p class="muted"><?= e((string)($p['description'] ?? '')) ?></p>
  <p>
    <a href="<?= e(url('bugs.php?project_id=' . (int)$p['id'])) ?>">此專案 Bugs</a>
    |
    <a href="<?= e(url('bug_new.php?project_id=' . (int)$p['id'])) ?>">提報 Bug</a>
  </p>
</div>
<?php endforeach; ?>
<?php layout_end(); ?>
