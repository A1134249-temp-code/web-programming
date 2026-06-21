<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/bug_options.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../config/database.php';

auth_require_login();
deny_admin_from_project_features();

$user = auth_user();
$role = (string)($user['role'] ?? '');
if (!in_array($role, ['member', 'pm'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$userId = (int)($user['id'] ?? 0);
$pdo = getPDO();
$projects = fetch_member_projects($pdo, $userId);
$preselect = (int)($_GET['project_id'] ?? 0);

$severities = bug_severity_list();
$tagPresets = bug_tag_presets();

$message = null;
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $message = 'CSRF 驗證失敗。';
    } else {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $severity = bug_validate_severity((string)($_POST['severity'] ?? ''));
        $tags = bug_resolve_tag(
            (string)($_POST['tag_preset'] ?? ''),
            (string)($_POST['tags_custom'] ?? '')
        );

        if ($projectId <= 0 || $title === '' || mb_strlen($title) > 200) {
            $message = '請選擇專案並輸入標題。';
        } elseif ($severity === null) {
            $message = '請選擇有效的緊急程度。';
        } elseif (!is_project_member($pdo, $userId, $projectId)) {
            $message = '您不屬於此專案。';
        } else {
            $imagePath = null;
            if (!empty($_FILES['image']) && is_array($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = upload_image($_FILES['image']);
                if (($up['ok'] ?? false) === true) {
                    $imagePath = (string)$up['path'];
                } else {
                    $message = (string)($up['message'] ?? '圖片上傳失敗。');
                }
            }

            if ($message === null) {
                $stmt = $pdo->prepare('
                    INSERT INTO bugs (project_id, reporter_id, title, description, image_path, status, severity, tags, created_at)
                    VALUES (:pid, :rid, :title, :description, :image_path, \'待處理\', :severity, :tags, NOW())
                ');
                $stmt->execute([
                    ':pid' => $projectId,
                    ':rid' => $userId,
                    ':title' => $title,
                    ':description' => $desc,
                    ':image_path' => $imagePath,
                    ':severity' => $severity,
                    ':tags' => $tags,
                ]);
                $newBugId = (int)$pdo->lastInsertId();
                log_action($userId, "Created bug #{$newBugId} in project #{$projectId}");
                redirect('bug_view.php?id=' . $newBugId);
            }
        }
    }
}

layout_start('提報 Bug', $user);
?>
<div class="panel">
  <h2>提報 Bug</h2>
  <?php if ($message): ?><div class="msg error"><?= e($message) ?></div><?php endif; ?>

  <form method="post" action="<?= e(url('bug_new.php')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

    <label>專案</label>
    <select name="project_id" required>
      <option value="">— 選擇 —</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= e((string)$p['id']) ?>" <?= (int)$p['id'] === $preselect ? 'selected' : '' ?>><?= e((string)$p['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>標題</label>
    <input name="title" required maxlength="200" />

    <label>描述（可用 ```php 程式碼區塊）</label>
    <textarea name="description"></textarea>

    <label>緊急程度</label>
    <select name="severity" required>
      <option value="">— 選擇 —</option>
      <?php foreach ($severities as $s): ?>
        <option value="<?= e($s) ?>"><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>

    <label>標籤</label>
    <select name="tag_preset" id="tag_preset">
      <option value="">— 不選 —</option>
      <?php foreach ($tagPresets as $t): ?>
        <option value="<?= e($t) ?>"><?= e($t) ?></option>
      <?php endforeach; ?>
      <option value="__other__">其他（自行填寫）</option>
    </select>
    <input type="text" name="tags_custom" id="tags_custom" placeholder="自訂標籤" style="display:none;margin-top:8px;" maxlength="200" />

    <label>POC 截圖（選填，最大 5MB）</label>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" />

    <div class="form-actions"><button type="submit">送出</button></div>
  </form>
</div>
<script>
(function () {
  var sel = document.getElementById('tag_preset');
  var custom = document.getElementById('tags_custom');
  if (!sel || !custom) return;
  function toggle() {
    var show = sel.value === '__other__';
    custom.style.display = show ? 'block' : 'none';
    custom.required = show;
    if (!show) custom.value = '';
  }
  sel.addEventListener('change', toggle);
  toggle();
})();
</script>
<?php layout_end(); ?>
