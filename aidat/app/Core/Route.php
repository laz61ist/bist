<?php

declare(strict_types=1);

namespace Aidat\Core;

final class Route
{
    public ?string $name = null;
    /** @var list<string> */
    public array $middleware = [];
    public readonly string $regex;
    /** @var list<string> */
    public readonly array $paramNames;

    /** @param array{0: class-string, 1: string}|callable $handler @param list<string> $middleware */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly mixed $handler,
        array $middleware,
        private readonly string $namePrefix,
        private readonly Router $router,
    ) {
        $this->middleware = $middleware;
        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', function (array $m) use (&$names): string {
            $names[] = $m[1];
            $constraint = $m[2] ?? '[^/]+';
            return '(?P<' . $m[1] . '>' . $constraint . ')';
        }, $pattern) ?? $pattern;
        $this->regex = '#^' . $regex . '$#u';
        $this->paramNames = $names;
    }

    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;
        $this->router->register($this);
        return $this;
    }

    /** @param list<string>|string $middleware */
    public function middleware(array|string $middleware): self
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = $m;
        }
        return $this;
    }
}
