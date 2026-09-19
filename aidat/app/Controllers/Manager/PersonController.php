<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Response;
use Aidat\Core\Str;
use Aidat\Services\NotificationService;

final class PersonController extends Controller
{
    private const RULES = [
        'type' => 'required|in_keys:lists.person_types',
        'first_name' => 'required|max:80',
        'last_name' => 'nullable|max:80',
        'company_name' => 'nullable|max:150',
        'phone' => 'nullable|phone|max:30',
        'phone2' => 'nullable|phone|max:30',
        'email' => 'nullable|email|max:190',
        'identity_no' => 'nullable|tckn_or_vkn',
        'contact_consent' => 'boolean',
        'emergency_name' => 'nullable|max:120',
        'emergency_phone' => 'nullable|phone|max:30',
        'notes' => 'nullable|max:2000',
    ];
    private const LABELS = ['type' => 'kişi türü', 'first_name' => 'ad / unvan', 'last_name' => 'soyad', 'company_name' => 'şirket unvanı', 'phone' => 'telefon', 'phone2' => 'ikinci telefon', 'email' => 'e-posta', 'identity_no' => 'TCKN / VKN', 'emergency_name' => 'acil durum kişisi', 'emergency_phone' => 'acil durum telefonu'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $role = $this->request->str('rol');
        $today = Dates::today();
        $where = 'p.building_id = ?';
        $params = [$b];
        if ($q !== '') {
            $where .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.company_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if ($role !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM occupancies o WHERE o.person_id = p.id AND o.role = ? AND (o.end_date IS NULL OR o.end_date >= ?))';
            array_push($params, $role, $today);
        }
        $total = $this->db->fetchInt("SELECT COUNT(*) FROM people p WHERE {$where}", $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll(
            "SELECT p.*, (SELECT GROUP_CONCAT(COALESCE(bl.name || ' ', '') || 'No ' || u.door_no || ' (' || o.role || ')', ', ') FROM occupancies o JOIN units u ON u.id = o.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id WHERE o.person_id = p.id AND (o.end_date IS NULL OR o.end_date >= ?)) AS units_text,
                    (SELECT COALESCE(SUM(c.amount - c.paid_amount),0) FROM charges c JOIN occupancies o2 ON o2.unit_id = c.unit_id AND o2.person_id = p.id AND (o2.end_date IS NULL OR o2.end_date >= ?) WHERE c.status IN ('odenmedi','kismi')) AS open_debt
             FROM people p WHERE {$where} ORDER BY p.first_name, p.last_name LIMIT {$p->perPage} OFFSET {$p->offset}",
            array_merge([$today, $today], $params),
        );
        return $this->view('manager.people.index', ['title' => 'Kişiler', 'rows' => $rows, 'p' => $p, 'filters' => ['q' => $q, 'rol' => $role], 'canIdentity' => $this->can('people.identity')] + $this->lists('occupancy_roles'));
    }

    public function create(): Response
    {
        return $this->view('manager.people.form', ['title' => 'Yeni kişi', 'row' => null, 'units' => $this->unitOptions(), 'preUnit' => $this->request->int('bolum')] + $this->lists('person_types', 'occupancy_roles', 'liability_modes'));
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES + ['unit_id' => 'nullable|integer', 'occ_role' => 'nullable|in_keys:lists.occupancy_roles', 'occ_liability' => 'nullable|in_keys:lists.liability_modes', 'occ_start' => 'nullable|date'], self::LABELS + ['unit_id' => 'bağımsız bölüm', 'occ_role' => 'sıfat', 'occ_start' => 'başlangıç']);
        $b = $this->buildingId();
        $unitId = $d['unit_id'] ? (int) $d['unit_id'] : null;
        unset($d['unit_id']);
        $occRole = $d['occ_role'] ?? 'malik';
        $occLiab = $d['occ_liability'] ?? 'malik';
        $occStart = $d['occ_start'] ?? Dates::today();
        unset($d['occ_role'], $d['occ_liability'], $d['occ_start']);
        $id = $this->db->transaction(function () use ($d, $b, $unitId, $occRole, $occLiab, $occStart): int {
            $id = $this->db->insert('people', $d + ['building_id' => $b, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            if ($unitId !== null) {
                $this->findOwned('units', $unitId);
                $this->db->insert('occupancies', ['building_id' => $b, 'unit_id' => $unitId, 'person_id' => $id, 'role' => $occRole, 'liability' => $occLiab, 'is_notify_contact' => 1, 'start_date' => $occStart, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
                $this->db->update('units', ['status' => 'dolu'], 'id = ? AND status = ?', [$unitId, 'bos']);
            }
            return $id;
        });
        $this->audit('person.create', 'person', $id, null, ['name' => trim($d['first_name'] . ' ' . ($d['last_name'] ?? ''))], 'Kişi eklendi');
        $this->success('Kişi kaydedildi.');
        return $unitId ? $this->redirectRoute('units.show', ['id' => $unitId]) : $this->redirectRoute('people.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $row = $this->findOwned('people', $id);
        $today = Dates::today();
        $occupancies = $this->db->fetchAll('SELECT o.*, u.door_no, u.type AS unit_type, bl.name AS block_name FROM occupancies o JOIN units u ON u.id = o.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id WHERE o.person_id = ? ORDER BY (o.end_date IS NULL OR o.end_date >= ?) DESC, o.start_date DESC', [$id, $today]);
        $unitIds = array_values(array_unique(array_map(static fn ($o) => (int) $o['unit_id'], array_filter($occupancies, static fn ($o) => $o['end_date'] === null || $o['end_date'] >= $today))));
        $charges = [];
        $payments = [];
        if ($unitIds !== []) {
            $in = implode(',', array_fill(0, count($unitIds), '?'));
            $charges = $this->db->fetchAll("SELECT c.*, u.door_no FROM charges c JOIN units u ON u.id = c.unit_id WHERE c.unit_id IN ({$in}) AND c.status IN ('odenmedi','kismi') ORDER BY c.due_date", $unitIds);
            $payments = $this->db->fetchAll("SELECT p.*, u.door_no FROM payments p LEFT JOIN units u ON u.id = p.unit_id WHERE p.unit_id IN ({$in}) ORDER BY p.payment_date DESC, p.id DESC LIMIT 10", $unitIds);
        }
        $user = $row['user_id'] ? $this->db->fetch('SELECT id, name, email, is_active, last_login_at FROM users WHERE id = ?', [(int) $row['user_id']]) : null;
        $notifications = $this->db->fetchAll('SELECT * FROM notifications WHERE person_id = ? ORDER BY id DESC LIMIT 8', [$id]);
        return $this->view('manager.people.show', ['title' => $row['company_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']), 'row' => $row, 'occupancies' => $occupancies, 'charges' => $charges, 'payments' => $payments, 'portalUser' => $user, 'notifications' => $notifications, 'canIdentity' => $this->can('people.identity')]);
    }

    public function edit(int $id): Response
    {
        $row = $this->findOwned('people', $id);
        return $this->view('manager.people.form', ['title' => 'Kişi düzenle', 'row' => $row, 'units' => [], 'preUnit' => 0] + $this->lists('person_types', 'occupancy_roles', 'liability_modes'));
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('people', $id);
        $d = $this->validate(self::RULES, self::LABELS);
        if (!$this->can('people.identity') && array_key_exists('identity_no', $d)) {
            unset($d['identity_no']);
        }
        $this->db->update('people', $d + ['updated_at' => Database::now()], 'id = ?', [$id]);
        [$o, $n] = \Aidat\Core\Audit::diff($row, $d);
        if (isset($o['identity_no'])) {
            $o['identity_no'] = Str::maskIdentity((string) $o['identity_no']);
            $n['identity_no'] = Str::maskIdentity((string) $n['identity_no']);
        }
        $this->audit('person.update', 'person', $id, $o, $n, 'Kişi güncellendi');
        $this->success('Kişi bilgileri güncellendi.');
        return $this->redirectRoute('people.show', ['id' => $id]);
    }

    /** Kişi için sakin alanı hesabı oluşturur (malik/kiracı). */
    public function createAccount(int $id): Response
    {
        $row = $this->findOwned('people', $id);
        if ($row['user_id']) {
            throw new DomainException('Bu kişinin zaten bir hesabı var.');
        }
        $email = mb_strtolower(trim((string) ($row['email'] ?: $this->request->str('email'))));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Hesap açmak için geçerli bir e-posta gerekli.');
        }
        if ($this->db->fetch('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            throw new DomainException('Bu e-posta başka bir kullanıcıda kayıtlı.');
        }
        $role = $this->db->fetchColumn("SELECT role FROM occupancies WHERE person_id = ? AND (end_date IS NULL OR end_date >= ?) ORDER BY CASE role WHEN 'malik' THEN 0 ELSE 1 END LIMIT 1", [$id, Dates::today()]);
        $userRole = $role === 'kiraci' ? 'tenant' : 'owner';
        $password = Str::random(5);
        $userId = $this->db->transaction(function () use ($row, $email, $userRole, $password, $id): int {
            $uid = $this->db->insert('users', ['name' => $row['company_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']), 'email' => $email, 'phone' => $row['phone'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $userRole, 'is_active' => 1, 'must_change_password' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
            $this->db->update('people', ['user_id' => $uid, 'email' => $email, 'updated_at' => Database::now()], 'id = ?', [$id]);
            $this->db->insert('building_users', ['user_id' => $uid, 'building_id' => $this->buildingId(), 'role' => $userRole, 'created_at' => Database::now()]);
            return $uid;
        });
        (new NotificationService($this->app))->sendEmail($this->buildingId(), $id, $userId, $email, 'Sakin alanı hesabınız oluşturuldu', "Merhaba,\n\n{$this->building()['name']} sakin alanına giriş bilgileriniz:\nE-posta: {$email}\nGeçici şifre: {$password}\n\nGiriş: " . rtrim((string) config('app.url'), '/') . $this->route('login') . "\nİlk girişte şifrenizi değiştirin.", 'account_created');
        $this->audit('person.account', 'user', $userId, null, ['email' => $email, 'role' => $userRole], 'Sakin hesabı oluşturuldu');
        $this->success("Hesap oluşturuldu. Geçici şifre: {$password} (kişiye e-posta ile de iletildi; posta sürücüsü log ise bildirim kaydında).");
        return $this->redirectRoute('people.show', ['id' => $id]);
    }

    /** @return array<string, string> */
    private function unitOptions(): array
    {
        $rows = $this->db->fetchAll('SELECT u.id, u.door_no, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? ORDER BY bl.sort_order, bl.name, CAST(u.door_no AS INTEGER), u.door_no', [$this->buildingId()]);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['id']] = ($r['block_name'] ? $r['block_name'] . ' · ' : '') . 'No ' . $r['door_no'];
        }
        return $out;
    }
}
