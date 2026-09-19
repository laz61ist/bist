<?php

declare(strict_types=1);

namespace Aidat\Core;

use RuntimeException;

/** Basit servis kaydı: tekil nesneler ve tembel fabrikalar. */
final class Container
{
    private static ?Container $instance = null;

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, callable> */
    private array $factories = [];

    public static function instance(): Container
    {
        return self::$instance ??= new Container();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function set(string $id, mixed $service): void
    {
        $this->instances[$id] = $service;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }
        throw new RuntimeException("Servis bulunamadı: {$id}");
    }
}
