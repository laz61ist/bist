<?php

declare(strict_types=1);

use Aidat\Core\Env;

$base = dirname(__DIR__);
$sqlite = (string) Env::get('DB_SQLITE_PATH', 'storage/database/aidat.sqlite');
if ($sqlite !== ':memory:' && !str_starts_with($sqlite, '/')) {
    $sqlite = $base . '/' . $sqlite;
}

return [
    'driver' => Env::get('DB_DRIVER', 'sqlite'),
    'sqlite_path' => $sqlite,
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'name' => Env::get('DB_NAME', 'aidat'),
    'user' => Env::get('DB_USER', 'aidat'),
    'pass' => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];
