import argparse

from predict import load_model, predict_tree


def ask_tree_features():
    """Demande les caracteristiques d'un arbre dans la console."""
    print("\nSaisissez les caracteristiques de l'arbre:")
    print("-" * 45)

    return {
        "haut_tot": float(input("Hauteur totale (m): ").strip()),
        "tronc_diam": float(input("Diametre du tronc (cm): ").strip()),
        "age_estim": float(input("Age estime (ans): ").strip()),
        "fk_stadedev": input("Stade de developpement (ex: Adulte): ").strip(),
        "fk_port": input("Port (ex: Semi libre): ").strip(),
        "fk_pied": input("Pied (ex: Gazon): ").strip(),
        "fk_situation": input("Situation (ex: Alignement): ").strip(),
        "fk_revetement": input("Revetement (ex: Oui): ").strip(),
    }


def predict_deracinement(model, tree_data, threshold=0.40):
    """Retourne une prediction de deracinement tempete a partir du modele multiclasses."""
    result = predict_tree(model, tree_data)
    if result is None:
        return None

    classes = list(result["classes"])
    probabilities = result["probabilities"]

    if "EN PLACE" in classes:
        en_place_prob = float(probabilities[classes.index("EN PLACE")])
        risk_score = 1.0 - en_place_prob
    else:
        # Si la classe EN PLACE n'existe pas, on prend un risque maximal par securite.
        en_place_prob = 0.0
        risk_score = 1.0

    is_at_risk = risk_score >= threshold

    return {
        "prediction_etat": result["prediction"],
        "proba_en_place": en_place_prob,
        "risk_score": risk_score,
        "seuil": threshold,
        "deracinement_tempete_predit": is_at_risk,
    }


def display_deracinement_result(output):
    """Affiche le resultat final de facon lisible."""
    print("\n" + "=" * 55)
    print("RESULTAT - PREDICTION DE DERACINEMENT TEMPETE")
    print("=" * 55)
    print(f"Etat predit: {output['prediction_etat']}")
    print(f"Probabilite d'etre EN PLACE: {output['proba_en_place']:.1%}")
    print(f"Score de risque: {output['risk_score']:.1%}")
    print(f"Seuil de decision: {output['seuil']:.0%}")

    if output["deracinement_tempete_predit"]:
        print("Prediction deracinement tempete: OUI (arbre a risque)")
    else:
        print("Prediction deracinement tempete: NON (arbre plutot stable)")

    print("=" * 55 + "\n")


def main():
    parser = argparse.ArgumentParser(
        description="Prediction de deracinement tempete pour un arbre"
    )
    parser.add_argument(
        "--threshold",
        type=float,
        default=0.40,
        help="Seuil de risque entre 0 et 1 (defaut: 0.40)",
    )
    args = parser.parse_args()

    if not (0.0 <= args.threshold <= 1.0):
        print("Erreur: --threshold doit etre entre 0 et 1.")
        return

    model = load_model()
    if model is None:
        return

    try:
        tree_data = ask_tree_features()
    except ValueError:
        print("Erreur: une valeur numerique est invalide.")
        return

    output = predict_deracinement(model, tree_data, threshold=args.threshold)
    if output is not None:
        display_deracinement_result(output)


if __name__ == "__main__":
    main()
