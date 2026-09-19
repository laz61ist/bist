<?php

declare(strict_types=1);

namespace Aidat\Controllers\Portal;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Response;
use Aidat\Services\BudgetService;
use Aidat\Services\ChargeService;
use Aidat\Services\LedgerService;
use Aidat\Services\NotificationService;
use Aidat\Services\PaymentService;

/**
 * Sakin alanı. Tüm sorgular, oturum açan kullanıcının seçili bağımsız bölümü ve o bölümün yapısıyla sınırlıdır.
 * Yönetim oturumundaki building_id KULLANILMAZ; yapı, bölümden türetilir.
 */
final class PortalController extends Controller
{
    private const SESSION_UNIT = 'portal_unit_id';

    /** @var array<string, mixed>|null */
    private ?array $ctx = null;

    // ------------------------------------------------------------ Bağlam

    /**
     * Kullanıcının kişi kayıtları + aktif oturumları, seçili bölüm ve yapısı.
     * @return array{user_id:int, units:array<int, array<string, mixed>>, unit:array<string, mixed>, unit_id:int, building_id:int, building:array<string, mixed>, person_ids:list<int>, person_id:int, roles:list<string>, role:string, is_owner:bool}
     */
    private function context(): array
    {
        if ($this->ctx !== null) {
            return $this->ctx;
        }
        $userId = $this->userId();
        $rows = $this->db->fetchAll(
            'SELECT o.id AS occupancy_id, o.role, o.liability AS occupancy_liability, o.person_id, o.start_date,
                    u.id AS unit_id, u.building_id, u.block_id, u.door_no, u.floor, u.type, u.gross_m2, u.status, u.liability_mode, u.late_fee_exempt,
                    bl.name AS block_name, bl.code AS block_code
             FROM occupancies o
             JOIN people p ON p.id = o.person_id
             JOIN units u ON u.id = o.unit_id
             LEFT JOIN blocks bl ON bl.id = u.block_id
             WHERE p.user_id = ? AND (o.end_date IS NULL OR o.end_date >= ?)
             ORDER BY bl.sort_order, bl.name, CAST(u.door_no AS INTEGER), u.door_no, CASE o.role WHEN \'malik\' THEN 0 WHEN \'kiraci\' THEN 1 ELSE 2 END, o.id',
            [$userId, Dates::today()],
        );
        if ($rows === []) {
            throw new AuthorizationException('Hesabınıza bağlı bir bağımsız bölüm bulunamadı. Yöneticinizle iletişime geçin.');
        }
        $units = [];
        $personIds = [];
        foreach ($rows as $r) {
            $uid = (int) $r['unit_id'];
            $personIds[] = (int) $r['person_id'];
            if (!isset($units[$uid])) {
                $units[$uid] = $r + ['roles' => [], 'person_ids' => [], 'label' => $this->unitLabel($r), 'short' => $this->unitShort($r)];
            }
            $units[$uid]['roles'][] = (string) $r['role'];
            $units[$uid]['person_ids'][] = (int) $r['person_id'];
        }
        $selected = $this->app->session()->get(self::SESSION_UNIT);
        if (!is_int($selected) || !isset($units[$selected])) {
            $selected = (int) array_key_first($units);
            $this->app->session()->set(self::SESSION_UNIT, $selected);
        }
        $unit = $units[$selected];
        $buildingId = (int) $unit['building_id'];
        $building = $this->db->fetch('SELECT * FROM buildings WHERE id = ?', [$buildingId]) ?? throw new HttpException(404, 'Yapı bulunamadı.');
        $roles = array_values(array_unique($unit['roles']));
        $role = in_array('malik', $roles, true) ? 'malik' : (in_array('kiraci', $roles, true) ? 'kiraci' : (string) ($roles[0] ?? 'oturan'));
        return $this->ctx = [
            'user_id' => $userId,
            'units' => $units,
            'unit' => $unit,
            'unit_id' => $selected,
            'building_id' => $buildingId,
            'building' => $building,
            'person_ids' => array_values(array_unique($personIds)),
            'person_id' => (int) $unit['person_ids'][0],
            'roles' => $roles,
            'role' => $role,
            'is_owner' => in_array('malik', $roles, true),
        ];
    }

    /** @param array<string, mixed> $u "A Blok · No 4" */
    private function unitLabel(array $u): string
    {
        return ($u['block_name'] ? $u['block_name'] . ' · ' : '') . 'No ' . $u['door_no'];
    }

    /** @param array<string, mixed> $u "A-4" (havale açıklaması için) */
    private function unitShort(array $u): string
    {
        $code = $u['block_code'] ?: ($u['block_name'] ? mb_substr((string) $u['block_name'], 0, 1) : '');
        return ($code !== '' ? $code . '-' : 'No ') . $u['door_no'];
    }

    /** Sakin görünümü: area=portal, yapı ve bağlam otomatik eklenir. @param array<string, mixed> $data */
    private function portalView(string $template, array $data = [], ?string $layout = null): Response
    {
        $c = $this->context();
        $data['area'] = 'portal';
        $data['title'] ??= '';
        $data['currentUser'] = $this->user();
        $data['building'] = $c['building'];
        $data['ctx'] = $c;
        return Response::html($this->app->view()->render($template, $data, $layout ?? 'layouts.app'));
    }

    private function flag(string $key): bool
    {
        return $this->app->settings()->bool($this->context()['building_id'], $key);
    }

    /** Sakin kaynaklı yazımlar için işlem izi (yapı = bölümün yapısı). @param array<string, mixed>|null $new */
    private function trail(string $action, string $entityType, ?int $entityId, ?array $new, string $summary): void
    {
        $this->app->audit()->log($action, $entityType, $entityId, null, $new, $this->context()['building_id'], $summary);
    }

    /** @return list<int> yapının yönetici kullanıcıları */
    private function managerUserIds(): array
    {
        return array_map('intval', array_column($this->db->fetchAll(
            "SELECT bu.user_id FROM building_users bu JOIN users us ON us.id = bu.user_id WHERE bu.building_id = ? AND bu.role = 'manager' AND us.is_active = 1",
            [$this->context()['building_id']],
        ), 'user_id'));
    }

    /** Süreli imzalı indirme bağlantısı (görünürlük denetimi bu controller'da yapıldıktan sonra kullanılır). */
    private function signedDocUrl(int $docId): string
    {
        return $this->route('file.signed', ['token' => $this->app->signer()->sign('doc:' . $docId, time() + 3600)]);
    }

