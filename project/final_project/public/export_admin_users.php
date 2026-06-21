<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/excel_export.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pdo = getPDO();

excel_export_begin('export_data.xls');

$stmt = $pdo->query('SELECT id, username, email, role, created_at, password_updated_at FROM users ORDER BY id ASC');
$users = $stmt->fetchAll();

echo '<table border="1">';
echo '<tr><th>ID</th><th>帳號</th><th>Email</th><th>角色</th><th>建立時間</th><th>密碼更新時間</th></tr>';
foreach ($users as $u) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$u['role'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$u['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($u['password_updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</table>';
