<?php

declare(strict_types=1);

namespace Fang\Session;

use RuntimeException;

final class NativeSession implements SessionStore
{
    public function __construct(private readonly bool $secure)
    {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        setcookie(session_name() ?: '', '', $this->cookieOptions(time() - 3600));
        session_destroy();
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_cache_limiter('');
        session_name($this->secure ? '__Host-fang_session' : 'fang_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Session could not be started.');
        }
    }

    private function cookieOptions(int $expires): array
    {
        return ['expires' => $expires, 'path' => '/', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Strict'];
    }
}
