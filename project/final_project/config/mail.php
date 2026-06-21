<?php
declare(strict_types=1);

/**
 * SMTP 設定（請填入 Gmail 應用程式密碼後測試）。
 *
 * Gmail 參考：
 *   Host: smtp.gmail.com
 *   Port: 587
 *   SMTPSecure: tls
 *   Username: 你的 Gmail
 *   Password: 16 碼應用程式密碼
 */

$envPath = __DIR__ . '/../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];

return [
    'smtp_host'     => $env['SMTP_HOST'] ?? 'smtp.gmail.com',
    'smtp_port'     => (int)($env['SMTP_PORT'] ?? 587),
    'smtp_secure'   => $env['SMTP_SECURE'] ?? 'tls',
    'smtp_username' => $env['SMTP_USERNAME'] ?? '',
    'smtp_password' => $env['SMTP_PASSWORD'] ?? '',
    'from_email'    => $env['FROM_EMAIL'] ?? '',
    'from_name'     => $env['FROM_NAME'] ?? 'Issue Tracker',
];