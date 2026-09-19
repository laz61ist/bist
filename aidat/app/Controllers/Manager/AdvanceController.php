<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Response;
use Aidat\Services\PaymentService;

/** Avans (dağıtılmamış tahsilat) ve açık borca mahsup. */
final class AdvanceController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $rows = $this->db->fetchAll(
            "SELECT u.id AS unit_id, u.door_no, u.type AS unit_type, bl.name AS block_name,
                    SUM(p.unallocated_amount) AS advance, COUNT(p.id) AS payment_count, MIN(p.payment_date) AS oldest_payment,
                    COALESCE((SELECT SUM(c.amount - c.paid_amount) FROM charges c WHERE c.unit_id = u.id AND c.status IN ('odenmedi','kismi')), 0) AS open_debt
             FROM payments p JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             WHERE p.building_id = ? AND p.status = 'gecerli' AND p.unallocated_amount > 0
             GROUP BY u.id, u.door_no, u.type, bl.name
             ORDER BY (open_debt > 0) DESC, advance DESC",
            [$b],
        );
        $totals = ['advance' => 0, 'units' => count($rows), 'both' => 0, 'applicable' => 0];
        foreach ($rows as $r) {
            $totals['advance'] += (int) $r['advance'];
            if ((int) $r['open_debt'] > 0) {
                $totals['both']++;
                $totals['applicable'] += min((int) $r['advance'], (int) $r['open_debt']);
            }
        }
        $unassigned = $this->db->fetch("SELECT COUNT(*) AS cnt, COALESCE(SUM(unallocated_amount), 0) AS total FROM payments WHERE building_id = ? AND status = 'gecerli' AND unit_id IS NULL AND unallocated_amount > 0", [$b]) ?? ['cnt' => 0, 'total' => 0];
        $recent = $this->db->fetchAll(
            "SELECT p.*, u.door_no, bl.name AS block_name FROM payments p LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             WHERE p.building_id = ? AND p.allocation_mode = 'avans' ORDER BY p.payment_date DESC, p.id DESC LIMIT 20",
            [$b],
        );
        return $this->view('manager.advances.index', ['title' => 'Avans ve mahsup', 'rows' => $rows, 'totals' => $totals, 'unassigned' => $unassigned, 'recent' => $recent] + $this->lists('payment_methods', 'payment_statuses', 'unit_types'));
    }

    public function apply(): Response
    {
        $b = $this->buildingId();
        $svc = new PaymentService($this->app);
        if ($this->request->str('unit_id') === 'all') {
            $ids = array_map('intval', array_column($this->db->fetchAll(
                "SELECT DISTINCT p.unit_id FROM payments p WHERE p.building_id = ? AND p.status = 'gecerli' AND p.unallocated_amount > 0 AND p.unit_id IS NOT NULL
                   AND EXISTS (SELECT 1 FROM charges c WHERE c.unit_id = p.unit_id AND c.status IN ('odenmedi','kismi'))",
                [$b],
            ), 'unit_id'));
            $applied = 0;
            $units = 0;
            foreach ($ids as $uid) {
                $a = $svc->applyAdvances($uid);
                if ($a > 0) {
                    $applied += $a;
                    $units++;
                }
            }
            if ($applied > 0) {
                $this->audit('advance.apply_all', 'building', $b, null, ['applied' => $applied, 'units' => $units], 'Toplu avans mahsubu: ' . $units . ' bölüm, ' . money($applied));
                $this->success($units . ' bölümde toplam ' . money($applied) . ' avans açık borçlara mahsup edildi.');
            } else {
                $this->error('Mahsup edilecek avans/borç eşleşmesi yok.');
            }
            return $this->redirectRoute('advances.index');
        }
        $d = $this->validate(['unit_id' => 'required|integer'], ['unit_id' => 'bölüm'], $this->route('advances.index'));
        $unit = $this->findOwned('units', (int) $d['unit_id']);
        $applied = $svc->applyAdvances((int) $unit['id']);
        if ($applied > 0) {
            $this->audit('advance.apply', 'unit', (int) $unit['id'], null, ['applied' => $applied], 'Avans mahsup edildi: No ' . $unit['door_no'] . ' · ' . money($applied));
            $this->success('No ' . $unit['door_no'] . ' için ' . money($applied) . ' avans açık borçlara mahsup edildi.');
        } else {
            $this->error('No ' . $unit['door_no'] . ' için mahsup edilecek açık borç veya avans yok.');
        }
        return $this->redirectRoute('advances.index');
    }
}
