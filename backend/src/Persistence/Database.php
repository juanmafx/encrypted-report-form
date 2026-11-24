<?php

declare(strict_types=1);

namespace Fang\Persistence;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA secure_delete = ON');
        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $pdo;
    }

    public static function connectExisting(string $path): PDO
    {
        if ($path !== ':memory:' && !is_file($path)) {
            throw new RuntimeException('Database is not initialized. Run db:migrate first.');
        }

        return self::connect($path);
    }

    public static function restrictPermissions(string $path): void
    {
        foreach (['', '-wal', '-shm'] as $suffix) {
            if (is_file($path . $suffix) && !chmod($path . $suffix, 0600)) {
                throw new RuntimeException('Database file permissions could not be restricted.');
            }
        }
    }

    public static function migrate(PDO $pdo, string $schemaPath): void
    {
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Schema file could not be read.');
        }
        $pdo->exec($schema);
    }
}
