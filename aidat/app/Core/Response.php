<?php

declare(strict_types=1);

namespace Aidat\Core;

/** HTTP yanıtı. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'dosya';
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $safe, rawurlencode($filename)),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function file(string $path, string $filename, string $mime, bool $inline = true): self
    {
        $content = file_get_contents($path);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'dosya';
        return new self($content === false ? '' : $content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $inline ? 'inline' : 'attachment', $safe, rawurlencode($filename)),
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
