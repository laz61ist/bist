<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\App;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use Bist\Http\Yetki;
use PHPUnit\Framework\TestCase;

/**
 * D4 (#8) ikinci yari + K6.
 *
 * KriterDeposu index.php'de KOSULSUZ kuruluyordu ve yapicisi CREATE TABLE
 * calistiriyordu. Yani disk salt okunur oldugunda kriter ozelligi degil,
 * ANA KOKPIT SAYFASI da coküyordu. Kokpit veritabanina hic ihtiyac duymuyor.
 */
final class AppDayaniklilikTest extends TestCase
{
    private string $okunamazDizin;

    protected function setUp(): void
    {
        $this->okunamazDizin = sys_get_temp_dir() . '/bist-salt-okunur-' . bin2hex(random_bytes(4));
        mkdir($this->okunamazDizin, 0o555, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->okunamazDizin, 0o755);
        @rmdir($this->okunamazDizin);
    }

    private const TOKEN = 'gizli-token-en-az-yirmi-karakter';

    private function bozukDepoluApp(): App
    {
        // Depo TEMBEL olmali: kurulum aninda DB'ye dokunmamali
        return new App(
            kaynak: new BosKaynak(),
            depoSaglayici: fn (): KriterDeposu
                => new KriterDeposu($this->okunamazDizin . '/olmayan/bist.sqlite'),
            yetki: new Yetki(self::TOKEN),
        );
    }

    // --- K6: DB erisilemezken kokpit YINE acilir ---

    public function testDbErisilemezkenKokpitAcilir(): void
    {
        $cevap = $this->bozukDepoluApp()->calistir('/');

        self::assertSame(200, $cevap['durum']);
        self::assertStringContainsString('TTRAK', $cevap['govde']);
    }

    public function testDbErisilemezkenKokpitTumMetrikleriGosterir(): void
    {
        $cevap = $this->bozukDepoluApp()->calistir('/');

        self::assertGreaterThanOrEqual(12, substr_count($cevap['govde'], 'class="kart'));
    }

    public function testDepoKurulumAnindaDbYeDokunmaz(): void
    {
        $kuruldu = false;
        $app = new App(
            kaynak: new BosKaynak(),
            depoSaglayici: function () use (&$kuruldu): KriterDeposu {
                $kuruldu = true;
                return new KriterDeposu(':memory:');
            },
        );

        $app->calistir('/');

        self::assertFalse($kuruldu, 'Kokpit isteginde depo kurulmamalıydı');
    }

    // --- Kriter yolu DB'ye ihtiyac duyar, orada hata dogal ---

    public function testDbErisilemezkenKriterYolu503Doner(): void
    {
        $cevap = $this->bozukDepoluApp()->calistir('/kriter', 'GET', sunulanToken: self::TOKEN);

        self::assertSame(503, $cevap['durum']);
        self::assertStringNotContainsString('/home/', $cevap['govde']);
        self::assertStringNotContainsString('PDOException', $cevap['govde']);
    }

    public function testDbErisilemezkenKriterYazma503Doner(): void
    {
        $cevap = $this->bozukDepoluApp()->calistir('/kriter', 'POST', [], [
            'ad' => 'Set',
            'kriterler' => [['ad' => 'F/K', 'anahtar' => 'fk', 'operator' => '<', 'esik' => 12]],
        ], sunulanToken: self::TOKEN);

        self::assertSame(503, $cevap['durum']);
    }

    // --- Kanitlanmis somuru: GET /?sirket[]=x ---

    public function testDiziSirketParametresiUyariUretmez(): void
    {
        $depo = new KriterDeposu(':memory:');
        $app = new App(new BosKaynak(), $depo);

        $cevap = $app->calistir('/', 'GET', ['sirket' => ['x']]);

        self::assertSame(200, $cevap['durum']);
        self::assertStringContainsString('TTRAK', $cevap['govde'], 'Dizi girdi varsayılana düşmeli');
    }

    // --- S8 (#15): regex sondaki satir sonunu kabul ediyordu ---

    public function testSirketKoduSondakiSatirSonunuKabulEtmez(): void
    {
        $app = new App(new BosKaynak(), new KriterDeposu(':memory:'));

        $cevap = $app->calistir('/', 'GET', ['sirket' => "TTRAK\n"]);

        // Varsayilana dusmeli, "TTRAK\n" gecerli sayilmamali
        self::assertStringNotContainsString("TTRAK\n", $cevap['govde']);
        self::assertSame(200, $cevap['durum']);
    }

    // --- S5 (#15): HTTP metod ayrimi ---

    public function testKriterYolundaYazmaDisiMetodlar405Doner(): void
    {
        $app = new App(new BosKaynak(), new KriterDeposu(':memory:'), yetki: new Yetki(self::TOKEN));

        foreach (['PUT', 'DELETE', 'PATCH'] as $metod) {
            $cevap = $app->calistir('/kriter', $metod);
            self::assertSame(405, $cevap['durum'], "{$metod} 405 dönmeliydi");
        }
    }

    public function testGetVeHeadKriterYolundaCalisir(): void
    {
        $app = new App(new BosKaynak(), new KriterDeposu(':memory:'), yetki: new Yetki(self::TOKEN));

        self::assertSame(200, $app->calistir('/kriter', 'GET', sunulanToken: self::TOKEN)['durum']);
        self::assertSame(200, $app->calistir('/kriter', 'HEAD', sunulanToken: self::TOKEN)['durum']);
    }
}
