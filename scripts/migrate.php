<?php

/**
 * Applies pending SQL migrations. Run inside the php container:
 *   docker compose exec php php scripts/migrate.php
 * In production, run it once on the host after deploying.
 */

declare(strict_types=1);

use Chess\Config;
use Chess\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = Config::load($root);

$pdo = null;
$attemptsLeft = 30;
while (true) {
    try {
        $pdo = Database::connect($config);
        break;
    } catch (PDOException $e) {
        if (--$attemptsLeft === 0) {
            fwrite(STDERR, 'Could not connect to the database: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        fwrite(STDOUT, 'Waiting for the database...' . PHP_EOL);
        sleep(1);
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL,
        applied_at DATETIME(3) NOT NULL,
        PRIMARY KEY (filename)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4',
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob($root . '/db/migrations/*.sql') ?: [];
sort($files);

$pending = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $applied, true)) {
        continue;
    }

    $pdo->exec((string) file_get_contents($file));
    $insert = $pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (:filename, UTC_TIMESTAMP(3))');
    $insert->execute(['filename' => $filename]);

    fwrite(STDOUT, "Applied {$filename}" . PHP_EOL);
    $pending++;
}

fwrite(STDOUT, $pending === 0 ? 'Database already up to date.' . PHP_EOL : "Applied {$pending} migration(s)." . PHP_EOL);
