<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Response;
use Aidat\Services\ExpenseService;
use Aidat\Services\RecurringExpenseService;

/** Periyodik giderler: kapıcı maaşı, asansör bakımı gibi tekrarlayan kalemler ve dönem üretimi. */
final class RecurringController extends Controller
{
    private const RULES = [
        'title' => 'required|max:150',
        'category_id' => 'nullable|integer',
        'vendor_id' => 'nullable|integer',
        'account_id' => 'nullable|integer',
        'amount' => 'required|money_positive',
        'day_of_month' => 'required|integer|min:1|max:31',
        'frequency' => 'required|in:aylik,uc_aylik,yillik',
        'start_period' => 'required|period',
        'end_period' => 'nullable|period',
        'auto_paid' => 'boolean',
        'is_active' => 'boolean',
        'notes' => 'nullable|max:2000',
    ];
    private const LABELS = ['title' => 'başlık', 'category_id' => 'kategori', 'vendor_id' => 'tedarikçi', 'account_id' => 'kasa/banka', 'amount' => 'tutar', 'day_of_month' => 'ayın günü', 'frequency' => 'sıklık', 'start_period' => 'başlangıç dönemi', 'end_period' => 'bitiş dönemi', 'notes' => 'notlar'];
    private const FREQUENCIES = ['aylik' => 'Aylık', 'uc_aylik' => 'Üç aylık', 'yillik' => 'Yıllık'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $rows = $this->db->fetchAll(
            'SELECT r.*, c.name AS category_name, v.name AS vendor_name, a.name AS account_name,
                    (SELECT COUNT(*) FROM expenses e WHERE e.recurring_id = r.id) AS generated_count,
                    (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.recurring_id = r.id AND e.status <> ?) AS generated_total
             FROM recurring_expenses r LEFT JOIN expense_categories c ON c.id = r.category_id LEFT JOIN vendors v ON v.id = r.vendor_id LEFT JOIN accounts a ON a.id = r.account_id
             WHERE r.building_id = ? ORDER BY r.is_active DESC, r.day_of_month, r.title',
            ['iptal', $b],
        );
        $current = Dates::currentPeriod();
        $monthlyTotal = 0;
        foreach ($rows as &$r) {
            $r['next_period'] = $this->nextPeriod($r);
            $r['due_now'] = $r['next_period'] !== null && $r['next_period'] <= $current;
            if ((int) $r['is_active'] === 1) {
                $monthlyTotal += intdiv((int) $r['amount'], match ($r['frequency']) { 'uc_aylik' => 3, 'yillik' => 12, default => 1 });
            }
        }
        unset($r);
        return $this->view('manager.recurring.index', [
            'title' => 'Periyodik giderler', 'rows' => $rows, 'current' => $current, 'monthlyTotal' => $monthlyTotal, 'frequencies' => self::FREQUENCIES,
            'active' => count(array_filter($rows, static fn (array $r) => (int) $r['is_active'] === 1)),
            'dueNow' => count(array_filter($rows, static fn (array $r) => (int) $r['is_active'] === 1 && $r['due_now'])),
        ]);
    }

