<?php

declare(strict_types=1);

namespace Aidat\Core;

use DateTimeImmutable;

final class Dates
{
    public const MONTHS = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    public const DAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    public static function today(): string
    {
        return date('Y-m-d');
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function currentPeriod(): string
    {
        return date('Y-m');
    }

    /** "2026-09-19" → "19.09.2026" */
    public static function tr(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $ts = strtotime($date);
        return $ts === false ? (string) $date : date('d.m.Y', $ts);
    }

    /** "2026-09-19 14:05:00" → "19.09.2026 14:05" */
    public static function trDateTime(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts === false ? (string) $datetime : date('d.m.Y H:i', $ts);
    }

    /** "2026-09-19" → "19 Eylül 2026" */
    public static function trLong(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return (string) $date;
        }
        return (int) date('j', $ts) . ' ' . self::MONTHS[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /** "2026-09" → "Eylül 2026" */
    public static function period(?string $ym): string
    {
        if ($ym === null || !preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
            return (string) $ym;
        }
        return (self::MONTHS[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    /** "19.09.2026" veya "2026-09-19" → "2026-09-19"; geçersizse null */
    public static function parse(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $input, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
        }
        if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $input, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])
                : null;
        }
        return null;
    }

    public static function addMonths(string $ym, int $months): string
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01') ?: new DateTimeImmutable();
        return $d->modify(($months >= 0 ? '+' : '') . $months . ' months')->format('Y-m');
    }

    /** İki tarih arasında başlamış ay sayısı (gecikme tazminatı için). */
    public static function monthsBetween(string $from, string $to): int
    {
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        if ($b <= $a) {
            return 0;
        }
        $diff = $a->diff($b);
        $months = $diff->y * 12 + $diff->m;
        if ($diff->d > 0) {
            $months++;
        }
        return $months;
    }

    public static function daysBetween(string $from, string $to): int
    {
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        return $b <= $a ? 0 : (int) $a->diff($b)->days;
    }

    /** Dönemin (YYYY-MM) verilen günü; ay kısa ise son güne kırpar. */
    public static function dayOfPeriod(string $ym, int $day): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym));
        $last = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        return sprintf('%04d-%02d-%02d', $y, $m, min(max($day, 1), $last));
    }

    /** @return list<string> başlangıçtan bitişe dönem listesi */
    public static function periodRange(string $fromYm, string $toYm): array
    {
        $out = [];
        $cur = $fromYm;
        $guard = 0;
        while ($cur <= $toYm && $guard++ < 240) {
            $out[] = $cur;
            $cur = self::addMonths($cur, 1);
        }
        return $out;
    }

    /** Göreli zaman: "3 gün önce" */
    public static function ago(?string $datetime): string
    {
        if ($datetime === null) {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'az önce';
        }
        if ($diff < 3600) {
            return intdiv($diff, 60) . ' dk önce';
        }
        if ($diff < 86400) {
            return intdiv($diff, 3600) . ' saat önce';
        }
        if ($diff < 86400 * 30) {
            return intdiv($diff, 86400) . ' gün önce';
        }
        return self::tr($datetime);
    }
}
