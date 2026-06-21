<?php
declare(strict_types=1);

/** Bug 提報表單選項 */

function bug_severity_list(): array
{
    return ['低', '一般', '高', '緊急'];
}

function bug_tag_presets(): array
{
    return ['XSS', 'SQL Injection', 'CSRF', '認證授權', '檔案上傳', '邏輯錯誤', 'UI/UX', '效能'];
}

function bug_validate_severity(string $value): ?string
{
    $value = trim($value);
    return in_array($value, bug_severity_list(), true) ? $value : null;
}

function bug_resolve_tag(string $preset, string $custom): ?string
{
    $preset = trim($preset);
    if ($preset === '__other__') {
        $custom = trim($custom);
        if ($custom === '') {
            return null;
        }
        return mb_substr($custom, 0, 200);
    }
    if ($preset !== '' && in_array($preset, bug_tag_presets(), true)) {
        return $preset;
    }
    return null;
}
