<?php
declare(strict_types=1);

/**
 * 統一輸出轉義（XSS 防禦）。
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Session 路由防護（Session 固定攻擊/竄改/被盜後跨路由濫用的基礎緩解）：
 * - 綁定 User-Agent 的雜湊
 * - 登入後 regenerate session id
 * - 每次請求檢查指紋一致性
 *
 * 注意：IP 綁定在企業/行動網路下容易誤傷，因此預設不綁 IP。
 */
function session_guard_init(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $uaHash = hash('sha256', $ua);

    if (!isset($_SESSION['__guard'])) {
        $_SESSION['__guard'] = [
            'ua' => $uaHash,
            'created_at' => time(),
        ];
        return;
    }

    $guard = $_SESSION['__guard'];
    if (!is_array($guard) || !isset($guard['ua']) || !is_string($guard['ua'])) {
        session_unset();
        session_destroy();
        throw new RuntimeException('Session 驗證失敗，請重新登入。');
    }

    if (!hash_equals($guard['ua'], $uaHash)) {
        session_unset();
        session_destroy();
        throw new RuntimeException('Session 驗證失敗，請重新登入。');
    }
}

/**
 * 極簡、安全的 fenced code block 轉換：
 * - 支援 ```lang ... ```（lang 只允許英數與 -）
 * - 其餘內容以純文字顯示（保留換行）
 *
 * 目的：配合 Prism.js 做語法高亮，同時避免引入複雜 Markdown parser 造成 XSS 風險。
 */
function render_description_safe(string $text): string
{
    $out = '';
    $offset = 0;

    // 支援 ```lang 換行 程式碼 ```（結尾 ``` 前可無強制換行）
    $pattern = '/```([a-zA-Z0-9-]{0,20})\s*\r?\n([\s\S]*?)```/';
    if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
        return '<div class="md">' . nl2br(e($text)) . '</div>';
    }

    foreach ($matches[0] as $i => $m) {
        $full = $m[0];
        $pos = $m[1];

        $before = substr($text, $offset, $pos - $offset);
        if ($before !== '') {
            $out .= '<div class="md">' . nl2br(e($before)) . '</div>';
        }

        $lang = $matches[1][$i][0] ?? '';
        $code = $matches[2][$i][0] ?? '';
        $lang = is_string($lang) ? trim($lang) : '';
        $code = is_string($code) ? rtrim($code, "\r\n") : '';

        $class = $lang !== '' ? ('language-' . $lang) : 'language-none';
        $out .= '<pre class="code-block"><code class="' . e($class) . '">' . e($code) . '</code></pre>';

        $offset = $pos + strlen($full);
    }

    $rest = substr($text, $offset);
    if ($rest !== '') {
        $out .= '<div class="md">' . nl2br(e($rest)) . '</div>';
    }

    return $out;
}

