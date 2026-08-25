<?php

declare(strict_types=1);

namespace Bist\Domain;

/** Bir sirketin tarama sonucu. */
final readonly class SirketSonucu
{
    /** @param array<string, Hucre> $hucreler */
    public function __construct(
        public string $kod,
        private array $hucreler,
    ) {
    }

    /** @return array<string, Hucre> */
    public function hucreler(): array
    {
        return $this->hucreler;
    }

    /** @return list<string> Degerlendirilemeyen kriterlerin adlari. */
    public function eksikKriterler(): array
    {
        return array_values(array_map(
            static fn (Hucre $h): string => $h->kriterAdi,
            array_filter($this->hucreler, static fn (Hucre $h): bool => !$h->degerlendirildi),
        ));
    }

    /** @return list<string> Esigi karsilamayan kriterlerin adlari. */
    public function kalinanKriterler(): array
    {
        return array_values(array_map(
            static fn (Hucre $h): string => $h->kriterAdi,
            array_filter($this->hucreler, static fn (Hucre $h): bool => $h->degerlendirildi && !$h->gecti),
        ));
    }

    public function degerlendirilebildi(): bool
    {
        return $this->eksikKriterler() === [];
    }

    public function gecti(): bool
    {
        return $this->degerlendirilebildi() && $this->kalinanKriterler() === [];
    }
}
