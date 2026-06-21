<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/paths.php';

function password_reset_request(string $email): array
{
    $email = trim(mb_strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true, 'message' => '若 Email 存在，我們已寄出重設連結。'];
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => true, 'message' => '若 Email 存在，我們已寄出重設連結。'];
    }

    $userId = (int)$user['id'];
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
    $stmt->execute([':email' => $email]);

    // expires_at 使用 MySQL NOW()，避免 PHP 與 DB 時區不一致導致「一開啟就過期」
    $stmt = $pdo->prepare('
        INSERT INTO password_resets (user_id, email, token_hash, expires_at)
        VALUES (:uid, :email, :hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ');
    $stmt->execute([
        ':uid' => $userId,
        ':email' => $email,
        ':hash' => $tokenHash,
    ]);

    $link = app_origin() . url('reset_password.php') . '?token=' . rawurlencode($rawToken);
    $body = "您好 " . (string)$user['username'] . "，\n\n"
        . "請在 1 小時內點擊以下連結重設密碼：\n"
        . $link . "\n\n"
        . "若連結無法點擊，請複製整段 URL 到瀏覽器。\n"
        . "若未申請請忽略此信。";
    send_mail_safe($email, '重設密碼', $body);

    return ['ok' => true, 'message' => '若 Email 存在，我們已寄出重設連結。'];
}

function password_reset_validate_token(string $rawToken): ?array
{
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 32) {
        return null;
    }

    $pdo = getPDO();
    $hash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare('
        SELECT user_id, email
        FROM password_resets
        WHERE token_hash = :hash AND expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    return [
        'user_id' => (int)($row['user_id'] ?? 0),
        'email' => (string)($row['email'] ?? ''),
    ];
}

function password_reset_apply(string $rawToken, string $newPassword): array
{
    if (mb_strlen($newPassword) < 8) {
        return ['ok' => false, 'message' => '密碼至少 8 碼。'];
    }

    $record = password_reset_validate_token($rawToken);
    if ($record === null) {
        return ['ok' => false, 'message' => '連結無效或已過期。'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        return ['ok' => false, 'message' => '密碼處理失敗。'];
    }

    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        if ($record['user_id'] > 0) {
            $stmt = $pdo->prepare('UPDATE users SET password = :p, password_updated_at = NOW() WHERE id = :id');
            $stmt->execute([':p' => $hash, ':id' => $record['user_id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = :p, password_updated_at = NOW() WHERE email = :email');
            $stmt->execute([':p' => $hash, ':email' => $record['email']]);
        }

        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
        $stmt->execute([':email' => $record['email']]);

        $pdo->commit();
        return ['ok' => true, 'message' => '密碼已更新，請登入。'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[RESET] ' . $e->getMessage());
        return ['ok' => false, 'message' => '更新失敗，請稍後再試。'];
    }
}
