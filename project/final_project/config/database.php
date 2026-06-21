<?php
declare(strict_types=1);

/**
 * PDO 連線工廠。
 * - 預設從環境變數讀取：DB_HOST, DB_NAME, DB_USER, DB_PASS
 * - 失敗時僅回傳通用錯誤，詳細資訊寫入 error_log
 */
function getPDO(): PDO
{

    $envPath = __DIR__ . '/../.env';
    $env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];

    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $db   = $env['DB_NAME'] ?? 'final_project';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';

    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[DB] Connection failed: ' . $e->getMessage());
        throw new RuntimeException('資料庫連線失敗，請稍後再試。');
    }
}

