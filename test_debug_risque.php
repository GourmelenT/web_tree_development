<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simuler la requête POST
$input = json_encode(['id_arbre' => '1']);

try {
    require __DIR__ . '/backend/api/common.php';
    
    $data = json_decode($input, true);
    $id_arbre = $data['id_arbre'] ?? null;
    
    echo "ID Arbre: $id_arbre\n";
    
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
    
    echo "Arbre found:\n";
    var_dump($arbre);
    
    if (!$arbre) {
        die("Arbre not found");
    }
    
    // Appeler le script Python Client3 wrapper avec les paramètres
    $pythonPath = realpath(dirname(__FILE__) . '/backend/pythonIA/Client3');
    echo "Python path: $pythonPath\n";
    
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
    echo "Command: $command\n\n";
    
    $output = shell_exec($command);
    echo "Python output:\n";
    echo $output . "\n\n";
    
    // Parser la sortie JSON du script Python
    $result = @json_decode($output, true);
    
    echo "Parsed result:\n";
    var_dump($result);
    
    if (!$result || !$result['success']) {
        echo "Error in prediction\n";
        var_dump($result);
        die();
    }
    
    echo "Final response:\n";
    $response = [
        'success' => true,
        'id_arbre' => $id_arbre,
        'prediction_risque' => [
            'etat' => $result['prediction_etat'],
            'risk_score' => (float) ($result['risk_score'] ?? 0.0),
            'a_risque' => $result['deracinement_tempete_predit'] ? 'OUI' : 'NON',
        ],
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
