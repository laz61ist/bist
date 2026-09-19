<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Response;

final class AssetController extends Controller
{
    private const RULES = [
        'name' => 'required|max:150',
        'category' => 'nullable|max:60',
        'serial_no' => 'nullable|max:60',
        'purchase_date' => 'nullable|date',
        'cost' => 'nullable|money',
        'location' => 'nullable|max:120',
        'status' => 'required|in_keys:lists.asset_statuses',
        'warranty_until' => 'nullable|date',
        'vendor_id' => 'nullable|integer',
        'expense_id' => 'nullable|integer',
        'notes' => 'nullable|max:2000',
    ];
    private const LABELS = ['name' => 'demirbaş adı', 'category' => 'kategori', 'serial_no' => 'seri no', 'purchase_date' => 'alım tarihi', 'cost' => 'maliyet', 'location' => 'konum', 'status' => 'durum', 'warranty_until' => 'garanti bitişi', 'vendor_id' => 'tedarikçi', 'expense_id' => 'gider kaydı'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $status = $this->request->str('durum');
        $q = trim($this->request->str('q'));
        $where = 'a.building_id = ?';
        $params = [$b];
        if ($status !== '') {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (a.name LIKE ? OR a.category LIKE ? OR a.serial_no LIKE ? OR a.location LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $rows = $this->db->fetchAll("SELECT a.*, v.name AS vendor_name FROM assets a LEFT JOIN vendors v ON v.id = a.vendor_id WHERE {$where} ORDER BY CASE a.status WHEN 'hurda' THEN 1 ELSE 0 END, a.name", $params);
        $today = Dates::today();
        $soon = date('Y-m-d', strtotime('+60 days'));
        $summary = [
            'count' => $this->db->fetchInt("SELECT COUNT(*) FROM assets WHERE building_id = ? AND status <> 'hurda'", [$b]),
            'cost' => $this->db->fetchInt("SELECT COALESCE(SUM(cost),0) FROM assets WHERE building_id = ? AND status <> 'hurda'", [$b]),
            'faulty' => $this->db->fetchInt("SELECT COUNT(*) FROM assets WHERE building_id = ? AND status IN ('arizali','bakimda')", [$b]),
            'warranty_soon' => $this->db->fetchInt("SELECT COUNT(*) FROM assets WHERE building_id = ? AND status <> 'hurda' AND warranty_until IS NOT NULL AND warranty_until >= ? AND warranty_until <= ?", [$b, $today, $soon]),
        ];
        $statusCounts = $this->db->fetchPairs('SELECT status, COUNT(*) FROM assets WHERE building_id = ? GROUP BY status', [$b]);
        $listTotal = array_sum(array_map(static fn (array $a): int => (int) $a['cost'], $rows));
        return $this->view('manager.assets.index', ['title' => 'Demirbaş', 'rows' => $rows, 'summary' => $summary, 'statusCounts' => $statusCounts, 'listTotal' => $listTotal, 'today' => $today, 'soon' => $soon, 'filters' => ['durum' => $status, 'q' => $q]] + $this->lists('asset_statuses'));
    }

    public function create(): Response
    {
        return $this->view('manager.assets.form', ['title' => 'Yeni demirbaş', 'row' => null] + $this->formData());
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $this->normalize($d);
        $id = $this->db->insert('assets', $d + ['building_id' => $this->buildingId(), 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('asset.create', 'asset', $id, null, $d, 'Demirbaş eklendi: ' . $d['name']);
        $this->success('Demirbaş kaydedildi.');
        return $this->redirectRoute('assets.index');
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('assets', $id);
        return $this->view('manager.assets.form', ['title' => $row['name'] . ' düzenle', 'row' => $row] + $this->formData());
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('assets', $id);
        $d = $this->validate(self::RULES, self::LABELS);
        $this->normalize($d);
        $this->db->update('assets', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $d);
        $this->audit('asset.update', 'asset', $id, $o, $n, 'Demirbaş güncellendi: ' . $d['name']);
        $this->success('Demirbaş güncellendi.');
        return $this->redirectRoute('assets.index');
    }

    /** @param array<string, mixed> $d */
    private function normalize(array &$d): void
    {
        $b = $this->buildingId();
        $d['cost'] = (int) ($d['cost'] ?? 0);
        if ($d['cost'] < 0) {
            throw new DomainException('Maliyet negatif olamaz.');
        }
        $d['vendor_id'] = $d['vendor_id'] ? (int) $d['vendor_id'] : null;
        if ($d['vendor_id'] !== null) {
            $this->findOwned('vendors', $d['vendor_id']);
        }
        $d['expense_id'] = $d['expense_id'] ? (int) $d['expense_id'] : null;
        if ($d['expense_id'] !== null && $this->db->fetch('SELECT id FROM expenses WHERE id = ? AND building_id = ?', [$d['expense_id'], $b]) === null) {
            throw new DomainException('Gider kaydı bu yapıda bulunamadı. Gider numarasını kontrol edin.');
        }
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $b = $this->buildingId();
        return [
            'vendors' => $this->db->fetchPairs('SELECT id, name FROM vendors WHERE building_id = ? ORDER BY name', [$b]),
            'categories' => array_map('strval', array_column($this->db->fetchAll("SELECT DISTINCT category FROM assets WHERE building_id = ? AND category IS NOT NULL AND category <> '' ORDER BY category", [$b]), 'category')),
        ] + $this->lists('asset_statuses');
    }
}
