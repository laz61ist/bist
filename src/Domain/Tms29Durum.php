<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Bir rakamin TMS 29 (Yuksek Enflasyonlu Ekonomilerde Finansal Raporlama)
 * duzeltmesinden gecip gecmedigi.
 *
 * BILINMIYOR, "duzeltilmemis" ile ayni sey DEGILDIR: teyit edilmemis demektir.
 * Ikisi de karsilastirmayi engeller ama nedenleri farklidir.
 */
enum Tms29Durum
{
    case DUZELTILMIS;
    case DUZELTILMEMIS;
    case BILINMIYOR;

    public function metin(): string
    {
        return match ($this) {
            self::DUZELTILMIS => 'TMS 29 düzeltilmiş',
            self::DUZELTILMEMIS => 'TMS 29 düzeltilmemiş',
            self::BILINMIYOR => 'düzeltme durumu teyit edilmedi',
        };
    }
}
