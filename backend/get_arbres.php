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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez GET.',
    ]);
}

try {
    $pdo = getConnection();

    $stmt = $pdo->query(
        'SELECT
            ARBRE.id_arbre,
            ESPECE.nom_latin AS espece,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.remarquable,
            LOCALISATION.latitude,
            LOCALISATION.longitude,
            ETAT.libelle AS etat,
            STADE_DEV.libelle AS stade_developpement,
            PORT.libelle AS port,
            PIED.libelle AS pied
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
        ORDER BY ARBRE.id_arbre ASC'
    );

    $arbres = $stmt->fetchAll();

    sendJsonResponse(200, [
        'success' => true,
        'data' => $arbres,
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Erreur lors de la récupération des arbres.',
        'error' => $e->getMessage(),
    ]);
}
