<?php

declare(strict_types=1);

namespace Fang\Security;

use PDO;
use Psr\Clock\ClockInterface;

final readonly class RateLimiter
{
    private const int RETENTION_SECONDS = 86400;

    public function __construct(
        private PDO $pdo,
        private string $key,
        private ClockInterface $clock,
    ) {
    }

    public function allow(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now()->getTimestamp();
        $statement = $this->pdo->prepare(
            'INSERT INTO rate_limit_buckets (bucket_key, window_started_at, hits)
             VALUES (:bucket, :now, 1)
             ON CONFLICT (bucket_key) DO UPDATE SET
                 hits = CASE WHEN window_started_at <= :expired THEN 1 ELSE hits + 1 END,
                 window_started_at = CASE WHEN window_started_at <= :expired THEN :now ELSE window_started_at END
             RETURNING hits',
        );
        $statement->execute([
            'bucket' => hash_hmac('sha256', $scope . "\0" . $identifier, $this->key),
            'now' => $now,
            'expired' => $now - $windowSeconds,
        ]);
        $hits = (int) $statement->fetchColumn();
        $statement->closeCursor();

        if (random_int(1, 100) === 1) {
            $this->pdo->prepare('DELETE FROM rate_limit_buckets WHERE window_started_at < ?')
                ->execute([$now - self::RETENTION_SECONDS]);
        }

        return $hits <= $limit;
    }
}
