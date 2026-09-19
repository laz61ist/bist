<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/** Hesaplar arası virman: çift taraflı hareket atomik oluşur; masraf ayrı çıkış olarak yazılır. */
final class TransferService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @param array<string, mixed> $d */
    public function create(int $buildingId, array $d): int
    {
        $from = (int) $d['from_account_id'];
        $to = (int) $d['to_account_id'];
        $amount = (int) $d['amount'];
        $fee = (int) ($d['fee_amount'] ?? 0);
        if ($from === $to) {
            throw new DomainException('Kaynak ve hedef hesap aynı olamaz.');
        }
        if ($amount <= 0) {
            throw new DomainException('Transfer tutarı sıfırdan büyük olmalı.');
        }
        (new PeriodService($this->app))->assertOpen($buildingId, (string) $d['transfer_date']);
        $ledger = new LedgerService($this->app);
        return $this->db->transaction(function () use ($buildingId, $d, $from, $to, $amount, $fee, $ledger): int {
            $id = $this->db->insert('transfers', [
                'building_id' => $buildingId, 'from_account_id' => $from, 'to_account_id' => $to,
                'transfer_date' => $d['transfer_date'], 'amount' => $amount, 'fee_amount' => $fee,
                'kind' => $d['kind'] ?? 'virman', 'reference_no' => ($d['reference_no'] ?? null) ?: null, 'description' => ($d['description'] ?? null) ?: null,
                'status' => 'gecerli', 'created_by' => $this->app->auth()->id(), 'created_at' => Database::now(), 'updated_at' => Database::now(),
            ]);
            $desc = 'Virman #' . $id . (!empty($d['description']) ? ' · ' . $d['description'] : '');
            $ledger->record($buildingId, $from, (string) $d['transfer_date'], 'out', $amount, 'transfer', $id, $desc);
            $ledger->record($buildingId, $to, (string) $d['transfer_date'], 'in', $amount, 'transfer', $id, $desc);
            if ($fee > 0) {
                $ledger->record($buildingId, $from, (string) $d['transfer_date'], 'out', $fee, 'transfer', $id, 'Transfer masrafı #' . $id);
            }
            $this->app->audit()->log('transfer.create', 'transfer', $id, null, ['from' => $from, 'to' => $to, 'amount' => $amount, 'fee' => $fee], $buildingId, 'Virman: ' . Money::format($amount));
            return $id;
        });
    }

    public function cancel(int $transferId, int $buildingId, string $reason): void
    {
        $t = $this->db->fetch('SELECT * FROM transfers WHERE id = ? AND building_id = ?', [$transferId, $buildingId]) ?? throw new DomainException('Transfer bulunamadı.');
        if ($t['status'] !== 'gecerli') {
            throw new DomainException('Transfer zaten iptal.');
        }
        $today = Dates::today();
        (new PeriodService($this->app))->assertOpen($buildingId, $today);
        $this->db->transaction(function () use ($transferId, $buildingId, $reason, $today): void {
            (new LedgerService($this->app))->reverse('transfer', $transferId, $today, 'Virman iptali · ' . $reason);
            $this->db->update('transfers', ['status' => 'iptal', 'cancelled_at' => Database::now(), 'cancel_reason' => $reason, 'updated_at' => Database::now()], 'id = ?', [$transferId]);
            $this->app->audit()->log('transfer.cancel', 'transfer', $transferId, ['status' => 'gecerli'], ['status' => 'iptal', 'reason' => $reason], $buildingId, 'Virman iptal edildi');
        });
    }
}