    private function absoluteUrl(string $path): string
    {
        $base = (string) config('app.url', '');
        if ($base === '') {
            $https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';
            $base = ($https ? 'https' : 'http') . '://' . ($this->request->server['HTTP_HOST'] ?? 'localhost');
        }
        return rtrim($base, '/') . $path;
    }

    // ------------------------------------------------------------ Bölüm seçimi

    public function switchUnit(): Response
    {
        $c = $this->context();
        $unitId = $this->request->int('unit_id');
        if (!isset($c['units'][$unitId])) {
            throw new DomainException('Bu bağımsız bölüm hesabınıza bağlı değil.');
        }
        $this->app->session()->set(self::SESSION_UNIT, $unitId);
        $this->success($c['units'][$unitId]['label'] . ' seçildi.');
        return $this->back($this->route('portal.home'));
    }

    // ------------------------------------------------------------ Özet

    public function home(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        $uid = $c['unit_id'];
        $today = Dates::today();
        $charges = new ChargeService($this->app);
        $balance = $charges->unitBalance($uid);
        $open = $charges->openCharges($uid);
        $overdueCount = count(array_filter($open, static fn (array $ch) => $ch['due_date'] < $today));
        $nextDue = null;
        foreach ($open as $ch) {
            if ($ch['due_date'] >= $today) {
                $nextDue = $ch;
                break;
            }
        }
        $oldest = $open[0] ?? null;
        $lastPayment = $this->db->fetch("SELECT * FROM payments WHERE unit_id = ? AND status = 'gecerli' ORDER BY payment_date DESC, id DESC LIMIT 1", [$uid]);
        $lastCharge = $this->db->fetch("SELECT * FROM charges WHERE unit_id = ? AND status <> 'iptal' AND charge_type = 'aidat' ORDER BY period DESC, id DESC LIMIT 1", [$uid]);
        $dueDay = $this->app->settings()->int($b, 'due_day', 10);
        $nextPeriod = Dates::addMonths(Dates::currentPeriod(), 1);
        $announcements = $this->announcementQuery('a.id, a.title, a.priority, a.published_at, a.is_pinned, r.read_at', 'ORDER BY a.is_pinned DESC, a.published_at DESC LIMIT 4');
        $unread = $this->db->fetchInt(
            'SELECT COUNT(*) FROM announcements a LEFT JOIN announcement_reads r ON r.announcement_id = a.id AND r.user_id = ? WHERE ' . $this->announcementWhere($params) . ' AND r.id IS NULL',
            array_merge([$c['user_id']], $params),
        );
        $openRequests = $this->db->fetchInt(
            "SELECT COUNT(*) FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal') AND (created_by = ? OR person_id IN (" . $this->in($c['person_ids']) . '))',
            array_merge([$b, $c['user_id']], $c['person_ids']),
        );
        $openPolls = $this->db->fetchInt("SELECT COUNT(*) FROM polls p WHERE p.building_id = ? AND p.status = 'acik' AND p.starts_at <= ? AND p.ends_at >= ? AND NOT EXISTS (SELECT 1 FROM poll_votes v WHERE v.poll_id = p.id AND v.user_id = ?)", [$b, Dates::now(), Dates::now(), $c['user_id']]);
        $nextMeeting = $this->db->fetch("SELECT id, title, meeting_date, location, type FROM meetings WHERE building_id = ? AND status = 'planlandi' AND meeting_date >= ? ORDER BY meeting_date LIMIT 1", [$b, Dates::now()]);
        $currentMonth = Dates::MONTHS[(int) date('n')];
        return $this->portalView('portal.home', [
            'title' => 'Özet',
            'balance' => $balance, 'open' => $open, 'overdueCount' => $overdueCount, 'nextDue' => $nextDue, 'oldest' => $oldest,
            'lastPayment' => $lastPayment, 'lastCharge' => $lastCharge, 'dueDay' => $dueDay, 'nextPeriod' => $nextPeriod,
            'announcements' => $announcements, 'unread' => $unread, 'openRequests' => $openRequests, 'openPolls' => $openPolls, 'nextMeeting' => $nextMeeting,
            'transferNote' => $c['unit']['short'] . ' ' . $currentMonth . ' aidatı',
            'showFinance' => $this->flag('portal_show_expenses') || $this->flag('portal_show_accounts'),
        ]);
    }

    // ------------------------------------------------------------ Borçlarım

