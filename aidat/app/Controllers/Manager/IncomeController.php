<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Exporter;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\IncomeService;
use Aidat\Services\LedgerService;

/** Aidat dışı gelirler (kira, reklam, faiz, bağış). */
final class IncomeController extends Controller
{
    private const STATUSES = ['gecerli' => 'Geçerli', 'iptal' => 'İptal'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $category = $this->request->int('kategori');
        $status = $this->request->str('durum');
        $from = Dates::parse($this->request->str('bas'));
        $to = Dates::parse($this->request->str('bit'));
        $where = 'i.building_id = ?';
        $params = [$b];
        if ($q !== '') {
            $where .= ' AND (i.description LIKE ? OR i.document_no LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }
        if ($category > 0) {
            $where .= ' AND i.category_id = ?';
            $params[] = $category;
        }
        if ($status !== '') {
            $where .= ' AND i.status = ?';
            $params[] = $status;
        }
        if ($from !== null) {
            $where .= ' AND i.income_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND i.income_date <= ?';
            $params[] = $to;
        }
        $totals = $this->db->fetch("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN i.status = 'gecerli' THEN i.amount ELSE 0 END),0) AS amount, COALESCE(SUM(CASE WHEN i.status <> 'gecerli' THEN i.amount ELSE 0 END),0) AS cancelled FROM incomes i WHERE {$where}", $params) ?? ['n' => 0, 'amount' => 0, 'cancelled' => 0];
        $select = "SELECT i.*, c.name AS category_name, a.name AS account_name, a.type AS account_type FROM incomes i LEFT JOIN income_categories c ON c.id = i.category_id LEFT JOIN accounts a ON a.id = i.account_id WHERE {$where} ORDER BY i.income_date DESC, i.id DESC";
        $format = $this->request->str('format');
        if ($format === 'csv' || $format === 'xlsx') {
            $this->authorize('reports.export');
            return $this->export($this->db->fetchAll($select . ' LIMIT 5000', $params), $format);
        }
        $p = $this->paginator((int) $totals['n'], 30);
        $rows = $this->db->fetchAll($select . " LIMIT {$p->perPage} OFFSET {$p->offset}", $params);
        $byCategory = $this->db->fetchAll("SELECT COALESCE(c.name, 'Kategorisiz') AS name, SUM(i.amount) AS total FROM incomes i LEFT JOIN income_categories c ON c.id = i.category_id WHERE i.building_id = ? AND i.status = 'gecerli' AND i.income_date BETWEEN ? AND ? GROUP BY COALESCE(c.name, 'Kategorisiz') ORDER BY total DESC LIMIT 8", [$b, $from ?? date('Y-01-01'), $to ?? Dates::today()]);
        return $this->view('manager.incomes.index', [
            'title' => 'Diğer gelirler', 'rows' => $rows, 'p' => $p, 'totals' => $totals, 'byCategory' => $byCategory, 'sumFrom' => $from ?? date('Y-01-01'), 'sumTo' => $to ?? Dates::today(),
            'categories' => $this->db->fetchPairs('SELECT id, name FROM income_categories WHERE (building_id IS NULL OR building_id = ?) ORDER BY sort_order, name', [$b]),
            'statuses' => self::STATUSES, 'filters' => ['q' => $q, 'kategori' => $category, 'durum' => $status, 'bas' => $from ?? '', 'bit' => $to ?? ''], 'xlsx' => Exporter::xlsxAvailable(),
        ]);
    }

    public function create(): Response
    {
        $b = $this->buildingId();
        $ledger = new LedgerService($this->app);
        return $this->view('manager.incomes.form', [
            'title' => 'Yeni gelir', 'defaults' => ['income_date' => Dates::today(), 'period' => Dates::currentPeriod(), 'account_id' => $ledger->defaultAccountId($b, 'banka') ?? $ledger->defaultAccountId($b)],
            'categories' => $this->db->fetchPairs('SELECT id, name FROM income_categories WHERE (building_id IS NULL OR building_id = ?) AND is_active = 1 ORDER BY sort_order, name', [$b]),
            'accounts' => $this->accountOptions($b),
        ]);
    }

    public function store(): Response
    {
        $d = $this->validate([
            'income_date' => 'required|date',
            'period' => 'nullable|period',
            'category_id' => 'nullable|integer',
            'account_id' => 'required|integer',
            'amount' => 'required|money_positive',
            'description' => 'nullable|max:2000',
            'document_no' => 'nullable|max:60',
        ], ['income_date' => 'gelir tarihi', 'period' => 'dönem', 'category_id' => 'kategori', 'account_id' => 'kasa/banka', 'amount' => 'tutar', 'description' => 'açıklama', 'document_no' => 'belge no']);
        $b = $this->buildingId();
        $errors = [];
        if (!empty($d['category_id']) && $this->db->fetch('SELECT id FROM income_categories WHERE id = ? AND (building_id IS NULL OR building_id = ?)', [(int) $d['category_id'], $b]) === null) {
            $errors['category_id'] = 'Geçersiz kategori.';
        }
        if ($this->db->fetch('SELECT id FROM accounts WHERE id = ? AND building_id = ? AND is_active = 1', [(int) $d['account_id'], $b]) === null) {
            $errors['account_id'] = 'Geçersiz kasa/banka hesabı.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $this->request->all());
        }
        (new IncomeService($this->app))->create($b, $d);
        $this->success('Gelir kaydedildi: ' . Money::format((int) $d['amount']));
        return $this->redirectRoute('incomes.index');
    }

    public function cancel(int $id): Response
    {
        $this->findOwned('incomes', $id);
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe']);
        (new IncomeService($this->app))->cancel($id, $this->buildingId(), (string) $d['reason']);
        $this->success('Gelir iptal edildi; defterde ters kayıt oluşturuldu.');
        return $this->redirectRoute('incomes.index');
    }

    /** @param list<array<string, mixed>> $rows */
    private function export(array $rows, string $format): Response
    {
        $headers = ['Tarih', 'Dönem', 'Kategori', 'Açıklama', 'Belge no', 'Kasa/Banka', 'Durum', 'Tutar'];
        $csv = $format === 'csv';
        $data = [];
        foreach ($rows as $r) {
            $data[] = [Dates::tr($r['income_date']), Dates::period($r['period']), $r['category_name'] ?? '', $r['description'] ?? '', $r['document_no'] ?? '', $r['account_name'] ?? '', self::STATUSES[$r['status']] ?? $r['status'], $csv ? Money::decimal($r['amount']) : (int) $r['amount'] / 100];
        }
        $name = 'gelirler-' . date('Ymd');
        if ($csv || !Exporter::xlsxAvailable()) {
            return Response::download(Exporter::csv($headers, $data), $name . '.csv', 'text/csv; charset=utf-8');
        }
        return Response::download(Exporter::xlsx($headers, $data, 'Gelirler'), $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @return array<int, string> */
    private function accountOptions(int $b): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, name, type FROM accounts WHERE building_id = ? AND is_active = 1 ORDER BY is_default DESC, type, name', [$b]) as $a) {
            $out[(int) $a['id']] = $a['name'] . ' (' . ($a['type'] === 'banka' ? 'banka' : 'kasa') . ')';
        }
        return $out;
    }
}
