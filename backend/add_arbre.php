<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody ?: '', true);

        if (!is_array($jsonData)) {
            sendJsonResponse(400, [
                'success' => false,
                'message' => 'JSON invalide.',
            ]);
        }

        return $jsonData;
    }

    return $_POST;
}

function requireField(array $data, string $field): mixed
{
    if (!array_key_exists($field, $data) || $data[$field] === '') {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} est obligatoire.",
        ]);
    }

    return $data[$field];
}

function requireFloat(array $data, string $field): float
{
    $value = requireField($data, $field);

    if (!is_numeric($value)) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} doit être un nombre.",
        ]);
    }

    return (float) $value;
}

function requireInt(array $data, string $field): int
{
    $value = requireField($data, $field);

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} doit être un entier.",
        ]);
    }

    return (int) $value;
}

function requireBoolAsInt(array $data, string $field): int
{
    $value = requireField($data, $field);

    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if ($value === '1' || $value === 1 || $value === 'true') {
        return 1;
    }

    if ($value === '0' || $value === 0 || $value === 'false') {
        return 0;
    }

    sendJsonResponse(400, [
        'success' => false,
        'message' => "Le champ {$field} doit valoir 0 ou 1.",
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ]);
}

$data = getRequestData();

$arbre = [
    'hauteur_tronc' => requireFloat($data, 'hauteur_tronc'),
    'hauteur_totale' => requireFloat($data, 'hauteur_totale'),
    'diametre_tronc' => requireFloat($data, 'diametre_tronc'),
    'remarquable' => requireBoolAsInt($data, 'remarquable'),
    'latitude' => requireFloat($data, 'latitude'),
    'longitude' => requireFloat($data, 'longitude'),
    'id_espece' => requireInt($data, 'id_espece'),
    'id_etat' => requireInt($data, 'id_etat'),
    'id_stad_dev' => requireInt($data, 'id_stad_dev'),
    'id_port' => requireInt($data, 'id_port'),
    'id_pied' => requireInt($data, 'id_pied'),
];

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare(
        'INSERT INTO ARBRE (
            hauteur_tronc,
            hauteur_totale,
            diametre_tronc,
            remarquable,
            latitude,
            longitude,
            id_espece,
            id_etat,
            id_stad_dev,
            id_port,
            id_pied
        ) VALUES (
            :hauteur_tronc,
            :hauteur_totale,
            :diametre_tronc,
            :remarquable,
            :latitude,
            :longitude,
            :id_espece,
            :id_etat,
            :id_stad_dev,
            :id_port,
            :id_pied
        )'
    );

    $stmt->execute([
        ':hauteur_tronc' => $arbre['hauteur_tronc'],
        ':hauteur_totale' => $arbre['hauteur_totale'],
        ':diametre_tronc' => $arbre['diametre_tronc'],
        ':remarquable' => $arbre['remarquable'],
        ':latitude' => $arbre['latitude'],
        ':longitude' => $arbre['longitude'],
        ':id_espece' => $arbre['id_espece'],
        ':id_etat' => $arbre['id_etat'],
        ':id_stad_dev' => $arbre['id_stad_dev'],
        ':id_port' => $arbre['id_port'],
        ':id_pied' => $arbre['id_pied'],
    ]);

    sendJsonResponse(201, [
        'success' => true,
        'message' => 'Arbre ajouté avec succès.',
        'id_arbre' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Erreur lors de l\'ajout de l\'arbre.',
        'error' => $e->getMessage(),
    ]);
}