    public function charges(): Response
    {
        $c = $this->context();
        $uid = $c['unit_id'];
        $today = Dates::today();
        $period = $this->request->str('donem');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = '';
        }
        $scope = $this->request->str('durum', 'tumu');
        if (!in_array($scope, ['tumu', 'acik', 'odenen'], true)) {
            $scope = 'tumu';
        }
        $where = "unit_id = ? AND status <> 'iptal'";
        $params = [$uid];
        if ($period !== '') {
            $where .= ' AND period = ?';
            $params[] = $period;
        }
        if ($scope === 'acik') {
            $where .= " AND status IN ('odenmedi','kismi')";
        } elseif ($scope === 'odenen') {
            $where .= " AND status = 'odendi'";
        }
        $rows = $this->db->fetchAll("SELECT * FROM charges WHERE {$where} ORDER BY due_date DESC, id DESC", $params);
        // Gecikme tazminatı satırlarını ana borcun altına yerleştir
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $children = [];
        $ordered = [];
        foreach ($rows as $r) {
            $pid = $r['parent_charge_id'] !== null ? (int) $r['parent_charge_id'] : null;
            if ($pid !== null && isset($byId[$pid])) {
                $children[$pid][] = $r;
            }
        }
        foreach ($rows as $r) {
            $pid = $r['parent_charge_id'] !== null ? (int) $r['parent_charge_id'] : null;
            if ($pid !== null && isset($byId[$pid])) {
                continue;
            }
            $r['is_child'] = false;
            $ordered[] = $r;
            foreach ($children[(int) $r['id']] ?? [] as $ch) {
                $ch['is_child'] = true;
                $ordered[] = $ch;
            }
        }
        $totals = ['amount' => 0, 'paid' => 0, 'open' => 0, 'overdue' => 0];
        foreach ($rows as $r) {
            $totals['amount'] += (int) $r['amount'];
            $totals['paid'] += (int) $r['paid_amount'];
            if (in_array($r['status'], ['odenmedi', 'kismi'], true)) {
                $rem = (int) $r['amount'] - (int) $r['paid_amount'];
                $totals['open'] += $rem;
                if ($r['due_date'] < $today) {
                    $totals['overdue'] += $rem;
                }
            }
        }
        $periods = array_map('strval', array_column($this->db->fetchAll("SELECT DISTINCT period FROM charges WHERE unit_id = ? AND status <> 'iptal' ORDER BY period DESC", [$uid]), 'period'));
        $balance = (new ChargeService($this->app))->unitBalance($uid);
        $lateRule = null;
        if ($this->app->settings()->bool($c['building_id'], 'late_fee_enabled') && !(int) $c['unit']['late_fee_exempt']) {
            $lateRule = ['rate' => $this->app->settings()->get($c['building_id'], 'late_fee_rate'), 'type' => $this->app->settings()->get($c['building_id'], 'late_fee_rate_type'), 'grace' => $this->app->settings()->int($c['building_id'], 'late_fee_grace_days')];
        }
        return $this->portalView('portal.charges', [
            'title' => 'Borçlarım',
            'rows' => $ordered, 'totals' => $totals, 'periods' => $periods, 'balance' => $balance,
            'filters' => ['donem' => $period, 'durum' => $scope], 'lateRule' => $lateRule, 'today' => $today,
        ]);
    }

    // ------------------------------------------------------------ Ödemelerim

    public function payments(): Response
    {
        $c = $this->context();
        $uid = $c['unit_id'];
        $total = $this->db->fetchInt('SELECT COUNT(*) FROM payments WHERE unit_id = ?', [$uid]);
        $p = $this->paginator($total, 20);
        $rows = $this->db->fetchAll("SELECT id, payment_date, amount, method, receipt_no, status, unallocated_amount, description FROM payments WHERE unit_id = ? ORDER BY payment_date DESC, id DESC LIMIT {$p->perPage} OFFSET {$p->offset}", [$uid]);
        $year = (int) date('Y');
        $yearTotal = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$uid, $year . '-01-01', $year . '-12-31']);
        $yearCount = $this->db->fetchInt("SELECT COUNT(*) FROM payments WHERE unit_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$uid, $year . '-01-01', $year . '-12-31']);
        $advance = $this->db->fetchInt("SELECT COALESCE(SUM(unallocated_amount),0) FROM payments WHERE unit_id = ? AND status = 'gecerli'", [$uid]);
        return $this->portalView('portal.payments', [
            'title' => 'Ödemelerim ve makbuzlar',
            'rows' => $rows, 'p' => $p, 'yearTotal' => $yearTotal, 'yearCount' => $yearCount, 'year' => $year, 'advance' => $advance,
        ]);
    }

    public function receipt(int $id): Response
    {
        $c = $this->context();
        $data = (new PaymentService($this->app))->receiptData($id, $c['building_id']);
        if ($data === null || (int) $data['unit_id'] !== $c['unit_id']) {
            throw new HttpException(404, 'Makbuz bulunamadı.');
        }
        // Gizlilik: ödeyen adı yalnızca kullanıcının kendi kişi kaydıysa gösterilir
        $ownPayer = $data['person_id'] !== null && in_array((int) $data['person_id'], $c['person_ids'], true);
        $verifyUrl = $data['verify_code'] ? $this->absoluteUrl($this->route('receipt.verify', ['code' => $data['verify_code']])) : null;
        return $this->portalView('portal.receipt', [
            'title' => 'Makbuz ' . $data['receipt_no'],
            'r' => $data, 'ownPayer' => $ownPayer, 'verifyUrl' => $verifyUrl,
            'unitLabel' => $c['unit']['label'],
            'footer' => $this->app->settings()->get($c['building_id'], 'receipt_footer'),
            'signerTitle' => $this->app->settings()->get($c['building_id'], 'receipt_signer_title'),
            'backUrl' => $this->route('portal.payments'),
        ], 'layouts.print');
    }

    // ------------------------------------------------------------ Ekstre

    public function statement(): Response
    {
        $c = $this->context();
        $from = Dates::parse($this->request->str('bas')) ?? date('Y-01-01');
        $to = Dates::parse($this->request->str('bit')) ?? Dates::today();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $st = (new PaymentService($this->app))->statement($c['unit_id'], $from, $to);
        $data = ['title' => 'Hesap ekstrem', 'st' => $st, 'from' => $from, 'to' => $to, 'unitLabel' => $c['unit']['label']];
        if ($this->request->has('yazdir')) {
            return $this->portalView('portal.statement', $data + ['print' => true, 'backUrl' => $this->route('portal.statement', ['bas' => $from, 'bit' => $to])], 'layouts.print');
        }
        return $this->portalView('portal.statement', $data + ['print' => false]);
    }

    // ------------------------------------------------------------ Şeffaflık: mali durum

    public function finance(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        $showExpenses = $this->flag('portal_show_expenses');
        $showAccounts = $this->flag('portal_show_accounts');
        if (!$showExpenses && !$showAccounts) {
            return $this->portalView('portal.finance', ['title' => 'Yapının mali durumu', 'hidden' => true, 'showExpenses' => false, 'showAccounts' => false]);
        }
        $period = Dates::currentPeriod();
        $today = Dates::today();
        $labels = [];
        $serIncome = [];
        $serExpense = [];
        for ($i = 11; $i >= 0; $i--) {
            $pp = Dates::addMonths($period, -$i);
            $pf = $pp . '-01';
            $pt = date('Y-m-t', strtotime($pf));
            $labels[] = mb_substr(Dates::MONTHS[(int) substr($pp, 5, 2)], 0, 3) . ' ' . substr($pp, 2, 2);
            $serIncome[] = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM payments WHERE building_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$b, $pf, $pt])
                + $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE building_id = ? AND status = 'gecerli' AND income_date BETWEEN ? AND ?", [$b, $pf, $pt]);
            $serExpense[] = $showExpenses ? $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE building_id = ? AND status <> 'iptal' AND expense_date BETWEEN ? AND ?", [$b, $pf, $pt]) : 0;
        }
        $from = $period . '-01';
        $to = date('Y-m-t', strtotime($from));
        $month = [
            'collected' => $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM payments WHERE building_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$b, $from, $to]),
            'other' => $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE building_id = ? AND status = 'gecerli' AND income_date BETWEEN ? AND ?", [$b, $from, $to]),
            'expense' => $showExpenses ? $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE building_id = ? AND status <> 'iptal' AND expense_date BETWEEN ? AND ?", [$b, $from, $to]) : null,
            'accrued' => $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM charges WHERE building_id = ? AND period = ? AND status <> 'iptal'", [$b, $period]),
            'collectedForPeriod' => $this->db->fetchInt("SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN charges ch ON ch.id = a.charge_id JOIN payments p ON p.id = a.payment_id WHERE ch.building_id = ? AND ch.period = ? AND p.status = 'gecerli'", [$b, $period]),
        ];
        $month['rate'] = $month['accrued'] > 0 ? round($month['collectedForPeriod'] / $month['accrued'] * 100, 1) : 0.0;
        $debt = (new ChargeService($this->app))->buildingDebtSummary($b, $today);
        $unitCount = $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$b]);
        $accounts = $showAccounts ? (new LedgerService($this->app))->balances($b) : [];
        $byCategory = $showExpenses ? $this->db->fetchAll(
            "SELECT COALESCE(pc.name, ec.name, 'Kategorisiz') AS name, SUM(e.amount) AS total FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id LEFT JOIN expense_categories pc ON pc.id = ec.parent_id WHERE e.building_id = ? AND e.status <> 'iptal' AND e.expense_date BETWEEN ? AND ? GROUP BY COALESCE(pc.name, ec.name, 'Kategorisiz') ORDER BY total DESC LIMIT 6",
            [$b, date('Y-m-01', strtotime($from . ' -5 months')), $to],
        ) : [];
        return $this->portalView('portal.finance', [
            'title' => 'Yapının mali durumu', 'hidden' => false,
            'showExpenses' => $showExpenses, 'showAccounts' => $showAccounts, 'showBudget' => $this->flag('portal_show_budget'),
            'period' => $period, 'chart' => ['labels' => $labels, 'income' => $serIncome, 'expense' => $serExpense],
            'month' => $month, 'debt' => $debt, 'unitCount' => $unitCount, 'accounts' => $accounts,
            'totalBalance' => array_sum(array_column($accounts, 'balance')), 'byCategory' => $byCategory,
        ]);
    }

    // ------------------------------------------------------------ Şeffaflık: giderler

    public function expenses(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        if (!$this->flag('portal_show_expenses')) {
            return $this->portalView('portal.expenses', ['title' => 'Giderler ve belgeler', 'hidden' => true]);
        }
        $periods = array_map('strval', array_column($this->db->fetchAll("SELECT DISTINCT period FROM expenses WHERE building_id = ? AND status <> 'iptal' ORDER BY period DESC", [$b]), 'period'));
        $period = $this->request->str('donem');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = in_array(Dates::currentPeriod(), $periods, true) ? Dates::currentPeriod() : ($periods[0] ?? Dates::currentPeriod());
        }
        $showDocs = $this->flag('portal_show_expense_documents');
        $rows = $this->db->fetchAll(
            "SELECT e.id, e.expense_date, e.amount, e.vat_amount, e.description, e.document_kind, e.status, e.scope, e.document_id,
                    COALESCE(ec.name, 'Kategorisiz') AS category_name, pc.name AS parent_name, v.name AS vendor_name, bl.name AS block_name
             FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id LEFT JOIN expense_categories pc ON pc.id = ec.parent_id
             LEFT JOIN vendors v ON v.id = e.vendor_id LEFT JOIN blocks bl ON bl.id = e.block_id
             WHERE e.building_id = ? AND e.period = ? AND e.status <> 'iptal' ORDER BY e.expense_date DESC, e.id DESC",
            [$b, $period],
        );
        $docs = [];
        if ($showDocs && $rows !== []) {
            $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
            $docIds = array_values(array_filter(array_map(static fn (array $r) => $r['document_id'] !== null ? (int) $r['document_id'] : null, $rows)));
            $sql = "SELECT id, entity_id, title, original_name, mime, size FROM documents WHERE building_id = ? AND ((entity_type = 'expense' AND entity_id IN (" . $this->in($ids) . '))';
            $params = array_merge([$b], $ids);
            if ($docIds !== []) {
                $sql .= ' OR id IN (' . $this->in($docIds) . ')';
                $params = array_merge($params, $docIds);
            }
            $sql .= ') ORDER BY id';
            foreach ($this->db->fetchAll($sql, $params) as $d) {
                $key = (int) ($d['entity_id'] ?? 0);
                if ($key === 0) {
                    foreach ($rows as $r) {
                        if ((int) $r['document_id'] === (int) $d['id']) {
                            $key = (int) $r['id'];
                        }
                    }
                }
                $docs[$key][(int) $d['id']] = $d;
            }
        }
        $byCategory = [];
        $total = 0;
        foreach ($rows as $r) {
            $name = $r['parent_name'] ?: $r['category_name'];
            $byCategory[$name] = ($byCategory[$name] ?? 0) + (int) $r['amount'];
            $total += (int) $r['amount'];
        }
        arsort($byCategory);
        return $this->portalView('portal.expenses', [
            'title' => 'Giderler ve belgeler', 'hidden' => false,
            'rows' => $rows, 'docs' => $docs, 'showDocs' => $showDocs, 'period' => $period, 'periods' => $periods,
            'byCategory' => $byCategory, 'total' => $total, 'unitCount' => $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$b]),
        ]);
    }

    // ------------------------------------------------------------ Şeffaflık: bütçe

    public function budget(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        if (!$this->flag('portal_show_budget')) {
            return $this->portalView('portal.budget', ['title' => 'Bütçe / işletme projesi', 'hidden' => true]);
        }
        $budget = $this->db->fetch("SELECT * FROM budgets WHERE building_id = ? AND status IN ('onayli','kapali') ORDER BY fiscal_year DESC, version DESC LIMIT 1", [$b]);
        if ($budget === null) {
            return $this->portalView('portal.budget', ['title' => 'Bütçe / işletme projesi', 'hidden' => false, 'budget' => null]);
        }
        $svc = new BudgetService($this->app);
        $lines = $svc->actuals((int) $budget['id'], $b);
        $totals = $svc->totals((int) $budget['id'], $b);
        $expenseLines = array_values(array_filter($lines, static fn (array $l) => $l['kind'] === 'gider'));
        $incomeLines = array_values(array_filter($lines, static fn (array $l) => $l['kind'] === 'gelir'));
        $actualExpense = array_sum(array_map(static fn (array $l) => (int) $l['actual'], $expenseLines));
        $actualIncome = array_sum(array_map(static fn (array $l) => (int) $l['actual'], $incomeLines));
        $myDues = $this->db->fetch("SELECT amount, period FROM charges WHERE unit_id = ? AND charge_type = 'aidat' AND status <> 'iptal' ORDER BY period DESC, id DESC LIMIT 1", [$c['unit_id']]);
        $yearProgress = (int) $budget['fiscal_year'] === (int) date('Y') ? round((int) date('z') / 365 * 100) : ((int) $budget['fiscal_year'] < (int) date('Y') ? 100 : 0);
        $docs = $this->visibleDocs("(entity_type = 'budget' AND entity_id = ?)" . ($budget['document_id'] ? ' OR id = ?' : ''), array_values(array_filter([(int) $budget['id'], $budget['document_id'] ? (int) $budget['document_id'] : null], static fn ($v) => $v !== null)));
        return $this->portalView('portal.budget', [
            'title' => 'Bütçe / işletme projesi', 'hidden' => false,
            'budget' => $budget, 'expenseLines' => $expenseLines, 'incomeLines' => $incomeLines, 'totals' => $totals,
            'actualExpense' => $actualExpense, 'actualIncome' => $actualIncome, 'myDues' => $myDues, 'yearProgress' => $yearProgress, 'docs' => $docs,
        ]);
    }

    // ------------------------------------------------------------ Şeffaflık: borçlular

    public function debtors(): Response
    {
        $c = $this->context();
        $mode = (string) $this->app->settings()->get($c['building_id'], 'debt_visibility', 'kapi_no');
        if (!in_array($mode, ['gizli', 'kapi_no', 'isim'], true)) {
            $mode = 'gizli';
        }
        $rows = [];
        $totals = ['units' => 0, 'overdue' => 0, 'open' => 0];
        if ($mode !== 'gizli') {
            $today = Dates::today();
            foreach ((new ChargeService($this->app))->debtors($c['building_id'], $today) as $d) {
                if ((int) $d['overdue'] <= 0) {
                    continue;
                }
                $rows[] = [
                    'unit_id' => (int) $d['unit_id'],
                    'label' => ($d['block_name'] ? $d['block_name'] . ' · ' : '') . 'No ' . $d['door_no'],
                    'overdue' => (int) $d['overdue'],
                    'total_open' => (int) $d['total_open'],
                    'days' => (int) $d['days'],
                    'responsible' => $mode === 'isim' ? (string) $d['responsible'] : null,
                    'mine' => isset($c['units'][(int) $d['unit_id']]),
                ];
                $totals['units']++;
                $totals['overdue'] += (int) $d['overdue'];
                $totals['open'] += (int) $d['total_open'];
            }
        }
        $unitCount = $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$c['building_id']]);
        return $this->portalView('portal.debtors', ['title' => 'Borçlu listesi', 'mode' => $mode, 'rows' => $rows, 'totals' => $totals, 'unitCount' => $unitCount]);
    }

    // ------------------------------------------------------------ Duyurular

    /** Görünür duyuru koşulu (yapı, hedef kitle, süre). @param array<int, mixed> $params */
    private function announcementWhere(?array &$params): string
    {
        $c = $this->context();
        $now = Dates::now();
        $params = [$c['building_id'], $now, $now, (string) $c['unit']['block_id'], (string) $c['unit_id']];
        $sql = 'a.building_id = ? AND a.published_at <= ? AND (a.expires_at IS NULL OR a.expires_at >= ?) AND (a.target = \'tumu\''
            . ' OR (a.target = \'blok\' AND a.target_ref = ?) OR (a.target = \'bolum\' AND a.target_ref = ?)'
            . ' OR (a.target = \'rol\' AND a.target_ref IN (' . $this->in($c['roles']) . '))'
            . ' OR (a.target = \'kisi\' AND a.target_ref IN (' . $this->in($c['person_ids']) . ')))';
        $params = array_merge($params, $c['roles'], array_map('strval', $c['person_ids']));
        return $sql;
    }

    /** @return list<array<string, mixed>> */
    private function announcementQuery(string $select, string $tail, array $extra = []): array
    {
        $where = $this->announcementWhere($params);
        return $this->db->fetchAll(
            "SELECT {$select} FROM announcements a LEFT JOIN announcement_reads r ON r.announcement_id = a.id AND r.user_id = ? WHERE {$where} {$tail}",
            array_merge([$this->context()['user_id']], $params, $extra),
        );
    }

    public function announcements(): Response
    {
        $where = $this->announcementWhere($params);
        $total = $this->db->fetchInt("SELECT COUNT(*) FROM announcements a WHERE {$where}", $params);
        $p = $this->paginator($total, 20);
        $rows = $this->announcementQuery('a.id, a.title, a.body, a.priority, a.published_at, a.expires_at, a.is_pinned, a.target, a.document_id, r.read_at', "ORDER BY a.is_pinned DESC, a.published_at DESC LIMIT {$p->perPage} OFFSET {$p->offset}");
        $unread = count(array_filter($rows, static fn (array $r) => $r['read_at'] === null));
        return $this->portalView('portal.announcements', ['title' => 'Duyurular', 'rows' => $rows, 'p' => $p, 'unread' => $unread]);
    }

    public function announcement(int $id): Response
    {
        $c = $this->context();
        $rows = $this->announcementQuery('a.*, r.read_at', 'AND a.id = ? LIMIT 1', [$id]);
        $a = $rows[0] ?? throw new HttpException(404, 'Duyuru bulunamadı.');
        if ($a['read_at'] === null) {
            $exists = $this->db->fetch('SELECT id FROM announcement_reads WHERE announcement_id = ? AND user_id = ?', [$id, $c['user_id']]);
            if ($exists === null) {
                $this->db->insert('announcement_reads', ['announcement_id' => $id, 'user_id' => $c['user_id'], 'read_at' => Database::now()]);
            }
        }
        $attachments = [];
        $docWhere = "(entity_type = 'announcement' AND entity_id = ?)";
        $docParams = [$id];
        if ($a['document_id'] !== null) {
            $docWhere .= ' OR id = ?';
            $docParams[] = (int) $a['document_id'];
        }
        foreach ($this->db->fetchAll("SELECT id, title, original_name, mime, size, visibility FROM documents WHERE building_id = ? AND ({$docWhere}) ORDER BY id", array_merge([$c['building_id']], $docParams)) as $d) {
            // Duyurunun görünürlüğü zaten denetlendi; ek belge süreli imzalı bağlantıyla verilir
            $d['url'] = $this->signedDocUrl((int) $d['id']);
            $attachments[] = $d;
        }
        return $this->portalView('portal.announcement', ['title' => $a['title'], 'a' => $a, 'attachments' => $attachments]);
    }

    // ------------------------------------------------------------ Toplantılar

    public function meetings(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        $meetings = $this->db->fetchAll("SELECT * FROM meetings WHERE building_id = ? AND status <> 'iptal' ORDER BY meeting_date DESC", [$b]);
        $ids = array_map(static fn (array $m) => (int) $m['id'], $meetings);
        $decisions = [];
        $docs = [];
        $attendance = [];
        if ($ids !== []) {
            foreach ($this->db->fetchAll('SELECT * FROM meeting_decisions WHERE meeting_id IN (' . $this->in($ids) . ') ORDER BY meeting_id, id', $ids) as $d) {
                $decisions[(int) $d['meeting_id']][] = $d;
            }
            $docIds = array_values(array_filter(array_map(static fn (array $m) => $m['document_id'] !== null ? (int) $m['document_id'] : null, $meetings)));
            $sql = "SELECT id, entity_id, title, original_name, mime, size, visibility FROM documents WHERE building_id = ? AND ((entity_type = 'meeting' AND entity_id IN (" . $this->in($ids) . '))' . ($docIds !== [] ? ' OR id IN (' . $this->in($docIds) . ')' : '') . ')';
            foreach ($this->db->fetchAll($sql, array_merge([$b], $ids, $docIds)) as $d) {
                $vis = (string) $d['visibility'];
                if (!($vis === 'sakinler' || ($vis === 'malikler' && $c['is_owner']))) {
                    continue;
                }
                $key = (int) ($d['entity_id'] ?? 0);
                if ($key === 0) {
                    foreach ($meetings as $m) {
                        if ((int) $m['document_id'] === (int) $d['id']) {
                            $key = (int) $m['id'];
                        }
                    }
                }
                $docs[$key][] = $d;
            }
            foreach ($this->db->fetchAll('SELECT meeting_id, attended, proxy_name FROM meeting_attendances WHERE unit_id = ? AND meeting_id IN (' . $this->in($ids) . ')', array_merge([$c['unit_id']], $ids)) as $at) {
                $attendance[(int) $at['meeting_id']] = $at;
            }
        }
        $upcoming = array_values(array_filter($meetings, static fn (array $m) => in_array($m['status'], ['planlandi', 'ertelendi'], true)));
        $past = array_values(array_filter($meetings, static fn (array $m) => $m['status'] === 'yapildi'));
        usort($upcoming, static fn ($x, $y) => strcmp((string) $x['meeting_date'], (string) $y['meeting_date']));
        return $this->portalView('portal.meetings', ['title' => 'Toplantı ve kararlar', 'upcoming' => $upcoming, 'past' => $past, 'decisions' => $decisions, 'docs' => $docs, 'attendance' => $attendance]);
    }

    // ------------------------------------------------------------ Anketler

    public function polls(): Response
    {
        $c = $this->context();
        $now = Dates::now();
        $polls = $this->db->fetchAll('SELECT * FROM polls WHERE building_id = ? ORDER BY CASE WHEN status = \'acik\' AND ends_at >= ? THEN 0 ELSE 1 END, ends_at DESC', [$c['building_id'], $now]);
        $out = [];
        foreach ($polls as $poll) {
            $options = json_decode((string) $poll['options'], true);
            $options = is_array($options) ? array_values($options) : [];
            $votes = $this->db->fetchAll('SELECT user_id, unit_id, option_indexes FROM poll_votes WHERE poll_id = ?', [(int) $poll['id']]);
            $counts = array_fill(0, count($options), 0);
            $mine = null;
            $unitVoted = false;
            foreach ($votes as $v) {
                $idx = json_decode((string) $v['option_indexes'], true);
                foreach (is_array($idx) ? $idx : [] as $i) {
                    if (isset($counts[(int) $i])) {
                        $counts[(int) $i]++;
                    }
                }
                if ((int) $v['user_id'] === $c['user_id']) {
                    $mine = is_array($idx) ? array_map('intval', $idx) : [];
                }
                if ($v['unit_id'] !== null && (int) $v['unit_id'] === $c['unit_id']) {
                    $unitVoted = true;
                }
            }
            $isOpen = $poll['status'] === 'acik' && $poll['starts_at'] <= $now && $poll['ends_at'] >= $now;
            $canVote = $isOpen && $mine === null && !((int) $poll['one_vote_per_unit'] === 1 && $unitVoted);
            $poll['options_list'] = $options;
            $poll['counts'] = $counts;
            $poll['total_votes'] = count($votes);
            $poll['mine'] = $mine;
            $poll['unit_voted'] = $unitVoted;
            $poll['is_open'] = $isOpen;
            $poll['can_vote'] = $canVote;
            $poll['show_results'] = !$canVote;
            $out[] = $poll;
        }
        return $this->portalView('portal.polls', ['title' => 'Anketler', 'polls' => $out, 'now' => $now]);
    }

    public function vote(int $id): Response
    {
        $c = $this->context();
        $now = Dates::now();
        $poll = $this->db->fetch('SELECT * FROM polls WHERE id = ? AND building_id = ?', [$id, $c['building_id']]) ?? throw new HttpException(404, 'Anket bulunamadı.');
        if (!($poll['status'] === 'acik' && $poll['starts_at'] <= $now && $poll['ends_at'] >= $now)) {
            throw new DomainException('Bu anket oylamaya kapalı.');
        }
        if ($this->db->fetch('SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?', [$id, $c['user_id']]) !== null) {
            throw new DomainException('Bu ankette zaten oy kullandınız.');
        }
        if ((int) $poll['one_vote_per_unit'] === 1 && $this->db->fetch('SELECT id FROM poll_votes WHERE poll_id = ? AND unit_id = ?', [$id, $c['unit_id']]) !== null) {
            throw new DomainException($c['unit']['label'] . ' için bu ankette zaten oy kullanılmış (bölüm başına tek oy).');
        }
        $options = json_decode((string) $poll['options'], true);
        $count = is_array($options) ? count($options) : 0;
        $raw = $this->request->input('secenek');
        $chosen = [];
        foreach (is_array($raw) ? $raw : [$raw] as $v) {
            if ($v === null || $v === '' || !preg_match('/^\d+$/', (string) $v)) {
                continue;
            }
            $i = (int) $v;
            if ($i >= 0 && $i < $count) {
                $chosen[$i] = $i;
            }
        }
        $chosen = array_values($chosen);
        if ($chosen === []) {
            throw new DomainException('Lütfen bir seçenek işaretleyin.');
        }
        if ($poll['type'] === 'tek' && count($chosen) > 1) {
            throw new DomainException('Bu ankette yalnızca tek seçenek işaretlenebilir.');
        }
        $voteId = $this->db->insert('poll_votes', [
            'poll_id' => $id, 'unit_id' => $c['unit_id'], 'user_id' => $c['user_id'],
            'option_indexes' => json_encode($chosen), 'created_at' => Database::now(),
        ]);
        $this->trail('poll.vote', 'poll', $id, ['unit_id' => $c['unit_id'], 'options' => (int) $poll['is_anonymous'] === 1 ? '(gizli)' : $chosen], 'Ankette oy kullanıldı: ' . mb_substr((string) $poll['question'], 0, 80));
        $this->success('Oyunuz kaydedildi. Teşekkürler.');
        return $this->redirectRoute('portal.polls');
    }

    // ------------------------------------------------------------ Talepler

    /** Kullanıcının kendi talebi koşulu. @param array<int, mixed> $params */
    private function ownRequestWhere(?array &$params): string
    {
        $c = $this->context();
        $params = array_merge([$c['user_id']], $c['person_ids']);
        return '(r.created_by = ? OR r.person_id IN (' . $this->in($c['person_ids']) . '))';
    }

    public function requests(): Response
    {
        $c = $this->context();
        $b = $c['building_id'];
        $scope = $this->request->str('kapsam', 'benim');
        if (!in_array($scope, ['benim', 'ortak'], true)) {
            $scope = 'benim';
        }
        $own = $this->ownRequestWhere($ownParams);
        if ($scope === 'benim') {
            $where = "r.building_id = ? AND {$own}";
            $params = array_merge([$b], $ownParams);
        } else {
            $where = "r.building_id = ? AND r.visibility = 'herkes' AND NOT {$own}";
            $params = array_merge([$b], $ownParams);
        }
        $total = $this->db->fetchInt("SELECT COUNT(*) FROM requests r WHERE {$where}", $params);
        $p = $this->paginator($total, 20);
        $rows = $this->db->fetchAll(
            "SELECT r.id, r.title, r.category, r.priority, r.status, r.visibility, r.created_at, r.updated_at, r.target_date, r.unit_id, u.door_no, bl.name AS block_name,
                    (SELECT COUNT(*) FROM request_comments rc WHERE rc.request_id = r.id AND rc.is_internal = 0) AS comment_count
             FROM requests r LEFT JOIN units u ON u.id = r.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             WHERE {$where} ORDER BY CASE WHEN r.status IN ('tamamlandi','iptal') THEN 1 ELSE 0 END, r.id DESC LIMIT {$p->perPage} OFFSET {$p->offset}",
            $params,
        );
        $ownOpen = $this->db->fetchInt("SELECT COUNT(*) FROM requests r WHERE r.building_id = ? AND {$own} AND r.status NOT IN ('tamamlandi','iptal')", array_merge([$b], $ownParams));
        $publicCount = $this->db->fetchInt("SELECT COUNT(*) FROM requests r WHERE r.building_id = ? AND r.visibility = 'herkes' AND NOT {$own}", array_merge([$b], $ownParams));
        return $this->portalView('portal.requests', ['title' => 'Taleplerim', 'rows' => $rows, 'p' => $p, 'scope' => $scope, 'ownOpen' => $ownOpen, 'publicCount' => $publicCount]);
    }

    public function requestCreate(): Response
    {
        return $this->portalView('portal.request-form', ['title' => 'Yeni talep'] + $this->lists('request_categories'));
    }

    public function requestStore(): Response
    {
        $c = $this->context();
        $d = $this->validate([
            'category' => 'required|in_keys:lists.request_categories',
            'title' => 'required|min:5|max:150',
            'description' => 'nullable|max:3000',
            'location' => 'nullable|max:120',
            'priority' => 'required|in:dusuk,normal,yuksek',
        ], ['category' => 'kategori', 'title' => 'başlık', 'description' => 'açıklama', 'location' => 'konum', 'priority' => 'öncelik'], $this->route('portal.requests_create'));
        $b = $c['building_id'];
        $photo = $this->request->file('photo');
        $stored = $photo !== null ? $this->app->uploads()->store($photo, 'talepler/' . $b, ['image/jpeg', 'image/png', 'application/pdf']) : null;
        $now = Database::now();
        $id = $this->db->transaction(function () use ($c, $d, $b, $stored, $now): int {
            $id = $this->db->insert('requests', [
                'building_id' => $b, 'unit_id' => $c['unit_id'], 'person_id' => $c['person_id'], 'created_by' => $c['user_id'],
                'category' => $d['category'], 'title' => $d['title'], 'description' => $d['description'], 'location' => $d['location'],
                'priority' => $d['priority'], 'status' => 'yeni', 'visibility' => 'ozel', 'cost_amount' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($stored !== null) {
                $this->db->insert('documents', [
                    'building_id' => $b, 'category' => 'diger', 'title' => 'Talep eki · ' . mb_substr((string) $d['title'], 0, 100),
                    'file_path' => $stored['path'], 'original_name' => $stored['original_name'], 'mime' => $stored['mime'], 'size' => $stored['size'],
                    'visibility' => 'yonetim', 'entity_type' => 'request', 'entity_id' => $id, 'uploaded_by' => $c['user_id'], 'created_at' => $now,
                ]);
            }
            return $id;
        });
        $this->trail('request.create', 'request', $id, ['title' => $d['title'], 'category' => $d['category'], 'priority' => $d['priority'], 'unit_id' => $c['unit_id']], 'Sakin talebi oluşturuldu: ' . $d['title']);
        $managers = $this->managerUserIds();
        if ($managers !== []) {
            (new NotificationService($this->app))->notifyUsers($b, $managers, 'Yeni talep · ' . $c['unit']['label'], sprintf('%s (%s, %s öncelik): %s', $d['title'], list_label('request_categories', $d['category']), list_label('request_priorities', $d['priority']), mb_substr((string) ($d['description'] ?? ''), 0, 200)), 'request_new', 'request', $id);
        }
        $this->success('Talebiniz alındı. Yönetim en kısa sürede dönüş yapacaktır.');
        return $this->redirectRoute('portal.request', ['id' => $id]);
    }

    public function requestShow(int $id): Response
    {
        $c = $this->context();
        $own = $this->ownRequestWhere($ownParams);
        $r = $this->db->fetch(
            "SELECT r.*, u.door_no, bl.name AS block_name, ({$own}) AS is_own FROM requests r LEFT JOIN units u ON u.id = r.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id WHERE r.id = ? AND r.building_id = ? AND ({$own} OR r.visibility = 'herkes')",
            array_merge($ownParams, [$id, $c['building_id']], $ownParams),
        ) ?? throw new HttpException(404, 'Talep bulunamadı.');
        $isOwn = (int) $r['is_own'] === 1;
        $comments = $this->db->fetchAll(
            "SELECT rc.id, rc.body, rc.status_change, rc.created_at, rc.user_id, bu.role AS member_role
             FROM request_comments rc LEFT JOIN building_users bu ON bu.user_id = rc.user_id AND bu.building_id = ?
             WHERE rc.request_id = ? AND rc.is_internal = 0 ORDER BY rc.id",
            [$c['building_id'], $id],
        );
        foreach ($comments as &$cm) {
            $cm['author'] = (int) $cm['user_id'] === $c['user_id'] ? 'Siz' : (in_array($cm['member_role'], ['manager', 'accountant', 'staff', 'auditor'], true) ? 'Yönetim' : 'Sakin');
            $cm['is_me'] = (int) $cm['user_id'] === $c['user_id'];
        }
        unset($cm);
        $attachments = [];
        if ($isOwn) {
            foreach ($this->db->fetchAll("SELECT id, title, original_name, mime, size FROM documents WHERE building_id = ? AND entity_type = 'request' AND entity_id = ? ORDER BY id", [$c['building_id'], $id]) as $d) {
                $d['url'] = $this->signedDocUrl((int) $d['id']);
                $attachments[] = $d;
            }
        }
        return $this->portalView('portal.request-show', ['title' => $r['title'], 'r' => $r, 'isOwn' => $isOwn, 'comments' => $comments, 'attachments' => $attachments]);
    }

    public function requestComment(int $id): Response
    {
        $c = $this->context();
        $own = $this->ownRequestWhere($ownParams);
        $r = $this->db->fetch("SELECT r.* FROM requests r WHERE r.id = ? AND r.building_id = ? AND {$own}", array_merge([$id, $c['building_id']], $ownParams)) ?? throw new HttpException(404, 'Talep bulunamadı.');
        if ($r['status'] === 'iptal') {
            throw new DomainException('İptal edilmiş talebe yorum eklenemez.');
        }
        $d = $this->validate(['body' => 'required|min:2|max:2000'], ['body' => 'mesaj'], $this->route('portal.request', ['id' => $id]));
        $cid = $this->db->insert('request_comments', ['request_id' => $id, 'user_id' => $c['user_id'], 'body' => $d['body'], 'is_internal' => 0, 'created_at' => Database::now()]);
        $this->db->update('requests', ['updated_at' => Database::now()], 'id = ?', [$id]);
        $this->trail('request.comment', 'request', $id, ['comment_id' => $cid], 'Sakin yorumu eklendi: ' . $r['title']);
        $managers = $this->managerUserIds();
        if ($managers !== []) {
            (new NotificationService($this->app))->notifyUsers($c['building_id'], $managers, 'Talebe yeni mesaj · ' . $c['unit']['label'], $r['title'] . ': ' . mb_substr((string) $d['body'], 0, 200), 'request_comment', 'request', $id);
        }
        $this->success('Mesajınız eklendi.');
        return $this->redirectRoute('portal.request', ['id' => $id]);
    }

    // ------------------------------------------------------------ Belgeler

    /** Görünürlük kuralına uyan belgeler. @param array<int, mixed> $params */
    private function visibleDocs(string $extraWhere = '', array $params = []): array
    {
        $c = $this->context();
        $vis = $c['is_owner'] ? "('sakinler','malikler')" : "('sakinler')";
        $sql = "SELECT id, category, title, original_name, mime, size, visibility, entity_type, created_at FROM documents WHERE building_id = ? AND visibility IN {$vis}";
        if ($extraWhere !== '') {
            $sql .= " AND ({$extraWhere})";
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        return $this->db->fetchAll($sql, array_merge([$c['building_id']], $params));
    }

    public function documents(): Response
    {
        $c = $this->context();
        $docs = $this->visibleDocs();
        $order = array_keys(list_options('document_categories'));
        $groups = [];
        foreach ($docs as $d) {
            $groups[(string) $d['category']][] = $d;
        }
        uksort($groups, static function (string $x, string $y) use ($order): int {
            $ix = array_search($x, $order, true);
            $iy = array_search($y, $order, true);
            return ($ix === false ? 999 : $ix) <=> ($iy === false ? 999 : $iy);
        });
        return $this->portalView('portal.documents', ['title' => 'Belgeler', 'groups' => $groups, 'count' => count($docs), 'isOwner' => $c['is_owner']]);
    }

    // ------------------------------------------------------------ Sayaçlar

    public function meters(): Response
    {
        $c = $this->context();
        $meters = $this->db->fetchAll('SELECT * FROM meters WHERE unit_id = ? AND building_id = ? ORDER BY is_active DESC, type, id', [$c['unit_id'], $c['building_id']]);
        foreach ($meters as &$m) {
            $readings = $this->db->fetchAll('SELECT period, reading_date, value, previous_value, consumption FROM meter_readings WHERE meter_id = ? ORDER BY period DESC LIMIT 24', [(int) $m['id']]);
            $m['readings'] = $readings;
            $chart = array_reverse(array_slice($readings, 0, 12));
            $m['chart'] = ['labels' => array_map(static fn (array $r) => mb_substr(Dates::MONTHS[(int) substr((string) $r['period'], 5, 2)], 0, 3) . ' ' . substr((string) $r['period'], 2, 2), $chart), 'data' => array_map(static fn (array $r) => round((float) ($r['consumption'] ?? 0), 2), $chart)];
            $cons = array_map(static fn (array $r) => (float) ($r['consumption'] ?? 0), array_slice($readings, 0, 12));
            $m['avg'] = $cons !== [] ? array_sum($cons) / count($cons) : 0.0;
            $m['last'] = $readings[0] ?? null;
            // Yapı ortalaması (aynı tür, son dönem) — kimlik içermez
            $m['building_avg'] = $m['last'] !== null ? (float) ($this->db->fetchColumn('SELECT AVG(r.consumption) FROM meter_readings r JOIN meters mm ON mm.id = r.meter_id WHERE mm.building_id = ? AND mm.type = ? AND r.period = ? AND mm.unit_id IS NOT NULL', [$c['building_id'], $m['type'], $m['last']['period']]) ?? 0) : null;
        }
        unset($m);
        return $this->portalView('portal.meters', ['title' => 'Sayaçlarım', 'meters' => $meters]);
    }

    // ------------------------------------------------------------ Yardımcı

    /** @param list<mixed> $values "?, ?, ?" — boş listede hiçbir satırla eşleşmeyen yer tutucu */
    private function in(array $values): string
    {
        return $values === [] ? 'NULL' : implode(',', array_fill(0, count($values), '?'));
    }
}
