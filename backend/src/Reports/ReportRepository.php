<?php

declare(strict_types=1);

namespace Fang\Reports;

use Fang\Security\DataCipher;
use PDO;
use Psr\Clock\ClockInterface;

final readonly class ReportRepository
{
    private const array ENCRYPTED_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'occurred_when',
        'duration',
        'alien_count',
        'alien_description',
        'alien_actions',
        'comments',
    ];

    public function __construct(
        private PDO $pdo,
        private DataCipher $cipher,
        private ClockInterface $clock,
    ) {
    }

    public function insert(array $values): string
    {
        $publicId = bin2hex(random_bytes(16));
        $columns = ['public_id', 'fang_spotted', 'created_at'];
        $parameters = [
            'public_id' => $publicId,
            'fang_spotted' => $values['fang_spotted'],
            'created_at' => $this->clock->now()->getTimestamp(),
        ];
        foreach (self::ENCRYPTED_FIELDS as $field) {
            $columns[] = $field . '_ct';
            $parameters[$field . '_ct'] = $this->cipher->encrypt($values[$field], $this->context($publicId, $field));
        }
        $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns));
        $this->pdo->prepare(
            'INSERT INTO abduction_reports (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
        )->execute($parameters);

        return $publicId;
    }

    public function recent(int $limit = 100): array
    {
        $statement = $this->pdo->prepare(
            'SELECT public_id, first_name_ct, last_name_ct, fang_spotted, created_at
             FROM abduction_reports ORDER BY created_at DESC, id DESC LIMIT :limit',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $reports = [];
        foreach ($statement->fetchAll() as $row) {
            $publicId = (string) $row['public_id'];
            $reports[] = [
                'public_id' => $publicId,
                'first_name' => $this->cipher->decrypt((string) $row['first_name_ct'], $this->context($publicId, 'first_name')),
                'last_name' => $this->cipher->decrypt((string) $row['last_name_ct'], $this->context($publicId, 'last_name')),
                'fang_spotted' => (string) $row['fang_spotted'],
                'created_at' => (int) $row['created_at'],
            ];
        }

        return $reports;
    }

    public function find(string $publicId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM abduction_reports WHERE public_id = ?');
        $statement->execute([$publicId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $report = [
            'public_id' => $publicId,
            'fang_spotted' => (string) $row['fang_spotted'],
            'created_at' => (int) $row['created_at'],
        ];
        foreach (self::ENCRYPTED_FIELDS as $field) {
            $report[$field] = $this->cipher->decrypt((string) $row[$field . '_ct'], $this->context($publicId, $field));
        }

        return $report;
    }

    public function delete(string $publicId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM abduction_reports WHERE public_id = ?');
        $statement->execute([$publicId]);

        return $statement->rowCount() === 1;
    }

    private function context(string $publicId, string $field): string
    {
        return 'abduction_reports|' . $publicId . '|' . $field;
    }
}
