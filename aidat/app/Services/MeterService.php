<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;

/** Sayaç okumaları ve tüketim dağıtımı → tahakkuk planı (manuel dağıtım) üretir. */
final class MeterService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    public function addReading(int $meterId, int $buildingId, string $period, string $date, float $value, ?string $notes = null): int
    {
        $meter = $this->db->fetch('SELECT * FROM meters WHERE id = ? AND building_id = ?', [$meterId, $buildingId]) ?? throw new DomainException('Sayaç bulunamadı.');
        $prev = $this->db->fetch('SELECT value FROM meter_readings WHERE meter_id = ? AND period < ? ORDER BY period DESC LIMIT 1', [$meterId, $period]);
        $previous = $prev ? (float) $prev['value'] : null;
        if ($previous !== null && $value < $previous) {
            throw new DomainException('Yeni okuma önceki okumadan (' . $previous . ') küçük olamaz. Sayaç değiştiyse yeni sayaç tanımlayın.');
        }
        $consumption = $previous === null ? 0.0 : ($value - $previous) * (float) $meter['multiplier'];
        $existing = $this->db->fetch('SELECT id FROM meter_readings WHERE meter_id = ? AND period = ?', [$meterId, $period]);
        $data = ['reading_date' => $date, 'value' => $value, 'previous_value' => $previous, 'consumption' => $consumption, 'read_by' => $this->app->auth()->id(), 'notes' => $notes];
        if ($existing) {
            $this->db->update('meter_readings', $data, 'id = ?', [(int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('meter_readings', array_merge($data, ['meter_id' => $meterId, 'period' => $period, 'created_at' => Database::now()]));
    }

    /**
     * Dönem tüketimlerini toplam faturaya oranlar; sabit pay (%) eşit dağıtılır. Sonuç: manuel dağıtımlı tahakkuk planı (taslak).
     * @return array{plan_id: int, rows: list<array<string, mixed>>}
     */
    public function distribute(int $buildingId, string $type, string $period, int $totalAmount, float $fixedSharePercent, string $dueDate): array
    {
        $readings = $this->db->fetchAll(
            'SELECT r.consumption, m.unit_id FROM meter_readings r JOIN meters m ON m.id = r.meter_id WHERE m.building_id = ? AND m.type = ? AND r.period = ? AND m.unit_id IS NOT NULL AND m.is_active = 1',
            [$buildingId, $type, $period],
        );
        if ($readings === []) {
            throw new DomainException('Bu dönem için okuma yok.');
        }
        $byUnit = [];
        foreach ($readings as $r) {
            $byUnit[(int) $r['unit_id']] = ($byUnit[(int) $r['unit_id']] ?? 0) + (float) $r['consumption'];
        }
        $totalCons = array_sum($byUnit);
        $fixedTotal = (int) round($totalAmount * $fixedSharePercent / 100);
        $varTotal = $totalAmount - $fixedTotal;
        $units = array_keys($byUnit);
        $fixedSplit = \Aidat\Core\Money::split($fixedTotal, array_fill_keys($units, 1));
        $varSplit = $totalCons > 0 ? \Aidat\Core\Money::split($varTotal, $byUnit) : array_fill_keys($units, 0);
        $lines = [];
        $rows = [];
        foreach ($units as $uid) {
            $amt = ($fixedSplit[$uid] ?? 0) + ($varSplit[$uid] ?? 0);
            $lines[$uid] = ['amount' => $amt, 'share' => $byUnit[$uid]];
            $rows[] = ['unit_id' => $uid, 'consumption' => $byUnit[$uid], 'amount' => $amt];
        }
        $charges = new ChargeService($this->app);
        $planId = $charges->savePlan($buildingId, [
            'name' => list_label('meter_types', $type) . ' tüketimi',
            'charge_type' => 'sayac',
            'block_id' => null, 'fee_group_id' => null,
            'period' => $period, 'due_date' => $dueDate,
            'recurrence' => 'tek', 'repeat_until' => null,
            'distribution' => 'manuel', 'total_amount' => $totalAmount, 'unit_amount' => 0,
            'liability' => 'kiraci', 'vat_mode' => 'yok', 'vat_rate' => 0,
            'description' => sprintf('%s · toplam tüketim %s · birim fiyat %s', Dates::period($period), number_tr($totalCons, 2), $totalCons > 0 ? number_tr($varTotal / 100 / $totalCons, 4) : '0'),
        ], $lines);
        $this->db->insert('meter_distributions', [
            'building_id' => $buildingId, 'type' => $type, 'period' => $period, 'total_amount' => $totalAmount,
            'total_consumption' => $totalCons, 'unit_price' => $totalCons > 0 ? $varTotal / 100 / $totalCons : 0, 'fixed_share_percent' => $fixedSharePercent,
            'status' => 'taslak', 'plan_id' => $planId, 'created_by' => $this->app->auth()->id(), 'created_at' => Database::now(),
        ]);
        return ['plan_id' => $planId, 'rows' => $rows];
    }
}
