<?php

namespace Oliveiraj\Aylin\Tests\Persistence;

use Oliveiraj\Aylin\Infraestructure\Persistence\DatabaseMigrator;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseMigratorTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO("sqlite::memory:");
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn->exec("PRAGMA foreign_keys = ON;");
    }

    public function testMigrateCreatesSchemaOnEmptyDatabase(): void
    {
        $migrator = new DatabaseMigrator();
        $applied = $migrator->run($this->conn);

        $this->assertGreaterThanOrEqual(1, $applied);
        $this->assertTrue($this->tableExists("files"));
        $this->assertTrue($this->tableExists("schema_migrations"));
    }

    public function testMigrateIsIdempotent(): void
    {
        $migrator = new DatabaseMigrator();
        $migrator->run($this->conn);

        $this->assertSame(0, $migrator->run($this->conn));
    }

    public function testBaselineSkipsInitialMigrationWhenTablesAlreadyExist(): void
    {
        $this->conn->exec(
            "CREATE TABLE files (id INTEGER PRIMARY KEY, path TEXT NOT NULL)",
        );
        $this->conn->exec(
            "CREATE TABLE tags (id INTEGER PRIMARY KEY, name TEXT NOT NULL)",
        );
        $this->conn->exec(
            "CREATE TABLE file_tags (fileId INTEGER, tagId INTEGER, PRIMARY KEY (fileId, tagId))",
        );

        $migrator = new DatabaseMigrator();
        $applied = $migrator->run($this->conn);

        $this->assertTrue($this->migrationIsRecorded("001_initial_schema.sql"));
        $this->assertSame(1, $applied);
        $this->assertSame(0, $migrator->run($this->conn));
    }

    private function migrationIsRecorded(string $migration): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM schema_migrations WHERE migration = :migration",
        );
        $stmt->bindValue(":migration", $migration);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name",
        );
        $stmt->bindValue(":name", $name);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }
}
