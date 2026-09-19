<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Exceptions\DomainException;
use Aidat\Services\ChargeService;
use Aidat\Services\LedgerService;
use Aidat\Services\PaymentService;
use Aidat\Tests\TestCase;

final class PaymentServiceTest extends TestCase
{
    private int $unit;
    private array $charges = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = $this->unitId(1);
        $c = new ChargeService($this->app);
        foreach (['2026-07', '2026-08', '2026-09'] as $p) {
            $this->charges[$p] = $c->createCharge($this->buildingId, ['unit_id' => $this->unit, 'charge_type' => 'aidat', 'title' => 'Aidat ' . $p, 'period' => $p, 'due_date' => $p . '-10', 'amount' => 250000]);
        }
    }

    private function charge(string $p): array
    {
        return $this->db->fetch('SELECT * FROM charges WHERE id = ?', [$this->charges[$p]]);
    }

    public function testEnEskiBorctanBaslar(): void
    {
        $ps = new PaymentService($this->app);
        $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 400000, 'method' => 'nakit', 'allocation_mode' => 'eski']);
        self::assertSame('odendi', $this->charge('2026-07')['status']);
        self::assertSame('kismi', $this->charge('2026-08')['status']);
        self::assertSame(150000, (int) $this->charge('2026-08')['paid_amount']);
        self::assertSame('odenmedi', $this->charge('2026-09')['status']);
    }

    public function testFazlaOdemeAvansOlurVeYeniBorcaMahsupEdilir(): void
    {
        $ps = new PaymentService($this->app);
        $pid = $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('banka'), 'payment_date' => '2026-09-12', 'amount' => 800000, 'method' => 'havale', 'allocation_mode' => 'eski']);
        self::assertSame(50000, (int) $this->db->fetchColumn('SELECT unallocated_amount FROM payments WHERE id = ?', [$pid]));
        $bal = (new ChargeService($this->app))->unitBalance($this->unit);
        self::assertSame(0, $bal['debt']);
        self::assertSame(50000, $bal['advance']);
        // Yeni borç gelince avans otomatik mahsup
        (new ChargeService($this->app))->createCharge($this->buildingId, ['unit_id' => $this->unit, 'charge_type' => 'aidat', 'title' => 'Ekim', 'period' => '2026-10', 'due_date' => '2026-10-10', 'amount' => 250000]);
        $bal = (new ChargeService($this->app))->unitBalance($this->unit);
        self::assertSame(200000, $bal['debt']);
        self::assertSame(0, $bal['advance']);
    }

    public function testManuelDagitimTutariAsamaz(): void
    {
        $ps = new PaymentService($this->app);
        $pid = $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 100000, 'method' => 'nakit', 'allocation_mode' => 'manuel', 'manual' => [$this->charges['2026-09'] => 250000]]);
        self::assertSame(100000, (int) $this->charge('2026-09')['paid_amount']);
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT unallocated_amount FROM payments WHERE id = ?', [$pid]));
        self::assertSame('odenmedi', $this->charge('2026-07')['status']);
    }

    public function testIptalBorclariGeriAcarVeDefterTersKayitYazar(): void
    {
        $ps = new PaymentService($this->app);
        $ledger = new LedgerService($this->app);
        $pid = $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 250000, 'method' => 'nakit', 'allocation_mode' => 'eski']);
        self::assertSame(250000, $ledger->balance($this->accountId('kasa')));
        $ps->cancel($pid, $this->buildingId, 'mükerrer');
        self::assertSame('iptal', $this->db->fetchColumn('SELECT status FROM payments WHERE id = ?', [$pid]));
        self::assertSame('odenmedi', $this->charge('2026-07')['status']);
        self::assertSame(0, $ledger->balance($this->accountId('kasa')));
        self::assertSame(2, $this->db->fetchInt('SELECT COUNT(*) FROM ledger_entries WHERE ref_id = ?', [$pid]));
        $this->expectException(DomainException::class);
        $ps->cancel($pid, $this->buildingId, 'ikinci kez');
    }

    public function testMakbuzNumarasiSiraliVeBenzersiz(): void
    {
        $ps = new PaymentService($this->app);
        $a = $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 10000, 'method' => 'nakit', 'allocation_mode' => 'avans']);
        $b = $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-09-12', 'amount' => 10000, 'method' => 'nakit', 'allocation_mode' => 'avans']);
        self::assertSame('MKB-2026-000001', $this->db->fetchColumn('SELECT receipt_no FROM payments WHERE id = ?', [$a]));
        self::assertSame('MKB-2026-000002', $this->db->fetchColumn('SELECT receipt_no FROM payments WHERE id = ?', [$b]));
        $data = $ps->receiptData($b, $this->buildingId);
        self::assertSame('yüz Türk Lirası', $data['amount_words']);
        self::assertNotEmpty($data['verify_code']);
    }

    public function testEkstreYuruyenBakiye(): void
    {
        $ps = new PaymentService($this->app);
        $ps->create($this->buildingId, ['unit_id' => $this->unit, 'account_id' => $this->accountId('kasa'), 'payment_date' => '2026-08-15', 'amount' => 250000, 'method' => 'nakit', 'allocation_mode' => 'eski']);
        $st = $ps->statement($this->unit, '2026-08-01', '2026-09-30');
        self::assertSame(250000, $st['opening']);
        self::assertSame(500000, $st['closing']);
        self::assertCount(3, $st['rows']);
    }
}
