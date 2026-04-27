<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
}

function getLabels(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SELECT libelle FROM {$table} ORDER BY libelle ASC");
    return array_map(static fn(array $row): string => (string) $row['libelle'], $stmt->fetchAll());
}

try {
    $pdo = getConnection();

    $especesStmt = $pdo->query('SELECT DISTINCT nom_latin FROM ESPECE ORDER BY nom_latin ASC');
    $typesStmt = $pdo->query('SELECT DISTINCT libelle FROM FEUILLAGE ORDER BY libelle ASC');

    sendJsonResponse(200, [
        'success' => true,
        'data' => [
            'especes' => array_map(static fn(array $row): string => (string) $row['nom_latin'], $especesStmt->fetchAll()),
            'types' => array_map(static fn(array $row): string => (string) $row['libelle'], $typesStmt->fetchAll()),
            'etats' => getLabels($pdo, 'ETAT'),
            'stades_developpement' => getLabels($pdo, 'STADE_DEV'),
            'ports' => getLabels($pdo, 'PORT'),
            'pieds' => getLabels($pdo, 'PIED'),
            'situations' => getLabels($pdo, 'SITUATION'),
        ],
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur api options',
        'error' => $e->getMessage(),
    ]);
}
