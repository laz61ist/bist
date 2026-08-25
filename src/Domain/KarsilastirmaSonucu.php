<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Iki donem arasi karsilastirmanin TMS 29 acisindan durumu.
 *
 * Kaynak: bist-enflasyon-kontrolu SKILL.md, "3. Karar tablosunu uygula".
 */
enum KarsilastirmaSonucu
{
    /** Her iki donem de ayni olcu birimine duzeltilmis. Karsilastir. */
    case GECERLI;

    /** Biri duzeltilmis digeri degil. Karsilastirma YAPILMAZ. */
    case KARSILASTIRILAMAZ;

    /** Duzeltme durumu teyit edilmedi. Karsilastir ama etiketle. */
    case TEYIT_EDILMEDI;

    /** Ikisi de nominal. Karsilastir ama donem enflasyonunu da goster. */
    case NOMINAL;

    /** Donemlerden birinin degeri yok. */
    case VERI_YOK;
}
