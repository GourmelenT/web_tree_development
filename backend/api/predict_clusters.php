<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
}

try {
    $pdo = getConnection();

    // Récupérer tous les arbres avec leurs données
    $stmt = $pdo->query(
        'SELECT
            ARBRE.id_arbre,
            ESPECE.nom_latin AS espece,
            FEUILLAGE.libelle AS type,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            LOCALISATION.latitude,
            LOCALISATION.longitude,
            LOCALISATION.quartier,
            ETAT.libelle AS etat,
            STADE_DEV.libelle AS stade_developpement,
            PORT.libelle AS port,
            PIED.libelle AS pied
        FROM ARBRE
        INNER JOIN ESPECE ON ARBRE.id_espece = ESPECE.id_espece
        INNER JOIN FEUILLAGE ON ESPECE.feuillage = FEUILLAGE.id_feuillage
        INNER JOIN ETAT ON ARBRE.id_etat = ETAT.id_etat
        INNER JOIN STADE_DEV ON ARBRE.id_stad_dev = STADE_DEV.id_stad_dev
        INNER JOIN PORT ON ARBRE.id_port = PORT.id_port
        INNER JOIN PIED ON ARBRE.id_pied = PIED.id_pied
        INNER JOIN LOCALISATION ON ARBRE.id_localisation = LOCALISATION.id_localisation'
    );

    $arbres = $stmt->fetchAll();

    // Créer un CSV temporaire pour prédictions batch
    $tmpCsv = sys_get_temp_dir() . '/arbres_' . uniqid() . '.csv';
    $fp = fopen($tmpCsv, 'w');

    // Header - fputcsv($stream, $fields, $separator, $enclosure, $escape)
    fputcsv($fp, ['id_arbre', 'hauteur_totale', 'diametre_tronc'], ',', '"', '\\');

    // Data rows
    foreach ($arbres as $arbre) {
        fputcsv($fp, [
            $arbre['id_arbre'],
            $arbre['hauteur_totale'] ?? 0,
            $arbre['diametre_tronc'] ?? 0,
        ], ',', '"', '\\');
    }
    fclose($fp);

    // Appeler le script Python Client1 batch
    $pythonPath = realpath(__DIR__ . '/../pythonIA/Client1');
    $command = 'cd ' . escapeshellarg($pythonPath) . ' && python predict_clusters_batch.py ' . escapeshellarg($tmpCsv) . ' 2>&1';
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
            'message' => 'erreur prediction clusters',
            'error' => $result['error'] ?? 'erreur inconnue',
            'output' => $output,
        ]);
    }

    // Ajouter les clusters aux arbres
    $clustersMap = $result['clusters'] ?? [];
    foreach ($arbres as &$arbre) {
        $id = (string) $arbre['id_arbre'];
        if (isset($clustersMap[$id])) {
            $arbre['cluster_id'] = $clustersMap[$id]['cluster_id'];
            $arbre['cluster_name'] = $clustersMap[$id]['cluster_name'];
        } else {
            $arbre['cluster_id'] = null;
            $arbre['cluster_name'] = 'ERROR';
        }
    }

    // Nettoyer le fichier temporaire
    @unlink($tmpCsv);

    sendJsonResponse(200, [
        'success' => true,
        'count' => count($arbres),
        'data' => $arbres,
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur prediction clusters',
        'error' => $e->getMessage(),
    ]);
}
