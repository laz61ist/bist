<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Core\Money;
use Aidat\Core\Response;
use Aidat\Services\ChargeService;
use InvalidArgumentException;

/**
 * Tahakkuk planları: taslak → onaylandı → işlendi / iptal.
 * Önizleme sunucu tarafında hesaplanır (ChargeService::distribute); Alpine yalnızca canlı yeniden hesap içindir.
 */
final class PlanController extends Controller
{
    private const RULES = [
        'name' => 'required|max:120',
        'charge_type' => 'required|in_keys:lists.charge_types',
        'block_id' => 'nullable|integer',
        'fee_group_id' => 'nullable|integer',
        'period' => 'required|period',
        'due_date' => 'required|date',
        'recurrence' => 'required|in_keys:lists.recurrences',
        'repeat_until' => 'nullable|period',
        'distribution' => 'required|in_keys:lists.distributions',
        'total_amount' => 'nullable|money',
        'unit_amount' => 'nullable|money',
        'liability' => 'required|in:malik,kiraci,paylasimli,bolum',
        'vat_mode' => 'required|in_keys:lists.vat_modes',
        'vat_rate' => 'nullable|numeric|min:0|max:100',
        'description' => 'nullable|max:2000',
        'plan_id' => 'nullable|integer',
    ];
    private const LABELS = [
        'name' => 'plan adı', 'charge_type' => 'borç türü', 'block_id' => 'blok', 'fee_group_id' => 'aidat grubu', 'period' => 'dönem', 'due_date' => 'vade tarihi',
        'recurrence' => 'tekrar', 'repeat_until' => 'bitiş dönemi', 'distribution' => 'dağıtım', 'total_amount' => 'toplam tutar', 'unit_amount' => 'birim tutar',
        'liability' => 'borç sorumluluğu', 'vat_mode' => 'KDV', 'vat_rate' => 'KDV oranı', 'description' => 'açıklama',
    ];

