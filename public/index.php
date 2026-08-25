<?php

declare(strict_types=1);

use Bist\App;
use Bist\Config\Ayarlar;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use Bist\Http\GovdeHatasi;
use Bist\Http\GovdeOkuyucu;

require dirname(__DIR__) . '/vendor/autoload.php';

$ayarlar = Ayarlar::global(dirname(__DIR__));

// 0o770: dizin dunyaya yazilabilir olmamali (#15/S6). @ kullanilmiyor;
// basarisizlik gizlenirse teshis imkansizlasir.
$dbDizini = dirname($ayarlar->dbYolu);
if (!is_dir($dbDizini) && !mkdir($dbDizini, 0o770, true) && !is_dir($dbDizini)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Veri dizini oluşturulamadı.';
    exit;
}

$app = new App(
    kaynak: new BosKaynak(),
    depo: new KriterDeposu($ayarlar->dbYolu),
);

$yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$govde = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        // D9 (#13): govde SINIRLI okunur. post_max_size application/json
        // govdelerine uygulanmadigi icin sinir burada zorlanmak zorunda.
        $govde = (new GovdeOkuyucu(
            azamiBayt: $ayarlar->azamiGovdeBayt,
            contentLength: isset($_SERVER['CONTENT_LENGTH'])
                ? (int) $_SERVER['CONTENT_LENGTH']
                : null,
        ))->json();
    } catch (GovdeHatasi $e) {
        http_response_code($e->durumKodu);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['hata' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$cevap = $app->calistir($yol, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $govde);

http_response_code($cevap['durum']);
header('Content-Type: ' . $cevap['tur']);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
echo $cevap['govde'];
