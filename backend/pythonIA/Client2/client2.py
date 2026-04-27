import os
import joblib
import pandas as pd

# Vérification des fichiers nécessaires
if not os.path.exists("modele_age_classe.pkl"):
    print("Erreur : fichier 'modele_age_classe.pkl' introuvable.")
    exit()

if not os.path.exists("colonnes_modele_age_classe.pkl"):
    print("Erreur : fichier 'colonnes_modele_age_classe.pkl' introuvable.")
    exit()

# Chargement du modèle et des colonnes
model = joblib.load("modele_age_classe.pkl")
model_columns = joblib.load("colonnes_modele_age_classe.pkl")

print("=== Prédiction de la classe d'âge d'un arbre ===")

# Saisie utilisateur
tronc_diam = float(input("Diamètre du tronc : "))
haut_tot = float(input("Hauteur totale : "))
haut_tronc = float(input("Hauteur du tronc : "))

fk_stadedev = input("Stade de développement : ")
fk_port = input("Type de port : ")
fk_pied = input("Type de pied : ")

# Création de la ligne d'entrée
nouvel_arbre = pd.DataFrame([{
    "tronc_diam": tronc_diam,
    "haut_tot": haut_tot,
    "haut_tronc": haut_tronc,
    "fk_stadedev": fk_stadedev,
    "fk_port": fk_port,
    "fk_pied": fk_pied
}])

# Encodage des variables qualitatives
nouvel_arbre = pd.get_dummies(
    nouvel_arbre,
    columns=["fk_stadedev", "fk_port", "fk_pied"],
    drop_first=True
)

# Alignement des colonnes avec celles du modèle
nouvel_arbre = nouvel_arbre.reindex(columns=model_columns, fill_value=0)
# Prédiction
prediction = model.predict(nouvel_arbre)

print("Classe d'âge prédite :", prediction[0])