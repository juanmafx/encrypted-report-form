<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Persistence\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fang-db-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->directory . '/*') as $file) {
            @unlink((string) $file);
        }
        @rmdir($this->directory);
    }

    public function testConnectExistingDoesNotCreateAMissingDatabase(): void
    {
        $path = $this->directory . '/missing.sqlite';

        try {
            Database::connectExisting($path);
            self::fail('A missing database must be rejected.');
        } catch (RuntimeException) {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testRestrictPermissionsTightensExistingFiles(): void
    {
        $path = $this->directory . '/fang.sqlite';
        foreach (['', '-wal', '-shm'] as $suffix) {
            touch($path . $suffix);
            chmod($path . $suffix, 0644);
        }

        Database::restrictPermissions($path);
        clearstatcache();

        foreach (['', '-wal', '-shm'] as $suffix) {
            self::assertSame(0600, fileperms($path . $suffix) & 0777);
        }
    }
}
