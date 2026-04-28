<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

const DEFAULT_SELECT_LABELS = [
    'ETAT' => [
        'ABATTU',
        'EN PLACE',
        'ESSOUCHE',
        'NON ESSOUCHE',
        'REMPLACE',
        'SUPPRIME',
    ],
    'STADE_DEV' => [
        'ADULTE',
        'JEUNE',
        'SENESCENT',
        'VIEUX',
    ],
    'PORT' => [
        'ARCHITECTURE',
        'CEPEE',
        'COURONNE',
        'ETETE',
        'LIBRE',
        'REDUIT',
        'RIDEAU',
        'SEMI LIBRE',
        'TETARD',
    ],
    'PIED' => [
        'BAC DE PLANTATION',
        'BANDE DE TERRE',
        'FOSSE ARBRE',
        'GAZON',
        'TERRE',
    ],
    'SITUATION' => [
        'ALIGNEMENT',
        'GROUPE',
        'ISOLE',
    ],
    'FEUILLAGE' => [
        'FEUILLU',
        'CONIFERE',
        'INCONNU',
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
}

function normalizeCsvDate(string $value, string $fallback): string
{
    $candidate = trim($value);
    if ($candidate === '') {
        return $fallback;
    }

    $candidate = str_replace('/', '-', $candidate);
    $timestamp = strtotime($candidate);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('Y-m-d', $timestamp);
}

function toFloatOrZero(string $value): float
{
    $normalized = str_replace(',', '.', trim($value));
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function toIntOrZero(string $value): int
{
    $normalized = trim($value);
    return is_numeric($normalized) ? (int) $normalized : 0;
}

function toBoolOrZero(string $value): int
{
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'oui'], true) ? 1 : 0;
}

function ensureDefaultSelectValues(PDO $pdo): void
{
    foreach (DEFAULT_SELECT_LABELS as $table => $labels) {
        $idColumn = $table === 'STADE_DEV' ? 'id_stad_dev' : 'id_' . strtolower($table);

        foreach ($labels as $label) {
            getOrCreateSimpleLabel($pdo, $table, $idColumn, $label);
        }
    }
}

function indexByName(array $header): array
{
    $index = [];
    foreach ($header as $position => $name) {
        $index[trim((string) $name)] = $position;
    }

    return $index;
}

function getRequiredCsvColumns(array $index): void
{
    $required = [
        'X',
        'Y',
        'clc_quartier',
        'clc_secteur',
        'haut_tot',
        'haut_tronc',
        'tronc_diam',
        'fk_arb_etat',
        'fk_stadedev',
        'fk_port',
        'fk_pied',
        'fk_situation',
        'nomlatin',
        'feuillage',
        'remarquable',
        'age_estim',
        'dte_plantation',
    ];

    foreach ($required as $column) {
        if (!array_key_exists($column, $index)) {
            throw new RuntimeException('colonne manquante dans data_clean.csv: ' . $column);
        }
    }
}

function pickRandomRows(string $csvPath, int $count): array
{
    if (!is_file($csvPath)) {
        throw new RuntimeException('fichier data_clean.csv introuvable');
    }

    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new RuntimeException('impossible d\'ouvrir data_clean.csv');
    }

    try {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            throw new RuntimeException('csv vide');
        }

        $index = indexByName($header);
        getRequiredCsvColumns($index);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        if (!$rows) {
            throw new RuntimeException('aucune ligne exploitable dans data_clean.csv');
        }

        shuffle($rows);

        return [
            'index' => $index,
            'rows' => array_slice($rows, 0, max(1, $count)),
        ];
    } finally {
        fclose($handle);
    }
}

function pickMappedLabel(string $table, string $rawValue): string
{
    $candidate = normalizeUpperNoAccent($rawValue, '');
    $defaults = DEFAULT_SELECT_LABELS[$table] ?? [];

    foreach ($defaults as $label) {
        if (normalizeUpperNoAccent($label, '') === $candidate && $candidate !== '') {
            return $label;
        }
    }

    if ($candidate !== '') {
        return $candidate;
    }

    if ($defaults) {
        return $defaults[array_rand($defaults)];
    }

    return 'INCONNU';
}

