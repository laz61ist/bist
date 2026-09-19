<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Exporter;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\ExpenseService;
use Aidat\Services\LedgerService;

final class ExpenseController extends Controller
{
    private const RULES = [
        'expense_date' => 'required|date',
        'due_date' => 'nullable|date',
        'period' => 'nullable|period',
        'category_id' => 'nullable|integer',
        'vendor_id' => 'nullable|integer',
        'contract_id' => 'nullable|integer',
        'account_id' => 'nullable|integer',
        'amount' => 'required|money_positive',
        'vat_mode' => 'required|in_keys:lists.vat_modes',
        'vat_rate' => 'required|in:0,1,10,20',
        'document_kind' => 'required|in_keys:lists.document_kinds',
        'document_no' => 'nullable|max:60',
        'description' => 'nullable|max:2000',
        'scope' => 'required|in:tumu,blok',
        'block_id' => 'nullable|integer',
        'budget_line_id' => 'nullable|integer',
    ];
    private const LABELS = ['expense_date' => 'gider tarihi', 'due_date' => 'vade', 'period' => 'dönem', 'category_id' => 'kategori', 'vendor_id' => 'tedarikçi', 'contract_id' => 'sözleşme', 'account_id' => 'kasa/banka', 'amount' => 'tutar', 'vat_mode' => 'KDV durumu', 'vat_rate' => 'KDV oranı', 'document_kind' => 'belge türü', 'document_no' => 'belge no', 'description' => 'açıklama', 'scope' => 'kapsam', 'block_id' => 'blok', 'budget_line_id' => 'bütçe kalemi', 'status' => 'durum'];
    private const SELECT = "SELECT e.*, c.name AS category_name, pc.name AS parent_name, v.name AS vendor_name, a.name AS account_name, bl.name AS block_name
             FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id LEFT JOIN expense_categories pc ON pc.id = c.parent_id
             LEFT JOIN vendors v ON v.id = e.vendor_id LEFT JOIN accounts a ON a.id = e.account_id LEFT JOIN blocks bl ON bl.id = e.block_id";

    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $category = $this->request->int('kategori');
        $vendor = $this->request->int('tedarikci');
        $status = $this->request->str('durum');
        $from = Dates::parse($this->request->str('bas'));
        $to = Dates::parse($this->request->str('bit'));
        $period = preg_match('/^\d{4}-\d{2}$/', $this->request->str('donem')) ? $this->request->str('donem') : '';
        $where = 'e.building_id = ?';
        $params = [$b];
        if ($q !== '') {
            $where .= ' AND (e.description LIKE ? OR e.document_no LIKE ? OR v.name LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($category > 0) {
            $where .= ' AND (e.category_id = ? OR c.parent_id = ?)';
            array_push($params, $category, $category);
        }
        if ($vendor > 0) {
            $where .= ' AND e.vendor_id = ?';
            $params[] = $vendor;
        }
        if ($status !== '') {
            $where .= ' AND e.status = ?';
            $params[] = $status;
        }
        if ($from !== null) {
            $where .= ' AND e.expense_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND e.expense_date <= ?';
            $params[] = $to;
        }
        if ($period !== '') {
            $where .= ' AND e.period = ?';
            $params[] = $period;
        }
        $fromSql = ' FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id LEFT JOIN vendors v ON v.id = e.vendor_id WHERE ' . $where;
        $totals = $this->db->fetch(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(CASE WHEN e.status <> 'iptal' THEN e.amount ELSE 0 END),0) AS amount,
                    COALESCE(SUM(CASE WHEN e.status <> 'iptal' THEN e.vat_amount ELSE 0 END),0) AS vat,
                    COALESCE(SUM(CASE WHEN e.status <> 'iptal' THEN e.paid_amount ELSE 0 END),0) AS paid" . $fromSql,
            $params,
        ) ?? ['n' => 0, 'amount' => 0, 'vat' => 0, 'paid' => 0];
        $totals['open'] = (int) $totals['amount'] - (int) $totals['paid'];
        $order = ' ORDER BY e.expense_date DESC, e.id DESC';
        $format = $this->request->str('format');
        if ($format === 'csv' || $format === 'xlsx') {
            $this->authorize('reports.export');
            $rows = $this->db->fetchAll(self::SELECT . ' WHERE ' . $where . $order . ' LIMIT 5000', $params);
            return $this->export($rows, $format);
        }
        $p = $this->paginator((int) $totals['n'], 30);
        $rows = $this->db->fetchAll(self::SELECT . ' WHERE ' . $where . $order . " LIMIT {$p->perPage} OFFSET {$p->offset}", $params);
        $service = new ExpenseService($this->app);
        $sumFrom = $from ?? ($period !== '' ? $period . '-01' : date('Y-01-01'));
        $sumTo = $to ?? ($period !== '' ? Dates::dayOfPeriod($period, 31) : Dates::today());
        $byParent = [];
        foreach ($service->byCategory($b, $sumFrom, $sumTo) as $row) {
            $byParent[$row['parent_name']] = ($byParent[$row['parent_name']] ?? 0) + (int) $row['total'];
        }
        arsort($byParent);
        return $this->view('manager.expenses.index', [
            'title' => 'Giderler', 'rows' => $rows, 'p' => $p, 'totals' => $totals, 'byParent' => array_slice($byParent, 0, 8, true), 'sumFrom' => $sumFrom, 'sumTo' => $sumTo,
            'categories' => $service->categoryOptions($b),
            'vendors' => $this->db->fetchPairs('SELECT id, name FROM vendors WHERE building_id = ? ORDER BY name', [$b]),
            'filters' => ['q' => $q, 'kategori' => $category, 'tedarikci' => $vendor, 'durum' => $status, 'bas' => $from ?? '', 'bit' => $to ?? '', 'donem' => $period],
            'xlsx' => Exporter::xlsxAvailable(),
        ] + $this->lists('expense_statuses', 'document_kinds'));
    }

    public function create(): Response
    {
        $b = $this->buildingId();
        $defaults = ['expense_date' => Dates::today(), 'period' => Dates::currentPeriod(), 'vendor_id' => $this->request->int('tedarikci') ?: null, 'contract_id' => $this->request->int('sozlesme') ?: null, 'account_id' => (new LedgerService($this->app))->defaultAccountId($b, 'banka') ?? (new LedgerService($this->app))->defaultAccountId($b)];
        return $this->view('manager.expenses.form', ['title' => 'Yeni gider', 'row' => null, 'defaults' => $defaults, 'formAmount' => null] + $this->formData((int) date('Y')));
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES + ['status' => 'required|in:odendi,planlandi'], self::LABELS);
        $b = $this->buildingId();
        $this->assertRefs($d, $b);
        $id = (new ExpenseService($this->app))->create($b, $d);
        $warn = $this->attachDocument($id, $d);
        $this->success('Gider kaydedildi.' . ($warn !== null ? ' Ancak belge yüklenemedi: ' . $warn : ''));
        return $this->redirectRoute('expenses.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $b = $this->buildingId();
        $e = $this->db->fetch(self::SELECT . ' WHERE e.id = ? AND e.building_id = ?', [$id, $b]) ?? throw new HttpException(404, 'Gider bulunamadı.');
        $payments = $this->db->fetchAll('SELECT p.*, a.name AS account_name, a.type AS account_type FROM expense_payments p JOIN accounts a ON a.id = p.account_id WHERE p.expense_id = ? ORDER BY p.paid_date, p.id', [$id]);
        $documents = $this->db->fetchAll("SELECT * FROM documents WHERE building_id = ? AND entity_type = 'expense' AND entity_id = ? ORDER BY id", [$b, $id]);
        $timeline = $this->db->fetchAll("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.building_id = ? AND a.entity_type = 'expense' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 20", [$b, $id]);
        $contract = $e['contract_id'] ? $this->db->fetch('SELECT * FROM contracts WHERE id = ? AND building_id = ?', [(int) $e['contract_id'], $b]) : null;
        $budgetLine = $e['budget_line_id'] ? $this->db->fetch('SELECT l.*, bu.fiscal_year, bu.id AS budget_id FROM budget_lines l JOIN budgets bu ON bu.id = l.budget_id WHERE l.id = ? AND bu.building_id = ?', [(int) $e['budget_line_id'], $b]) : null;
        $recurring = $e['recurring_id'] ? $this->db->fetch('SELECT id, title FROM recurring_expenses WHERE id = ? AND building_id = ?', [(int) $e['recurring_id'], $b]) : null;
        $accounts = (new LedgerService($this->app))->balances($b);
        return $this->view('manager.expenses.show', [
            'title' => 'Gider #' . $e['id'], 'e' => $e, 'payments' => $payments, 'documents' => $documents, 'timeline' => $timeline, 'contract' => $contract, 'budgetLine' => $budgetLine, 'recurring' => $recurring, 'accounts' => $accounts,
            'remaining' => max(0, (int) $e['amount'] - (int) $e['paid_amount']), 'today' => Dates::today(),
        ] + $this->lists('expense_statuses', 'vat_modes', 'document_kinds'));
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('expenses', $id);
        if ($row['status'] === 'iptal') {
            throw new DomainException('İptal edilmiş gider düzenlenemez.');
        }
        $formAmount = $row['vat_mode'] === 'haric' ? (int) $row['amount'] - (int) $row['vat_amount'] : (int) $row['amount'];
        return $this->view('manager.expenses.form', ['title' => 'Gider #' . $id . ' düzenle', 'row' => $row, 'defaults' => [], 'formAmount' => $formAmount] + $this->formData((int) substr((string) $row['expense_date'], 0, 4)));
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('expenses', $id);
        $d = $this->validate(self::RULES, self::LABELS);
        $b = $this->buildingId();
        $this->assertRefs($d, $b);
        (new ExpenseService($this->app))->update($id, $b, $d);
        $links = ['contract_id' => $d['contract_id'] ?: null, 'budget_line_id' => $d['budget_line_id'] ?: null];
        [$o, $n] = \Aidat\Core\Audit::diff($row, $links);
        if ($n !== []) {
            $this->db->update('expenses', $links, 'id = ?', [$id]);
            $this->audit('expense.link', 'expense', $id, $o, $n, 'Gider bağlantıları güncellendi');
        }
        $warn = $this->attachDocument($id, $d);
        $this->success('Gider güncellendi.' . ($warn !== null ? ' Ancak belge yüklenemedi: ' . $warn : ''));
        return $this->redirectRoute('expenses.show', ['id' => $id]);
    }

    public function pay(int $id): Response
    {
        $this->findOwned('expenses', $id);
        $d = $this->validate([
            'account_id' => 'required|integer',
            'amount' => 'required|money_positive',
            'paid_date' => 'required|date',
            'reference_no' => 'nullable|max:60',
        ], ['account_id' => 'kasa/banka', 'amount' => 'ödeme tutarı', 'paid_date' => 'ödeme tarihi', 'reference_no' => 'referans']);
        $this->findOwned('accounts', (int) $d['account_id']);
        (new ExpenseService($this->app))->pay($id, $this->buildingId(), (int) $d['account_id'], (int) $d['amount'], (string) $d['paid_date'], $d['reference_no'] ?: null);
        $this->success('Ödeme kaydedildi: ' . Money::format((int) $d['amount']));
        return $this->redirectRoute('expenses.show', ['id' => $id]);
    }

    public function cancel(int $id): Response
    {
        $this->findOwned('expenses', $id);
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe']);
        (new ExpenseService($this->app))->cancel($id, $this->buildingId(), (string) $d['reason']);
        $this->success('Gider iptal edildi; ödemeler için ters kayıt oluşturuldu.');
        return $this->redirectRoute('expenses.show', ['id' => $id]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function export(array $rows, string $format): Response
    {
        $headers = ['Tarih', 'Vade', 'Dönem', 'Kategori', 'Üst kategori', 'Tedarikçi', 'Belge türü', 'Belge no', 'Açıklama', 'Durum', 'Kasa/Banka', 'Tutar', 'KDV', 'Ödenen', 'Kalan'];
        $csv = $format === 'csv';
        $data = [];
        foreach ($rows as $r) {
            $open = (int) $r['amount'] - (int) $r['paid_amount'];
            $data[] = [
                Dates::tr($r['expense_date']), $r['due_date'] ? Dates::tr($r['due_date']) : '', Dates::period($r['period']), $r['category_name'] ?? '', $r['parent_name'] ?? '', $r['vendor_name'] ?? '',
                list_label('document_kinds', $r['document_kind']), $r['document_no'] ?? '', $r['description'] ?? '', list_label('expense_statuses', $r['status']), $r['account_name'] ?? '',
                $csv ? Money::decimal($r['amount']) : (int) $r['amount'] / 100, $csv ? Money::decimal($r['vat_amount']) : (int) $r['vat_amount'] / 100,
                $csv ? Money::decimal($r['paid_amount']) : (int) $r['paid_amount'] / 100, $csv ? Money::decimal($open) : $open / 100,
            ];
        }
        $name = 'giderler-' . date('Ymd');
        if ($csv || !Exporter::xlsxAvailable()) {
            return Response::download(Exporter::csv($headers, $data), $name . '.csv', 'text/csv; charset=utf-8');
        }
        return Response::download(Exporter::xlsx($headers, $data, 'Giderler'), $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** Dosya varsa giderler/<yapı> altına kaydeder ve documents satırı açar; hata mesajı döner (yoksa null). @param array<string, mixed> $d */
    private function attachDocument(int $expenseId, array $d): ?string
    {
        $file = $this->request->file('file');
        if ($file === null) {
            return null;
        }
        $b = $this->buildingId();
        try {
            $stored = $this->app->uploads()->store($file, 'giderler/' . $b);
        } catch (DomainException $ex) {
            return $ex->getMessage();
        }
        $title = (string) (($d['document_no'] ?? null) ?: ($d['description'] ?? null) ?: ('Gider #' . $expenseId));
        $docId = $this->db->insert('documents', [
            'building_id' => $b, 'category' => 'fatura', 'title' => mb_substr($title, 0, 150), 'file_path' => $stored['path'], 'original_name' => $stored['original_name'],
            'mime' => $stored['mime'], 'size' => $stored['size'], 'visibility' => 'yonetim', 'entity_type' => 'expense', 'entity_id' => $expenseId, 'uploaded_by' => $this->userId(), 'created_at' => Database::now(),
        ]);
        $this->db->update('expenses', ['document_id' => $docId, 'updated_at' => Database::now()], 'id = ? AND document_id IS NULL', [$expenseId]);
        $this->audit('document.upload', 'document', $docId, null, ['entity_type' => 'expense', 'entity_id' => $expenseId, 'name' => $stored['original_name']], 'Gider belgesi yüklendi: ' . $stored['original_name']);
        return null;
    }

    /** Seçilen referansların bu yapıya ait olduğunu doğrular. @param array<string, mixed> $d */
    private function assertRefs(array $d, int $b): void
    {
        $errors = [];
        if (!empty($d['category_id']) && $this->db->fetch('SELECT id FROM expense_categories WHERE id = ? AND (building_id IS NULL OR building_id = ?) AND is_active = 1', [(int) $d['category_id'], $b]) === null) {
            $errors['category_id'] = 'Geçersiz kategori.';
        }
        if (!empty($d['vendor_id']) && $this->db->fetch('SELECT id FROM vendors WHERE id = ? AND building_id = ?', [(int) $d['vendor_id'], $b]) === null) {
            $errors['vendor_id'] = 'Geçersiz tedarikçi.';
        }
        if (!empty($d['contract_id'])) {
            $c = $this->db->fetch('SELECT id, vendor_id FROM contracts WHERE id = ? AND building_id = ?', [(int) $d['contract_id'], $b]);
            if ($c === null || (!empty($d['vendor_id']) && (int) $c['vendor_id'] !== (int) $d['vendor_id'])) {
                $errors['contract_id'] = 'Sözleşme seçilen tedarikçiye ait değil.';
            }
        }
        if (!empty($d['account_id']) && $this->db->fetch('SELECT id FROM accounts WHERE id = ? AND building_id = ? AND is_active = 1', [(int) $d['account_id'], $b]) === null) {
            $errors['account_id'] = 'Geçersiz kasa/banka hesabı.';
        }
        if (($d['scope'] ?? 'tumu') === 'blok') {
            if (empty($d['block_id'])) {
                $errors['block_id'] = 'Blok kapsamı için blok seçin.';
            } elseif ($this->db->fetch('SELECT id FROM blocks WHERE id = ? AND building_id = ?', [(int) $d['block_id'], $b]) === null) {
                $errors['block_id'] = 'Geçersiz blok.';
            }
        }
        if (!empty($d['budget_line_id']) && $this->db->fetch("SELECT l.id FROM budget_lines l JOIN budgets bu ON bu.id = l.budget_id WHERE l.id = ? AND bu.building_id = ? AND l.kind = 'gider'", [(int) $d['budget_line_id'], $b]) === null) {
            $errors['budget_line_id'] = 'Geçersiz bütçe kalemi.';
        }
        if (($d['status'] ?? null) === 'odendi' && empty($d['account_id'])) {
            $errors['account_id'] = 'Ödendi olarak kaydetmek için kasa/banka hesabı seçin.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $this->request->all());
        }
    }

    /** @return array<string, mixed> */
    private function formData(int $year): array
    {
        $b = $this->buildingId();
        $contracts = $this->db->fetchAll("SELECT c.id, c.vendor_id, c.title, c.status FROM contracts c WHERE c.building_id = ? ORDER BY c.status = 'aktif' DESC, c.title", [$b]);
        $budget = $this->db->fetch("SELECT id, fiscal_year, version FROM budgets WHERE building_id = ? AND fiscal_year = ? AND status = 'onayli' ORDER BY version DESC LIMIT 1", [$b, $year]);
        $budgetLines = $budget ? $this->db->fetchPairs("SELECT id, name FROM budget_lines WHERE budget_id = ? AND kind = 'gider' ORDER BY sort_order, id", [(int) $budget['id']]) : [];
        $accounts = [];
        foreach ($this->db->fetchAll('SELECT id, name, type FROM accounts WHERE building_id = ? AND is_active = 1 ORDER BY is_default DESC, type, name', [$b]) as $a) {
            $accounts[(int) $a['id']] = $a['name'] . ' (' . ($a['type'] === 'banka' ? 'banka' : 'kasa') . ')';
        }
        return [
            'categories' => (new ExpenseService($this->app))->categoryOptions($b),
            'vendors' => $this->db->fetchPairs('SELECT id, name FROM vendors WHERE building_id = ? AND is_active = 1 ORDER BY name', [$b]),
            'contracts' => $contracts,
            'accounts' => $accounts,
            'blocks' => $this->db->fetchPairs('SELECT id, name FROM blocks WHERE building_id = ? ORDER BY sort_order, name', [$b]),
            'budgetLines' => $budgetLines,
            'budgetYear' => $budget ? (int) $budget['fiscal_year'] : $year,
        ] + $this->lists('vat_modes', 'document_kinds');
    }
}
