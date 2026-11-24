<?php

declare(strict_types=1);

namespace Fang\Config;

use InvalidArgumentException;

final readonly class AppConfig
{
    public function __construct(
        #[\SensitiveParameter] public string $masterKey,
        public string $databasePath,
        #[\SensitiveParameter] public string $mailerDsn,
        public string $mailFrom,
        public string $mailTo,
        public bool $cookieSecure,
    ) {
        if (strlen($masterKey) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new InvalidArgumentException('FANG_MASTER_KEY must decode to 32 bytes.');
        }
        foreach (['FANG_MAIL_FROM' => $mailFrom, 'FANG_MAIL_TO' => $mailTo] as $name => $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException($name . ' must be a valid email address.');
            }
        }
    }

    public static function fromEnvironment(#[\SensitiveParameter] array $environment): self
    {
        $encodedKey = self::required($environment, 'FANG_MASTER_KEY');
        $masterKey = base64_decode($encodedKey, true);
        if ($masterKey === false) {
            throw new InvalidArgumentException('FANG_MASTER_KEY must be valid base64.');
        }

        return new self(
            $masterKey,
            self::databasePath(self::required($environment, 'FANG_DB_PATH')),
            self::required($environment, 'FANG_MAILER_DSN'),
            self::required($environment, 'FANG_MAIL_FROM'),
            self::required($environment, 'FANG_MAIL_TO'),
            ($environment['FANG_COOKIE_SECURE'] ?? 'true') !== 'false',
        );
    }

    private static function databasePath(string $path): string
    {
        if ($path === ':memory:') {
            return $path;
        }
        $root = dirname(__DIR__, 3);
        $segments = [];
        foreach (explode('/', str_starts_with($path, '/') ? $path : $root . '/' . $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        $normalized = '/' . implode('/', $segments);
        $directory = realpath(dirname($normalized)) ?: dirname($normalized);
        $webRoot = realpath($root . '/frontend/public') ?: $root . '/frontend/public';
        if (str_starts_with($directory . '/', $webRoot . '/')) {
            throw new InvalidArgumentException('FANG_DB_PATH must be outside the web root.');
        }

        return $normalized;
    }

    private static function required(#[\SensitiveParameter] array $environment, string $name): string
    {
        $value = $environment[$name] ?? '';
        if ($value === '') {
            throw new InvalidArgumentException($name . ' is required.');
        }

        return $value;
    }
}
