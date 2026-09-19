<?php

declare(strict_types=1);

/**
 * Test önyükleme: bellek içi SQLite, test ortamı, oturum yok.
 * Her test sınıfı TestCase::app() ile taze bir uygulama + migrasyon alır.
 */

putenv('APP_ENV=test');
putenv('APP_DEBUG=true');
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('APP_KEY=test-key-test-key-test-key-test-key-0000');
putenv('MAIL_DRIVER=log');
$_ENV['APP_ENV'] = 'test';
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_SQLITE_PATH'] = ':memory:';

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/TestCase.php';
