<?php

declare(strict_types=1);

namespace Fang\Console;

use Fang\Admin\AdminRepository;
use Fang\Admin\Authenticator;
use Fang\Application;
use Fang\Config\AppConfig;
use Fang\Persistence\Database;
use Fang\Security\DataCipher;
use Fang\Security\KeyRing;
use Fang\Security\PasswordHasher;
use Fang\Security\SystemClock;
use Fang\Security\TotpService;
use InvalidArgumentException;

final class Console
{
    private const string USAGE = "Usage: fang key:generate | db:migrate | admin:create <username>\n";

    public function run(array $arguments): int
    {
        try {
            return match ($arguments[1] ?? '') {
                'key:generate' => $this->generateKey(),
                'db:migrate' => $this->migrate(),
                'admin:create' => $this->createAdmin((string) ($arguments[2] ?? '')),
                default => $this->usage(),
            };
        } catch (InvalidArgumentException $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");

            return 1;
        }
    }

    private function usage(): int
    {
        fwrite(STDERR, self::USAGE);

        return 2;
    }

    private function generateKey(): int
    {
        echo base64_encode(sodium_crypto_kdf_keygen()), "\n";

        return 0;
    }

    private function migrate(): int
    {
        $config = AppConfig::fromEnvironment(getenv());
        $previousMask = umask(0077);
        $pdo = Database::connect($config->databasePath);
        Database::migrate($pdo, Application::ROOT . '/db/schema.sql');
        umask($previousMask);
        echo "Schema applied.\n";

        return 0;
    }

    private function createAdmin(string $username): int
    {
        if (preg_match('/\A[A-Za-z0-9._-]{3,64}\z/', $username) !== 1) {
            throw new InvalidArgumentException('Username must be 3-64 characters: letters, digits, dot, dash, underscore.');
        }
        $config = AppConfig::fromEnvironment(getenv());
        $clock = new SystemClock();
        $admins = new AdminRepository(Database::connect($config->databasePath), $clock);
        if ($admins->findByUsername($username) !== null) {
            throw new InvalidArgumentException('That username already exists.');
        }
        fwrite(STDERR, 'Password (min ' . PasswordHasher::MIN_LENGTH . " characters, read from stdin): ");
        $password = rtrim((string) fgets(STDIN), "\r\n");
        $hash = (new PasswordHasher())->hash($password);
        $totp = new TotpService($clock);
        $secret = $totp->generateSecret();
        $cipher = new DataCipher((new KeyRing($config->masterKey))->dataKey());
        $admins->create($username, $hash, $cipher->encrypt($secret, Authenticator::totpContext($username)));
        fwrite(STDERR, "\nAdmin created. Add this URI to an authenticator app. It is shown only once.\n");
        echo $totp->provisioningUri($secret, $username), "\n";

        return 0;
    }
}
