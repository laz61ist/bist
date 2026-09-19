<?php

declare(strict_types=1);

namespace Aidat\Core;

final class Str
{
    public static function upperTr(string $s): string
    {
        return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $s), 'UTF-8');
    }

    public static function lowerTr(string $s): string
    {
        return mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $s), 'UTF-8');
    }

    public static function titleTr(string $s): string
    {
        $words = preg_split('/\s+/u', trim($s)) ?: [];
        return implode(' ', array_map(
            fn (string $w) => self::upperTr(mb_substr($w, 0, 1)) . self::lowerTr(mb_substr($w, 1)),
            $words,
        ));
    }

    public static function slug(string $s): string
    {
        $s = str_replace(['ş', 'Ş', 'ğ', 'Ğ', 'ı', 'İ', 'ç', 'Ç', 'ö', 'Ö', 'ü', 'Ü'], ['s', 's', 'g', 'g', 'i', 'i', 'c', 'c', 'o', 'o', 'u', 'u'], $s);
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '', '-'));
        return $s === '' ? 'kayit' : $s;
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
        return self::upperTr(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    public static function limit(string $s, int $len = 80): string
    {
        return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
    }

    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 7) {
            return $phone;
        }
        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 6) . substr($digits, -2);
    }

    public static function maskIdentity(?string $no): string
    {
        if ($no === null || $no === '') {
            return '';
        }
        $len = strlen($no);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($no, 0, 2) . str_repeat('*', $len - 4) . substr($no, -2);
    }

    public static function formatPhone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }
        if (strlen($d) === 10) {
            return sprintf('0%s %s %s %s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
        }
        return $phone;
    }

    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** TCKN algoritma kontrolü. */
    public static function validTckn(string $no): bool
    {
        if (!preg_match('/^[1-9][0-9]{10}$/', $no)) {
            return false;
        }
        $d = array_map('intval', str_split($no));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        if ((($odd * 7) - $even) % 10 !== $d[9]) {
            return false;
        }
        return (array_sum(array_slice($d, 0, 10)) % 10) === $d[10];
    }

    /** IBAN mod-97 kontrolü. */
    public static function validIban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $moved = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($moved) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder . $chunk) % 97);
        }
        return $remainder === 1;
    }

    public static function formatIban(?string $iban): string
    {
        if ($iban === null) {
            return '';
        }
        return trim(chunk_split(strtoupper(str_replace(' ', '', $iban)), 4, ' '));
    }
}
