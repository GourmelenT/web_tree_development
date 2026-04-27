import joblib
import pandas as pd
from pathlib import Path

MODEL_FILES = [
    "scaler.pkl",
    "kmeans_3.pkl",
    "mapping_pg.pkl",
    "mapping_mg.pkl",
    "mapping_pmg.pkl",
]


def check_model_files() -> bool:
    missing = [f for f in MODEL_FILES if not Path(f).exists()]
    if missing:
        print("Fichiers modeles manquants:")
        for file_name in missing:
            print(f"- {file_name}")
        print("\nLance d'abord script.py pour entrainer les modeles.")
        return False
    return True


def ask_cluster_mode() -> str:
    print("Choisis le mode de classification:")
    print("1 - Petit / Grand")
    print("2 - Moyen / Grand")
    print("3 - Petit / Moyen / Grand")

    choice = input("Ton choix (1, 2 ou 3): ").strip()

    mode_by_choice = {
        "1": "pg",
        "2": "mg",
        "3": "pmg",
    }
    return mode_by_choice.get(choice, "")


def ask_tree_values():
    hauteur = input("Hauteur de l'arbre (m): ").strip()
    diametre = input("Diametre du tronc (cm): ").strip()

    try:
        return float(hauteur), float(diametre)
    except ValueError:
        return None, None


def main() -> None:
    if not check_model_files():
        return

    mode = ask_cluster_mode()
    if mode == "":
        print("Choix invalide.")
        return

    scaler = joblib.load("scaler.pkl")
    kmeans = joblib.load("kmeans_3.pkl")
    mapping = joblib.load(f"mapping_{mode}.pkl")

    hauteur, diametre = ask_tree_values()
    if hauteur is None or diametre is None:
        print("Erreur de saisie: entre des nombres valides.")
        return

    x = pd.DataFrame([[hauteur, diametre]], columns=["haut_tot", "tronc_diam"])
    x_scaled = scaler.transform(x)
    cluster = kmeans.predict(x_scaled)[0]
    categorie = mapping.get(cluster, "Inconnu")

    mode_label = {
        "pg": "Petit / Grand",
        "mg": "Moyen / Grand",
        "pmg": "Petit / Moyen / Grand",
    }[mode]

    print(f"\nCategorie predite ({mode_label}): {categorie}")


if __name__ == "__main__":
    main()