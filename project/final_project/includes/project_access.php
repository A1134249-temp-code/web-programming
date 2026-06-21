<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function is_project_member(PDO $pdo, int $userId, int $projectId): bool
{
    $stmt = $pdo->prepare('
        SELECT 1
        FROM project_members pm
        INNER JOIN projects p ON p.id = pm.project_id
        WHERE pm.project_id = :pid AND pm.user_id = :uid AND p.is_archived = 0
        LIMIT 1
    ');
    $stmt->execute([':pid' => $projectId, ':uid' => $userId]);
    return (bool)$stmt->fetch();
}

/**
 * 是否為專案「負責 PM」（僅 projects.pm_user_id，不含一般成員）。
 */
function pm_can_manage_project(PDO $pdo, int $pmUserId, int $projectId): bool
{
    $stmt = $pdo->prepare('
        SELECT 1
        FROM projects p
        INNER JOIN users u ON u.id = :uid1 AND u.role = \'pm\'
        WHERE p.id = :pid AND p.is_archived = 0 AND p.pm_user_id = :uid2
        LIMIT 1
    ');
    $stmt->execute([':pid' => $projectId, ':uid1' => $pmUserId, ':uid2' => $pmUserId]);
    return (bool)$stmt->fetch();
}

/** PM 可管理的專案列表（僅負責人） */
function fetch_pm_projects(PDO $pdo, int $pmUserId): array
{
    $stmt = $pdo->prepare('
        SELECT p.id, p.name, p.description
        FROM projects p
        WHERE p.is_archived = 0 AND p.pm_user_id = :uid
        ORDER BY p.id DESC
    ');
    $stmt->execute([':uid' => $pmUserId]);
    return $stmt->fetchAll();
}

function fetch_member_projects(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT p.id, p.name, p.description
        FROM projects p
        INNER JOIN project_members pm ON pm.project_id = p.id
        WHERE pm.user_id = :uid AND p.is_archived = 0
        ORDER BY p.id DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function fetch_project_bug_stats(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM bugs WHERE project_id = :pid GROUP BY status');
    $stmt->execute([':pid' => $projectId]);
    $rows = $stmt->fetchAll();

    $stats = ['待處理' => 0, '處理中' => 0, '已解決' => 0, '已駁回' => 0, 'total' => 0];
    foreach ($rows as $r) {
        $st = (string)$r['status'];
        $cnt = (int)$r['cnt'];
        if (isset($stats[$st])) {
            $stats[$st] = $cnt;
        }
        $stats['total'] += $cnt;
    }
    return $stats;
}

function fetch_project_pm(PDO $pdo, int $projectId): ?array
{
    $stmt = $pdo->prepare('
        SELECT u.id, u.username
        FROM projects p
        LEFT JOIN users u ON u.id = p.pm_user_id
        WHERE p.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $projectId]);
    $row = $stmt->fetch();
    if (!is_array($row) || empty($row['id'])) {
        return null;
    }
    return ['id' => (int)$row['id'], 'username' => (string)$row['username']];
}
