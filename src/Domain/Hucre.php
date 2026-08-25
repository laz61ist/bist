<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Tarama tablosunda tek hucre.
 *
 * Skill kurali: "Her hucreye gercek degeri yaz, sadece OK/HATA koyma.
 * Kullanici sinirda olani gorsun."
 */
final readonly class Hucre
{
    public function __construct(
        public string $kriterAdi,
        public string $gorunenDeger,
        public bool $degerlendirildi,
        public bool $gecti,
    ) {
    }
}
