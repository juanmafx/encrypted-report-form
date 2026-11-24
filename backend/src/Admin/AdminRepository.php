<?php

declare(strict_types=1);

namespace Fang\Admin;

use PDO;
use Psr\Clock\ClockInterface;

final readonly class AdminRepository
{
    public function __construct(private PDO $pdo, private ClockInterface $clock)
    {
    }

    public function findByUsername(string $username): ?AdminUser
    {
        $statement = $this->pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
        $statement->execute([$username]);

        return $this->hydrate($statement->fetch());
    }

    public function findById(int $id): ?AdminUser
    {
        $statement = $this->pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $statement->execute([$id]);

        return $this->hydrate($statement->fetch());
    }

    public function create(string $username, string $passwordHash, string $totpSecretCiphertext): void
    {
        $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, totp_secret_ct, created_at) VALUES (?, ?, ?, ?)',
        )->execute([$username, $passwordHash, $totpSecretCiphertext, $this->clock->now()->getTimestamp()]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public function advanceTotpStep(int $id, int $step): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users SET last_totp_step = ? WHERE id = ? AND last_totp_step < ?',
        );
        $statement->execute([$step, $id, $step]);

        return $statement->rowCount() === 1;
    }

    private function hydrate(mixed $row): ?AdminUser
    {
        if (!is_array($row)) {
            return null;
        }

        return new AdminUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            (string) $row['totp_secret_ct'],
            (int) $row['last_totp_step'],
        );
    }
}
