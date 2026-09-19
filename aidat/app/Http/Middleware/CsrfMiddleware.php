<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isPost()) {
            $token = $request->body['_token'] ?? $request->header('X-CSRF-TOKEN');
            if (!$this->app->csrf()->verify(is_string($token) ? $token : null)) {
                throw new HttpException(419, 'Oturum süresi dolmuş veya form geçersiz. Sayfayı yenileyip tekrar deneyin.');
            }
        }
        return $next($request);
    }
}
