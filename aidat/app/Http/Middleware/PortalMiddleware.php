<?php

declare(strict_types=1);

namespace Aidat\Http\Middleware;

use Aidat\Core\Application;
use Aidat\Core\Exceptions\AuthorizationException;
use Aidat\Core\Request;
use Aidat\Core\Response;
use Closure;

/** Sakin alanı: kullanıcıya bağlı en az bir kişi kaydı + aktif oturum (malik/kiracı) olmalı. */
final class PortalMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Application $app, private readonly ?string $arg = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $this->app->auth()->id();
        $count = $this->app->db()->fetchInt(
            'SELECT COUNT(*) FROM occupancies o JOIN people p ON p.id = o.person_id WHERE p.user_id = ? AND (o.end_date IS NULL OR o.end_date >= ?)',
            [$userId, date('Y-m-d')],
        );
        if ($count === 0 && !$this->app->gate()->isAdmin()) {
            if ($this->app->gate()->hasManagementAccess()) {
                $this->app->session()->flash('info', 'Hesabınıza bağlı bir bağımsız bölüm yok; yönetim paneline yönlendirildiniz.');
                return Response::redirect($this->app->router()->url('dashboard'));
            }
            throw new AuthorizationException('Hesabınıza bağlı bir bağımsız bölüm bulunamadı. Yöneticinizle iletişime geçin.');
        }
        return $next($request);
    }
}
