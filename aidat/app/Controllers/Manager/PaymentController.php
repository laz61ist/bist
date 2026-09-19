<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Exporter;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;
use Aidat\Services\LedgerService;
use Aidat\Services\PaymentService;

/** Tahsilat: liste, giriş (borç dağıtım önizlemesi), detay, iptal/iade, makbuz. */
final class PaymentController extends Controller
{
    private const RULES = [
        'unit_id' => 'nullable|integer',
        'account_id' => 'required|integer',
        'payment_date' => 'required|date',
        'payment_time' => 'nullable|regex:/^\d{2}:\d{2}$/',
        'method' => 'required|in_keys:lists.payment_methods',
        'amount' => 'required|money_positive',
        'reference_no' => 'nullable|max:60',
        'description' => 'nullable|max:500',
        'allocation_mode' => 'required|in_keys:lists.allocation_modes',
    ];
    private const LABELS = ['unit_id' => 'bağımsız bölüm', 'account_id' => 'kasa / banka hesabı', 'payment_date' => 'tahsilat tarihi', 'payment_time' => 'saat', 'method' => 'ödeme yöntemi', 'amount' => 'tutar', 'reference_no' => 'referans / dekont no', 'description' => 'açıklama', 'allocation_mode' => 'dağıtım şekli'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $f = [
            'q' => trim($this->request->str('q')),
            'bolum' => $this->request->int('bolum'),
            'hesap' => $this->request->int('hesap'),
            'yontem' => $this->request->str('yontem'),
            'durum' => $this->request->str('durum'),
            'bas' => Dates::parse($this->request->str('bas')) ?? '',
            'bit' => Dates::parse($this->request->str('bit')) ?? '',
        ];
        $where = 'p.building_id = ?';
        $params = [$b];
        if ($f['q'] !== '') {
            $where .= ' AND (p.receipt_no LIKE ? OR p.reference_no LIKE ? OR u.door_no = ? OR p.description LIKE ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%', $f['q'], '%' . $f['q'] . '%');
        }
        if ($f['bolum'] > 0) {
            $where .= ' AND p.unit_id = ?';
            $params[] = $f['bolum'];
        }
        if ($f['hesap'] > 0) {
            $where .= ' AND p.account_id = ?';
            $params[] = $f['hesap'];
        }
        if ($f['yontem'] !== '') {
            $where .= ' AND p.method = ?';
            $params[] = $f['yontem'];
        }
        if ($f['durum'] !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $f['durum'];
        }
        if ($f['bas'] !== '') {
            $where .= ' AND p.payment_date >= ?';
            $params[] = $f['bas'];
        }
        if ($f['bit'] !== '') {
            $where .= ' AND p.payment_date <= ?';
            $params[] = $f['bit'];
        }
        $from = 'FROM payments p LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN accounts a ON a.id = p.account_id LEFT JOIN people per ON per.id = p.person_id';
        $select = "SELECT p.*, u.door_no, bl.name AS block_name, a.name AS account_name, a.type AS account_type, COALESCE(per.company_name, TRIM(COALESCE(per.first_name, '') || ' ' || COALESCE(per.last_name, ''))) AS payer_name";
        $order = 'ORDER BY p.payment_date DESC, p.id DESC';

        $format = $this->request->str('format');
        if ($format === 'csv' || $format === 'xlsx') {
            $rows = $this->db->fetchAll("{$select} {$from} WHERE {$where} {$order}", $params);
            return $this->export($rows, $format);
        }

