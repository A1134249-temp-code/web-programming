<?php
declare(strict_types=1);

/**
 * 讀取 .env 中的固定測試管理員設定
 */
$envPath = __DIR__ . '/../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];

return [
    'username' => trim((string)($env['TEST_ADMIN_USER'] ?? 'testAdmin')),
    'email'    => trim((string)($env['TEST_ADMIN_EMAIL'] ?? 'testadmin@example.com')),
    'password' => (string)($env['TEST_ADMIN_PASS'] ?? '')
];