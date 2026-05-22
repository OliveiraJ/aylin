<?php

require __DIR__ . "/../vendor/autoload.php";

use Oliveiraj\Aylin\Infraestructure\Persistence\ConnectionCreator;
use Oliveiraj\Aylin\Infraestructure\Persistence\DatabaseMigrator;

$conn = ConnectionCreator::createConnection();
DatabaseMigrator::migrate($conn);

echo "Database schema applied.\n";
