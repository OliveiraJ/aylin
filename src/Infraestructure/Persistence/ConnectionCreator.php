<?php

namespace Oliveiraj\Aylin\Infraestructure\Persistence;

use PDO;

class ConnectionCreator
{
    public static function createConnection(): PDO
    {
        $databasePath = __DIR__ . "/../../../database/aylin.db";

        $conn = new PDO("sqlite:" . $databasePath);
        $conn->exec("PRAGMA foreign_keys = ON;");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    }
}
