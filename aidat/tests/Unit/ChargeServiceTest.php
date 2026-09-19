<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Exceptions\DomainException;
use Aidat\Services\ChargeService;
use Aidat\Services\PaymentService;
use Aidat\Services\PeriodService;
use Aidat\Tests\TestCase;

final class ChargeServiceTest extends TestCase
{
    private function plan(array $over = []): array
    {
        return $over + ['name' => 'Aidat', 'charge_type' => 'aidat', 'block_id' => null, 'fee_group_id' => null, 'period' => '2026-09', 'due_date' => '2026-09-10', 'recurrence' => 'tek', 'repeat_until' => null, 'distribution' => 'esit', 'total_amount' => 300000, 'unit_amount' => 0, 'liability' => 'malik', 'vat_mode' => 'yok', 'vat_rate' => 0, 'description' => null];
    }

    public function testEsitDagitimToplamiKorur(): void
    {
        $s = new ChargeService($this->app);
        $lines = $s->distribute($this->plan(['total_amount' => 100000]), $s->planUnits($this->buildingId));
        self::assertCount(3, $lines);
        self::assertSame(100000, array_sum(array_column($lines, 'amount')));
    }

    public function testM2DagitimiOranli(): void
    {
        $s = new ChargeService($this->app);
        $lines = $s->distribute($this->plan(['distribution' => 'm2', 'total_amount' => 400000]), $s->planUnits($this->buildingId));
        self::assertSame(100000, $lines[$this->unitId(1)]['amount']);
        self::assertSame(200000, $lines[$this->unitId(3)]['amount']);
    }

    public function testSabitVeGrupDagitimi(): void
    {
        $s = new ChargeService($this->app);
        $lines = $s->distribute($this->plan(['distribution' => 'sabit', 'unit_amount' => 250000]), $s->planUnits($this->buildingId));
        self::assertSame([250000, 250000, 250000], array_values(array_column($lines, 'amount')));
    }

    public function testPlanYasamDongusuVeMukerrerKoruma(): void
    {
        $s = new ChargeService($this->app);
        $units = $s->planUnits($this->buildingId);
        $id = $s->savePlan($this->buildingId, $this->plan(['recurrence' => 'aylik', 'repeat_until' => '2026-12']), $s->distribute($this->plan(), $units));
        self::assertSame('taslak', $this->db->fetchColumn('SELECT status FROM charge_plans WHERE id = ?', [$id]));
        $s->approvePlan($id, $this->buildingId);
        self::assertSame(3, $s->processPlan($id, $this->buildingId));
        self::assertSame(3, $this->db->fetchInt('SELECT COUNT(*) FROM charges WHERE plan_id = ?', [$id]));
        $this->expectException(DomainException::class);
        $s->processPlan($id, $this->buildingId); // aynı dönem ikinci kez işlenemez
    }

    public function testTekrarliPlanSonrakiDonemiUretir(): void
    {
        $s = new ChargeService($this->app);
        $units = $s->planUnits($this->buildingId);
        $id = $s->savePlan($this->buildingId, $this->plan(['recurrence' => 'aylik', 'repeat_until' => '2026-12']), $s->distribute($this->plan(), $units));
        $s->approvePlan($id, $this->buildingId);
        $s->processPlan($id, $this->buildingId);
        self::assertSame(3, $s->generateRecurringPlans($this->buildingId, '2026-10'));
        self::assertSame(0, $s->generateRecurringPlans($this->buildingId, '2026-10'));
        self::assertSame('2026-10-10', $this->db->fetchColumn("SELECT due_date FROM charges WHERE plan_id = ? AND period = '2026-10' LIMIT 1", [$id]));
    }

    public function testKapaliDonemeTahakkukYapilamaz(): void
    {
        (new PeriodService($this->app))->close($this->buildingId, '2026-09', $this->userId);
        $this->expectException(DomainException::class);
        (new ChargeService($this->app))->createCharge($this->buildingId, ['unit_id' => $this->unitId(1), 'charge_type' => 'aidat', 'title' => 'Eylül', 'period' => '2026-09', 'due_date' => '2026-09-10', 'amount' => 100000]);
    }

    public function testOdenmisBorcIptalEdilemez(): void
    {
        $c = new ChargeService($this->app);
        $chargeId = $c->createCharge($this->buildingId, ['unit_id' => $this->unitId(1), 'charge_type' => 'aidat', 'title' => 'Eylül', 'period' => '2026-09', 'due_date' => '2026-09-10', 'amount' => 100000]);
        (new PaymentService($this->app))->create($this->buildingId, ['unit_id' => $this->unitId(1), 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 40000, 'method' => 'nakit', 'allocation_mode' => 'eski']);
        $this->expectException(DomainException::class);
        $c->cancelCharge($chargeId, $this->buildingId, 'test');
    }

    public function testBorcluListesiYaslandirma(): void
    {
        $c = new ChargeService($this->app);
        $c->createCharge($this->buildingId, ['unit_id' => $this->unitId(2), 'charge_type' => 'aidat', 'title' => 'Eski', 'period' => '2026-05', 'due_date' => '2026-05-10', 'amount' => 100000]);
        $rows = $c->debtors($this->buildingId, '2026-09-19');
        self::assertCount(1, $rows);
        self::assertSame(100000, (int) $rows[0]['d90p']);
        self::assertSame('Malik 2 (Malik)', $rows[0]['responsible']);
    }
}
