<?php

declare(strict_types=1);

use Aidat\Core\Blueprint;
use Aidat\Core\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 190)->unique();
            $t->string('phone', 30)->nullable();
            $t->string('password_hash', 255);
            $t->string('role', 30)->default('manager'); // admin | manager | accountant | auditor | staff | owner | tenant
            $t->boolean('is_active')->default(1);
            $t->boolean('must_change_password')->default(0);
            $t->datetime('last_login_at')->nullable();
            $t->string('theme', 10)->default('auto');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $schema->create('user_tokens', function (Blueprint $t): void {
            $t->id();
            $t->integer('user_id')->references('users', 'CASCADE');
            $t->string('selector', 24)->unique();
            $t->string('validator_hash', 64);
            $t->datetime('expires_at');
            $t->datetime('created_at');
        });

        $schema->create('login_attempts', function (Blueprint $t): void {
            $t->id();
            $t->string('email', 190)->unique();
            $t->string('ip', 45)->nullable();
            $t->integer('attempts')->default(0);
            $t->datetime('locked_until')->nullable();
            $t->datetime('updated_at')->nullable();
        });

        $schema->create('password_resets', function (Blueprint $t): void {
            $t->id();
            $t->string('email', 190);
            $t->string('token_hash', 64);
            $t->datetime('expires_at');
            $t->datetime('created_at');
            $t->index(['email']);
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['password_resets', 'login_attempts', 'user_tokens', 'users'] as $t) {
            $schema->drop($t);
        }
    }
};
