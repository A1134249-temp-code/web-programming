<?php
declare(strict_types=1);

/**
 * 應用程式 URL 基底路徑（自動偵測 + 可覆寫）。
 *
 * 本地 XAMPP 範例：http://localhost/final_project/public/login.php
 *   → app_base_path() = /final_project/public
 *
 * 上線若 DocumentRoot 指向 public/：http://example.com/login.php
 *   → app_base_path() = ''（空字串，連結為 /login.php）
 *
 * 覆寫方式（擇一）：
 *   1. 環境變數 APP_BASE_PATH=/your/path/public
 *   2. 建立 includes/app.local.php，內容：<?php return ['base_path' => '/final_project/public'];
 */
function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $localFile = __DIR__ . '/app.local.php';
    if (is_file($localFile)) {
        $cfg = require $localFile;
        if (is_array($cfg) && isset($cfg['base_path']) && is_string($cfg['base_path'])) {
            $cached = rtrim($cfg['base_path'], '/');
            return $cached;
        }
    }

    $env = getenv('APP_BASE_PATH');
    if ($env !== false && $env !== '') {
        $cached = rtrim($env, '/');
        return $cached;
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $cached = '';
    } else {
        $cached = $dir;
    }

    return $cached;
}

/** 產生站內 URL（相對於 public/ 下的檔名） */
function url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = app_base_path();

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

/** HTTP 302 導向 */
function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

/** Session / Cookie 應使用的 path */
function app_cookie_path(): string
{
    $base = app_base_path();
    return $base === '' ? '/' : $base;
}
