<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

/** can:izin.anahtari — seçili yapıda yetki denetimi. */
final class CanMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $buildingId = $this->app->session()->get('building_id');
        $perms = array_filter(explode(',', (string) $this->arg));
        foreach ($perms as $perm) {
            if ($this->app->gate()->allows(trim($perm), is_int($buildingId) ? $buildingId : null)) {
                return $next($request);
            }
        }
        throw new AuthorizationException();
    }
}
