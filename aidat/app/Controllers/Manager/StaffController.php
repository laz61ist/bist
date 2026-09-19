<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Response;
use Aidat\Core\Str;

final class StaffController extends Controller
{
    private const RULES = [
        'full_name' => 'required|max:120',
        'position' => 'required|in_keys:lists.staff_positions',
        'phone' => 'nullable|phone|max:30',
        'email' => 'nullable|email|max:190',
        'identity_no' => 'nullable|tckn',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'salary' => 'nullable|money',
        'sgk_no' => 'nullable|max:30',
        'is_active' => 'boolean',
        'notes' => 'nullable|max:2000',
    ];
    private const LABELS = ['full_name' => 'ad soyad', 'position' => 'görev', 'phone' => 'telefon', 'email' => 'e-posta', 'identity_no' => 'TCKN', 'start_date' => 'işe başlama', 'end_date' => 'ayrılış', 'salary' => 'maaş', 'sgk_no' => 'SGK sicil no'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $status = $this->request->str('durum', 'aktif');
        $where = 'building_id = ?';
        $params = [$b];
        if ($status === 'aktif') {
            $where .= ' AND is_active = 1';
        } elseif ($status === 'pasif') {
            $where .= ' AND is_active = 0';
        }
        $rows = $this->db->fetchAll("SELECT * FROM staff WHERE {$where} ORDER BY is_active DESC, full_name", $params);
        $summary = [
            'active' => $this->db->fetchInt('SELECT COUNT(*) FROM staff WHERE building_id = ? AND is_active = 1', [$b]),
            'total' => $this->db->fetchInt('SELECT COUNT(*) FROM staff WHERE building_id = ?', [$b]),
            'payroll' => $this->db->fetchInt('SELECT COALESCE(SUM(salary),0) FROM staff WHERE building_id = ? AND is_active = 1', [$b]),
        ];
        return $this->view('manager.staff.index', ['title' => 'Personel', 'rows' => $rows, 'summary' => $summary, 'filters' => ['durum' => $status], 'canIdentity' => $this->can('people.identity'), 'today' => Dates::today()] + $this->lists('staff_positions'));
    }

    public function create(): Response
    {
        return $this->view('manager.staff.form', ['title' => 'Yeni personel', 'row' => null] + $this->lists('staff_positions'));
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $d['salary'] = (int) ($d['salary'] ?? 0);
        $id = $this->db->insert('staff', $d + ['building_id' => $this->buildingId(), 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->audit('staff.create', 'staff', $id, null, $this->masked($d), 'Personel eklendi: ' . $d['full_name']);
        $this->success('Personel kaydedildi.');
        return $this->redirectRoute('staff.index');
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('staff', $id);
        return $this->view('manager.staff.form', ['title' => $row['full_name'] . ' düzenle', 'row' => $row, 'canIdentity' => $this->can('people.identity')] + $this->lists('staff_positions'));
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('staff', $id);
        $d = $this->validate(self::RULES, self::LABELS);
        $d['salary'] = (int) ($d['salary'] ?? 0);
        if (!$this->can('people.identity') && array_key_exists('identity_no', $d)) {
            unset($d['identity_no']);
        }
        if ($d['end_date'] !== null && $d['end_date'] <= Dates::today()) {
            $d['is_active'] = 0;
        }
        $this->db->update('staff', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $d);
        $this->audit('staff.update', 'staff', $id, $this->masked($o), $this->masked($n), 'Personel güncellendi: ' . $d['full_name']);
        $this->success('Personel bilgileri güncellendi.');
        return $this->redirectRoute('staff.index');
    }

    /** @param array<string, mixed> $d @return array<string, mixed> */
    private function masked(array $d): array
    {
        if (isset($d['identity_no'])) {
            $d['identity_no'] = Str::maskIdentity((string) $d['identity_no']);
        }
        return $d;
    }
}
