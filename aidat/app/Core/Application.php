<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Exceptions\HttpException;
use Aidat\Core\Exceptions\ValidationException;
use Aidat\Http\Middleware\MiddlewareInterface;
use Closure;
use Throwable;

/** Uygulama çekirdeği: servisleri kurar, isteği yönlendirir, hataları yanıta çevirir. */
final class Application
{
    private readonly Container $container;
    private bool $routesLoaded = false;

    public function __construct(public readonly string $basePath, Config $config)
    {
        $c = Container::instance();
        $this->container = $c;
        $c->set('app', $this);
        $c->set('config', $config);
        $secure = str_starts_with((string) $config->get('app.url', ''), 'https://');
        $c->singleton('db', static fn () => new Database($config->get('database', [])));
        $c->singleton('session', static fn () => new Session((string) $config->get('app.session_name', 'aidat_session'), $secure));
        $c->singleton('csrf', static fn (Container $c) => new Csrf($c->get('session')));
        $c->singleton('auth', static fn (Container $c) => new Auth($c->get('db'), $c->get('session'), $secure));
        $c->singleton('gate', static fn (Container $c) => new Gate($c->get('db'), $c->get('auth'), $config));
        $c->singleton('audit', static fn (Container $c) => new Audit($c->get('db'), $c->get('auth')));
        $c->singleton('settings', static fn (Container $c) => new Settings($c->get('db')));
        $c->singleton('router', static fn () => new Router());
        $c->singleton('view', fn () => new View($this->basePath . '/app/Views'));
        $c->singleton('logger', fn () => new Logger($this->basePath . '/storage/logs'));
        $c->singleton('signer', static function () use ($config): Signer {
            $key = (string) $config->get('app.key', '');
            if ($key === '') {
                // Anahtar yoksa kurulum bazlı türetilmiş, yine de gizli bir değer kullan.
                $key = hash('sha256', 'aidat|' . php_uname('n') . '|' . __DIR__);
            }
            return new Signer($key);
        });
        $c->singleton('uploads', fn () => new Uploads(
            $this->basePath . '/storage/uploads',
            (int) $config->get('app.upload_max_bytes', 5 * 1024 * 1024),
            (array) $config->get('app.upload_mimes', []),
        ));
    }

    public function config(): Config
    {
        return $this->container->get('config');
    }

    public function db(): Database
    {
        return $this->container->get('db');
    }

    public function session(): Session
    {
        return $this->container->get('session');
    }

    public function csrf(): Csrf
    {
        return $this->container->get('csrf');
    }

    public function auth(): Auth
    {
        return $this->container->get('auth');
    }

    public function gate(): Gate
    {
        return $this->container->get('gate');
    }

    public function audit(): Audit
    {
        return $this->container->get('audit');
    }

    public function settings(): Settings
    {
        return $this->container->get('settings');
    }

    public function router(): Router
    {
        if (!$this->routesLoaded) {
            $this->routesLoaded = true;
            $router = $this->container->get('router');
            $file = $this->basePath . '/app/routes.php';
            if (is_file($file)) {
                (static function (Router $router, string $file): void {
                    require $file;
                })($router, $file);
            }
            return $router;
        }
        return $this->container->get('router');
    }

    public function view(): View
    {
        return $this->container->get('view');
    }

    public function logger(): Logger
    {
        return $this->container->get('logger');
    }

    public function signer(): Signer
    {
        return $this->container->get('signer');
    }

    public function uploads(): Uploads
    {
        return $this->container->get('uploads');
    }

