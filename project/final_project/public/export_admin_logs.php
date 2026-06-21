<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/excel_export.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pdo = getPDO();

excel_export_begin('export_data.xls');

$stmt = $pdo->query('
    SELECT al.id, al.created_at, u.username, al.action
    FROM action_logs al
    INNER JOIN users u ON u.id = al.user_id
    ORDER BY al.id ASC
');
$logs = $stmt->fetchAll();

echo '<table border="1">';
echo '<tr><th>ID</th><th>時間</th><th>使用者</th><th>動作</th></tr>';
foreach ($logs as $log) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars((string)$log['id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$log['username'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$log['action'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</table>';
