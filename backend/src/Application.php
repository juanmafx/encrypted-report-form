<?php

declare(strict_types=1);

namespace Fang;

use Fang\Admin\AdminRepository;
use Fang\Admin\Authenticator;
use Fang\Config\AppConfig;
use Fang\Http\Controller\AdminController;
use Fang\Http\Controller\ReportController;
use Fang\Http\Kernel;
use Fang\Http\Renderer;
use Fang\Mail\ReportNotifier;
use Fang\Persistence\Database;
use Fang\Reports\ReportRepository;
use Fang\Reports\ReportValidator;
use Fang\Security\CsrfGuard;
use Fang\Security\DataCipher;
use Fang\Security\KeyRing;
use Fang\Security\PasswordHasher;
use Fang\Security\RateLimiter;
use Fang\Security\SystemClock;
use Fang\Security\TotpService;
use Fang\Session\NativeSession;
use Fang\Session\SessionStore;
use PDO;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

final class Application
{
    public const string ROOT = __DIR__ . '/../..';

    public static function kernelFromEnvironment(): Kernel
    {
        $config = AppConfig::fromEnvironment(getenv());
        $mailer = new Mailer(Transport::fromDsn($config->mailerDsn));

        return self::kernel(
            $config,
            Database::connectExisting($config->databasePath),
            new NativeSession($config->cookieSecure),
            $mailer,
            new SystemClock(),
            new PasswordHasher(),
        );
    }

    public static function kernel(
        AppConfig $config,
        PDO $pdo,
        SessionStore $session,
        MailerInterface $mailer,
        ClockInterface $clock,
        PasswordHasher $hasher,
    ): Kernel {
        $keys = new KeyRing($config->masterKey);
        $cipher = new DataCipher($keys->dataKey());
        $renderer = new Renderer(self::ROOT . '/frontend/templates');
        $csrf = new CsrfGuard($session);
        $limiter = new RateLimiter($pdo, $keys->rateLimitKey(), $clock);
        $reports = new ReportRepository($pdo, $cipher, $clock);
        $admins = new AdminRepository($pdo, $clock);
        $authenticator = new Authenticator($admins, $hasher, new TotpService($clock), $cipher);

        return new Kernel(
            new ReportController(
                $renderer,
                $csrf,
                new ReportValidator(),
                $reports,
                new ReportNotifier($mailer, $config->mailFrom, $config->mailTo),
                $limiter,
            ),
            new AdminController($renderer, $session, $csrf, $authenticator, $admins, $reports, $limiter, $clock),
            $csrf,
            $renderer,
            $config->cookieSecure,
        );
    }
}
