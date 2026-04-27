<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendJsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeText(string $value, string $default = 'inconnu'): string
{
    $trimmed = trim($value);
    return $trimmed === '' ? $default : $trimmed;
}

function normalizeUpperNoAccent(string $value, string $default = 'INCONNU'): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $default;
    }

    $withoutAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
    if ($withoutAccents === false || $withoutAccents === null) {
        $withoutAccents = $trimmed;
    }

    $withoutAccents = preg_replace('/[^A-Za-z0-9\s\-\']/u', '', $withoutAccents) ?? $withoutAccents;
    $collapsed = preg_replace('/\s+/', ' ', $withoutAccents) ?? $withoutAccents;
    $upper = strtoupper(trim($collapsed));

    return $upper === '' ? $default : $upper;
}

function parseBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody ?: '', true);
        if (!is_array($jsonData)) {
            sendJsonResponse(400, [
                'success' => false,
                'message' => 'json invalide',
            ]);
        }

        return $jsonData;
    }

    return $_POST;
}

function requireString(array $data, string $field): string
{
    if (!array_key_exists($field, $data)) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "champ obligatoire: {$field}",
        ]);
    }

    $value = trim((string) $data[$field]);
    if ($value === '') {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "champ obligatoire: {$field}",
        ]);
    }

    return $value;
}

function requireFloat(array $data, string $field): float
{
    $value = requireString($data, $field);
    $value = str_replace(',', '.', $value);

    if (!is_numeric($value)) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "{$field} doit etre un nombre",
        ]);
    }

    return (float) $value;
}

function toBoolInt(mixed $value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'oui', 'yes'], true) ? 1 : 0;
}

function firstColumnId(PDO $pdo, string $sql, array $params): ?int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();

    if ($result === false) {
        return null;
    }

    return (int) $result;
}

function getOrCreateSimpleLabel(PDO $pdo, string $table, string $idColumn, string $label): int
{
    $label = normalizeText($label);

    $existingId = firstColumnId(
        $pdo,
        "SELECT {$idColumn} FROM {$table} WHERE libelle = :libelle LIMIT 1",
        [':libelle' => $label]
    );

    if ($existingId !== null) {
        return $existingId;
    }

    $stmt = $pdo->prepare("INSERT INTO {$table} (libelle) VALUES (:libelle)");
    $stmt->execute([':libelle' => $label]);

    return (int) $pdo->lastInsertId();
}

function getOrCreateLocalisation(PDO $pdo, string $quartier, string $secteur, float $longitude, float $latitude): int
{
    $existingId = firstColumnId(
        $pdo,
        'SELECT id_localisation FROM LOCALISATION WHERE quartier = :quartier AND secteur = :secteur AND longitude = :longitude AND latitude = :latitude LIMIT 1',
        [
            ':quartier' => normalizeText($quartier),
            ':secteur' => normalizeText($secteur),
            ':longitude' => $longitude,
            ':latitude' => $latitude,
        ]
    );

    if ($existingId !== null) {
        return $existingId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO LOCALISATION (quartier, secteur, longitude, latitude)
         VALUES (:quartier, :secteur, :longitude, :latitude)'
    );
    $stmt->execute([
        ':quartier' => normalizeText($quartier),
        ':secteur' => normalizeText($secteur),
        ':longitude' => $longitude,
        ':latitude' => $latitude,
    ]);

    return (int) $pdo->lastInsertId();
}

function getOrCreateEspece(PDO $pdo, string $nomLatin, int $feuillageId): int
{
    $nomLatin = normalizeUpperNoAccent($nomLatin, 'INCONNU');

    $existingId = firstColumnId(
        $pdo,
        'SELECT id_espece FROM ESPECE WHERE nom_latin = :nom_latin AND feuillage = :feuillage LIMIT 1',
        [':nom_latin' => $nomLatin, ':feuillage' => $feuillageId]
    );

    if ($existingId !== null) {
        return $existingId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ESPECE (nom_latin, feuillage) VALUES (:nom_latin, :feuillage)'
    );
    $stmt->execute([
        ':nom_latin' => $nomLatin,
        ':feuillage' => $feuillageId,
    ]);

    return (int) $pdo->lastInsertId();
}

function linkEspeceFeuillage(PDO $pdo, int $feuillageId, int $especeId): void
{
    $stmt = $pdo->prepare('INSERT INTO est_de_type (id_feuillage, id_espece) VALUES (:id_feuillage, :id_espece)');

    try {
        $stmt->execute([
            ':id_feuillage' => $feuillageId,
            ':id_espece' => $especeId,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
}

function linkSituationArbre(PDO $pdo, int $situationId, int $arbreId): void
{
    $stmt = $pdo->prepare('INSERT INTO possede (id_situation, id_arbre) VALUES (:id_situation, :id_arbre)');

    try {
        $stmt->execute([
            ':id_situation' => $situationId,
            ':id_arbre' => $arbreId,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
}
