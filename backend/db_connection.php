<?php

declare(strict_types=1);

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
}

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function dbConfig(): array
{
    loadEnv();

    $host = rtrim(envValue('DB_HOST', '127.0.0.1'), '/');
    if (str_contains($host, '://')) {
        $host = parse_url($host, PHP_URL_HOST) ?: '127.0.0.1';
    }

    return [
        'host' => $host,
        'port' => envValue('DB_PORT', '3306'),
        'name' => envValue('DB_NAME', 'grp1tr3'),
        'user' => envValue('DB_USER', 'root'),
        'password' => envValue('DB_PASSWORD'),
    ];
}

function pdo(string $dsn, array $config): PDO
{
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function getConnection(): PDO
{
    $config = dbConfig();
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";

    try {
        return pdo($dsn, $config);
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion MySQL: ' . $e->getMessage(), 0, $e);
    }
}

function getServerConnection(): PDO
{
    $config = dbConfig();
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";

    try {
        return pdo($dsn, $config);
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion MySQL: ' . $e->getMessage(), 0, $e);
    }
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        getConnection();
        echo "Connexion MySQL reussie." . PHP_EOL;
    } catch (RuntimeException $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
