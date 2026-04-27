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

function requireString(array $data, string $field): string
{
    return trim((string) requireField($data, $field));
}

function requireFloat(array $data, string $field): float
{
    $value = requireField($data, $field);

    if (!is_numeric($value)) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} doit etre un nombre.",
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
            'message' => "Le champ {$field} doit etre un entier.",
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

function requireDateString(array $data, string $field): string
{
    $value = requireString($data, $field);
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} doit etre une date au format YYYY-MM-DD.",
        ]);
    }

    return $value;
}

function optionalInt(array $data, string $field): ?int
{
    if (!array_key_exists($field, $data) || $data[$field] === '') {
        return null;
    }

    if (filter_var($data[$field], FILTER_VALIDATE_INT) === false) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => "Le champ {$field} doit etre un entier.",
        ]);
    }

    return (int) $data[$field];
}

function getArbreSelectSql(string $whereClause = ''): string
{
    return 'SELECT
            ARBRE.id_arbre,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.remarquable,
            ARBRE.date_plantation,
            ARBRE.age_estime,
            ARBRE.cluster_prediction,
            ARBRE.date_edited,
            ESPECE.id_espece,
            ESPECE.nom_latin AS espece,
            ETAT.id_etat,
            ETAT.libelle AS etat,
            STADE_DEV.id_stad_dev,
            STADE_DEV.libelle AS stade_developpement,
            PORT.id_port,
            PORT.libelle AS port,
            PIED.id_pied,
            PIED.libelle AS pied,
            LOCALISATION.id_localisation,
            LOCALISATION.quartier,
            LOCALISATION.secteur,
            LOCALISATION.latitude,
            LOCALISATION.longitude,
            GROUP_CONCAT(SITUATION.libelle ORDER BY SITUATION.libelle SEPARATOR ", ") AS situations
        FROM ARBRE
        INNER JOIN ESPECE
            ON ARBRE.id_espece = ESPECE.id_espece
        INNER JOIN ETAT
            ON ARBRE.id_etat = ETAT.id_etat
        INNER JOIN STADE_DEV
            ON ARBRE.id_stad_dev = STADE_DEV.id_stad_dev
        INNER JOIN PORT
            ON ARBRE.id_port = PORT.id_port
        INNER JOIN PIED
            ON ARBRE.id_pied = PIED.id_pied
        INNER JOIN LOCALISATION
            ON ARBRE.id_localisation = LOCALISATION.id_localisation
        LEFT JOIN possede
            ON ARBRE.id_arbre = possede.id_arbre
        LEFT JOIN SITUATION
            ON possede.id_situation = SITUATION.id_situation
        ' . $whereClause . '
        GROUP BY
            ARBRE.id_arbre,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.remarquable,
            ARBRE.date_plantation,
            ARBRE.age_estime,
            ARBRE.cluster_prediction,
            ARBRE.date_edited,
            ESPECE.id_espece,
            ESPECE.nom_latin,
            ETAT.id_etat,
            ETAT.libelle,
            STADE_DEV.id_stad_dev,
            STADE_DEV.libelle,
            PORT.id_port,
            PORT.libelle,
            PIED.id_pied,
            PIED.libelle,
            LOCALISATION.id_localisation,
            LOCALISATION.quartier,
            LOCALISATION.secteur,
            LOCALISATION.latitude,
            LOCALISATION.longitude';
}

function getAllArbres(PDO $pdo): void
{
    $stmt = $pdo->query(getArbreSelectSql() . ' ORDER BY ARBRE.id_arbre ASC');
    $arbres = $stmt->fetchAll();

    sendJsonResponse(200, [
        'success' => true,
        'data' => $arbres,
    ]);
}

function getArbreById(PDO $pdo, int $idArbre): void
{
    $stmt = $pdo->prepare(getArbreSelectSql('WHERE ARBRE.id_arbre = :id_arbre'));
    $stmt->execute([':id_arbre' => $idArbre]);
    $arbre = $stmt->fetch();

    if (!$arbre) {
        sendJsonResponse(404, [
            'success' => false,
            'message' => 'Aucun arbre ne correspond a cet id.',
        ]);
    }

    sendJsonResponse(200, [
        'success' => true,
        'data' => $arbre,
    ]);
}

