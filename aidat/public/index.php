<?php

declare(strict_types=1);

/**
 * Aidat Yönetim Sistemi — tek giriş noktası.
 * Apache: .htaccess ile tüm istekler buraya gelir.
 * php -S ile: php -S 127.0.0.1:8090 -t public public/index.php
 */

// Yerleşik sunucuda statik dosyaları doğrudan servis et
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->run();
