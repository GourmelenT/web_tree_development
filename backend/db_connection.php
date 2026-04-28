<?php

declare(strict_types=1);

// Simple PDO connection helper. Loads .env (if present) and returns a PDO instance.
function loadEnv(string $file = __DIR__ . '/.env'): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }

function getConnection(): PDO
{
    loadEnv();

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'grp1tr3';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $user, $password, $options);
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion MySQL: ' . $e->getMessage(), 0, $e);
    }
}

// When run from CLI for quick check
if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        getConnection();
        echo "Connexion MySQL reussie." . PHP_EOL;
    } catch (RuntimeException $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
}
