<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Exporter;
use Aidat\Core\Money;
use Aidat\Core\Response;

/** Makbuz defteri: sıra numarasına göre kesintisiz liste, atlama (boşluk) tespiti, yıl/durum filtresi, dışa aktarım. */
final class ReceiptController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $years = array_map('intval', array_column($this->db->fetchAll('SELECT DISTINCT receipt_year FROM payments WHERE building_id = ? AND receipt_no IS NOT NULL ORDER BY receipt_year DESC', [$b]), 'receipt_year'));
        $year = $this->request->has('yil') ? $this->request->int('yil', -1) : ($years[0] ?? -1);
        $status = $this->request->str('durum');
        $q = trim($this->request->str('q'));

        $where = 'p.building_id = ? AND p.receipt_no IS NOT NULL';
        $params = [$b];
        if ($year >= 0) {
            $where .= ' AND p.receipt_year = ?';
            $params[] = $year;
        }
        if ($status !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (p.receipt_no LIKE ? OR u.door_no = ?)';
            array_push($params, '%' . $q . '%', $q);
        }
        $from = 'FROM payments p LEFT JOIN units u ON u.id = p.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id LEFT JOIN accounts a ON a.id = p.account_id LEFT JOIN people per ON per.id = p.person_id LEFT JOIN users col ON col.id = p.collected_by';
        $select = "SELECT p.id, p.receipt_no, p.receipt_year, p.receipt_seq, p.payment_date, p.payment_time, p.amount, p.method, p.status, p.unit_id, p.cancel_reason,
                          u.door_no, bl.name AS block_name, a.name AS account_name, col.name AS collector_name,
                          COALESCE(per.company_name, TRIM(COALESCE(per.first_name, '') || ' ' || COALESCE(per.last_name, ''))) AS payer_name,
                          (SELECT MAX(p2.receipt_seq) FROM payments p2 WHERE p2.building_id = p.building_id AND p2.receipt_year = p.receipt_year AND p2.receipt_seq < p.receipt_seq) AS prev_seq";
        $order = 'ORDER BY p.receipt_year, p.receipt_seq, p.id';

        $format = $this->request->str('format');
        if ($format === 'csv' || $format === 'xlsx') {
            return $this->export($this->annotate($this->db->fetchAll("{$select} {$from} WHERE {$where} {$order}", $params)), $format);
        }

        $total = $this->db->fetchInt("SELECT COUNT(*) {$from} WHERE {$where}", $params);
        $p = $this->paginator($total, 50);
        $rows = $this->annotate($this->db->fetchAll("{$select} {$from} WHERE {$where} {$order} LIMIT {$p->perPage} OFFSET {$p->offset}", $params));
        $sum = $this->db->fetch("SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(CASE WHEN p.status = 'gecerli' THEN p.amount ELSE 0 END), 0) AS valid_total,
                    SUM(CASE WHEN p.status = 'gecerli' THEN 1 ELSE 0 END) AS valid_count,
                    COALESCE(SUM(CASE WHEN p.status <> 'gecerli' THEN p.amount ELSE 0 END), 0) AS cancelled_total,
                    SUM(CASE WHEN p.status <> 'gecerli' THEN 1 ELSE 0 END) AS cancelled_count,
                    MIN(p.receipt_seq) AS first_seq, MAX(p.receipt_seq) AS last_seq
                {$from} WHERE {$where}", $params) ?? [];
        // Boşluk sayısı: yıl içindeki beklenen adet − mevcut adet (durum filtresinden bağımsız)
        $gapCount = null;
        if ($year >= 0) {
            $seqStats = $this->db->fetch('SELECT COUNT(*) AS cnt, MIN(receipt_seq) AS mn, MAX(receipt_seq) AS mx FROM payments WHERE building_id = ? AND receipt_year = ? AND receipt_no IS NOT NULL', [$b, $year]) ?? ['cnt' => 0, 'mn' => 0, 'mx' => 0];
            $gapCount = (int) $seqStats['cnt'] > 0 ? max(0, (int) $seqStats['mx'] - (int) $seqStats['cnt']) : 0; // seri 1'den başlar
        }
        return $this->view('manager.receipts.index', [
            'title' => 'Makbuz defteri',
            'rows' => $rows,
            'p' => $p,
            'years' => $years,
            'filters' => ['yil' => $year, 'durum' => $status, 'q' => $q],
            'summary' => [
                'count' => (int) ($sum['cnt'] ?? 0),
                'valid_total' => (int) ($sum['valid_total'] ?? 0),
                'valid_count' => (int) ($sum['valid_count'] ?? 0),
                'cancelled_total' => (int) ($sum['cancelled_total'] ?? 0),
                'cancelled_count' => (int) ($sum['cancelled_count'] ?? 0),
                'first_seq' => (int) ($sum['first_seq'] ?? 0),
                'last_seq' => (int) ($sum['last_seq'] ?? 0),
                'gaps' => $gapCount,
            ],
            'prefix' => (string) ($this->setting('receipt_prefix') ?? 'MKB'),
            'resetYearly' => in_array($this->setting('receipt_reset_yearly'), ['1', 'true', 'on'], true),
            'xlsx' => Exporter::xlsxAvailable(),
        ] + $this->lists('payment_statuses', 'payment_methods'));
    }

    /** Sıra atlaması: önceki mevcut sıra ile fark 1'den büyükse boşluk vardır. @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function annotate(array $rows): array
    {
        foreach ($rows as &$r) {
            $seq = (int) $r['receipt_seq'];
            $prev = $r['prev_seq'] === null ? 0 : (int) $r['prev_seq'];
            $r['gap'] = $seq - $prev > 1 ? $seq - $prev - 1 : 0;
            $r['gap_from'] = $prev + 1;
            $r['gap_to'] = $seq - 1;
        }
        unset($r);
        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    private function export(array $rows, string $format): Response
    {
        $isXlsx = $format === 'xlsx' && Exporter::xlsxAvailable();
        $headers = ['Sıra', 'Makbuz No', 'Yıl', 'Tarih', 'Bölüm', 'Ödeyen', 'Yöntem', 'Hesap', 'Tutar', 'Durum', 'Tahsil eden', 'Atlama', 'İptal gerekçesi'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (int) $r['receipt_seq'], $r['receipt_no'], (int) $r['receipt_year'] ?: '', tr_date($r['payment_date']),
                $r['door_no'] ? (($r['block_name'] ? $r['block_name'] . ' ' : '') . 'No ' . $r['door_no']) : '',
                trim((string) $r['payer_name']), list_label('payment_methods', $r['method']), $r['account_name'],
                $isXlsx ? (int) $r['amount'] / 100 : Money::decimal($r['amount']),
                list_label('payment_statuses', $r['status']), $r['collector_name'],
                $r['gap'] > 0 ? ($r['gap_from'] === $r['gap_to'] ? (string) $r['gap_from'] : $r['gap_from'] . '–' . $r['gap_to']) . ' eksik' : '',
                $r['cancel_reason'],
            ];
        }
        $name = 'makbuz-defteri-' . date('Ymd-Hi');
        if ($isXlsx) {
            return Response::download(Exporter::xlsx($headers, $data, 'Makbuz defteri'), $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        return Response::download(Exporter::csv($headers, $data), $name . '.csv', 'text/csv; charset=utf-8');
    }
}
