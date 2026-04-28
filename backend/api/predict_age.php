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

    $result = pythonJson('Client2', 'predict_age_wrapper.py', [
        $arbre['diametre_tronc'] ?? 0,
        $arbre['hauteur_totale'] ?? 0,
        $arbre['hauteur_tronc'] ?? 0,
        $arbre['stade_developpement'] ?? 'ADULTE',
        $arbre['port'] ?? 'libre',
        $arbre['pied'] ?? 'gazon',
    ]);

    if (empty($result['success'])) {
        fail('erreur prediction age', 500, [
            'error' => $result['error'] ?? 'erreur inconnue',
            'output' => $result['output'] ?? '',
        ]);
    }

    ok([
        'id_arbre' => $id,
        'prediction_age' => $result['prediction_age'] ?? 'Inconnu',
    ]);
} catch (Throwable $e) {
    fail('erreur prediction age', 500, ['error' => $e->getMessage()]);
}