    public function create(): Response
    {
        return $this->view('manager.recurring.form', ['title' => 'Yeni periyodik gider', 'row' => null, 'defaults' => ['start_period' => Dates::currentPeriod(), 'day_of_month' => 1, 'frequency' => 'aylik']] + $this->formData());
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $b = $this->buildingId();
        $this->assertRefs($d, $b);
        $id = $this->db->insert('recurring_expenses', $this->payload($d) + ['building_id' => $b, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('recurring.create', 'recurring_expense', $id, null, $d, 'Periyodik gider eklendi: ' . $d['title']);
        $this->success('Periyodik gider eklendi. "Bu dönemi üret" ile gider kayıtlarını oluşturabilirsiniz.');
        return $this->redirectRoute('recurring.index');
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('recurring_expenses', $id);
        return $this->view('manager.recurring.form', ['title' => $row['title'] . ' düzenle', 'row' => $row, 'defaults' => []] + $this->formData());
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('recurring_expenses', $id);
        if ($this->request->has('toggle_active')) {
            $new = ['is_active' => (int) $row['is_active'] === 1 ? 0 : 1];
            $this->db->update('recurring_expenses', $new + ['updated_at' => Database::now()], 'id = ?', [$id]);
            $this->audit('recurring.toggle', 'recurring_expense', $id, ['is_active' => $row['is_active']], $new, ($new['is_active'] ? 'Etkinleştirildi: ' : 'Durduruldu: ') . $row['title']);
            $this->success($new['is_active'] ? 'Periyodik gider etkinleştirildi.' : 'Periyodik gider durduruldu; dönem üretiminde atlanır.');
            return $this->redirectRoute('recurring.index');
        }
        $d = $this->validate(self::RULES, self::LABELS);
        $this->assertRefs($d, $this->buildingId());
        $new = $this->payload($d);
        $this->db->update('recurring_expenses', $new + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $new);
        $this->audit('recurring.update', 'recurring_expense', $id, $o, $n, 'Periyodik gider güncellendi: ' . $d['title']);
        $this->success('Periyodik gider güncellendi.');
        return $this->redirectRoute('recurring.index');
    }

    public function generate(): Response
    {
        $d = $this->validate(['period' => 'required|period'], ['period' => 'dönem']);
        $b = $this->buildingId();
        $period = (string) $d['period'];
        if ($period > Dates::addMonths(Dates::currentPeriod(), 1)) {
            throw new DomainException('En fazla bir sonraki dönem üretilebilir.');
        }
        $n = (new RecurringExpenseService($this->app))->generate($period, $b);
        $this->audit('recurring.generate', 'recurring_expense', null, null, ['period' => $period, 'count' => $n], Dates::period($period) . ' için ' . $n . ' periyodik gider üretildi');
        if ($n === 0) {
            $this->error(Dates::period($period) . ' dönemi için üretilecek gider yok (hepsi zaten üretilmiş, dönem kapalı veya sıklık uymuyor).');
        } else {
            $this->success(Dates::period($period) . ' dönemi için ' . $n . ' gider kaydı üretildi.');
        }
        return $this->redirectRoute($n > 0 ? 'expenses.index' : 'recurring.index', $n > 0 ? ['donem' => $period] : []);
    }

    /** @param array<string, mixed> $r */
    private function nextPeriod(array $r): ?string
    {
        $step = match ($r['frequency']) { 'uc_aylik' => 3, 'yillik' => 12, default => 1 };
        $next = $r['last_generated_period'] !== null && $r['last_generated_period'] >= $r['start_period']
            ? Dates::addMonths((string) $r['last_generated_period'], $step)
            : (string) $r['start_period'];
        if ($r['end_period'] !== null && $next > $r['end_period']) {
            return null;
        }
        return $next;
    }

    /** @param array<string, mixed> $d @return array<string, mixed> */
    private function payload(array $d): array
    {
        return [
            'title' => $d['title'], 'category_id' => $d['category_id'] ?: null, 'vendor_id' => $d['vendor_id'] ?: null, 'account_id' => $d['account_id'] ?: null,
            'amount' => (int) $d['amount'], 'day_of_month' => (int) $d['day_of_month'], 'frequency' => $d['frequency'], 'start_period' => $d['start_period'], 'end_period' => $d['end_period'] ?: null,
            'auto_paid' => (int) ($d['auto_paid'] ?? 0), 'is_active' => (int) ($d['is_active'] ?? 0), 'notes' => $d['notes'] ?: null,
        ];
    }

    /** @param array<string, mixed> $d */
    private function assertRefs(array $d, int $b): void
    {
        $errors = [];
        if (!empty($d['category_id']) && $this->db->fetch('SELECT id FROM expense_categories WHERE id = ? AND (building_id IS NULL OR building_id = ?)', [(int) $d['category_id'], $b]) === null) {
            $errors['category_id'] = 'Geçersiz kategori.';
        }
        if (!empty($d['vendor_id']) && $this->db->fetch('SELECT id FROM vendors WHERE id = ? AND building_id = ?', [(int) $d['vendor_id'], $b]) === null) {
            $errors['vendor_id'] = 'Geçersiz tedarikçi.';
        }
        if (!empty($d['account_id']) && $this->db->fetch('SELECT id FROM accounts WHERE id = ? AND building_id = ?', [(int) $d['account_id'], $b]) === null) {
            $errors['account_id'] = 'Geçersiz kasa/banka hesabı.';
        }
        if (!empty($d['auto_paid']) && empty($d['account_id'])) {
            $errors['account_id'] = 'Otomatik ödendi için kasa/banka hesabı seçin.';
        }
        if (!empty($d['end_period']) && $d['end_period'] < $d['start_period']) {
            $errors['end_period'] = 'Bitiş dönemi başlangıçtan önce olamaz.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $this->request->all());
        }
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $b = $this->buildingId();
        $accounts = [];
        foreach ($this->db->fetchAll('SELECT id, name, type FROM accounts WHERE building_id = ? AND is_active = 1 ORDER BY is_default DESC, type, name', [$b]) as $a) {
            $accounts[(int) $a['id']] = $a['name'] . ' (' . ($a['type'] === 'banka' ? 'banka' : 'kasa') . ')';
        }
        return [
            'categories' => (new ExpenseService($this->app))->categoryOptions($b),
            'vendors' => $this->db->fetchPairs('SELECT id, name FROM vendors WHERE building_id = ? AND is_active = 1 ORDER BY name', [$b]),
            'accounts' => $accounts,
            'frequencies' => self::FREQUENCIES,
        ];
    }
}