    public function request(): Request
    {
        return $this->container->get('request');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function isDebug(): bool
    {
        return (bool) $this->config()->get('app.debug', false);
    }

    public function run(): void
    {
        $response = $this->handle(Request::fromGlobals());
        $response->send();
    }

    public function handle(Request $request): Response
    {
        $this->container->set('request', $request);
        $this->auth()->forgetUser();
        $this->gate()->flush();
        try {
            $this->session()->start();
            $router = $this->router();
            $match = $router->resolve($request->method, $request->path);
            $route = $match['route'];
            $request = $request->withRouteParams($match['params']);
            $this->container->set('request', $request);

            $pipeline = $this->buildPipeline($route->middleware, function (Request $req) use ($route): Response {
                return $this->callHandler($route->handler, $req);
            });
            $response = $pipeline($request);
        } catch (ValidationException $e) {
            $response = $this->handleValidation($e, $request);
        } catch (DomainException $e) {
            $response = $this->handleDomain($e, $request);
        } catch (HttpException $e) {
            $response = $this->renderError($e->status, $e->getMessage(), $request);
            foreach ($e->headers as $k => $v) {
                $response->withHeader($k, $v);
            }
        } catch (Throwable $e) {
            $eventId = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $this->logger()->exception($e, $eventId);
            $message = $this->isDebug()
                ? get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                : "Beklenmeyen bir hata oluştu. Olay kodu: {$eventId}";
            $response = $this->renderError(500, $message, $request, $this->isDebug() ? $e->getTraceAsString() : null);
        }
        return $this->securityHeaders($response);
    }

    /** @param list<string> $middleware */
    private function buildPipeline(array $middleware, Closure $core): Closure
    {
        $aliases = $this->router()->middlewareAliases();
        $stack = $core;
        foreach (array_reverse($middleware) as $name) {
            [$alias, $arg] = array_pad(explode(':', $name, 2), 2, null);
            $class = $aliases[$alias] ?? null;
            if ($class === null || !class_exists($class)) {
                throw new \RuntimeException("Ara katman bulunamadı: {$name}");
            }
            $instance = new $class($this, $arg);
            if (!$instance instanceof MiddlewareInterface) {
                throw new \RuntimeException("Geçersiz ara katman: {$class}");
            }
            $next = $stack;
            $stack = static fn (Request $req): Response => $instance->handle($req, $next);
        }
        return $stack;
    }

    private function callHandler(mixed $handler, Request $request): Response
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            [$class, $method] = $handler;
            $controller = new $class($this, $request);
            // Rota parametrelerini metod imzasına göre tipe çevir ({id:\d+} → int)
            $args = [];
            $ref = new \ReflectionMethod($controller, $method);
            $params = $request->params();
            foreach ($ref->getParameters() as $i => $p) {
                $name = $p->getName();
                $value = $params[$name] ?? array_values($params)[$i] ?? null;
                if ($value === null) {
                    if ($p->isDefaultValueAvailable()) {
                        $args[] = $p->getDefaultValue();
                        continue;
                    }
                    break;
                }
                $type = $p->getType();
                $args[] = $type instanceof \ReflectionNamedType && $type->getName() === 'int' ? (int) $value : $value;
            }
            $result = $controller->{$method}(...$args);
        } elseif (is_callable($handler)) {
            $result = $handler($request, $this);
        } else {
            throw new \RuntimeException('Geçersiz rota işleyicisi.');
        }
        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return Response::html($result);
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html('');
    }

    private function handleValidation(ValidationException $e, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['hata' => 'Doğrulama hatası', 'alanlar' => $e->errors], 422);
        }
        $session = $this->session();
        $session->flash('errors', $e->errors);
        $input = $e->input;
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $session->flash('old', $input);
        $session->flash('error', 'Lütfen işaretli alanları düzeltin.');
        $back = $e->redirectTo ?? $request->header('Referer') ?? '/';
        return Response::redirect($back);
    }

    private function handleDomain(DomainException $e, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['hata' => $e->getMessage()], 422);
        }
        $session = $this->session();
        $session->flash('error', $e->getMessage());
        $input = $request->body;
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $session->flash('old', $input);
        return Response::redirect($request->header('Referer') ?? '/');
    }

    public function renderError(int $status, string $message, Request $request, ?string $trace = null): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['hata' => $message], $status);
        }
        try {
            $html = $this->view()->render('errors.error', [
                'status' => $status,
                'message' => $message,
                'trace' => $trace,
                'title' => match ($status) {
                    404 => 'Sayfa bulunamadı',
                    403 => 'Yetkiniz yok',
                    405 => 'Metod desteklenmiyor',
                    419 => 'Oturum süresi doldu',
                    default => 'Bir hata oluştu',
                },
            ]);
        } catch (Throwable) {
            $html = '<!doctype html><html lang="tr"><meta charset="utf-8"><title>' . $status . '</title><body style="font-family:sans-serif;padding:40px"><h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }
        return Response::html($html, $status);
    }

    private function securityHeaders(Response $response): Response
    {
        $headers = $response->headers();
        if (!isset($headers['X-Content-Type-Options'])) {
            $response->withHeader('X-Content-Type-Options', 'nosniff');
        }
        $response->withHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->withHeader('Referrer-Policy', 'same-origin');
        $response->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (!isset($headers['Content-Security-Policy'])) {
            $response->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; font-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
        }
        return $response;
    }
}
