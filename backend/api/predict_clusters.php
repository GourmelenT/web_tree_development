<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

requireMethod('GET');

try {
    $pdo = getConnection();
    $arbres = fetchAll($pdo, arbresSql());

    $csv = tempnam(sys_get_temp_dir(), 'arbres_');
    $file = fopen($csv, 'w');
    fputcsv($file, ['id_arbre', 'hauteur_totale', 'diametre_tronc'], ',', '"', '\\');

    foreach ($arbres as $arbre) {
        fputcsv($file, [
            $arbre['id_arbre'],
            $arbre['hauteur_totale'] ?? 0,
            $arbre['diametre_tronc'] ?? 0,
        ], ',', '"', '\\');
    }
    fclose($file);

    $result = pythonJson('Client1', 'predict_clusters_batch.py', [$csv]);
    @unlink($csv);

    if (empty($result['success'])) {
        fail('erreur prediction clusters', 500, [
            'error' => $result['error'] ?? 'erreur inconnue',
            'output' => $result['output'] ?? '',
        ]);
    }

    $clusters = $result['clusters'] ?? [];
    foreach ($arbres as &$arbre) {
        $prediction = $clusters[(string) $arbre['id_arbre']] ?? null;
        $arbre['cluster_id'] = $prediction['cluster_id'] ?? null;
        $arbre['cluster_name'] = $prediction['cluster_name'] ?? 'ERROR';
    }

    ok(['count' => count($arbres), 'data' => $arbres]);
} catch (Throwable $e) {
    fail('erreur prediction clusters', 500, ['error' => $e->getMessage()]);
}
