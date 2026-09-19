<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;

/** Borç kayıtları: liste/filtre, tekil borçlandırma, detay ve iptal (ters kayıt). */
final class ChargeController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $today = Dates::today();
        $f = [
            'q' => trim($this->request->str('q')),
            'bolum' => $this->request->int('bolum'),
            'tur' => $this->request->str('tur'),
            'durum' => $this->request->str('durum'),
            'donem' => $this->request->str('donem'),
            'vade' => $this->request->bool('vade'),
            'plan' => $this->request->int('plan'),
        ];
        $where = 'c.building_id = ?';
        $params = [$b];
        if ($f['q'] !== '') {
            $where .= ' AND (u.door_no LIKE ? OR c.title LIKE ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        if ($f['bolum'] > 0) {
            $where .= ' AND c.unit_id = ?';
            $params[] = $f['bolum'];
        }
        if ($f['tur'] !== '') {
            $where .= ' AND c.charge_type = ?';
            $params[] = $f['tur'];
        }
        if ($f['durum'] !== '') {
            $where .= ' AND c.status = ?';
            $params[] = $f['durum'];
        }
        if (preg_match('/^\d{4}-\d{2}$/', $f['donem'])) {
            $where .= ' AND c.period = ?';
            $params[] = $f['donem'];
        } else {
            $f['donem'] = '';
        }
        if ($f['vade']) {
            $where .= " AND c.status IN ('odenmedi','kismi') AND c.due_date < ?";
            $params[] = $today;
        }
        if ($f['plan'] > 0) {
            $where .= ' AND c.plan_id = ?';
            $params[] = $f['plan'];
        }
        $from = 'FROM charges c JOIN units u ON u.id = c.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id';
        $total = $this->db->fetchInt("SELECT COUNT(*) {$from} WHERE {$where}", $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll(
            "SELECT c.*, u.door_no, bl.name AS block_name, COALESCE(pe.company_name, TRIM(COALESCE(pe.first_name,'') || ' ' || COALESCE(pe.last_name,''))) AS person_name
             {$from} LEFT JOIN people pe ON pe.id = c.person_id
             WHERE {$where} ORDER BY c.due_date DESC, c.id DESC LIMIT {$p->perPage} OFFSET {$p->offset}",
            $params,
        );
        $totals = $this->db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN c.status <> 'iptal' THEN c.amount ELSE 0 END),0) AS amount,
                    COALESCE(SUM(CASE WHEN c.status <> 'iptal' THEN c.paid_amount ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN c.status IN ('odenmedi','kismi') THEN c.amount - c.paid_amount ELSE 0 END),0) AS open,
                    COALESCE(SUM(CASE WHEN c.status IN ('odenmedi','kismi') AND c.due_date < ? THEN c.amount - c.paid_amount ELSE 0 END),0) AS overdue
             {$from} WHERE {$where}",
            array_merge([$today], $params),
        ) ?? ['amount' => 0, 'paid' => 0, 'open' => 0, 'overdue' => 0];
        $units = $this->unitOptions($b);
        $planName = $f['plan'] > 0 ? $this->db->fetchColumn('SELECT name FROM charge_plans WHERE id = ? AND building_id = ?', [$f['plan'], $b]) : null;
        $periods = array_column($this->db->fetchAll('SELECT DISTINCT period FROM charges WHERE building_id = ? ORDER BY period DESC LIMIT 36', [$b]), 'period');
        return $this->view('manager.charges.index', ['title' => 'Borç kayıtları', 'rows' => $rows, 'p' => $p, 'totals' => $totals, 'filters' => $f, 'units' => $units, 'periods' => $periods, 'planName' => $planName, 'today' => $today] + $this->lists('charge_types', 'charge_statuses'));
    }

    public function create(): Response
    {
        $b = $this->buildingId();
        $dueDay = max(1, min(28, (int) ($this->setting('due_day', '10') ?? 10)));
        $period = Dates::currentPeriod();
        $preselect = $this->request->int('bolum');
        return $this->view('manager.charges.form', [
            'title' => 'Tekil borçlandırma', 'units' => $this->unitOptions($b), 'preselect' => $preselect,
            'defaults' => ['period' => $period, 'due_date' => max(Dates::today(), Dates::dayOfPeriod($period, $dueDay))],
        ] + $this->formLists());
    }

    public function store(): Response
    {
        $d = $this->validate([
            'unit_id' => 'required|integer',
            'charge_type' => 'required|in_keys:lists.charge_types',
            'title' => 'required|max:150',
            'period' => 'required|period',
            'due_date' => 'required|date',
            'amount' => 'required|money_positive',
            'liability' => 'nullable|in:malik,kiraci,paylasimli',
            'description' => 'nullable|max:2000',
            'late_fee_exempt' => 'boolean',
        ], ['unit_id' => 'bağımsız bölüm', 'charge_type' => 'borç türü', 'title' => 'başlık', 'period' => 'dönem', 'due_date' => 'vade', 'amount' => 'tutar', 'liability' => 'borç sorumluluğu', 'description' => 'açıklama']);
        if (in_array($d['charge_type'], ['gecikme', 'devir'], true)) {
            throw new \Aidat\Core\Exceptions\ValidationException(['charge_type' => 'Gecikme tazminatı ve devir bakiyesi elle oluşturulamaz.'], $this->request->all());
        }
        $this->findOwned('units', (int) $d['unit_id']);
        if (empty($d['liability'])) {
            unset($d['liability']);
        }
        $id = (new ChargeService($this->app))->createCharge($this->buildingId(), $d);
        $this->success('Borç kaydı oluşturuldu. Bölümde avans varsa otomatik mahsup edildi.');
        return $this->redirectRoute('charges.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $b = $this->buildingId();
        $c = $this->db->fetch(
            'SELECT c.*, u.door_no, u.type AS unit_type, u.liability_mode, bl.name AS block_name, cp.name AS plan_name, cp.status AS plan_status,
                    pe.id AS pid, pe.first_name, pe.last_name, pe.company_name, pe.phone, pe.email,
                    uc.name AS created_by_name, ux.name AS cancelled_by_name, pc.title AS parent_title
             FROM charges c JOIN units u ON u.id = c.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             LEFT JOIN charge_plans cp ON cp.id = c.plan_id LEFT JOIN people pe ON pe.id = c.person_id
             LEFT JOIN users uc ON uc.id = c.created_by LEFT JOIN users ux ON ux.id = c.cancelled_by
             LEFT JOIN charges pc ON pc.id = c.parent_charge_id
             WHERE c.id = ? AND c.building_id = ?',
            [$id, $b],
        ) ?? throw new HttpException(404, 'Borç kaydı bulunamadı.');
        $allocations = $this->db->fetchAll(
            'SELECT a.*, p.receipt_no, p.payment_date, p.method, p.status AS payment_status, p.amount AS payment_amount
             FROM payment_allocations a JOIN payments p ON p.id = a.payment_id WHERE a.charge_id = ? ORDER BY p.payment_date, a.id',
            [$id],
        );
        $children = $this->db->fetchAll('SELECT * FROM charges WHERE parent_charge_id = ? ORDER BY id', [$id]);
        $audit = $this->db->fetchAll("SELECT a.*, us.name AS user_name FROM audit_logs a LEFT JOIN users us ON us.id = a.user_id WHERE a.entity_type = 'charge' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 20", [$id]);
        $balance = (new ChargeService($this->app))->unitBalance((int) $c['unit_id']);
        $personName = $c['pid'] ? ($c['company_name'] ?: trim($c['first_name'] . ' ' . $c['last_name'])) : null;
        return $this->view('manager.charges.show', [
            'title' => $c['title'], 'c' => $c, 'allocations' => $allocations, 'children' => $children, 'audit' => $audit, 'balance' => $balance,
            'personName' => $personName, 'today' => Dates::today(),
        ] + $this->lists('charge_types', 'charge_statuses', 'liability_modes', 'payment_methods', 'unit_types'));
    }

    public function cancel(int $id): Response
    {
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe'], $this->route('charges.show', ['id' => $id]));
        (new ChargeService($this->app))->cancelCharge($id, $this->buildingId(), (string) $d['reason']);
        $this->success('Borç iptal edildi (ters kayıt). Bağlı gecikme tazminatları da iptal edildi.');
        return $this->redirectRoute('charges.show', ['id' => $id]);
    }

    /** Bloklara göre gruplu bölüm seçenekleri (optgroup). @return array<string, array<int, string>> */
    private function unitOptions(int $b): array
    {
        $rows = $this->db->fetchAll("SELECT u.id, u.door_no, u.status, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? AND u.status <> 'pasif' ORDER BY bl.sort_order, bl.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no", [$b]);
        $out = [];
        foreach ($rows as $r) {
            $g = $r['block_name'] ?: 'Blok yok';
            $out[$g][(int) $r['id']] = 'No ' . $r['door_no'];
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private function formLists(): array
    {
        $types = array_filter($this->app->config()->get('lists.charge_types', []), static fn ($v, $k) => !in_array($k, ['gecikme', 'devir'], true), ARRAY_FILTER_USE_BOTH);
        return ['charge_types' => $types] + $this->lists('liability_modes');
    }
}
