<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Exceptions\DomainException;
use Aidat\Services\ExpenseService;
use Aidat\Services\IncomeService;
use Aidat\Services\LedgerService;
use Aidat\Services\TransferService;
use Aidat\Tests\TestCase;

final class ExpenseLedgerTest extends TestCase
{
    public function testKdvHesabi(): void
    {
        self::assertSame(['amount' => 120000, 'vat' => 20000], ExpenseService::vat(100000, 'haric', 20));
        self::assertSame(['amount' => 120000, 'vat' => 20000], ExpenseService::vat(120000, 'dahil', 20));
        self::assertSame(['amount' => 100000, 'vat' => 0], ExpenseService::vat(100000, 'yok', 20));
    }

    public function testGiderOdemeVeIptalDefteriDengeler(): void
    {
        $e = new ExpenseService($this->app);
        $ledger = new LedgerService($this->app);
        $banka = $this->accountId('banka');
        $id = $e->create($this->buildingId, ['category_id' => null, 'vendor_id' => null, 'account_id' => $banka, 'expense_date' => '2026-09-05', 'amount' => 45000, 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fatura', 'description' => 'Asansör bakım', 'status' => 'odendi']);
        self::assertSame(100000 - 45000, $ledger->balance($banka));
        self::assertSame('odendi', $this->db->fetchColumn('SELECT status FROM expenses WHERE id = ?', [$id]));
        $e->cancel($id, $this->buildingId, 'yanlış yapı');
        self::assertSame(100000, $ledger->balance($banka));
        self::assertSame('iptal', $this->db->fetchColumn('SELECT status FROM expenses WHERE id = ?', [$id]));
    }

    public function testKismiOdemeDurumu(): void
    {
        $e = new ExpenseService($this->app);
        $id = $e->create($this->buildingId, ['category_id' => null, 'vendor_id' => null, 'account_id' => null, 'expense_date' => '2026-09-05', 'due_date' => '2026-09-30', 'amount' => 100000, 'vat_mode' => 'yok', 'vat_rate' => 0, 'document_kind' => 'fatura', 'description' => 'Boya', 'status' => 'planlandi']);
        $e->pay($id, $this->buildingId, $this->accountId('banka'), 40000, '2026-09-10');
        self::assertSame('kismi', $this->db->fetchColumn('SELECT status FROM expenses WHERE id = ?', [$id]));
        $this->expectException(DomainException::class);
        $e->pay($id, $this->buildingId, $this->accountId('banka'), 70000, '2026-09-11'); // kalanı aşar
    }

    public function testVirmanCiftTarafliVeIptal(): void
    {
        $t = new TransferService($this->app);
        $ledger = new LedgerService($this->app);
        $id = $t->create($this->buildingId, ['from_account_id' => $this->accountId('banka'), 'to_account_id' => $this->accountId('kasa'), 'transfer_date' => '2026-09-01', 'amount' => 30000, 'fee_amount' => 500, 'kind' => 'nakit_cekme']);
        self::assertSame(69500, $ledger->balance($this->accountId('banka')));
        self::assertSame(30000, $ledger->balance($this->accountId('kasa')));
        $t->cancel($id, $this->buildingId, 'hata');
        self::assertSame(100000, $ledger->balance($this->accountId('banka')));
        self::assertSame(0, $ledger->balance($this->accountId('kasa')));
    }

    public function testAyniHesabaVirmanOlmaz(): void
    {
        $this->expectException(DomainException::class);
        (new TransferService($this->app))->create($this->buildingId, ['from_account_id' => $this->accountId('kasa'), 'to_account_id' => $this->accountId('kasa'), 'transfer_date' => '2026-09-01', 'amount' => 100]);
    }

    public function testDigerGelir(): void
    {
        $i = new IncomeService($this->app);
        $id = $i->create($this->buildingId, ['category_id' => null, 'account_id' => $this->accountId('kasa'), 'income_date' => '2026-09-01', 'amount' => 15000, 'description' => 'Reklam']);
        self::assertSame(15000, (new LedgerService($this->app))->balance($this->accountId('kasa')));
        $i->cancel($id, $this->buildingId, 'iade');
        self::assertSame(0, (new LedgerService($this->app))->balance($this->accountId('kasa')));
    }
}
