<?php

declare(strict_types=1);

use Aidat\Core\Application;
use Aidat\Core\Container;
use Aidat\Core\Dates;
use Aidat\Core\Money;

/** Görünümlerde kısa yardımcılar. */

function app(): Application
{
    return Container::instance()->get('app');
}

function config(string $key, mixed $default = null): mixed
{
    return app()->config()->get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, mixed> $params */
function route(string $name, array $params = []): string
{
    return app()->router()->url($name, $params);
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = app()->basePath . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : config('app.version', '1');
    return '/' . ltrim($path, '/') . '?v=' . $v;
}

function money(int|float|string|null $kurus, bool $symbol = true): string
{
    return Money::format($kurus, $symbol);
}

function money_input(int|float|string|null $kurus): string
{
    return Money::input($kurus);
}

function tr_date(?string $date): string
{
    return Dates::tr($date);
}

function tr_datetime(?string $dt): string
{
    return Dates::trDateTime($dt);
}

function tr_period(?string $ym): string
{
    return Dates::period($ym);
}

function old(string $key, mixed $default = null): mixed
{
    $old = app()->session()->getFlash('old', []);
    if (is_array($old) && array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $default;
}

/** @return array<string, string> */
function errors(): array
{
    $e = app()->session()->getFlash('errors', []);
    return is_array($e) ? $e : [];
}

function error_for(string $field): ?string
{
    return errors()[$field] ?? null;
}

function csrf_token(): string
{
    return app()->csrf()->token();
}

function csrf_field(): string
{
    return app()->csrf()->field();
}

/** @return array<string, mixed>|null */
function auth_user(): ?array
{
    return app()->auth()->user();
}

function can(string $permission): bool
{
    $b = app()->session()->get('building_id');
    return app()->gate()->allows($permission, is_int($b) ? $b : null);
}

function is_admin(): bool
{
    return app()->gate()->isAdmin();
}

function current_building_id(): ?int
{
    $b = app()->session()->get('building_id');
    return is_int($b) ? $b : null;
}

function setting(string $key, ?string $default = null): ?string
{
    return app()->settings()->get(current_building_id(), $key, $default);
}

function flash(string $key, mixed $default = null): mixed
{
    return app()->session()->getFlash($key, $default);
}

/** Menü aktiflik: geçerli yol verilen ön ekle başlıyorsa true. */
function is_active(string ...$prefixes): bool
{
    $path = app()->request()->path;
    foreach ($prefixes as $p) {
        if ($p === '/' ? $path === '/' : ($path === $p || str_starts_with($path, rtrim($p, '/') . '/'))) {
            return true;
        }
    }
    return false;
}

/** config/lists.php etiketini döndürür. */
function list_label(string $list, mixed $key): string
{
    $items = config('lists.' . $list, []);
    return (string) ($items[(string) $key] ?? $key ?? '');
}

/** @return array<string, string> */
function list_options(string $list): array
{
    $items = config('lists.' . $list, []);
    return is_array($items) ? $items : [];
}

function icon(string $name, string $class = ''): string
{
    return '<i class="bi bi-' . e($name) . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true"></i>';
}

/** Durum rozeti sınıfı: durum anahtarını semantik renge çevirir (renk + metin birlikte). */
function status_tone(string $status): string
{
    return match ($status) {
        'odendi', 'gecerli', 'aktif', 'onayli', 'onaylandi', 'tamamlandi', 'yapildi', 'acik', 'dolu', 'islendi' => 'ok',
        'kismi', 'beklemede', 'inceleniyor', 'atandi', 'devam', 'planlandi', 'taslak', 'revizyonda', 'tadilatta', 'gecikti', 'onemli' => 'warn',
        'odenmedi', 'iptal', 'iade', 'feshedildi', 'arizali', 'acil', 'pasif', 'kapali' => 'bad',
        default => 'neutral',
    };
}

function number_tr(int|float|null $n, int $decimals = 0): string
{
    return number_format((float) ($n ?? 0), $decimals, ',', '.');
}

function percent_tr(float|int|null $n, int $decimals = 1): string
{
    return '%' . number_format((float) ($n ?? 0), $decimals, ',', '.');
}

/** Sayfalama ve filtre için mevcut sorgu dizisini birleştirir. */
function query_with(array $overrides): string
{
    $q = array_merge(app()->request()->query, $overrides);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');
    return $q === [] ? '' : '?' . http_build_query($q);
}
