<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Config\AppConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    private function environment(array $overrides = []): array
    {
        return [...[
            'FANG_MASTER_KEY' => base64_encode(random_bytes(32)),
            'FANG_DB_PATH' => '/tmp/fang.sqlite',
            'FANG_MAILER_DSN' => 'null://null',
            'FANG_MAIL_FROM' => 'from@example.test',
            'FANG_MAIL_TO' => 'to@example.test',
        ], ...$overrides];
    }

    public function testSecureCookiesAreTheDefault(): void
    {
        self::assertTrue(AppConfig::fromEnvironment($this->environment())->cookieSecure);
    }

    public function testRejectsMissingKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AppConfig::fromEnvironment($this->environment(['FANG_MASTER_KEY' => '']));
    }

    public function testRejectsShortKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AppConfig::fromEnvironment($this->environment(['FANG_MASTER_KEY' => base64_encode('short')]));
    }

    public function testRejectsInvalidRecipient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AppConfig::fromEnvironment($this->environment(['FANG_MAIL_TO' => "a@example.test\r\nBcc: b@example.test"]));
    }
}
