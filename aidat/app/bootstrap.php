<?php

declare(strict_types=1);

/**
 * Uygulama önyükleme.
 *
 * Composer varsa onun autoload'u kullanılır; yoksa PSR-4 uyumlu yerleşik
 * autoloader devreye girer. Böylece uygulama "composer install" olmadan da
 * (paylaşımlı hosting) çalışır.
 */

use Aidat\Core\Application;
use Aidat\Core\Config;
use Aidat\Core\Env;

$basePath = dirname(__DIR__);

if (is_file($basePath . '/vendor/autoload.php')) {
    require $basePath . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'Aidat\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = $basePath . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (!is_file($file) && str_starts_with($relative, 'Database\\')) {
            $file = $basePath . '/database/' . str_replace('\\', '/', substr($relative, 9)) . '.php';
        }
        if (is_file($file)) {
            require $file;
        }
    });
}

require_once __DIR__ . '/helpers.php';

Env::load($basePath . '/.env');

$config = new Config($basePath . '/config', $basePath);

date_default_timezone_set($config->get('app.timezone', 'Europe/Istanbul'));
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'tr_TR.UTF-8', 'tr_TR', 'C.UTF-8');

return new Application($basePath, $config);
