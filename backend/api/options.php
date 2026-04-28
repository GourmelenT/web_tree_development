<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

requireMethod('GET');

try {
    $pdo = getConnection();

    ok([
        'data' => [
            'especes' => labels($pdo, 'ESPECE', 'nom_latin'),
            'types' => labels($pdo, 'FEUILLAGE'),
            'etats' => labels($pdo, 'ETAT'),
            'stades_developpement' => labels($pdo, 'STADE_DEV'),
            'ports' => labels($pdo, 'PORT'),
            'pieds' => labels($pdo, 'PIED'),
            'situations' => labels($pdo, 'SITUATION'),
        ],
    ]);
} catch (Throwable $e) {
    fail('erreur api options', 500, ['error' => $e->getMessage()]);
}
