<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mail.php';

function notify_user(int $userId, string $message, ?string $emailSubject = null, ?string $emailBody = null): void
{
    $message = trim($message);
    if ($userId <= 0 || $message === '') {
        return;
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:uid, :msg, 0, NOW())');
    $stmt->execute([':uid' => $userId, ':msg' => mb_substr($message, 0, 500)]);

    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    $email = is_array($row) ? trim((string)($row['email'] ?? '')) : '';

    if ($email !== '' && $emailSubject !== null) {
        send_mail_safe($email, $emailSubject, $emailBody ?? $message);
    }
}

function notification_unread_count(int $userId): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? (int)($row['c'] ?? 0) : 0;
}

function notification_fetch_recent(int $userId, int $limit = 8): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('
        SELECT id, message, is_read, created_at
        FROM notifications
        WHERE user_id = :uid
        ORDER BY id DESC
        LIMIT ' . (int)$limit . '
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function notification_mark_read(int $userId, int $notificationId): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
}

function notification_mark_all_read(int $userId): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
}
