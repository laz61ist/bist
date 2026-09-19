<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;
use Aidat\Services\LedgerService;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $period = $this->request->str('donem', Dates::currentPeriod());
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = Dates::currentPeriod();
        }
        $from = $period . '-01';
        $to = date('Y-m-t', strtotime($from));
        $today = Dates::today();
        $charges = new ChargeService($this->app);
        $ledger = new LedgerService($this->app);

        $accrued = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM charges WHERE building_id = ? AND period = ? AND status <> 'iptal'", [$b, $period]);
        $collectedForPeriod = $this->db->fetchInt("SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN charges c ON c.id = a.charge_id JOIN payments p ON p.id = a.payment_id WHERE c.building_id = ? AND c.period = ? AND p.status = 'gecerli'", [$b, $period]);
        $collectedInPeriod = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM payments WHERE building_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$b, $from, $to]);
        $expensesInPeriod = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE building_id = ? AND status <> 'iptal' AND expense_date BETWEEN ? AND ?", [$b, $from, $to]);
        $otherIncome = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE building_id = ? AND status = 'gecerli' AND income_date BETWEEN ? AND ?", [$b, $from, $to]);
        $debt = $charges->buildingDebtSummary($b, $today);
        $rate = $accrued > 0 ? round($collectedForPeriod / $accrued * 100, 1) : 0.0;
        $unitCount = $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$b]);

        // 12 aylık seri: tahsilat vs gider
        $labels = [];
        $serCollected = [];
        $serExpense = [];
        $serAccrued = [];
        for ($i = 11; $i >= 0; $i--) {
            $p = Dates::addMonths($period, -$i);
            $pf = $p . '-01';
            $pt = date('Y-m-t', strtotime($pf));
            $labels[] = mb_substr(Dates::MONTHS[(int) substr($p, 5, 2)], 0, 3) . ' ' . substr($p, 2, 2);
            $serCollected[] = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM payments WHERE building_id = ? AND status = 'gecerli' AND payment_date BETWEEN ? AND ?", [$b, $pf, $pt]);
            $serExpense[] = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE building_id = ? AND status <> 'iptal' AND expense_date BETWEEN ? AND ?", [$b, $pf, $pt]);
            $serAccrued[] = $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM charges WHERE building_id = ? AND period = ? AND status <> 'iptal'", [$b, $p]);
        }
        $byCategory = $this->db->fetchAll(
            "SELECT COALESCE(pc.name, c.name, 'Kategorisiz') AS name, SUM(e.amount) AS total FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id LEFT JOIN expense_categories pc ON pc.id = c.parent_id WHERE e.building_id = ? AND e.status <> 'iptal' AND e.expense_date BETWEEN ? AND ? GROUP BY COALESCE(pc.name, c.name, 'Kategorisiz') ORDER BY total DESC LIMIT 6",
            [$b, date('Y-m-01', strtotime($from . ' -5 months')), $to],
        );
        $blocks = $this->db->fetchAll(
            "SELECT COALESCE(bl.name, 'Blok yok') AS name, COUNT(DISTINCT u.id) AS units,
                    COALESCE(SUM(CASE WHEN c.status IN ('odenmedi','kismi') AND c.due_date < ? THEN c.amount - c.paid_amount ELSE 0 END),0) AS overdue,
                    COUNT(DISTINCT CASE WHEN c.status IN ('odenmedi','kismi') AND c.due_date < ? THEN u.id END) AS debtor_units
             FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN charges c ON c.unit_id = u.id WHERE u.building_id = ? GROUP BY bl.id, bl.name, bl.sort_order ORDER BY bl.sort_order, bl.name",
            [$today, $today, $b],
        );
        $recentPayments = $this->db->fetchAll('SELECT p.*, u.door_no FROM payments p LEFT JOIN units u ON u.id = p.unit_id WHERE p.building_id = ? ORDER BY p.id DESC LIMIT 8', [$b]);
        $recentExpenses = $this->db->fetchAll('SELECT e.*, c.name AS category_name FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.building_id = ? ORDER BY e.id DESC LIMIT 6', [$b]);
        $upcoming = $this->db->fetchAll("SELECT e.*, c.name AS category_name FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.building_id = ? AND e.status IN ('planlandi','kismi','gecikti') ORDER BY COALESCE(e.due_date, e.expense_date) LIMIT 6", [$b]);
        $contractsEnding = $this->db->fetchAll("SELECT c.*, v.name AS vendor_name FROM contracts c JOIN vendors v ON v.id = c.vendor_id WHERE c.building_id = ? AND c.status = 'aktif' AND c.end_date IS NOT NULL AND c.end_date <= ? ORDER BY c.end_date LIMIT 5", [$b, date('Y-m-d', strtotime('+45 days'))]);
        $openRequests = $this->db->fetchInt("SELECT COUNT(*) FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal')", [$b]);
        $urgentRequests = $this->db->fetchAll("SELECT * FROM requests WHERE building_id = ? AND status NOT IN ('tamamlandi','iptal') AND priority IN ('acil','yuksek') ORDER BY id DESC LIMIT 5", [$b]);
        $pendingPlans = $this->db->fetchInt("SELECT COUNT(*) FROM charge_plans WHERE building_id = ? AND status IN ('taslak','onaylandi')", [$b]);
        $pendingImports = $this->db->fetchInt("SELECT COUNT(*) FROM bank_import_rows r JOIN bank_imports i ON i.id = r.import_id WHERE i.building_id = ? AND r.status = 'bekliyor'", [$b]);
        $topDebtors = array_slice($charges->debtors($b, $today), 0, 6);
        $accounts = $ledger->balances($b);
        $announcements = $this->db->fetchAll('SELECT id, title, priority, published_at FROM announcements WHERE building_id = ? AND (expires_at IS NULL OR expires_at >= ?) ORDER BY is_pinned DESC, published_at DESC LIMIT 4', [$b, Dates::now()]);
        $periodClosed = $this->db->fetch("SELECT 1 FROM fiscal_periods WHERE building_id = ? AND period = ? AND status = 'kapali'", [$b, $period]) !== null;

        return $this->view('manager.dashboard.index', [
            'title' => 'Genel bakış',
            'period' => $period, 'periodClosed' => $periodClosed,
            'kpi' => compact('accrued', 'collectedForPeriod', 'collectedInPeriod', 'expensesInPeriod', 'otherIncome', 'rate', 'unitCount'),
            'debt' => $debt,
            'chart' => ['labels' => $labels, 'collected' => $serCollected, 'expense' => $serExpense, 'accrued' => $serAccrued],
            'byCategory' => $byCategory, 'blocks' => $blocks,
            'recentPayments' => $recentPayments, 'recentExpenses' => $recentExpenses, 'upcoming' => $upcoming, 'contractsEnding' => $contractsEnding,
            'openRequests' => $openRequests, 'urgentRequests' => $urgentRequests, 'pendingPlans' => $pendingPlans, 'pendingImports' => $pendingImports,
            'topDebtors' => $topDebtors, 'accounts' => $accounts, 'announcements' => $announcements,
            'totalBalance' => array_sum(array_column($accounts, 'balance')),
        ]);
    }
}
