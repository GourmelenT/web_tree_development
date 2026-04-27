<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

function insertSampleData(): void
{
    $pdo = getConnection();

    try {
        // debut transac
        $pdo->beginTransaction();

        // tables ref d'abord
        $stmt = $pdo->prepare('INSERT INTO FEUILLAGE (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Caduc']);
        $feuillageId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO ESPECE (nom_latin, feuillage) VALUES (:nom_latin, :feuillage)');
        $stmt->execute([
            ':nom_latin' => 'Acer platanoides',
            ':feuillage' => $feuillageId,
        ]);
        $especeId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO ETAT (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Bon']);
        $etatId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO PORT (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Evase']);
        $portId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO LOCALISATION (quartier, secteur) VALUES (:quartier, :secteur)');
        $stmt->execute([
            ':quartier' => 'Centre-ville',
            ':secteur' => 'Secteur A',
        ]);
        $localisationId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO SITUATION (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Alignement']);
        $situationId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO PIED (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Gazon']);
        $piedId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO STADE_DEV (libelle) VALUES (:libelle)');
        $stmt->execute([':libelle' => 'Adulte']);
        $stadeDevId = (int) $pdo->lastInsertId();

        // lien espece/feuillage
        $stmt = $pdo->prepare('INSERT INTO est_de_type (id_feuillage, id_espece) VALUES (:id_feuillage, :id_espece)');
        $stmt->execute([
            ':id_feuillage' => $feuillageId,
            ':id_espece' => $especeId,
        ]);

        // insert arbre principal
        $stmt = $pdo->prepare(
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

        $stmt->execute([
            ':hauteur_tronc' => 2.3,
            ':hauteur_totale' => 7.8,
            ':diametre_tronc' => 0.45,
            ':remarquable' => 0,
            ':date_plantation' => '2010-04-15',
            ':age_estime' => 16,
            ':cluster_prediction' => 1,
            ':date_edited' => '2026-04-27',
            ':id_espece' => $especeId,
            ':id_etat' => $etatId,
            ':id_stad_dev' => $stadeDevId,
            ':id_port' => $portId,
            ':id_pied' => $piedId,
            ':id_localisation' => $localisationId,
        ]);

        $arbreId = (int) $pdo->lastInsertId();

        // lien situation/arbre
        $stmt = $pdo->prepare('INSERT INTO possede (id_situation, id_arbre) VALUES (:id_situation, :id_arbre)');
        $stmt->execute([
            ':id_situation' => $situationId,
            ':id_arbre' => $arbreId,
        ]);

        // fin ok
        $pdo->commit();
        echo "Donnees inserees avec succes." . PHP_EOL;
    } catch (Throwable $e) {
        // annule si erreur catch
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo 'Erreur lors de l\'insertion: ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    insertSampleData();
}
