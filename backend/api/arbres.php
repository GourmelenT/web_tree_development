<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function listArbres(PDO $pdo): void
{
    $rows = fetchAll($pdo, arbresSql('ORDER BY ARBRE.id_arbre ASC'));
    ok(['count' => count($rows), 'data' => $rows]);
}

function createArbre(PDO $pdo): void
{
    $data = body();
    $today = date('Y-m-d');

    $ids = [];
    $pdo->beginTransaction();

    try {
        $ids['feuillage'] = getLabelId($pdo, 'FEUILLAGE', 'id_feuillage', text($data, 'type', 'inconnu'));
        $ids['espece'] = getOrCreate($pdo, 'ESPECE', 'id_espece', [
            'nom_latin' => normalizeLatinName(requiredText($data, 'espece')),
            'feuillage' => $ids['feuillage'],
        ]);
        $ids['etat'] = getLabelId($pdo, 'ETAT', 'id_etat', text($data, 'etat', 'EN PLACE'));
        $ids['stade'] = getLabelId($pdo, 'STADE_DEV', 'id_stad_dev', text($data, 'stade_developpement', 'inconnu'));
        $ids['port'] = getLabelId($pdo, 'PORT', 'id_port', text($data, 'port', 'inconnu'));
        $ids['pied'] = getLabelId($pdo, 'PIED', 'id_pied', text($data, 'pied', 'inconnu'));
        $ids['situation'] = getLabelId($pdo, 'SITUATION', 'id_situation', text($data, 'situation', 'Alignement'));
        $ids['localisation'] = getOrCreate($pdo, 'LOCALISATION', 'id_localisation', [
            'quartier' => text($data, 'quartier', 'inconnu'),
            'secteur' => text($data, 'secteur', 'inconnu'),
            'longitude' => requiredFloat($data, 'longitude'),
            'latitude' => requiredFloat($data, 'latitude'),
        ]);

        insertIgnore(
            $pdo,
            'INSERT INTO est_de_type (id_feuillage, id_espece) VALUES (:feuillage, :espece)',
            [':feuillage' => $ids['feuillage'], ':espece' => $ids['espece']]
        );

        $stmt = $pdo->prepare(
            'INSERT INTO ARBRE (
                hauteur_tronc, hauteur_totale, diametre_tronc, remarquable,
                date_plantation, age_estime, cluster_prediction, date_edited,
                id_espece, id_etat, id_stad_dev, id_port, id_pied, id_localisation
            ) VALUES (
                :hauteur_tronc, :hauteur_totale, :diametre_tronc, :remarquable,
                :date_plantation, :age_estime, 0, :date_edited,
                :id_espece, :id_etat, :id_stad_dev, :id_port, :id_pied, :id_localisation
            )'
        );
        $stmt->execute([
            ':hauteur_tronc' => requiredFloat($data, 'hauteur_tronc'),
            ':hauteur_totale' => requiredFloat($data, 'hauteur_totale'),
            ':diametre_tronc' => requiredFloat($data, 'diametre_tronc'),
            ':remarquable' => boolInt($data['remarquable'] ?? 0),
            ':date_plantation' => text($data, 'date_plantation', $today),
            ':age_estime' => max(0, (int) ($data['age_estime'] ?? 0)),
            ':date_edited' => $today,
            ':id_espece' => $ids['espece'],
            ':id_etat' => $ids['etat'],
            ':id_stad_dev' => $ids['stade'],
            ':id_port' => $ids['port'],
            ':id_pied' => $ids['pied'],
            ':id_localisation' => $ids['localisation'],
        ]);

        $arbreId = (int) $pdo->lastInsertId();
        insertIgnore(
            $pdo,
            'INSERT INTO possede (id_situation, id_arbre) VALUES (:situation, :arbre)',
            [':situation' => $ids['situation'], ':arbre' => $arbreId]
        );

        $pdo->commit();
        ok(['message' => 'arbre ajoute avec succes', 'id_arbre' => $arbreId], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fail('erreur ajout arbre', 500, ['error' => $e->getMessage()]);
    }
}

function deleteArbre(PDO $pdo): void
{
    $id = (int) ($_GET['id_arbre'] ?? 0);
    if ($id <= 0) {
        fail('id_arbre invalide', 400);
    }
    if (!fetchOne($pdo, 'SELECT id_arbre FROM ARBRE WHERE id_arbre = :id', [':id' => $id])) {
        fail('arbre non trouve', 404);
    }

    $pdo->prepare('DELETE FROM possede WHERE id_arbre = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM ARBRE WHERE id_arbre = :id')->execute([':id' => $id]);

    ok(['message' => 'arbre supprime avec succes', 'id_arbre' => $id]);
}

try {
    $pdo = getConnection();

    switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
        case 'GET':
            listArbres($pdo);
            break;
        case 'POST':
            createArbre($pdo);
            break;
        case 'DELETE':
            deleteArbre($pdo);
            break;
        default:
            fail('methode non autorisee', 405);
    }
} catch (Throwable $e) {
    fail('erreur api arbres', 500, ['error' => $e->getMessage()]);
}
