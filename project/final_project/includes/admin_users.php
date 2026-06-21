<?php
declare(strict_types=1);

/**
 * Admin 使用者權限管理 — 後端驗證。
 * 規則：
 * - 僅允許將他人設為 pm / member
 * - 禁止透過 UI 建立或升級 admin
 * - 禁止修改 admin 帳號（含自己與其他 admin）
 * - 禁止修改自己的角色
 */

/** Admin 可賦予的角色（不含 admin） */
function admin_assignable_roles(): array
{
    return ['pm', 'member'];
}

function admin_can_modify_user(int $targetUserId, string $targetRole, int $currentAdminId): bool
{
    if ($targetUserId <= 0 || $targetUserId === $currentAdminId) {
        return false;
    }
    if ($targetRole === 'admin') {
        return false;
    }
    return true;
}

function admin_validate_role_change(int $targetUserId, string $newRole, int $currentAdminId, PDO $pdo): ?string
{
    if ($targetUserId === $currentAdminId) {
        return '無法修改自己的角色。';
    }

    if ($newRole === 'admin' || !in_array($newRole, admin_assignable_roles(), true)) {
        return '僅允許設定為 pm 或 member。';
    }

    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $targetUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        return '使用者不存在。';
    }

    if ((string)($target['role'] ?? '') === 'admin') {
        return '無法修改系統管理員帳號。';
    }

    if ((string)($target['role'] ?? '') === 'pm' && $newRole === 'member') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE pm_user_id = :uid');
        $stmt->execute([':uid' => $targetUserId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return '此 PM 仍為專案負責人，無法降級為 member。請先轉移專案負責人。';
        }
    }

    return null;
}

function admin_user_row_editable(array $userRow, int $currentAdminId): bool
{
    $uid = (int)($userRow['id'] ?? 0);
    $role = (string)($userRow['role'] ?? '');
    if ($uid === $currentAdminId) {
        return false;
    }
    if ($role === 'admin') {
        return false;
    }
    return true;
}
