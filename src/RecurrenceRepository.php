<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk;

use JsonException;
use PDO;
use RuntimeException;

final class RecurrenceRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly RecurrenceValidator $validator = new RecurrenceValidator(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $validated = $this->validator->validate(array_merge([
            'enabled' => true,
            'notify_customer' => false,
            'custom_fields' => [],
        ], $data));
        $now = self::utcNow();

        $statement = $this->connection->prepare(
            'INSERT INTO recurrences (
                name, enabled, timezone, interval_value, interval_unit, next_run_at,
                quantity, customer_id, category_id, priority_name, status_id,
                owner_id, openedby_id, subject, message, notify_customer,
                custom_fields_json, created_at, updated_at
            ) VALUES (
                :name, :enabled, :timezone, :interval_value, :interval_unit, :next_run_at,
                :quantity, :customer_id, :category_id, :priority_name, :status_id,
                :owner_id, :openedby_id, :subject, :message, :notify_customer,
                :custom_fields_json, :created_at, :updated_at
            )'
        );
        $statement->execute($this->databaseValues($validated, $now, $now));

        $id = (int) $this->connection->lastInsertId();
        $created = $this->findById($id);

        if ($created === null) {
            throw new RuntimeException("A recorrência {$id} foi inserida, mas não pôde ser relida.");
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

        $statement = $this->connection->prepare('SELECT * FROM recurrences WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $statement = $this->connection->query('SELECT * FROM recurrences ORDER BY id');
        $recurrences = [];

        foreach ($statement->fetchAll() as $row) {
            $recurrences[] = $this->hydrate($row);
        }

        return $recurrences;
    }

    /**
     * Atualiza uma recorrência com dados completos ou parciais.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $changes): ?array
    {
        $current = $this->findById($id);

        if ($current === null) {
            return null;
        }

        unset($changes['id'], $changes['created_at'], $changes['updated_at']);
        $validated = $this->validator->validate(array_merge($current, $changes));
        $updatedAt = self::utcNow();
        $values = $this->databaseValues($validated, (string) $current['created_at'], $updatedAt);
        $values['id'] = $id;

        $statement = $this->connection->prepare(
            'UPDATE recurrences SET
                name = :name,
                enabled = :enabled,
                timezone = :timezone,
                interval_value = :interval_value,
                interval_unit = :interval_unit,
                next_run_at = :next_run_at,
                quantity = :quantity,
                customer_id = :customer_id,
                category_id = :category_id,
                priority_name = :priority_name,
                status_id = :status_id,
                owner_id = :owner_id,
                openedby_id = :openedby_id,
                subject = :subject,
                message = :message,
                notify_customer = :notify_customer,
                custom_fields_json = :custom_fields_json,
                updated_at = :updated_at
             WHERE id = :id'
        );

        unset($values['created_at']);
        $statement->execute($values);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setEnabled(int $id, bool $enabled): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $statement = $this->connection->prepare(
            'UPDATE recurrences
             SET enabled = :enabled, updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'updated_at' => self::utcNow(),
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, int|string>
     */
    private function databaseValues(array $validated, string $createdAt, string $updatedAt): array
    {
        return [
            'name' => $validated['name'],
            'enabled' => $validated['enabled'] ? 1 : 0,
            'timezone' => $validated['timezone'],
            'interval_value' => $validated['interval_value'],
            'interval_unit' => $validated['interval_unit'],
            'next_run_at' => $validated['next_run_at'],
            'quantity' => $validated['quantity'],
            'customer_id' => $validated['customer_id'],
            'category_id' => $validated['category_id'],
            'priority_name' => $validated['priority_name'],
            'status_id' => $validated['status_id'],
            'owner_id' => $validated['owner_id'],
            'openedby_id' => $validated['openedby_id'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'notify_customer' => $validated['notify_customer'] ? 1 : 0,
            'custom_fields_json' => $this->encodeCustomFields($validated['custom_fields']),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param array<string, string> $customFields
     */
    private function encodeCustomFields(array $customFields): string
    {
        if ($customFields === []) {
            return '{}';
        }

        try {
            return json_encode(
                $customFields,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $error) {
            throw new RuntimeException('Falha ao serializar custom_fields.', 0, $error);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        try {
            $customFields = json_decode((string) $row['custom_fields_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException(
                "custom_fields_json inválido na recorrência {$row['id']}.",
                0,
                $error
            );
        }

        if (!is_array($customFields)) {
            throw new RuntimeException("custom_fields_json não contém um objeto na recorrência {$row['id']}.");
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'enabled' => (bool) $row['enabled'],
            'timezone' => (string) $row['timezone'],
            'interval_value' => (int) $row['interval_value'],
            'interval_unit' => (string) $row['interval_unit'],
            'next_run_at' => (string) $row['next_run_at'],
            'quantity' => (int) $row['quantity'],
            'customer_id' => (int) $row['customer_id'],
            'category_id' => (int) $row['category_id'],
            'priority_name' => (string) $row['priority_name'],
            'status_id' => (int) $row['status_id'],
            'owner_id' => (int) $row['owner_id'],
            'openedby_id' => (int) $row['openedby_id'],
            'subject' => (string) $row['subject'],
            'message' => (string) $row['message'],
            'notify_customer' => (bool) $row['notify_customer'],
            'custom_fields' => $customFields,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function utcNow(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
