<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;

/** Periyodik giderleri (kapıcı maaşı, asansör bakımı…) dönem için gider kaydına çevirir. */
final class RecurringExpenseService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    public function generate(string $period, ?int $buildingId = null): int
    {
        $sql = 'SELECT * FROM recurring_expenses WHERE is_active = 1 AND start_period <= ? AND (end_period IS NULL OR end_period >= ?)';
        $params = [$period, $period];
        if ($buildingId !== null) {
            $sql .= ' AND building_id = ?';
            $params[] = $buildingId;
        }
        $n = 0;
        $expenses = new ExpenseService($this->app);
        foreach ($this->db->fetchAll($sql, $params) as $r) {
            $step = match ($r['frequency']) { 'uc_aylik' => 3, 'yillik' => 12, default => 1 };
            $months = (int) substr($period, 0, 4) * 12 + (int) substr($period, 5, 2) - ((int) substr((string) $r['start_period'], 0, 4) * 12 + (int) substr((string) $r['start_period'], 5, 2));
            if ($months < 0 || $months % $step !== 0) {
                continue;
            }
            if ($r['last_generated_period'] !== null && $r['last_generated_period'] >= $period) {
                continue;
            }
            $exists = $this->db->fetchInt('SELECT COUNT(*) FROM expenses WHERE recurring_id = ? AND period = ?', [(int) $r['id'], $period]);
            if ($exists > 0) {
                continue;
            }
            $date = Dates::dayOfPeriod($period, (int) $r['day_of_month']);
            try {
                $expenses->create((int) $r['building_id'], [
                    'category_id' => $r['category_id'],
                    'vendor_id' => $r['vendor_id'],
                    'account_id' => $r['account_id'],
                    'expense_date' => $date,
                    'due_date' => $date,
                    'period' => $period,
                    'amount' => (int) $r['amount'],
                    'vat_mode' => 'dahil',
                    'vat_rate' => 0,
                    'document_kind' => 'diger',
                    'description' => $r['title'] . ' · ' . Dates::period($period),
                    'status' => ((int) $r['auto_paid'] === 1 && $r['account_id']) ? 'odendi' : 'planlandi',
                    'recurring_id' => (int) $r['id'],
                ]);
                $this->db->update('recurring_expenses', ['last_generated_period' => $period, 'updated_at' => Database::now()], 'id = ?', [(int) $r['id']]);
                $n++;
            } catch (DomainException) {
                // kapalı dönem vb. — atla
            }
        }
        return $n;
    }
}
