<?php

declare(strict_types=1);

namespace Aidat\Core;

use InvalidArgumentException;

/**
 * Para: veritabanında tam sayı KURUŞ. Ekranda Türkçe biçim (1.250,50 ₺).
 *
 * Türkçe ondalık ayrımı KRİTİK: "10.636,8" (nokta=binlik, virgül=ondalık)
 * ile "10,636.8" (İngilizce) karıştırılırsa hesap yanlış çıkar. parse()
 * her iki biçimi belirleyici kurallarla çözer.
 */
final class Money
{
    public static function format(int|float|string|null $kurus, bool $symbol = true, bool $signed = false): string
    {
        $kurus = (int) round((float) ($kurus ?? 0));
        $negative = $kurus < 0;
        $abs = abs($kurus);
        $tl = intdiv($abs, 100);
        $kr = $abs % 100;
        $text = number_format($tl, 0, ',', '.') . ',' . str_pad((string) $kr, 2, '0', STR_PAD_LEFT);
        if ($negative) {
            $text = '-' . $text;
        } elseif ($signed && $kurus > 0) {
            $text = '+' . $text;
        }
        return $symbol ? $text . ' ₺' : $text;
    }

    /** Form alanına yazılacak ham değer: "1250,50" */
    public static function input(int|float|string|null $kurus): string
    {
        $kurus = (int) round((float) ($kurus ?? 0));
        if ($kurus === 0) {
            return '';
        }
        return self::format($kurus, false);
    }

    /** CSV/XLSX için nokta ondalıklı: "1250.50" */
    public static function decimal(int|float|string|null $kurus): string
    {
        $kurus = (int) round((float) ($kurus ?? 0));
        $sign = $kurus < 0 ? '-' : '';
        $abs = abs($kurus);
        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Kullanıcı girdisini kuruşa çevirir.
     *  "1.250,50" → 125050   "1250,5" → 125050   "1250.50" → 125050
     *  "1.250" → 125000 (nokta + 3 hane = binlik)   "12.5" → 1250 (nokta + 1-2 hane = ondalık)
     *  "1,250.75" → 125075 (İngilizce)
     */
    public static function parse(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            return (int) round($value * 100);
        }
        $raw = trim((string) $value);
        $raw = str_replace(['₺', 'TL', 'tl', ' ', "\u{00A0}"], '', $raw);
        if ($raw === '' || $raw === '-') {
            return 0;
        }
        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        if (!preg_match('/^[0-9.,]+$/', $raw)) {
            throw new InvalidArgumentException('Geçersiz tutar biçimi.');
        }
        $hasDot = str_contains($raw, '.');
        $hasComma = str_contains($raw, ',');
        if ($hasDot && $hasComma) {
            // Son görülen ayraç ondalık ayracıdır
            $decimalSep = strrpos($raw, ',') > strrpos($raw, '.') ? ',' : '.';
            $thousandsSep = $decimalSep === ',' ? '.' : ',';
            $raw = str_replace($thousandsSep, '', $raw);
            $raw = str_replace($decimalSep, '.', $raw);
        } elseif ($hasComma) {
            if (substr_count($raw, ',') > 1) {
                $raw = str_replace(',', '', $raw); // 1,250,000 (İngilizce binlik)
            } else {
                $raw = str_replace(',', '.', $raw);
            }
        } elseif ($hasDot) {
            if (substr_count($raw, '.') > 1) {
                $raw = str_replace('.', '', $raw); // 1.250.000
            } else {
                $after = strlen($raw) - strrpos($raw, '.') - 1;
                if ($after === 3) {
                    $raw = str_replace('.', '', $raw); // 1.250 → binlik
                }
            }
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Geçersiz tutar biçimi.');
        }
        [$int, $frac] = array_pad(explode('.', $raw, 2), 2, '0');
        $frac = substr(str_pad($frac, 2, '0'), 0, 2);
        $kurus = (int) $int * 100 + (int) $frac;
        return $negative ? -$kurus : $kurus;
    }

    /** Toplamı paylara böler; yuvarlama farkı son paya eklenir (toplam korunur). */
    public static function split(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0 || $weights === []) {
            return [];
        }
        $out = [];
        $allocated = 0;
        $keys = array_keys($weights);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $out[$key] = $total - $allocated;
            } else {
                $share = (int) floor($total * ($weights[$key] / $sum));
                $out[$key] = $share;
                $allocated += $share;
            }
        }
        return $out;
    }

    /** Sayıyı Türkçe yazıya çevirir (makbuz için). */
    public static function words(int $kurus): string
    {
        $tl = intdiv(abs($kurus), 100);
        $kr = abs($kurus) % 100;
        $text = self::numberToWords($tl) . ' Türk Lirası';
        if ($kr > 0) {
            $text .= ' ' . self::numberToWords($kr) . ' Kuruş';
        }
        return ($kurus < 0 ? 'eksi ' : '') . trim($text);
    }

    private static function numberToWords(int $n): string
    {
        if ($n === 0) {
            return 'sıfır';
        }
        $ones = ['', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz'];
        $tens = ['', 'on', 'yirmi', 'otuz', 'kırk', 'elli', 'altmış', 'yetmiş', 'seksen', 'doksan'];
        $groups = ['', 'bin', 'milyon', 'milyar', 'trilyon'];
        $parts = [];
        $g = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                $h = intdiv($chunk, 100);
                $t = intdiv($chunk % 100, 10);
                $o = $chunk % 10;
                $words = [];
                if ($h > 0) {
                    $words[] = ($h === 1 ? '' : $ones[$h] . ' ') . 'yüz';
                }
                if ($t > 0) {
                    $words[] = $tens[$t];
                }
                if ($o > 0) {
                    if (!($g === 1 && $chunk === 1)) {
                        $words[] = $ones[$o];
                    }
                }
                $parts[] = trim(implode(' ', $words) . ' ' . $groups[$g]);
            }
            $n = intdiv($n, 1000);
            $g++;
        }
        return trim(implode(' ', array_reverse($parts)));
    }
}
