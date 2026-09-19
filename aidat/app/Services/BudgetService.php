<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\DomainException;

/** İşletme projesi / bütçe (KMK md. 37): kalemler, önerilen aidat, planlanan-gerçekleşen. */
final class BudgetService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @return array{expense: int, income: int, net: int, reserve: int, need: int, units: int, monthly_per_unit: int} */
    public function totals(int $budgetId, int $buildingId): array
    {
        $b = $this->db->fetch('SELECT * FROM budgets WHERE id = ? AND building_id = ?', [$budgetId, $buildingId]) ?? throw new DomainException('Bütçe bulunamadı.');
        $expense = $this->db->fetchInt("SELECT COALESCE(SUM(annual_amount),0) FROM budget_lines WHERE budget_id = ? AND kind = 'gider'", [$budgetId]);
        $income = $this->db->fetchInt("SELECT COALESCE(SUM(annual_amount),0) FROM budget_lines WHERE budget_id = ? AND kind = 'gelir'", [$budgetId]);
        $reserve = (int) $b['reserve_fund_amount'] + (int) round($expense * (float) $b['reserve_fund_percent'] / 100);
        $need = max(0, $expense - $income + $reserve);
        $units = $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$buildingId]);
        $monthly = $units > 0 ? (int) ceil($need / 12 / $units) : 0;
        return ['expense' => $expense, 'income' => $income, 'net' => $income - $expense, 'reserve' => $reserve, 'need' => $need, 'units' => $units, 'monthly_per_unit' => $monthly];
    }

    public function refreshSuggested(int $budgetId, int $buildingId): void
    {
        $t = $this->totals($budgetId, $buildingId);
        $this->db->update('budgets', ['monthly_dues_suggested' => $t['monthly_per_unit'], 'updated_at' => Database::now()], 'id = ?', [$budgetId]);
    }

    /** Planlanan-gerçekleşen: gider kalemleri kategori bazında. @return list<array<string, mixed>> */
    public function actuals(int $budgetId, int $buildingId): array
    {
        $b = $this->db->fetch('SELECT * FROM budgets WHERE id = ? AND building_id = ?', [$budgetId, $buildingId]) ?? throw new DomainException('Bütçe bulunamadı.');
        $year = (int) $b['fiscal_year'];
        $from = $year . '-01-01';
        $to = $year . '-12-31';
        $lines = $this->db->fetchAll('SELECT * FROM budget_lines WHERE budget_id = ? ORDER BY kind, sort_order, id', [$budgetId]);
        $out = [];
        foreach ($lines as $l) {
            if ($l['kind'] === 'gider') {
                $actual = $l['category_id']
                    ? $this->db->fetchInt("SELECT COALESCE(SUM(e.amount),0) FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.building_id = ? AND e.status <> 'iptal' AND e.expense_date BETWEEN ? AND ? AND (e.category_id = ? OR c.parent_id = ?)", [$buildingId, $from, $to, (int) $l['category_id'], (int) $l['category_id']])
                    : $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE building_id = ? AND status <> 'iptal' AND budget_line_id = ? AND expense_date BETWEEN ? AND ?", [$buildingId, (int) $l['id'], $from, $to]);
            } else {
                $actual = $l['category_id']
                    ? $this->db->fetchInt("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE building_id = ? AND status = 'gecerli' AND category_id = ? AND income_date BETWEEN ? AND ?", [$buildingId, (int) $l['category_id'], $from, $to])
                    : 0;
                if ($l['category_id'] === null && stripos((string) $l['name'], 'aidat') !== false) {
                    $actual = $this->db->fetchInt("SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a JOIN payments p ON p.id = a.payment_id JOIN charges c ON c.id = a.charge_id WHERE p.building_id = ? AND p.status = 'gecerli' AND c.charge_type = 'aidat' AND p.payment_date BETWEEN ? AND ?", [$buildingId, $from, $to]);
                }
            }
            $l['actual'] = $actual;
            $l['diff'] = (int) $l['annual_amount'] - $actual;
            $l['pct'] = (int) $l['annual_amount'] > 0 ? round($actual / (int) $l['annual_amount'] * 100, 1) : 0;
            $out[] = $l;
        }
        return $out;
    }
}
