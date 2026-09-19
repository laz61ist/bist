<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

/**
 * Yönetim alanı: kullanıcının yönetim erişimi olan bir yapı seçili olmalı.
 * Seçili yapı yoksa ilk erişilebilir yapı seçilir; hiç yoksa yapı oluşturma ekranına gidilir.
 */
final class BuildingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $gate = $this->app->gate();
        $session = $this->app->session();
        if (!$gate->hasManagementAccess()) {
            // Yalnızca portal erişimi olan kullanıcı yönetim alanına giremez
            if ($this->app->router()->hasRoute('portal.home')) {
                return Response::redirect($this->app->router()->url('portal.home'));
            }
            throw new AuthorizationException('Yönetim alanına erişim yetkiniz yok.');
        }
        $ids = $gate->managedBuildingIds();
        $current = $session->get('building_id');
        if (!is_int($current) || !in_array($current, $ids, true)) {
            if ($ids === []) {
                $router = $this->app->router();
                if ($request->path !== $router->url('buildings.create') && $request->path !== $router->url('buildings.store')) {
                    if ($gate->isAdmin()) {
                        $session->flash('info', 'Henüz yapı yok. İlk yapınızı oluşturun.');
                        return Response::redirect($router->url('buildings.create'));
                    }
                    throw new AuthorizationException('Size atanmış bir yapı yok. Yöneticinizle iletişime geçin.');
                }
                $session->remove('building_id');
                return $next($request);
            }
            $session->set('building_id', $ids[0]);
        }
        return $next($request);
    }
}
