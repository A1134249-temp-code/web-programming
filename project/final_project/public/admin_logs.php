<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pdo = getPDO();
$user = auth_user();

$stmt = $pdo->query('
    SELECT al.id, al.action, al.created_at, u.username
    FROM action_logs al
    INNER JOIN users u ON u.id = al.user_id
    ORDER BY al.id DESC
    LIMIT 500
');
$logs = $stmt->fetchAll();

layout_start('稽核日誌', $user);
?>
<div class="panel">
  <h2>action_logs（最近 500 筆）</h2>
  <p><a class="btn" href="<?= e(url('export_admin_logs.php')) ?>">匯出系統操作日誌 (Excel)</a></p>
  <table>
    <thead>
      <tr><th>ID</th><th>時間</th><th>使用者</th><th>動作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= e((string)$log['id']) ?></td>
        <td><?= e((string)$log['created_at']) ?></td>
        <td><?= e((string)$log['username']) ?></td>
        <td><?= e((string)$log['action']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_end(); ?>
