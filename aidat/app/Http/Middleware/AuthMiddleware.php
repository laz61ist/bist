<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->app->auth()->check()) {
            if ($request->wantsJson()) {
                return Response::json(['hata' => 'Giriş yapmalısınız.'], 401);
            }
            $this->app->session()->set('intended', $request->fullUrl());
            $this->app->session()->flash('error', 'Devam etmek için giriş yapın.');
            return Response::redirect($this->app->router()->url('login'));
        }
        return $next($request);
    }
}
