<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;

/** Makbuz seri/sıra: yapı ve mali yıl içinde benzersiz. */
final class ReceiptService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @return array{no: string, seq: int, year: int} İşlem içinde çağrılmalı. */
    public function next(int $buildingId, string $date): array
    {
        $settings = $this->app->settings();
        $prefix = strtoupper(trim((string) $settings->get($buildingId, 'receipt_prefix', 'MKB'))) ?: 'MKB';
        $resetYearly = $settings->bool($buildingId, 'receipt_reset_yearly');
        $year = $resetYearly ? (int) substr($date, 0, 4) : 0;
        $row = $this->db->fetch('SELECT id, last_seq FROM receipt_sequences WHERE building_id = ? AND year = ?', [$buildingId, $year]);
        if ($row === null) {
            $this->db->insert('receipt_sequences', ['building_id' => $buildingId, 'year' => $year, 'last_seq' => 0]);
            $row = $this->db->fetch('SELECT id, last_seq FROM receipt_sequences WHERE building_id = ? AND year = ?', [$buildingId, $year]);
        }
        $seq = (int) $row['last_seq'] + 1;
        $this->db->update('receipt_sequences', ['last_seq' => $seq], 'id = ? AND last_seq = ?', [(int) $row['id'], (int) $row['last_seq']]);
        $no = $resetYearly
            ? sprintf('%s-%d-%06d', $prefix, $year, $seq)
            : sprintf('%s-%07d', $prefix, $seq);
        return ['no' => $no, 'seq' => $seq, 'year' => $year];
    }

    public function verifyCode(int $paymentId, int $buildingId): string
    {
        return $this->app->signer()->sign('receipt:' . $paymentId . ':' . $buildingId, PHP_INT_MAX >> 1);
    }

    /** @return array{payment_id: int, building_id: int}|null */
    public function decodeVerifyCode(string $code): ?array
    {
        $payload = $this->app->signer()->verify($code);
        if ($payload === null || !preg_match('/^receipt:(\d+):(\d+)$/', $payload, $m)) {
            return null;
        }
        return ['payment_id' => (int) $m[1], 'building_id' => (int) $m[2]];
    }
}
