<?php

declare(strict_types=1);

use Aidat\Core\Blueprint;
use Aidat\Core\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('expense_categories', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->nullable()->references('buildings', 'CASCADE');
            $t->integer('parent_id')->nullable();
            $t->string('name', 100);
            $t->boolean('is_active')->default(1);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->index(['building_id', 'parent_id']);
        });

        $schema->create('income_categories', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->nullable()->references('buildings', 'CASCADE');
            $t->string('name', 100);
            $t->boolean('is_active')->default(1);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        $schema->create('vendors', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 150);
            $t->string('service_type', 60)->nullable();
            $t->string('contact_name', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('tax_no', 20)->nullable();
            $t->string('tax_office', 80)->nullable();
            $t->string('iban', 34)->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(1);
            $t->timestamps();
        });

        $schema->create('contracts', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('vendor_id')->references('vendors', 'CASCADE');
            $t->string('title', 150);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->money('amount');
            $t->string('billing_period', 10)->default('aylik'); // aylik | yillik | tek
            $t->integer('renewal_notice_days')->default(30);
            $t->string('status', 20)->default('aktif');
            $t->integer('document_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('expenses', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('category_id')->nullable()->references('expense_categories', 'SET NULL');
            $t->integer('vendor_id')->nullable()->references('vendors', 'SET NULL');
            $t->integer('contract_id')->nullable()->references('contracts', 'SET NULL');
            $t->integer('account_id')->nullable()->references('accounts', 'SET NULL');
            $t->date('expense_date');
            $t->date('due_date')->nullable();
            $t->string('period', 7);
            $t->money('amount');
            $t->string('vat_mode', 10)->default('dahil');
            $t->float('vat_rate')->default(0);
            $t->money('vat_amount');
            $t->string('document_kind', 15)->default('fatura');
            $t->string('document_no', 60)->nullable();
            $t->text('description')->nullable();
            $t->integer('budget_line_id')->nullable();
            $t->string('scope', 20)->default('tumu'); // tumu | blok
            $t->integer('block_id')->nullable()->references('blocks', 'SET NULL');
            $t->string('status', 15)->default('odendi'); // planlandi | kismi | odendi | gecikti | iptal
            $t->money('paid_amount');
            $t->integer('document_id')->nullable();
            $t->integer('recurring_id')->nullable();
            $t->boolean('is_reversal')->default(0);
            $t->integer('reversed_expense_id')->nullable();
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->integer('cancelled_by')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'expense_date']);
            $t->index(['building_id', 'period']);
        });

        $schema->create('expense_payments', function (Blueprint $t): void {
            $t->id();
            $t->integer('expense_id')->references('expenses', 'CASCADE');
            $t->integer('account_id')->references('accounts', 'RESTRICT');
            $t->date('paid_date');
            $t->money('amount');
            $t->string('reference_no', 60)->nullable();
            $t->text('description')->nullable();
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('incomes', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('category_id')->nullable()->references('income_categories', 'SET NULL');
            $t->integer('account_id')->references('accounts', 'RESTRICT');
            $t->date('income_date');
            $t->string('period', 7);
            $t->money('amount');
            $t->text('description')->nullable();
            $t->string('document_no', 60)->nullable();
            $t->integer('document_id')->nullable();
            $t->string('status', 10)->default('gecerli');
            $t->datetime('cancelled_at')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->integer('cancelled_by')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'income_date']);
        });

        $schema->create('recurring_expenses', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('category_id')->nullable()->references('expense_categories', 'SET NULL');
            $t->integer('vendor_id')->nullable()->references('vendors', 'SET NULL');
            $t->integer('account_id')->nullable()->references('accounts', 'SET NULL');
            $t->string('title', 150);
            $t->money('amount');
            $t->integer('day_of_month')->default(1);
            $t->string('frequency', 10)->default('aylik'); // aylik | uc_aylik | yillik
            $t->string('start_period', 7);
            $t->string('end_period', 7)->nullable();
            $t->string('last_generated_period', 7)->nullable();
            $t->boolean('auto_paid')->default(0);
            $t->boolean('is_active')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('budgets', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('fiscal_year');
            $t->integer('version')->default(1);
            $t->string('title', 150);
            $t->string('status', 20)->default('taslak'); // taslak | onayli | revizyonda | kapali
            $t->string('distribution', 20)->default('esit');
            $t->money('reserve_fund_amount');
            $t->float('reserve_fund_percent')->default(0);
            $t->money('monthly_dues_suggested');
            $t->datetime('approved_at')->nullable();
            $t->string('decision_no', 40)->nullable();
            $t->integer('meeting_id')->nullable();
            $t->integer('document_id')->nullable();
            $t->text('notes')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->unique(['building_id', 'fiscal_year', 'version']);
        });

        $schema->create('budget_lines', function (Blueprint $t): void {
            $t->id();
            $t->integer('budget_id')->references('budgets', 'CASCADE');
            $t->string('kind', 10)->default('gider'); // gider | gelir
            $t->integer('category_id')->nullable();
            $t->string('name', 150);
            $t->money('annual_amount');
            $t->json('monthly_json')->nullable();
            $t->text('notes')->nullable();
            $t->integer('sort_order')->default(0);
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['budget_lines', 'budgets', 'recurring_expenses', 'incomes', 'expense_payments', 'expenses', 'contracts', 'vendors', 'income_categories', 'expense_categories'] as $t) {
            $schema->drop($t);
        }
    }
};