        $total = $this->db->fetchInt("SELECT COUNT(*) {$from} WHERE {$where}", $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll("{$select} {$from} WHERE {$where} {$order} LIMIT {$p->perPage} OFFSET {$p->offset}", $params);
        $sum = $this->db->fetch("SELECT COALESCE(SUM(CASE WHEN p.status = 'gecerli' THEN p.amount ELSE 0 END), 0) AS valid_total, COALESCE(SUM(CASE WHEN p.status = 'gecerli' THEN p.unallocated_amount ELSE 0 END), 0) AS advance_total, SUM(CASE WHEN p.status = 'gecerli' THEN 1 ELSE 0 END) AS valid_count, SUM(CASE WHEN p.status <> 'gecerli' THEN 1 ELSE 0 END) AS cancelled_count {$from} WHERE {$where}", $params) ?? [];
        $pageValid = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'gecerli') {
                $pageValid += (int) $r['amount'];
            }
        }
        return $this->view('manager.payments.index', [
            'title' => 'Tahsilatlar',
            'rows' => $rows,
            'p' => $p,
            'filters' => $f,
            'summary' => ['valid_total' => (int) ($sum['valid_total'] ?? 0), 'advance_total' => (int) ($sum['advance_total'] ?? 0), 'valid_count' => (int) ($sum['valid_count'] ?? 0), 'cancelled_count' => (int) ($sum['cancelled_count'] ?? 0), 'page_valid' => $pageValid],
            'units' => $this->unitOptions(),
            'accounts' => $this->db->fetchPairs('SELECT id, name FROM accounts WHERE building_id = ? ORDER BY is_default DESC, type, name', [$b]),
            'xlsx' => Exporter::xlsxAvailable(),
        ] + $this->lists('payment_methods', 'payment_statuses'));
    }

    public function create(): Response
    {
        $b = $this->buildingId();
        $preUnit = $this->request->int('bolum');
        if ($preUnit > 0 && $this->db->fetch('SELECT id FROM units WHERE id = ? AND building_id = ?', [$preUnit, $b]) === null) {
            $preUnit = 0;
        }
        $ledger = new LedgerService($this->app);
        $accounts = [];
        foreach ($ledger->balances($b) as $a) {
            $accounts[(string) $a['id']] = $a['name'] . ' · ' . list_label('account_types', $a['type']) . ' · ' . Money::format((int) $a['balance']);
        }
        return $this->view('manager.payments.form', [
            'title' => 'Tahsilat girişi',
            'units' => $this->unitOptions(),
            'preUnit' => (int) old('unit_id', $preUnit),
            'accounts' => $accounts,
            'defaultAccount' => $ledger->defaultAccountId($b),
            'today' => Dates::today(),
            'now' => date('H:i'),
        ] + $this->lists('payment_methods', 'allocation_modes'));
    }

    /** Bölümün açık borçları ve bakiyesi (tahsilat formu için JSON). */
    public function unitCharges(int $id): Response
    {
        $unit = $this->findOwned('units', $id);
        $svc = new ChargeService($this->app);
        $today = Dates::today();
        $charges = [];
        foreach ($svc->openCharges($id) as $c) {
            $charges[] = [
                'id' => (int) $c['id'],
                'title' => (string) $c['title'],
                'type' => list_label('charge_types', $c['charge_type']),
                'period' => (string) $c['period'],
                'period_label' => tr_period($c['period']),
                'due_date' => (string) $c['due_date'],
                'due_label' => tr_date($c['due_date']),
                'overdue' => $c['due_date'] < $today,
                'amount' => (int) $c['amount'],
                'paid' => (int) $c['paid_amount'],
                'open' => (int) $c['amount'] - (int) $c['paid_amount'],
            ];
        }
        $bal = $svc->unitBalance($id);
        return $this->json([
            'unit' => ['id' => (int) $unit['id'], 'door_no' => $unit['door_no'], 'responsible' => $svc->responsibleName($id)],
            'charges' => $charges,
            'balance' => ['debt' => $bal['debt'], 'advance' => $bal['advance'], 'overdue' => $bal['overdue']],
        ]);
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $b = $this->buildingId();
        if (!empty($d['payment_time']) && ((int) substr((string) $d['payment_time'], 0, 2) > 23 || (int) substr((string) $d['payment_time'], 3, 2) > 59)) {
            throw new ValidationException(['payment_time' => 'Saat SS:DD biçiminde olmalıdır.'], $this->request->all());
        }
        $this->findOwned('accounts', (int) $d['account_id']);
        $unitId = $d['unit_id'] ? (int) $d['unit_id'] : null;
        if ($unitId !== null) {
            $this->findOwned('units', $unitId);
        }
        $manual = [];
        if ($d['allocation_mode'] === 'manuel') {
            if ($unitId === null) {
                throw new ValidationException(['unit_id' => 'Manuel dağıtım için bağımsız bölüm seçin.'], $this->request->all());
            }
            $raw = $this->request->input('manual');
            $sum = 0;
            foreach (is_array($raw) ? $raw : [] as $chargeId => $val) {
                if (!is_scalar($val) || trim((string) $val) === '') {
                    continue;
                }
                try {
                    $k = Money::parse($val);
                } catch (\InvalidArgumentException) {
                    throw new ValidationException(['amount' => 'Manuel dağıtım tutarlarından biri geçersiz (örn. 1.250,50).'], $this->request->all());
                }
                if ($k > 0) {
                    $manual[(int) $chargeId] = $k;
                    $sum += $k;
                }
            }
            if ($manual === []) {
                throw new ValidationException(['allocation_mode' => 'Manuel dağıtımda en az bir borca tutar yazın.'], $this->request->all());
            }
            if ($sum > (int) $d['amount']) {
                throw new ValidationException(['amount' => 'Dağıtım toplamı (' . Money::format($sum) . ') tahsilat tutarını aşamaz.'], $this->request->all());
            }
            // Yalnızca bu bölümün açık borçları kabul edilir
            $openIds = array_map(static fn ($c) => (int) $c['id'], (new ChargeService($this->app))->openCharges($unitId));
            foreach (array_keys($manual) as $cid) {
                if (!in_array($cid, $openIds, true)) {
                    throw new ValidationException(['allocation_mode' => 'Seçilen borçlardan biri bu bölüme ait değil veya artık açık değil.'], $this->request->all());
                }
            }
        }
        $id = (new PaymentService($this->app))->create($b, [
            'unit_id' => $unitId,
            'account_id' => (int) $d['account_id'],
            'payment_date' => $d['payment_date'],
            'payment_time' => $d['payment_time'] ?: date('H:i'),
            'amount' => (int) $d['amount'],
            'method' => $d['method'],
            'reference_no' => $d['reference_no'] ?: null,
            'description' => $d['description'] ?: null,
            'allocation_mode' => $d['allocation_mode'],
            'manual' => $manual,
        ]);
        $receiptNo = (string) ($this->db->fetchColumn('SELECT receipt_no FROM payments WHERE id = ?', [$id]) ?? '');
        $this->success('Tahsilat kaydedildi: <strong>' . e($receiptNo) . '</strong>. <a href="' . e($this->route('payments.receipt', ['id' => $id, 'otomatik' => 1])) . '" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Makbuzu yazdır</a>');
        return $this->redirectRoute('payments.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $b = $this->buildingId();
        $payment = $this->db->fetch(
            "SELECT p.*, u.door_no, u.type AS unit_type, bl.name AS block_name, a.name AS account_name, a.type AS account_type,
                    per.first_name, per.last_name, per.company_name,
                    col.name AS collector_name, cu.name AS canceller_name, r.import_id
             FROM payments p
             LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             LEFT JOIN accounts a ON a.id = p.account_id
             LEFT JOIN people per ON per.id = p.person_id
             LEFT JOIN users col ON col.id = p.collected_by
             LEFT JOIN users cu ON cu.id = p.cancelled_by
             LEFT JOIN bank_import_rows r ON r.id = p.import_row_id
             WHERE p.id = ? AND p.building_id = ?",
            [$id, $b],
        ) ?? throw new HttpException(404, 'Tahsilat bulunamadı.');
        $svc = new PaymentService($this->app);
        $allocations = $svc->allocationsOf($id);
        $balance = $payment['unit_id'] ? (new ChargeService($this->app))->unitBalance((int) $payment['unit_id']) : null;
        $ledger = $this->db->fetchAll('SELECT l.*, a.name AS account_name FROM ledger_entries l JOIN accounts a ON a.id = l.account_id WHERE l.ref_id = ? AND l.ref_type IN (?, ?) ORDER BY l.id', [$id, 'payment', 'payment_reversal']);
        $timeline = $this->db->fetchAll("SELECT a.*, usr.name AS user_name FROM audit_logs a LEFT JOIN users usr ON usr.id = a.user_id WHERE a.entity_type = 'payment' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 20", [$id]);
        $payment['payer_name'] = $payment['company_name'] ?: trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''));
        return $this->view('manager.payments.show', [
            'title' => 'Makbuz ' . $payment['receipt_no'],
            'payment' => $payment,
            'allocations' => $allocations,
            'balance' => $balance,
            'ledger' => $ledger,
            'timeline' => $timeline,
        ] + $this->lists('payment_methods', 'payment_statuses', 'allocation_modes'));
    }

    public function cancel(int $id): Response
    {
        $this->findOwned('payments', $id);
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe']);
        (new PaymentService($this->app))->cancel($id, $this->buildingId(), (string) $d['reason']);
        $this->success('Tahsilat iptal edildi; ters kayıt oluşturuldu. Makbuz numarası korunur.');
        return $this->redirectRoute('payments.show', ['id' => $id]);
    }

    public function refund(int $id): Response
    {
        $this->findOwned('payments', $id);
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe']);
        (new PaymentService($this->app))->cancel($id, $this->buildingId(), (string) $d['reason'], true);
        $this->success('Tahsilat iade edildi; kasadan/bankadan çıkış kaydı oluşturuldu.');
        return $this->redirectRoute('payments.show', ['id' => $id]);
    }

    public function receipt(int $id): Response
    {
        $b = $this->buildingId();
        $data = (new PaymentService($this->app))->receiptData($id, $b) ?? throw new HttpException(404, 'Makbuz bulunamadı.');
        $verifyPath = $this->route('receipt.verify', ['code' => (string) $data['verify_code']]);
        $base = rtrim((string) config('app.url', ''), '/');
        $copies = max(1, min(4, (int) ($this->setting('receipt_copies') ?? '2')));
        $labels = ['Yönetim nüshası', 'Sakin nüshası', 'Ek nüsha', 'Ek nüsha'];
        $html = $this->app->view()->render('manager.payments.receipt', [
            'title' => 'Makbuz ' . $data['receipt_no'],
            'r' => $data,
            'verifyUrl' => $base !== '' ? $base . $verifyPath : $verifyPath,
            'copies' => $copies,
            'copyLabels' => $labels,
            'signerTitle' => (string) ($this->setting('receipt_signer_title') ?? 'Yönetici'),
            'footer' => (string) ($this->setting('receipt_footer') ?? ''),
            'backUrl' => $this->route('payments.show', ['id' => $id]),
            'autoPrint' => $this->request->bool('otomatik'),
        ], 'layouts.print');
        return Response::html($html);
    }

    /** @return array<string, array<string, string>> blok → [unit_id => etiket] */
    private function unitOptions(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.door_no, u.type, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? AND u.status <> 'pasif' ORDER BY bl.sort_order, bl.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no",
            [$this->buildingId()],
        );
        $out = [];
        foreach ($rows as $r) {
            $group = $r['block_name'] ?: 'Bölümler';
            $out[$group][(string) $r['id']] = 'No ' . $r['door_no'] . ' · ' . list_label('unit_types', $r['type']);
        }
        return $out;
    }

    /** @param list<array<string, mixed>> $rows */
    private function export(array $rows, string $format): Response
    {
        $headers = ['Makbuz No', 'Tarih', 'Saat', 'Blok', 'Kapı No', 'Ödeyen', 'Yöntem', 'Hesap', 'Referans', 'Tutar', 'Avans', 'Durum', 'Açıklama'];
        $isXlsx = $format === 'xlsx' && Exporter::xlsxAvailable();
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['receipt_no'], tr_date($r['payment_date']), $r['payment_time'], $r['block_name'], $r['door_no'], trim((string) $r['payer_name']),
                list_label('payment_methods', $r['method']), $r['account_name'], $r['reference_no'],
                $isXlsx ? (int) $r['amount'] / 100 : Money::decimal($r['amount']),
                $isXlsx ? (int) $r['unallocated_amount'] / 100 : Money::decimal($r['unallocated_amount']),
                list_label('payment_statuses', $r['status']), $r['description'],
            ];
        }
        $name = 'tahsilatlar-' . date('Ymd-Hi');
        if ($isXlsx) {
            return Response::download(Exporter::xlsx($headers, $data, 'Tahsilatlar'), $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        return Response::download(Exporter::csv($headers, $data), $name . '.csv', 'text/csv; charset=utf-8');
    }
}
