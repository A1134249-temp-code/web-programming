<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

function log_action(int $userId, string $action): void
{
    $action = trim($action);
    if ($action === '') {
        return;
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO action_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())');
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
    ]);
}

function log_action_current_user(string $action): void
{
    $u = auth_user();
    $id = is_array($u) ? ($u['id'] ?? null) : null;
    if (is_int($id) && $id > 0) {
        log_action($id, $action);
    }
}

