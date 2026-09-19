<?php

declare(strict_types=1);

namespace Aidat\Core;

use Aidat\Core\Exceptions\HttpException;
use Closure;
use RuntimeException;

/** Rota kayıt ve eşleme. Gruplar ön ek ve ara katman biriktirir. */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];
    /** @var array<string, Route> */
    private array $named = [];
    /** @var list<array{prefix: string, middleware: list<string>, name: string}> */
    private array $groupStack = [];
    /** @var array<string, class-string> */
    private array $middlewareAliases = [];

    /** @param array<string, class-string> $aliases */
    public function aliasMiddleware(array $aliases): void
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);
    }

    /** @param array{prefix?: string, middleware?: list<string>|string, name?: string} $attributes */
    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = [
            'prefix' => $attributes['prefix'] ?? '',
            'middleware' => (array) ($attributes['middleware'] ?? []),
            'name' => $attributes['name'] ?? '',
        ];
        $routes($this);
        array_pop($this->groupStack);
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $pattern, mixed $handler): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->add(strtoupper($method), $pattern, $handler);
        }
        return $route;
    }

    private function add(string $method, string $pattern, mixed $handler): Route
    {
        $prefix = '';
        $middleware = [];
        $namePrefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= rtrim($group['prefix'], '/');
            $middleware = array_merge($middleware, $group['middleware']);
            $namePrefix .= $group['name'];
        }
        $full = '/' . trim($prefix . '/' . ltrim($pattern, '/'), '/');
        $route = new Route($method, $full === '' ? '/' : $full, $handler, $middleware, $namePrefix, $this);
        $this->routes[] = $route;
        return $route;
    }

    public function register(Route $route): void
    {
        if ($route->name !== null) {
            $this->named[$route->name] = $route;
        }
    }

    /** @return array{route: Route, params: array<string, string>} */
    public function resolve(string $method, string $path): array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route->regex, $path, $m)) {
                continue;
            }
            if ($route->method !== $method) {
                $allowed[] = $route->method;
                continue;
            }
            $params = [];
            foreach ($route->paramNames as $name) {
                $params[$name] = $m[$name] ?? '';
            }
            return ['route' => $route, 'params' => $params];
        }
        if ($allowed !== []) {
            throw new HttpException(405, 'Bu yol için metod desteklenmiyor.', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw new HttpException(404, 'Sayfa bulunamadı.');
    }

    /** @param array<string, mixed> $params */
    public function url(string $name, array $params = []): string
    {
        $route = $this->named[$name] ?? null;
        if ($route === null) {
            throw new RuntimeException("Rota adı bulunamadı: {$name}");
        }
        $url = $route->pattern;
        foreach ($route->paramNames as $param) {
            if (!array_key_exists($param, $params)) {
                throw new RuntimeException("Rota parametresi eksik: {$name} -> {$param}");
            }
            $url = preg_replace('/\{' . $param . '(?::[^}]+)?\}/', rawurlencode((string) $params[$param]), $url) ?? $url;
            unset($params[$param]);
        }
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->named[$name]);
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @return array<string, class-string> */
    public function middlewareAliases(): array
    {
        return $this->middlewareAliases;
    }
}
