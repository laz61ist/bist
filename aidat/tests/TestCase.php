<?php

declare(strict_types=1);

namespace Aidat\Tests;

use Aidat\Core\Application;
use Aidat\Core\Config;
use Aidat\Core\Container;
use Aidat\Core\Database;
use Aidat\Core\Migrator;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    protected Database $db;
    protected int $buildingId = 0;
    protected int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Container::reset();
        $base = dirname(__DIR__);
        $config = new Config($base . '/config', $base);
        $config->set('database.driver', 'sqlite');
        $config->set('database.sqlite_path', ':memory:');
        $this->app = new Application($base, $config);
        $this->db = $this->app->db();
        (new Migrator($this->db, $base . '/database/migrations'))->migrate();
        $this->seedMinimal();
    }

    /** Bir yönetici, bir yapı, bir kasa, üç bölüm ve üç malik. */
    protected function seedMinimal(): void
    {
        $now = Database::now();
        $this->userId = $this->db->insert('users', ['name' => 'Test Yönetici', 'email' => 'test@aidat.local', 'password_hash' => password_hash('Test1234!', PASSWORD_DEFAULT), 'role' => 'manager', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->buildingId = $this->db->insert('buildings', ['name' => 'TEST Apartmanı', 'type' => 'apartman', 'currency' => 'TRY', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('building_users', ['user_id' => $this->userId, 'building_id' => $this->buildingId, 'role' => 'manager', 'created_at' => $now]);
        $this->db->insert('accounts', ['building_id' => $this->buildingId, 'name' => 'Kasa', 'type' => 'kasa', 'opening_balance' => 0, 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('accounts', ['building_id' => $this->buildingId, 'name' => 'Banka', 'type' => 'banka', 'opening_balance' => 100000, 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[1, 100, 10], [2, 100, 10], [3, 200, 20]] as [$no, $m2, $share]) {
            $uid = $this->db->insert('units', ['building_id' => $this->buildingId, 'door_no' => (string) $no, 'type' => 'daire', 'gross_m2' => $m2, 'land_share' => $share, 'status' => 'dolu', 'liability_mode' => 'malik', 'created_at' => $now, 'updated_at' => $now]);
            $pid = $this->db->insert('people', ['building_id' => $this->buildingId, 'type' => 'gercek', 'first_name' => 'Malik', 'last_name' => (string) $no, 'created_at' => $now, 'updated_at' => $now]);
            $this->db->insert('occupancies', ['building_id' => $this->buildingId, 'unit_id' => $uid, 'person_id' => $pid, 'role' => 'malik', 'liability' => 'malik', 'start_date' => '2024-01-01', 'created_at' => $now, 'updated_at' => $now]);
        }
        // Oturum: yönetici olarak giriş yapmış gibi
        $this->app->session()->start();
        $this->app->session()->set('auth_user_id', $this->userId);
        $this->app->session()->set('building_id', $this->buildingId);
        $this->app->auth()->forgetUser();
    }

    protected function unitId(int $doorNo): int
    {
        return (int) $this->db->fetchColumn('SELECT id FROM units WHERE building_id = ? AND door_no = ?', [$this->buildingId, (string) $doorNo]);
    }

    protected function accountId(string $type): int
    {
        return (int) $this->db->fetchColumn('SELECT id FROM accounts WHERE building_id = ? AND type = ?', [$this->buildingId, $type]);
    }
}
