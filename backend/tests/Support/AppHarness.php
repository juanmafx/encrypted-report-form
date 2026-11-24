<?php

declare(strict_types=1);

namespace Fang\Tests\Support;

use Fang\Admin\AdminRepository;
use Fang\Admin\Authenticator;
use Fang\Application;
use Fang\Config\AppConfig;
use Fang\Http\Kernel;
use Fang\Http\Request;
use Fang\Http\Response;
use Fang\Persistence\Database;
use Fang\Security\DataCipher;
use Fang\Security\KeyRing;
use Fang\Security\PasswordHasher;
use Fang\Session\ArraySession;
use OTPHP\TOTP;
use PDO;

final class AppHarness
{
    public const string USERNAME = 'site-admin';
    public const string PASSWORD = 'correct horse battery staple';
    public const string TOTP_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public readonly PDO $pdo;
    public readonly ArraySession $session;
    public readonly CapturingMailer $mailer;
    public readonly FixedClock $clock;
    public readonly Kernel $kernel;
    public readonly string $masterKey;

    public function __construct()
    {
        $this->masterKey = sodium_crypto_kdf_keygen();
        $this->pdo = Database::connect(':memory:');
        Database::migrate($this->pdo, Application::ROOT . '/db/schema.sql');
        $this->session = new ArraySession();
        $this->mailer = new CapturingMailer();
        $this->clock = new FixedClock();
        $hasher = new PasswordHasher(1024, 1);
        $config = new AppConfig($this->masterKey, ':memory:', 'null://null', 'from@example.test', 'to@example.test', true);
        $this->kernel = Application::kernel($config, $this->pdo, $this->session, $this->mailer, $this->clock, $hasher);
        $cipher = new DataCipher((new KeyRing($this->masterKey))->dataKey());
        (new AdminRepository($this->pdo, $this->clock))->create(
            self::USERNAME,
            $hasher->hash(self::PASSWORD),
            $cipher->encrypt(self::TOTP_SECRET, Authenticator::totpContext(self::USERNAME)),
        );
    }

    public function get(string $path): Response
    {
        return $this->kernel->handle(new Request('GET', $path));
    }

    public function post(string $path, array $fields, string $ip = '203.0.113.10'): Response
    {
        return $this->kernel->handle(new Request('POST', $path, $fields, $ip));
    }

    public function postWithToken(string $path, array $fields, string $ip = '203.0.113.10'): Response
    {
        return $this->post($path, [...$fields, 'csrf_token' => $this->csrfToken()], $ip);
    }

    public function csrfToken(): string
    {
        $this->get('/');

        return (string) $this->session->get('csrf_token');
    }

    public function currentCode(int $offsetSteps = 0): string
    {
        return TOTP::createFromSecret(self::TOTP_SECRET, $this->clock)->at($this->clock->timestamp + 30 * $offsetSteps);
    }

    public function signIn(): void
    {
        $this->postWithToken('/admin/login', ['username' => self::USERNAME, 'password' => self::PASSWORD]);
        $this->postWithToken('/admin/two-factor', ['code' => $this->currentCode()]);
    }

    public static function validReport(array $overrides = []): array
    {
        return [...[
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'occurred_when' => 'Last Tuesday',
            'duration' => 'Two hours',
            'alien_count' => 'Three',
            'alien_description' => 'Small and green',
            'alien_actions' => 'Asked about Fang',
            'fang_spotted' => 'yes',
            'comments' => 'Please find the dog',
        ], ...$overrides];
    }
}
