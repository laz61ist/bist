<?php

declare(strict_types=1);

namespace Bist\Domain;

/** Kriter esigi karsilastirma operatoru. */
enum Operator: string
{
    case KUCUK = '<';
    case KUCUK_ESIT = '<=';
    case BUYUK = '>';
    case BUYUK_ESIT = '>=';

    public function karsilar(float $deger, float $esik): bool
    {
        return match ($this) {
            self::KUCUK => $deger < $esik,
            self::KUCUK_ESIT => $deger <= $esik,
            self::BUYUK => $deger > $esik,
            self::BUYUK_ESIT => $deger >= $esik,
        };
    }
}
