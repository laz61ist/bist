<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;

/** Oturum kayıtları (malik/kiracı/oturan) ve devir işlemleri. */
final class OccupancyController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $only = $this->request->str('durum', 'aktif');
        $today = Dates::today();
        $where = 'o.building_id = ?';
        $params = [$b];
        if ($only === 'aktif') {
            $where .= ' AND (o.end_date IS NULL OR o.end_date >= ?)';
            $params[] = $today;
        } elseif ($only === 'gecmis') {
            $where .= ' AND o.end_date IS NOT NULL AND o.end_date < ?';
            $params[] = $today;
        }
        if ($q !== '') {
            $where .= ' AND (u.door_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.company_name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $total = $this->db->fetchInt("SELECT COUNT(*) FROM occupancies o JOIN units u ON u.id = o.unit_id JOIN people p ON p.id = o.person_id WHERE {$where}", $params);
        $p = $this->paginator($total, 40);
        $rows = $this->db->fetchAll("SELECT o.*, u.door_no, bl.name AS block_name, p.first_name, p.last_name, p.company_name, p.phone FROM occupancies o JOIN units u ON u.id = o.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id JOIN people p ON p.id = o.person_id WHERE {$where} ORDER BY o.start_date DESC, o.id DESC LIMIT {$p->perPage} OFFSET {$p->offset}", $params);
        $handovers = $this->db->fetchAll('SELECT h.*, u.door_no, pf.first_name AS from_first, pf.last_name AS from_last, pf.company_name AS from_company, pt.first_name AS to_first, pt.last_name AS to_last, pt.company_name AS to_company FROM handovers h JOIN units u ON u.id = h.unit_id LEFT JOIN people pf ON pf.id = h.from_person_id LEFT JOIN people pt ON pt.id = h.to_person_id WHERE h.building_id = ? ORDER BY h.handover_date DESC, h.id DESC LIMIT 20', [$b]);
        return $this->view('manager.occupancies.index', ['title' => 'Devir ve oturum geçmişi', 'rows' => $rows, 'p' => $p, 'handovers' => $handovers, 'filters' => ['q' => $q, 'durum' => $only]] + $this->lists('occupancy_roles', 'liability_modes'));
    }

    public function store(): Response
    {
        $d = $this->validate([
            'unit_id' => 'required|integer', 'person_id' => 'required|integer',
            'role' => 'required|in_keys:lists.occupancy_roles', 'liability' => 'required|in_keys:lists.liability_modes',
            'start_date' => 'required|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'is_notify_contact' => 'boolean', 'notes' => 'nullable|max:1000',
        ], ['unit_id' => 'bölüm', 'person_id' => 'kişi', 'role' => 'sıfat', 'liability' => 'borç sorumluluğu', 'start_date' => 'başlangıç', 'end_date' => 'bitiş']);
        $unit = $this->findOwned('units', (int) $d['unit_id']);
        $this->findOwned('people', (int) $d['person_id']);
        $b = $this->buildingId();
        if (in_array($d['role'], ['malik', 'kiraci'], true)) {
            $dup = $this->db->fetch('SELECT id FROM occupancies WHERE unit_id = ? AND person_id = ? AND role = ? AND (end_date IS NULL OR end_date >= ?)', [(int) $d['unit_id'], (int) $d['person_id'], $d['role'], Dates::today()]);
            if ($dup !== null) {
                throw new DomainException('Bu kişi için aynı bölümde aktif bir ' . list_label('occupancy_roles', $d['role']) . ' kaydı zaten var.');
            }
        }
        $id = $this->db->insert('occupancies', $d + ['building_id' => $b, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->db->update('units', ['status' => 'dolu', 'liability_mode' => $d['liability']], 'id = ?', [(int) $d['unit_id']]);
        $this->audit('occupancy.create', 'occupancy', $id, null, $d, 'Oturum kaydı eklendi: No ' . $unit['door_no'] . ' · ' . list_label('occupancy_roles', $d['role']));
        $this->success('Oturum kaydı eklendi.');
        return $this->redirectRoute('units.show', ['id' => (int) $d['unit_id']]);
    }

    public function end(int $id): Response
    {
        $row = $this->findOwned('occupancies', $id);
        $d = $this->validate(['end_date' => 'required|date'], ['end_date' => 'bitiş tarihi']);
        if ($d['end_date'] < $row['start_date']) {
            throw new DomainException('Bitiş tarihi başlangıçtan önce olamaz.');
        }
        $this->db->update('occupancies', ['end_date' => $d['end_date'], 'updated_at' => Database::now()], 'id = ?', [$id]);
        $remaining = $this->db->fetchInt('SELECT COUNT(*) FROM occupancies WHERE unit_id = ? AND (end_date IS NULL OR end_date >= ?)', [(int) $row['unit_id'], Dates::today()]);
        if ($remaining === 0) {
            $this->db->update('units', ['status' => 'bos'], 'id = ?', [(int) $row['unit_id']]);
        }
        $this->audit('occupancy.end', 'occupancy', $id, ['end_date' => $row['end_date']], ['end_date' => $d['end_date']], 'Oturum sonlandırıldı');
        $this->success('Oturum kaydı sonlandırıldı.');
        return $this->redirectRoute('units.show', ['id' => (int) $row['unit_id']]);
    }

    public function handoverCreate(): Response
    {
        $unitId = $this->request->int('bolum');
        $unit = $unitId > 0 ? $this->findOwned('units', $unitId) : null;
        $current = $unit ? $this->db->fetchAll('SELECT o.*, p.first_name, p.last_name, p.company_name FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.unit_id = ? AND (o.end_date IS NULL OR o.end_date >= ?) ORDER BY o.role', [$unitId, Dates::today()]) : [];
        $balance = $unit ? (new ChargeService($this->app))->unitBalance($unitId) : null;
        $units = $this->db->fetchAll('SELECT u.id, u.door_no, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? ORDER BY bl.sort_order, CAST(u.door_no AS INTEGER), u.door_no', [$this->buildingId()]);
        $people = $this->db->fetchAll('SELECT id, first_name, last_name, company_name FROM people WHERE building_id = ? ORDER BY first_name', [$this->buildingId()]);
        return $this->view('manager.occupancies.handover', ['title' => 'Devir işlemi', 'unit' => $unit, 'current' => $current, 'balance' => $balance, 'units' => $units, 'people' => $people] + $this->lists('occupancy_roles', 'liability_modes'));
    }

    public function handoverStore(): Response
    {
        $d = $this->validate([
            'unit_id' => 'required|integer', 'from_occupancy_id' => 'nullable|integer', 'to_person_id' => 'required|integer', 'to_role' => 'required|in_keys:lists.occupancy_roles', 'to_liability' => 'required|in_keys:lists.liability_modes',
            'handover_date' => 'required|date', 'balance_mode' => 'required|in:kalir,devreder', 'deposit_transferred' => 'nullable|money', 'notes' => 'nullable|max:2000',
        ], ['unit_id' => 'bölüm', 'to_person_id' => 'yeni taraf', 'to_role' => 'yeni sıfat', 'handover_date' => 'devir tarihi', 'balance_mode' => 'bakiye', 'deposit_transferred' => 'depozito/avans']);
        $unit = $this->findOwned('units', (int) $d['unit_id']);
        $this->findOwned('people', (int) $d['to_person_id']);
        $b = $this->buildingId();
        $charges = new ChargeService($this->app);
        $balance = $charges->unitBalance((int) $d['unit_id']);
        $fromPersonId = null;
        $id = $this->db->transaction(function () use ($d, $b, $unit, $balance, $charges, &$fromPersonId): int {
            if (!empty($d['from_occupancy_id'])) {
                $from = $this->findOwned('occupancies', (int) $d['from_occupancy_id']);
                $fromPersonId = (int) $from['person_id'];
                $this->db->update('occupancies', ['end_date' => date('Y-m-d', strtotime($d['handover_date'] . ' -1 day')), 'updated_at' => Database::now()], 'id = ?', [(int) $from['id']]);
            }
            $this->db->insert('occupancies', ['building_id' => $b, 'unit_id' => (int) $d['unit_id'], 'person_id' => (int) $d['to_person_id'], 'role' => $d['to_role'], 'liability' => $d['to_liability'], 'is_notify_contact' => 1, 'start_date' => $d['handover_date'], 'notes' => 'Devir ile başladı', 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            $this->db->update('units', ['status' => 'dolu', 'liability_mode' => $d['to_liability']], 'id = ?', [(int) $d['unit_id']]);
            $transferred = 0;
            if ($d['balance_mode'] === 'devreder' && $balance['debt'] > 0) {
                // Açık borçların sorumlu kişisi yeni tarafa geçer
                $this->db->update('charges', ['person_id' => (int) $d['to_person_id'], 'updated_at' => Database::now()], "unit_id = ? AND status IN ('odenmedi','kismi')", [(int) $d['unit_id']]);
                $transferred = $balance['debt'];
            }
            return $this->db->insert('handovers', [
                'building_id' => $b, 'unit_id' => (int) $d['unit_id'], 'from_person_id' => $fromPersonId, 'to_person_id' => (int) $d['to_person_id'],
                'handover_date' => $d['handover_date'], 'balance_mode' => $d['balance_mode'], 'transferred_balance' => $transferred, 'deposit_transferred' => (int) ($d['deposit_transferred'] ?? 0),
                'notes' => $d['notes'], 'created_by' => $this->userId(), 'created_at' => Database::now(),
            ]);
        });
        $this->audit('handover.create', 'handover', $id, null, ['unit_id' => $d['unit_id'], 'balance_mode' => $d['balance_mode'], 'debt' => $balance['debt']], 'Devir yapıldı: No ' . $unit['door_no'] . ' · bakiye ' . Money::format($balance['debt']));
        $this->success('Devir tamamlandı. Eski oturum kapatıldı, yeni oturum başlatıldı.');
        return $this->redirectRoute('handovers.show', ['id' => $id]);
    }

    public function handoverShow(int $id): Response
    {
        $h = $this->db->fetch('SELECT h.*, u.door_no, bl.name AS block_name, pf.first_name AS from_first, pf.last_name AS from_last, pf.company_name AS from_company, pt.first_name AS to_first, pt.last_name AS to_last, pt.company_name AS to_company, usr.name AS created_by_name FROM handovers h JOIN units u ON u.id = h.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN people pf ON pf.id = h.from_person_id LEFT JOIN people pt ON pt.id = h.to_person_id LEFT JOIN users usr ON usr.id = h.created_by WHERE h.id = ? AND h.building_id = ?', [$id, $this->buildingId()]) ?? throw new \Aidat\Core\Exceptions\HttpException(404, 'Devir kaydı bulunamadı.');
        $print = $this->request->has('yazdir');
        $data = ['title' => 'Devir tutanağı · No ' . $h['door_no'], 'h' => $h, 'building' => $this->building()];
        if ($print) {
            return Response::html($this->app->view()->render('manager.occupancies.handover-print', $data + ['backUrl' => $this->route('handovers.show', ['id' => $id])], 'layouts.print'));
        }
        return $this->view('manager.occupancies.handover-show', $data);
    }
}
