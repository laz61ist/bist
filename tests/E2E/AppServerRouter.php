<?php

declare(strict_types=1);

/**
 * PHP yerlesik sunucusu icin yonlendirici.
 *
 * Kullanim:
 *   E2E_DB_PATH=/tmp/e2e.sqlite php -S 127.0.0.1:8200 -t public tests/E2E/AppServerRouter.php
 *
 * Var olan statik dosyalari oldugu gibi servis eder, digerlerini
 * public/index.php on denetleyicisine yonlendirir.
 */
$yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$dosya = __DIR__ . '/../../public' . $yol;

if ($yol !== '/' && is_file($dosya)) {
    return false; // sunucu statik dosyayi kendisi versin
}

require __DIR__ . '/../../public/index.php';
