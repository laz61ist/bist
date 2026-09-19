<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Exporter;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;
use Aidat\Services\NotificationService;

/** Borçlu listesi: yaşlandırma (0-30 / 31-60 / 61-90 / 90+), dışa aktarma ve toplu hatırlatma. */
final class DebtorController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $asOf = Dates::parse($this->request->str('tarih')) ?? Dates::today();
        $blockId = $this->request->int('blok');
        $minDays = max(0, $this->request->int('gun'));
        $rows = (new ChargeService($this->app))->debtors($b, $asOf, $blockId > 0 ? $blockId : null, $minDays);
        $totals = ['total_open' => 0, 'overdue' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90p' => 0, 'current' => 0];
        foreach ($rows as $r) {
            foreach (['total_open', 'overdue', 'd30', 'd60', 'd90', 'd90p'] as $k) {
                $totals[$k] += (int) $r[$k];
            }
        }
        $totals['current'] = $totals['total_open'] - $totals['overdue'];
        $format = $this->request->str('format');
        if ($format === 'csv' || $format === 'xlsx') {
            $this->authorize('reports.export');
            return $this->export($rows, $asOf, $format);
        }
        $blocks = $this->db->fetchPairs('SELECT id, name FROM blocks WHERE building_id = ? ORDER BY sort_order, name', [$b]);
        $unitCount = $this->db->fetchInt("SELECT COUNT(*) FROM units WHERE building_id = ? AND status <> 'pasif'", [$b]);
        $lastReminder = $this->db->fetch("SELECT created_at, COUNT(*) AS cnt FROM notifications WHERE building_id = ? AND template = 'debt_reminder' GROUP BY created_at ORDER BY created_at DESC LIMIT 1", [$b]);
        return $this->view('manager.debtors.index', [
            'title' => 'Borçlu listesi', 'rows' => $rows, 'totals' => $totals, 'asOf' => $asOf, 'blocks' => $blocks, 'unitCount' => $unitCount, 'lastReminder' => $lastReminder,
            'filters' => ['tarih' => $asOf, 'blok' => $blockId, 'gun' => $minDays],
        ] + $this->lists('unit_types'));
    }

    public function remind(): Response
    {
        $b = $this->buildingId();
        $n = (new NotificationService($this->app))->sendDebtReminders($b);
        $this->audit('debtors.remind', 'building', $b, null, ['sent' => $n], 'Borç hatırlatması gönderildi: ' . $n . ' bildirim');
        if ($n === 0) {
            $this->error('Gönderilecek hatırlatma yok: vadesi geçmiş borcu olan bölümlerde kayıtlı malik/kiracı bulunamadı.');
        } else {
            $this->success($n . ' hatırlatma bildirimi oluşturuldu (uygulama + e-posta).');
        }
        return $this->redirectRoute('debtors.index');
    }

    /** @param list<array<string, mixed>> $rows */
    private function export(array $rows, string $asOf, string $format): Response
    {
        $headers = ['Blok', 'Kapı no', 'Tür', 'Sorumlu', 'En eski vade', 'Gün', 'Açık kalem', 'Toplam açık', 'Vadesi geçmiş', '1-30 gün', '31-60 gün', '61-90 gün', '90+ gün'];
        $xlsx = $format === 'xlsx' && Exporter::xlsxAvailable();
        $m = static fn ($k) => $xlsx ? (int) $k / 100 : Money::decimal((int) $k);
        $data = [];
        foreach ($rows as $r) {
            $data[] = [$r['block_name'] ?: '', $r['door_no'], list_label('unit_types', $r['unit_type']), $r['responsible'], Dates::tr((string) $r['oldest_due']), (int) $r['days'], (int) $r['open_count'], $m($r['total_open']), $m($r['overdue']), $m($r['d30']), $m($r['d60']), $m($r['d90']), $m($r['d90p'])];
        }
        $name = 'borclular-' . $asOf;
        if ($xlsx) {
            return Response::download(Exporter::xlsx($headers, $data, 'Borçlular'), $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        return Response::download(Exporter::csv($headers, $data), $name . '.csv', 'text/csv; charset=utf-8');
    }
}
