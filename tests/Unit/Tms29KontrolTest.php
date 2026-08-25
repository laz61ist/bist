<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Etiket;
use Bist\Domain\KarsilastirmaSonucu;
use Bist\Domain\Metrik;
use Bist\Domain\Tms29Durum;
use Bist\Domain\Tms29Kontrol;
use PHPUnit\Framework\TestCase;

/**
 * Kaynak: .claude/skills/bist-enflasyon-kontrolu/SKILL.md "3. Karar tablosunu uygula"
 *
 * | Durum                                   | Ne yapilir                          |
 * | Her iki donem de duzeltilmis            | Karsilastir, "TMS 29 duzeltilmis"   |
 * | Biri duzeltilmis digeri degil           | KARSILASTIRMA YAPMA                 |
 * | Durum belirsiz                          | Karsilastir + teyit edilmedi etiketi|
 * | Nominal (ikisi de duzeltilmemis)        | Karsilastir + donem enflasyonu yaz  |
 */
final class Tms29KontrolTest extends TestCase
{
    private function metrik(float $deger, string $donem, ?Tms29Durum $tms29): Metrik
    {
        return new Metrik(
            ad: 'Net satış',
            neDemek: 'Şirketin sattığı mal ve hizmetin toplam tutarı.',
            deger: $deger,
            birim: 'TL',
            donem: $donem,
            kaynak: 'KAP',
            tms29: $tms29,
        );
    }

    // --- Satir 1: her iki donem de duzeltilmis ---

    public function testIkiDonemDeDuzeltilmisseKarsilastirmaGecerlidir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertSame(KarsilastirmaSonucu::GECERLI, $k->sonuc);
        self::assertTrue($k->hesaplanabilir());
        self::assertEqualsWithDelta(50.0, $k->buyumeOrani(), 0.0001);
        self::assertNull($k->etiket());
        self::assertStringContainsString('TMS 29 düzeltilmiş', $k->aciklama());
    }

    // --- Satir 2: biri duzeltilmis digeri degil -> HESAPLAMA YOK ---

    public function testKarisikDurumdaBuyumeHesaplanmaz(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMEMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertSame(KarsilastirmaSonucu::KARSILASTIRILAMAZ, $k->sonuc);
        self::assertFalse($k->hesaplanabilir());
        self::assertNull($k->buyumeOrani());
        self::assertStringContainsString('farklı ölçü birimi', $k->aciklama());
    }

    public function testKarisikDurumTersSiradaDaKarsilastirilamaz(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMEMIS),
        );

        self::assertSame(KarsilastirmaSonucu::KARSILASTIRILAMAZ, $k->sonuc);
        self::assertNull($k->buyumeOrani());
    }

    // --- Satir 3: durum belirsiz -> hesapla ama etiketle ---

    public function testDurumBelirsizseHesaplanirAmaEtiketlenir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::BILINMIYOR),
            $this->metrik(150.0, '2024/12', Tms29Durum::BILINMIYOR),
        );

        self::assertSame(KarsilastirmaSonucu::TEYIT_EDILMEDI, $k->sonuc);
        self::assertTrue($k->hesaplanabilir());
        self::assertEqualsWithDelta(50.0, $k->buyumeOrani(), 0.0001);
        self::assertSame(Etiket::DUZELTME_YOK, $k->etiket());
    }

    public function testTms29DurumuHicVerilmemisseTeyitEdilmemisSayilir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', null),
            $this->metrik(150.0, '2024/12', null),
        );

        self::assertSame(KarsilastirmaSonucu::TEYIT_EDILMEDI, $k->sonuc);
    }

    // --- Satir 4: nominal seri -> hesapla ama enflasyon baglami iste ---

    public function testNominalSeridemBuyumeHesaplanirAmaEnflasyonUyarisiTasir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMEMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMEMIS),
        );

        self::assertSame(KarsilastirmaSonucu::NOMINAL, $k->sonuc);
        self::assertTrue($k->hesaplanabilir());
        self::assertEqualsWithDelta(50.0, $k->buyumeOrani(), 0.0001);
        self::assertTrue($k->enflasyonBaglamiGerekir());
        self::assertStringContainsString('nominal', $k->aciklama());
    }

    public function testGecerliKarsilastirmaEnflasyonBaglamiIstemez(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertFalse($k->enflasyonBaglamiGerekir());
    }

    // --- Kenar durumlar ---

    public function testTabanSifirsaBuyumeTanimsizdir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(0.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertFalse($k->hesaplanabilir());
        self::assertNull($k->buyumeOrani());
        self::assertStringContainsString('tanımsız', $k->aciklama());
    }

    public function testTabanNegatifseBuyumeYuzdesiTanimsizdir(): void
    {
        // -100'den 50'ye "gecis" yuzdesi yaniltici olur; oran uretilmez.
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(-100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(50.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertFalse($k->hesaplanabilir());
        self::assertNull($k->buyumeOrani());
        self::assertStringContainsString('tanımsız', $k->aciklama());
    }

    /**
     * Kaynak: TR literatur taramasi bulgusu — TMS 29 duzeltmesi sonrasi
     * FAVOK gibi kalemler pozitiften negatife donebiliyor. Bu durumda
     * yuzde degisim matematiksel olarak hesaplanir ama yaniltici olur
     * ("%-150 buyume" ifadesi anlamsizdir). Mutlak fark + uyari dondurulur.
     */
    public function testIsaretDegisimindeYuzdeDegisimUretilmez(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(-50.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertFalse($k->hesaplanabilir());
        self::assertNull($k->buyumeOrani());
        self::assertStringContainsString('işaret değiştirdi', $k->aciklama());
        self::assertEqualsWithDelta(-150.0, $k->mutlakFark(), 0.0001);
    }

    public function testIsaretDegismiyorsaNormalHesaplanir(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(40.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertTrue($k->hesaplanabilir());
        self::assertEqualsWithDelta(-60.0, $k->buyumeOrani(), 0.0001);
    }

    public function testDegerYoksaKarsilastirmaYapilmaz(): void
    {
        $eksik = new Metrik(ad: 'Net satış', neDemek: 'Açıklama.', eksikNedeni: 'Kaynak yok.');

        $k = Tms29Kontrol::karsilastir(
            $eksik,
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        self::assertFalse($k->hesaplanabilir());
        self::assertNull($k->buyumeOrani());
        self::assertSame(KarsilastirmaSonucu::VERI_YOK, $k->sonuc);
    }

    // --- G-08: sonuc metrige donusturulunce bos kalir ---

    public function testKarsilastirilamazSonucBosMetrigeDonusur(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMEMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        $m = $k->metrik('Net satış büyümesi', 'İki dönem arasındaki yüzde değişim.');

        self::assertFalse($m->vardir());
        self::assertSame('—', $m->gorunenDeger());
        self::assertStringContainsString('farklı ölçü birimi', (string) $m->eksikNedeni());
        self::assertContains(Etiket::KAYNAKSIZ, $m->etiketler());
    }

    public function testGecerliSonucDoluMetrigeDonusur(): void
    {
        $k = Tms29Kontrol::karsilastir(
            $this->metrik(100.0, '2022/12', Tms29Durum::DUZELTILMIS),
            $this->metrik(150.0, '2024/12', Tms29Durum::DUZELTILMIS),
        );

        $m = $k->metrik('Net satış büyümesi', 'İki dönem arasındaki yüzde değişim.');

        self::assertTrue($m->vardir());
        self::assertSame('%50,00', $m->gorunenDeger());
        self::assertSame('2022/12 → 2024/12', $m->donem);
    }
}
