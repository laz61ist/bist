<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/** Gider kayıtları: KDV, ödeme (kısmi/tam), iptal (ters kayıt), kategori özetleri. */
final class ExpenseService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @return array{amount: int, vat: int} toplam ve KDV tutarı (kuruş) */
    public static function vat(int $amount, string $mode, float $rate): array
    {
        if ($mode === 'yok' || $rate <= 0) {
            return ['amount' => $amount, 'vat' => 0];
        }
        if ($mode === 'haric') {
            $vat = (int) round($amount * $rate / 100);
            return ['amount' => $amount + $vat, 'vat' => $vat];
        }
        $vat = (int) round($amount - $amount / (1 + $rate / 100));
        return ['amount' => $amount, 'vat' => $vat];
    }

    /** @param array<string, mixed> $d doğrulanmış alanlar */
    public function create(int $buildingId, array $d): int
    {
        $periods = new PeriodService($this->app);
        $periods->assertOpen($buildingId, (string) $d['expense_date']);
        $calc = self::vat((int) $d['amount'], (string) ($d['vat_mode'] ?? 'dahil'), (float) ($d['vat_rate'] ?? 0));
        $status = (string) ($d['status'] ?? 'odendi');
        if ($status === 'odendi' && empty($d['account_id'])) {
            throw new DomainException('Ödendi olarak kaydetmek için kasa/banka hesabı seçin.');
        }
        return $this->db->transaction(function () use ($buildingId, $d, $calc, $status): int {
            $id = $this->db->insert('expenses', [
                'building_id' => $buildingId,
                'category_id' => ($d['category_id'] ?? null) ?: null,
                'vendor_id' => ($d['vendor_id'] ?? null) ?: null,
                'contract_id' => $d['contract_id'] ?? null,
                'account_id' => ($d['account_id'] ?? null) ?: null,
                'expense_date' => $d['expense_date'],
                'due_date' => ($d['due_date'] ?? null) ?: null,
                'period' => ($d['period'] ?? null) ?: substr((string) $d['expense_date'], 0, 7),
                'amount' => $calc['amount'],
                'vat_mode' => $d['vat_mode'] ?? 'dahil',
                'vat_rate' => (float) ($d['vat_rate'] ?? 0),
                'vat_amount' => $calc['vat'],
                'document_kind' => $d['document_kind'] ?? 'fatura',
                'document_no' => ($d['document_no'] ?? null) ?: null,
                'description' => ($d['description'] ?? null) ?: null,
                'budget_line_id' => $d['budget_line_id'] ?? null,
                'scope' => $d['scope'] ?? 'tumu',
                'block_id' => ($d['scope'] ?? 'tumu') === 'blok' ? (($d['block_id'] ?? null) ?: null) : null,
                'status' => $status === 'odendi' ? 'odendi' : 'planlandi',
                'paid_amount' => 0,
                'recurring_id' => $d['recurring_id'] ?? null,
                'created_by' => $this->app->auth()->id(),
                'created_at' => Database::now(),
                'updated_at' => Database::now(),
            ]);
            if ($status === 'odendi') {
                $this->pay($id, $buildingId, (int) $d['account_id'], $calc['amount'], (string) $d['expense_date'], $d['document_no'] ?? null, false);
            }
            $this->app->audit()->log('expense.create', 'expense', $id, null, ['amount' => $calc['amount'], 'category_id' => $d['category_id'] ?? null, 'description' => $d['description'] ?? null], $buildingId, 'Gider kaydedildi: ' . Money::format($calc['amount']));
            return $id;
        });
    }

    /** @param array<string, mixed> $d */
    public function update(int $expenseId, int $buildingId, array $d): void
    {
        $old = $this->find($expenseId, $buildingId);
        if ($old['status'] === 'iptal') {
            throw new DomainException('İptal edilmiş gider düzenlenemez.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $old['expense_date']);
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $d['expense_date']);
        $calc = self::vat((int) $d['amount'], (string) ($d['vat_mode'] ?? 'dahil'), (float) ($d['vat_rate'] ?? 0));
        if ((int) $old['paid_amount'] > $calc['amount']) {
            throw new DomainException('Tutar, ödenmiş tutarın (' . Money::format((int) $old['paid_amount']) . ') altına indirilemez.');
        }
        $new = [
            'category_id' => ($d['category_id'] ?? null) ?: null,
            'vendor_id' => ($d['vendor_id'] ?? null) ?: null,
            'expense_date' => $d['expense_date'],
            'due_date' => ($d['due_date'] ?? null) ?: null,
            'period' => ($d['period'] ?? null) ?: substr((string) $d['expense_date'], 0, 7),
            'amount' => $calc['amount'],
            'vat_mode' => $d['vat_mode'] ?? 'dahil',
            'vat_rate' => (float) ($d['vat_rate'] ?? 0),
            'vat_amount' => $calc['vat'],
            'document_kind' => $d['document_kind'] ?? 'fatura',
            'document_no' => ($d['document_no'] ?? null) ?: null,
            'description' => ($d['description'] ?? null) ?: null,
            'scope' => $d['scope'] ?? 'tumu',
            'block_id' => ($d['scope'] ?? 'tumu') === 'blok' ? (($d['block_id'] ?? null) ?: null) : null,
            'status' => $this->statusFor($calc['amount'], (int) $old['paid_amount'], ($d['due_date'] ?? null) ?: null),
            'updated_at' => Database::now(),
        ];
        $this->db->update('expenses', $new, 'id = ?', [$expenseId]);
        [$o, $n] = \Aidat\Core\Audit::diff($old, $new);
        $this->app->audit()->log('expense.update', 'expense', $expenseId, $o, $n, $buildingId, 'Gider güncellendi');
    }

    private function statusFor(int $amount, int $paid, ?string $dueDate): string
    {
        if ($paid >= $amount) {
            return 'odendi';
        }
        if ($paid > 0) {
            return 'kismi';
        }
        if ($dueDate !== null && $dueDate < Dates::today()) {
            return 'gecikti';
        }
        return 'planlandi';
    }

    /** Gider ödemesi (kısmi olabilir): expense_payments + defter çıkışı. */
    public function pay(int $expenseId, int $buildingId, int $accountId, int $amount, string $date, ?string $reference = null, bool $audit = true): int
    {
        $e = $this->find($expenseId, $buildingId);
        if ($e['status'] === 'iptal') {
            throw new DomainException('İptal edilmiş gidere ödeme yapılamaz.');
        }
        $open = (int) $e['amount'] - (int) $e['paid_amount'];
        if ($amount <= 0 || $amount > $open) {
            throw new DomainException('Ödeme tutarı 0 ile kalan tutar (' . Money::format($open) . ') arasında olmalı.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, $date);
        return $this->db->transaction(function () use ($e, $expenseId, $buildingId, $accountId, $amount, $date, $reference, $audit): int {
            $pid = $this->db->insert('expense_payments', [
                'expense_id' => $expenseId, 'account_id' => $accountId, 'paid_date' => $date, 'amount' => $amount,
                'reference_no' => $reference, 'created_by' => $this->app->auth()->id(), 'created_at' => Database::now(),
            ]);
            (new LedgerService($this->app))->record($buildingId, $accountId, $date, 'out', $amount, 'expense', $pid, 'Gider ödemesi #' . $expenseId . ' ' . ($e['description'] ? mb_substr((string) $e['description'], 0, 60) : ''));
            $paid = (int) $e['paid_amount'] + $amount;
            $this->db->update('expenses', ['paid_amount' => $paid, 'account_id' => $accountId, 'status' => $paid >= (int) $e['amount'] ? 'odendi' : 'kismi', 'updated_at' => Database::now()], 'id = ?', [$expenseId]);
            if ($audit) {
                $this->app->audit()->log('expense.pay', 'expense', $expenseId, null, ['amount' => $amount, 'account_id' => $accountId], $buildingId, 'Gider ödendi: ' . Money::format($amount));
            }
            return $pid;
        });
    }

    public function cancel(int $expenseId, int $buildingId, string $reason): void
    {
        $e = $this->find($expenseId, $buildingId);
        if ($e['status'] === 'iptal') {
            throw new DomainException('Gider zaten iptal.');
        }
        $today = Dates::today();
        (new PeriodService($this->app))->assertOpen($buildingId, $today);
        $this->db->transaction(function () use ($e, $expenseId, $buildingId, $reason, $today): void {
            $ledger = new LedgerService($this->app);
            foreach ($this->db->fetchAll('SELECT id FROM expense_payments WHERE expense_id = ?', [$expenseId]) as $p) {
                $ledger->reverse('expense', (int) $p['id'], $today, 'Gider iptali #' . $expenseId . ' · ' . $reason);
            }
            $this->db->update('expenses', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => $reason, 'cancelled_by' => $this->app->auth()->id(), 'updated_at' => Database::now()], 'id = ?', [$expenseId]);
            $this->app->audit()->log('expense.cancel', 'expense', $expenseId, ['status' => $e['status']], ['status' => 'iptal', 'reason' => $reason], $buildingId, 'Gider iptal edildi: ' . Money::format((int) $e['amount']));
        });
    }

    /** @return array<string, mixed> */
    public function find(int $expenseId, int $buildingId): array
    {
        return $this->db->fetch('SELECT * FROM expenses WHERE id = ? AND building_id = ?', [$expenseId, $buildingId]) ?? throw new DomainException('Gider bulunamadı.');
    }

    /** Kategori bazlı toplam. @return list<array<string, mixed>> */
    public function byCategory(int $buildingId, string $from, string $to): array
    {
        return $this->db->fetchAll(
            "SELECT COALESCE(pc.name, c.name, 'Kategorisiz') AS parent_name, COALESCE(c.name, 'Kategorisiz') AS name, SUM(e.amount) AS total, COUNT(*) AS n
             FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id LEFT JOIN expense_categories pc ON pc.id = c.parent_id
             WHERE e.building_id = ? AND e.status <> 'iptal' AND e.expense_date BETWEEN ? AND ?
             GROUP BY COALESCE(pc.name, c.name, 'Kategorisiz'), COALESCE(c.name, 'Kategorisiz') ORDER BY total DESC",
            [$buildingId, $from, $to],
        );
    }

    /** Yapının kategori ağacı (global + yapıya özel). @return array<string, array<int, string>> optgroup */
    public function categoryOptions(int $buildingId): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM expense_categories WHERE (building_id IS NULL OR building_id = ?) AND is_active = 1 ORDER BY sort_order, name', [$buildingId]);
        $parents = [];
        $children = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $parents[(int) $r['id']] = $r['name'];
            } else {
                $children[(int) $r['parent_id']][(int) $r['id']] = $r['name'];
            }
        }
        $out = [];
        foreach ($parents as $pid => $pname) {
            if (isset($children[$pid])) {
                $out[$pname] = $children[$pid];
            } else {
                $out[$pname] = [$pid => $pname];
            }
        }
        return $out;
    }

    /** Yapı için varsayılan kategori setini oluşturur (yoksa). */
    public function ensureDefaultCategories(int $buildingId): void
    {
        $count = $this->db->fetchInt('SELECT COUNT(*) FROM expense_categories WHERE building_id = ?', [$buildingId]);
        if ($count > 0) {
            return;
        }
        $defaults = $this->app->config()->get('lists.expense_categories_default', []);
        $order = 0;
        foreach ($defaults as $group) {
            $pid = $this->db->insert('expense_categories', ['building_id' => $buildingId, 'parent_id' => null, 'name' => $group['name'], 'is_active' => 1, 'sort_order' => $order++, 'created_at' => Database::now()]);
            foreach ($group['children'] as $child) {
                $this->db->insert('expense_categories', ['building_id' => $buildingId, 'parent_id' => $pid, 'name' => $child, 'is_active' => 1, 'sort_order' => $order++, 'created_at' => Database::now()]);
            }
        }
        $order = 0;
        foreach ($this->app->config()->get('lists.income_categories_default', []) as $name) {
            $this->db->insert('income_categories', ['building_id' => $buildingId, 'name' => $name, 'is_active' => 1, 'sort_order' => $order++, 'created_at' => Database::now()]);
        }
    }
}
