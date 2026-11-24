<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Application;
use Fang\Persistence\Database;
use Fang\Security\RateLimiter;
use Fang\Tests\Support\FixedClock;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private FixedClock $clock;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->pdo = Database::connect(':memory:');
        Database::migrate($this->pdo, Application::ROOT . '/db/schema.sql');
        $this->clock = new FixedClock();
        $this->limiter = new RateLimiter($this->pdo, random_bytes(32), $this->clock);
    }

    public function testBlocksAfterTheLimit(): void
    {
        self::assertTrue($this->limiter->allow('scope', 'id', 2, 60));
        self::assertTrue($this->limiter->allow('scope', 'id', 2, 60));
        self::assertFalse($this->limiter->allow('scope', 'id', 2, 60));
    }

    public function testWindowResets(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);
        self::assertFalse($this->limiter->allow('scope', 'id', 1, 60));

        $this->clock->advance(60);

        self::assertTrue($this->limiter->allow('scope', 'id', 1, 60));
    }

    public function testScopesAndIdentifiersAreIsolated(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);

        self::assertTrue($this->limiter->allow('other', 'id', 1, 60));
        self::assertTrue($this->limiter->allow('scope', 'other', 1, 60));
    }

    public function testIdentifiersAreNotStoredInClear(): void
    {
        $this->limiter->allow('scope', '203.0.113.77', 5, 60);
        $key = (string) $this->pdo->query('SELECT bucket_key FROM rate_limit_buckets')->fetchColumn();

        self::assertStringNotContainsString('203.0.113.77', $key);
        self::assertSame(64, strlen($key));
    }
}
