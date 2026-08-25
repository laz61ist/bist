<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Kullanicinin esiklerini sirketlere uygular.
 *
 * Kaynak: .claude/skills/bist-kriter-taramasi/SKILL.md
 * - Esikleri kullanici koyar, bu sinif yalnizca uygular (G-19)
 * - Veri eksikse gecti/kaldi DENMEZ, "degerlendirilemedi" grubuna girer
 * - Her hucre gercek degeri tasir, sinirda olan gorunur
 */
final class KriterTarama
{
    /**
     * @param array<string, array<string, Metrik>> $sirketler
     *        sirket kodu => (metrik anahtari => Metrik)
     */
    public static function uygula(KriterSeti $seti, array $sirketler): TaramaSonucu
    {
        $sonuclar = [];

        foreach ($sirketler as $kod => $metrikler) {
            $hucreler = [];

            foreach ($seti->kriterler as $kriter) {
                $metrik = $metrikler[$kriter->metrikAnahtari] ?? null;

                if ($metrik === null || !$metrik->vardir()) {
                    $hucreler[$kriter->ad] = new Hucre(
                        kriterAdi: $kriter->ad,
                        gorunenDeger: $metrik?->gorunenDeger() ?? '—',
                        degerlendirildi: false,
                        gecti: false,
                    );
                    continue;
                }

                $hucreler[$kriter->ad] = new Hucre(
                    kriterAdi: $kriter->ad,
                    gorunenDeger: $metrik->gorunenDeger(),
                    degerlendirildi: true,
                    gecti: $kriter->operator->karsilar((float) $metrik->deger, $kriter->esik),
                );
            }

            $sonuclar[] = new SirketSonucu((string) $kod, $hucreler);
        }

        return new TaramaSonucu($seti, $sonuclar);
    }
}
