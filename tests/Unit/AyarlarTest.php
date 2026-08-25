<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Config\Ayarlar;
use Bist\Config\Ortam;
use PHPUnit\Framework\TestCase;

/**
 * D1 (#5): Uygulamadaki tek getenv cagrisi E2E_DB_PATH idi.
 *
 * Production'da veritabani yolunu vermenin tek yolu, adi "bu bir E2E test
 * degiskenidir" diyen bir degiskeni set etmekti. Sonraki gelistiricinin
 * production yapilandirmasini test artigi sanip temizlemesi icin kurulmus tuzak.
 */
final class AyarlarTest extends TestCase
{
    /** @param array<string, string> $env */
    private function ayarlar(array $env, string $kok = '/uygulama'): Ayarlar
    {
        return Ayarlar::ortamdan($env, $kok);
    }

    // --- DB yolu oncelik sirasi ---

    public function testBistDbPathVarsaOKullanilir(): void
    {
        $a = $this->ayarlar(['BIST_DB_PATH' => '/data/bist.sqlite']);

        self::assertSame('/data/bist.sqlite', $a->dbYolu);
    }

    public function testBistDbPathYoksaE2eDbPathKullanilir(): void
    {
        // gecis donemi: mevcut E2E kosucusu bozulmamali
        $a = $this->ayarlar(['E2E_DB_PATH' => '/tmp/e2e.sqlite']);

        self::assertSame('/tmp/e2e.sqlite', $a->dbYolu);
    }

    public function testBistDbPathE2eDbPathTenOnceliklidir(): void
    {
        $a = $this->ayarlar([
            'BIST_DB_PATH' => '/data/bist.sqlite',
            'E2E_DB_PATH' => '/tmp/e2e.sqlite',
        ]);

        self::assertSame('/data/bist.sqlite', $a->dbYolu);
    }

    public function testHicbiriYoksaKokAltindaVarsayilanKullanilir(): void
    {
        $a = $this->ayarlar([], '/uygulama');

        self::assertSame('/uygulama/var/bist.sqlite', $a->dbYolu);
    }

    public function testBosDegiskenSetEdilmemisSayilir(): void
    {
        $a = $this->ayarlar(['BIST_DB_PATH' => '   '], '/uygulama');

        self::assertSame('/uygulama/var/bist.sqlite', $a->dbYolu);
    }

    // --- Ortam: guvenli varsayilan ---

    public function testOrtamVarsayilaniProductiondir(): void
    {
        // Guvenli varsayilan: belirtilmediyse en kisitli mod
        self::assertSame(Ortam::PRODUCTION, $this->ayarlar([])->ortam);
    }

    public function testBistEnvOkunur(): void
    {
        self::assertSame(Ortam::DEVELOPMENT, $this->ayarlar(['BIST_ENV' => 'development'])->ortam);
        self::assertSame(Ortam::TEST, $this->ayarlar(['BIST_ENV' => 'test'])->ortam);
        self::assertSame(Ortam::PRODUCTION, $this->ayarlar(['BIST_ENV' => 'production'])->ortam);
    }

    public function testBistEnvBuyukKucukHarfDuyarsizdir(): void
    {
        self::assertSame(Ortam::DEVELOPMENT, $this->ayarlar(['BIST_ENV' => 'DEVELOPMENT'])->ortam);
    }

    public function testTanimsizOrtamDegeriProductionaDuser(): void
    {
        // Yazim hatasi guvenligi gevsetmemeli
        self::assertSame(Ortam::PRODUCTION, $this->ayarlar(['BIST_ENV' => 'developmnet'])->ortam);
    }

    // --- Ortamin davranissal sonuclari ---

    public function testProductiondaHataDetayiGosterilmez(): void
    {
        self::assertFalse(Ortam::PRODUCTION->hataDetayiGoster());
    }

    public function testGelistirmedeHataDetayiGosterilir(): void
    {
        self::assertTrue(Ortam::DEVELOPMENT->hataDetayiGoster());
        self::assertTrue(Ortam::TEST->hataDetayiGoster());
    }

    // --- govde siniri ayarlanabilir olmali ---

    public function testGovdeSiniriVarsayilani64KB(): void
    {
        self::assertSame(65536, $this->ayarlar([])->azamiGovdeBayt);
    }

    public function testGovdeSiniriEnvdenAyarlanabilir(): void
    {
        self::assertSame(1024, $this->ayarlar(['BIST_AZAMI_GOVDE_BAYT' => '1024'])->azamiGovdeBayt);
    }

    public function testGecersizGovdeSiniriVarsayilanaDuser(): void
    {
        self::assertSame(65536, $this->ayarlar(['BIST_AZAMI_GOVDE_BAYT' => 'abc'])->azamiGovdeBayt);
        self::assertSame(65536, $this->ayarlar(['BIST_AZAMI_GOVDE_BAYT' => '-5'])->azamiGovdeBayt);
        self::assertSame(65536, $this->ayarlar(['BIST_AZAMI_GOVDE_BAYT' => '0'])->azamiGovdeBayt);
    }
}
