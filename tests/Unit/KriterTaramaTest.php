<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Kriter;
use Bist\Domain\KriterSeti;
use Bist\Domain\KriterTarama;
use Bist\Domain\Metrik;
use Bist\Domain\Operator;
use Bist\Domain\SirketSonucu;
use Bist\Domain\Tms29Durum;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Kaynak: .claude/skills/bist-kriter-taramasi/SKILL.md
 * - Kriterleri kullanici koyar, sistem uygular (G-19)
 * - Esik yoksa tarama calismaz (G-11)
 * - Veri eksikse "degerlendirilemedi", gecti/kaldi DEME
 * - Her hucrede gercek deger gorunur
 */
final class KriterTaramaTest extends TestCase
{
    private function m(string $ad, ?float $deger, string $birim = 'x'): Metrik
    {
        if ($deger === null) {
            return new Metrik(ad: $ad, neDemek: 'Açıklama.', eksikNedeni: 'Veri kaynağı bağlanmadı.');
        }

        return new Metrik(
            ad: $ad,
            neDemek: 'Açıklama.',
            deger: $deger,
            birim: $birim,
            donem: '2025/12',
            kaynak: 'KAP',
            tms29: Tms29Durum::DUZELTILMIS,
        );
    }

    // --- G-11: esik yoksa tarama yok ---

    public function testBosKriterSetiKurulamaz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('eşik');

