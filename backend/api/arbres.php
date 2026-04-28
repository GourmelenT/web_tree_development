<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function listArbres(PDO $pdo): void
{
    $stmt = $pdo->query(
        'SELECT
            ARBRE.id_arbre,
            ESPECE.nom_latin AS espece,
            FEUILLAGE.libelle AS type,
            ARBRE.hauteur_totale,
            ARBRE.hauteur_tronc,
            ARBRE.diametre_tronc,
            ARBRE.remarquable,
            LOCALISATION.latitude,
            LOCALISATION.longitude,
            LOCALISATION.quartier,
            LOCALISATION.secteur,
            ETAT.libelle AS etat,
            STADE_DEV.libelle AS stade_developpement,
            PORT.libelle AS port,
            PIED.libelle AS pied,
            ARBRE.age_estime
        FROM ARBRE
        INNER JOIN ESPECE ON ARBRE.id_espece = ESPECE.id_espece
        INNER JOIN FEUILLAGE ON ESPECE.feuillage = FEUILLAGE.id_feuillage
        INNER JOIN ETAT ON ARBRE.id_etat = ETAT.id_etat
        INNER JOIN STADE_DEV ON ARBRE.id_stad_dev = STADE_DEV.id_stad_dev
        INNER JOIN PORT ON ARBRE.id_port = PORT.id_port
        INNER JOIN PIED ON ARBRE.id_pied = PIED.id_pied
        INNER JOIN LOCALISATION ON ARBRE.id_localisation = LOCALISATION.id_localisation
        ORDER BY ARBRE.id_arbre ASC'
    );

    $rows = $stmt->fetchAll();

    sendJsonResponse(200, [
        'success' => true,
        'count' => count($rows),
        'data' => $rows,
    ]);
}

function createArbre(PDO $pdo): void
{
    $data = parseBody();

    $espece = requireString($data, 'espece');
    $hauteurTotale = requireFloat($data, 'hauteur_totale');
    $hauteurTronc = requireFloat($data, 'hauteur_tronc');
    $diametreTronc = requireFloat($data, 'diametre_tronc');
    $latitude = requireFloat($data, 'latitude');
    $longitude = requireFloat($data, 'longitude');

    $remarquable = toBoolInt($data['remarquable'] ?? 0);
    $type = normalizeText((string) ($data['type'] ?? 'inconnu'));
    $etat = normalizeText((string) ($data['etat'] ?? 'EN PLACE'));
    $stadeDev = normalizeText((string) ($data['stade_developpement'] ?? 'inconnu'));
    $port = normalizeText((string) ($data['port'] ?? 'inconnu'));
    $pied = normalizeText((string) ($data['pied'] ?? 'inconnu'));
    $quartier = normalizeText((string) ($data['quartier'] ?? 'inconnu'));
    $secteur = normalizeText((string) ($data['secteur'] ?? 'inconnu'));
    $situation = normalizeText((string) ($data['situation'] ?? 'Alignement'));
    $ageEstime = max(0, (int) ($data['age_estime'] ?? 0));

    $dateEdited = date('Y-m-d');
    $datePlantation = normalizeText((string) ($data['date_plantation'] ?? ''), $dateEdited);

    $pdo->beginTransaction();

    try {
        $feuillageId = getOrCreateSimpleLabel($pdo, 'FEUILLAGE', 'id_feuillage', $type);
        $especeId = getOrCreateEspece($pdo, $espece, $feuillageId);

        $etatId = getOrCreateSimpleLabel($pdo, 'ETAT', 'id_etat', $etat);
        $stadeDevId = getOrCreateSimpleLabel($pdo, 'STADE_DEV', 'id_stad_dev', $stadeDev);
        $portId = getOrCreateSimpleLabel($pdo, 'PORT', 'id_port', $port);
        $piedId = getOrCreateSimpleLabel($pdo, 'PIED', 'id_pied', $pied);
        $situationId = getOrCreateSimpleLabel($pdo, 'SITUATION', 'id_situation', $situation);
        $localisationId = getOrCreateLocalisation($pdo, $quartier, $secteur, $longitude, $latitude);

        linkEspeceFeuillage($pdo, $feuillageId, $especeId);

        $stmt = $pdo->prepare(
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

        $stmt->execute([
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

        $pdo->commit();

        sendJsonResponse(201, [
            'success' => true,
            'message' => 'arbre ajoute avec succes',
            'id_arbre' => $arbreId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        sendJsonResponse(500, [
            'success' => false,
            'message' => 'erreur ajout arbre',
            'error' => $e->getMessage(),
        ]);
    }
}

try {
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        listArbres($pdo);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        createArbre($pdo);
    }

    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur api arbres',
        'error' => $e->getMessage(),
    ]);
}
