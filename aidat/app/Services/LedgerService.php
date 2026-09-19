<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Exceptions\DomainException;

/** Kasa/banka hareket defteri. Her para hareketi buraya yazılır; bakiye buradan türetilir. */
final class LedgerService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    public function record(int $buildingId, int $accountId, string $date, string $direction, int $amount, string $refType, ?int $refId, ?string $description): int
    {
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new DomainException('Geçersiz hareket yönü.');
        }
        if ($amount <= 0) {
            throw new DomainException('Hareket tutarı sıfırdan büyük olmalı.');
        }
        $acc = $this->db->fetch('SELECT id, building_id, is_active FROM accounts WHERE id = ?', [$accountId]);
        if ($acc === null || (int) $acc['building_id'] !== $buildingId) {
            throw new DomainException('Kasa/banka hesabı bu yapıya ait değil.');
        }
        return $this->db->insert('ledger_entries', [
            'building_id' => $buildingId,
            'account_id' => $accountId,
            'entry_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'created_by' => $this->app->auth()->id(),
            'created_at' => Database::now(),
        ]);
    }

    /** Verilen referansın tüm hareketlerini ters kayıtla kapatır. */
    public function reverse(string $refType, int $refId, string $date, string $description): int
    {
        $rows = $this->db->fetchAll('SELECT * FROM ledger_entries WHERE ref_type = ? AND ref_id = ?', [$refType, $refId]);
        $n = 0;
        foreach ($rows as $r) {
            $this->db->insert('ledger_entries', [
                'building_id' => $r['building_id'],
                'account_id' => $r['account_id'],
                'entry_date' => $date,
                'direction' => $r['direction'] === 'in' ? 'out' : 'in',
                'amount' => $r['amount'],
                'ref_type' => $refType . '_reversal',
                'ref_id' => $refId,
                'description' => mb_substr($description, 0, 255),
                'created_by' => $this->app->auth()->id(),
                'created_at' => Database::now(),
            ]);
            $n++;
        }
        return $n;
    }

    public function balance(int $accountId, ?string $asOf = null): int
    {
        $acc = $this->db->fetch('SELECT opening_balance FROM accounts WHERE id = ?', [$accountId]);
        if ($acc === null) {
            return 0;
        }
        $sql = "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) FROM ledger_entries WHERE account_id = ?";
        $params = [$accountId];
        if ($asOf !== null) {
            $sql .= ' AND entry_date <= ?';
            $params[] = $asOf;
        }
        return (int) $acc['opening_balance'] + $this->db->fetchInt($sql, $params);
    }

    /** @return list<array<string, mixed>> hesaplar + bakiye */
    public function balances(int $buildingId, bool $onlyActive = true): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.*, COALESCE((SELECT SUM(CASE WHEN l.direction = 'in' THEN l.amount ELSE -l.amount END) FROM ledger_entries l WHERE l.account_id = a.id), 0) AS movement
             FROM accounts a WHERE a.building_id = ?" . ($onlyActive ? ' AND a.is_active = 1' : '') . ' ORDER BY a.is_default DESC, a.type, a.name',
            [$buildingId],
        );
        foreach ($rows as &$r) {
            $r['balance'] = (int) $r['opening_balance'] + (int) $r['movement'];
        }
        return $rows;
    }

    public function totalBalance(int $buildingId): int
    {
        return array_sum(array_column($this->balances($buildingId), 'balance'));
    }

    /** @return array{opening: int, rows: list<array<string, mixed>>, closing: int} */
    public function statement(int $accountId, string $from, string $to): array
    {
        $opening = $this->balance($accountId, date('Y-m-d', strtotime($from . ' -1 day')));
        $rows = $this->db->fetchAll(
            'SELECT * FROM ledger_entries WHERE account_id = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date, id',
            [$accountId, $from, $to],
        );
        $running = $opening;
        foreach ($rows as &$r) {
            $running += $r['direction'] === 'in' ? (int) $r['amount'] : -(int) $r['amount'];
            $r['running'] = $running;
        }
        return ['opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    public function defaultAccountId(int $buildingId, ?string $type = null): ?int
    {
        $sql = 'SELECT id FROM accounts WHERE building_id = ? AND is_active = 1';
        $params = [$buildingId];
        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY is_default DESC, id LIMIT 1';
        $id = $this->db->fetchColumn($sql, $params);
        return $id === null ? null : (int) $id;
    }
}
