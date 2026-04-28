import joblib
import pandas as pd
import os
import argparse
import unicodedata

FEATURE_COLUMNS = [
    "haut_tot",
    "tronc_diam",
    "age_estim",
    "fk_stadedev",
    "fk_port",
    "fk_pied",
    "fk_situation",
    "fk_revetement",
]

CATEGORICAL_COLUMNS = [
    "fk_stadedev",
    "fk_port",
    "fk_pied",
    "fk_situation",
    "fk_revetement",
]


def _normalize_token(value):
    """Normalise une valeur texte pour comparaison robuste (casse, accents, espaces)."""
    text = str(value).strip().upper()
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    # On retire les séparateurs pour aligner "SEMI LIBRE" et "semilibre".
    text = text.replace(" ", "").replace("-", "").replace("_", "")
    return text


def _build_category_alias_map(model):
    """Construit des correspondances normalisées -> catégorie vue en entraînement."""
    alias_map = {col: {} for col in CATEGORICAL_COLUMNS}
    try:
        preprocessor = model.named_steps["preprocessor"]
        cat_encoder = preprocessor.named_transformers_["cat"]
        categories = cat_encoder.categories_
        for col, values in zip(CATEGORICAL_COLUMNS, categories):
            for val in values:
                alias_map[col][_normalize_token(val)] = val
    except Exception:
        # Fallback: pas de mapping disponible, on laissera les valeurs telles quelles.
        return alias_map
    return alias_map


def _normalize_categorical_inputs(df, model):
    """Applique le mapping des catégories pour corriger les variantes d'écriture utilisateur."""
    alias_map = _build_category_alias_map(model)
    normalized_df = df.copy()
    for col in CATEGORICAL_COLUMNS:
        if col not in normalized_df.columns:
            continue
        col_alias = alias_map.get(col, {})
        normalized_df[col] = normalized_df[col].apply(
            lambda v: col_alias.get(_normalize_token(v), v) if pd.notna(v) else v
        )
    return normalized_df

def load_model():
    """Charge le modèle entraîné"""
    if not os.path.exists("model.pkl"):
        print("Erreur : Le modèle n'existe pas.")
        print("Exécutez d'abord : python train.py")
        return None
    
    print("Chargement du modèle...")
    model = joblib.load("model.pkl")
    if hasattr(model, "named_steps") and "model" in model.named_steps:
        model.named_steps["model"].set_params(n_jobs=1)
    print("Modèle chargé")
    return model

def predict_tree(model, data_dict):
    """Prédit l'état d'un arbre"""
    if model is None:
        return None
    
    df = pd.DataFrame([data_dict])
    df = _normalize_categorical_inputs(df, model)
    prediction = model.predict(df)
    probabilities = model.predict_proba(df)
    
    return {
        "prediction": prediction[0],
        "probabilities": probabilities[0],
        "classes": model.classes_
    }

def display_result(result):
    """Affiche les résultats avec détails"""
    print(f"\n{'='*50}")
    print(f"PRÉDICTION D'ÉTAT DE L'ARBRE")
    print(f"{'='*50}")
    print(f"\nÉtat prédit : {result['prediction']}")
    print(f"\nProbabilités par classe :")
    print(f"{'-'*50}")
    for class_name, prob in zip(result['classes'], result['probabilities']):
        bar_length = int(prob * 30)
        bar = '█' * bar_length + '░' * (30 - bar_length)
        print(f"   {class_name:20} [{bar}] {prob:.1%}")
    print(f"{'='*50}\n")


def explain_risk(row, risk_score, median_values, classes, probabilities):
    """Construit une explication simple et lisible du risque d'un arbre."""
    reasons = []

    if "EN PLACE" in classes:
        en_place_prob = probabilities[classes.index("EN PLACE")]
        reasons.append(f"probabilité d'être 'EN PLACE' faible ({en_place_prob:.1%})")

    # Raisons simples basées sur les valeurs observées dans le lot courant.
    if row.get("age_estim", 0) >= median_values.get("age_estim", 0):
        reasons.append("âge estimé élevé")
    if row.get("tronc_diam", 0) >= median_values.get("tronc_diam", 0):
        reasons.append("diamètre du tronc élevé")
    if row.get("haut_tot", 0) >= median_values.get("haut_tot", 0):
        reasons.append("hauteur totale élevée")

    if not reasons:
        reasons.append("profil global jugé défavorable par le modèle")

    return f"Risque élevé car {', '.join(reasons[:3])}. Score de risque = {risk_score:.1%}."


