<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly string $path)
    {
        if (trim($this->path) === '') {
            throw new RuntimeException('O caminho do banco SQLite não pode ficar vazio.');
        }
    }

    public function connect(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('A extensão PDO SQLite não está disponível neste PHP.');
        }

        $this->prepareParentDirectory();

        try {
            $connection = new PDO('sqlite:' . $this->path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $connection->exec('PRAGMA foreign_keys = ON');
            $connection->exec('PRAGMA busy_timeout = 5000');
            $journalMode = strtolower((string) $connection->query('PRAGMA journal_mode = WAL')->fetchColumn());

            if ($journalMode !== 'wal' && $this->path !== ':memory:') {
                throw new RuntimeException(
                    "SQLite não conseguiu ativar journal_mode=WAL; modo retornado: {$journalMode}."
                );
            }

            if ((int) $connection->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
                throw new RuntimeException('SQLite não confirmou PRAGMA foreign_keys=ON.');
            }

            if ($this->path !== ':memory:' && is_file($this->path)) {
                @chmod($this->path, 0660);
            }

            $this->connection = $connection;

            return $this->connection;
        } catch (PDOException $error) {
            throw new RuntimeException(
                "Não foi possível abrir o banco SQLite em {$this->path}: {$error->getMessage()}",
                0,
                $error
            );
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array{foreign_keys: int, journal_mode: string} */
    public function diagnostics(): array
    {
        $connection = $this->connect();

        return [
            'foreign_keys' => (int) $connection->query('PRAGMA foreign_keys')->fetchColumn(),
            'journal_mode' => strtolower((string) $connection->query('PRAGMA journal_mode')->fetchColumn()),
        ];
    }

    private function prepareParentDirectory(): void
    {
        if ($this->path === ':memory:') {
            return;
        }

        $parent = dirname($this->path);

        if (!is_dir($parent) && !@mkdir($parent, 0770, true) && !is_dir($parent)) {
            throw new RuntimeException("Não foi possível criar o diretório do SQLite: {$parent}");
        }

        if (!is_writable($parent)) {
            throw new RuntimeException("O diretório do SQLite não permite escrita: {$parent}");
        }
    }
}
