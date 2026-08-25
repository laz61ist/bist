<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Etiket;
use Bist\Domain\Metrik;
use Bist\Domain\Tms29Durum;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MetrikTest extends TestCase
{
    // --- G-01: kaynaksiz rakam yok ---

    public function testDegerVarkenKaynakYoksaMetrikKurulamaz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kaynaksız');

        new Metrik(
            ad: 'Net satış',
            neDemek: 'Şirketin bir yılda sattığı mal ve hizmetin toplam tutarı.',
            deger: 12_400_000_000.0,
            birim: 'TL',
            donem: '2025/12',
            kaynak: null,
        );
    }

    public function testDegerVarkenDonemYoksaMetrikKurulamaz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dönem');

        new Metrik(
            ad: 'Net satış',
            neDemek: 'Açıklama.',
            deger: 12_400_000_000.0,
            birim: 'TL',
            donem: null,
            kaynak: 'Fintables MCP',
        );
    }

    // --- G-13: her rakamin yaninda "ne demek" satiri ---

    public function testNeDemekBosBirakilamaz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ne demek');

        new Metrik(ad: 'Net satış', neDemek: '   ');
    }

    // --- G-14: eksik veri gizlenmez ---

    public function testDegersizMetrikOtomatikKaynaksizEtiketiAlir(): void
    {
        $m = new Metrik(
            ad: 'F/K',
            neDemek: 'Hisse fiyatının yıllık kâra oranı.',
            eksikNedeni: 'Veri kaynağı bağlanmadı.',
        );

        self::assertFalse($m->vardir());
        self::assertContains(Etiket::KAYNAKSIZ, $m->etiketler());
        self::assertSame('—', $m->gorunenDeger());
        self::assertSame('Veri kaynağı bağlanmadı.', $m->eksikNedeni());
    }

    public function testDegersizMetrikNedenSizKurulamaz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('neden');

        new Metrik(ad: 'F/K', neDemek: 'Açıklama.');
    }

    // --- G-02: her rakamin yaninda donem ve kaynak ---

    public function testDoluMetrikKaynagiVeDonemiBirlikteYazar(): void
    {
        $m = new Metrik(
            ad: 'Net satış',
            neDemek: 'Açıklama.',
            deger: 12_400_000_000.0,
            birim: 'TL',
            donem: '2025/12',
            kaynak: 'Fintables MCP',
            tms29: Tms29Durum::DUZELTILMIS,
        );

        self::assertTrue($m->vardir());
        self::assertSame('Fintables MCP · 2025/12', $m->kaynakSatiri());
        self::assertContains(Etiket::TAM, $m->etiketler());
        self::assertNotContains(Etiket::KAYNAKSIZ, $m->etiketler());
    }

    public function testTms29BilinmiyorsaDuzeltmeYokEtiketiEklenir(): void
    {
        $m = new Metrik(
            ad: 'Net satış',
            neDemek: 'Açıklama.',
            deger: 100.0,
            birim: 'TL',
            donem: '2022/12',
            kaynak: 'KAP',
            tms29: Tms29Durum::BILINMIYOR,
        );

        self::assertContains(Etiket::DUZELTME_YOK, $m->etiketler());
        self::assertNotContains(Etiket::TAM, $m->etiketler());
    }

    // --- sayi bicimlendirme ---

    /**
     * @return array<string, array{float, string, string}>
     */
    public static function bicimSaglayici(): array
    {
        return [
            'milyar TL'  => [12_400_000_000.0, 'TL', '12,40 milyar TL'],
            'milyon TL'  => [345_000_000.0, 'TL', '345,00 milyon TL'],
            'bin TL'     => [12_500.0, 'TL', '12,50 bin TL'],
            'kucuk TL'   => [842.0, 'TL', '842,00 TL'],
            'yuzde'      => [18.4, '%', '%18,40'],
            'carpan'     => [14.2, 'x', '14,20x'],
            'negatif'    => [-3_200_000.0, 'TL', '-3,20 milyon TL'],
            'sifir'      => [0.0, 'TL', '0,00 TL'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bicimSaglayici')]
    public function testDegerTurkceBicimdeGosterilir(float $deger, string $birim, string $beklenen): void
    {
        $m = new Metrik(
            ad: 'Test',
            neDemek: 'Açıklama.',
            deger: $deger,
            birim: $birim,
            donem: '2025/12',
            kaynak: 'KAP',
            tms29: Tms29Durum::DUZELTILMIS,
        );

        self::assertSame($beklenen, $m->gorunenDeger());
    }

    // --- ek etiketler ---

    public function testDusukLikiditeVeAykiriEtiketiElleEklenebilir(): void
    {
        $m = (new Metrik(
            ad: 'F/K',
            neDemek: 'Açıklama.',
            deger: 14.2,
            birim: 'x',
            donem: '2025/12',
            kaynak: 'Fintables MCP',
            tms29: Tms29Durum::DUZELTILMIS,
        ))->etiketle(Etiket::DUSUK_LIKIDITE)->etiketle(Etiket::AYKIRI);

        self::assertContains(Etiket::DUSUK_LIKIDITE, $m->etiketler());
        self::assertContains(Etiket::AYKIRI, $m->etiketler());
        // veri eksiksiz degilse TAM dusmelidir
        self::assertNotContains(Etiket::TAM, $m->etiketler());
    }

    public function testMetrikDegismezdir(): void
    {
        $m = new Metrik(ad: 'F/K', neDemek: 'Açıklama.', eksikNedeni: 'Kaynak yok.');
        $m2 = $m->etiketle(Etiket::DUSUK_LIKIDITE);

        self::assertNotSame($m, $m2);
        self::assertNotContains(Etiket::DUSUK_LIKIDITE, $m->etiketler());
    }
}
