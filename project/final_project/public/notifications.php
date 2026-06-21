<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/notifications.php';

auth_require_login();
$user = auth_user();
$userId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (csrf_verify(is_string($token) ? $token : null)) {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'read_all') {
            notification_mark_all_read($userId);
            flash_set('success', '已全部標記為已讀。');
        } elseif ($action === 'read_one') {
            notification_mark_read($userId, (int)($_POST['id'] ?? 0));
        }
    }
    $back = (string)($_POST['redirect'] ?? 'notifications.php');
    redirect($back);
}

$pdo = getPDO();
$stmt = $pdo->prepare('
    SELECT id, message, is_read, created_at
    FROM notifications
    WHERE user_id = :uid
    ORDER BY id DESC
    LIMIT 100
');
$stmt->execute([':uid' => $userId]);
$items = $stmt->fetchAll();

layout_start('通知中心', $user);
flash_render();
?>
<div class="panel">
  <h2>站內通知</h2>
  <?php if (notification_unread_count($userId) > 0): ?>
    <form method="post" action="<?= e(url('notifications.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="action" value="read_all" />
      <button type="submit">全部標記已讀</button>
    </form>
  <?php endif; ?>
  <table style="margin-top:12px;">
    <thead><tr><th>時間</th><th>訊息</th><th>狀態</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $n): ?>
      <tr>
        <td><?= e((string)$n['created_at']) ?></td>
        <td><?= e((string)$n['message']) ?></td>
        <td><?= (int)$n['is_read'] === 1 ? '已讀' : '未讀' ?></td>
        <td>
          <?php if ((int)$n['is_read'] === 0): ?>
            <form class="inline-form" method="post" action="<?= e(url('notifications.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="read_one" />
              <input type="hidden" name="id" value="<?= e((string)$n['id']) ?>" />
              <input type="hidden" name="redirect" value="notifications.php" />
              <button type="submit">已讀</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_end(); ?>
