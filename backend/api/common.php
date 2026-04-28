<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ok(array $payload = [], int $status = 200): void
{
    jsonResponse($status, ['success' => true] + $payload);
}

function fail(string $message, int $status = 400, array $extra = []): void
{
    jsonResponse($status, ['success' => false, 'message' => $message] + $extra);
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        fail('methode non autorisee', 405);
    }
}

function body(): array
{
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            fail('json invalide', 400);
        }

        return $data;
    }

    return $_POST;
}

function text(array $data, string $key, string $default = ''): string
{
    $value = trim((string) ($data[$key] ?? $default));
    return $value === '' ? $default : $value;
}

function requiredText(array $data, string $key): string
{
    $value = text($data, $key);
    if ($value === '') {
        fail("champ obligatoire: {$key}", 400);
    }

    return $value;
}

function requiredFloat(array $data, string $key): float
{
    $value = str_replace(',', '.', requiredText($data, $key));
    if (!is_numeric($value)) {
        fail("{$key} doit etre un nombre", 400);
    }

    return (float) $value;
}

function boolInt($value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'oui', 'yes'], true) ? 1 : 0;
}

function fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $rows = fetchAll($pdo, $sql, $params);
    return $rows[0] ?? null;
}

function labels(PDO $pdo, string $table, string $column = 'libelle'): array
{
    return array_column(fetchAll($pdo, "SELECT DISTINCT {$column} FROM {$table} ORDER BY {$column}"), $column);
}

function normalizeLatinName(string $value): string
{
    $value = trim($value);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $ascii = preg_replace('/[^A-Za-z0-9\s\-\']/u', '', $ascii) ?? $ascii;
    return strtoupper(trim(preg_replace('/\s+/', ' ', $ascii) ?? $ascii)) ?: 'INCONNU';
}

function getOrCreate(PDO $pdo, string $table, string $idColumn, array $data): int
{
    $where = implode(' AND ', array_map(static function ($key) { return "{$key} = :{$key}"; }, array_keys($data)));
    $found = fetchOne($pdo, "SELECT {$idColumn} FROM {$table} WHERE {$where} LIMIT 1", $data);

    if ($found) {
        return (int) $found[$idColumn];
    }

    $columns = implode(', ', array_keys($data));
    $params = ':' . implode(', :', array_keys($data));
    $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$params})");
    $stmt->execute($data);

    return (int) $pdo->lastInsertId();
}

function getLabelId(PDO $pdo, string $table, string $idColumn, string $label): int
{
    return getOrCreate($pdo, $table, $idColumn, ['libelle' => $label ?: 'inconnu']);
}

function insertIgnore(PDO $pdo, string $sql, array $params): void
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
}

function arbresSql(string $where = ''): string
{
    return "
        SELECT
            ARBRE.id_arbre,
            ESPECE.nom_latin AS espece,
            FEUILLAGE.libelle AS type,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.remarquable,
            ARBRE.age_estime,
            LOCALISATION.latitude,
            LOCALISATION.longitude,
            LOCALISATION.quartier,
            LOCALISATION.secteur,
            ETAT.libelle AS etat,
            STADE_DEV.libelle AS stade_developpement,
            PORT.libelle AS port,
            PIED.libelle AS pied
        FROM ARBRE
        JOIN ESPECE ON ARBRE.id_espece = ESPECE.id_espece
        JOIN FEUILLAGE ON ESPECE.feuillage = FEUILLAGE.id_feuillage
        JOIN ETAT ON ARBRE.id_etat = ETAT.id_etat
        JOIN STADE_DEV ON ARBRE.id_stad_dev = STADE_DEV.id_stad_dev
        JOIN PORT ON ARBRE.id_port = PORT.id_port
        JOIN PIED ON ARBRE.id_pied = PIED.id_pied
        JOIN LOCALISATION ON ARBRE.id_localisation = LOCALISATION.id_localisation
        {$where}
    ";
}

function pythonJson(string $folder, string $script, array $args = []): array
{
    $path = realpath(__DIR__ . "/../pythonIA/{$folder}");
    if (!$path) {
        fail("dossier python introuvable: {$folder}", 500);
    }

    $arguments = escapeshellarg($script);
    foreach ($args as $arg) {
        $arguments .= ' ' . escapeshellarg((string) $arg);
    }

    $pythonBin = getenv('PYTHON_BIN');
    $commands = $pythonBin ? [escapeshellarg($pythonBin), 'python', 'py -3', 'python3'] : ['python', 'py -3', 'python3'];

    $lastOutput = '';
    foreach ($commands as $python) {
        $command = 'cd ' . escapeshellarg($path) . " && {$python} {$arguments} 2>&1";
        $output = shell_exec($command) ?? '';
        $lastOutput = $output;

        foreach (array_reverse(array_filter(array_map('trim', explode("\n", $output)))) as $line) {
            if (strpos($line, '{') === 0) {
                $json = json_decode($line, true);
                if (is_array($json)) {
                    return $json + ['output' => $output];
                }
            }
        }
    }

    return ['success' => false, 'error' => 'sortie python invalide', 'output' => $lastOutput];
}
