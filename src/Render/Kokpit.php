<?php

declare(strict_types=1);

namespace Bist\Render;

use Bist\Data\VeriKaynagi;
use Bist\Domain\Metrik;
use Bist\Domain\TaramaSonucu;

/** Bir kokpit panosunun icerigi. */
final readonly class Kokpit
{
    /** @param list<Metrik> $metrikler */
    public function __construct(
        public string $sirketKodu,
        public VeriKaynagi $kaynak,
        public array $metrikler,
        public ?TaramaSonucu $tarama = null,
        public string $baslik = 'Kokpit',
        public string $altBaslik = 'Kaynaklı finansal analiz çıktısı.',
    ) {
    }
}
