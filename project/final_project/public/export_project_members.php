<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/project_access.php';
require_once __DIR__ . '/../includes/excel_export.php';
require_once __DIR__ . '/../config/database.php';

require_role(['pm']);

$pdo = getPDO();
$user = auth_user();
$pmId = (int)($user['id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);

if ($projectId <= 0 || !pm_can_manage_project($pdo, $pmId, $projectId)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

excel_export_begin('export_data.xls');

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.email, u.role, pm.joined_at
    FROM project_members pm
    INNER JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = :pid
    ORDER BY u.username ASC
');
$stmt->execute([':pid' => $projectId]);
$rows = $stmt->fetchAll();

echo '<table border="1">';
echo '<tr><th>ID</th><th>帳號</th><th>Email</th><th>角色</th><th>加入時間</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['username'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['role'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['joined_at'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</table>';
