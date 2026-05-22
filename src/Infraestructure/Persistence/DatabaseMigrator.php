<?php

namespace Oliveiraj\Aylin\Infraestructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseMigrator
{
    private const MIGRATIONS_TABLE = "schema_migrations";
    private const BASELINE_MIGRATION = "001_initial_schema.sql";

    public function __construct(
        private readonly string $migrationsPath = __DIR__ .
            "/../../../database/migrations",
    ) {}

    public static function migrate(PDO $conn): int
    {
        return (new self())->run($conn);
    }

    public function run(PDO $conn): int
    {
        $this->ensureMigrationsTable($conn);
        $this->baselineLegacyDatabase($conn);

        $applied = 0;
        foreach ($this->pendingMigrations($conn) as $migration) {
            $this->applyMigration($conn, $migration);
            $this->recordMigration($conn, $migration);
            $applied++;
        }

        $this->upgradeFileTagsForeignKeys($conn);

        return $applied;
    }

    private function ensureMigrationsTable(PDO $conn): void
    {
        $conn->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                appliedAt TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            );
            SQL,
        );
    }

    /**
     * Databases created with the old init.sql runner have tables but no ledger.
     */
    private function baselineLegacyDatabase(PDO $conn): void
    {
        if (!$this->hasApplicationTables($conn)) {
            return;
        }

        if ($this->isMigrationApplied($conn, self::BASELINE_MIGRATION)) {
            return;
        }

        $this->recordMigration($conn, self::BASELINE_MIGRATION);
    }

    private function hasApplicationTables(PDO $conn): bool
    {
        $stmt = $conn->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'files' LIMIT 1",
        );

        return (bool) $stmt->fetchColumn();
    }

    /** @return string[] */
    private function pendingMigrations(PDO $conn): array
    {
        $files = glob($this->migrationsPath . "/*.sql");
        if ($files === false) {
            throw new RuntimeException(
                "Migrations directory not found: {$this->migrationsPath}",
            );
        }

        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (!$this->isMigrationApplied($conn, $name)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    private function applyMigration(PDO $conn, string $migration): void
    {
        $path = $this->migrationsPath . "/" . $migration;
        if (!is_readable($path)) {
            throw new RuntimeException("Migration file not readable: {$path}");
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === "") {
            throw new RuntimeException("Migration file is empty: {$path}");
        }

        try {
            $conn->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Migration {$migration} failed: " . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * SQLite cannot ALTER FOREIGN KEY actions; rebuild file_tags when needed.
     */
    private function upgradeFileTagsForeignKeys(PDO $conn): void
    {
        if (!$this->hasApplicationTables($conn)) {
            return;
        }

        if ($this->fileTagsHasOnDeleteCascade($conn)) {
            return;
        }

        $conn->exec("PRAGMA foreign_keys = OFF;");
        try {
            $conn->exec(
                <<<SQL
                BEGIN;
                CREATE TABLE file_tags_new (
                    fileId INTEGER NOT NULL,
                    tagId INTEGER NOT NULL,
                    PRIMARY KEY (fileId, tagId),
                    FOREIGN KEY (tagId) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (fileId) REFERENCES files(id) ON DELETE CASCADE
                );
                INSERT INTO file_tags_new (fileId, tagId)
                    SELECT fileId, tagId FROM file_tags;
                DROP TABLE file_tags;
                ALTER TABLE file_tags_new RENAME TO file_tags;
                COMMIT;
                SQL,
            );
        } finally {
            $conn->exec("PRAGMA foreign_keys = ON;");
        }
    }

    private function fileTagsHasOnDeleteCascade(PDO $conn): bool
    {
        $stmt = $conn->query(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'file_tags'",
        );
        $ddl = $stmt->fetchColumn();

        return is_string($ddl) && stripos($ddl, "ON DELETE CASCADE") !== false;
    }

    private function isMigrationApplied(PDO $conn, string $migration): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM schema_migrations WHERE migration = :migration LIMIT 1",
        );
        $stmt->bindValue(":migration", $migration);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    private function recordMigration(PDO $conn, string $migration): void
    {
        $stmt = $conn->prepare(
            "INSERT INTO schema_migrations (migration) VALUES (:migration)",
        );
        $stmt->bindValue(":migration", $migration);
        $stmt->execute();
    }
}
