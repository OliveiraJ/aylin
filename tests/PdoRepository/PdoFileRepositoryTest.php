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
}
