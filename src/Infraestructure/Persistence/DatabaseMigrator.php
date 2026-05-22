<?php

namespace Oliveiraj\Aylin\Infraestructure\Persistence;

use PDO;

class DatabaseMigrator
{
    public static function migrate(PDO $conn): void
    {
        $sql = file_get_contents(
            __DIR__ . "/../../../database/initial_script/init.sql",
        );
        $conn->exec($sql);
    }
}
