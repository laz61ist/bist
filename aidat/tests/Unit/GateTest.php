<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Database;
use Aidat\Tests\TestCase;

final class GateTest extends TestCase
{
    public function testYoneticiHerseyeYetkili(): void
    {
        self::assertTrue($this->app->gate()->allows('payments.cancel', $this->buildingId));
        self::assertFalse($this->app->gate()->allows('payments.cancel', 999));
    }

    public function testDenetciSaltOkunur(): void
    {
        $uid = $this->db->insert('users', ['name' => 'Denetçi', 'email' => 'd@aidat.local', 'password_hash' => 'x', 'role' => 'auditor', 'is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->db->insert('building_users', ['user_id' => $uid, 'building_id' => $this->buildingId, 'role' => 'auditor', 'created_at' => Database::now()]);
        $this->app->session()->set('auth_user_id', $uid);
        $this->app->auth()->forgetUser();
        $this->app->gate()->flush();
        self::assertTrue($this->app->gate()->allows('payments.view', $this->buildingId));
        self::assertTrue($this->app->gate()->allows('audit.view', $this->buildingId));
        self::assertFalse($this->app->gate()->allows('payments.create', $this->buildingId));
        self::assertFalse($this->app->gate()->allows('users.manage', $this->buildingId));
    }

    public function testOzelYetkiSetiRoluEzer(): void
    {
        $uid = $this->db->insert('users', ['name' => 'Görevli', 'email' => 'g@aidat.local', 'password_hash' => 'x', 'role' => 'staff', 'is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->db->insert('building_users', ['user_id' => $uid, 'building_id' => $this->buildingId, 'role' => 'staff', 'permissions' => json_encode(['payments.create']), 'created_at' => Database::now()]);
        $this->app->session()->set('auth_user_id', $uid);
        $this->app->auth()->forgetUser();
        $this->app->gate()->flush();
        self::assertTrue($this->app->gate()->allows('payments.create', $this->buildingId));
        self::assertFalse($this->app->gate()->allows('requests.manage', $this->buildingId));
    }

    public function testMalikYonetimAlaninaGiremez(): void
    {
        $uid = $this->db->insert('users', ['name' => 'Malik', 'email' => 'm@aidat.local', 'password_hash' => 'x', 'role' => 'owner', 'is_active' => 1, 'created_at' => Database::now(), 'updated_at' => Database::now()]);
        $this->db->insert('building_users', ['user_id' => $uid, 'building_id' => $this->buildingId, 'role' => 'owner', 'created_at' => Database::now()]);
        $this->app->session()->set('auth_user_id', $uid);
        $this->app->auth()->forgetUser();
        $this->app->gate()->flush();
        self::assertFalse($this->app->gate()->hasManagementAccess());
        self::assertSame([], $this->app->gate()->managedBuildingIds());
    }
}
