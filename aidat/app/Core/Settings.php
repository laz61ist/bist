<?php

declare(strict_types=1);

namespace Aidat\Core;

/** Yapı bazlı ayarlar (settings tablosu), önbellekli. building_id NULL = global. */
final class Settings
{
    /** @var array<string, array<string, string|null>> */
    private array $cache = [];

    public const DEFAULTS = [
        'due_day' => '10',
        'late_fee_enabled' => '1',
        'late_fee_rate_type' => 'aylik',
        'late_fee_rate' => '5',
        'late_fee_grace_days' => '0',
        'late_fee_start_rule' => 'vade',
        'late_fee_start_day' => '1',
        'late_fee_cap_percent' => '',
        'late_fee_compound' => '0',
        'receipt_prefix' => 'MKB',
        'receipt_reset_yearly' => '1',
        'receipt_copies' => '2',
        'receipt_footer' => 'Bu makbuz elektronik ortamda üretilmiştir.',
        'receipt_signer_title' => 'Yönetici',
        'debt_visibility' => 'kapi_no',
        'portal_show_expenses' => '1',
        'portal_show_expense_documents' => '1',
        'portal_show_accounts' => '1',
        'portal_show_budget' => '1',
        'portal_show_audit_summary' => '0',
        'fiscal_year_start_month' => '1',
        'vat_default' => 'dahil',
        'reminder_days_before' => '3',
        'reminder_days_after' => '7',
        'currency' => 'TRY',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    private function load(?int $buildingId): array
    {
        $key = (string) ($buildingId ?? 'global');
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $rows = $buildingId === null
            ? $this->db->fetchAll('SELECT `key`, `value` FROM settings WHERE building_id IS NULL')
            : $this->db->fetchAll('SELECT `key`, `value` FROM settings WHERE building_id = ?', [$buildingId]);
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key']] = $row['value'];
        }
        return $this->cache[$key] = $out;
    }

    public function get(?int $buildingId, string $key, ?string $default = null): ?string
    {
        $values = $this->load($buildingId);
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        if ($buildingId !== null) {
            $global = $this->load(null);
            if (array_key_exists($key, $global)) {
                return $global[$key];
            }
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function bool(?int $buildingId, string $key): bool
    {
        return in_array($this->get($buildingId, $key), ['1', 'true', 'on'], true);
    }

    public function int(?int $buildingId, string $key, int $default = 0): int
    {
        $v = $this->get($buildingId, $key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function float(?int $buildingId, string $key, float $default = 0.0): float
    {
        $v = $this->get($buildingId, $key);
        return is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : $default;
    }

    public function set(?int $buildingId, string $key, ?string $value): void
    {
        $existing = $buildingId === null
            ? $this->db->fetch('SELECT id FROM settings WHERE building_id IS NULL AND `key` = ?', [$key])
            : $this->db->fetch('SELECT id FROM settings WHERE building_id = ? AND `key` = ?', [$buildingId, $key]);
        if ($existing === null) {
            $this->db->insert('settings', ['building_id' => $buildingId, 'key' => $key, 'value' => $value, 'updated_at' => Database::now()]);
        } else {
            $this->db->update('settings', ['value' => $value, 'updated_at' => Database::now()], 'id = ?', [(int) $existing['id']]);
        }
        unset($this->cache[(string) ($buildingId ?? 'global')]);
    }

    /** @param array<string, string|null> $values */
    public function setMany(?int $buildingId, array $values): void
    {
        foreach ($values as $k => $v) {
            $this->set($buildingId, $k, $v);
        }
    }

    /** @return array<string, string|null> tüm etkin değerler (varsayılanlar dahil) */
    public function all(?int $buildingId): array
    {
        $out = self::DEFAULTS;
        foreach ($this->load(null) as $k => $v) {
            $out[$k] = $v;
        }
        if ($buildingId !== null) {
            foreach ($this->load($buildingId) as $k => $v) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
