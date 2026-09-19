<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Response;
use Aidat\Services\ExpenseService;
use Aidat\Services\LedgerService;

final class BuildingController extends Controller
{
    public function index(): Response
    {
        $ids = $this->app->gate()->managedBuildingIds();
        $rows = [];
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db->fetchAll("SELECT b.*, (SELECT COUNT(*) FROM units u WHERE u.building_id = b.id) AS unit_count, (SELECT COUNT(*) FROM building_users bu WHERE bu.building_id = b.id) AS user_count FROM buildings b WHERE b.id IN ({$in}) ORDER BY b.name", $ids);
            $ledger = new LedgerService($this->app);
            foreach ($rows as &$r) {
                $r['balance'] = $ledger->totalBalance((int) $r['id']);
                $r['open_debt'] = $this->db->fetchInt("SELECT COALESCE(SUM(amount - paid_amount),0) FROM charges WHERE building_id = ? AND status IN ('odenmedi','kismi')", [(int) $r['id']]);
            }
        }
        return $this->view('manager.buildings.index', ['title' => 'Yapılar', 'rows' => $rows, 'canCreate' => is_admin() || $this->can('buildings.manage')]);
    }

    public function create(): Response
    {
        if (!is_admin() && !$this->app->gate()->hasManagementAccess()) {
            throw new AuthorizationException();
        }
        return $this->view('manager.buildings.form', ['title' => 'Yeni yapı', 'row' => null] + $this->lists('building_types'));
    }

    public function store(): Response
    {
        if (!is_admin() && !$this->app->gate()->hasManagementAccess()) {
            throw new AuthorizationException();
        }
        $d = $this->validated();
        $id = $this->db->transaction(function () use ($d): int {
            $id = $this->db->insert('buildings', $d + ['is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            $this->db->insert('building_users', ['user_id' => $this->userId(), 'building_id' => $id, 'role' => 'manager', 'is_default' => 1, 'created_at' => Database::now()]);
            $this->db->insert('accounts', ['building_id' => $id, 'name' => 'Nakit kasa', 'type' => 'kasa', 'opening_balance' => 0, 'is_default' => 1, 'is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            if (!empty($d['iban'])) {
                $this->db->insert('accounts', ['building_id' => $id, 'name' => ($d['bank_name'] ?: 'Banka') . ' hesabı', 'type' => 'banka', 'bank_name' => $d['bank_name'], 'iban' => $d['iban'], 'opening_balance' => 0, 'is_default' => 0, 'is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            }
            (new ExpenseService($this->app))->ensureDefaultCategories($id);
            return $id;
        });
        $this->app->gate()->flush();
        $this->app->session()->set('building_id', $id);
        $this->app->audit()->log('building.create', 'building', $id, null, $d, $id, 'Yapı oluşturuldu: ' . $d['name']);
        $this->success('Yapı oluşturuldu. Şimdi blok ve bağımsız bölümleri ekleyebilirsiniz.');
        return $this->redirectRoute('units.bulk');
    }

    public function edit(int $id): Response
    {
        $row = $this->ownedBuilding($id);
        return $this->view('manager.buildings.form', ['title' => $row['name'], 'row' => $row] + $this->lists('building_types'));
    }

    public function update(int $id): Response
    {
        $row = $this->ownedBuilding($id);
        $d = $this->validated();
        $d['is_active'] = $this->request->bool('is_active') ? 1 : 0;
        $this->db->update('buildings', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $d);
        $this->app->audit()->log('building.update', 'building', $id, $o, $n, $id, 'Yapı güncellendi');
        $this->success('Yapı bilgileri güncellendi.');
        return $this->redirectRoute('buildings.index');
    }

    public function switch(int $id): Response
    {
        if (!in_array($id, $this->app->gate()->managedBuildingIds(), true)) {
            throw new AuthorizationException('Bu yapıya erişiminiz yok.');
        }
        $this->app->session()->set('building_id', $id);
        return $this->redirectRoute('dashboard');
    }

    /** @return array<string, mixed> */
    private function validated(): array
    {
        $d = $this->validate([
            'name' => 'required|string|min:2|max:150',
            'type' => 'required|in_keys:lists.building_types',
            'tax_no' => 'nullable|max:20',
            'tax_office' => 'nullable|max:80',
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:60',
            'district' => 'nullable|max:60',
            'management_start' => 'nullable|date',
            'iban' => 'nullable|iban',
            'bank_name' => 'nullable|max:80',
            'account_holder' => 'nullable|max:120',
            'phone' => 'nullable|phone',
            'email' => 'nullable|email|max:190',
            'notes' => 'nullable|max:2000',
        ], ['name' => 'yapı adı', 'type' => 'tür', 'tax_no' => 'vergi no', 'tax_office' => 'vergi dairesi', 'address' => 'adres', 'city' => 'il', 'district' => 'ilçe', 'management_start' => 'yönetim başlangıcı', 'iban' => 'IBAN', 'bank_name' => 'banka', 'account_holder' => 'hesap sahibi', 'phone' => 'telefon', 'email' => 'e-posta', 'notes' => 'notlar']);
        $d['currency'] = 'TRY';
        return $d;
    }

    /** @return array<string, mixed> */
    private function ownedBuilding(int $id): array
    {
        if (!in_array($id, $this->app->gate()->managedBuildingIds(), true)) {
            throw new HttpException(404, 'Yapı bulunamadı.');
        }
        if (!$this->app->gate()->allows('buildings.manage', $id)) {
            throw new AuthorizationException();
        }
        return $this->db->fetch('SELECT * FROM buildings WHERE id = ?', [$id]) ?? throw new HttpException(404, 'Yapı bulunamadı.');
    }
}
