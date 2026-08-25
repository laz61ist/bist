<?php

declare(strict_types=1);

namespace Bist\Domain;

use InvalidArgumentException;

/**
 * Kullanicinin tanimladigi esik kumesi.
 *
 * G-11: Esik tanimlanmadan tarama calistirilamaz. Bos set kurulamaz.
 */
final readonly class KriterSeti
{
    /** @var list<Kriter> */
    public array $kriterler;

    /** @param list<Kriter> $kriterler */
    public function __construct(array $kriterler)
    {
        if ($kriterler === []) {
            throw new InvalidArgumentException(
                'Kriter seti boş olamaz: eşik tanımlanmadan tarama yapılmaz (G-11). '
                . 'Eşikleri kullanıcı belirler.',
            );
        }

        $this->kriterler = array_values($kriterler);
    }

    public function sayi(): int
    {
        return count($this->kriterler);
    }
}
