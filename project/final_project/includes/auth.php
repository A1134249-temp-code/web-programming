<?php
declare(strict_types=1);

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/system_settings.php';

/**
 * users: id, username, email, password, role
 */

function auth_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_cookie_path(),
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    session_guard_init();
}

function csrf_token(): string
{
    auth_init();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    auth_init();
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function auth_user(): ?array
{
    auth_init();
    $u = $_SESSION['user'] ?? null;
    return is_array($u) ? $u : null;
}

function auth_require_login(): void
{
    $user = auth_user();
    if ($user === null) {
        redirect('login.php');
    }

    if (($user['username'] ?? '') === 'testAdmin') {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== 'admin_reset.php' && $script !== 'logout.php') {
            flash_set('error', '創始測試帳號僅能執行系統重設。');
            redirect('admin_reset.php');
        }
    }
}

function auth_logout(): void
{
    auth_init();
    $_SESSION = [];
    session_regenerate_id(true);
}

function auth_register(string $username, string $email, string $password): array
{
    auth_init();

    if (!registration_allowed()) {
        return ['ok' => false, 'message' => '目前不開放註冊。'];
    }

    $username = trim($username);
    $email = trim(mb_strtolower($email));

    if ($username === '' || mb_strlen($username) > 50) {
        return ['ok' => false, 'message' => '帳號格式不正確。'];
    }
    if (!preg_match('/^[a-zA-Z0-9_\\.\\-]{3,50}$/', $username)) {
        return ['ok' => false, 'message' => '帳號僅允許英數與 _ . -，長度 3~50。'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Email 格式不正確。'];
    }
    if (mb_strlen($password) < 8) {
        return ['ok' => false, 'message' => '密碼長度至少 8 碼。'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        return ['ok' => false, 'message' => '密碼處理失敗，請稍後再試。'];
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => '帳號或 Email 已存在。'];
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, password_updated_at) VALUES (:username, :email, :password, \'member\', NOW())');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $hash,
    ]);

    $newUserId = (int)$pdo->lastInsertId();
    require_once __DIR__ . '/actions.php';
    log_action($newUserId, "User registered: {$username}");

    return ['ok' => true, 'message' => '註冊成功，請登入。'];
}

function auth_login(string $username, string $password): array
{
    auth_init();

    $username = trim($username);
    if ($username === '' || mb_strlen($username) > 50) {
        return ['ok' => false, 'message' => '帳號或密碼錯誤。'];
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return ['ok' => false, 'message' => '帳號或密碼錯誤。'];
    }

    $hash = $row['password'] ?? '';
    if (!is_string($hash) || $hash === '') {
        return ['ok' => false, 'message' => '帳號或密碼錯誤。'];
    }

    $verified = password_verify($password, $hash);
    if (!$verified && hash_equals($hash, $password)) {
        return ['ok' => false, 'message' => '此帳號密碼未雜湊，請使用 tools/setup_admin.php 重設。'];
    }
    if (!$verified) {
        return ['ok' => false, 'message' => '帳號或密碼錯誤。'];
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'email' => (string)($row['email'] ?? ''),
        'role' => (string)$row['role'],
    ];
    $_SESSION['__guard'] = [
        'ua' => hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'created_at' => time(),
    ];

    require_once __DIR__ . '/actions.php';
    log_action((int)$row['id'], 'User logged in');

    return ['ok' => true, 'message' => '登入成功。', 'user_id' => (int)$row['id']];
}

function deny_admin_from_project_features(): void
{
    if (auth_role() === 'admin') {
        http_response_code(403);
        echo 'Admin 不介入專案內部流程。請使用「系統管理」功能。';
        exit;
    }
}

function auth_role(): ?string
{
    $u = auth_user();
    $role = is_array($u) ? ($u['role'] ?? null) : null;
    return is_string($role) ? $role : null;
}
