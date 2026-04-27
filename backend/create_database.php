<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

function runSchema(PDO $pdo, string $schemaPath): void
{
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException('impossible de lire create_table.sql');
    }

    // retire commentaires '--' pour faciliter le split
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    if (!is_string($sql)) {
        throw new RuntimeException('impossible de parser le schema sql');
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // ignore "already exists" pour relancer sans bloquer
            if ((string) $e->getCode() === '42S01' || strpos($e->getMessage(), 'already exists') !== false) {
                continue;
            }

            throw $e;
        }
    }
}

function createDatabaseAndTables(): void
{
    $config = getDbConfig();

    if ($config['driver'] === 'sqlite') {
        $pdo = getConnection();
        runSchema($pdo, __DIR__ . '/create_table_sqlite.sql');
        echo "base sqlite et tables creees avec succes." . PHP_EOL;
        return;
    }

    // en mysql distant, on part d'une base deja creee (ex: phpmyadmin hebergeur)
    $pdo = getConnection();

    runSchema($pdo, __DIR__ . '/create_table.sql');

    echo "tables mysql creees avec succes." . PHP_EOL;
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        createDatabaseAndTables();
    } catch (Throwable $e) {
        echo 'erreur creation base/tables: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
