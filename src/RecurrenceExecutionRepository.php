<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk;

use DomainException;
use PDO;

final class RecurrenceExecutionRepository
{
    private const STATUSES = ['pending', 'running', 'succeeded', 'failed', 'partial'];

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $validated = $this->validate(array_merge([
            'status' => 'pending',
            'created_count' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
        ], $data));
        $now = self::utcNow();

        $statement = $this->connection->prepare(
            'INSERT INTO recurrence_executions (
                recurrence_id, scheduled_for, status, expected_count, created_count,
                started_at, finished_at, error_message, created_at, updated_at
            ) VALUES (
                :recurrence_id, :scheduled_for, :status, :expected_count, :created_count,
                :started_at, :finished_at, :error_message, :created_at, :updated_at
            )'
        );
        $statement->execute(array_merge($validated, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $id = (int) $this->connection->lastInsertId();
        $created = $this->findById($id);

        if ($created === null) {
            throw new DomainException("A execução {$id} foi inserida, mas não pôde ser relida.");
        }

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT * FROM recurrence_executions WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByRecurrence(int $recurrenceId): array
    {
        if ($recurrenceId < 1) {
            return [];
        }

        $statement = $this->connection->prepare(
            'SELECT * FROM recurrence_executions
             WHERE recurrence_id = :recurrence_id
             ORDER BY scheduled_for, id'
        );
        $statement->execute(['recurrence_id' => $recurrenceId]);
        $executions = [];

        foreach ($statement->fetchAll() as $row) {
            $executions[] = $this->hydrate($row);
        }

        return $executions;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, int|string|null>
     */
    private function validate(array $data): array
    {
        $errors = [];

        $recurrenceId = $this->integer($data, 'recurrence_id', 1, $errors);
        $expectedCount = $this->integer($data, 'expected_count', 1, $errors);
        $createdCount = $this->integer($data, 'created_count', 0, $errors);

        if ($createdCount > $expectedCount) {
            $errors[] = 'created_count não pode ser maior que expected_count.';
        }

        $status = $data['status'] ?? null;

        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors[] = 'status de execução inválido.';
            $status = 'pending';
        }

        $scheduledFor = $this->normalizeRequiredDate($data['scheduled_for'] ?? null, 'scheduled_for', $errors);
        $startedAt = $this->normalizeOptionalDate($data['started_at'], 'started_at', $errors);
        $finishedAt = $this->normalizeOptionalDate($data['finished_at'], 'finished_at', $errors);
        $errorMessage = $data['error_message'];

        if ($errorMessage !== null && !is_string($errorMessage)) {
            $errors[] = 'error_message deve ser texto ou null.';
            $errorMessage = null;
        }

        if ($errors !== []) {
            throw new DomainException("Execução de recorrência inválida:\n- " . implode("\n- ", $errors));
        }

        return [
            'recurrence_id' => $recurrenceId,
            'scheduled_for' => $scheduledFor,
            'status' => $status,
            'expected_count' => $expectedCount,
            'created_count' => $createdCount,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'error_message' => $errorMessage,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function integer(array $data, string $key, int $minimum, array &$errors): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value) || $value < $minimum) {
            $errors[] = "{$key} deve ser inteiro maior ou igual a {$minimum}.";
            return $minimum;
        }

        return $value;
    }

    /** @param list<string> $errors */
    private function normalizeRequiredDate(mixed $value, string $key, array &$errors): string
    {
        try {
            return RecurrenceValidator::normalizeUtc($value, $key);
        } catch (DomainException $error) {
            $errors[] = $error->getMessage();
            return '';
        }
    }

    /** @param list<string> $errors */
    private function normalizeOptionalDate(mixed $value, string $key, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->normalizeRequiredDate($value, $key, $errors);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'recurrence_id' => (int) $row['recurrence_id'],
            'scheduled_for' => (string) $row['scheduled_for'],
            'status' => (string) $row['status'],
            'expected_count' => (int) $row['expected_count'],
            'created_count' => (int) $row['created_count'],
            'started_at' => $row['started_at'] === null ? null : (string) $row['started_at'],
            'finished_at' => $row['finished_at'] === null ? null : (string) $row['finished_at'],
            'error_message' => $row['error_message'] === null ? null : (string) $row['error_message'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function utcNow(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
