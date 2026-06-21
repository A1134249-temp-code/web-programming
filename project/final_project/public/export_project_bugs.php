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
    SELECT b.id, p.name AS project_name, b.title, b.status, b.severity, b.tags,
           u.username AS reporter, b.created_at
    FROM bugs b
    INNER JOIN projects p ON p.id = b.project_id
    INNER JOIN users u ON u.id = b.reporter_id
    WHERE b.project_id = :pid
    ORDER BY b.id ASC
');
$stmt->execute([':pid' => $projectId]);
$rows = $stmt->fetchAll();

echo '<table border="1">';
echo '<tr><th>ID</th><th>專案</th><th>標題</th><th>狀態</th><th>緊急程度</th><th>標籤</th><th>回報者</th><th>建立時間</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['project_name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['title'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['severity'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['tags'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['reporter'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</table>';