function insertRandomArbres(PDO $pdo, int $count): int
{
    $csvData = pickRandomRows(__DIR__ . '/../data_clean.csv', $count);
    $index = $csvData['index'];
    $rows = $csvData['rows'];

    ensureDefaultSelectValues($pdo);

    $insertArbre = $pdo->prepare(
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

    $inserted = 0;
    $dateEdited = date('Y-m-d');

    foreach ($rows as $row) {
        $espece = normalizeUpperNoAccent((string) ($row[$index['nomlatin']] ?? ''), 'INCONNU');
        $type = pickMappedLabel('FEUILLAGE', (string) ($row[$index['feuillage']] ?? ''));
        $etat = pickMappedLabel('ETAT', (string) ($row[$index['fk_arb_etat']] ?? ''));
        $stade = pickMappedLabel('STADE_DEV', (string) ($row[$index['fk_stadedev']] ?? ''));
        $port = pickMappedLabel('PORT', (string) ($row[$index['fk_port']] ?? ''));
        $pied = pickMappedLabel('PIED', (string) ($row[$index['fk_pied']] ?? ''));
        $situation = pickMappedLabel('SITUATION', (string) ($row[$index['fk_situation']] ?? ''));
        $quartier = normalizeText((string) ($row[$index['clc_quartier']] ?? ''), 'inconnu');
        $secteur = normalizeText((string) ($row[$index['clc_secteur']] ?? ''), 'inconnu');

        $hauteurTotale = max(0, toFloatOrZero((string) ($row[$index['haut_tot']] ?? '0')));
        $hauteurTronc = max(0, toFloatOrZero((string) ($row[$index['haut_tronc']] ?? '0')));
        $diametreTronc = max(0, toFloatOrZero((string) ($row[$index['tronc_diam']] ?? '0')));
        $latitude = toFloatOrZero((string) ($row[$index['Y']] ?? '0'));
        $longitude = toFloatOrZero((string) ($row[$index['X']] ?? '0'));
        $remarquable = toBoolOrZero((string) ($row[$index['remarquable']] ?? '0'));
        $ageEstime = max(0, toIntOrZero((string) ($row[$index['age_estim']] ?? '0')));
        $datePlantation = normalizeCsvDate((string) ($row[$index['dte_plantation']] ?? ''), $dateEdited);

        $feuillageId = getOrCreateSimpleLabel($pdo, 'FEUILLAGE', 'id_feuillage', $type);
        $especeId = getOrCreateEspece($pdo, $espece, $feuillageId);
        $etatId = getOrCreateSimpleLabel($pdo, 'ETAT', 'id_etat', $etat);
        $stadeDevId = getOrCreateSimpleLabel($pdo, 'STADE_DEV', 'id_stad_dev', $stade);
        $portId = getOrCreateSimpleLabel($pdo, 'PORT', 'id_port', $port);
        $piedId = getOrCreateSimpleLabel($pdo, 'PIED', 'id_pied', $pied);
        $situationId = getOrCreateSimpleLabel($pdo, 'SITUATION', 'id_situation', $situation);
        $localisationId = getOrCreateLocalisation($pdo, $quartier, $secteur, $longitude, $latitude);

        linkEspeceFeuillage($pdo, $feuillageId, $especeId);

        $insertArbre->execute([
            ':hauteur_tronc' => $hauteurTronc,
            ':hauteur_totale' => $hauteurTotale,
            ':diametre_tronc' => $diametreTronc,
            ':remarquable' => $remarquable,
            ':date_plantation' => $datePlantation,
            ':age_estime' => $ageEstime,
            ':cluster_prediction' => 0,
            ':date_edited' => $dateEdited,
            ':id_espece' => $especeId,
            ':id_etat' => $etatId,
            ':id_stad_dev' => $stadeDevId,
            ':id_port' => $portId,
            ':id_pied' => $piedId,
            ':id_localisation' => $localisationId,
        ]);

        $arbreId = (int) $pdo->lastInsertId();
        linkSituationArbre($pdo, $situationId, $arbreId);

        $inserted++;
    }

    return $inserted;
}

try {
    $pdo = getConnection();
    $payload = parseBody();
    $count = max(1, (int) ($payload['count'] ?? 5));

    $pdo->beginTransaction();
    try {
        $inserted = insertRandomArbres($pdo, $count);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    sendJsonResponse(201, [
        'success' => true,
        'message' => $inserted . ' arbres aleatoires inseres avec succes.',
        'inserted' => $inserted,
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur insertion aleatoire',
        'error' => $e->getMessage(),
    ]);
}
