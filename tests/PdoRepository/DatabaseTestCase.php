<?php

namespace Oliveiraj\Aylin\Tests\PdoRepository;

use Oliveiraj\Aylin\Infraestructure\Persistence\DatabaseMigrator;
use Oliveiraj\Aylin\Infraestructure\Repository\PdoFileRepository;
use Oliveiraj\Aylin\Infraestructure\Repository\PdoTagRepository;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $conn;
    protected PdoFileRepository $fileRepository;
    protected PdoTagRepository $tagRepository;

    protected function setUp(): void
    {
        $this->conn = new PDO("sqlite::memory:");
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->conn->exec("PRAGMA foreign_keys = ON;");

        DatabaseMigrator::migrate($this->conn);

        $this->fileRepository = new PdoFileRepository($this->conn);
        $this->tagRepository = new PdoTagRepository($this->conn);
    }
}
