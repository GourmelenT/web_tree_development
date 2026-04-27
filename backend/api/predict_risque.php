<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
}

try {
    $data = parseBody();
    $id_arbre = requireString($data, 'id_arbre');

    $pdo = getConnection();

    // Récupérer les données de l'arbre avec JOINS
    $stmt = $pdo->prepare(
        'SELECT
            ARBRE.id_arbre,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.age_estime,
            ARBRE.remarquable,
            STADE_DEV.libelle AS stade_developpement,
            PORT.libelle AS port,
            PIED.libelle AS pied,
            ETAT.libelle AS etat
        FROM ARBRE
        INNER JOIN STADE_DEV ON ARBRE.id_stad_dev = STADE_DEV.id_stad_dev
        INNER JOIN PORT ON ARBRE.id_port = PORT.id_port
        INNER JOIN PIED ON ARBRE.id_pied = PIED.id_pied
        INNER JOIN ETAT ON ARBRE.id_etat = ETAT.id_etat
        WHERE ARBRE.id_arbre = :id_arbre'
    );
    $stmt->execute([':id_arbre' => $id_arbre]);
    $arbre = $stmt->fetch();

    if (!$arbre) {
        sendJsonResponse(404, [
            'success' => false,
            'message' => 'arbre non trouve',
        ]);
    }

    // Appeler le script Python Client3 wrapper avec les paramètres
    $pythonPath = realpath(__DIR__ . '/../pythonIA/Client3');
    $args = sprintf(
        '%s %s %s %s %s %s %s %s',
        escapeshellarg((string) ($arbre['hauteur_totale'] ?? 0)),
        escapeshellarg((string) ($arbre['diametre_tronc'] ?? 0)),
        escapeshellarg((string) ($arbre['age_estime'] ?? 0)),
        escapeshellarg((string) ($arbre['stade_developpement'] ?? 'ADULTE')),
        escapeshellarg((string) ($arbre['port'] ?? 'libre')),
        escapeshellarg((string) ($arbre['pied'] ?? 'gazon')),
        escapeshellarg('Alignement'), // situation placeholder
        escapeshellarg('Oui') // revetement placeholder
    );

    $command = 'cd ' . escapeshellarg($pythonPath) . ' && python predict_risque_wrapper.py ' . $args . ' 2>&1';
    $output = shell_exec($command);

    // Parser la sortie JSON du script Python
    // Extraire la dernière ligne qui contient du JSON
    $lines = array_filter(array_map('trim', explode("\n", $output)));
    $result = null;
    foreach (array_reverse($lines) as $line) {
        if (strpos($line, '{') === 0) {
            $result = @json_decode($line, true);
            if ($result) break;
        }
    }
    
    if (!$result || !$result['success']) {
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'erreur prediction risque',
            'error' => $result['error'] ?? 'erreur inconnue',
            'output' => $output,
        ]);
    }

    sendJsonResponse(200, [
        'success' => true,
        'id_arbre' => $id_arbre,
        'prediction_risque' => [
            'etat' => $result['prediction_etat'],
            'risk_score' => (float) ($result['risk_score'] ?? 0.0),
            'a_risque' => $result['deracinement_tempete_predit'] ? 'OUI' : 'NON',
        ],
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur prediction risque',
        'error' => $e->getMessage(),
    ]);
}
