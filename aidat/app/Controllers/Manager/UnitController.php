<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;
use Aidat\Services\PaymentService;

final class UnitController extends Controller
{
    private const RULES = [
        'block_id' => 'nullable|integer',
        'fee_group_id' => 'nullable|integer',
        'door_no' => 'required|max:20',
        'floor' => 'nullable|max:10',
        'type' => 'required|in_keys:lists.unit_types',
        'gross_m2' => 'nullable|numeric|min:0',
        'net_m2' => 'nullable|numeric|min:0',
        'land_share' => 'nullable|numeric|min:0',
        'status' => 'required|in_keys:lists.unit_statuses',
        'liability_mode' => 'required|in_keys:lists.liability_modes',
        'late_fee_exempt' => 'boolean',
        'notes' => 'nullable|max:2000',
    ];
    private const LABELS = ['block_id' => 'blok', 'fee_group_id' => 'aidat grubu', 'door_no' => 'kapı no', 'floor' => 'kat', 'type' => 'tür', 'gross_m2' => 'brüt m²', 'net_m2' => 'net m²', 'land_share' => 'arsa payı', 'status' => 'durum', 'liability_mode' => 'borç sorumluluğu'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $block = $this->request->int('blok');
        $status = $this->request->str('durum');
        $debtOnly = $this->request->bool('borclu');
        $today = Dates::today();
        $where = 'u.building_id = ?';
        $params = [$b];
        if ($q !== '') {
            $where .= " AND (u.door_no LIKE ? OR EXISTS (SELECT 1 FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = u.id AND (o.end_date IS NULL OR o.end_date >= ?) AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.company_name LIKE ?)))";
            array_push($params, '%' . $q . '%', $today, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($block > 0) {
            $where .= ' AND u.block_id = ?';
            $params[] = $block;
        }
        if ($status !== '') {
            $where .= ' AND u.status = ?';
            $params[] = $status;
        }
        $having = $debtOnly ? ' HAVING open_debt > 0' : '';
        $countSql = "SELECT COUNT(*) FROM (SELECT u.id, COALESCE((SELECT SUM(c.amount - c.paid_amount) FROM charges c WHERE c.unit_id = u.id AND c.status IN ('odenmedi','kismi')),0) AS open_debt FROM units u WHERE {$where} {$having}) x";
        $total = $this->db->fetchInt($countSql, $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll(
            "SELECT u.*, bl.name AS block_name, g.name AS fee_group_name,
                    COALESCE((SELECT SUM(c.amount - c.paid_amount) FROM charges c WHERE c.unit_id = u.id AND c.status IN ('odenmedi','kismi')),0) AS open_debt,
                    COALESCE((SELECT SUM(c.amount - c.paid_amount) FROM charges c WHERE c.unit_id = u.id AND c.status IN ('odenmedi','kismi') AND c.due_date < ?),0) AS overdue,
                    (SELECT COALESCE(p.company_name, p.first_name || ' ' || COALESCE(p.last_name,'')) FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = u.id AND o.role = 'malik' AND (o.end_date IS NULL OR o.end_date >= ?) ORDER BY o.id DESC LIMIT 1) AS owner_name,
                    (SELECT COALESCE(p.company_name, p.first_name || ' ' || COALESCE(p.last_name,'')) FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = u.id AND o.role = 'kiraci' AND (o.end_date IS NULL OR o.end_date >= ?) ORDER BY o.id DESC LIMIT 1) AS tenant_name
             FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN fee_groups g ON g.id = u.fee_group_id
             WHERE {$where} {$having}
             ORDER BY bl.sort_order, bl.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no LIMIT {$p->perPage} OFFSET {$p->offset}",
            array_merge([$today, $today, $today], $params),
        );
        $blocks = $this->db->fetchPairs('SELECT id, name FROM blocks WHERE building_id = ? ORDER BY sort_order, name', [$b]);
        $summary = [
            'total' => $this->db->fetchInt('SELECT COUNT(*) FROM units WHERE building_id = ?', [$b]),
            'occupied' => $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status = 'dolu'", [$b]),
            'empty' => $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status = 'bos'", [$b]),
        ];
        return $this->view('manager.units.index', ['title' => 'Bağımsız bölümler', 'rows' => $rows, 'p' => $p, 'blocks' => $blocks, 'summary' => $summary, 'filters' => ['q' => $q, 'blok' => $block, 'durum' => $status, 'borclu' => $debtOnly]] + $this->lists('unit_statuses', 'unit_types'));
    }

    public function create(): Response
    {
        return $this->view('manager.units.form', ['title' => 'Yeni bağımsız bölüm', 'row' => null] + $this->formData());
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $this->assertUniqueDoor($d, null);
        $id = $this->db->insert('units', $d + ['building_id' => $this->buildingId(), 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('unit.create', 'unit', $id, null, $d, 'Bağımsız bölüm eklendi: No ' . $d['door_no']);
        $this->success('Bağımsız bölüm eklendi. Şimdi malik/kiracı kaydı ekleyebilirsiniz.');
        return $this->redirectRoute('units.show', ['id' => $id]);
    }

    public function bulk(): Response
    {
        return $this->view('manager.units.bulk', ['title' => 'Toplu bölüm oluştur'] + $this->formData());
    }

    public function bulkStore(): Response
    {
        $d = $this->validate([
            'block_id' => 'nullable|integer',
            'start_no' => 'required|integer|min:1|max:9999',
            'end_no' => 'required|integer|min:1|max:9999|gte:start_no',
            'per_floor' => 'nullable|integer|min:1|max:50',
            'first_floor' => 'nullable|integer|min:-5|max:200',
            'type' => 'required|in_keys:lists.unit_types',
            'gross_m2' => 'nullable|numeric|min:0',
            'land_share' => 'nullable|numeric|min:0',
            'prefix' => 'nullable|max:5',
        ], ['start_no' => 'başlangıç no', 'end_no' => 'bitiş no', 'per_floor' => 'kat başına bölüm', 'first_floor' => 'ilk kat', 'type' => 'tür', 'prefix' => 'ön ek']);
        $b = $this->buildingId();
        if ($d['end_no'] - $d['start_no'] > 500) {
            throw new DomainException('Tek seferde en fazla 500 bölüm oluşturulabilir.');
        }
        $created = 0;
        $skipped = 0;
        $this->db->transaction(function () use ($d, $b, &$created, &$skipped): void {
            $perFloor = (int) ($d['per_floor'] ?? 0);
            $firstFloor = (int) ($d['first_floor'] ?? 1);
            $i = 0;
            for ($no = (int) $d['start_no']; $no <= (int) $d['end_no']; $no++, $i++) {
                $door = ($d['prefix'] ?? '') . $no;
                $exists = $this->db->fetch('SELECT id FROM units WHERE building_id = ? AND door_no = ? AND ' . ($d['block_id'] ? 'block_id = ?' : 'block_id IS NULL'), array_filter([$b, $door, $d['block_id']], static fn ($v) => $v !== null));
                if ($exists !== null) {
                    $skipped++;
                    continue;
                }
                $this->db->insert('units', [
                    'building_id' => $b, 'block_id' => $d['block_id'] ?: null, 'door_no' => $door,
                    'floor' => $perFloor > 0 ? (string) ($firstFloor + intdiv($i, $perFloor)) : null,
                    'type' => $d['type'], 'gross_m2' => $d['gross_m2'], 'land_share' => $d['land_share'], 'status' => 'dolu', 'liability_mode' => 'malik',
                    'sort_order' => $no, 'created_at' => Database::now(), 'updated_at' => Database::now(),
                ]);
                $created++;
            }
        });
        $this->audit('unit.bulk_create', 'unit', null, null, ['created' => $created, 'skipped' => $skipped, 'range' => $d['start_no'] . '-' . $d['end_no']], 'Toplu bölüm oluşturuldu: ' . $created);
        $this->success("{$created} bağımsız bölüm oluşturuldu" . ($skipped ? ", {$skipped} tanesi zaten vardı (atlandı)." : '.'));
        return $this->redirectRoute('units.index');
    }

    public function show(int $id): Response
    {
        $unit = $this->db->fetch('SELECT u.*, bl.name AS block_name, g.name AS fee_group_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN fee_groups g ON g.id = u.fee_group_id WHERE u.id = ? AND u.building_id = ?', [$id, $this->buildingId()]) ?? throw new \Aidat\Core\Exceptions\HttpException(404, 'Bölüm bulunamadı.');
        $charges = new ChargeService($this->app);
        $today = Dates::today();
        $occupancies = $this->db->fetchAll('SELECT o.*, p.first_name, p.last_name, p.company_name, p.phone, p.email, p.user_id FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = ? ORDER BY (o.end_date IS NULL OR o.end_date >= ?) DESC, o.start_date DESC', [$id, $today]);
        $balance = $charges->unitBalance($id);
        $openCharges = $charges->openCharges($id);
        $recentCharges = $this->db->fetchAll('SELECT * FROM charges WHERE unit_id = ? ORDER BY due_date DESC, id DESC LIMIT 12', [$id]);
        $payments = $this->db->fetchAll('SELECT * FROM payments WHERE unit_id = ? ORDER BY payment_date DESC, id DESC LIMIT 10', [$id]);
        $vehicles = $this->db->fetchAll('SELECT * FROM unit_vehicles WHERE unit_id = ? ORDER BY id', [$id]);
        $meters = $this->db->fetchAll('SELECT m.*, (SELECT value FROM meter_readings r WHERE r.meter_id = m.id ORDER BY period DESC LIMIT 1) AS last_value, (SELECT period FROM meter_readings r WHERE r.meter_id = m.id ORDER BY period DESC LIMIT 1) AS last_period FROM meters m WHERE m.unit_id = ? AND m.is_active = 1', [$id]);
        $requests = $this->db->fetchAll('SELECT * FROM requests WHERE unit_id = ? ORDER BY id DESC LIMIT 5', [$id]);
        $handovers = $this->db->fetchAll('SELECT h.*, pf.first_name AS from_first, pf.last_name AS from_last, pt.first_name AS to_first, pt.last_name AS to_last FROM handovers h LEFT JOIN people pf ON pf.id = h.from_person_id LEFT JOIN people pt ON pt.id = h.to_person_id WHERE h.unit_id = ? ORDER BY h.handover_date DESC', [$id]);
        $people = $this->db->fetchAll('SELECT id, first_name, last_name, company_name FROM people WHERE building_id = ? ORDER BY first_name, last_name', [$this->buildingId()]);
        $timeline = $this->db->fetchAll("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.building_id = ? AND ((a.entity_type = 'unit' AND a.entity_id = ?) OR (a.entity_type = 'charge' AND a.entity_id IN (SELECT id FROM charges WHERE unit_id = ?)) OR (a.entity_type = 'payment' AND a.entity_id IN (SELECT id FROM payments WHERE unit_id = ?))) ORDER BY a.id DESC LIMIT 15", [$this->buildingId(), $id, $id, $id]);
        return $this->view('manager.units.show', [
            'title' => ($unit['block_name'] ? $unit['block_name'] . ' · ' : '') . 'No ' . $unit['door_no'],
            'unit' => $unit, 'occupancies' => $occupancies, 'balance' => $balance, 'openCharges' => $openCharges, 'recentCharges' => $recentCharges,
            'payments' => $payments, 'vehicles' => $vehicles, 'meters' => $meters, 'requests' => $requests, 'handovers' => $handovers, 'people' => $people, 'timeline' => $timeline,
        ] + $this->lists('occupancy_roles', 'liability_modes'));
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('units', $id);
        return $this->view('manager.units.form', ['title' => 'No ' . $row['door_no'] . ' düzenle', 'row' => $row] + $this->formData());
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('units', $id);
        $d = $this->validate(self::RULES, self::LABELS);
        $this->assertUniqueDoor($d, $id);
        $this->db->update('units', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $d);
        $this->audit('unit.update', 'unit', $id, $o, $n, 'Bölüm güncellendi: No ' . $d['door_no']);
        $this->success('Bağımsız bölüm güncellendi.');
        return $this->redirectRoute('units.show', ['id' => $id]);
    }

    public function statement(int $id): Response
    {
        $unit = $this->findOwned('units', $id);
        $from = Dates::parse($this->request->str('bas')) ?? date('Y-01-01');
        $to = Dates::parse($this->request->str('bit')) ?? Dates::today();
        $st = (new PaymentService($this->app))->statement($id, $from, $to);
        $owner = (new ChargeService($this->app))->responsibleName($id);
        $data = ['title' => 'Hesap ekstresi · No ' . $unit['door_no'], 'unit' => $unit, 'st' => $st, 'from' => $from, 'to' => $to, 'owner' => $owner, 'building' => $this->building()];
        if ($this->request->has('yazdir')) {
            return Response::html($this->app->view()->render('manager.units.statement-print', $data + ['backUrl' => $this->route('units.statement', ['id' => $id, 'bas' => $from, 'bit' => $to])], 'layouts.print'));
        }
        return $this->view('manager.units.statement', $data);
    }

    public function addVehicle(int $id): Response
    {
        $this->findOwned('units', $id);
        $d = $this->validate(['plate' => 'required|max:15', 'description' => 'nullable|max:80'], ['plate' => 'plaka']);
        $this->db->insert('unit_vehicles', ['unit_id' => $id, 'plate' => mb_strtoupper(str_replace(' ', '', (string) $d['plate'])), 'description' => $d['description'], 'created_at' => Database::now()]);
        $this->success('Araç eklendi.');
        return $this->redirectRoute('units.show', ['id' => $id]);
    }

    public function removeVehicle(int $id, int $vid): Response
    {
        $this->findOwned('units', $id);
        $this->db->delete('unit_vehicles', 'id = ? AND unit_id = ?', [$vid, $id]);
        $this->success('Araç kaydı silindi.');
        return $this->redirectRoute('units.show', ['id' => $id]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $b = $this->buildingId();
        return [
            'blocks' => $this->db->fetchPairs('SELECT id, name FROM blocks WHERE building_id = ? ORDER BY sort_order, name', [$b]),
            'groups' => $this->db->fetchPairs('SELECT id, name FROM fee_groups WHERE building_id = ? ORDER BY name', [$b]),
        ] + $this->lists('unit_types', 'unit_statuses', 'liability_modes');
    }

    /** @param array<string, mixed> $d */
    private function assertUniqueDoor(array $d, ?int $ignoreId): void
    {
        $sql = 'SELECT id FROM units WHERE building_id = ? AND door_no = ? AND ' . ($d['block_id'] ? 'block_id = ?' : 'block_id IS NULL');
        $params = [$this->buildingId(), $d['door_no']];
        if ($d['block_id']) {
            $params[] = (int) $d['block_id'];
        }
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        if ($this->db->fetch($sql, $params) !== null) {
            throw new \Aidat\Core\Exceptions\ValidationException(['door_no' => 'Bu blokta aynı kapı numarası zaten var.'], $this->request->all());
        }
    }
}
