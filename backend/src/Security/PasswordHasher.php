<?php

declare(strict_types=1);

namespace Fang\Security;

use InvalidArgumentException;

final class PasswordHasher
{
    public const int MIN_LENGTH = 14;
    public const int MAX_LENGTH = 1024;

    private ?string $decoyHash = null;

    public function __construct(
        private readonly int $memoryCost = 65536,
        private readonly int $timeCost = 3,
    ) {
    }

    public function hash(#[\SensitiveParameter] string $password): string
    {
        $length = strlen($password);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Password must be between 14 and 1024 bytes.');
        }

        return password_hash($password, PASSWORD_ARGON2ID, $this->options());
    }

    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        if (strlen($password) > self::MAX_LENGTH) {
            $this->burnDecoy($password);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function burnDecoy(#[\SensitiveParameter] string $password): void
    {
        password_verify(substr($password, 0, self::MAX_LENGTH), $this->decoyHash());
    }

    public function decoyHash(): string
    {
        return $this->decoyHash ??= sprintf(
            '$argon2id$v=19$m=%d,t=%d,p=1$%s$%s',
            $this->memoryCost,
            $this->timeCost,
            rtrim(base64_encode(random_bytes(16)), '='),
            rtrim(base64_encode(random_bytes(32)), '='),
        );
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options());
    }

    private function options(): array
    {
        return ['memory_cost' => $this->memoryCost, 'time_cost' => $this->timeCost, 'threads' => 1];
    }
}
