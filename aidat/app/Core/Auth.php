<?php

declare(strict_types=1);

namespace Aidat\Core;

/** Oturum tabanlı kimlik doğrulama; "beni hatırla" çerezi seçici:doğrulayıcı modeliyle. */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';
    private const COOKIE = 'aidat_remember';
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    /** @var array<string, mixed>|null|false */
    private array|null|false $user = false;

    public function __construct(private readonly Database $db, private readonly Session $session, private readonly bool $secureCookie = false)
    {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->user !== false) {
            return $this->user;
        }
        $id = $this->session->get(self::SESSION_KEY);
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $user = $this->db->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $id]);
            if ($user !== null) {
                return $this->user = $user;
            }
            $this->session->remove(self::SESSION_KEY);
        }
        $this->user = $this->loginFromCookie();
        return $this->user;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u === null ? null : (int) $u['id'];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    /** @return array{ok: bool, error?: string, user?: array<string, mixed>} */
    public function attempt(string $email, string $password, bool $remember, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $lock = $this->db->fetch('SELECT * FROM login_attempts WHERE email = ?', [$email]);
        if ($lock !== null && $lock['locked_until'] !== null && $lock['locked_until'] > Database::now()) {
            return ['ok' => false, 'error' => 'Çok fazla başarısız deneme. ' . Dates::trDateTime($lock['locked_until']) . ' sonrasında tekrar deneyin.'];
        }
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailure($email, $ip, $lock);
            return ['ok' => false, 'error' => 'E-posta veya şifre hatalı.'];
        }
        if ((int) $user['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Hesabınız pasif durumda. Yöneticinizle iletişime geçin.'];
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [(int) $user['id']]);
        }
        $this->db->delete('login_attempts', 'email = ?', [$email]);
        $this->login($user, $remember);
        return ['ok' => true, 'user' => $user];
    }

    /** @param array<string, mixed> $user */
    public function login(array $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        $this->user = $user;
        $this->db->update('users', ['last_login_at' => Database::now()], 'id = ?', [(int) $user['id']]);
        if ($remember) {
            $this->issueRememberCookie((int) $user['id']);
        }
    }

    public function logout(): void
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($cookie) && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            $this->db->delete('user_tokens', 'selector = ?', [$selector]);
        }
        if (!headers_sent()) {
            setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $this->secureCookie]);
        }
        $this->user = null;
        $this->session->destroy();
    }

    private function issueRememberCookie(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + 60 * 60 * 24 * 30;
        $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', $expires),
            'created_at' => Database::now(),
        ]);
        if (!headers_sent()) {
            setcookie(self::COOKIE, $selector . ':' . $validator, [
                'expires' => $expires,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $this->secureCookie,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function loginFromCookie(): ?array
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);
        $token = $this->db->fetch('SELECT * FROM user_tokens WHERE selector = ? AND expires_at > ?', [$selector, Database::now()]);
        if ($token === null || !hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            return null;
        }
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $token['user_id']]);
        if ($user === null) {
            return null;
        }
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        return $user;
    }

    /** @param array<string, mixed>|null $lock */
    private function recordFailure(string $email, string $ip, ?array $lock): void
    {
        $attempts = ($lock['attempts'] ?? 0) + 1;
        $lockedUntil = $attempts >= self::MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
        if ($lock === null) {
            $this->db->insert('login_attempts', ['email' => $email, 'ip' => $ip, 'attempts' => $attempts, 'locked_until' => $lockedUntil, 'updated_at' => Database::now()]);
        } else {
            $this->db->update('login_attempts', ['ip' => $ip, 'attempts' => $lockedUntil ? 0 : $attempts, 'locked_until' => $lockedUntil, 'updated_at' => Database::now()], 'email = ?', [$email]);
        }
    }

    public function forgetUser(): void
    {
        $this->user = false;
    }
}
