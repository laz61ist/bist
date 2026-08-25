<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Tarama ciktisi: uc grup + sayim.
 *
 * Skill kurali: "Sonuna tek paragraf: kac sirket tarandi, kaci elendi,
 * hangi kriter en cok eledi. Yorum yok, sayim var."
 */
final readonly class TaramaSonucu
{
    /** @param list<SirketSonucu> $sirketler */
    public function __construct(
        public KriterSeti $seti,
        private array $sirketler,
    ) {
    }

    public function taranan(): int
    {
        return count($this->sirketler);
    }

    /** @return list<SirketSonucu> */
    public function gecenler(): array
    {
        return array_values(array_filter($this->sirketler, static fn (SirketSonucu $s): bool => $s->gecti()));
    }

    /** @return list<SirketSonucu> */
    public function kalanlar(): array
    {
        return array_values(array_filter(
            $this->sirketler,
            static fn (SirketSonucu $s): bool => $s->degerlendirilebildi() && !$s->gecti(),
        ));
    }

    /** @return list<SirketSonucu> */
    public function degerlendirilemeyenler(): array
    {
        return array_values(array_filter(
            $this->sirketler,
            static fn (SirketSonucu $s): bool => !$s->degerlendirilebildi(),
        ));
    }

    /** En cok sirket eleyen kriterin adi; eleyen yoksa null. */
    public function enCokEleyenKriter(): ?string
    {
        $sayim = [];
        foreach ($this->kalanlar() as $s) {
            foreach ($s->kalinanKriterler() as $ad) {
                $sayim[$ad] = ($sayim[$ad] ?? 0) + 1;
            }
        }

        if ($sayim === []) {
            return null;
        }

        arsort($sayim);

        return (string) array_key_first($sayim);
    }

    /**
     * Sayim paragrafi. Hukum, niteleme veya oneri icermez.
     */
    public function ozet(): string
    {
        $satirlar = [
            sprintf('%d şirket tarandı, %d kriterle.', $this->taranan(), $this->seti->sayi()),
            sprintf('%d şirket tüm kriterleri geçti.', count($this->gecenler())),
            sprintf('%d şirket en az bir kriterde kaldı.', count($this->kalanlar())),
        ];

        if ($this->degerlendirilemeyenler() !== []) {
            $satirlar[] = sprintf(
                '%d şirket veri eksikliği nedeniyle değerlendirilemedi.',
                count($this->degerlendirilemeyenler()),
            );
        }

        $enCok = $this->enCokEleyenKriter();
        if ($enCok !== null) {
            $satirlar[] = "En çok eleyen kriter: {$enCok}.";
        }

        $satirlar[] = 'Karar senin.';

        return implode(' ', $satirlar);
    }
}
