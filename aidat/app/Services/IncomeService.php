<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/** Aidat dışı gelirler (kira, reklam, faiz, bağış). Defter girişi + iptalde ters kayıt. */
final class IncomeService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @param array<string, mixed> $d */
    public function create(int $buildingId, array $d): int
    {
        if ((int) $d['amount'] <= 0) {
            throw new DomainException('Gelir tutarı sıfırdan büyük olmalı.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $d['income_date']);
        return $this->db->transaction(function () use ($buildingId, $d): int {
            $id = $this->db->insert('incomes', [
                'building_id' => $buildingId,
                'category_id' => ($d['category_id'] ?? null) ?: null,
                'account_id' => (int) $d['account_id'],
                'income_date' => $d['income_date'],
                'period' => ($d['period'] ?? null) ?: substr((string) $d['income_date'], 0, 7),
                'amount' => (int) $d['amount'],
                'description' => ($d['description'] ?? null) ?: null,
                'document_no' => ($d['document_no'] ?? null) ?: null,
                'status' => 'gecerli',
                'created_by' => $this->app->auth()->id(),
                'created_at' => Database::now(),
                'updated_at' => Database::now(),
            ]);
            (new LedgerService($this->app))->record($buildingId, (int) $d['account_id'], (string) $d['income_date'], 'in', (int) $d['amount'], 'income', $id, 'Gelir: ' . ($d['description'] ?? ''));
            $this->app->audit()->log('income.create', 'income', $id, null, ['amount' => $d['amount'], 'description' => $d['description'] ?? null], $buildingId, 'Gelir kaydedildi: ' . Money::format((int) $d['amount']));
            return $id;
        });
    }

    public function cancel(int $incomeId, int $buildingId, string $reason): void
    {
        $row = $this->db->fetch('SELECT * FROM incomes WHERE id = ? AND building_id = ?', [$incomeId, $buildingId]) ?? throw new DomainException('Gelir bulunamadı.');
        if ($row['status'] !== 'gecerli') {
            throw new DomainException('Gelir zaten iptal.');
        }
        $today = Dates::today();
        (new PeriodService($this->app))->assertOpen($buildingId, $today);
        $this->db->transaction(function () use ($incomeId, $buildingId, $reason, $today, $row): void {
            (new LedgerService($this->app))->reverse('income', $incomeId, $today, 'Gelir iptali · ' . $reason);
            $this->db->update('incomes', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => $reason, 'cancelled_by' => $this->app->auth()->id(), 'updated_at' => Database::now()], 'id = ?', [$incomeId]);
            $this->app->audit()->log('income.cancel', 'income', $incomeId, ['status' => 'gecerli'], ['status' => 'iptal', 'reason' => $reason], $buildingId, 'Gelir iptal edildi: ' . Money::format((int) $row['amount']));
        });
    }
}
