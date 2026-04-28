<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

// Valeurs de reference minimales pour les tables de labels.
const REFERENCE_LABELS = [
    'ETAT' => [
        'ABATTU',
        'EN PLACE',
        'ESSOUCHÉ',
        'NON ESSOUCHÉ',
        'REMPLACE',
        'SUPPRIMÉ',
    ],
    'STADE_DEV' => [
        'ADULTE',
        'JEUNE',
        'SENESCENT',
        'VIEUX',
    ],
    'PORT' => [
        'ARCHITECTURE',
        'CÉPÉE',
        'COURONNÉ',
        'ÉTÊTÉ',
        'LIBRE',
        'RÉDUIT',
        'RÉDUIT RELÂCHÉ',
        'RIDEAU',
        'SEMI LIBRE',
        'TÊTARD',
        'TÊTARD RELÂCHÉ',
        'TETE DE CHAT',
        'TÊTE DE CHAT RELACHÉ',
    ],
    'PIED' => [
        'BAC DE PLANTATION',
        'BANDE DE TERRE',
        'FOSSE ARBRE',
        'GAZON',
        'REVÊTEMENT NON PERMÉABLE',
        'TERRE',
        'TOILE TISSÉE',
        'VÉGÉTATION',
    ],
    'SITUATION' => [
        'ALIGNEMENT',
        'GROUPE',
        'ISOLÉ',
    ],
];

// Nettoie une chaine (sans accent, uppercase) pour matcher les labels.
function upperNoAccent(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $withoutAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
    if ($withoutAccents === false || $withoutAccents === null) {
        $withoutAccents = $trimmed;
    }

    $withoutAccents = preg_replace('/[^A-Za-z0-9\s\-\']/u', '', $withoutAccents) ?? $withoutAccents;
    $collapsed = preg_replace('/\s+/', ' ', $withoutAccents) ?? $withoutAccents;

    return strtoupper(trim($collapsed));
}

// Nettoyage texte libre + valeur par defaut si vide/NA.
function cleanText(?string $value, string $default = 'inconnu'): string
{
    if ($value === null) {
        return $default;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || strtoupper($trimmed) === 'NA') {
        return $default;
    }

    return $trimmed;
}

// Nettoyage label categoriquement compare (version uppercase/no accent).
function cleanLabel(?string $value, string $default = 'INCONNU'): string
{
    if ($value === null) {
        return $default;
    }

    $cleaned = upperNoAccent($value);
    if ($cleaned === '' || $cleaned === 'NA') {
        return $default;
    }

    return $cleaned;
}

// Parse float robuste (virgule ou point), fallback 0.
function toFloatOrZero(?string $value): float
{
    $clean = cleanText($value, '0');
    $clean = str_replace(',', '.', $clean);
    return is_numeric($clean) ? (float) $clean : 0.0;
}

// Parse int robuste, fallback 0.
function toIntOrZero(?string $value): int
{
    $clean = cleanText($value, '0');
    return is_numeric($clean) ? (int) $clean : 0;
}

// Convertit differents formats booleens vers 0/1.
function toBoolInt(?string $value): int
{
    $clean = strtolower(cleanText($value, 'non'));
    return in_array($clean, ['oui', 'yes', '1', 'true'], true) ? 1 : 0;
}

