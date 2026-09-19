<?php

declare(strict_types=1);

namespace Aidat\Core;

/** HMAC imzalı, süreli belirteçler (dosya bağlantıları, makbuz doğrulama, şifre sıfırlama). */
final class Signer
{
    public function __construct(private readonly string $key)
    {
    }

    public function sign(string $payload, int $expiresAt): string
    {
        $data = $payload . '|' . $expiresAt;
        $sig = hash_hmac('sha256', $data, $this->key);
        return rtrim(strtr(base64_encode($data . '|' . $sig), '+/', '-_'), '=');
    }

    /** Geçerliyse payload döner, değilse null. */
    public function verify(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('|', $decoded);
        if (count($parts) < 3) {
            return null;
        }
        $sig = array_pop($parts);
        $exp = (int) array_pop($parts);
        $payload = implode('|', $parts);
        if ($exp < time()) {
            return null;
        }
        $expected = hash_hmac('sha256', $payload . '|' . $exp, $this->key);
        return hash_equals($expected, $sig) ? $payload : null;
    }
}
