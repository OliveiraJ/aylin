<?php

require __DIR__ . "/../vendor/autoload.php";

use Oliveiraj\Aylin\Infraestructure\Persistence\ConnectionCreator;
use Oliveiraj\Aylin\Infraestructure\Persistence\DatabaseMigrator;

$conn = ConnectionCreator::createConnection();
$applied = (new DatabaseMigrator())->run($conn);

if ($applied === 0) {
    echo "Database is up to date.\n";
    exit(0);
}

echo "Applied {$applied} migration(s).\n";
