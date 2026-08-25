<?php

declare(strict_types=1);

namespace Bist\Config;

/**
 * Uygulama yapilandirmasi. Deger yalnizca ortamdan okunur; kod deger bilmez
 * (anayasa madde IX).
 *
 * D1 (#5): Onceden tek getenv cagrisi `E2E_DB_PATH` idi — yani production
 * yapilandirmasinin tek yolu, adi "bu bir test degiskenidir" diyen bir
 * degiskeni set etmekti. `BIST_DB_PATH` bunu duzeltir; `E2E_DB_PATH` gecis
 * donemi icin desteklenmeye devam eder, boylece mevcut E2E kosucusu bozulmaz.
 */
final readonly class Ayarlar
{
    public function __construct(
        public string $dbYolu,
        public Ortam $ortam,
        public int $azamiGovdeBayt,
        public ?string $yazmaToken = null,
    ) {
    }

    /**
     * @param array<string, string> $env
     * @param string $kok Uygulama kok dizini (varsayilan DB yolu icin)
     */
    public static function ortamdan(array $env, string $kok): self
    {
        return new self(
            dbYolu: self::metin($env, 'BIST_DB_PATH')
                ?? self::metin($env, 'E2E_DB_PATH')
                ?? rtrim($kok, '/') . '/var/bist.sqlite',
            ortam: Ortam::tryFrom(strtolower(self::metin($env, 'BIST_ENV') ?? ''))
                ?? Ortam::PRODUCTION,
            azamiGovdeBayt: self::pozitifTamsayi($env, 'BIST_AZAMI_GOVDE_BAYT') ?? 65536,
            // Yoksa yazma KAPALI kalir (D6 / #10) — guvenli varsayilan.
            yazmaToken: self::metin($env, 'BIST_YAZMA_TOKEN'),
        );
    }

    /** Genel kullanim: gercek ortam degiskenlerinden kur. */
    public static function global(string $kok): self
    {
        /** @var array<string, string> $env */
        $env = getenv();

        return self::ortamdan($env, $kok);
    }

    /** @param array<string, string> $env */
    private static function metin(array $env, string $ad): ?string
    {
        $deger = trim($env[$ad] ?? '');

        return $deger === '' ? null : $deger;
    }

    /** @param array<string, string> $env */
    private static function pozitifTamsayi(array $env, string $ad): ?int
    {
        $ham = self::metin($env, $ad);
        if ($ham === null || !ctype_digit($ham)) {
            return null;
        }

        $deger = (int) $ham;

        return $deger > 0 ? $deger : null;
    }
}
