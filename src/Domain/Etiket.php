<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Kokpit etiket sistemi.
 *
 * proje-talimati.md BOLUM 2, "Zorunlu kurallar" madde 5'te tanimlanan bes etiket.
 * Sira anlamlidir: en kritik (veri yok) en ustte, en iyi (veri tam) en altta.
 */
enum Etiket
{
    /** Rakam bulunamadi. */
    case KAYNAKSIZ;

    /** Enflasyon duzeltmesi (TMS 29) teyit edilmedi. */
    case DUZELTME_YOK;

    /** Islem hacmi dusuk. */
    case DUSUK_LIKIDITE;

    /** Medyandan belirgin sapma. */
    case AYKIRI;

    /** Veri eksiksiz. */
    case TAM;

    /** Panoda gorunen Turkce metin. */
    public function metin(): string
    {
        return match ($this) {
            self::KAYNAKSIZ => 'KAYNAKSIZ',
            self::DUZELTME_YOK => 'DÜZELTME YOK',
            self::DUSUK_LIKIDITE => 'DÜŞÜK LİKİDİTE',
            self::AYKIRI => 'AYKIRI',
            self::TAM => 'TAM',
        };
    }

    /** Tasarim sistemindeki renk anahtari. */
    public function renk(): string
    {
        return match ($this) {
            self::KAYNAKSIZ, self::AYKIRI => 'turuncu',
            self::DUZELTME_YOK, self::DUSUK_LIKIDITE => 'gri',
            self::TAM => 'yesil',
        };
    }
}
