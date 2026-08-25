<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Config\Ortam;
use Bist\Http\HataYakalayici;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * D4 (#8): Hata yakalama ve log tamamen yoktu.
 *
 * Kanitlanan iki somuru:
 *  - GET /?sirket[]=x -> "Warning: Array to string conversion in
 *    /.../src/App.php on line 47" (kaynak dosya yolu ifsasi)
 *  - DB acilamayinca -> tam PDOException yigin izi, ustelik HTTP 200 OK ile
 *    (cunku http_response_code satirina hic ulasilmiyordu)
 */
final class HataYakalayiciTest extends TestCase
{
    /** @var list<string> */
    private array $loglananlar = [];

    private function yakalayici(Ortam $ortam): HataYakalayici
    {
        $this->loglananlar = [];

        return new HataYakalayici(
            ortam: $ortam,
            loglayici: function (string $mesaj): void {
                $this->loglananlar[] = $mesaj;
            },
        );
    }

    private function istisna(): RuntimeException
    {
        return new RuntimeException('Veritabanı /home/user/bist/var/bist.sqlite açılamadı');
    }

    // --- Production: hicbir ic ayrinti disari cikmaz ---

    public function testProductiondaYanitDosyaYoluSizdirmaz(): void
    {
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertStringNotContainsString('/home/', $cevap['govde']);
        self::assertStringNotContainsString('bist.sqlite', $cevap['govde']);
    }

    public function testProductiondaYanitYiginIziSizdirmaz(): void
    {
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertStringNotContainsString('Stack trace', $cevap['govde']);
        self::assertStringNotContainsString('#0 ', $cevap['govde']);
        self::assertStringNotContainsString('RuntimeException', $cevap['govde']);
    }

    public function testProductiondaYanitSinifAdiSizdirmaz(): void
    {
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertStringNotContainsString('Bist\\', $cevap['govde']);
    }

    // --- Durum kodu: 200 DEGIL ---

    public function testYakalanmamisIstisna500Doner(): void
    {
        self::assertSame(500, $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna())['durum']);
        self::assertSame(500, $this->yakalayici(Ortam::DEVELOPMENT)->cevaba($this->istisna())['durum']);
    }

    // --- Kullaniciya bos sayfa degil, anlamli bir sey donmeli ---

    public function testKullaniciyaAnlamliMesajDoner(): void
    {
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertNotSame('', trim($cevap['govde']));
        self::assertStringContainsString('hata', mb_strtolower($cevap['govde'], 'UTF-8'));
    }

    public function testIzSurmeIcinOlayKimligiVerilir(): void
    {
        // Kullanici destege bu kimligi soyler, log'da ayni kimlik bulunur
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertMatchesRegularExpression('/[0-9a-f]{12}/', $cevap['govde']);
    }

    // --- Log: TAM ayrinti gitmeli ---

    public function testLogaTamAyrintiYazilir(): void
    {
        $y = $this->yakalayici(Ortam::PRODUCTION);
        $y->cevaba($this->istisna());

        self::assertCount(1, $this->loglananlar);
        $log = $this->loglananlar[0];
        self::assertStringContainsString('bist.sqlite', $log);
        self::assertStringContainsString('RuntimeException', $log);
    }

    public function testLogVeYanitAyniOlayKimliginiTasir(): void
    {
        $y = $this->yakalayici(Ortam::PRODUCTION);
        $cevap = $y->cevaba($this->istisna());

        preg_match('/([0-9a-f]{12})/', $cevap['govde'], $m);
        self::assertNotEmpty($m, 'Yanıtta olay kimliği yok');
        self::assertStringContainsString($m[1], $this->loglananlar[0]);
    }

    // --- Gelistirme ortami: ayrinti GORUNUR ---

    public function testGelistirmedeAyrintiGosterilir(): void
    {
        $cevap = $this->yakalayici(Ortam::DEVELOPMENT)->cevaba($this->istisna());

        self::assertStringContainsString('bist.sqlite', $cevap['govde']);
    }

    // --- Icerik turu ---

    public function testYanitIcerikTuruBelirtilir(): void
    {
        $cevap = $this->yakalayici(Ortam::PRODUCTION)->cevaba($this->istisna());

        self::assertArrayHasKey('tur', $cevap);
        self::assertStringContainsString('charset=utf-8', $cevap['tur']);
    }

    public function testHtmlKacisiYapilir(): void
    {
        $kotu = new RuntimeException('<script>alert(1)</script>');
        $cevap = $this->yakalayici(Ortam::DEVELOPMENT)->cevaba($kotu);

        self::assertStringNotContainsString('<script>alert(1)</script>', $cevap['govde']);
    }
}
