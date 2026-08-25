<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Bir TMS 29 karsilastirmasinin sonucu.
 *
 * Hesaplanamayan durumda buyume orani null kalir; bu bir hata degil,
 * kuralin geregidir (G-08). Sayi uretmemek, yanlis sayi uretmekten iyidir.
 */
final readonly class Karsilastirma
{
    public function __construct(
        public KarsilastirmaSonucu $sonuc,
        public ?float $buyumeYuzdesi,
        private string $aciklama,
        public ?string $eskiDonem = null,
        public ?string $yeniDonem = null,
        public ?string $kaynak = null,
    ) {
    }

    public function aciklama(): string
    {
        return $this->aciklama;
    }

    public function hesaplanabilir(): bool
    {
        return $this->buyumeYuzdesi !== null;
    }

    public function buyumeOrani(): ?float
    {
        return $this->buyumeYuzdesi;
    }

    /** Sonucun tasidigi uyari etiketi; yoksa null. */
    public function etiket(): ?Etiket
    {
        return match ($this->sonuc) {
            KarsilastirmaSonucu::TEYIT_EDILMEDI, KarsilastirmaSonucu::NOMINAL => Etiket::DUZELTME_YOK,
            KarsilastirmaSonucu::KARSILASTIRILAMAZ, KarsilastirmaSonucu::VERI_YOK => Etiket::KAYNAKSIZ,
            KarsilastirmaSonucu::GECERLI => null,
        };
    }

    /**
     * Nominal seride buyume yazilir ama yanina donem enflasyonu da yazilmalidir
     * ki kullanici reel mi nominal mi oldugunu gorsun (skill karar tablosu, satir 4).
     */
    public function enflasyonBaglamiGerekir(): bool
    {
        return $this->sonuc === KarsilastirmaSonucu::NOMINAL;
    }

    /** Sonucu panoda gosterilecek metrige cevirir. */
    public function metrik(string $ad, string $neDemek): Metrik
    {
        if (!$this->hesaplanabilir()) {
            return new Metrik(ad: $ad, neDemek: $neDemek, eksikNedeni: $this->aciklama);
        }

        $m = new Metrik(
            ad: $ad,
            neDemek: $neDemek,
            deger: $this->buyumeYuzdesi,
            birim: '%',
            donem: "{$this->eskiDonem} → {$this->yeniDonem}",
            kaynak: $this->kaynak ?? 'hesaplanmış',
            tms29: $this->sonuc === KarsilastirmaSonucu::GECERLI
                ? Tms29Durum::DUZELTILMIS
                : Tms29Durum::BILINMIYOR,
        );

        $etiket = $this->etiket();

        return $etiket === null ? $m : $m->etiketle($etiket);
    }
}
