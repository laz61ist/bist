<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Response;
use Aidat\Services\LateFeeService;

/** Gecikme tazminatı: kural özeti, tarih bazlı önizleme ve uygulama (alt borç açar / günceller). */
final class LateFeeController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $svc = new LateFeeService($this->app);
        $asOf = Dates::parse($this->request->str('tarih')) ?? Dates::today();
        $unitId = $this->request->int('bolum');
        $rule = $svc->rule($b);
        $rows = $svc->preview($b, $asOf, $unitId > 0 ? $unitId : null);
        $totals = ['base' => 0, 'accrued' => 0, 'already' => 0, 'delta' => 0, 'positive' => 0];
        foreach ($rows as $r) {
            $totals['base'] += (int) $r['base'];
            $totals['accrued'] += (int) $r['accrued'];
            $totals['already'] += (int) $r['already'];
            $totals['delta'] += (int) $r['delta'];
            if ((int) $r['delta'] > 0) {
                $totals['positive']++;
            }
        }
        $units = [];
        foreach ($this->db->fetchAll("SELECT u.id, u.door_no, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? AND u.status <> 'pasif' ORDER BY bl.sort_order, bl.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no", [$b]) as $u) {
            $units[$u['block_name'] ?: 'Blok yok'][(int) $u['id']] = 'No ' . $u['door_no'];
        }
        $lastApply = $this->db->fetch("SELECT a.created_at, a.new_values, us.name AS user_name FROM audit_logs a LEFT JOIN users us ON us.id = a.user_id WHERE a.building_id = ? AND a.action = 'latefee.apply' ORDER BY a.id DESC LIMIT 1", [$b]);
        if ($lastApply !== null) {
            $lastApply['data'] = json_decode((string) $lastApply['new_values'], true) ?: [];
        }
        return $this->view('manager.latefee.index', [
            'title' => 'Gecikme tazminatı', 'rule' => $rule, 'rows' => $rows, 'totals' => $totals, 'asOf' => $asOf, 'unitId' => $unitId, 'units' => $units, 'lastApply' => $lastApply,
        ] + $this->lists('late_fee_rate_types', 'late_fee_start_rules', 'charge_types'));
    }

    public function apply(): Response
    {
        $b = $this->buildingId();
        $d = $this->validate(['as_of' => 'required|date', 'unit_id' => 'nullable|integer'], ['as_of' => 'hesap tarihi', 'unit_id' => 'bölüm'], $this->route('latefee.index'));
        $asOf = (string) $d['as_of'];
        $unitId = ($d['unit_id'] ?? null) ? (int) $d['unit_id'] : null;
        if ($unitId !== null) {
            $this->findOwned('units', $unitId);
        }
        $rule = (new LateFeeService($this->app))->rule($b);
        if (!$rule['enabled']) {
            $this->error('Gecikme tazminatı bu yapı için kapalı. Ayarlar sayfasından etkinleştirin.');
            return $this->redirectRoute('latefee.index', ['tarih' => $asOf, 'bolum' => $unitId]);
        }
        $r = (new LateFeeService($this->app))->apply($b, $asOf, $unitId);
        if ($r['created'] + $r['updated'] === 0) {
            $this->error('Uygulanacak fark yok: seçilen tarihe kadar birikmiş tazminatların tamamı zaten tahakkuk etmiş.');
        } else {
            $this->success(sprintf('Gecikme tazminatı uygulandı: %d yeni kayıt, %d güncelleme, toplam %s.', $r['created'], $r['updated'], money($r['amount'])));
        }
        return $this->redirectRoute('latefee.index', ['tarih' => $asOf, 'bolum' => $unitId]);
    }
}
