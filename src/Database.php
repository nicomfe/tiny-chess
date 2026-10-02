<?php

declare(strict_types=1);

namespace Chess;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        return new PDO(
            $config->databaseDsn(),
            $config->databaseUser(),
            $config->databasePassword(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
