<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Iki donemin TMS 29 acisindan karsilastirilabilir olup olmadigina karar verir.
 *
 * Rehberin Test 2'si tam olarak bunu olcer (docs/REHBER-SAYFA-HARITASI.md, sayfa 7):
 * duzeltme kontrolu yapilmadan hesaplanan buyume orani yanlis cikar ve
 * yanlis oldugu ciktidan anlasilmaz, cunku sayi makul gorunur.
 *
 * Karar tablosu: .claude/skills/bist-enflasyon-kontrolu/SKILL.md
 */
final class Tms29Kontrol
{
    public static function karsilastir(Metrik $eski, Metrik $yeni): Karsilastirma
    {
        if (!$eski->vardir() || !$yeni->vardir()) {
            return new Karsilastirma(
                sonuc: KarsilastirmaSonucu::VERI_YOK,
                buyumeYuzdesi: null,
                aciklama: 'Dönemlerden en az birinin değeri yok, karşılaştırma yapılamadı.',
            );
        }

        $sonuc = self::durumBelirle($eski->tms29, $yeni->tms29);

        if ($sonuc === KarsilastirmaSonucu::KARSILASTIRILAMAZ) {
            return new Karsilastirma(
                sonuc: $sonuc,
                buyumeYuzdesi: null,
                aciklama: sprintf(
                    'Dönemler farklı ölçü birimiyle ifade edilmiş (%s: %s, %s: %s). '
                    . 'Büyüme oranı hesaplanmadı.',
                    $eski->donem,
                    ($eski->tms29 ?? Tms29Durum::BILINMIYOR)->metin(),
                    $yeni->donem,
                    ($yeni->tms29 ?? Tms29Durum::BILINMIYOR)->metin(),
                ),
            );
        }

        $taban = (float) $eski->deger;

        if ($taban <= 0.0) {
            return new Karsilastirma(
                sonuc: $sonuc,
                buyumeYuzdesi: null,
                aciklama: sprintf(
                    'Başlangıç değeri %s olduğu için yüzde değişim tanımsızdır.',
                    $taban === 0.0 ? 'sıfır' : 'negatif',
                ),
            );
        }

        $buyume = (((float) $yeni->deger) - $taban) / $taban * 100.0;

        return new Karsilastirma(
            sonuc: $sonuc,
            buyumeYuzdesi: $buyume,
            aciklama: self::aciklama($sonuc),
            eskiDonem: $eski->donem,
            yeniDonem: $yeni->donem,
            kaynak: $eski->kaynak === $yeni->kaynak ? $eski->kaynak : "{$eski->kaynak} + {$yeni->kaynak}",
        );
    }

    private static function durumBelirle(?Tms29Durum $eski, ?Tms29Durum $yeni): KarsilastirmaSonucu
    {
        $eski ??= Tms29Durum::BILINMIYOR;
        $yeni ??= Tms29Durum::BILINMIYOR;

        if ($eski === Tms29Durum::BILINMIYOR || $yeni === Tms29Durum::BILINMIYOR) {
            return KarsilastirmaSonucu::TEYIT_EDILMEDI;
        }

        if ($eski !== $yeni) {
            return KarsilastirmaSonucu::KARSILASTIRILAMAZ;
        }

        return $eski === Tms29Durum::DUZELTILMIS
            ? KarsilastirmaSonucu::GECERLI
            : KarsilastirmaSonucu::NOMINAL;
    }

    private static function aciklama(KarsilastirmaSonucu $sonuc): string
    {
        return match ($sonuc) {
            KarsilastirmaSonucu::GECERLI =>
                'Her iki dönem de TMS 29 düzeltilmiş, aynı ölçü birimi. Karşılaştırma geçerli.',
            KarsilastirmaSonucu::TEYIT_EDILMEDI =>
                'Düzeltme durumu teyit edilmedi. Oran hesaplandı ancak doğrulanması gerekir.',
            KarsilastirmaSonucu::NOMINAL =>
                'Her iki dönem de nominal (düzeltilmemiş). Oran nominal büyümedir; '
                . 'reel değişim için dönem enflasyonuyla birlikte okunmalıdır.',
            KarsilastirmaSonucu::KARSILASTIRILAMAZ =>
                'Dönemler farklı ölçü birimiyle ifade edilmiş.',
            KarsilastirmaSonucu::VERI_YOK =>
                'Dönemlerden en az birinin değeri yok.',
        };
    }
}
