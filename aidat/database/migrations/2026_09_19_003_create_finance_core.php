<?php

declare(strict_types=1);

use Aidat\Core\Blueprint;
use Aidat\Core\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('fiscal_periods', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('period', 7);
            $t->string('status', 10)->default('acik');
            $t->integer('closed_by')->nullable();
            $t->datetime('closed_at')->nullable();
            $t->integer('reopened_by')->nullable();
            $t->datetime('reopened_at')->nullable();
            $t->text('note')->nullable();
            $t->unique(['building_id', 'period']);
        });

        $schema->create('accounts', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 80);
            $t->string('type', 10)->default('kasa'); // kasa | banka
            $t->string('bank_name', 80)->nullable();
            $t->string('iban', 34)->nullable();
            $t->string('account_no', 40)->nullable();
            $t->money('opening_balance');
            $t->date('opening_date')->nullable();
            $t->boolean('is_default')->default(0);
            $t->boolean('is_active')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('charge_plans', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 120);
            $t->string('charge_type', 20)->default('aidat');
            $t->integer('block_id')->nullable()->references('blocks', 'SET NULL');
            $t->integer('fee_group_id')->nullable()->references('fee_groups', 'SET NULL');
            $t->string('period', 7);
            $t->integer('due_day')->default(10);
            $t->date('due_date');
            $t->string('recurrence', 20)->default('tek'); // tek | aylik | uc_aylik | yillik
            $t->string('repeat_until', 7)->nullable();
            $t->string('distribution', 20)->default('esit'); // esit | m2 | arsa_payi | sabit | grup | manuel
            $t->money('total_amount');
            $t->money('unit_amount');
            $t->json('group_amounts')->nullable();
            $t->string('liability', 20)->default('malik');
            $t->string('vat_mode', 10)->default('yok');
            $t->float('vat_rate')->default(0);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('taslak'); // taslak | onaylandi | islendi | iptal
            $t->integer('approved_by')->nullable();
            $t->datetime('approved_at')->nullable();
            $t->datetime('processed_at')->nullable();
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->string('last_generated_period', 7)->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'status']);
        });

        $schema->create('charge_plan_lines', function (Blueprint $t): void {
            $t->id();
            $t->integer('plan_id')->references('charge_plans', 'CASCADE');
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->boolean('included')->default(1);
            $t->float('share_value')->nullable();
            $t->money('amount');
            $t->money('rounding_diff');
            $t->unique(['plan_id', 'unit_id']);
        });

        $schema->create('charges', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->integer('person_id')->nullable()->references('people', 'SET NULL');
            $t->integer('plan_id')->nullable()->references('charge_plans', 'SET NULL');
            $t->integer('parent_charge_id')->nullable(); // gecikme tazminatının ana borcu
            $t->string('charge_type', 20)->default('aidat');
            $t->string('title', 150);
            $t->string('period', 7);
            $t->date('due_date');
            $t->money('amount');
            $t->money('paid_amount');
            $t->string('status', 15)->default('odenmedi'); // odenmedi | kismi | odendi | iptal
            $t->string('liability', 20)->default('malik');
            $t->text('description')->nullable();
            $t->boolean('is_reversal')->default(0);
            $t->integer('reversed_charge_id')->nullable();
            $t->boolean('late_fee_exempt')->default(0);
            $t->date('late_fee_accrued_to')->nullable();
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->integer('cancelled_by')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'unit_id', 'status']);
            $t->index(['building_id', 'period']);
            $t->index(['due_date']);
            $t->index(['parent_charge_id']);
        });

        $schema->create('receipt_sequences', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('year');
            $t->integer('last_seq')->default(0);
            $t->unique(['building_id', 'year']);
        });

        $schema->create('payments', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->nullable()->references('units', 'SET NULL');
            $t->integer('person_id')->nullable()->references('people', 'SET NULL');
            $t->integer('account_id')->references('accounts', 'RESTRICT');
            $t->date('payment_date');
            $t->string('payment_time', 5)->nullable();
            $t->money('amount');
            $t->string('method', 15)->default('nakit');
            $t->string('reference_no', 60)->nullable();
            $t->text('description')->nullable();
            $t->string('allocation_mode', 10)->default('eski');
            $t->money('unallocated_amount'); // avans
            $t->string('receipt_no', 30)->nullable();
            $t->integer('receipt_seq')->nullable();
            $t->integer('receipt_year')->nullable();
            $t->string('status', 10)->default('gecerli'); // gecerli | iptal | iade
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->integer('cancelled_by')->nullable();
            $t->integer('refund_of_id')->nullable();
            $t->integer('document_id')->nullable();
            $t->integer('collected_by')->nullable();
            $t->integer('import_row_id')->nullable();
            $t->string('verify_code', 40)->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'payment_date']);
            $t->index(['unit_id']);
            $t->unique(['building_id', 'receipt_no']);
        });

        $schema->create('payment_allocations', function (Blueprint $t): void {
            $t->id();
            $t->integer('payment_id')->references('payments', 'CASCADE');
            $t->integer('charge_id')->references('charges', 'CASCADE');
            $t->money('amount');
            $t->datetime('created_at')->nullable();
            $t->index(['charge_id']);
        });

        $schema->create('ledger_entries', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('account_id')->references('accounts', 'CASCADE');
            $t->date('entry_date');
            $t->string('direction', 3); // in | out
            $t->money('amount');
            $t->string('ref_type', 30); // payment | expense | income | transfer | opening | adjustment | refund
            $t->integer('ref_id')->nullable();
            $t->string('description', 255)->nullable();
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['account_id', 'entry_date']);
            $t->index(['ref_type', 'ref_id']);
        });

        $schema->create('transfers', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('from_account_id')->references('accounts', 'RESTRICT');
            $t->integer('to_account_id')->references('accounts', 'RESTRICT');
            $t->date('transfer_date');
            $t->money('amount');
            $t->money('fee_amount');
            $t->string('kind', 20)->default('virman');
            $t->string('reference_no', 60)->nullable();
            $t->text('description')->nullable();
            $t->string('status', 10)->default('gecerli');
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['transfers', 'ledger_entries', 'payment_allocations', 'payments', 'receipt_sequences', 'charges', 'charge_plan_lines', 'charge_plans', 'accounts', 'fiscal_periods'] as $t) {
            $schema->drop($t);
        }
    }
};
