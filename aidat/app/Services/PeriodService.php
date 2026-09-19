<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\DomainException;

/** Mali dönem (ay) kapama. Kapalı döneme finansal kayıt yapılamaz; yalnız yetkili yeniden açar ve iz bırakır. */
final class PeriodService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    public static function periodOf(string $date): string
    {
        return substr($date, 0, 7);
    }

    public function isClosed(int $buildingId, string $dateOrPeriod): bool
    {
        $period = strlen($dateOrPeriod) > 7 ? self::periodOf($dateOrPeriod) : $dateOrPeriod;
        $row = $this->db->fetch('SELECT status FROM fiscal_periods WHERE building_id = ? AND period = ?', [$buildingId, $period]);
        return $row !== null && $row['status'] === 'kapali';
    }

    /** @throws DomainException */
    public function assertOpen(int $buildingId, string $dateOrPeriod): void
    {
        if ($this->isClosed($buildingId, $dateOrPeriod)) {
            $period = strlen($dateOrPeriod) > 7 ? self::periodOf($dateOrPeriod) : $dateOrPeriod;
            throw new DomainException(\Aidat\Core\Dates::period($period) . ' dönemi kapatılmış. Kayıt için önce dönemin yeniden açılması gerekir.');
        }
    }

    public function close(int $buildingId, string $period, int $userId, ?string $note = null): void
    {
        $row = $this->db->fetch('SELECT * FROM fiscal_periods WHERE building_id = ? AND period = ?', [$buildingId, $period]);
        $data = ['status' => 'kapali', 'closed_by' => $userId, 'closed_at' => Database::now(), 'note' => $note];
        if ($row === null) {
            $this->db->insert('fiscal_periods', array_merge(['building_id' => $buildingId, 'period' => $period], $data));
        } else {
            $this->db->update('fiscal_periods', $data, 'id = ?', [(int) $row['id']]);
        }
        $this->app->audit()->log('period.close', 'fiscal_period', null, null, ['period' => $period, 'note' => $note], $buildingId, 'Dönem kapatıldı: ' . $period);
    }

    public function reopen(int $buildingId, string $period, int $userId, ?string $note = null): void
    {
        $row = $this->db->fetch('SELECT * FROM fiscal_periods WHERE building_id = ? AND period = ?', [$buildingId, $period]);
        if ($row === null) {
            return;
        }
        $this->db->update('fiscal_periods', ['status' => 'acik', 'reopened_by' => $userId, 'reopened_at' => Database::now(), 'note' => $note], 'id = ?', [(int) $row['id']]);
        $this->app->audit()->log('period.reopen', 'fiscal_period', (int) $row['id'], ['status' => 'kapali'], ['status' => 'acik', 'note' => $note], $buildingId, 'Dönem yeniden açıldı: ' . $period);
    }

    /** @return array<string, array<string, mixed>> period => satır (yılın 12 ayı) */
    public function yearMap(int $buildingId, int $year): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM fiscal_periods WHERE building_id = ? AND period LIKE ?', [$buildingId, $year . '-%']);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['period']] = $r;
        }
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $p = sprintf('%04d-%02d', $year, $m);
            $out[$p] = $map[$p] ?? ['period' => $p, 'status' => 'acik', 'closed_at' => null, 'closed_by' => null, 'note' => null];
        }
        return $out;
    }
}
