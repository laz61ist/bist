<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\AuthorizationException;

/**
 * Yetki denetimi. Sunucu tarafında zorunlu; ekran gizlemek güvenlik sayılmaz.
 * Süper yönetici (users.role = admin) her şeye yetkili. Diğer kullanıcıların
 * yetkisi building_users.role + (varsa) building_users.permissions JSON ile belirlenir.
 */
final class Gate
{
    /** @var array<string, list<string>> building_id => izin listesi */
    private array $cache = [];
    /** @var array<int, array<string, mixed>>|null */
    private ?array $memberships = null;

    public function __construct(private readonly Database $db, private readonly Auth $auth, private readonly Config $config)
    {
    }

    public function isAdmin(): bool
    {
        return $this->auth->role() === 'admin';
    }

    /** @return array<int, array<string, mixed>> building_id => üyelik satırı */
    public function memberships(): array
    {
        if ($this->memberships !== null) {
            return $this->memberships;
        }
        $userId = $this->auth->id();
        if ($userId === null) {
            return $this->memberships = [];
        }
        $rows = $this->db->fetchAll(
            'SELECT bu.*, b.name AS building_name FROM building_users bu JOIN buildings b ON b.id = bu.building_id WHERE bu.user_id = ? AND b.is_active = 1 ORDER BY b.name',
            [$userId],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['building_id']] = $row;
        }
        return $this->memberships = $out;
    }

    /** @return array<string, mixed>|null */
    public function membership(int $buildingId): ?array
    {
        return $this->memberships()[$buildingId] ?? null;
    }

    public function roleIn(int $buildingId): ?string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }
        return $this->membership($buildingId)['role'] ?? null;
    }

    /** @return list<string> */
    public function permissionsIn(int $buildingId): array
    {
        if (isset($this->cache[$buildingId])) {
            return $this->cache[$buildingId];
        }
        if ($this->isAdmin()) {
            return $this->cache[$buildingId] = ['*'];
        }
        $m = $this->membership($buildingId);
        if ($m === null) {
            return $this->cache[$buildingId] = [];
        }
        $custom = $m['permissions'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $decoded = json_decode($custom, true);
            if (is_array($decoded)) {
                return $this->cache[$buildingId] = array_values(array_map('strval', $decoded));
            }
        }
        $role = (string) $m['role'];
        $perms = $this->config->get("permissions.roles.{$role}.permissions", []);
        return $this->cache[$buildingId] = is_array($perms) ? $perms : [];
    }

    public function allows(string $permission, ?int $buildingId = null): bool
    {
        if (!$this->auth->check()) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        if ($buildingId === null) {
            return false;
        }
        $perms = $this->permissionsIn($buildingId);
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public function authorize(string $permission, ?int $buildingId = null): void
    {
        if (!$this->allows($permission, $buildingId)) {
            throw new AuthorizationException();
        }
    }

    /** Kullanıcının yönetim paneline (portal dışı) girebileceği bir yapı var mı? */
    public function hasManagementAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        foreach ($this->memberships() as $m) {
            $portal = (bool) $this->config->get("permissions.roles.{$m['role']}.portal", false);
            if (!$portal) {
                return true;
            }
        }
        return false;
    }

    /** @return list<int> yönetim erişimi olan yapı kimlikleri */
    public function managedBuildingIds(): array
    {
        if ($this->isAdmin()) {
            return array_map('intval', array_column($this->db->fetchAll('SELECT id FROM buildings WHERE is_active = 1 ORDER BY name'), 'id'));
        }
        $ids = [];
        foreach ($this->memberships() as $id => $m) {
            $portal = (bool) $this->config->get("permissions.roles.{$m['role']}.portal", false);
            if (!$portal) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->memberships = null;
    }
}