    public function index(): Response
    {
        $b = $this->buildingId();
        $status = $this->request->str('durum');
        $counts = ['' => 0];
        foreach ($this->db->fetchAll('SELECT status, COUNT(*) AS c FROM charge_plans WHERE building_id = ? GROUP BY status', [$b]) as $r) {
            $counts[(string) $r['status']] = (int) $r['c'];
            $counts[''] += (int) $r['c'];
        }
        $where = 'p.building_id = ?';
        $params = [$b];
        if ($status !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        $total = $this->db->fetchInt("SELECT COUNT(*) FROM charge_plans p WHERE {$where}", $params);
        $p = $this->paginator($total, 30);
        $rows = $this->db->fetchAll(
            "SELECT p.*, b.name AS block_name, g.name AS fee_group_name,
                    (SELECT COUNT(*) FROM charge_plan_lines l WHERE l.plan_id = p.id AND l.included = 1) AS line_count,
                    (SELECT COALESCE(SUM(l.amount), 0) FROM charge_plan_lines l WHERE l.plan_id = p.id AND l.included = 1) AS line_total,
                    (SELECT COUNT(*) FROM charges c WHERE c.plan_id = p.id) AS charge_count
             FROM charge_plans p LEFT JOIN blocks b ON b.id = p.block_id LEFT JOIN fee_groups g ON g.id = p.fee_group_id
             WHERE {$where}
             ORDER BY CASE p.status WHEN 'taslak' THEN 0 WHEN 'onaylandi' THEN 1 WHEN 'islendi' THEN 2 ELSE 3 END, p.period DESC, p.id DESC
             LIMIT {$p->perPage} OFFSET {$p->offset}",
            $params,
        );
        return $this->view('manager.plans.index', ['title' => 'Tahakkuk planları', 'rows' => $rows, 'p' => $p, 'counts' => $counts, 'filters' => ['durum' => $status]] + $this->lists('plan_statuses', 'charge_types', 'recurrences', 'distributions'));
    }

    public function create(): Response
    {
        $b = $this->buildingId();
        $row = null;
        $planId = null;
        $source = $this->request->int('duzenle') ?: $this->request->int('kopya');
        if ($source > 0) {
            $row = $this->findOwned('charge_plans', $source);
            $row['group_amounts'] = $row['group_amounts'] ? (json_decode((string) $row['group_amounts'], true) ?: []) : [];
            if ($this->request->int('duzenle') > 0) {
                if ($row['status'] !== 'taslak') {
                    throw new DomainException('Yalnızca taslak planlar düzenlenebilir.');
                }
                $planId = (int) $row['id'];
            } else {
                $row['name'] .= ' (kopya)';
            }
        }
        $dueDay = max(1, min(28, (int) ($this->setting('due_day', '10') ?? 10)));
        $defaults = [
            'period' => Dates::currentPeriod(),
            'due_date' => Dates::dayOfPeriod(Dates::currentPeriod(), $dueDay),
        ];
        return $this->view('manager.plans.form', ['title' => $planId ? 'Planı düzenle' : 'Yeni tahakkuk planı', 'row' => $row, 'planId' => $planId, 'defaults' => $defaults] + $this->formData($b));
    }

    /** Sunucu tarafı önizleme: satırlar, toplamlar ve yuvarlama farkı. */
    public function preview(): Response
    {
        $b = $this->buildingId();
        $d = $this->validatePlan();
        try {
            $calc = $this->compute($b, $d);
        } catch (DomainException $e) {
            $this->error($e->getMessage());
            $this->app->session()->flash('old', $this->request->body);
            return $this->redirectRoute('plans.create');
        }
        return $this->view('manager.plans.preview', ['title' => 'Plan önizleme · ' . $d['name'], 'd' => $d] + $calc + $this->formData($b));
    }

    public function store(): Response
    {
        $b = $this->buildingId();
        $d = $this->validatePlan();
        $calc = $this->compute($b, $d);
        if ($calc['lines'] === []) {
            throw new DomainException('Plana dahil edilen bölüm yok. En az bir bölüm seçin.');
        }
        if ($calc['sum'] <= 0) {
            throw new DomainException('Plan toplamı sıfır. Tutarları kontrol edin.');
        }
        $planId = ($d['plan_id'] ?? null) ? (int) $d['plan_id'] : null;
        if ($planId !== null) {
            $this->findOwned('charge_plans', $planId);
        }
        $data = $d;
        $data['total_amount'] = $calc['plan']['total_amount'];
        $data['unit_amount'] = $calc['plan']['unit_amount'];
        $data['group_amounts'] = $d['distribution'] === 'grup' ? $calc['plan']['group_amounts'] : null;
        if ($data['group_amounts'] === null) {
            unset($data['group_amounts']);
        }
        $id = (new ChargeService($this->app))->savePlan($b, $data, $calc['lines'], $planId);
        $this->success($planId ? 'Taslak plan güncellendi.' : 'Plan taslak olarak kaydedildi. Onayladıktan sonra işleyebilirsiniz.');
        return $this->redirectRoute('plans.show', ['id' => $id]);
    }

    public function show(int $id): Response
    {
        $b = $this->buildingId();
        $plan = $this->db->fetch(
            'SELECT p.*, b.name AS block_name, g.name AS fee_group_name, uc.name AS created_by_name, ua.name AS approved_by_name
             FROM charge_plans p LEFT JOIN blocks b ON b.id = p.block_id LEFT JOIN fee_groups g ON g.id = p.fee_group_id
             LEFT JOIN users uc ON uc.id = p.created_by LEFT JOIN users ua ON ua.id = p.approved_by
             WHERE p.id = ? AND p.building_id = ?',
            [$id, $b],
        ) ?? throw new HttpException(404, 'Plan bulunamadı.');
        $plan['group_amounts_arr'] = $plan['group_amounts'] ? (json_decode((string) $plan['group_amounts'], true) ?: []) : [];
        $lines = $this->db->fetchAll(
            'SELECT l.*, u.door_no, u.type AS unit_type, u.gross_m2, u.net_m2, u.land_share, u.fee_group_id, b.name AS block_name, g.name AS fee_group_name
             FROM charge_plan_lines l JOIN units u ON u.id = l.unit_id LEFT JOIN blocks b ON b.id = u.block_id LEFT JOIN fee_groups g ON g.id = u.fee_group_id
             WHERE l.plan_id = ? ORDER BY b.sort_order, b.name, u.sort_order, CAST(u.door_no AS INTEGER), u.door_no',
            [$id],
        );
        $included = array_values(array_filter($lines, static fn (array $l) => (int) $l['included'] === 1));
        $sum = array_sum(array_map(static fn (array $l) => (int) $l['amount'], $included));
        $periods = $this->db->fetchAll(
            "SELECT period, COUNT(*) AS cnt, SUM(CASE WHEN status <> 'iptal' THEN amount ELSE 0 END) AS total, SUM(CASE WHEN status <> 'iptal' THEN paid_amount ELSE 0 END) AS paid, SUM(CASE WHEN status = 'iptal' THEN 1 ELSE 0 END) AS cancelled
             FROM charges WHERE plan_id = ? GROUP BY period ORDER BY period",
            [$id],
        );
        $step = match ((string) $plan['recurrence']) { 'uc_aylik' => 3, 'yillik' => 12, default => 1 };
        $nextPeriod = $plan['last_generated_period'] ? Dates::addMonths((string) $plan['last_generated_period'], $step) : (string) $plan['period'];
        $groups = $this->db->fetchPairs('SELECT id, name FROM fee_groups WHERE building_id = ? ORDER BY name', [$b]);
        $timeline = $this->db->fetchAll("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'charge_plan' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 20", [$id]);
        return $this->view('manager.plans.show', [
            'title' => $plan['name'], 'plan' => $plan, 'lines' => $lines, 'included' => $included, 'sum' => $sum, 'periods' => $periods,
            'nextPeriod' => $nextPeriod, 'groups' => $groups, 'timeline' => $timeline,
        ] + $this->lists('plan_statuses', 'charge_types', 'recurrences', 'distributions', 'vat_modes', 'liability_modes', 'unit_types'));
    }

    public function approve(int $id): Response
    {
        (new ChargeService($this->app))->approvePlan($id, $this->buildingId());
        $this->success('Plan onaylandı. Şimdi işleyerek borç kayıtlarını oluşturabilirsiniz.');
        return $this->redirectRoute('plans.show', ['id' => $id]);
    }

    public function process(int $id): Response
    {
        $period = null;
        if ($this->request->has('period') && trim($this->request->str('period')) !== '') {
            $d = $this->validate(['period' => 'required|period'], ['period' => 'dönem'], $this->route('plans.show', ['id' => $id]));
            $period = (string) $d['period'];
        }
        $n = (new ChargeService($this->app))->processPlan($id, $this->buildingId(), $period);
        $plan = $this->findOwned('charge_plans', $id);
        $this->success($n . ' borç kaydı oluşturuldu (' . tr_period((string) $plan['last_generated_period']) . '). Mevcut avanslar otomatik mahsup edildi.');
        return $this->redirectRoute('plans.show', ['id' => $id]);
    }

    public function cancel(int $id): Response
    {
        $d = $this->validate(['reason' => 'required|min:3|max:500'], ['reason' => 'gerekçe'], $this->route('plans.show', ['id' => $id]));
        (new ChargeService($this->app))->cancelPlan($id, $this->buildingId(), (string) $d['reason']);
        $this->success('Plan iptal edildi. Daha önce üretilmiş borç kayıtları etkilenmez; gerekiyorsa tek tek iptal edin.');
        return $this->redirectRoute('plans.show', ['id' => $id]);
    }

    // ------------------------------------------------------------ yardımcılar

    /** @return array<string, mixed> doğrulanmış plan alanları (+ group_amounts kuruş dizisi) */
    private function validatePlan(): array
    {
        $back = $this->route('plans.create');
        $d = $this->validate(self::RULES, self::LABELS, $back);
        $errors = [];
        if (in_array($d['charge_type'], ['gecikme', 'devir'], true)) {
            $errors['charge_type'] = 'Gecikme tazminatı ve devir bakiyesi planla oluşturulamaz.';
        }
        if ($d['recurrence'] !== 'tek' && !empty($d['repeat_until']) && $d['repeat_until'] < $d['period']) {
            $errors['repeat_until'] = 'Bitiş dönemi başlangıç döneminden önce olamaz.';
        }
        $dist = (string) $d['distribution'];
        if (in_array($dist, ['esit', 'm2', 'arsa_payi'], true) && (int) ($d['total_amount'] ?? 0) <= 0) {
            $errors['total_amount'] = 'Bu dağıtım için toplam tutar gerekli.';
        }
        if ($dist === 'sabit' && (int) ($d['unit_amount'] ?? 0) <= 0) {
            $errors['unit_amount'] = 'Sabit dağıtım için birim tutar gerekli.';
        }
        $groups = [];
        if ($dist === 'grup') {
            $raw = $this->request->input('group_amounts');
            foreach (is_array($raw) ? $raw : [] as $gid => $val) {
                try {
                    $k = Money::parse($val);
                } catch (InvalidArgumentException) {
                    $errors['group_amounts'] = 'Grup tutarlarından biri geçersiz (örn. 1.250,50).';
                    continue;
                }
                if ($k > 0) {
                    $groups[(int) $gid] = $k;
                }
            }
            if ($groups === [] && !isset($errors['group_amounts'])) {
                $errors['group_amounts'] = 'En az bir aidat grubu için tutar girin.';
            }
        }
        if ($d['vat_mode'] !== 'yok' && (float) ($d['vat_rate'] ?? 0) <= 0) {
            $errors['vat_rate'] = 'KDV oranı girin (örn. 20).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $this->request->all(), $back);
        }
        $d['group_amounts'] = $groups;
        $d['vat_rate'] = $d['vat_mode'] === 'yok' ? 0.0 : (float) ($d['vat_rate'] ?? 0);
        if ($d['recurrence'] === 'tek') {
            $d['repeat_until'] = null;
        }
        return $d;
    }

    /**
     * Dağıtımı hesaplar. KDV hariç girildiyse tutarlar brütleştirilir.
     * @param array<string, mixed> $d
     * @return array{plan: array<string, mixed>, units: list<array<string, mixed>>, lines: array<int, array{amount: int, share: float|null}>, includedIds: list<int>, manual: array<int, int>, sum: int, count: int, roundingDiff: int, vatAdded: int}
     */
    private function compute(int $b, array $d): array
    {
        $svc = new ChargeService($this->app);
        $units = $svc->planUnits($b, (int) ($d['block_id'] ?? 0) ?: null, (int) ($d['fee_group_id'] ?? 0) ?: null);
        $allIds = array_map(static fn (array $u) => (int) $u['id'], $units);
        $includedIds = $allIds;
        if ($this->request->has('included')) {
            $inc = $this->request->input('included');
            $includedIds = array_values(array_intersect($allIds, array_map('intval', is_array($inc) ? $inc : [])));
        }
        $manual = [];
        $rawManual = $this->request->input('manual');
        foreach (is_array($rawManual) ? $rawManual : [] as $uid => $val) {
            try {
                $manual[(int) $uid] = max(0, Money::parse($val));
            } catch (InvalidArgumentException) {
                throw new DomainException('Manuel tutarlardan biri geçersiz (No ' . e((string) $uid) . ').');
            }
        }
        $factor = $d['vat_mode'] === 'haric' && (float) $d['vat_rate'] > 0 ? 1 + (float) $d['vat_rate'] / 100 : 1.0;
        $gross = static fn (int $k): int => (int) round($k * $factor);
        $plan = [
            'distribution' => $d['distribution'],
            'total_amount' => $gross((int) ($d['total_amount'] ?? 0)),
            'unit_amount' => $gross((int) ($d['unit_amount'] ?? 0)),
            'group_amounts' => array_map($gross, $d['group_amounts'] ?? []),
        ];
        $manualGross = array_map($gross, $manual);
        $lines = $includedIds === [] ? [] : $svc->distribute($plan, $units, $manualGross, $includedIds);
        $sum = array_sum(array_map(static fn (array $l) => (int) $l['amount'], $lines));
        $roundingDiff = 0;
        if (in_array($d['distribution'], ['esit', 'm2', 'arsa_payi'], true) && $lines !== []) {
            $sumW = array_sum(array_map(static fn (array $l) => (float) ($l['share'] ?? 0), $lines));
            $floorSum = 0;
            foreach ($lines as $l) {
                $floorSum += $sumW > 0 ? (int) floor($plan['total_amount'] * ((float) $l['share'] / $sumW)) : 0;
            }
            $roundingDiff = $sum - $floorSum;
        }
        $vatAdded = 0;
        if ($factor > 1.0) {
            $net = (int) round($sum / $factor);
            $vatAdded = $sum - $net;
        }
        return ['plan' => $plan, 'units' => $units, 'lines' => $lines, 'includedIds' => $includedIds, 'manual' => $manual, 'sum' => $sum, 'count' => count($lines), 'roundingDiff' => $roundingDiff, 'vatAdded' => $vatAdded];
    }

    /** @return array<string, mixed> */
    private function formData(int $b): array
    {
        $chargeTypes = array_filter($this->app->config()->get('lists.charge_types', []), static fn ($v, $k) => !in_array($k, ['gecikme', 'devir'], true), ARRAY_FILTER_USE_BOTH);
        return [
            'blocks' => $this->db->fetchPairs('SELECT id, name FROM blocks WHERE building_id = ? ORDER BY sort_order, name', [$b]),
            'groups' => $this->db->fetchPairs('SELECT id, name FROM fee_groups WHERE building_id = ? ORDER BY name', [$b]),
            'charge_types' => $chargeTypes,
        ] + $this->lists('recurrences', 'distributions', 'vat_modes', 'liability_modes');
    }
}
