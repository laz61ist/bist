<?php

declare(strict_types=1);

use Bist\App;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;

require dirname(__DIR__) . '/vendor/autoload.php';

$dbYolu = getenv('E2E_DB_PATH') ?: (dirname(__DIR__) . '/var/bist.sqlite');
@mkdir(dirname($dbYolu), 0o777, true);

$app = new App(
    kaynak: new BosKaynak(),
    depo: new KriterDeposu($dbYolu),
);

$yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$govde = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ham = file_get_contents('php://input') ?: '';
    $govde = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
        ? (json_decode($ham, true) ?: [])
        : $_POST;
}

$cevap = $app->calistir($yol, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, is_array($govde) ? $govde : []);

http_response_code($cevap['durum']);
header('Content-Type: ' . $cevap['tur']);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
echo $cevap['govde'];
