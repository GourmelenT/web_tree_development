<?php

declare(strict_types=1);

function loadEnvFile(string $filePath): void
{
    // stop si pas de .env
    if (!is_file($filePath)) {
        return;
    }

    // lit ligne par ligne
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // ignore vide/commentaire
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        // retire les quotes simples/doubles
        if (
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

function normalizeDbHost(string $host): string
{
    // si url complete, garde juste le host
    if (strpos($host, '://') !== false) {
        $parsedHost = parse_url($host, PHP_URL_HOST);
        if (is_string($parsedHost) && $parsedHost !== '') {
            return $parsedHost;
        }
    }

    // nettoie slash de fin
    return rtrim($host, '/');
}

function getDbConfig(): array
{
    // charge variable depuis .env
    loadEnvFile(__DIR__ . '/.env');

    return [
        'host' => normalizeDbHost(getenv('DB_HOST') ?: '127.0.0.1'),
        'port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: 'grp1tr3',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => (($password = getenv('DB_PASSWORD')) === false) ? '' : $password,
    ];
}

function getServerConnection(): PDO
{
    $config = getDbConfig();

    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";

    try {
        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion MySQL: ' . $e->getMessage(), 0, $e);
    }
}

function getConnection(): PDO
{
    $config = getDbConfig();

    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion MySQL: ' . $e->getMessage(), 0, $e);
    }
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        $pdo = getConnection();
        echo "Connexion MySQL reussie." . PHP_EOL;
    } catch (RuntimeException $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
