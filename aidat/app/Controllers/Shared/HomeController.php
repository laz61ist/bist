<?php

declare(strict_types=1);

namespace Aidat\Controllers\Shared;

use Aidat\Core\Controller;
use Aidat\Core\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        if (!$this->app->auth()->check()) {
            return $this->redirectRoute('login');
        }
        if ($this->app->gate()->hasManagementAccess()) {
            return $this->redirectRoute('dashboard');
        }
        return $this->redirectRoute('portal.home');
    }

    /** Dürüst sağlık ucu: DB'ye gerçek sorgu; bozuksa 503. */
    public function health(): Response
    {
        $ok = $this->app->db()->ping();
        return Response::json(['durum' => $ok ? 'ok' : 'bozuk', 'db' => $this->app->db()->driver(), 'surum' => config('app.version')], $ok ? 200 : 503)
            ->withHeader('Cache-Control', 'no-store');
    }
}
