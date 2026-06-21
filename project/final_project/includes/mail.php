<?php
declare(strict_types=1);

/**
 * 使用 PHPMailer（手動引入 vendor/PHPMailer/）進行 SMTP 寄信。
 * 失敗只寫 error_log，不阻斷主流程、不向前端暴露錯誤。
 */

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/** @return array<string, mixed> */
function mail_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $defaults = [
        'smtp_host'     => 'smtp.gmail.com',
        'smtp_port'     => 587,
        'smtp_secure'   => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email'    => '',
        'from_name'     => 'Issue Tracker',
    ];

    $path = __DIR__ . '/../config/mail.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $settings = array_merge($defaults, $loaded);
            return $settings;
        }
    }

    $settings = $defaults;
    return $settings;
}

function mail_load_phpmailer(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $base = __DIR__ . '/../vendor/PHPMailer/src';
    require_once $base . '/Exception.php';
    require_once $base . '/PHPMailer.php';
    require_once $base . '/SMTP.php';
    $loaded = true;
}

function send_mail_safe(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[MAIL] Invalid recipient: ' . $to);
        return false;
    }

    $subject = str_replace(["\r", "\n"], '', $subject);
    $cfg = mail_settings();

    if (($cfg['smtp_username'] ?? '') === '' || ($cfg['smtp_password'] ?? '') === '') {
        error_log('[MAIL] SMTP credentials not configured in config/mail.php');
        return false;
    }

    try {
        mail_load_phpmailer();

        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = (string)$cfg['smtp_host'];
        $mail->Port = (int)$cfg['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = (string)$cfg['smtp_username'];
        $mail->Password = (string)$cfg['smtp_password'];

        $secure = strtolower(trim((string)($cfg['smtp_secure'] ?? 'tls')));
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = (string)(($cfg['from_email'] ?? '') !== '' ? $cfg['from_email'] : $cfg['smtp_username']);
        $fromName = (string)($cfg['from_name'] ?? 'Issue Tracker');
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('[MAIL] PHPMailer: ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('[MAIL] ' . $e->getMessage());
        return false;
    }
}

function app_origin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}
