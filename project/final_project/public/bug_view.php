<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/project_service.php';
require_once __DIR__ . '/../config/database.php';

auth_require_login();
deny_admin_from_project_features();

$user = auth_user();
$role = (string)($user['role'] ?? '');
$userId = (int)($user['id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$pdo = getPDO();

function fetch_bug_for_user(PDO $pdo, int $bugId, int $userId): ?array
{
    $stmt = $pdo->prepare('
        SELECT b.*, p.name AS project_name, u.username AS reporter_username
        FROM bugs b
        INNER JOIN projects p ON p.id = b.project_id AND p.is_archived = 0
        INNER JOIN users u ON u.id = b.reporter_id
        INNER JOIN project_members pm ON pm.project_id = b.project_id AND pm.user_id = :uid
        WHERE b.id = :id
        LIMIT 1
    ');
    $stmt->execute([':uid' => $userId, ':id' => $bugId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

$bug = fetch_bug_for_user($pdo, $id, $userId);
if ($bug === null) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗。';
        $messageType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'delete') {
            $isReporter = (int)$bug['reporter_id'] === $userId;
            if (!$isReporter || (string)$bug['status'] !== '待處理') {
                $message = '僅能在「待處理」狀態由回報者自行刪除。';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare('DELETE FROM bugs WHERE id = :id AND reporter_id = :rid AND status = \'待處理\'');
                $stmt->execute([':id' => $id, ':rid' => $userId]);
                if ($stmt->rowCount() > 0) {
                    log_action($userId, "Deleted bug #{$id} (project #" . (int)$bug['project_id'] . ')');
                }
                flash_set('success', 'Bug 已刪除。');
                redirect('bugs.php');
            }
        } elseif ($action === 'in_progress' && $role === 'pm') {
            if (!pm_can_manage_project($pdo, $userId, (int)$bug['project_id'])) {
                http_response_code(403);
                echo 'Forbidden';
                exit;
            }
            $stmt = $pdo->prepare('UPDATE bugs SET status = \'處理中\' WHERE id = :id');
            $stmt->execute([':id' => $id]);
            log_action($userId, "PM set bug #{$id} to 處理中");
            notify_bug_status_change($pdo, $id, '處理中', $userId);
            redirect('bug_view.php?id=' . $id);
        } elseif ($action === 'resolve' && $role === 'pm') {
            if (!pm_can_manage_project($pdo, $userId, (int)$bug['project_id'])) {
                http_response_code(403);
                echo 'Forbidden';
                exit;
            }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE bugs SET status = \'已解決\' WHERE id = :id');
                $stmt->execute([':id' => $id]);
                log_action($userId, "PM resolved bug #{$id}");
                notify_bug_status_change($pdo, $id, '已解決', $userId);
                $pdo->commit();
                redirect('bug_view.php?id=' . $id);
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('[BUG] resolve: ' . $e->getMessage());
                $message = '操作失敗。';
                $messageType = 'error';
            }
        } elseif ($action === 'reject' && $role === 'pm') {
            if (!pm_can_manage_project($pdo, $userId, (int)$bug['project_id'])) {
                http_response_code(403);
                echo 'Forbidden';
                exit;
            }
            if (!in_array((string)$bug['status'], ['待處理', '處理中'], true)) {
                $message = '僅能駁回「待處理」或「處理中」的 Bug。';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare('UPDATE bugs SET status = \'已駁回\' WHERE id = :id');
                $stmt->execute([':id' => $id]);
                log_action($userId, "PM rejected bug #{$id}");
                notify_bug_status_change($pdo, $id, '已駁回', $userId);
                redirect('bug_view.php?id=' . $id);
            }
        }
    }
}

$bug = fetch_bug_for_user($pdo, $id, $userId);
if ($bug === null) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$prismCss = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" />';

layout_start('Bug #' . (string)$bug['id'], $user, [$prismCss]);
flash_render();
?>
<?php if ($message): ?><div class="msg <?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>

<div class="panel">
  <p class="muted">專案：<?= e((string)$bug['project_name']) ?></p>
  <h2>#<?= e((string)$bug['id']) ?> <?= e((string)$bug['title']) ?></h2>
  <p>
    狀態：<strong><?= e((string)$bug['status']) ?></strong>
    | 緊急：<?= e((string)($bug['severity'] ?? '')) ?>
    | 標籤：<?= e((string)($bug['tags'] ?? '')) ?>
  </p>
  <p class="muted">回報者：<?= e((string)$bug['reporter_username']) ?> | <?= e((string)$bug['created_at']) ?></p>
</div>

<div class="panel">
  <h3>描述</h3>
  <?= render_description_safe((string)($bug['description'] ?? '')) ?>
</div>

<?php if (!empty($bug['image_path'])): ?>
<div class="panel">
  <h3>POC 截圖</h3>
  <img src="<?= e(url((string)$bug['image_path'])) ?>" alt="POC" style="max-width:100%; border:1px solid #808080;" />
</div>
<?php endif; ?>

<div class="panel">
  <h3>操作</h3>

  <?php if ((int)$bug['reporter_id'] === $userId && (string)$bug['status'] === '待處理'): ?>
    <form class="inline-form" method="post" action="<?= e(url('bug_view.php?id=' . $id)) ?>" onsubmit="return confirm('確定刪除此 Bug？');">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="action" value="delete" />
      <button type="submit" class="btn-danger">刪除（僅待處理）</button>
    </form>
  <?php elseif ((int)$bug['reporter_id'] === $userId && (string)$bug['status'] === '處理中'): ?>
    <p class="muted">此 Bug 已進入處理中，無法刪除。</p>
  <?php endif; ?>

  <?php if ($role === 'pm' && pm_can_manage_project($pdo, $userId, (int)$bug['project_id'])): ?>
    <?php if ((string)$bug['status'] === '待處理'): ?>
      <form class="inline-form" method="post" action="<?= e(url('bug_view.php?id=' . $id)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="in_progress" />
        <button type="submit">標記為處理中</button>
      </form>
    <?php endif; ?>
    <?php if (in_array((string)$bug['status'], ['待處理', '處理中'], true)): ?>
      <form class="inline-form" method="post" action="<?= e(url('bug_view.php?id=' . $id)) ?>" onsubmit="return confirm('確定駁回此 Bug？');">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="reject" />
        <button type="submit">駁回</button>
      </form>
    <?php endif; ?>
    <?php if ((string)$bug['status'] !== '已解決' && (string)$bug['status'] !== '已駁回'): ?>
      <form class="inline-form" method="post" action="<?= e(url('bug_view.php?id=' . $id)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="resolve" />
        <button type="submit">標記為已解決</button>
      </form>
      <p class="muted">標記為已解決或駁回時會寫入 action_logs 並通知回報者。</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<p><a href="<?= e(url('bugs.php')) ?>">← 返回列表</a></p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
<script>if (window.Prism) { Prism.highlightAll(); }</script>
<?php layout_end(); ?>
