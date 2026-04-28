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

function ensureDatabaseExists(): bool
{
    $config = dbConfig();
    $dbName = (string) ($config['name'] ?? '');
    if ($dbName === '') {
        throw new RuntimeException('nom de base de donnees manquant');
    }

    $serverPdo = getServerConnection();
    $stmt = $serverPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db_name LIMIT 1');
    $stmt->execute([':db_name' => $dbName]);
    $exists = $stmt->fetchColumn() !== false;

    if ($exists) {
        return false;
    }

    $quotedDbName = '`' . str_replace('`', '``', $dbName) . '`';
    $serverPdo->exec("CREATE DATABASE {$quotedDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    return true;
}

function createDatabaseAndTables(): array
{
    $created = ensureDatabaseExists();
    $pdo = getConnection();

    runSchema($pdo, __DIR__ . '/create_table.sql');

    return [
        'database_created' => $created,
        'message' => $created
            ? 'base de donnees creee puis tables initialisees avec succes.'
            : 'base deja existante, tables verifiees/initialisees avec succes.',
    ];
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        $result = createDatabaseAndTables();
        echo $result['message'] . PHP_EOL;
    } catch (Throwable $e) {
        echo 'erreur creation base/tables: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
