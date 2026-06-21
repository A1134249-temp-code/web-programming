<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

function require_role(array $allowedRoles): void
{
    auth_require_login();
    $role = auth_role();
    if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        layout_start('禁止存取', auth_user());
        echo '<div class="panel"><p>您沒有權限存取此頁面。</p></div>';
        layout_end();
        exit;
    }
}
