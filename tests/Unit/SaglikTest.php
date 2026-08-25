<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\App;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use PHPUnit\Framework\TestCase;

/**
 * D5 (#9): /saglik yalnizca sabit bir dize donduruyordu, hicbir seye
 * dokunmuyordu. Sqlite bozulsa, volume baglanmasa, disk dolsa bile
 * 200 {"durum":"ok"} donuyordu.
 *
 * Var olmayan health check'ten kotusu, yalan soyleyen health check'tir:
 * yuk dengeleyici hasta instance'a trafik gondermeye devam eder.
 */
final class SaglikTest extends TestCase
{
    /** @return array<string, mixed> */
    private function govde(array $cevap): array
    {
        return json_decode((string) $cevap['govde'], true, 8, JSON_THROW_ON_ERROR);
    }

    private function saglikliApp(): App
    {
        return new App(new BosKaynak(), new KriterDeposu(':memory:'));
    }

    private function bozukApp(): App
    {
        return new App(
            kaynak: new BosKaynak(),
            depoSaglayici: static fn (): KriterDeposu
                => new KriterDeposu('/etc/passwd/alt/bist.sqlite'),
        );
    }

    // --- Saglikli durum ---

    public function testSaglikliDurumda200Doner(): void
    {
        self::assertSame(200, $this->saglikliApp()->calistir('/saglik')['durum']);
    }

    public function testSaglikliDurumdaTumBilesenlerIyi(): void
    {
        $g = $this->govde($this->saglikliApp()->calistir('/saglik'));

        self::assertSame('ok', $g['durum']);
        self::assertSame('ok', $g['bilesenler']['db']);
    }

    // --- Bozuk durum: 503 ---

    public function testDbErisilemezken503Doner(): void
    {
        self::assertSame(503, $this->bozukApp()->calistir('/saglik')['durum']);
    }

    public function testDbErisilemezkenDurumBozukYazar(): void
    {
        $g = $this->govde($this->bozukApp()->calistir('/saglik'));

        self::assertSame('bozuk', $g['durum']);
        self::assertNotSame('ok', $g['bilesenler']['db']);
    }

    public function testHangiBilesenDustuguYazar(): void
    {
        $g = $this->govde($this->bozukApp()->calistir('/saglik'));

        self::assertArrayHasKey('bilesenler', $g);
        self::assertArrayHasKey('db', $g['bilesenler']);
        self::assertArrayHasKey('kaynak', $g['bilesenler']);
    }

    // --- Sizinti yok ---

    public function testBozukDurumdaIcAyrintiSizmaz(): void
    {
        $cevap = $this->bozukApp()->calistir('/saglik');

        self::assertStringNotContainsString('/etc/passwd', (string) $cevap['govde']);
        self::assertStringNotContainsString('PDOException', (string) $cevap['govde']);
        self::assertStringNotContainsString('Bist\\', (string) $cevap['govde']);
    }

    // --- Denetim GERCEKTEN sorgu atmali ---

    public function testSaglikDbYeGercektenSorguAtar(): void
    {
        $sorulan = false;
        $app = new App(
            kaynak: new BosKaynak(),
            depoSaglayici: function () use (&$sorulan): KriterDeposu {
                $sorulan = true;
                return new KriterDeposu(':memory:');
            },
        );

        $app->calistir('/saglik');

        self::assertTrue($sorulan, 'Sağlık denetimi veritabanına hiç dokunmadı');
    }

    public function testSaglikOnbelleklenmemeliBasligiTasir(): void
    {
        $cevap = $this->saglikliApp()->calistir('/saglik');

        self::assertSame('no-store', $cevap['basliklar']['Cache-Control'] ?? null);
    }
}
