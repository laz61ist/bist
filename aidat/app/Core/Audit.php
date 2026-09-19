<?php

declare(strict_types=1);

namespace Aidat\Core;

/** İşlem izi: kim, neyi, önceki/yeni değer, ne zaman, hangi yapı. */
final class Audit
{
    public function __construct(private readonly Database $db, private readonly Auth $auth)
    {
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $buildingId = null,
        ?string $summary = null,
    ): void {
        $request = Container::instance()->has('request') ? Container::instance()->get('request') : null;
        $this->db->insert('audit_logs', [
            'user_id' => $this->auth->id(),
            'building_id' => $buildingId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => $summary,
            'old_values' => $old === null ? null : json_encode(self::scrub($old), JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode(self::scrub($new), JSON_UNESCAPED_UNICODE),
            'ip' => $request instanceof Request ? $request->ip() : 'cli',
            'user_agent' => $request instanceof Request ? $request->userAgent() : 'cli',
            'created_at' => Database::now(),
        ]);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private static function scrub(array $values): array
    {
        foreach (['password', 'password_hash', 'password_confirmation', '_token', 'validator_hash'] as $k) {
            if (array_key_exists($k, $values)) {
                $values[$k] = '***';
            }
        }
        return $values;
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new @return array{0: array<string, mixed>, 1: array<string, mixed>} yalnızca değişen alanlar */
    public static function diff(array $old, array $new): array
    {
        $o = [];
        $n = [];
        foreach ($new as $k => $v) {
            if (!array_key_exists($k, $old) || (string) $old[$k] !== (string) $v) {
                $o[$k] = $old[$k] ?? null;
                $n[$k] = $v;
            }
        }
        return [$o, $n];
    }
}