// Convertit une date texte en format SQL YYYY-mm-dd.
function toSqlDate(?string $value): string
{
    $clean = cleanText($value, '');
    if ($clean === '') {
        return '';
    }

    $candidate = str_replace('/', '-', $clean);
    $timestamp = strtotime($candidate);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

// Helper generic: prend un id en cache, sinon SELECT, sinon INSERT.
function getOrCreateId(PDO $pdo, array &$cache, string $cacheKey, string $selectSql, string $insertSql, array $params): int
{
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $select = $pdo->prepare($selectSql);
    $select->execute($params);
    $foundId = $select->fetchColumn();
    if ($foundId !== false) {
        $cache[$cacheKey] = (int) $foundId;
        return (int) $foundId;
    }

    $insert = $pdo->prepare($insertSql);
    $insert->execute($params);
    $id = (int) $pdo->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

function resetTables(PDO $pdo): void
{
    // RAZ complete pour eviter les doublons avant nouvel import.
    $pdo->exec('DELETE FROM possede');
    $pdo->exec('DELETE FROM ARBRE');
    $pdo->exec('DELETE FROM est_de_type');
    $pdo->exec('DELETE FROM LOCALISATION');
    $pdo->exec('DELETE FROM ESPECE');
    $pdo->exec('DELETE FROM FEUILLAGE');
    $pdo->exec('DELETE FROM ETAT');
    $pdo->exec('DELETE FROM PORT');
    $pdo->exec('DELETE FROM PIED');
    $pdo->exec('DELETE FROM STADE_DEV');
    $pdo->exec('DELETE FROM SITUATION');
}

// Re-injecte les labels de reference utilises dans les selects du front.
function seedReferenceLabels(PDO $pdo): void
{
    foreach (REFERENCE_LABELS as $table => $labels) {
        $stmt = $pdo->prepare("INSERT INTO {$table} (libelle) VALUES (:libelle)");
        foreach ($labels as $label) {
            $stmt->execute([':libelle' => $label]);
        }
    }
}

// Tente de garder la valeur CSV si compatible, sinon prend une valeur reference.
function pickReferenceLabel(string $table, ?string $rawValue): string
{
    $allowed = REFERENCE_LABELS[$table] ?? [];
    if ($allowed === []) {
        return cleanLabel($rawValue, 'INCONNU');
    }

    $candidate = cleanLabel($rawValue, '');
    if ($candidate !== '') {
        foreach ($allowed as $label) {
            if (upperNoAccent($label) === $candidate) {
                return $label;
            }
        }
    }

    return $allowed[array_rand($allowed)];
}

// Charge toutes les lignes du CSV puis en tire un echantillon aleatoire.
function pickRandomRowsFromCsv($handle, int $limit): array
{
    $rows = [];

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $rows[] = $row;
    }

    if (count($rows) <= $limit) {
        return $rows;
    }

    shuffle($rows);
    return array_slice($rows, 0, $limit);
}

// Pipeline principal: lit CSV + reset + remplit toutes les tables relationnelles.
function insertCsvData(string $csvPath): void
{
    if (!is_file($csvPath)) {
        throw new RuntimeException('fichier data_clean.csv introuvable');
    }

    // Connexion DB + ouverture du CSV.
    $pdo = getConnection();
    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new RuntimeException('impossible d\'ouvrir data_clean.csv');
    }

    try {
        // Debut transaction: tout ou rien.
        $pdo->beginTransaction();
        resetTables($pdo);
        seedReferenceLabels($pdo);

        // Lecture de l'entete pour mapper les index de colonnes.
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            throw new RuntimeException('csv vide ou header invalide');
        }

        $indexByName = [];
        foreach ($header as $idx => $name) {
            $indexByName[trim((string) $name)] = $idx;
        }

        // Colonnes minimales attendues pour un import coherent.
        $required = [
            'X', 'Y', 'clc_quartier', 'clc_secteur', 'haut_tot', 'haut_tronc',
            'tronc_diam', 'fk_arb_etat', 'fk_stadedev', 'fk_port', 'fk_pied',
            'fk_situation', 'last_edited_date', 'nomlatin', 'feuillage',
            'remarquable', 'age_estim', 'clc_nbr_diag', 'dte_plantation',
        ];
        foreach ($required as $col) {
            if (!array_key_exists($col, $indexByName)) {
                throw new RuntimeException('colonne manquante dans csv: ' . $col);
            }
        }

        // Caches memo pour limiter les SELECT/INSERT repetitifs.
        $cacheFeuillage = [];
        $cacheEspece = [];
        $cacheEtat = [];
        $cachePort = [];
        $cachePied = [];
        $cacheStade = [];
        $cacheSituation = [];
        $cacheLoc = [];
        $linksEspeceFeuillage = [];

        // Requetes preparees reutilisees dans la boucle.
        $insertArbre = $pdo->prepare(
            'INSERT INTO ARBRE (
                hauteur_tronc, hauteur_totale, diametre_tronc, remarquable,
                date_plantation, age_estime, cluster_prediction, date_edited,
                id_espece, id_etat, id_stad_dev, id_port, id_pied, id_localisation
            ) VALUES (
                :hauteur_tronc, :hauteur_totale, :diametre_tronc, :remarquable,
                :date_plantation, :age_estime, :cluster_prediction, :date_edited,
                :id_espece, :id_etat, :id_stad_dev, :id_port, :id_pied, :id_localisation
            )'
        );

        $insertPossede = $pdo->prepare('INSERT INTO possede (id_situation, id_arbre) VALUES (:id_situation, :id_arbre)');
        $insertType = $pdo->prepare('INSERT INTO est_de_type (id_feuillage, id_espece) VALUES (:id_feuillage, :id_espece)');

        $count = 0;
        // Ici on ne garde que 5 lignes aleatoires du CSV.
        $selectedRows = pickRandomRowsFromCsv($handle, 5);

        foreach ($selectedRows as $row) {
            // Champs geo/contexte.
            $quartier = cleanText($row[$indexByName['clc_quartier']] ?? null);
            $secteur = cleanText($row[$indexByName['clc_secteur']] ?? null);
            $longitude = toFloatOrZero($row[$indexByName['X']] ?? null);
            $latitude = toFloatOrZero($row[$indexByName['Y']] ?? null);

            // Champs categories (normalises pour matcher les tables de refs).
            $feuillageLib = cleanLabel($row[$indexByName['feuillage']] ?? null, 'INCONNU');
            $nomLatin = cleanLabel($row[$indexByName['nomlatin']] ?? null, 'INCONNU');
            $etatLib = pickReferenceLabel('ETAT', $row[$indexByName['fk_arb_etat']] ?? null);
            $stadeLib = pickReferenceLabel('STADE_DEV', $row[$indexByName['fk_stadedev']] ?? null);
            $portLib = pickReferenceLabel('PORT', $row[$indexByName['fk_port']] ?? null);
            $piedLib = pickReferenceLabel('PIED', $row[$indexByName['fk_pied']] ?? null);
            $situationLib = pickReferenceLabel('SITUATION', $row[$indexByName['fk_situation']] ?? null);

            // Dates: fallback date du jour si non parseable.
            $dateEdited = toSqlDate($row[$indexByName['last_edited_date']] ?? null);
            $datePlantation = toSqlDate($row[$indexByName['dte_plantation']] ?? null);
            if ($dateEdited === '') {
                $dateEdited = date('Y-m-d');
            }
            if ($datePlantation === '') {
                $datePlantation = $dateEdited;
            }

            // Get-or-create des dimensions de reference + espece.
            $feuillageId = getOrCreateId(
                $pdo,
                $cacheFeuillage,
                $feuillageLib,
                'SELECT id_feuillage FROM FEUILLAGE WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO FEUILLAGE (libelle) VALUES (:libelle)',
                [':libelle' => $feuillageLib]
            );

            $especeId = getOrCreateId(
                $pdo,
                $cacheEspece,
                $nomLatin . '|' . $feuillageId,
                'SELECT id_espece FROM ESPECE WHERE nom_latin = :nom_latin AND feuillage = :feuillage LIMIT 1',
                'INSERT INTO ESPECE (nom_latin, feuillage) VALUES (:nom_latin, :feuillage)',
                [':nom_latin' => $nomLatin, ':feuillage' => $feuillageId]
            );

            $etatId = getOrCreateId(
                $pdo,
                $cacheEtat,
                $etatLib,
                'SELECT id_etat FROM ETAT WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO ETAT (libelle) VALUES (:libelle)',
                [':libelle' => $etatLib]
            );

            $portId = getOrCreateId(
                $pdo,
                $cachePort,
                $portLib,
                'SELECT id_port FROM PORT WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO PORT (libelle) VALUES (:libelle)',
                [':libelle' => $portLib]
            );

            $piedId = getOrCreateId(
                $pdo,
                $cachePied,
                $piedLib,
                'SELECT id_pied FROM PIED WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO PIED (libelle) VALUES (:libelle)',
                [':libelle' => $piedLib]
            );

            $stadeId = getOrCreateId(
                $pdo,
                $cacheStade,
                $stadeLib,
                'SELECT id_stad_dev FROM STADE_DEV WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO STADE_DEV (libelle) VALUES (:libelle)',
                [':libelle' => $stadeLib]
            );

            $situationId = getOrCreateId(
                $pdo,
                $cacheSituation,
                $situationLib,
                'SELECT id_situation FROM SITUATION WHERE libelle = :libelle LIMIT 1',
                'INSERT INTO SITUATION (libelle) VALUES (:libelle)',
                [':libelle' => $situationLib]
            );

            // La localisation est unique par quartet quartier/secteur/lon/lat.
            $locKey = $quartier . '|' . $secteur . '|' . $longitude . '|' . $latitude;
            $localisationId = getOrCreateId(
                $pdo,
                $cacheLoc,
                $locKey,
                'SELECT id_localisation FROM LOCALISATION WHERE quartier = :quartier AND secteur = :secteur AND longitude = :longitude AND latitude = :latitude LIMIT 1',
                'INSERT INTO LOCALISATION (quartier, secteur, longitude, latitude) VALUES (:quartier, :secteur, :longitude, :latitude)',
                [
                    ':quartier' => $quartier,
                    ':secteur' => $secteur,
                    ':longitude' => $longitude,
                    ':latitude' => $latitude,
                ]
            );

            // Evite de recreer le meme lien espece<->feuillage.
            $typeKey = $feuillageId . '-' . $especeId;
            if (!isset($linksEspeceFeuillage[$typeKey])) {
                $insertType->execute([
                    ':id_feuillage' => $feuillageId,
                    ':id_espece' => $especeId,
                ]);
                $linksEspeceFeuillage[$typeKey] = true;
            }

            // Insert ligne ARBRE principale.
            $insertArbre->execute([
                ':hauteur_tronc' => toFloatOrZero($row[$indexByName['haut_tronc']] ?? null),
                ':hauteur_totale' => toFloatOrZero($row[$indexByName['haut_tot']] ?? null),
                ':diametre_tronc' => toFloatOrZero($row[$indexByName['tronc_diam']] ?? null),
                ':remarquable' => toBoolInt($row[$indexByName['remarquable']] ?? null),
                ':date_plantation' => $datePlantation,
                ':age_estime' => toIntOrZero($row[$indexByName['age_estim']] ?? null),
                ':cluster_prediction' => toIntOrZero($row[$indexByName['clc_nbr_diag']] ?? null),
                ':date_edited' => $dateEdited,
                ':id_espece' => $especeId,
                ':id_etat' => $etatId,
                ':id_stad_dev' => $stadeId,
                ':id_port' => $portId,
                ':id_pied' => $piedId,
                ':id_localisation' => $localisationId,
            ]);

            // Insert table de liaison situation<->arbre.
            $arbreId = (int) $pdo->lastInsertId();
            $insertPossede->execute([
                ':id_situation' => $situationId,
                ':id_arbre' => $arbreId,
            ]);

            $count++;
        }

        // Validation finale de toute la transaction.
        $pdo->commit();
        echo "donnees csv inserees: {$count}" . PHP_EOL;
    } catch (Throwable $e) {
        // En cas d'erreur: annule tout l'import pour garder une base propre.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo 'erreur lors de l\'insertion csv: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    } finally {
        fclose($handle);
    }
}

// Point d'entree CLI: php backend/insert_data.php
if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    insertCsvData(__DIR__ . '/data_clean.csv');
}
