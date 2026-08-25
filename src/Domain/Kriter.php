<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Kullanicinin koydugu tek bir esik.
 *
 * G-19: Sistem kendi esigini uretmez. Bu sinif yalnizca verileni tasir.
 */
final readonly class Kriter
{
    public function __construct(
        public string $ad,
        public string $metrikAnahtari,
        public Operator $operator,
        public float $esik,
    ) {
    }

    public function metin(): string
    {
        $esik = rtrim(rtrim(number_format($this->esik, 2, ',', '.'), '0'), ',');

        return "{$this->ad} {$this->operator->value} {$esik}";
    }
}
