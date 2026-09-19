<?php

declare(strict_types=1);

namespace Aidat\Core;

/** Güvenli oturum: HttpOnly, SameSite=Lax, sabitleme koruması. */
final class Session
{
    private bool $started = false;

    public function __construct(private readonly string $name = 'aidat_session', private readonly bool $secure = false)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        if (PHP_SAPI === 'cli' && !headers_sent()) {
            // Testlerde çerez başlığı gönderilemez; uyarıyı bastır.
            @session_name($this->name);
        } else {
            session_name($this->name);
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
        $this->started = true;
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
        // Flash mesajlarını bir istek ömrüyle sınırla
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_old'][$key] ?? $_SESSION['_flash_new'][$key] ?? $default;
    }

    /** Flash verisini bir sonraki isteğe taşır (yönlendirme zinciri için). */
    public function reflash(): void
    {
        $_SESSION['_flash_new'] = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash_new'] ?? []);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]);
            }
            @session_destroy();
        }
        $this->started = false;
    }

    public function id(): string
    {
        return session_id() ?: '';
    }
}
