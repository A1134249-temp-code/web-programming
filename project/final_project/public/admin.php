<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../includes/admin_users.php';
require_once __DIR__ . '/../includes/project_service.php';
require_once __DIR__ . '/../includes/system_settings.php';
require_once __DIR__ . '/../includes/sort.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pdo = getPDO();
$user = auth_user();
$adminId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        flash_set('error', 'CSRF 驗證失敗。');
        redirect('admin.php');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'set_role') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $role = (string)($_POST['role'] ?? '');
            $err = admin_validate_role_change($uid, $role, $adminId, $pdo);
            if ($err !== null) {
                flash_set('error', $err);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id AND role != \'admin\'');
                $stmt->execute([':role' => $role, ':id' => $uid]);
                if ($stmt->rowCount() > 0) {
                    log_action($adminId, "Admin set user #{$uid} role to {$role}");
                    flash_set('success', '已更新使用者角色。');
                } else {
                    flash_set('error', '更新失敗或目標為受保護帳號。');
                }
            }
        } elseif ($action === 'delete_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === $adminId) {
                flash_set('error', '無法刪除自己的帳號。');
            } elseif ($uid > 0) {
                $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $uid]);
                $target = $stmt->fetch();
                if (!$target) {
                    flash_set('error', '使用者不存在。');
                } elseif ((string)($target['role'] ?? '') === 'admin') {
                    flash_set('error', '無法刪除系統管理員帳號。');
                } else {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND role != \'admin\'');
                    $stmt->execute([':id' => $uid]);
                    if ($stmt->rowCount() > 0) {
                        log_action($adminId, "Admin deleted user #{$uid}");
                        flash_set('success', '已刪除使用者。');
                    } else {
                        flash_set('error', '刪除失敗。');
                    }
                }
            }
        } elseif ($action === 'create_project') {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $pmId = (int)($_POST['pm_user_id'] ?? 0);
            if ($name === '' || $pmId <= 0) {
                flash_set('error', '請填寫專案名稱並指派 PM。');
            } else {
                $pid = admin_create_project_with_pm($pdo, $adminId, $name, $desc !== '' ? $desc : null, $pmId);
                flash_set('success', '已建立專案 #' . $pid . ' 並指派 PM。');
            }
        } elseif ($action === 'transfer_pm') {
            $pid = (int)($_POST['project_id'] ?? 0);
            $newPm = (int)($_POST['new_pm_user_id'] ?? 0);
            if ($pid > 0 && $newPm > 0) {
                admin_transfer_project_pm($pdo, $adminId, $pid, $newPm);
                flash_set('success', '已轉移專案負責 PM。');
            }
        } elseif ($action === 'archive_project') {
            $pid = (int)($_POST['project_id'] ?? 0);
            if ($pid > 0) {
                $pdo->prepare('UPDATE projects SET is_archived = 1 WHERE id = :id')->execute([':id' => $pid]);
                log_action($adminId, "Admin archived project #{$pid}");
                flash_set('success', '專案已封存。');
            }
        } elseif ($action === 'unarchive_project') {
            $pid = (int)($_POST['project_id'] ?? 0);
            if ($pid > 0) {
                $pdo->prepare('UPDATE projects SET is_archived = 0 WHERE id = :id')->execute([':id' => $pid]);
                log_action($adminId, "Admin unarchived project #{$pid}");
                flash_set('success', '專案已解除封存。');
            }
        } elseif ($action === 'approve_request') {
            admin_approve_project_request($pdo, $adminId, (int)($_POST['request_id'] ?? 0));
            flash_set('success', '已核准專案申請。');
        } elseif ($action === 'reject_request') {
            admin_reject_project_request($pdo, $adminId, (int)($_POST['request_id'] ?? 0));
            flash_set('success', '已駁回專案申請。');
        } elseif ($action === 'save_settings') {
            $expiryDays = (int)($_POST['password_expiry_days'] ?? 90);
            $allowReg = isset($_POST['allow_registration']) ? '1' : '0';
            if ($expiryDays < 1 || $expiryDays > 3650) {
                flash_set('error', '密碼有效天數需介於 1～3650。');
            } else {
                setting_set('password_expiry_days', (string)$expiryDays);
                setting_set('allow_registration', $allowReg);
                log_action($adminId, 'Admin updated system settings');
                flash_set('success', '系統參數已更新。');
            }
        }
    } catch (Throwable $e) {
        error_log('[ADMIN] ' . $e->getMessage());
        flash_set('error', $e->getMessage());
    }

    redirect('admin.php');
}

