<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

requireMethod('POST');

try {
    $id = requiredText(body(), 'id_arbre');
    $pdo = getConnection();
    $arbre = fetchOne($pdo, arbresSql('WHERE ARBRE.id_arbre = :id'), [':id' => $id]);

    if (!$arbre) {
        fail('arbre non trouve', 404);
    }

    $result = pythonJson('Client3', 'predict_risque_wrapper.py', [
        $arbre['hauteur_totale'] ?? 0,
        $arbre['diametre_tronc'] ?? 0,
        $arbre['age_estime'] ?? 0,
        $arbre['stade_developpement'] ?? 'ADULTE',
        $arbre['port'] ?? 'libre',
        $arbre['pied'] ?? 'gazon',
        'Alignement',
        'Oui',
    ]);

    if (empty($result['success'])) {
        fail('erreur prediction risque', 500, [
            'error' => $result['error'] ?? 'erreur inconnue',
            'output' => $result['output'] ?? '',
        ]);
    }

    ok([
        'id_arbre' => $id,
        'prediction_risque' => [
            'etat' => $result['prediction_etat'] ?? 'inconnu',
            'risk_score' => (float) ($result['risk_score'] ?? 0),
            'a_risque' => !empty($result['deracinement_tempete_predit']) ? 'OUI' : 'NON',
        ],
    ]);
} catch (Throwable $e) {
    fail('erreur prediction risque', 500, ['error' => $e->getMessage()]);
}
