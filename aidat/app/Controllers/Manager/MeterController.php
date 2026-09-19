<?php

declare(strict_types=1);

namespace Aidat\Controllers\Manager;

use Aidat\Core\Controller;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Response;
use Aidat\Services\MeterService;

final class MeterController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $types = $this->app->config()->get('lists.meter_types', []);
        $tab = $this->request->str('tur');
        if (!array_key_exists($tab, $types)) {
            $tab = (string) array_key_first($types);
        }
        $period = $this->request->str('donem');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = Dates::currentPeriod();
        }
        $counts = $this->db->fetchPairs('SELECT type, COUNT(*) FROM meters WHERE building_id = ? GROUP BY type', [$b]);
        $rows = $this->db->fetchAll(
            'SELECT m.*, u.door_no, bl.name AS block_name,
                    (SELECT r.value FROM meter_readings r WHERE r.meter_id = m.id ORDER BY r.period DESC LIMIT 1) AS last_value,
                    (SELECT r.period FROM meter_readings r WHERE r.meter_id = m.id ORDER BY r.period DESC LIMIT 1) AS last_period,
                    (SELECT r.consumption FROM meter_readings r WHERE r.meter_id = m.id ORDER BY r.period DESC LIMIT 1) AS last_consumption,
                    (SELECT r.value FROM meter_readings r WHERE r.meter_id = m.id AND r.period < ? ORDER BY r.period DESC LIMIT 1) AS prev_value,
                    (SELECT r.value FROM meter_readings r WHERE r.meter_id = m.id AND r.period = ?) AS period_value,
                    (SELECT r.consumption FROM meter_readings r WHERE r.meter_id = m.id AND r.period = ?) AS period_consumption
             FROM meters m LEFT JOIN units u ON u.id = m.unit_id LEFT JOIN blocks bl ON bl.id = u.block_id
             WHERE m.building_id = ? AND m.type = ?
             ORDER BY m.is_active DESC, bl.sort_order, bl.name, CAST(u.door_no AS INTEGER), u.door_no, m.id',
            [$period, $period, $period, $b, $tab],
        );
        $active = array_values(array_filter($rows, static fn (array $m): bool => (int) $m['is_active'] === 1));
        $distributions = $this->db->fetchAll('SELECT * FROM meter_distributions WHERE building_id = ? AND type = ? ORDER BY period DESC, id DESC LIMIT 6', [$b, $tab]);
        return $this->view('manager.meters.index', [
            'title' => 'Sayaçlar', 'rows' => $rows, 'active' => $active, 'tab' => $tab, 'period' => $period, 'counts' => $counts,
            'units' => $this->unitOptions(), 'distributions' => $distributions, 'today' => Dates::today(),
        ] + $this->lists('meter_types'));
    }

    public function store(): Response
    {
        $d = $this->validate([
            'unit_id' => 'nullable|integer',
            'type' => 'required|in_keys:lists.meter_types',
            'serial_no' => 'nullable|max:40',
            'multiplier' => 'nullable|numeric|min:0',
            'notes' => 'nullable|max:500',
        ], ['unit_id' => 'bağımsız bölüm', 'type' => 'sayaç türü', 'serial_no' => 'seri no', 'multiplier' => 'çarpan']);
        $b = $this->buildingId();
        if ($d['unit_id']) {
            $this->findOwned('units', (int) $d['unit_id']);
        }
        $id = $this->db->insert('meters', [
            'building_id' => $b, 'unit_id' => $d['unit_id'] ?: null, 'type' => $d['type'], 'serial_no' => $d['serial_no'],
            'multiplier' => $d['multiplier'] !== null && (float) $d['multiplier'] > 0 ? (float) $d['multiplier'] : 1.0,
            'is_active' => 1, 'notes' => $d['notes'], 'created_at' => Database::now(), 'updated_at' => Database::now(),
        ]);
        $this->audit('meter.create', 'meter', $id, null, $d, 'Sayaç eklendi: ' . list_label('meter_types', $d['type']) . ' ' . ($d['serial_no'] ?? ''));
        $this->success('Sayaç eklendi.');
        return $this->redirectRoute('meters.index', ['tur' => $d['type']]);
    }

    public function reading(int $id): Response
    {
        $meter = $this->findOwned('meters', $id);
        $d = $this->validate([
            'period' => 'required|period',
            'reading_date' => 'required|date',
            'value' => 'required|numeric|min:0',
            'notes' => 'nullable|max:500',
        ], ['period' => 'dönem', 'reading_date' => 'okuma tarihi', 'value' => 'okuma değeri']);
        $rid = (new MeterService($this->app))->addReading($id, $this->buildingId(), (string) $d['period'], (string) $d['reading_date'], (float) $d['value'], $d['notes']);
        $this->audit('meter.reading', 'meter_reading', $rid, null, ['meter_id' => $id, 'period' => $d['period'], 'value' => $d['value']], 'Sayaç okuması: ' . ($meter['serial_no'] ?: '#' . $id) . ' · ' . Dates::period((string) $d['period']));
        $this->success('Okuma kaydedildi.');
        return $this->redirectRoute('meters.index', ['tur' => $meter['type'], 'donem' => $d['period']]);
    }

    public function bulkReading(): Response
    {
        $d = $this->validate([
            'period' => 'required|period',
            'reading_date' => 'required|date',
            'type' => 'nullable|in_keys:lists.meter_types',
        ], ['period' => 'dönem', 'reading_date' => 'okuma tarihi', 'type' => 'sayaç türü']);
        $b = $this->buildingId();
        $readings = $this->request->input('readings');
        $readings = is_array($readings) ? $readings : [];
        $service = new MeterService($this->app);
        $saved = 0;
        $errors = [];
        foreach ($readings as $meterId => $value) {
            $value = is_string($value) ? trim(str_replace(',', '.', $value)) : $value;
            if ($value === '' || $value === null) {
                continue;
            }
            $meterId = (int) $meterId;
            $meter = $this->db->fetch('SELECT id, serial_no, unit_id FROM meters WHERE id = ? AND building_id = ?', [$meterId, $b]);
            if ($meter === null) {
                continue;
            }
            $label = $meter['serial_no'] ?: '#' . $meterId;
            if (!is_numeric($value) || (float) $value < 0) {
                $errors[] = $label . ': geçersiz değer';
                continue;
            }
            try {
                $service->addReading($meterId, $b, (string) $d['period'], (string) $d['reading_date'], (float) $value);
                $saved++;
            } catch (DomainException $e) {
                $errors[] = $label . ': ' . $e->getMessage();
            }
        }
        $this->audit('meter.bulk_reading', 'meter_reading', null, null, ['period' => $d['period'], 'saved' => $saved, 'errors' => count($errors)], 'Toplu sayaç okuması: ' . Dates::period((string) $d['period']) . ' · ' . $saved . ' okuma');
        if ($saved > 0) {
            $this->success($saved . ' okuma kaydedildi.');
        }
        if ($errors !== []) {
            $this->error('Kaydedilemeyen okumalar: ' . implode(' · ', array_slice($errors, 0, 8)) . (count($errors) > 8 ? ' · …' : ''));
        } elseif ($saved === 0) {
            $this->error('Hiç okuma girilmedi.');
        }
        return $this->redirectRoute('meters.index', ['tur' => $d['type'] ?? '', 'donem' => $d['period']]);
    }

    public function distribute(): Response
    {
        $d = $this->validate([
            'type' => 'required|in_keys:lists.meter_types',
            'period' => 'required|period',
            'total_amount' => 'required|money_positive',
            'fixed_share_percent' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'required|date',
        ], ['type' => 'sayaç türü', 'period' => 'dönem', 'total_amount' => 'toplam fatura', 'fixed_share_percent' => 'sabit pay', 'due_date' => 'vade']);
        $result = (new MeterService($this->app))->distribute($this->buildingId(), (string) $d['type'], (string) $d['period'], (int) $d['total_amount'], (float) ($d['fixed_share_percent'] ?? 0), (string) $d['due_date']);
        $this->audit('meter.distribute', 'charge_plan', $result['plan_id'], null, ['type' => $d['type'], 'period' => $d['period'], 'total_amount' => $d['total_amount'], 'units' => count($result['rows'])], 'Tüketim dağıtımı: ' . list_label('meter_types', $d['type']) . ' ' . Dates::period((string) $d['period']));
        $this->success(count($result['rows']) . ' bölüme dağıtıldı. Taslak tahakkuk planını kontrol edip onaylayın.');
        return $this->redirectRoute('plans.show', ['id' => $result['plan_id']]);
    }

    /** @return array<string, array<string, string>> blok bazlı optgroup */
    private function unitOptions(): array
    {
        $rows = $this->db->fetchAll('SELECT u.id, u.door_no, bl.name AS block_name FROM units u LEFT JOIN blocks bl ON bl.id = u.block_id WHERE u.building_id = ? ORDER BY bl.sort_order, bl.name, CAST(u.door_no AS INTEGER), u.door_no', [$this->buildingId()]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['block_name'] ?: 'Bloksuz'][(string) $r['id']] = 'No ' . $r['door_no'];
        }
        return $out;
    }
}
