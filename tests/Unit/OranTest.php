<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Metrik;
use Bist\Domain\Oran;
use Bist\Domain\Tms29Durum;
use PHPUnit\Framework\TestCase;

/**
 * G-10: Payda sifir veya negatifse oran TANIMSIZ doner; sayi uretilmez.
 * Rehber Test 4: "Kar negatifse oranin tanimsiz oldugunu soyler, uydurmaz."
 */
final class OranTest extends TestCase
{
    private function m(string $ad, ?float $deger, string $birim = 'TL', string $kaynak = 'KAP'): Metrik
    {
        if ($deger === null) {
            return new Metrik(ad: $ad, neDemek: 'Açıklama.', eksikNedeni: 'Veri yok.');
        }

        return new Metrik(
            ad: $ad,
            neDemek: 'Açıklama.',
            deger: $deger,
            birim: $birim,
            donem: '2025/12',
            kaynak: $kaynak,
            tms29: Tms29Durum::DUZELTILMIS,
        );
    }

    public function testNormalDurumdaOranHesaplanir(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Hisse fiyatının yıllık kâra oranı.',
            $this->m('Fiyat', 142.0),
            $this->m('Hisse başına kâr', 10.0),
        );

        self::assertTrue($o->vardir());
        self::assertEqualsWithDelta(14.2, $o->deger, 0.0001);
        self::assertSame('14,20x', $o->gorunenDeger());
    }

    public function testPaydaSifirsaOranTanimsizdir(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Hisse fiyatının yıllık kâra oranı.',
            $this->m('Fiyat', 142.0),
            $this->m('Hisse başına kâr', 0.0),
        );

        self::assertFalse($o->vardir());
        self::assertSame('—', $o->gorunenDeger());
        self::assertStringContainsString('tanımsız', (string) $o->eksikNedeni());
    }

    public function testPaydaNegatifseOranTanimsizdir(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Hisse fiyatının yıllık kâra oranı.',
            $this->m('Fiyat', 142.0),
            $this->m('Hisse başına kâr', -3.5),
        );

        self::assertFalse($o->vardir());
        self::assertStringContainsString('tanımsız', (string) $o->eksikNedeni());
        self::assertStringContainsString('negatif', (string) $o->eksikNedeni());
    }

    public function testPayEksikseOranHesaplanmaz(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Açıklama.',
            $this->m('Fiyat', null),
            $this->m('Hisse başına kâr', 10.0),
        );

        self::assertFalse($o->vardir());
        self::assertStringContainsString('Fiyat', (string) $o->eksikNedeni());
    }

    public function testOranIkiKaynagiBirlestirir(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Açıklama.',
            $this->m('Fiyat', 142.0, 'TL', 'Fintables MCP'),
            $this->m('Hisse başına kâr', 10.0, 'TL', 'KAP'),
        );

        self::assertStringContainsString('Fintables MCP', (string) $o->kaynak);
        self::assertStringContainsString('KAP', (string) $o->kaynak);
    }

    public function testAyniKaynaktaTekrarYazilmaz(): void
    {
        $o = Oran::hesapla(
            'F/K',
            'Açıklama.',
            $this->m('Fiyat', 142.0, 'TL', 'KAP'),
            $this->m('Hisse başına kâr', 10.0, 'TL', 'KAP'),
        );

        self::assertSame('KAP', $o->kaynak);
    }
}
