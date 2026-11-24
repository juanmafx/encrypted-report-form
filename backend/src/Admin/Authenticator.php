<?php

declare(strict_types=1);

namespace Fang\Admin;

use Fang\Security\DataCipher;
use Fang\Security\PasswordHasher;
use Fang\Security\TotpService;

final readonly class Authenticator
{
    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private TotpService $totp,
        private DataCipher $cipher,
    ) {
    }

    public function verifyPassword(string $username, #[\SensitiveParameter] string $password): ?AdminUser
    {
        $admin = $this->admins->findByUsername($username);
        if ($admin === null) {
            $this->hasher->burnDecoy($password);

            return null;
        }
        if (!$this->hasher->verify($password, $admin->passwordHash)) {
            return null;
        }
        if ($this->hasher->needsRehash($admin->passwordHash)) {
            $this->admins->updatePasswordHash($admin->id, $this->hasher->hash($password));
        }

        return $admin;
    }

    public function verifySecondFactor(AdminUser $admin, #[\SensitiveParameter] string $code): bool
    {
        $secret = $this->cipher->decrypt($admin->totpSecretCiphertext, self::totpContext($admin->username));
        $step = $this->totp->matchingStep($secret, $code);

        return $step !== null && $this->admins->advanceTotpStep($admin->id, $step);
    }

    public static function totpContext(string $username): string
    {
        return 'admin_users|totp|' . mb_strtolower($username, 'UTF-8');
    }
}
