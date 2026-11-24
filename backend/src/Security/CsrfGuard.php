<?php

declare(strict_types=1);

namespace Fang\Security;

use Fang\Session\SessionStore;

final readonly class CsrfGuard
{
    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private SessionStore $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->rotate();
    }

    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(mixed $submitted): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($submitted) && is_string($token) && $token !== '' && hash_equals($token, $submitted);
    }
}
