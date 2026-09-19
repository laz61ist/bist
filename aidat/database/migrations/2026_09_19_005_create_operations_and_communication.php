<?php

declare(strict_types=1);

use Aidat\Core\Blueprint;
use Aidat\Core\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('documents', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('category', 20)->default('diger');
            $t->string('title', 150);
            $t->string('file_path', 255);
            $t->string('original_name', 190);
            $t->string('mime', 80);
            $t->integer('size')->default(0);
            $t->string('visibility', 10)->default('yonetim'); // yonetim | sakinler | malikler
            $t->string('entity_type', 30)->nullable();
            $t->integer('entity_id')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['building_id', 'category']);
            $t->index(['entity_type', 'entity_id']);
        });

        $schema->create('meters', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->nullable()->references('units', 'CASCADE');
            $t->string('type', 15)->default('su');
            $t->string('serial_no', 40)->nullable();
            $t->float('multiplier')->default(1);
            $t->boolean('is_active')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('meter_readings', function (Blueprint $t): void {
            $t->id();
            $t->integer('meter_id')->references('meters', 'CASCADE');
            $t->string('period', 7);
            $t->date('reading_date');
            $t->float('value');
            $t->float('previous_value')->nullable();
            $t->float('consumption')->nullable();
            $t->integer('read_by')->nullable();
            $t->text('notes')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique(['meter_id', 'period']);
        });

        $schema->create('meter_distributions', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('type', 15);
            $t->string('period', 7);
            $t->money('total_amount');
            $t->float('total_consumption')->default(0);
            $t->float('unit_price')->default(0);
            $t->float('fixed_share_percent')->default(0);
            $t->string('status', 15)->default('taslak');
            $t->integer('plan_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('requests', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('unit_id')->nullable()->references('units', 'SET NULL');
            $t->integer('person_id')->nullable()->references('people', 'SET NULL');
            $t->integer('created_by')->nullable();
            $t->string('category', 15)->default('ariza');
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->string('location', 120)->nullable();
            $t->string('priority', 10)->default('normal');
            $t->string('status', 15)->default('yeni');
            $t->integer('assigned_to')->nullable()->references('users', 'SET NULL');
            $t->date('target_date')->nullable();
            $t->string('visibility', 10)->default('ozel');
            $t->money('cost_amount');
            $t->integer('expense_id')->nullable();
            $t->datetime('resolved_at')->nullable();
            $t->datetime('closed_at')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'status']);
        });

        $schema->create('request_comments', function (Blueprint $t): void {
            $t->id();
            $t->integer('request_id')->references('requests', 'CASCADE');
            $t->integer('user_id')->nullable();
            $t->text('body');
            $t->boolean('is_internal')->default(0);
            $t->string('status_change', 15)->nullable();
            $t->integer('document_id')->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('announcements', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('title', 150);
            $t->text('body');
            $t->string('target', 10)->default('tumu'); // tumu | blok | bolum | rol | kisi
            $t->string('target_ref', 40)->nullable();
            $t->string('channel', 10)->default('uygulama');
            $t->string('priority', 10)->default('normal');
            $t->datetime('published_at');
            $t->datetime('expires_at')->nullable();
            $t->boolean('is_pinned')->default(0);
            $t->integer('document_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->index(['building_id', 'published_at']);
        });

        $schema->create('announcement_reads', function (Blueprint $t): void {
            $t->id();
            $t->integer('announcement_id')->references('announcements', 'CASCADE');
            $t->integer('user_id')->references('users', 'CASCADE');
            $t->datetime('read_at');
            $t->unique(['announcement_id', 'user_id']);
        });

        $schema->create('meetings', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('type', 15)->default('olagan');
            $t->string('title', 150);
            $t->datetime('meeting_date');
            $t->string('location', 150)->nullable();
            $t->text('agenda')->nullable();
            $t->text('minutes')->nullable();
            $t->integer('quorum_required')->nullable();
            $t->integer('quorum_present')->nullable();
            $t->string('status', 15)->default('planlandi');
            $t->integer('document_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
        });

        $schema->create('meeting_decisions', function (Blueprint $t): void {
            $t->id();
            $t->integer('meeting_id')->references('meetings', 'CASCADE');
            $t->string('decision_no', 20);
            $t->text('text');
            $t->integer('votes_for')->default(0);
            $t->integer('votes_against')->default(0);
            $t->integer('votes_abstain')->default(0);
            $t->datetime('created_at')->nullable();
        });

        $schema->create('meeting_attendances', function (Blueprint $t): void {
            $t->id();
            $t->integer('meeting_id')->references('meetings', 'CASCADE');
            $t->integer('unit_id')->references('units', 'CASCADE');
            $t->integer('person_id')->nullable();
            $t->boolean('attended')->default(0);
            $t->string('proxy_name', 120)->nullable();
            $t->unique(['meeting_id', 'unit_id']);
        });

        $schema->create('polls', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('question', 255);
            $t->text('description')->nullable();
            $t->string('type', 10)->default('tek');
            $t->json('options');
            $t->datetime('starts_at');
            $t->datetime('ends_at');
            $t->boolean('is_anonymous')->default(1);
            $t->boolean('one_vote_per_unit')->default(1);
            $t->string('status', 10)->default('acik');
            $t->integer('created_by')->nullable();
            $t->timestamps();
        });

        $schema->create('poll_votes', function (Blueprint $t): void {
            $t->id();
            $t->integer('poll_id')->references('polls', 'CASCADE');
            $t->integer('unit_id')->nullable();
            $t->integer('user_id')->references('users', 'CASCADE');
            $t->json('option_indexes');
            $t->datetime('created_at');
            $t->unique(['poll_id', 'user_id']);
        });

        $schema->create('staff', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('full_name', 120);
            $t->string('position', 30)->default('kapici');
            $t->string('phone', 30)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('identity_no', 11)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->money('salary');
            $t->string('sgk_no', 30)->nullable();
            $t->boolean('is_active')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('assets', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->string('name', 150);
            $t->string('category', 60)->nullable();
            $t->string('serial_no', 60)->nullable();
            $t->date('purchase_date')->nullable();
            $t->money('cost');
            $t->string('location', 120)->nullable();
            $t->string('status', 15)->default('aktif');
            $t->date('warranty_until')->nullable();
            $t->integer('vendor_id')->nullable();
            $t->integer('expense_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('notifications', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->nullable()->references('buildings', 'CASCADE');
            $t->integer('user_id')->nullable();
            $t->integer('person_id')->nullable();
            $t->string('channel', 10)->default('uygulama'); // uygulama | eposta | sms
            $t->string('recipient', 190)->nullable();
            $t->string('subject', 190);
            $t->text('body');
            $t->string('status', 10)->default('kuyrukta'); // kuyrukta | gonderildi | loglandi | hata
            $t->text('error')->nullable();
            $t->string('template', 40)->nullable();
            $t->string('ref_type', 30)->nullable();
            $t->integer('ref_id')->nullable();
            $t->datetime('read_at')->nullable();
            $t->datetime('sent_at')->nullable();
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['building_id', 'created_at']);
            $t->index(['user_id', 'read_at']);
        });

        $schema->create('notification_templates', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->nullable()->references('buildings', 'CASCADE');
            $t->string('key', 40);
            $t->string('name', 100);
            $t->string('subject', 190);
            $t->text('body');
            $t->datetime('updated_at')->nullable();
            $t->index(['building_id', 'key']);
        });

        $schema->create('bank_imports', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->references('buildings', 'CASCADE');
            $t->integer('account_id')->references('accounts', 'CASCADE');
            $t->string('file_name', 190);
            $t->integer('row_count')->default(0);
            $t->integer('matched_count')->default(0);
            $t->string('status', 15)->default('bekliyor');
            $t->integer('created_by')->nullable();
            $t->datetime('created_at')->nullable();
        });

        $schema->create('bank_import_rows', function (Blueprint $t): void {
            $t->id();
            $t->integer('import_id')->references('bank_imports', 'CASCADE');
            $t->integer('row_no');
            $t->date('txn_date');
            $t->money('amount');
            $t->text('description')->nullable();
            $t->string('reference_no', 80)->nullable();
            $t->string('counterparty', 190)->nullable();
            $t->integer('suggested_unit_id')->nullable();
            $t->integer('matched_unit_id')->nullable();
            $t->integer('payment_id')->nullable();
            $t->string('status', 10)->default('bekliyor'); // bekliyor | eslesti | yoksayildi
            $t->datetime('created_at')->nullable();
        });

        $schema->create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->integer('building_id')->nullable();
            $t->string('action', 40);
            $t->string('entity_type', 40);
            $t->integer('entity_id')->nullable();
            $t->string('summary', 255)->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->datetime('created_at');
            $t->index(['building_id', 'created_at']);
            $t->index(['entity_type', 'entity_id']);
        });

        $schema->create('settings', function (Blueprint $t): void {
            $t->id();
            $t->integer('building_id')->nullable()->references('buildings', 'CASCADE');
            $t->string('key', 60);
            $t->text('value')->nullable();
            $t->datetime('updated_at')->nullable();
            $t->index(['building_id', 'key']);
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['settings', 'audit_logs', 'bank_import_rows', 'bank_imports', 'notification_templates', 'notifications', 'assets', 'staff', 'poll_votes', 'polls', 'meeting_attendances', 'meeting_decisions', 'meetings', 'announcement_reads', 'announcements', 'request_comments', 'requests', 'meter_distributions', 'meter_readings', 'meters', 'documents'] as $t) {
            $schema->drop($t);
        }
    }
};
