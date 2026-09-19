<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Settings;

/**
 * Gecikme tazminatı (KMK 634 md. 20: aylık %5 varsayılan; oran/başlangıç/tolerans/üst sınır ayarlanabilir).
 * Ana borç başına "gecikme" türünde alt borç açılır; tahakkuk arttıkça açık alt borç güncellenir, kapalıysa fark için yeni satır açılır.
 * Not: Yasal oran ve uygulama biçimi yönetim planına göre değişebilir; varsayılanlar ayarlardan değiştirilebilir. [DOĞRULANMASI GEREKİYOR: güncel mevzuat]
 */
final class LateFeeService
{
    private Database $db;
    private Settings $settings;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
        $this->settings = $app->settings();
    }

    /** @return array<string, mixed> yapı bazlı kural */
    public function rule(int $buildingId): array
    {
        $s = $this->settings;
        return [
            'enabled' => $s->bool($buildingId, 'late_fee_enabled'),
            'rate_type' => $s->get($buildingId, 'late_fee_rate_type', 'aylik'),
            'rate' => $s->float($buildingId, 'late_fee_rate', 5.0),
            'grace_days' => $s->int($buildingId, 'late_fee_grace_days', 0),
            'start_rule' => $s->get($buildingId, 'late_fee_start_rule', 'vade'),
            'start_day' => $s->int($buildingId, 'late_fee_start_day', 1),
            'cap_percent' => $s->float($buildingId, 'late_fee_cap_percent', 0.0),
            'compound' => $s->bool($buildingId, 'late_fee_compound'),
        ];
    }

    /** Ana borç için verilen tarihe kadar birikmiş toplam tazminatı (kuruş) hesaplar. @param array<string, mixed> $charge */
    public function accrued(array $charge, array $rule, string $asOf): int
    {
        if (!$rule['enabled'] || (int) ($charge['late_fee_exempt'] ?? 0) === 1) {
            return 0;
        }
        $base = (int) $charge['amount'] - (int) $charge['paid_amount'];
        if ($base <= 0) {
            return 0;
        }
        $start = $this->startDate((string) $charge['due_date'], $rule);
        if ($asOf <= $start) {
            return 0;
        }
        $rate = (float) $rule['rate'] / 100;
        if ($rule['rate_type'] === 'gunluk') {
            $days = Dates::daysBetween($start, $asOf);
            $fee = $rule['compound'] ? $base * ((1 + $rate) ** $days - 1) : $base * $rate * $days;
        } else {
            $months = Dates::monthsBetween($start, $asOf);
            $fee = $rule['compound'] ? $base * ((1 + $rate) ** $months - 1) : $base * $rate * $months;
        }
        $fee = (int) round($fee);
        if ((float) $rule['cap_percent'] > 0) {
            $fee = min($fee, (int) round($base * (float) $rule['cap_percent'] / 100));
        }
        return max(0, $fee);
    }

    private function startDate(string $dueDate, array $rule): string
    {
        $start = date('Y-m-d', strtotime($dueDate . ' +' . max(0, (int) $rule['grace_days']) . ' days'));
        if ($rule['start_rule'] === 'ay_gunu') {
            $day = max(1, min(28, (int) $rule['start_day']));
            $candidate = date('Y-m-', strtotime($start)) . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            if ($candidate <= $start) {
                $candidate = date('Y-m-d', strtotime($candidate . ' +1 month'));
            }
            $start = $candidate;
        }
        return $start;
    }

    /**
     * Önizleme: ana borç, birikmiş, daha önce tahakkuk eden, fark.
     * @return list<array<string, mixed>>
     */
    public function preview(int $buildingId, string $asOf, ?int $unitId = null): array
    {
        $rule = $this->rule($buildingId);
        $sql = "SELECT c.*, u.door_no, u.late_fee_exempt AS unit_exempt, b.name AS block_name FROM charges c JOIN units u ON u.id = c.unit_id LEFT JOIN blocks b ON b.id = u.block_id
                WHERE c.building_id = ? AND c.status IN ('odenmedi','kismi') AND c.charge_type <> 'gecikme' AND c.due_date < ?";
        $params = [$buildingId, $asOf];
        if ($unitId) {
            $sql .= ' AND c.unit_id = ?';
            $params[] = $unitId;
        }
        $sql .= ' ORDER BY u.door_no, c.due_date';
        $out = [];
        foreach ($this->db->fetchAll($sql, $params) as $c) {
            if ((int) $c['unit_exempt'] === 1) {
                continue;
            }
            $accrued = $this->accrued($c, $rule, $asOf);
            $already = $this->db->fetchInt("SELECT COALESCE(SUM(amount), 0) FROM charges WHERE parent_charge_id = ? AND status <> 'iptal'", [(int) $c['id']]);
            $delta = $accrued - $already;
            if ($accrued <= 0 && $already <= 0) {
                continue;
            }
            $out[] = ['charge' => $c, 'base' => (int) $c['amount'] - (int) $c['paid_amount'], 'accrued' => $accrued, 'already' => $already, 'delta' => $delta, 'days' => Dates::daysBetween((string) $c['due_date'], $asOf)];
        }
        return $out;
    }

    /** Uygular: farkı açık alt borca ekler ya da yeni alt borç açar. @return array{created: int, updated: int, amount: int} */
    public function apply(int $buildingId, string $asOf, ?int $unitId = null): array
    {
        $rows = $this->preview($buildingId, $asOf, $unitId);
        $created = 0;
        $updated = 0;
        $total = 0;
        $charges = new ChargeService($this->app);
        $this->db->transaction(function () use ($rows, $buildingId, $asOf, &$created, &$updated, &$total, $charges): void {
            foreach ($rows as $r) {
                if ($r['delta'] <= 0) {
                    continue;
                }
                $c = $r['charge'];
                $open = $this->db->fetch("SELECT id, amount FROM charges WHERE parent_charge_id = ? AND status IN ('odenmedi','kismi') ORDER BY id DESC LIMIT 1", [(int) $c['id']]);
                if ($open !== null) {
                    $this->db->update('charges', ['amount' => (int) $open['amount'] + $r['delta'], 'late_fee_accrued_to' => $asOf, 'updated_at' => Database::now()], 'id = ?', [(int) $open['id']]);
                    $updated++;
                } else {
                    $charges->createCharge($buildingId, [
                        'unit_id' => (int) $c['unit_id'],
                        'person_id' => $c['person_id'],
                        'parent_charge_id' => (int) $c['id'],
                        'charge_type' => 'gecikme',
                        'title' => 'Gecikme tazminatı · ' . $c['title'],
                        'period' => substr($asOf, 0, 7),
                        'due_date' => $asOf,
                        'amount' => $r['delta'],
                        'liability' => $c['liability'],
                        'description' => 'Vade: ' . Dates::tr((string) $c['due_date']) . ' · ' . $r['days'] . ' gün gecikme',
                        'late_fee_exempt' => 1,
                        'skip_advances' => true,
                    ]);
                    $created++;
                }
                $this->db->update('charges', ['late_fee_accrued_to' => $asOf], 'id = ?', [(int) $c['id']]);
                $total += $r['delta'];
            }
            if ($created + $updated > 0) {
                $this->app->audit()->log('latefee.apply', 'charge', null, null, ['as_of' => $asOf, 'created' => $created, 'updated' => $updated, 'amount' => $total], $buildingId, 'Gecikme tazminatı uygulandı: ' . ($created + $updated) . ' kayıt');
            }
        });
        return ['created' => $created, 'updated' => $updated, 'amount' => $total];
    }

    /** Tüm yapılar (veya biri) için uygular. @return array{created: int, updated: int, amount: int} */
    public function applyAll(?int $buildingId, string $asOf): array
    {
        $ids = $buildingId !== null ? [$buildingId] : array_map('intval', array_column($this->db->fetchAll('SELECT id FROM buildings WHERE is_active = 1'), 'id'));
        $sum = ['created' => 0, 'updated' => 0, 'amount' => 0];
        foreach ($ids as $id) {
            $r = $this->apply($id, $asOf);
            $sum['created'] += $r['created'];
            $sum['updated'] += $r['updated'];
            $sum['amount'] += $r['amount'];
        }
        return $sum;
    }
}
