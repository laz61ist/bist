<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Exceptions\HttpException;

abstract class Controller
{
    protected readonly Database $db;
    protected string $layout = 'layouts.app';

    public function __construct(protected readonly Application $app, protected readonly Request $request)
    {
        $this->db = $app->db();
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        $data['title'] ??= '';
        $data['currentUser'] = $this->app->auth()->user();
        $data['building'] = $this->hasBuilding() ? $this->building() : null;
        return Response::html($this->app->view()->render($template, $data, $layout ?? $this->layout));
    }

    /** @param array<string, mixed> $params */
    protected function route(string $name, array $params = []): string
    {
        return $this->app->router()->url($name, $params);
    }

    /** @param array<string, mixed> $params */
    protected function redirectRoute(string $name, array $params = []): Response
    {
        return Response::redirect($this->route($name, $params));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        $ref = $this->request->header('Referer');
        return Response::redirect($ref !== null && $ref !== '' ? $ref : $fallback);
    }

    /** @param array<mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function success(string $message): void
    {
        $this->app->session()->flash('success', $message);
    }

    protected function error(string $message): void
    {
        $this->app->session()->flash('error', $message);
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    protected function validate(array $rules, array $labels = [], ?string $redirectTo = null): array
    {
        return Validator::make($this->request->all(), $rules, $labels, $this->db)->validate($redirectTo);
    }

    /** @return array<string, mixed> */
    protected function user(): array
    {
        return $this->app->auth()->user() ?? throw new HttpException(401, 'Giriş yapmalısınız.');
    }

    protected function userId(): int
    {
        return (int) $this->user()['id'];
    }

    protected function hasBuilding(): bool
    {
        $id = $this->app->session()->get('building_id');
        return is_int($id) && $id > 0;
    }

    protected function buildingId(): int
    {
        $id = $this->app->session()->get('building_id');
        if (!is_int($id) || $id <= 0) {
            throw new HttpException(400, 'Önce bir yapı seçin.');
        }
        return $id;
    }

    /** @return array<string, mixed> */
    protected function building(): array
    {
        static $cache = [];
        $id = $this->buildingId();
        if (!isset($cache[$id])) {
            $cache[$id] = $this->db->fetch('SELECT * FROM buildings WHERE id = ?', [$id]) ?? throw new HttpException(404, 'Yapı bulunamadı.');
        }
        return $cache[$id];
    }

    protected function can(string $permission): bool
    {
        return $this->app->gate()->allows($permission, $this->hasBuilding() ? $this->buildingId() : null);
    }

    protected function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new AuthorizationException();
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new */
    protected function audit(string $action, string $entityType, ?int $entityId = null, ?array $old = null, ?array $new = null, ?string $summary = null): void
    {
        $this->app->audit()->log($action, $entityType, $entityId, $old, $new, $this->hasBuilding() ? $this->buildingId() : null, $summary);
    }

    protected function setting(string $key, ?string $default = null): ?string
    {
        return $this->app->settings()->get($this->hasBuilding() ? $this->buildingId() : null, $key, $default);
    }

    /** Yapıya ait kaydı getirir; başka yapının kaydına erişim 404 döner (IDOR koruması). */
    protected function findOwned(string $table, int $id, string $buildingColumn = 'building_id'): array
    {
        $row = $this->db->fetch("SELECT * FROM `{$table}` WHERE id = ? AND `{$buildingColumn}` = ?", [$id, $this->buildingId()]);
        return $row ?? throw new HttpException(404, 'Kayıt bulunamadı.');
    }

    protected function paginator(int $total, int $perPage = 25): Paginator
    {
        return new Paginator($total, $this->request->int('sayfa', 1), $this->request->int('adet', $perPage));
    }

    /** @return array<string, mixed> */
    protected function lists(string ...$names): array
    {
        $out = [];
        foreach ($names as $name) {
            $out[$name] = $this->app->config()->get('lists.' . $name, []);
        }
        return $out;
    }
}