function addArbre(PDO $pdo, array $data): void
{
    $arbre = [
        'hauteur_tronc' => requireFloat($data, 'hauteur_tronc'),
        'hauteur_totale' => requireFloat($data, 'hauteur_totale'),
        'diametre_tronc' => requireFloat($data, 'diametre_tronc'),
        'remarquable' => requireBoolAsInt($data, 'remarquable'),
        'date_plantation' => requireDateString($data, 'date_plantation'),
        'age_estime' => requireInt($data, 'age_estime'),
        'cluster_prediction' => requireInt($data, 'cluster_prediction'),
        'date_edited' => array_key_exists('date_edited', $data) && $data['date_edited'] !== ''
            ? requireDateString($data, 'date_edited')
            : date('Y-m-d'),
        'id_espece' => requireInt($data, 'id_espece'),
        'id_etat' => requireInt($data, 'id_etat'),
        'id_stad_dev' => requireInt($data, 'id_stad_dev'),
        'id_port' => requireInt($data, 'id_port'),
        'id_pied' => requireInt($data, 'id_pied'),
        'id_situation' => optionalInt($data, 'id_situation'),
    ];

    $localisation = [
        'quartier' => requireString($data, 'quartier'),
        'secteur' => requireString($data, 'secteur'),
        'longitude' => requireFloat($data, 'longitude'),
        'latitude' => requireFloat($data, 'latitude'),
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO LOCALISATION (quartier, secteur, longitude, latitude)
             VALUES (:quartier, :secteur, :longitude, :latitude)'
        );
        $stmt->execute([
            ':quartier' => $localisation['quartier'],
            ':secteur' => $localisation['secteur'],
            ':longitude' => $localisation['longitude'],
            ':latitude' => $localisation['latitude'],
        ]);
        $localisationId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO ARBRE (
                hauteur_tronc,
                hauteur_totale,
                diametre_tronc,
                remarquable,
                date_plantation,
                age_estime,
                cluster_prediction,
                date_edited,
                id_espece,
                id_etat,
                id_stad_dev,
                id_port,
                id_pied,
                id_localisation
            ) VALUES (
                :hauteur_tronc,
                :hauteur_totale,
                :diametre_tronc,
                :remarquable,
                :date_plantation,
                :age_estime,
                :cluster_prediction,
                :date_edited,
                :id_espece,
                :id_etat,
                :id_stad_dev,
                :id_port,
                :id_pied,
                :id_localisation
            )'
        );
        $stmt->execute([
            ':hauteur_tronc' => $arbre['hauteur_tronc'],
            ':hauteur_totale' => $arbre['hauteur_totale'],
            ':diametre_tronc' => $arbre['diametre_tronc'],
            ':remarquable' => $arbre['remarquable'],
            ':date_plantation' => $arbre['date_plantation'],
            ':age_estime' => $arbre['age_estime'],
            ':cluster_prediction' => $arbre['cluster_prediction'],
            ':date_edited' => $arbre['date_edited'],
            ':id_espece' => $arbre['id_espece'],
            ':id_etat' => $arbre['id_etat'],
            ':id_stad_dev' => $arbre['id_stad_dev'],
            ':id_port' => $arbre['id_port'],
            ':id_pied' => $arbre['id_pied'],
            ':id_localisation' => $localisationId,
        ]);
        $arbreId = (int) $pdo->lastInsertId();

        if ($arbre['id_situation'] !== null) {
            $stmt = $pdo->prepare(
                'INSERT INTO possede (id_situation, id_arbre)
                 VALUES (:id_situation, :id_arbre)'
            );
            $stmt->execute([
                ':id_situation' => $arbre['id_situation'],
                ':id_arbre' => $arbreId,
            ]);
        }

        $pdo->commit();

        sendJsonResponse(201, [
            'success' => true,
            'message' => 'Arbre ajoute avec succes.',
            'id_arbre' => $arbreId,
            'id_localisation' => $localisationId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Erreur lors de l\'ajout de l\'arbre.',
            'error' => $e->getMessage(),
        ]);
    }
}

try {
    $pdo = getConnection();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (isset($_GET['id_arbre']) && $_GET['id_arbre'] !== '') {
            if (filter_var($_GET['id_arbre'], FILTER_VALIDATE_INT) === false) {
                sendJsonResponse(400, [
                    'success' => false,
                    'message' => 'Le parametre id_arbre doit etre un entier.',
                ]);
            }

            getArbreById($pdo, (int) $_GET['id_arbre']);
        }

        getAllArbres($pdo);
    }

    if ($method === 'POST') {
        addArbre($pdo, getRequestData());
    }

    sendJsonResponse(405, [
        'success' => false,
        'message' => 'Methode non autorisee. Utilisez GET ou POST.',
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Erreur serveur.',
        'error' => $e->getMessage(),
    ]);
}