$userSort = (string)($_GET['user_sort'] ?? '');
$projectSort = (string)($_GET['project_sort'] ?? '');

$users = $pdo->query('SELECT id, username, email, role FROM users ORDER BY ' . user_list_sort_clause($userSort))->fetchAll();
$pmUsers = $pdo->query("SELECT id, username FROM users WHERE role = 'pm' ORDER BY username ASC")->fetchAll();
$projects = $pdo->query('
    SELECT p.id, p.name, p.is_archived, p.pm_user_id, u.username AS pm_username
    FROM projects p
    LEFT JOIN users u ON u.id = p.pm_user_id
    ORDER BY ' . project_list_sort_clause($projectSort) . '
')->fetchAll();

$pendingRequests = $pdo->query('
    SELECT pr.*, u.username AS pm_username
    FROM project_requests pr
    INNER JOIN users u ON u.id = pr.pm_id
    WHERE pr.status = \'pending\'
    ORDER BY pr.id ASC
')->fetchAll();

layout_start('系統管理', $user);
flash_render();
?>
<div class="panel">
  <h2>系統參數設定</h2>
  <form method="post" action="<?= e(url('admin.php')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_settings" />
    <label for="password_expiry_days">密碼有效天數（password_expiry_days）</label>
    <input id="password_expiry_days" name="password_expiry_days" type="number" min="1" max="3650" value="<?= e((string)password_expiry_days()) ?>" required />
    <label class="checkbox-label">
      <input type="checkbox" name="allow_registration" value="1" <?= registration_allowed() ? 'checked' : '' ?> />
      允許公開註冊（allow_registration）
    </label>
    <div class="form-actions"><button type="submit">儲存系統參數</button></div>
  </form>
</div>

<div class="panel">
  <h2>資料匯出</h2>
  <p>
    <a class="btn" href="<?= e(url('export_admin_logs.php')) ?>">匯出系統操作日誌 (Excel)</a>
    <a class="btn" href="<?= e(url('export_admin_users.php')) ?>">匯出全站使用者名單 (Excel)</a>
  </p>
</div>

<div class="panel">
  <h2>使用者管理</h2>
  <?php render_sort_form('admin.php', user_list_sort_options(), $userSort, ['project_sort' => $projectSort], 'user_sort'); ?>
  <p class="muted">僅可將他人設為 pm 或 member；無法透過此介面建立 admin。目前登入帳號與所有 admin 帳號均不可修改或刪除。</p>
  <table>
    <thead>
      <tr><th>ID</th><th>帳號</th><th>Email</th><th>角色</th><th>修改角色</th><th>刪除</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <?php $editable = admin_user_row_editable($u, $adminId); ?>
      <tr>
        <td><?= e((string)$u['id']) ?></td>
        <td><?= e((string)$u['username']) ?></td>
        <td><?= e((string)($u['email'] ?? '')) ?></td>
        <td><?= e((string)$u['role']) ?></td>
        <td>
          <?php if ($editable): ?>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="set_role" />
              <input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>" />
              <select name="role" required>
                <option value="member" <?= ($u['role'] ?? '') === 'member' ? 'selected' : '' ?>>member</option>
                <option value="pm" <?= ($u['role'] ?? '') === 'pm' ? 'selected' : '' ?>>pm</option>
              </select>
              <button type="submit">更新</button>
            </form>
          <?php else: ?>
            <span class="muted">
              <?php if ((int)$u['id'] === $adminId): ?>
                目前登入帳號（不可修改）
              <?php else: ?>
                系統管理員（不可修改）
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($editable): ?>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>" onsubmit="return confirm('確定刪除此使用者？');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="delete_user" />
              <input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>" />
              <button type="submit" class="btn-danger">刪除</button>
            </form>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>審核專案申請</h2>
  <?php
  $requestSort = (string)($_GET['request_sort'] ?? '');
  if ($requestSort === 'name') {
      usort($pendingRequests, static fn(array $a, array $b): int => strcmp((string)$a['project_name'], (string)$b['project_name']));
  }
  render_sort_form('admin.php', ['' => '預設（ID）', 'name' => '專案名稱'], $requestSort, ['user_sort' => $userSort, 'project_sort' => $projectSort], 'request_sort');
  ?>
  <?php if (!$pendingRequests): ?>
    <p class="muted">目前無待審申請。</p>
  <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>申請人</th><th>專案名稱</th><th>說明</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($pendingRequests as $pr): ?>
        <tr>
          <td><?= e((string)$pr['id']) ?></td>
          <td><?= e((string)$pr['pm_username']) ?></td>
          <td><?= e((string)$pr['project_name']) ?></td>
          <td><?= e((string)($pr['description'] ?? '')) ?></td>
          <td>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="approve_request" />
              <input type="hidden" name="request_id" value="<?= e((string)$pr['id']) ?>" />
              <button type="submit">核准</button>
            </form>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="reject_request" />
              <input type="hidden" name="request_id" value="<?= e((string)$pr['id']) ?>" />
              <button type="submit">駁回</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>建立專案（必須指派 PM）</h2>
  <?php if (!$pmUsers): ?>
    <p class="msg error">尚無 PM 帳號，請先將使用者升級為 pm。</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('admin.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="action" value="create_project" />
      <label>專案名稱</label>
      <input name="name" required maxlength="120" />
      <label>描述</label>
      <textarea name="description"></textarea>
      <label>指派 PM（必填）</label>
      <select name="pm_user_id" required>
        <option value="">— 選擇 PM —</option>
        <?php foreach ($pmUsers as $pm): ?>
          <option value="<?= e((string)$pm['id']) ?>"><?= e((string)$pm['username']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-actions"><button type="submit">建立並加入 project_members</button></div>
    </form>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>專案列表 / 封存 / 負責人轉移</h2>
  <?php render_sort_form('admin.php', project_list_sort_options(), $projectSort, ['user_sort' => $userSort], 'project_sort'); ?>
  <table>
    <thead><tr><th>ID</th><th>名稱</th><th>負責 PM</th><th>狀態</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
      <tr>
        <td><?= e((string)$p['id']) ?></td>
        <td><?= e((string)$p['name']) ?></td>
        <td><?= e((string)($p['pm_username'] ?? '—')) ?></td>
        <td><?= (int)$p['is_archived'] === 1 ? '已封存' : '進行中' ?></td>
        <td>
          <?php if ((int)$p['is_archived'] === 0 && $pmUsers): ?>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>" style="display:block;margin-bottom:6px;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="transfer_pm" />
              <input type="hidden" name="project_id" value="<?= e((string)$p['id']) ?>" />
              <select name="new_pm_user_id" required>
                <?php foreach ($pmUsers as $pm): ?>
                  <option value="<?= e((string)$pm['id']) ?>" <?= (int)$pm['id'] === (int)($p['pm_user_id'] ?? 0) ? 'selected' : '' ?>><?= e((string)$pm['username']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">轉移 PM</button>
            </form>
          <?php endif; ?>
          <?php if ((int)$p['is_archived'] === 1): ?>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="unarchive_project" />
              <input type="hidden" name="project_id" value="<?= e((string)$p['id']) ?>" />
              <button type="submit">解除封存</button>
            </form>
          <?php else: ?>
            <form class="inline-form" method="post" action="<?= e(url('admin.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="archive_project" />
              <input type="hidden" name="project_id" value="<?= e((string)$p['id']) ?>" />
              <button type="submit">封存</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="panel" style="border: 1px solid #ef4444;">
  <h2 style="color: #ef4444;">系統重設與清理</h2>
  <p>若需要清空測試資料或還原系統初始狀態，請前往專屬的操作頁面。</p>
  <p>
    <a class="btn" href="<?= e(url('admin_reset.php')) ?>" style="background-color: #ef4444; color: white; border-color: #dc2626;">前往系統重設頁面</a>
  </p>
</div>
<?php layout_end(); ?>
