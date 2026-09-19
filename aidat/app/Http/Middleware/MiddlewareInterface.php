<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}
