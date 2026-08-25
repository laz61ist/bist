<?php

declare(strict_types=1);

namespace Bist\Data;

use Bist\Domain\Metrik;

/**
 * Kokpitin veri kaynagi.
 *
 * G-16: Kaynak degistirilebilir olmali. Fintables MCP, KAP Excel veya
 * JSON disa aktarma bu arayuzu uygular; kokpit hangisinin bagli
 * oldugunu bilmez.
 *
 * G-01 geregi: bir uygulama deger dondururken kaynak ve donem
 * bilgisini de doldurmak zorundadir, yoksa Metrik kurulamaz.
 */
interface VeriKaynagi
{
    /** Panoda gorunen kaynak adi. */
    public function ad(): string;

    /** Kaynak gercekten bagli mi. Degilse kokpit bunu ustte yazar (G-15). */
    public function bagli(): bool;

    /** Istenen metrik. Bulunamazsa DEGER YERINE bos Metrik doner, asla uydurmaz. */
    public function metrik(string $sirketKodu, string $anahtar): Metrik;
}
