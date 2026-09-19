<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Services\ChargeService;
use Aidat\Services\LateFeeService;
use Aidat\Tests\TestCase;

final class LateFeeServiceTest extends TestCase
{
    private function rule(array $over = []): array
    {
        return $over + ['enabled' => true, 'rate_type' => 'aylik', 'rate' => 5.0, 'grace_days' => 0, 'start_rule' => 'vade', 'start_day' => 1, 'cap_percent' => 0.0, 'compound' => false];
    }

    public function testAylikYuzdeBesBasitFaiz(): void
    {
        $s = new LateFeeService($this->app);
        $charge = ['amount' => 100000, 'paid_amount' => 0, 'due_date' => '2026-06-10', 'late_fee_exempt' => 0];
        self::assertSame(0, $s->accrued($charge, $this->rule(), '2026-06-10'));
        self::assertSame(5000, $s->accrued($charge, $this->rule(), '2026-06-11'));   // başlayan ay sayılır
        self::assertSame(5000, $s->accrued($charge, $this->rule(), '2026-07-10'));
        self::assertSame(10000, $s->accrued($charge, $this->rule(), '2026-07-11'));
        self::assertSame(15000, $s->accrued($charge, $this->rule(), '2026-09-10'));
    }

    public function testToleransUstSinirVeMuafiyet(): void
    {
        $s = new LateFeeService($this->app);
        $charge = ['amount' => 100000, 'paid_amount' => 50000, 'due_date' => '2026-06-10', 'late_fee_exempt' => 0];
        self::assertSame(0, $s->accrued($charge, $this->rule(['grace_days' => 10]), '2026-06-15'));
        self::assertSame(2500, $s->accrued($charge, $this->rule(['grace_days' => 10]), '2026-06-21'));
        self::assertSame(5000, $s->accrued($charge, $this->rule(['cap_percent' => 10]), '2027-06-10'));
        self::assertSame(0, $s->accrued(['late_fee_exempt' => 1] + $charge, $this->rule(), '2027-06-10'));
        self::assertSame(0, $s->accrued($charge, $this->rule(['enabled' => false]), '2027-06-10'));
    }

    public function testGunlukOran(): void
    {
        $s = new LateFeeService($this->app);
        $charge = ['amount' => 100000, 'paid_amount' => 0, 'due_date' => '2026-06-10', 'late_fee_exempt' => 0];
        self::assertSame(1000, $s->accrued($charge, $this->rule(['rate_type' => 'gunluk', 'rate' => 0.1]), '2026-06-20'));
    }

    public function testUygulamaAltBorcAcarVeArtisiGunceller(): void
    {
        $c = new ChargeService($this->app);
        $unit = $this->unitId(1);
        $c->createCharge($this->buildingId, ['unit_id' => $unit, 'charge_type' => 'aidat', 'title' => 'Haziran', 'period' => '2026-06', 'due_date' => '2026-06-10', 'amount' => 100000]);
        $s = new LateFeeService($this->app);
        $r1 = $s->apply($this->buildingId, '2026-07-15');
        self::assertSame(1, $r1['created']);
        self::assertSame(10000, $r1['amount']);
        $r2 = $s->apply($this->buildingId, '2026-08-15');
        self::assertSame(0, $r2['created']);
        self::assertSame(1, $r2['updated']);
        self::assertSame(5000, $r2['amount']);
        $fee = $this->db->fetch("SELECT * FROM charges WHERE charge_type = 'gecikme' AND unit_id = ?", [$unit]);
        self::assertSame(15000, (int) $fee['amount']);
        self::assertSame(1, (int) $fee['late_fee_exempt']);
        // Üçüncü uygulama aynı tarihte fark üretmez
        $r3 = $s->apply($this->buildingId, '2026-08-15');
        self::assertSame(0, $r3['created'] + $r3['updated']);
    }
}
