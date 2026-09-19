<?php

declare(strict_types=1);

namespace Aidat\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function verify(?string $candidate): bool
    {
        $token = $this->session->get(self::KEY);
        return is_string($token) && is_string($candidate) && $candidate !== '' && hash_equals($token, $candidate);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
