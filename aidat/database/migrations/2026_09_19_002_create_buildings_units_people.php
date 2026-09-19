<?php

declare(strict_types=1);

use Aidat\Core\Blueprint;
use Aidat\Core\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('buildings', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 150);
            $t->string('type', 20)->default('apartman');
            $t->string('tax_no', 20)->nullable();
            $t->string('tax_office', 80)->nullable();
            $t->text('address')->nullable();
            $t->string('city', 60)->nullable();
            $t->string('district', 60)->nullable();
            $t->date('management_start')->nullable();
            $t->string('currency', 3)->default('TRY');
            $t->string('iban', 34)->nullable();
            $t->string('bank_name', 80)->nullable();
            $t->string('account_holder', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('logo_path', 255)->nullable();
            $t->boolean('is_active')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('building_users', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id')->references('users', 'CASCADE');
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('role', 30)->default('manager');
            $t->json('permissions')->nullable(); // null = rol varsayılanı
            $t->boolean('is_default')->default(0);
            $t->datetime('created_at')->nullable();
            $t->unique(['user_id', 'building_id']);
        });

        $schema->create('blocks', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 60);
            $t->string('code', 20)->nullable();
            $t->integer('floors')->nullable();
            $t->text('description')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        $schema->create('fee_groups', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 80);
            $t->text('description')->nullable();
            $t->timestamps();
        });

        $schema->create('units', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('block_id')->nullable()->references('blocks', 'SET NULL');
            $t->integer('fee_group_id')->nullable()->references('fee_groups', 'SET NULL');
            $t->string('door_no', 20);
            $t->string('floor', 10)->nullable();
            $t->string('type', 20)->default('daire');
            $t->float('gross_m2')->nullable();
            $t->float('net_m2')->nullable();
            $t->float('land_share')->nullable(); // arsa payı
            $t->string('status', 20)->default('dolu');
            $t->string('liability_mode', 20)->default('malik');
            $t->boolean('late_fee_exempt')->default(0);
            $t->text('notes')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->index(['building_id', 'block_id', 'door_no']);
        });

        $schema->create('unit_vehicles', function (Blueprint $t): void {
            $t->id();
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->string('plate', 15);
            $t->string('description', 80)->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('people', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('type', 10)->default('gercek');
            $t->string('first_name', 80);
            $t->string('last_name', 80)->nullable();
            $t->string('company_name', 150)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('phone2', 30)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('identity_no', 11)->nullable(); // TCKN / VKN — yetkiye bağlı ve maskeli gösterilir
            $t->boolean('contact_consent')->default(0);
            $t->string('emergency_name', 120)->nullable();
            $t->string('emergency_phone', 30)->nullable();
            $t->integer('user_id')->nullable()->references('users', 'SET NULL');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'last_name']);
        });

        $schema->create('occupancies', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->integer('person_id')->references('people', 'CASCADE');
            $t->string('role', 20)->default('malik'); // malik | kiraci | oturan | vekil
            $t->string('liability', 20)->default('malik'); // malik | kiraci | paylasimli
            $t->boolean('is_notify_contact')->default(0);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['unit_id', 'end_date']);
        });

        $schema->create('handovers', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->integer('from_person_id')->nullable()->references('people', 'SET NULL');
            $t->integer('to_person_id')->nullable()->references('people', 'SET NULL');
            $t->date('handover_date');
            $t->string('balance_mode', 20)->default('kalir'); // kalir | devreder
            $t->money('transferred_balance');
            $t->money('deposit_transferred');
            $t->text('notes')->nullable();
            $t->integer('document_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['handovers', 'occupancies', 'people', 'unit_vehicles', 'units', 'fee_groups', 'blocks', 'building_users', 'buildings'] as $t) {
            $schema->drop($t);
        }
    }
};
