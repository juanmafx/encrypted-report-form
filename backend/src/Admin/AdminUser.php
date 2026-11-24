<?php

declare(strict_types=1);

namespace Fang\Admin;

final readonly class AdminUser
{
    public function __construct(
        public int $id,
        public string $username,
        public string $passwordHash,
        public string $totpSecretCiphertext,
        public int $lastTotpStep,
    ) {
    }
}
