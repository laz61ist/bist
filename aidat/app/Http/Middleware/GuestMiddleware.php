<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->app->auth()->check()) {
            return Response::redirect($this->app->router()->url('home'));
        }
        return $next($request);
    }
}
