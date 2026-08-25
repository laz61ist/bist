<?php

declare(strict_types=1);

namespace Bist\Data;

use Bist\Domain\KriterSeti;

/** Kaydedilmis bir kriter seti. */
final readonly class KriterKaydi
{
    public function __construct(
        public int $id,
        public string $ad,
        public KriterSeti $seti,
        public string $gerekce,
        public string $olusturma,
    ) {
    }
}
