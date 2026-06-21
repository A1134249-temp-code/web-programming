<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/notifications.php';

/** 驗證使用者是否為 PM 角色 */
function user_is_pm(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) && ($row['role'] ?? '') === 'pm';
}

/**
 * Admin 建立專案並指派 PM（同時寫入 project_members）。
 */
function admin_create_project_with_pm(PDO $pdo, int $adminId, string $name, ?string $description, int $pmUserId): int
{
    if (!user_is_pm($pdo, $pmUserId)) {
        throw new InvalidArgumentException('指派對象必須為 PM 角色。');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO projects (name, description, pm_user_id, is_archived) VALUES (:n, :d, :pm, 0)');
        $stmt->execute([
            ':n' => $name,
            ':d' => $description,
            ':pm' => $pmUserId,
        ]);
        $projectId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (:pid, :uid)');
        $stmt->execute([':pid' => $projectId, ':uid' => $pmUserId]);

        log_action($adminId, "Admin created project #{$projectId} and assigned PM user #{$pmUserId}");
        $pdo->commit();
        return $projectId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Admin 轉移專案負責 PM。
 */
function admin_transfer_project_pm(PDO $pdo, int $adminId, int $projectId, int $newPmUserId): void
{
    if (!user_is_pm($pdo, $newPmUserId)) {
        throw new InvalidArgumentException('新負責人必須為 PM 角色。');
    }

    $stmt = $pdo->prepare('SELECT id, pm_user_id, name FROM projects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        throw new RuntimeException('專案不存在。');
    }

    $oldPm = (int)($project['pm_user_id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE projects SET pm_user_id = :pm WHERE id = :id');
        $stmt->execute([':pm' => $newPmUserId, ':id' => $projectId]);

        $stmt = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (:pid, :uid)');
        $stmt->execute([':pid' => $projectId, ':uid' => $newPmUserId]);

        if ($oldPm > 0 && $oldPm !== $newPmUserId) {
            $stmt = $pdo->prepare('DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid');
            $stmt->execute([':pid' => $projectId, ':uid' => $oldPm]);
            notify_user(
                $oldPm,
                '專案「' . (string)$project['name'] . '」的負責 PM 已轉移給其他使用者，您不再具有此專案的管理權限。',
                '專案 PM 已轉移',
                '專案「' . (string)$project['name'] . '」的管理權限已移交，您無法再管理此專案成員。'
            );
        }

        log_action($adminId, "Admin transferred project #{$projectId} PM from #{$oldPm} to #{$newPmUserId}");

        notify_user(
            $newPmUserId,
            '您已被指派為專案「' . (string)$project['name'] . '」的負責 PM。',
            '專案負責人指派',
            '您已成為專案「' . (string)$project['name'] . '」的負責 PM。'
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * PM 提交專案申請。
 */
function pm_submit_project_request(PDO $pdo, int $pmId, string $projectName, ?string $description): int
{
    $stmt = $pdo->prepare('
        INSERT INTO project_requests (pm_id, project_name, description, status, created_at)
        VALUES (:pm, :name, :desc, \'pending\', NOW())
    ');
    $stmt->execute([
        ':pm' => $pmId,
        ':name' => $projectName,
        ':desc' => $description,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Admin 核准專案申請（Transaction）。
 */
function admin_approve_project_request(PDO $pdo, int $adminId, int $requestId): void
{
    $stmt = $pdo->prepare('SELECT * FROM project_requests WHERE id = :id AND status = \'pending\' LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $req = $stmt->fetch();
    if (!$req) {
        throw new RuntimeException('申請不存在或已處理。');
    }

    $pmId = (int)$req['pm_id'];
    if (!user_is_pm($pdo, $pmId)) {
        throw new RuntimeException('申請人已不是 PM 角色。');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO projects (name, description, pm_user_id, is_archived) VALUES (:n, :d, :pm, 0)');
        $stmt->execute([
            ':n' => (string)$req['project_name'],
            ':d' => $req['description'],
            ':pm' => $pmId,
        ]);
        $projectId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (:pid, :uid)');
        $stmt->execute([':pid' => $projectId, ':uid' => $pmId]);

        $stmt = $pdo->prepare('
            UPDATE project_requests
            SET status = \'approved\', reviewed_by = :admin, reviewed_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([':admin' => $adminId, ':id' => $requestId]);

        log_action($adminId, "Admin approved project request #{$requestId}, created project #{$projectId}");

        $msg = '您的專案申請「' . (string)$req['project_name'] . '」已核准（專案 #' . $projectId . '）。';
        notify_user($pmId, $msg, '專案申請已核准', $msg);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Admin 駁回專案申請。
 */
function admin_reject_project_request(PDO $pdo, int $adminId, int $requestId): void
{
    $stmt = $pdo->prepare('SELECT * FROM project_requests WHERE id = :id AND status = \'pending\' LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $req = $stmt->fetch();
    if (!$req) {
        throw new RuntimeException('申請不存在或已處理。');
    }

    $stmt = $pdo->prepare('
        UPDATE project_requests
        SET status = \'rejected\', reviewed_by = :admin, reviewed_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute([':admin' => $adminId, ':id' => $requestId]);

    log_action($adminId, "Admin rejected project request #{$requestId}");

    $pmId = (int)$req['pm_id'];
    $msg = '您的專案申請「' . (string)$req['project_name'] . '」已被駁回。';
    notify_user($pmId, $msg, '專案申請已駁回', $msg);
}

/** Bug 狀態變更時通知回報者 */
function notify_bug_status_change(PDO $pdo, int $bugId, string $newStatus, int $actorUserId): void
{
    $stmt = $pdo->prepare('
        SELECT b.title, b.reporter_id, u.username AS actor_name
        FROM bugs b
        INNER JOIN users u ON u.id = :actor
        WHERE b.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $bugId, ':actor' => $actorUserId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $reporterId = (int)$row['reporter_id'];
    if ($reporterId === $actorUserId) {
        return;
    }

    $msg = 'Bug #' . $bugId . '「' . (string)$row['title'] . '」狀態已更新為「' . $newStatus . '」（由 ' . (string)$row['actor_name'] . '）。';
    notify_user($reporterId, $msg, 'Bug 狀態更新', $msg);
}
