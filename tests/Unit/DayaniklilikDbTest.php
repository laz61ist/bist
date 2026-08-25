<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Kriter;
use Bist\Domain\KriterSeti;
use Bist\Domain\Operator;
use Bist\Data\KriterDeposu;
use PHPUnit\Framework\TestCase;

/**
 * D3 (#7): Sqlite ephemeral diskteydi, WAL yoktu, busy_timeout yoktu.
 *
 * Bu sinif DB seviyesindeki dayanikliligi olcer. Volume baglama ve
 * yedekleme dagitim isi; burada test edilemez ama Dockerfile ve
 * README'de karsilanir.
 */
final class DayaniklilikDbTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        $this->db = tempnam(sys_get_temp_dir(), 'bist-day-') ?: '';
        @unlink($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->db, $this->db . '-wal', $this->db . '-shm'] as $f) {
            @unlink($f);
        }
    }

    private function seti(): KriterSeti
    {
        return new KriterSeti([new Kriter('F/K', 'fk', Operator::KUCUK, 12.0)]);
    }

    public function testWalModuAcik(): void
    {
        $d = new KriterDeposu($this->db);

        self::assertSame('wal', strtolower($d->pragma('journal_mode')));
    }

    public function testBusyTimeoutAyarli(): void
    {
        $d = new KriterDeposu($this->db);

        self::assertGreaterThan(0, (int) $d->pragma('busy_timeout'));
    }

    public function testYabanciAnahtarlarAcik(): void
    {
        $d = new KriterDeposu($this->db);

        self::assertSame('1', (string) $d->pragma('foreign_keys'));
    }

    public function testVeriYenidenAcildigindaKorunur(): void
    {
        $id = (new KriterDeposu($this->db))->kaydet('Kalıcı', $this->seti(), 'Gerekçem');

        $okunan = (new KriterDeposu($this->db))->getir($id);

        self::assertSame('Kalıcı', $okunan?->ad);
        self::assertSame('Gerekçem', $okunan?->gerekce);
    }

    public function testIkiBaglantiAyniAndaOkuyabilir(): void
    {
        $yazan = new KriterDeposu($this->db);
        $id = $yazan->kaydet('Set', $this->seti());

        $okuyan = new KriterDeposu($this->db);

        self::assertSame('Set', $okuyan->getir($id)?->ad);
        self::assertSame('Set', $yazan->getir($id)?->ad);
    }

    public function testBellekIciDbdeWalIstenmez(): void
    {
        // :memory: WAL desteklemez; kurulum patlamamali
        $d = new KriterDeposu(':memory:');

        self::assertTrue($d->calisiyorMu());
    }

    public function testYedekAlinabilir(): void
    {
        $d = new KriterDeposu($this->db);
        $d->kaydet('Yedeklenecek', $this->seti(), 'Gerekçe');

        $hedef = $this->db . '.yedek';
        $d->yedekle($hedef);

        self::assertFileExists($hedef);
        $yedek = new KriterDeposu($hedef);
        self::assertCount(1, $yedek->hepsi());
        self::assertSame('Yedeklenecek', $yedek->hepsi()[0]->ad);
        @unlink($hedef);
    }

    public function testYedekYazilamazYolaAlinamaz(): void
    {
        $d = new KriterDeposu($this->db);

        $this->expectException(\RuntimeException::class);
        $d->yedekle('/etc/passwd/alt/yedek.sqlite');
    }
}