        new KriterSeti([]);
    }

    // --- G-19: sistem kendi esigini uretmez, sadece verileni uygular ---

    public function testKriterKullanicininVerdigiEsigiSaklar(): void
    {
        $k = new Kriter('Net borç/FAVÖK', 'net_borc_favok', Operator::KUCUK, 2.0);

        self::assertSame(2.0, $k->esik);
        self::assertSame(Operator::KUCUK, $k->operator);
        self::assertSame('Net borç/FAVÖK < 2', $k->metin());
    }

    // --- gecti / kaldi ---

    public function testTumKriterleriGecenSirketGectiGrubunaGirer(): void
    {
        $seti = new KriterSeti([
            new Kriter('F/K', 'fk', Operator::KUCUK, 12.0),
            new Kriter('FAVÖK marjı', 'favok_marji', Operator::BUYUK, 15.0),
        ]);

        $sonuc = KriterTarama::uygula($seti, [
            'AAAAA' => ['fk' => $this->m('F/K', 9.4), 'favok_marji' => $this->m('FAVÖK marjı', 18.4, '%')],
        ]);

        self::assertSame(['AAAAA'], array_map(static fn (SirketSonucu $s) => $s->kod, $sonuc->gecenler()));
        self::assertSame([], $sonuc->kalanlar());
        self::assertSame([], $sonuc->degerlendirilemeyenler());
    }

    public function testTekKriterdeKalanSirketKaldiGrubunaGirer(): void
    {
        $seti = new KriterSeti([
            new Kriter('F/K', 'fk', Operator::KUCUK, 12.0),
            new Kriter('FAVÖK marjı', 'favok_marji', Operator::BUYUK, 15.0),
        ]);

        $sonuc = KriterTarama::uygula($seti, [
            'BBBBB' => ['fk' => $this->m('F/K', 14.2), 'favok_marji' => $this->m('FAVÖK marjı', 18.4, '%')],
        ]);

        $kalan = $sonuc->kalanlar()[0];
        self::assertSame('BBBBB', $kalan->kod);
        self::assertSame(['F/K'], $kalan->kalinanKriterler());
    }

    // --- veri eksikse degerlendirilemedi ---

    public function testVerisiEksikSirketGectiVeyaKaldiDemekYerineAyriGrubaGirer(): void
    {
        $seti = new KriterSeti([
            new Kriter('F/K', 'fk', Operator::KUCUK, 12.0),
            new Kriter('FAVÖK marjı', 'favok_marji', Operator::BUYUK, 15.0),
        ]);

        $sonuc = KriterTarama::uygula($seti, [
            // F/K esigi gecerdi ama FAVOK marji yok -> yine de "gecti" DENMEZ
            'CCCCC' => ['fk' => $this->m('F/K', 9.0), 'favok_marji' => $this->m('FAVÖK marjı', null)],
        ]);

        self::assertSame([], $sonuc->gecenler());
        self::assertSame([], $sonuc->kalanlar());
        $d = $sonuc->degerlendirilemeyenler()[0];
        self::assertSame('CCCCC', $d->kod);
        self::assertSame(['FAVÖK marjı'], $d->eksikKriterler());
    }

    public function testMetrikHicVerilmemisseDeDegerlendirilemez(): void
    {
        $seti = new KriterSeti([new Kriter('F/K', 'fk', Operator::KUCUK, 12.0)]);

        $sonuc = KriterTarama::uygula($seti, ['DDDDD' => []]);

        self::assertSame([], $sonuc->gecenler());
        self::assertCount(1, $sonuc->degerlendirilemeyenler());
    }

    // --- her hucrede gercek deger ---

    public function testHerKriterHucresiGercekDegeriTasir(): void
    {
        $seti = new KriterSeti([new Kriter('F/K', 'fk', Operator::KUCUK, 12.0)]);

        $sonuc = KriterTarama::uygula($seti, ['EEEEE' => ['fk' => $this->m('F/K', 14.2)]]);

        $hucre = $sonuc->kalanlar()[0]->hucreler()['F/K'];
        self::assertSame('14,20x', $hucre->gorunenDeger);
        self::assertFalse($hucre->gecti);
        self::assertTrue($hucre->degerlendirildi);
    }

    public function testEksikHucreVeriYokYazar(): void
    {
        $seti = new KriterSeti([new Kriter('F/K', 'fk', Operator::KUCUK, 12.0)]);

        $sonuc = KriterTarama::uygula($seti, ['FFFFF' => ['fk' => $this->m('F/K', null)]]);

        $hucre = $sonuc->degerlendirilemeyenler()[0]->hucreler()['F/K'];
        self::assertSame('—', $hucre->gorunenDeger);
        self::assertFalse($hucre->degerlendirildi);
    }

    // --- operatorler ---

    /**
     * @return array<string, array{Operator, float, float, bool}>
     */
    public static function operatorSaglayici(): array
    {
        return [
            'kucuk gecer'      => [Operator::KUCUK, 9.0, 12.0, true],
            'kucuk kalir'      => [Operator::KUCUK, 14.0, 12.0, false],
            'kucuk sinirda'    => [Operator::KUCUK, 12.0, 12.0, false],
            'kucuk esit gecer' => [Operator::KUCUK_ESIT, 12.0, 12.0, true],
            'buyuk gecer'      => [Operator::BUYUK, 18.0, 15.0, true],
            'buyuk sinirda'    => [Operator::BUYUK, 15.0, 15.0, false],
            'buyuk esit gecer' => [Operator::BUYUK_ESIT, 15.0, 15.0, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('operatorSaglayici')]
    public function testOperatorlerSinirDegerlerdeDogruCalisir(
        Operator $op,
        float $deger,
        float $esik,
        bool $beklenen,
    ): void {
        self::assertSame($beklenen, $op->karsilar($deger, $esik));
    }

    // --- ozet: yorum yok, sayim var ---

    public function testOzetSadeceSayimVerir(): void
    {
        $seti = new KriterSeti([
            new Kriter('F/K', 'fk', Operator::KUCUK, 12.0),
            new Kriter('FAVÖK marjı', 'favok_marji', Operator::BUYUK, 15.0),
        ]);

        $sonuc = KriterTarama::uygula($seti, [
            'AAAAA' => ['fk' => $this->m('F/K', 9.4), 'favok_marji' => $this->m('FAVÖK marjı', 18.4, '%')],
            'BBBBB' => ['fk' => $this->m('F/K', 14.2), 'favok_marji' => $this->m('FAVÖK marjı', 18.4, '%')],
            'CCCCC' => ['fk' => $this->m('F/K', 20.0), 'favok_marji' => $this->m('FAVÖK marjı', 2.0, '%')],
            'DDDDD' => ['fk' => $this->m('F/K', null), 'favok_marji' => $this->m('FAVÖK marjı', 18.4, '%')],
        ]);

        self::assertSame(4, $sonuc->taranan());
        self::assertCount(1, $sonuc->gecenler());
        self::assertCount(2, $sonuc->kalanlar());
        self::assertCount(1, $sonuc->degerlendirilemeyenler());
        self::assertSame('F/K', $sonuc->enCokEleyenKriter());

        $ozet = $sonuc->ozet();
        self::assertStringContainsString('4 şirket tarandı', $ozet);
        self::assertStringContainsString('1 şirket tüm kriterleri geçti', $ozet);
        self::assertStringContainsString('Karar senin', $ozet);
    }

    public function testOzetTavsiyeIfadesiIcermez(): void
    {
        $seti = new KriterSeti([new Kriter('F/K', 'fk', Operator::KUCUK, 12.0)]);
        $sonuc = KriterTarama::uygula($seti, ['AAAAA' => ['fk' => $this->m('F/K', 9.0)]]);

        $yasak = ['cazip', 'fırsat', 'ucuz', 'pahalı', 'al', 'sat', 'öneri', 'tavsiye', 'portföy'];
        $ozet = mb_strtolower($sonuc->ozet(), 'UTF-8');

        foreach ($yasak as $kelime) {
            self::assertStringNotContainsString(" {$kelime} ", " {$ozet} ", "Özette yasak ifade: {$kelime}");
        }
    }
}
