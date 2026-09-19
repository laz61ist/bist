<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Response;
use Aidat\Services\ExpenseService;
use Aidat\Services\NotificationService;

final class RequestController extends Controller
{
    private const RULES = [
        'category' => 'required|in_keys:lists.request_categories',
        'unit_id' => 'nullable|integer',
        'person_id' => 'nullable|integer',
        'title' => 'required|max:150',
        'description' => 'nullable|max:4000',
        'location' => 'nullable|max:120',
        'priority' => 'required|in_keys:lists.request_priorities',
        'assigned_to' => 'nullable|integer',
        'target_date' => 'nullable|date',
        'visibility' => 'required|in_keys:lists.request_visibilities',
    ];
    private const LABELS = ['category' => 'kategori', 'unit_id' => 'bağımsız bölüm', 'person_id' => 'talep sahibi', 'title' => 'başlık', 'description' => 'açıklama', 'location' => 'konum', 'priority' => 'öncelik', 'assigned_to' => 'atanan', 'target_date' => 'hedef tarih', 'visibility' => 'görünürlük'];

    public function index(): Response
    {
        $b = $this->buildingId();
        $q = trim($this->request->str('q'));
        $status = $this->request->str('durum');
        $category = $this->request->str('kategori');
        $priority = $this->request->str('oncelik');
        $assigned = $this->request->int('atanan');
        $where = 'r.building_id = ?';
        $params = [$b];
        if ($q !== '') {
            $where .= ' AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR u.door_no LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if ($status !== '') {
            if ($status === 'acik') {
                $where .= " AND r.status NOT IN ('tamamlandi','iptal')";
            } else {
                $where .= ' AND r.status = ?';
                $params[] = $status;
            }
        }
        if ($category !== '') {
            $where .= ' AND r.category = ?';
            $params[] = $category;
        }
        if ($priority !== '') {
            $where .= ' AND r.priority = ?';
            $params[] = $priority;
        }
        if ($assigned > 0) {
            $where .= ' AND r.assigned_to = ?';
            $params[] = $assigned;
        }
        $from = 'FROM requests r LEFT JOIN units u ON u.id = r.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN people p ON p.id = r.person_id LEFT JOIN users a ON a.id = r.assigned_to';
        $total = $this->db->fetchInt("SELECT COUNT(*) {$from} WHERE {$where}", $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll(
            "SELECT r.*, u.door_no, bl.name AS block_name, p.first_name, p.last_name, p.company_name, a.name AS assigned_name,
                    (SELECT COUNT(*) FROM request_comments c WHERE c.request_id = r.id) AS comment_count
             {$from} WHERE {$where}
             ORDER BY CASE r.status WHEN 'tamamlandi' THEN 1 WHEN 'iptal' THEN 2 ELSE 0 END, CASE r.priority WHEN 'acil' THEN 0 WHEN 'yuksek' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, r.id DESC
             LIMIT {$p->perPage} OFFSET {$p->offset}",
            $params,
        );
        $statusCounts = $this->db->fetchPairs('SELECT status, COUNT(*) FROM requests WHERE building_id = ? GROUP BY status', [$b]);
        $summary = [
            'open' => $this->db->fetchInt("SELECT COUNT(*) FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal')", [$b]),
            'overdue' => $this->db->fetchInt("SELECT COUNT(*) FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal') AND target_date IS NOT NULL AND target_date < ?", [$b, Dates::today()]),
            'urgent' => $this->db->fetchInt("SELECT COUNT(*) FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal') AND priority = 'acil'", [$b]),
            'done_month' => $this->db->fetchInt("SELECT COUNT(*) FROM requests WHERE building_id = ? AND status = 'tamamlandi' AND resolved_at >= ?", [$b, date('Y-m-01 00:00:00')]),
        ];
        return $this->view('manager.requests.index', [
            'title' => 'Talepler ve iş emirleri', 'rows' => $rows, 'p' => $p, 'summary' => $summary, 'statusCounts' => $statusCounts, 'staff' => $this->staffUsers(), 'today' => Dates::today(),
            'filters' => ['q' => $q, 'durum' => $status, 'kategori' => $category, 'oncelik' => $priority, 'atanan' => $assigned],
        ] + $this->lists('request_statuses', 'request_categories', 'request_priorities'));
    }

    public function create(): Response
    {
        return $this->view('manager.requests.form', ['title' => 'Yeni talep / iş emri', 'row' => null, 'preUnit' => $this->request->int('bolum')] + $this->formData());
    }

    public function store(): Response
    {
        $d = $this->validate(self::RULES, self::LABELS);
        $b = $this->buildingId();
        $this->assertRefs($d);
        $file = $this->request->file('photo');
        $stored = $file !== null ? $this->app->uploads()->store($file, 'talepler/' . $b) : null;
        $id = $this->db->transaction(function () use ($d, $b, $stored): int {
            $id = $this->db->insert('requests', [
                'building_id' => $b, 'unit_id' => $d['unit_id'] ?: null, 'person_id' => $d['person_id'] ?: null, 'created_by' => $this->userId(),
                'category' => $d['category'], 'title' => $d['title'], 'description' => $d['description'], 'location' => $d['location'],
                'priority' => $d['priority'], 'status' => $d['assigned_to'] ? 'atandi' : 'yeni', 'assigned_to' => $d['assigned_to'] ?: null,
                'target_date' => $d['target_date'], 'visibility' => $d['visibility'], 'cost_amount' => 0,
                'created_at' => Database::now(), 'updated_at' => Database::now(),
            ]);
            if ($stored !== null) {
                $this->db->insert('documents', [
                    'building_id' => $b, 'category' => 'diger', 'title' => 'Talep eki: ' . mb_substr((string) $d['title'], 0, 120), 'file_path' => $stored['path'], 'original_name' => $stored['original_name'],
                    'mime' => $stored['mime'], 'size' => $stored['size'], 'visibility' => 'yonetim', 'entity_type' => 'request', 'entity_id' => $id, 'uploaded_by' => $this->userId(), 'created_at' => Database::now(),
                ]);
            }
            return $id;
        });
        $this->audit('request.create', 'request', $id, null, ['title' => $d['title'], 'category' => $d['category'], 'priority' => $d['priority']], 'Talep açıldı: ' . $d['title']);
        if ($d['assigned_to']) {
            (new NotificationService($this->app))->notifyUsers($b, [(int) $d['assigned_to']], 'Size yeni bir iş emri atandı', '#' . $id . ' ' . $d['title'] . ($d['target_date'] ? ' · hedef ' . Dates::tr($d['target_date']) : ''), 'request', 'request', $id);
        }
        $this->success('Talep kaydedildi.');
        return $this->redirectRoute('requests.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $b = $this->buildingId();
        $row = $this->db->fetch(
            'SELECT r.*, u.door_no, bl.name AS block_name, p.first_name, p.last_name, p.company_name, p.phone AS person_phone, p.email AS person_email, p.user_id AS person_user_id, a.name AS assigned_name, cu.name AS creator_name
             FROM requests r LEFT JOIN units u ON u.id = r.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN people p ON p.id = r.person_id LEFT JOIN users a ON a.id = r.assigned_to LEFT JOIN users cu ON cu.id = r.created_by
             WHERE r.id = ? AND r.building_id = ?',
            [$id, $b],
        ) ?? throw new HttpException(404, 'Talep bulunamadı.');
        $comments = $this->db->fetchAll('SELECT c.*, us.name AS user_name FROM request_comments c LEFT JOIN users us ON us.id = c.user_id WHERE c.request_id = ? ORDER BY c.id', [$id]);
        $documents = $this->db->fetchAll("SELECT * FROM documents WHERE building_id = ? AND entity_type = 'request' AND entity_id = ? ORDER BY id", [$b, $id]);
        $expense = $row['expense_id'] ? $this->db->fetch('SELECT id, amount, status, expense_date FROM expenses WHERE id = ? AND building_id = ?', [(int) $row['expense_id'], $b]) : null;
        $accounts = $this->db->fetchPairs('SELECT id, name FROM accounts WHERE building_id = ? AND is_active = 1 ORDER BY is_default DESC, name', [$b]);
        return $this->view('manager.requests.show', [
            'title' => '#' . $row['id'] . ' ' . $row['title'], 'row' => $row, 'comments' => $comments, 'documents' => $documents, 'expense' => $expense,
            'staff' => $this->staffUsers(), 'accounts' => $accounts, 'expenseCategories' => (new ExpenseService($this->app))->categoryOptions($b), 'today' => Dates::today(),
        ] + $this->lists('request_statuses', 'request_categories', 'request_priorities', 'request_visibilities'));
    }

    public function update(int $id): Response
    {
        $row = $this->findOwned('requests', $id);
        $d = $this->validate([
            'status' => 'required|in_keys:lists.request_statuses',
            'assigned_to' => 'nullable|integer',
            'priority' => 'required|in_keys:lists.request_priorities',
            'target_date' => 'nullable|date',
            'cost_amount' => 'nullable|money',
            'to_expense' => 'boolean',
            'expense_account_id' => 'nullable|integer',
            'expense_category_id' => 'nullable|integer',
            'note' => 'nullable|max:2000',
        ], ['status' => 'durum', 'assigned_to' => 'atanan', 'priority' => 'öncelik', 'target_date' => 'hedef tarih', 'cost_amount' => 'masraf', 'expense_account_id' => 'hesap', 'expense_category_id' => 'gider kategorisi', 'note' => 'not']);
        $b = $this->buildingId();
        $this->assertRefs(['unit_id' => null, 'person_id' => null, 'assigned_to' => $d['assigned_to']]);
        $cost = (int) ($d['cost_amount'] ?? 0);
        if ($cost < 0) {
            throw new DomainException('Masraf tutarı negatif olamaz.');
        }
        $new = [
            'status' => $d['status'], 'assigned_to' => $d['assigned_to'] ?: null, 'priority' => $d['priority'], 'target_date' => $d['target_date'], 'cost_amount' => $cost,
            'updated_at' => Database::now(),
        ];
        if ($d['status'] === 'tamamlandi' && $row['resolved_at'] === null) {
            $new['resolved_at'] = Database::now();
        }
        if ($d['status'] === 'iptal' && $row['closed_at'] === null) {
            $new['closed_at'] = Database::now();
        }
        if ($d['status'] === 'atandi' && !$new['assigned_to']) {
            throw new DomainException('"Atandı" durumu için bir görevli seçin.');
        }
        if ((int) $d['to_expense'] === 1 && $row['expense_id'] === null) {
            if ($cost <= 0) {
                throw new DomainException('Gidere aktarmak için masraf tutarı girin.');
            }
            if (empty($d['expense_account_id'])) {
                throw new DomainException('Gidere aktarmak için ödemenin yapıldığı kasa/banka hesabını seçin.');
            }
            $this->findOwned('accounts', (int) $d['expense_account_id']);
            $this->authorize('expenses.manage');
            $new['expense_id'] = (new ExpenseService($this->app))->create($b, [
                'category_id' => $d['expense_category_id'] ?: null, 'account_id' => (int) $d['expense_account_id'], 'expense_date' => Dates::today(),
                'amount' => $cost, 'vat_mode' => 'dahil', 'vat_rate' => 0, 'document_kind' => 'fis', 'status' => 'odendi',
                'description' => 'Talep #' . $id . ' · ' . $row['title'],
            ]);
        }
        $this->db->update('requests', $new, 'id = ?', [$id]);
        $statusChanged = $row['status'] !== $d['status'];
        $note = trim((string) ($d['note'] ?? ''));
        if ($statusChanged || $note !== '') {
            $this->db->insert('request_comments', [
                'request_id' => $id, 'user_id' => $this->userId(),
                'body' => $note !== '' ? $note : 'Durum güncellendi: ' . list_label('request_statuses', $d['status']),
                'is_internal' => 0, 'status_change' => $statusChanged ? $d['status'] : null, 'created_at' => Database::now(),
            ]);
        }
        [$o, $n] = \Aidat\Core\Audit::diff($row, $new);
        unset($o['updated_at'], $n['updated_at']);
        $this->audit('request.update', 'request', $id, $o, $n, 'Talep güncellendi: ' . $row['title'] . ($statusChanged ? ' → ' . list_label('request_statuses', $d['status']) : ''));
        $notifier = new NotificationService($this->app);
        if ($statusChanged && $row['person_id']) {
            $notifier->notifyPeople($b, [(int) $row['person_id']], 'Talebiniz güncellendi: ' . list_label('request_statuses', $d['status']), '#' . $id . ' "' . $row['title'] . '" talebinizin durumu "' . list_label('request_statuses', $d['status']) . '" olarak güncellendi.' . ($note !== '' ? "\n\n" . $note : ''), ['uygulama', 'eposta'], 'request', 'request', $id);
        }
        if ($new['assigned_to'] && (int) $new['assigned_to'] !== (int) ($row['assigned_to'] ?? 0) && (int) $new['assigned_to'] !== $this->userId()) {
            $notifier->notifyUsers($b, [(int) $new['assigned_to']], 'Size bir iş emri atandı', '#' . $id . ' ' . $row['title'], 'request', 'request', $id);
        }
        $this->success('Talep güncellendi.' . (isset($new['expense_id']) ? ' Masraf gider olarak kaydedildi.' : ''));
        return $this->redirectRoute('requests.show', ['id' => $id]);
    }

    public function comment(int $id): Response
    {
        $row = $this->findOwned('requests', $id);
        $d = $this->validate(['body' => 'required|min:2|max:2000', 'is_internal' => 'boolean'], ['body' => 'yorum']);
        $cid = $this->db->insert('request_comments', ['request_id' => $id, 'user_id' => $this->userId(), 'body' => $d['body'], 'is_internal' => (int) $d['is_internal'], 'status_change' => null, 'created_at' => Database::now()]);
        $this->audit('request.comment', 'request', $id, null, ['comment_id' => $cid, 'is_internal' => (int) $d['is_internal']], 'Talebe yorum eklendi: ' . $row['title']);
        if ((int) $d['is_internal'] === 0 && $row['person_id']) {
            (new NotificationService($this->app))->notifyPeople($this->buildingId(), [(int) $row['person_id']], 'Talebinize yanıt verildi', '#' . $id . ' "' . $row['title'] . '": ' . $d['body'], ['uygulama', 'eposta'], 'request', 'request', $id);
        }
        $this->success((int) $d['is_internal'] === 1 ? 'İç not eklendi.' : 'Yorum eklendi ve talep sahibine bildirildi.');
        return $this->redirectRoute('requests.show', ['id' => $id]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $b = $this->buildingId();
        $today = Dates::today();
        $unitRows = $this->db->fetchAll('SELECT u.id, u.door_no, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? ORDER BY bl.sort_order, bl.name, CAST(u.door_no AS INTEGER), u.door_no', [$b]);
        $units = [];
        foreach ($unitRows as $u) {
            $units[$u['block_name'] ?: 'Bloksuz'][(string) $u['id']] = 'No ' . $u['door_no'];
        }
        $people = [];
        foreach ($this->db->fetchAll('SELECT id, first_name, last_name, company_name FROM people WHERE building_id = ? ORDER BY first_name, last_name', [$b]) as $pp) {
            $people[(string) $pp['id']] = $pp['company_name'] ?: trim($pp['first_name'] . ' ' . $pp['last_name']);
        }
        $unitPerson = [];
        foreach ($this->db->fetchAll("SELECT unit_id, person_id FROM occupancies WHERE building_id = ? AND (end_date IS NULL OR end_date >= ?) ORDER BY CASE role WHEN 'kiraci' THEN 0 WHEN 'malik' THEN 1 ELSE 2 END, id", [$b, $today]) as $o) {
            $unitPerson[(int) $o['unit_id']] ??= (int) $o['person_id'];
        }
        return ['units' => $units, 'people' => $people, 'unitPerson' => $unitPerson, 'staff' => $this->staffUsers()] + $this->lists('request_categories', 'request_priorities', 'request_visibilities');
    }

    /** @return array<int, string> */
    private function staffUsers(): array
    {
        return $this->db->fetchPairs("SELECT u.id, u.name FROM users u JOIN building_users bu ON bu.user_id = u.id WHERE bu.building_id = ? AND bu.role IN ('manager','staff','accountant') AND u.is_active = 1 ORDER BY u.name", [$this->buildingId()]);
    }

    /** @param array<string, mixed> $d */
    private function assertRefs(array $d): void
    {
        if (!empty($d['unit_id'])) {
            $this->findOwned('units', (int) $d['unit_id']);
        }
        if (!empty($d['person_id'])) {
            $this->findOwned('people', (int) $d['person_id']);
        }
        if (!empty($d['assigned_to']) && !array_key_exists((int) $d['assigned_to'], $this->staffUsers())) {
            throw new DomainException('Seçilen görevli bu yapıda yetkili değil.');
        }
    }
}
