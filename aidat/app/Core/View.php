<?php

declare(strict_types=1);

namespace Aidat\Core;

use RuntimeException;
use Throwable;

/**
 * Saf PHP şablon motoru. Şablon içinde $this yardımcıları kullanılabilir:
 * e(), partial(), section()/endSection()/yield(), extend().
 */
final class View
{
    /** @var array<string, string> */
    private array $sections = [];
    /** @var list<string> */
    private array $sectionStack = [];
    private ?string $layout = null;
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $dir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $this->layout = $layout;
        $content = $this->evaluate($template, $data);
        if ($this->layout === null) {
            return $content;
        }
        $layoutName = $this->layout;
        $this->layout = null;
        $this->sections['content'] = $content;
        return $this->evaluate($layoutName, $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->evaluate($template, $data);
    }

    /** Şablon içinden layout seçimi. */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('Açık bölüm yok.');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $data */
    private function evaluate(string $template, array $data): string
    {
        $file = $this->dir . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Şablon bulunamadı: {$template}");
        }
        $data = array_merge($this->shared, $data);
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
