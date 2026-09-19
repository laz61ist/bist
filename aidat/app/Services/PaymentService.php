<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/**
 * Tahsilat: kayıt, borca dağıtım (en eski / manuel / avans), makbuz, iptal/iade, avans mahsubu.
 * Kural: dağıtım toplamı tahsilat tutarını aşamaz; kalan avanstır. Kayıt silinmez.
 */
final class PaymentService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /**
     * @param array<string, mixed> $data unit_id, person_id?, account_id, payment_date, amount(kuruş), method, reference_no?, description?, allocation_mode, manual(array charge_id=>kuruş)?, import_row_id?
     * @return int payment id
     */
    public function create(int $buildingId, array $data): int
    {
        $amount = (int) $data['amount'];
        if ($amount <= 0) {
            throw new DomainException('Tahsilat tutarı sıfırdan büyük olmalı.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $data['payment_date']);
        $unitId = isset($data['unit_id']) && $data['unit_id'] ? (int) $data['unit_id'] : null;
        if ($unitId !== null) {
            $unit = $this->db->fetch('SELECT id FROM units WHERE id = ? AND building_id = ?', [$unitId, $buildingId]);
            if ($unit === null) {
                throw new DomainException('Bağımsız bölüm bu yapıya ait değil.');
            }
        }
        $mode = (string) ($data['allocation_mode'] ?? 'eski');
        if ($unitId === null && $mode !== 'avans') {
            throw new DomainException('Bölüm seçilmeden tahsilat yalnızca avans olarak kaydedilebilir.');
        }
        $ledger = new LedgerService($this->app);
        $receipts = new ReceiptService($this->app);

        return $this->db->transaction(function () use ($buildingId, $data, $amount, $unitId, $mode, $ledger, $receipts): int {
            $receipt = $receipts->next($buildingId, (string) $data['payment_date']);
            $paymentId = $this->db->insert('payments', [
                'building_id' => $buildingId,
                'unit_id' => $unitId,
                'person_id' => $data['person_id'] ?? ($unitId ? (new ChargeService($this->app))->responsiblePerson($unitId, 'malik') : null),
                'account_id' => (int) $data['account_id'],
                'payment_date' => $data['payment_date'],
                'payment_time' => $data['payment_time'] ?? date('H:i'),
                'amount' => $amount,
                'method' => $data['method'] ?? 'nakit',
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'allocation_mode' => $mode,
                'unallocated_amount' => $amount,
                'receipt_no' => $receipt['no'],
                'receipt_seq' => $receipt['seq'],
                'receipt_year' => $receipt['year'],
                'status' => 'gecerli',
                'collected_by' => $this->app->auth()->id(),
                'import_row_id' => $data['import_row_id'] ?? null,
                'created_by' => $this->app->auth()->id(),
                'created_at' => Database::now(),
                'updated_at' => Database::now(),
            ]);
            $this->db->update('payments', ['verify_code' => $receipts->verifyCode($paymentId, $buildingId)], 'id = ?', [$paymentId]);

            if ($unitId !== null && $mode !== 'avans') {
                $manual = $mode === 'manuel' ? array_map('intval', (array) ($data['manual'] ?? [])) : null;
                $this->allocate($paymentId, $unitId, $amount, $manual);
            }
            $ledger->record($buildingId, (int) $data['account_id'], (string) $data['payment_date'], 'in', $amount, 'payment', $paymentId, 'Tahsilat ' . $receipt['no'] . ($unitId ? ' · bölüm #' . $unitId : ''));
            $this->app->audit()->log('payment.create', 'payment', $paymentId, null, ['amount' => $amount, 'unit_id' => $unitId, 'method' => $data['method'] ?? 'nakit', 'receipt_no' => $receipt['no']], $buildingId, 'Tahsilat kaydedildi: ' . $receipt['no'] . ' ' . Money::format($amount));
            return $paymentId;
        });
    }

    /**
     * Ödemeyi açık borçlara dağıtır. $manual verilirse yalnızca o borçlara, verilmezse en eski vadeden başlar.
     * @param array<int, int>|null $manual charge_id => kuruş
     */
    private function allocate(int $paymentId, int $unitId, int $amount, ?array $manual): void
    {
        $open = $this->db->fetchAll("SELECT id, amount, paid_amount FROM charges WHERE unit_id = ? AND status IN ('odenmedi','kismi') ORDER BY due_date, id", [$unitId]);
        $remaining = $amount;
        $allocatedTotal = 0;
        foreach ($open as $c) {
            if ($remaining <= 0) {
                break;
            }
            $openAmt = (int) $c['amount'] - (int) $c['paid_amount'];
            if ($openAmt <= 0) {
                continue;
            }
            if ($manual !== null) {
                $want = (int) ($manual[(int) $c['id']] ?? 0);
                if ($want <= 0) {
                    continue;
                }
                $alloc = min($want, $openAmt, $remaining);
            } else {
                $alloc = min($openAmt, $remaining);
            }
            if ($alloc <= 0) {
                continue;
            }
            $this->db->insert('payment_allocations', ['payment_id' => $paymentId, 'charge_id' => (int) $c['id'], 'amount' => $alloc, 'created_at' => Database::now()]);
            $newPaid = (int) $c['paid_amount'] + $alloc;
            $this->db->update('charges', ['paid_amount' => $newPaid, 'status' => $newPaid >= (int) $c['amount'] ? 'odendi' : 'kismi', 'updated_at' => Database::now()], 'id = ?', [(int) $c['id']]);
            $remaining -= $alloc;
            $allocatedTotal += $alloc;
        }
        if ($allocatedTotal > $amount) {
            throw new DomainException('Dağıtım toplamı tahsilat tutarını aşamaz.');
        }
        $this->db->update('payments', ['unallocated_amount' => $amount - $allocatedTotal], 'id = ?', [$paymentId]);
    }

    /** Bölümdeki avansları (dağıtılmamış tahsilat) açık borçlara mahsup eder. @return int mahsup edilen kuruş */
    public function applyAdvances(int $unitId): int
    {
        $advances = $this->db->fetchAll("SELECT id, unallocated_amount FROM payments WHERE unit_id = ? AND status = 'gecerli' AND unallocated_amount > 0 ORDER BY payment_date, id", [$unitId]);
        if ($advances === []) {
            return 0;
        }
        $applied = 0;
        foreach ($advances as $p) {
            $open = $this->db->fetchAll("SELECT id, amount, paid_amount FROM charges WHERE unit_id = ? AND status IN ('odenmedi','kismi') ORDER BY due_date, id", [$unitId]);
            if ($open === []) {
                break;
            }
            $remaining = (int) $p['unallocated_amount'];
            foreach ($open as $c) {
                if ($remaining <= 0) {
                    break;
                }
                $openAmt = (int) $c['amount'] - (int) $c['paid_amount'];
                $alloc = min($openAmt, $remaining);
                if ($alloc <= 0) {
                    continue;
                }
                $this->db->insert('payment_allocations', ['payment_id' => (int) $p['id'], 'charge_id' => (int) $c['id'], 'amount' => $alloc, 'created_at' => Database::now()]);
                $newPaid = (int) $c['paid_amount'] + $alloc;
                $this->db->update('charges', ['paid_amount' => $newPaid, 'status' => $newPaid >= (int) $c['amount'] ? 'odendi' : 'kismi', 'updated_at' => Database::now()], 'id = ?', [(int) $c['id']]);
                $remaining -= $alloc;
                $applied += $alloc;
            }
            $this->db->update('payments', ['unallocated_amount' => $remaining, 'updated_at' => Database::now()], 'id = ?', [(int) $p['id']]);
        }
        return $applied;
    }

    /** Tahsilat iptali: eşleştirmeler kaldırılır, borçlar geri açılır, defterde ters kayıt oluşur. Makbuz numarası korunur. */
    public function cancel(int $paymentId, int $buildingId, string $reason, bool $refund = false, ?string $date = null): void
    {
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ? AND building_id = ?', [$paymentId, $buildingId]);
        if ($payment === null) {
            throw new DomainException('Tahsilat bulunamadı.');
        }
        if ($payment['status'] !== 'gecerli') {
            throw new DomainException('Bu tahsilat zaten iptal/iade edilmiş.');
        }
        $date ??= Dates::today();
        $periods = new PeriodService($this->app);
        $periods->assertOpen($buildingId, $date);
        $this->db->transaction(function () use ($payment, $paymentId, $buildingId, $reason, $refund, $date): void {
            $this->releaseAllocations($paymentId);
            $this->db->update('payments', [
                'status' => $refund ? 'iade' : 'iptal',
                'unallocated_amount' => 0,
                'cancelled_at' => Database::now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $this->app->auth()->id(),
                'updated_at' => Database::now(),
            ], 'id = ?', [$paymentId]);
            (new LedgerService($this->app))->reverse('payment', $paymentId, $date, ($refund ? 'İade' : 'İptal') . ' · ' . $payment['receipt_no'] . ' · ' . $reason);
            $this->app->audit()->log($refund ? 'payment.refund' : 'payment.cancel', 'payment', $paymentId, ['status' => 'gecerli'], ['status' => $refund ? 'iade' : 'iptal', 'reason' => $reason], $buildingId, ($refund ? 'Tahsilat iade edildi: ' : 'Tahsilat iptal edildi: ') . $payment['receipt_no']);
        });
    }

    private function releaseAllocations(int $paymentId): void
    {
        $allocs = $this->db->fetchAll('SELECT * FROM payment_allocations WHERE payment_id = ?', [$paymentId]);
        foreach ($allocs as $a) {
            $c = $this->db->fetch('SELECT id, amount, paid_amount, status FROM charges WHERE id = ?', [(int) $a['charge_id']]);
            if ($c === null) {
                continue;
            }
            $newPaid = max(0, (int) $c['paid_amount'] - (int) $a['amount']);
            $status = $c['status'] === 'iptal' ? 'iptal' : ($newPaid <= 0 ? 'odenmedi' : ($newPaid >= (int) $c['amount'] ? 'odendi' : 'kismi'));
            $this->db->update('charges', ['paid_amount' => $newPaid, 'status' => $status, 'updated_at' => Database::now()], 'id = ?', [(int) $c['id']]);
        }
        $this->db->delete('payment_allocations', 'payment_id = ?', [$paymentId]);
    }

    /** @return list<array<string, mixed>> */
    public function allocationsOf(int $paymentId): array
    {
        return $this->db->fetchAll('SELECT a.*, c.title, c.period, c.due_date, c.charge_type, c.amount AS charge_amount FROM payment_allocations a JOIN charges c ON c.id = a.charge_id WHERE a.payment_id = ? ORDER BY c.due_date', [$paymentId]);
    }

    /**
     * Bölüm cari ekstresi: borç (+) ve tahsilat (−) satırları, yürüyen bakiye.
     * @return array{opening: int, rows: list<array<string, mixed>>, closing: int}
     */
    public function statement(int $unitId, string $from, string $to): array
    {
        $openingDebt = $this->db->fetchInt("SELECT COALESCE(SUM(amount), 0) FROM charges WHERE unit_id = ? AND status <> 'iptal' AND due_date < ?", [$unitId, $from]);
        $openingPaid = $this->db->fetchInt("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE unit_id = ? AND status = 'gecerli' AND payment_date < ?", [$unitId, $from]);
        $opening = $openingDebt - $openingPaid;
        $charges = $this->db->fetchAll("SELECT id, due_date AS d, title, charge_type, amount, status, 'charge' AS kind FROM charges WHERE unit_id = ? AND status <> 'iptal' AND due_date BETWEEN ? AND ?", [$unitId, $from, $to]);
        $payments = $this->db->fetchAll("SELECT id, payment_date AS d, receipt_no AS title, method AS charge_type, amount, status, 'payment' AS kind FROM payments WHERE unit_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$unitId, $from, $to]);
        $rows = array_merge($charges, $payments);
        usort($rows, static fn ($a, $b) => [$a['d'], $a['kind'] === 'charge' ? 0 : 1, $a['id']] <=> [$b['d'], $b['kind'] === 'charge' ? 0 : 1, $b['id']]);
        $running = $opening;
        foreach ($rows as &$r) {
            $running += $r['kind'] === 'charge' ? (int) $r['amount'] : -(int) $r['amount'];
            $r['running'] = $running;
        }
        return ['opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    /** @return array<string, mixed>|null makbuz verisi */
    public function receiptData(int $paymentId, ?int $buildingId = null): ?array
    {
        $sql = 'SELECT p.*, b.name AS building_name, b.address AS building_address, b.tax_no AS building_tax_no, b.iban AS building_iban, b.bank_name AS building_bank,
                       u.door_no, u.type AS unit_type, bl.name AS block_name,
                       per.first_name, per.last_name, per.company_name,
                       a.name AS account_name, a.type AS account_type, usr.name AS collector_name
                FROM payments p
                JOIN buildings b ON b.id = p.building_id
                LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
                LEFT JOIN people per ON per.id = p.person_id
                LEFT JOIN accounts a ON a.id = p.account_id
                LEFT JOIN users usr ON usr.id = p.collected_by
                WHERE p.id = ?';
        $params = [$paymentId];
        if ($buildingId !== null) {
            $sql .= ' AND p.building_id = ?';
            $params[] = $buildingId;
        }
        $p = $this->db->fetch($sql, $params);
        if ($p === null) {
            return null;
        }
        $p['allocations'] = $this->allocationsOf($paymentId);
        $p['payer_name'] = $p['company_name'] ?: trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
        $p['amount_words'] = Money::words((int) $p['amount']);
        $bal = $p['unit_id'] ? (new ChargeService($this->app))->unitBalance((int) $p['unit_id']) : null;
        $p['remaining_debt'] = $bal ? $bal['debt'] : null;
        $p['advance'] = $bal ? $bal['advance'] : null;
        return $p;
    }
}
