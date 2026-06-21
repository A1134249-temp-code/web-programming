<?php
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/**
 * 安全圖片上傳至 public/uploads/
 * DB 儲存相對路徑：uploads/檔名
 */
function upload_image(array $file): array
{
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        if (isset($file['error']) && (int)$file['error'] === UPLOAD_ERR_INI_SIZE) {
            return ['ok' => false, 'path' => null, 'message' => '圖片大小不可超過 5MB。'];
        }
        if (isset($file['error']) && (int)$file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'path' => null, 'message' => '圖片大小不可超過 5MB。'];
        }
        return ['ok' => false, 'path' => null, 'message' => '上傳失敗。'];
    }
    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || $file['tmp_name'] === '') {
        return ['ok' => false, 'path' => null, 'message' => '上傳檔案無效。'];
    }

    $maxBytes = 5 * 1024 * 1024;
    if (isset($file['size']) && (int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'path' => null, 'message' => '圖片大小不可超過 5MB。'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        return ['ok' => false, 'path' => null, 'message' => '僅允許上傳圖片（jpeg/png/webp/gif）。'];
    }

    $destDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['ok' => false, 'path' => null, 'message' => '伺服器無法建立上傳資料夾。'];
    }

    $ext = $allowed[$mime];
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $destDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'message' => '檔案保存失敗。'];
    }

    return ['ok' => true, 'path' => 'uploads/' . $name, 'message' => '上傳成功。'];
}
