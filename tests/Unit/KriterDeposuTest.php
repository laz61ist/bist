<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Data\KriterDeposu;
use Bist\Domain\Kriter;
use Bist\Domain\KriterSeti;
use Bist\Domain\Operator;
use PHPUnit\Framework\TestCase;

/**
 * Kriter setleri sqlite'ta saklanir.
 *
 * Gerekce (arastirma bulgusu, otomasyon yanliligi literaturu): kullanicinin
 * kendi esigini ve GEREKCESINI yazmasi, cikti uzerinde hesap verebilirlik
 * yaratir. Esik sistemin degil kullanicinin olur (G-19).
 */
final class KriterDeposuTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        $this->db = tempnam(sys_get_temp_dir(), 'bist-test-') ?: '';
        @unlink($this->db);
    }

    protected function tearDown(): void
    {
        @unlink($this->db);
    }

    private function seti(): KriterSeti
    {
        return new KriterSeti([
            new Kriter('F/K', 'fk', Operator::KUCUK, 12.0),
            new Kriter('Net borç/FAVÖK', 'net_borc_favok', Operator::KUCUK, 2.0),
        ]);
    }

    public function testDepoKendiSemasiniKurar(): void
    {
        $d = new KriterDeposu($this->db);

        self::assertSame([], $d->hepsi());
    }

    public function testKriterSetiKaydedilirVeGeriOkunur(): void
    {
        $d = new KriterDeposu($this->db);
        $id = $d->kaydet('Muhafazakâr set', $this->seti(), 'Borçlu şirket istemiyorum.');

        $kayit = $d->getir($id);

        self::assertNotNull($kayit);
        self::assertSame('Muhafazakâr set', $kayit->ad);
        self::assertSame('Borçlu şirket istemiyorum.', $kayit->gerekce);
        self::assertCount(2, $kayit->seti->kriterler);
        self::assertSame('F/K', $kayit->seti->kriterler[0]->ad);
        self::assertSame(12.0, $kayit->seti->kriterler[0]->esik);
        self::assertSame(Operator::KUCUK, $kayit->seti->kriterler[0]->operator);
    }

    public function testGerekceZorunluDegildirAmaBosStringOlarakSaklanir(): void
    {
        $d = new KriterDeposu($this->db);
        $id = $d->kaydet('Set', $this->seti());

        self::assertSame('', $d->getir($id)?->gerekce);
    }

    public function testKayitZamanDamgasiTasir(): void
    {
        $d = new KriterDeposu($this->db);
        $id = $d->kaydet('Set', $this->seti());

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $d->getir($id)?->olusturma,
        );
    }

    public function testBirdenFazlaSetYeniIlkSirada(): void
    {
        $d = new KriterDeposu($this->db);
        $d->kaydet('Birinci', $this->seti());
        $d->kaydet('İkinci', $this->seti());

        $hepsi = $d->hepsi();

        self::assertCount(2, $hepsi);
        self::assertSame('İkinci', $hepsi[0]->ad);
    }

    public function testOlmayanIdNullDonner(): void
    {
        self::assertNull((new KriterDeposu($this->db))->getir(999));
    }

    public function testAyniDosyaYenidenAcildigindaVeriKorunur(): void
    {
        $id = (new KriterDeposu($this->db))->kaydet('Kalıcı', $this->seti());

        self::assertSame('Kalıcı', (new KriterDeposu($this->db))->getir($id)?->ad);
    }

    public function testSqlEnjeksiyonuDenemesiVeriYiBozmaz(): void
    {
        $d = new KriterDeposu($this->db);
        $kotu = "'; DROP TABLE kriter_seti; --";
        $id = $d->kaydet($kotu, $this->seti());

        self::assertSame($kotu, $d->getir($id)?->ad);
        self::assertCount(1, $d->hepsi());
    }
}
