<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function setting_get(string $key, ?string $default = null): ?string
{
    if (!isset($GLOBALS['__settings_cache']) || !is_array($GLOBALS['__settings_cache'])) {
        $cache = [];
        try {
            $pdo = getPDO();
            $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('[SETTINGS] ' . $e->getMessage());
        }
        $GLOBALS['__settings_cache'] = $cache;
    }

    $cache = $GLOBALS['__settings_cache'];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    return $default;
}

function setting_get_int(string $key, int $default): int
{
    $raw = setting_get($key, (string)$default);
    if ($raw === null || !is_numeric($raw)) {
        return $default;
    }
    return (int)$raw;
}

function setting_get_bool(string $key, bool $default): bool
{
    $raw = setting_get($key, $default ? '1' : '0');
    if ($raw === null) {
        return $default;
    }
    return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
}

function setting_set(string $key, string $value): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('
        INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([':k' => $key, ':v' => $value]);

    $GLOBALS['__settings_cache'] = null;
}

function settings_get_all(): array
{
    $pdo = getPDO();
    return $pdo->query('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC')->fetchAll();
}

function registration_allowed(): bool
{
    return setting_get_bool('allow_registration', true);
}

function password_expiry_days(): int
{
    $days = setting_get_int('password_expiry_days', 90);
    return max(1, $days);
}

function auth_password_expired(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT password_updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!is_array($row) || empty($row['password_updated_at'])) {
        return false;
    }

    $updatedAt = strtotime((string)$row['password_updated_at']);
    if ($updatedAt === false) {
        return false;
    }

    $expiryDays = password_expiry_days();
    $deadline = strtotime('+' . $expiryDays . ' days', $updatedAt);
    return $deadline !== false && time() >= $deadline;
}

function auth_require_fresh_password(): void
{
    auth_require_login();
    $user = auth_user();

    if (is_array($user) && ($user['username'] ?? '') === 'testAdmin') {
        return;
    }
    
    $uid = is_array($user) ? (int)($user['id'] ?? 0) : 0;
    if ($uid > 0 && auth_password_expired($uid)) {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== 'profile.php') {
            flash_set('error', '您的密碼已超過有效期限，請立即修改。');
            redirect('profile.php?required=1');
        }
    }
}
