<?php

declare(strict_types=1);

use Bist\App;
use Bist\Config\Ayarlar;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use Bist\Http\GovdeHatasi;
use Bist\Http\HataYakalayici;
use Bist\Http\Yetki;
use Bist\Http\GovdeOkuyucu;

require dirname(__DIR__) . '/vendor/autoload.php';

$ayarlar = Ayarlar::global(dirname(__DIR__));

// D4 (#8): Yakalanmamis istisna ve uyarilar buradan sonra kullaniciya
// sizmaz. Once DB acilamayinca tam PDOException yigin izi doniyordu,
// ustelik HTTP 200 OK ile.
(new HataYakalayici($ayarlar->ortam))->bagla();

// Depo TEMBEL: kokpit istegi DB'ye hic dokunmaz, disk salt okunur olsa
// bile ana sayfa acilir (K6).
//
// Dizin olusturma da bu kapanisin ICINDE: onceden ust seviyede kosulsuz
// calisiyordu ve DB yolu yazilamaz oldugunda kokpiti de dusuruyordu.
// Birim testi App'i dogrudan test ettigi icin bunu gormemisti; canli
// dogrulamada yakalandi.
$app = new App(
    kaynak: new BosKaynak(),
    depoSaglayici: static function () use ($ayarlar): KriterDeposu {
        // 0o770: dizin dunyaya yazilabilir olmamali (#15/S6).
        $dizin = dirname($ayarlar->dbYolu);
        if (!is_dir($dizin) && !mkdir($dizin, 0o770, true) && !is_dir($dizin)) {
            throw new RuntimeException("Veri dizini oluşturulamadı: {$dizin}");
        }

        return new KriterDeposu($ayarlar->dbYolu);
    },
    // D6 (#10): token yapilandirilmamissa yazma ve okuma KAPALI.
    yetki: new Yetki($ayarlar->yazmaToken),
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

$cevap = $app->calistir(
    $yol,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_GET,
    $govde,
    Yetki::baslikta($_SERVER),
);

http_response_code($cevap['durum']);
header('Content-Type: ' . $cevap['tur']);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

foreach ($cevap['basliklar'] ?? [] as $ad => $deger) {
    header($ad . ': ' . $deger);
}

echo $cevap['govde'];
