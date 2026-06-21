<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/system_settings.php';

function profile_update(int $userId, string $currentPassword, string $newEmail, ?string $newPassword): array
{
    $newEmail = trim(mb_strtolower($newEmail));
    if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Email 格式不正確。'];
    }

    if ($newPassword !== null && mb_strlen($newPassword) < 8) {
        return ['ok' => false, 'message' => '新密碼長度至少 8 碼。'];
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, email, password FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['ok' => false, 'message' => '使用者不存在。'];
    }

    $hash = (string)($row['password'] ?? '');
    if ($hash === '' || !password_verify($currentPassword, $hash)) {
        return ['ok' => false, 'message' => '目前密碼不正確。'];
    }

    $passwordChanged = false;
    $emailChanged = $newEmail !== (string)($row['email'] ?? '');

    if ($newEmail !== (string)($row['email'] ?? '')) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $stmt->execute([':email' => $newEmail, ':id' => $userId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Email 已被其他帳號使用。'];
        }
    }

    if ($newPassword !== null && $newPassword !== '') {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            return ['ok' => false, 'message' => '密碼處理失敗。'];
        }
        $stmt = $pdo->prepare('UPDATE users SET email = :email, password = :password, password_updated_at = NOW() WHERE id = :id');
        $stmt->execute([':email' => $newEmail, ':password' => $newHash, ':id' => $userId]);
        $passwordChanged = true;
    } elseif ($emailChanged) {
        $stmt = $pdo->prepare('UPDATE users SET email = :email WHERE id = :id');
        $stmt->execute([':email' => $newEmail, ':id' => $userId]);
    } else {
        return ['ok' => false, 'message' => '沒有任何變更。'];
    }

    if ($passwordChanged) {
        log_action($userId, 'User changed password via profile');
        return ['ok' => true, 'message' => '密碼已更新，請重新登入。', 'logout' => true];
    }

    log_action($userId, 'User updated profile email');
    return ['ok' => true, 'message' => '個人資料已更新。', 'logout' => false];
}

function profile_sync_session_email(int $userId, string $email): void
{
    auth_init();
    if (isset($_SESSION['user']) && is_array($_SESSION['user']) && (int)($_SESSION['user']['id'] ?? 0) === $userId) {
        $_SESSION['user']['email'] = $email;
    }
}
