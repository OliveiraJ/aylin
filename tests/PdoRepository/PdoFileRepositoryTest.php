<?php

use PHPUnit\Framework\TestCase;

use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Infraestructure\Repository\PdoFileRepository;
use Oliveiraj\Aylin\Infraestructure\Persistence\ConnectionCreator;

class PdoFileRepositoryTest extends TestCase
{
    public function testInsertFile(): void
    {
        $file = new File(null, "./teste.txt", []);
        $conn = ConnectionCreator::createConnection();
        $repository = new PdoFileRepository($conn);
        $this->assertTrue(
            $repository->insert($file),
            "File added to the database with success",
        );
    }

    public function testGetFiles(): void
    {
        $conn = ConnectionCreator::createConnection();
        $repository = new PdoFileRepository($conn);
        $files = $repository->allFiles();
        $this->assertIsArray($files, "Files found successfully") &&
            $this->assertContainsNotOnlyInstancesOf("File", $files);
    }

    public function testGetFile(): void
    {
        $conn = ConnectionCreator::createConnection();
        $repository = new PdoFileRepository($conn);
        $file = $repository->find(1);
        $this->assertInstanceOf("File", $file);
    }
}
