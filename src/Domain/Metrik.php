<?php

declare(strict_types=1);

namespace Bist\Domain;

use InvalidArgumentException;

/**
 * Tek bir finansal metrik: deger + kokeni + etiketleri.
 *
 * Degismezdir (immutable). Etiket eklemek yeni ornek dondurur.
 *
 * Tasidigi kurallar (docs/REHBER-SAYFA-HARITASI.md):
 * - G-01 Degeri olan metrigin kaynagi olmak zorunda.
 * - G-02 Degeri olan metrigin donemi olmak zorunda.
 * - G-13 Her metrigin "ne demek" satiri olmak zorunda.
 * - G-14 Degeri olmayan metrigin eksik nedeni olmak zorunda; gorunen deger "—".
 */
final readonly class Metrik
{
    /** @var list<Etiket> */
    private array $elleEklenen;

    /**
     * @param list<Etiket> $elleEklenen
     */
    public function __construct(
        public string $ad,
        public string $neDemek,
        public ?float $deger = null,
        public string $birim = '',
        public ?string $donem = null,
        public ?string $kaynak = null,
        public ?Tms29Durum $tms29 = null,
        private ?string $eksikNedeni = null,
        array $elleEklenen = [],
    ) {
        if (trim($this->neDemek) === '') {
            throw new InvalidArgumentException(
                "Metrik '{$this->ad}': 'ne demek' satırı boş bırakılamaz (G-13).",
            );
        }

        if ($this->deger !== null) {
            if ($this->kaynak === null || trim($this->kaynak) === '') {
                throw new InvalidArgumentException(
                    "Metrik '{$this->ad}': kaynaksız rakam kullanılamaz (G-01).",
                );
            }
            if ($this->donem === null || trim($this->donem) === '') {
                throw new InvalidArgumentException(
                    "Metrik '{$this->ad}': rakamın dönemi belirtilmeden kullanılamaz (G-02).",
                );
            }
        } elseif ($this->eksikNedeni === null || trim($this->eksikNedeni) === '') {
            throw new InvalidArgumentException(
                "Metrik '{$this->ad}': değer yoksa eksik olma nedeni yazılmak zorunda (G-14).",
            );
        }

        $this->elleEklenen = array_values($elleEklenen);
    }

    public function vardir(): bool
    {
        return $this->deger !== null;
    }

    public function eksikNedeni(): ?string
    {
        return $this->eksikNedeni;
    }

    /** Yeni etiketli kopya dondurur; bu ornek degismez. */
    public function etiketle(Etiket $etiket): self
    {
        if (in_array($etiket, $this->elleEklenen, true)) {
            return $this;
        }

        return new self(
            ad: $this->ad,
            neDemek: $this->neDemek,
            deger: $this->deger,
            birim: $this->birim,
            donem: $this->donem,
            kaynak: $this->kaynak,
            tms29: $this->tms29,
            eksikNedeni: $this->eksikNedeni,
            elleEklenen: [...$this->elleEklenen, $etiket],
        );
    }

    /**
     * Metrigin tasidigi tum etiketler.
     *
     * TAM etiketi kazanilir, verilmez: deger var, kaynak var, donem var,
     * TMS 29 durumu teyit edilmis ve hicbir uyari etiketi yoksa eklenir.
     *
     * @return list<Etiket>
     */
    public function etiketler(): array
    {
        if (!$this->vardir()) {
            return [Etiket::KAYNAKSIZ, ...$this->elleEklenen];
        }

        $etiketler = $this->elleEklenen;

        if ($this->tms29 === null || $this->tms29 === Tms29Durum::BILINMIYOR) {
            $etiketler = [Etiket::DUZELTME_YOK, ...$etiketler];
        }

        return $etiketler === [] ? [Etiket::TAM] : array_values($etiketler);
    }

    /** Rakamin altinda gorunen kaynak satiri: "Fintables MCP · 2025/12". */
    public function kaynakSatiri(): string
    {
        if (!$this->vardir()) {
            return '';
        }

        return "{$this->kaynak} · {$this->donem}";
    }

    /** Panoda gorunen deger. Deger yoksa "—" (G-14). */
    public function gorunenDeger(): string
    {
        if (!$this->vardir()) {
            return '—';
        }

        $d = $this->deger;

        if ($this->birim === '%') {
            return '%' . self::sayi($d);
        }

        if ($this->birim === 'x') {
            return self::sayi($d) . 'x';
        }

        $mutlak = abs($d);
        [$bolen, $son] = match (true) {
            $mutlak >= 1_000_000_000 => [1_000_000_000, ' milyar'],
            $mutlak >= 1_000_000 => [1_000_000, ' milyon'],
            $mutlak >= 1_000 => [1_000, ' bin'],
            default => [1, ''],
        };

        $govde = self::sayi($d / $bolen) . $son;

        return $this->birim === '' ? $govde : "{$govde} {$this->birim}";
    }

    /** Turkce sayi bicimi: binlik nokta, ondalik virgul, iki basamak. */
    private static function sayi(float $d): string
    {
        return number_format($d, 2, ',', '.');
    }
}
