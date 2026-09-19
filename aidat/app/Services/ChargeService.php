<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/**
 * Tahakkuk (borçlandırma): plan önizleme/dağıtım, işleme, tekil borç, iptal, bakiye.
 * Tutarlar kuruş (int). Dağıtımda yuvarlama farkı son satıra eklenir; toplam korunur.
 */
final class ChargeService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    // ------------------------------------------------------------ Sorumlu kişi

    /** Bölümdeki sorumlu kişiyi bulur (malik / kiracı / paylaşımlı → malik). */
    public function responsiblePerson(int $unitId, string $liability, ?string $asOf = null): ?int
    {
        $asOf ??= Dates::today();
        $prefer = $liability === 'kiraci' ? ['kiraci', 'malik'] : ['malik', 'kiraci'];
        foreach ($prefer as $role) {
            $id = $this->db->fetchColumn(
                'SELECT person_id FROM occupancies WHERE unit_id = ? AND role = ? AND start_date <= ? AND (end_date IS NULL OR end_date >= ?) ORDER BY id DESC LIMIT 1',
                [$unitId, $role, $asOf, $asOf],
            );
            if ($id !== null) {
                return (int) $id;
            }
        }
        return null;
    }

    // ------------------------------------------------------------ Dağıtım

    /**
     * @param array<string, mixed> $plan distribution,total_amount,unit_amount,group_amounts(json/array),block_id,fee_group_id
     * @param list<array<string, mixed>> $units
     * @param array<int, int>|null $manual unit_id => kuruş (manuel dağıtım)
     * @param list<int>|null $includedIds
     * @return array<int, array{amount: int, share: float|null}>
     */
    public function distribute(array $plan, array $units, ?array $manual = null, ?array $includedIds = null): array
    {
        $dist = (string) ($plan['distribution'] ?? 'esit');
        $active = array_values(array_filter($units, static fn (array $u) => $includedIds === null || in_array((int) $u['id'], $includedIds, true)));
        if ($active === []) {
            return [];
        }
        $out = [];
        if ($dist === 'sabit') {
            $amt = (int) ($plan['unit_amount'] ?? 0);
            foreach ($active as $u) {
                $out[(int) $u['id']] = ['amount' => $amt, 'share' => null];
            }
            return $out;
        }
        if ($dist === 'manuel') {
            foreach ($active as $u) {
                $out[(int) $u['id']] = ['amount' => (int) ($manual[(int) $u['id']] ?? 0), 'share' => null];
            }
            return $out;
        }
        if ($dist === 'grup') {
            $groups = $plan['group_amounts'] ?? [];
            if (is_string($groups)) {
                $groups = json_decode($groups, true) ?: [];
            }
            foreach ($active as $u) {
                $g = (int) ($u['fee_group_id'] ?? 0);
                $out[(int) $u['id']] = ['amount' => (int) ($groups[$g] ?? $groups[(string) $g] ?? 0), 'share' => null];
            }
            return $out;
        }
        $total = (int) ($plan['total_amount'] ?? 0);
        $weights = [];
        foreach ($active as $u) {
            $w = match ($dist) {
                'm2' => (float) ($u['gross_m2'] ?: $u['net_m2'] ?: 0),
                'arsa_payi' => (float) ($u['land_share'] ?: 0),
                default => 1.0,
            };
            $weights[(int) $u['id']] = $w;
        }
        $sum = array_sum($weights);
        if ($sum <= 0) {
            throw new DomainException($dist === 'm2' ? 'Seçili bölümlerin m² bilgisi eksik.' : 'Seçili bölümlerin arsa payı bilgisi eksik.');
        }
        $split = Money::split($total, $weights);
        foreach ($weights as $id => $w) {
            $out[$id] = ['amount' => $split[$id] ?? 0, 'share' => $w];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function planUnits(int $buildingId, ?int $blockId = null, ?int $feeGroupId = null): array
    {
        $sql = "SELECT u.*, b.name AS block_name, g.name AS fee_group_name FROM units u LEFT JOIN blocks b ON b.id = u.block_id LEFT JOIN fee_groups g ON g.id = u.fee_group_id WHERE u.building_id = ? AND u.status <> 'pasif'";
        $params = [$buildingId];
        if ($blockId) {
            $sql .= ' AND u.block_id = ?';
            $params[] = $blockId;
        }
        if ($feeGroupId) {
            $sql .= ' AND u.fee_group_id = ?';
            $params[] = $feeGroupId;
        }
        $sql .= ' ORDER BY b.sort_order, b.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no';
        return $this->db->fetchAll($sql, $params);
    }

    // ------------------------------------------------------------ Plan yaşam döngüsü

    /**
     * @param array<string, mixed> $data doğrulanmış plan alanları
     * @param array<int, array{amount: int, share: float|null}> $lines
     */
    public function savePlan(int $buildingId, array $data, array $lines, ?int $planId = null): int
    {
        $now = Database::now();
        $row = [
            'building_id' => $buildingId,
            'name' => $data['name'],
            'charge_type' => $data['charge_type'],
            'block_id' => ($data['block_id'] ?? null) ?: null,
            'fee_group_id' => ($data['fee_group_id'] ?? null) ?: null,
            'period' => $data['period'],
            'due_day' => (int) substr((string) $data['due_date'], 8, 2),
            'due_date' => $data['due_date'],
            'recurrence' => $data['recurrence'],
            'repeat_until' => ($data['repeat_until'] ?? null) ?: null,
            'distribution' => $data['distribution'],
            'total_amount' => (int) ($data['total_amount'] ?? 0),
            'unit_amount' => (int) ($data['unit_amount'] ?? 0),
            'group_amounts' => isset($data['group_amounts']) ? json_encode($data['group_amounts']) : null,
            'liability' => $data['liability'] ?? 'malik',
            'vat_mode' => $data['vat_mode'] ?? 'yok',
            'vat_rate' => (float) ($data['vat_rate'] ?? 0),
            'description' => $data['description'] ?? null,
            'updated_at' => $now,
        ];
        return $this->db->transaction(function () use ($row, $lines, $planId, $now, $buildingId): int {
            if ($planId === null) {
                $row['status'] = 'taslak';
                $row['created_by'] = $this->app->auth()->id();
                $row['created_at'] = $now;
                $planId = $this->db->insert('charge_plans', $row);
                $this->app->audit()->log('plan.create', 'charge_plan', $planId, null, $row, $buildingId, 'Tahakkuk planı oluşturuldu: ' . $row['name']);
            } else {
                $old = $this->db->fetch('SELECT * FROM charge_plans WHERE id = ?', [$planId]);
                if ($old === null || !in_array($old['status'], ['taslak'], true)) {
                    throw new DomainException('Yalnızca taslak planlar düzenlenebilir.');
                }
                $this->db->update('charge_plans', $row, 'id = ?', [$planId]);
                $this->db->delete('charge_plan_lines', 'plan_id = ?', [$planId]);
                $this->app->audit()->log('plan.update', 'charge_plan', $planId, $old, $row, $buildingId, 'Tahakkuk planı güncellendi');
            }
            $total = array_sum(array_column($lines, 'amount'));
            $i = 0;
            $n = count($lines);
            foreach ($lines as $unitId => $line) {
                $i++;
                $this->db->insert('charge_plan_lines', [
                    'plan_id' => $planId,
                    'unit_id' => $unitId,
                    'included' => 1,
                    'share_value' => $line['share'],
                    'amount' => $line['amount'],
                    'rounding_diff' => 0,
                ]);
            }
            if (in_array($row['distribution'], ['esit', 'm2', 'arsa_payi'], true) && $row['total_amount'] !== $total) {
                $this->db->update('charge_plans', ['total_amount' => $total], 'id = ?', [$planId]);
            }
            return $planId;
        });
    }

    public function approvePlan(int $planId, int $buildingId): void
    {
        $plan = $this->planOrFail($planId, $buildingId);
        if ($plan['status'] !== 'taslak') {
            throw new DomainException('Plan zaten onaylanmış veya iptal edilmiş.');
        }
        $this->db->update('charge_plans', ['status' => 'onaylandi', 'approved_by' => $this->app->auth()->id(), 'approved_at' => Database::now(), 'updated_at' => Database::now()], 'id = ?', [$planId]);
        $this->app->audit()->log('plan.approve', 'charge_plan', $planId, ['status' => 'taslak'], ['status' => 'onaylandi'], $buildingId, 'Plan onaylandı: ' . $plan['name']);
    }

    /** Onaylı planı işler: satırlardan borç kayıtları üretir. Tek seferlik planlar "işlendi" olur; tekrarlı planlar açık kalır. */
    public function processPlan(int $planId, int $buildingId, ?string $period = null): int
    {
        $plan = $this->planOrFail($planId, $buildingId);
        if (!in_array($plan['status'], ['onaylandi'], true)) {
            throw new DomainException('Yalnızca onaylanmış planlar işlenebilir.');
        }
        $period ??= (string) $plan['period'];
        (new PeriodService($this->app))->assertOpen($buildingId, $period);
        $lines = $this->db->fetchAll('SELECT l.*, u.liability_mode FROM charge_plan_lines l JOIN units u ON u.id = l.unit_id WHERE l.plan_id = ? AND l.included = 1 AND l.amount > 0', [$planId]);
        if ($lines === []) {
            throw new DomainException('Planda işlenecek satır yok.');
        }
        $existing = $this->db->fetchInt('SELECT COUNT(*) FROM charges WHERE plan_id = ? AND period = ?', [$planId, $period]);
        if ($existing > 0) {
            throw new DomainException(Dates::period($period) . ' dönemi bu plan için zaten işlenmiş (mükerrer tahakkuk engellendi).');
        }
        $dueDate = $period === $plan['period'] ? (string) $plan['due_date'] : Dates::dayOfPeriod($period, (int) $plan['due_day']);
        $count = $this->db->transaction(function () use ($plan, $lines, $period, $dueDate, $buildingId, $planId): int {
            $n = 0;
            $paymentService = new PaymentService($this->app);
            foreach ($lines as $line) {
                $liability = $plan['liability'] === 'bolum' ? (string) $line['liability_mode'] : (string) $plan['liability'];
                $title = $plan['name'] . ' · ' . Dates::period($period);
                $chargeId = $this->db->insert('charges', [
                    'building_id' => $buildingId,
                    'unit_id' => (int) $line['unit_id'],
                    'person_id' => $this->responsiblePerson((int) $line['unit_id'], $liability, $dueDate),
                    'plan_id' => $planId,
                    'charge_type' => $plan['charge_type'],
                    'title' => mb_substr($title, 0, 150),
                    'period' => $period,
                    'due_date' => $dueDate,
                    'amount' => (int) $line['amount'],
                    'paid_amount' => 0,
                    'status' => 'odenmedi',
                    'liability' => $liability,
                    'description' => $plan['description'],
                    'created_by' => $this->app->auth()->id(),
                    'created_at' => Database::now(),
                    'updated_at' => Database::now(),
                ]);
                $paymentService->applyAdvances((int) $line['unit_id']);
                $n++;
            }
            $update = ['last_generated_period' => $period, 'updated_at' => Database::now()];
            if ($plan['recurrence'] === 'tek') {
                $update['status'] = 'islendi';
                $update['processed_at'] = Database::now();
            } elseif ($plan['repeat_until'] !== null && $period >= $plan['repeat_until']) {
                $update['status'] = 'islendi';
                $update['processed_at'] = Database::now();
            }
            $this->db->update('charge_plans', $update, 'id = ?', [$planId]);
            $this->app->audit()->log('plan.process', 'charge_plan', $planId, null, ['period' => $period, 'count' => $n], $buildingId, 'Plan işlendi: ' . $plan['name'] . ' (' . $n . ' borç)');
            return $n;
        });
        return $count;
    }

    /** Tekrarlı onaylı planları verilen dönem için işler (konsol/cron). */
    public function generateRecurringPlans(?int $buildingId, string $period): int
    {
        $sql = "SELECT id, building_id, recurrence, last_generated_period, period FROM charge_plans WHERE status = 'onaylandi' AND recurrence <> 'tek' AND period <= ? AND (repeat_until IS NULL OR repeat_until >= ?)";
        $params = [$period, $period];
        if ($buildingId !== null) {
            $sql .= ' AND building_id = ?';
            $params[] = $buildingId;
        }
        $total = 0;
        foreach ($this->db->fetchAll($sql, $params) as $plan) {
            $step = match ($plan['recurrence']) { 'uc_aylik' => 3, 'yillik' => 12, default => 1 };
            $months = (int) substr($period, 0, 4) * 12 + (int) substr($period, 5, 2) - ((int) substr((string) $plan['period'], 0, 4) * 12 + (int) substr((string) $plan['period'], 5, 2));
            if ($months < 0 || $months % $step !== 0) {
                continue;
            }
            if ($plan['last_generated_period'] !== null && $plan['last_generated_period'] >= $period) {
                continue;
            }
            try {
                $total += $this->processPlan((int) $plan['id'], (int) $plan['building_id'], $period);
            } catch (DomainException) {
                // Kapalı dönem veya mükerrer: atla
            }
        }
        return $total;
    }

    public function cancelPlan(int $planId, int $buildingId, string $reason): void
    {
        $plan = $this->planOrFail($planId, $buildingId);
        if ($plan['status'] === 'iptal') {
            throw new DomainException('Plan zaten iptal.');
        }
        $this->db->update('charge_plans', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => $reason, 'updated_at' => Database::now()], 'id = ?', [$planId]);
        $this->app->audit()->log('plan.cancel', 'charge_plan', $planId, ['status' => $plan['status']], ['status' => 'iptal', 'reason' => $reason], $buildingId, 'Plan iptal edildi: ' . $plan['name']);
    }

    /** @return array<string, mixed> */
    public function planOrFail(int $planId, int $buildingId): array
    {
        $plan = $this->db->fetch('SELECT * FROM charge_plans WHERE id = ? AND building_id = ?', [$planId, $buildingId]);
        return $plan ?? throw new DomainException('Plan bulunamadı.');
    }

    // ------------------------------------------------------------ Tekil borç

    /** @param array<string, mixed> $data unit_id, charge_type, title, period, due_date, amount, liability, description */
    public function createCharge(int $buildingId, array $data): int
    {
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $data['period']);
        $unit = $this->db->fetch('SELECT id, liability_mode FROM units WHERE id = ? AND building_id = ?', [(int) $data['unit_id'], $buildingId]);
        if ($unit === null) {
            throw new DomainException('Bağımsız bölüm bu yapıya ait değil.');
        }
        if ((int) $data['amount'] <= 0) {
            throw new DomainException('Borç tutarı sıfırdan büyük olmalı.');
        }
        $liability = (string) ($data['liability'] ?? $unit['liability_mode'] ?? 'malik');
        return $this->db->transaction(function () use ($buildingId, $data, $liability): int {
            $id = $this->db->insert('charges', [
                'building_id' => $buildingId,
                'unit_id' => (int) $data['unit_id'],
                'person_id' => $data['person_id'] ?? $this->responsiblePerson((int) $data['unit_id'], $liability, (string) $data['due_date']),
                'plan_id' => null,
                'parent_charge_id' => $data['parent_charge_id'] ?? null,
                'charge_type' => $data['charge_type'],
                'title' => mb_substr((string) $data['title'], 0, 150),
                'period' => $data['period'],
                'due_date' => $data['due_date'],
                'amount' => (int) $data['amount'],
                'paid_amount' => 0,
                'status' => 'odenmedi',
                'liability' => $liability,
                'description' => $data['description'] ?? null,
                'late_fee_exempt' => (int) ($data['late_fee_exempt'] ?? 0),
                'created_by' => $this->app->auth()->id(),
                'created_at' => Database::now(),
                'updated_at' => Database::now(),
            ]);
            $this->app->audit()->log('charge.create', 'charge', $id, null, ['unit_id' => $data['unit_id'], 'amount' => $data['amount'], 'title' => $data['title']], $buildingId, 'Borç oluşturuldu: ' . $data['title'] . ' ' . Money::format((int) $data['amount']));
            if (empty($data['skip_advances'])) {
                (new PaymentService($this->app))->applyAdvances((int) $data['unit_id']);
            }
            return $id;
        });
    }

    public function cancelCharge(int $chargeId, int $buildingId, string $reason): void
    {
        $charge = $this->db->fetch('SELECT * FROM charges WHERE id = ? AND building_id = ?', [$chargeId, $buildingId]);
        if ($charge === null) {
            throw new DomainException('Borç kaydı bulunamadı.');
        }
        if ($charge['status'] === 'iptal') {
            throw new DomainException('Bu borç zaten iptal edilmiş.');
        }
        if ((int) $charge['paid_amount'] > 0) {
            throw new DomainException('Kısmen veya tamamen ödenmiş borç iptal edilemez. Önce ilgili tahsilatı iptal edin veya eşleştirmeyi kaldırın.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $charge['period']);
        $this->db->transaction(function () use ($charge, $chargeId, $buildingId, $reason): void {
            $this->db->update('charges', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => $reason, 'cancelled_by' => $this->app->auth()->id(), 'updated_at' => Database::now()], 'id = ?', [$chargeId]);
            // Bağlı gecikme tazminatı borçları da iptal
            $this->db->update('charges', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => 'Ana borç iptal edildi: ' . $reason, 'cancelled_by' => $this->app->auth()->id()], "parent_charge_id = ? AND paid_amount = 0 AND status <> 'iptal'", [$chargeId]);
            $this->app->audit()->log('charge.cancel', 'charge', $chargeId, ['status' => $charge['status']], ['status' => 'iptal', 'reason' => $reason], $buildingId, 'Borç iptal edildi: ' . $charge['title']);
        });
    }

    // ------------------------------------------------------------ Bakiye ve sorgular

    /** @return array{debt: int, advance: int, overdue: int, balance: int} */
    public function unitBalance(int $unitId, ?string $asOf = null): array
    {
        $asOf ??= Dates::today();
        $debt = $this->db->fetchInt("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM charges WHERE unit_id = ? AND status IN ('odenmedi','kismi')", [$unitId]);
        $overdue = $this->db->fetchInt("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM charges WHERE unit_id = ? AND status IN ('odenmedi','kismi') AND due_date < ?", [$unitId, $asOf]);
        $advance = $this->db->fetchInt("SELECT COALESCE(SUM(unallocated_amount), 0) FROM payments WHERE unit_id = ? AND status = 'gecerli'", [$unitId]);
        return ['debt' => $debt, 'advance' => $advance, 'overdue' => $overdue, 'balance' => $debt - $advance];
    }

    /** @return list<array<string, mixed>> */
    public function openCharges(int $unitId): array
    {
        return $this->db->fetchAll("SELECT * FROM charges WHERE unit_id = ? AND status IN ('odenmedi','kismi') ORDER BY due_date, id", [$unitId]);
    }

    /** Yapı genelinde borç özetleri. @return array<string, int> */
    public function buildingDebtSummary(int $buildingId, ?string $asOf = null): array
    {
        $asOf ??= Dates::today();
        return [
            'open' => $this->db->fetchInt("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM charges WHERE building_id = ? AND status IN ('odenmedi','kismi')", [$buildingId]),
            'overdue' => $this->db->fetchInt("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM charges WHERE building_id = ? AND status IN ('odenmedi','kismi') AND due_date < ?", [$buildingId, $asOf]),
            'debtor_units' => $this->db->fetchInt("SELECT COUNT(DISTINCT unit_id) FROM charges WHERE building_id = ? AND status IN ('odenmedi','kismi') AND due_date < ?", [$buildingId, $asOf]),
            'advance' => $this->db->fetchInt("SELECT COALESCE(SUM(unallocated_amount), 0) FROM payments WHERE building_id = ? AND status = 'gecerli'", [$buildingId]),
        ];
    }

    /**
     * Borçlu listesi (bölüm bazlı) + yaşlandırma.
     * @return list<array<string, mixed>>
     */
    public function debtors(int $buildingId, ?string $asOf = null, ?int $blockId = null, int $minDays = 0): array
    {
        $asOf ??= Dates::today();
        $sql = "SELECT u.id AS unit_id, u.door_no, u.type AS unit_type, b.name AS block_name,
                  SUM(c.amount - c.paid_amount) AS total_open,
                  SUM(CASE WHEN c.due_date < ? THEN c.amount - c.paid_amount ELSE 0 END) AS overdue,
                  SUM(CASE WHEN julianday(?) - julianday(c.due_date) BETWEEN 1 AND 30 THEN c.amount - c.paid_amount ELSE 0 END) AS d30,
                  SUM(CASE WHEN julianday(?) - julianday(c.due_date) BETWEEN 31 AND 60 THEN c.amount - c.paid_amount ELSE 0 END) AS d60,
                  SUM(CASE WHEN julianday(?) - julianday(c.due_date) BETWEEN 61 AND 90 THEN c.amount - c.paid_amount ELSE 0 END) AS d90,
                  SUM(CASE WHEN julianday(?) - julianday(c.due_date) > 90 THEN c.amount - c.paid_amount ELSE 0 END) AS d90p,
                  MIN(c.due_date) AS oldest_due, COUNT(*) AS open_count
                FROM charges c JOIN units u ON u.id = c.unit_id LEFT JOIN blocks b ON b.id = u.block_id
                WHERE c.building_id = ? AND c.status IN ('odenmedi','kismi')";
        $params = [$asOf, $asOf, $asOf, $asOf, $asOf, $buildingId];
        if ($this->db->driver() === 'mysql') {
            $sql = str_replace('julianday(?) - julianday(c.due_date)', 'DATEDIFF(?, c.due_date)', $sql);
        }
        if ($blockId) {
            $sql .= ' AND u.block_id = ?';
            $params[] = $blockId;
        }
        $sql .= ' GROUP BY u.id, u.door_no, u.type, b.name HAVING SUM(c.amount - c.paid_amount) > 0 ORDER BY overdue DESC, total_open DESC';
        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $r['days'] = $r['oldest_due'] ? Dates::daysBetween((string) $r['oldest_due'], $asOf) : 0;
            $r['responsible'] = $this->responsibleName((int) $r['unit_id']);
        }
        unset($r);
        if ($minDays > 0) {
            $rows = array_values(array_filter($rows, static fn ($r) => $r['days'] >= $minDays));
        }
        return $rows;
    }

    public function responsibleName(int $unitId): string
    {
        $row = $this->db->fetch(
            "SELECT p.first_name, p.last_name, p.company_name, o.role FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = ? AND (o.end_date IS NULL OR o.end_date >= ?) ORDER BY CASE o.role WHEN 'malik' THEN 0 WHEN 'kiraci' THEN 1 ELSE 2 END LIMIT 1",
            [$unitId, Dates::today()],
        );
        if ($row === null) {
            return '—';
        }
        $name = $row['company_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']);
        return $name . ' (' . list_label('occupancy_roles', $row['role']) . ')';
    }
}
