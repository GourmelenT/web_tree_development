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

function resolveSqlitePath(string $sqlitePath): string
{
    $trimmedPath = trim($sqlitePath);
    if ($trimmedPath === '') {
        return __DIR__ . '/database.sqlite';
    }

    $isWindowsAbsolute = (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $trimmedPath);
    $isUnixAbsolute = strpos($trimmedPath, '/') === 0;

    if ($isWindowsAbsolute || $isUnixAbsolute) {
        return $trimmedPath;
    }

    return __DIR__ . '/' . ltrim($trimmedPath, './\\');
}

function getDbConfig(): array
{
    // charge variable depuis .env
    loadEnvFile(__DIR__ . '/.env');

    $sqlitePath = resolveSqlitePath((string) (getenv('SQLITE_PATH') ?: 'database.sqlite'));

    return [
        'driver' => strtolower(getenv('DB_DRIVER') ?: 'sqlite'),
        'host' => normalizeDbHost(getenv('DB_HOST') ?: '127.0.0.1'),
        'port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: 'web_tree_development',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'sqlite_path' => $sqlitePath,
    ];
}

function getServerConnection(): PDO
{
    $config = getDbConfig();

    if ($config['driver'] !== 'mysql') {
        throw new RuntimeException('connexion serveur dispo uniquement en mysql');
    }

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
        if ($config['driver'] === 'sqlite') {
            $sqlitePath = $config['sqlite_path'];
            $sqliteDir = dirname($sqlitePath);
            if (!is_dir($sqliteDir)) {
                mkdir($sqliteDir, 0777, true);
            }

            $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        }

        if ($config['driver'] !== 'mysql') {
            throw new RuntimeException('driver non supporte: ' . $config['driver']);
        }

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        if ($config['driver'] === 'sqlite') {
            throw new RuntimeException('Erreur de connexion SQLite: ' . $e->getMessage(), 0, $e);
        }

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
