<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Iki metrikten oran uretir.
 *
 * G-10: Payda sifir veya negatifse oran TANIMSIZ doner; sayi uretilmez.
 * Rehber Test 4 bunu olcer: "Kar negatifse oranin tanimsiz oldugunu
 * soyler, uydurmaz."
 *
 * Negatif kar ile hesaplanan F/K matematiksel olarak bir sayi verir ama
 * finansal olarak anlamsizdir; kullanici onu "dusuk carpan" diye okur.
 * Bu yuzden sayi hic uretilmez.
 */
final class Oran
{
    public static function hesapla(
        string $ad,
        string $neDemek,
        Metrik $pay,
        Metrik $payda,
        string $birim = 'x',
    ): Metrik {
        $eksikler = [];
        if (!$pay->vardir()) {
            $eksikler[] = $pay->ad;
        }
        if (!$payda->vardir()) {
            $eksikler[] = $payda->ad;
        }

        if ($eksikler !== []) {
            return new Metrik(
                ad: $ad,
                neDemek: $neDemek,
                eksikNedeni: sprintf('%s verisi yok, oran hesaplanamadı.', implode(' ve ', $eksikler)),
                nedenTipi: EksiklikNedeni::KAYNAK_YOK,
            );
        }

        $paydaDeger = (float) $payda->deger;

        if ($paydaDeger <= 0.0) {
            return new Metrik(
                ad: $ad,
                neDemek: $neDemek,
                eksikNedeni: sprintf(
                    '%s %s olduğu için oran tanımsızdır. Sayı üretilmedi.',
                    $payda->ad,
                    $paydaDeger === 0.0 ? 'sıfır' : 'negatif',
                ),
                nedenTipi: EksiklikNedeni::HESAPLANAMAZ,
            );
        }

        return new Metrik(
            ad: $ad,
            neDemek: $neDemek,
            deger: ((float) $pay->deger) / $paydaDeger,
            birim: $birim,
            donem: $pay->donem === $payda->donem
                ? (string) $pay->donem
                : "{$pay->donem} / {$payda->donem}",
            kaynak: $pay->kaynak === $payda->kaynak
                ? (string) $pay->kaynak
                : "{$pay->kaynak} + {$payda->kaynak}",
            tms29: $pay->tms29 === $payda->tms29 ? $pay->tms29 : Tms29Durum::BILINMIYOR,
        );
    }
}
