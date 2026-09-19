<?php

declare(strict_types=1);

use Aidat\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Aidat Yönetim'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'key' => (string) Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Istanbul'),
    'locale' => 'tr',
    'version' => '1.0.0',
    'session_name' => 'aidat_session',
    'upload_max_bytes' => (int) Env::get('UPLOAD_MAX_BYTES', 5 * 1024 * 1024),
    'upload_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'log'),
        'from' => Env::get('MAIL_FROM', 'aidat@example.com'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Site Yönetimi'),
        'smtp_host' => Env::get('SMTP_HOST'),
        'smtp_port' => (int) Env::get('SMTP_PORT', 587),
        'smtp_user' => Env::get('SMTP_USER'),
        'smtp_pass' => Env::get('SMTP_PASS'),
        'smtp_secure' => Env::get('SMTP_SECURE', 'tls'),
    ],
    'sms' => [
        'driver' => Env::get('SMS_DRIVER', 'log'),
        'webhook_url' => Env::get('SMS_WEBHOOK_URL'),
        'webhook_token' => Env::get('SMS_WEBHOOK_TOKEN'),
    ],
];