def predict_csv_batch(model, input_csv, output_csv="predictions_arbres.csv", risk_threshold=0.40):
    """Prédit l'état pour tous les arbres d'un CSV et détecte les arbres à risque."""
    if model is None:
        return None

    if not os.path.exists(input_csv):
        print(f"Fichier introuvable : {input_csv}")
        return None

    df = pd.read_csv(input_csv)

    missing_cols = [c for c in FEATURE_COLUMNS if c not in df.columns]
    if missing_cols:
        print("Colonnes manquantes dans le CSV :")
        for col in missing_cols:
            print(f"   - {col}")
        return None

    valid_mask = df[FEATURE_COLUMNS].notna().all(axis=1)
    df_valid = df.loc[valid_mask].copy()
    skipped_count = int((~valid_mask).sum())

    if df_valid.empty:
        print("Aucune ligne exploitable (valeurs manquantes sur les colonnes d'entrée).")
        return None

    # Normalisation des catégories avant inférence pour éviter les écarts de saisie.
    feature_df = _normalize_categorical_inputs(df_valid[FEATURE_COLUMNS], model)

    # On prédit en une seule fois pour tout le fichier afin d'obtenir un traitement automatique.
    predictions = model.predict(feature_df)
    probabilities = model.predict_proba(feature_df)
    classes = list(model.classes_)
    median_values = {
        "haut_tot": df_valid["haut_tot"].median(),
        "tronc_diam": df_valid["tronc_diam"].median(),
        "age_estim": df_valid["age_estim"].median(),
    }

    result_df = df_valid.copy()
    result_df["prediction_etat"] = predictions

    for idx, class_name in enumerate(classes):
        result_df[f"proba_{class_name}"] = probabilities[:, idx]

    # Score de risque: probabilité de ne pas être "EN PLACE"
    if "EN PLACE" in classes:
        en_place_idx = classes.index("EN PLACE")
        result_df["risk_score"] = 1.0 - probabilities[:, en_place_idx]
    else:
        result_df["risk_score"] = 1.0

    result_df["a_risque_tempete"] = result_df["risk_score"] >= risk_threshold
    result_df = result_df.sort_values(by="risk_score", ascending=False)

    # Ajoute une explication lisible pour les arbres à risque.
    result_df["risk_reason"] = result_df.apply(
        lambda row: explain_risk(
            row=row,
            risk_score=row["risk_score"],
            median_values=median_values,
            classes=classes,
            probabilities=row[[f"proba_{c}" for c in classes]].to_numpy(),
        ),
        axis=1,
    )

    result_df.to_csv(output_csv, index=False)

    risky_df = result_df[result_df["a_risque_tempete"]].copy()
    risky_output_csv = output_csv.replace(".csv", "_a_risque.csv")
    risky_df.to_csv(risky_output_csv, index=False)

    print("\nRésumé prédiction batch")
    print(f"   Lignes totales: {len(df)}")
    print(f"   Lignes prédites: {len(result_df)}")
    print(f"   Lignes ignorées (NaN): {skipped_count}")
    print(f"   Arbres à risque (seuil {risk_threshold:.0%}): {len(risky_df)}")
    print(f"\nFichier complet: {output_csv}")
    print(f"Fichier arbres à risque: {risky_output_csv}")

    return {
        "all_predictions": result_df,
        "risky_predictions": risky_df,
        "output_csv": output_csv,
        "risky_output_csv": risky_output_csv,
    }

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Prédictions arbre: unitaire ou batch CSV")
    parser.add_argument("--batch", action="store_true", help="Active la prédiction batch CSV")
    parser.add_argument("--input", default="Data_Arbre_Input.csv", help="Fichier CSV d'entrée")
    parser.add_argument("--output", default="predictions_arbres.csv", help="Fichier CSV de sortie")
    parser.add_argument("--threshold", type=float, default=0.40, help="Seuil de risque [0-1]")
    args = parser.parse_args()

    model = load_model()

    if model is not None and args.batch:
        predict_csv_batch(
            model=model,
            input_csv=args.input,
            output_csv=args.output,
            risk_threshold=args.threshold,
        )
    elif model is not None:
        arbre = {
            "haut_tot": 10,
            "tronc_diam": 30,
            "age_estim": 20,
            "fk_stadedev": "Adulte",
            "fk_port": "Normal",
            "fk_pied": "Plein",
            "fk_situation": "Alignement",
            "fk_revetement": "Gazon"
        }

        result = predict_tree(model, arbre)
        display_result(result)
