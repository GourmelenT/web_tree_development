#!/usr/bin/env python3
"""
Client2 wrapper pour prédiction d'âge
Accepte les paramètres en arguments et retourne JSON
"""
import sys
import json
import os
import joblib
import pandas as pd

try:
    os.chdir(os.path.dirname(__file__))
    
    # Vérifier les fichiers nécessaires
    if not os.path.exists("modele_age_classe.pkl"):
        sys.stderr.write("Erreur: modele_age_classe.pkl non trouvé\n")
        sys.exit(1)
    
    if not os.path.exists("colonnes_modele_age_classe.pkl"):
        sys.stderr.write("Erreur: colonnes_modele_age_classe.pkl non trouvé\n")
        sys.exit(1)
    
    # Vérifier les arguments
    if len(sys.argv) < 7:
        print(json.dumps({
            "success": False,
            "error": "Arguments manquants. Usage: script.py tronc_diam haut_tot haut_tronc stade_dev port pied"
        }))
        sys.exit(1)
    
    # Récupérer les arguments
    tronc_diam = float(sys.argv[1])
    haut_tot = float(sys.argv[2])
    haut_tronc = float(sys.argv[3])
    fk_stadedev = sys.argv[4]
    fk_port = sys.argv[5]
    fk_pied = sys.argv[6]
    
    # Charger le modèle et les colonnes
    model = joblib.load("modele_age_classe.pkl")
    model_columns = joblib.load("colonnes_modele_age_classe.pkl")
    
    # Créer la ligne d'entrée
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
    
    # Aligner les colonnes avec celles du modèle
    nouvel_arbre = nouvel_arbre.reindex(columns=model_columns, fill_value=0)
    
    # Prédiction
    prediction = model.predict(nouvel_arbre)[0]
    
    print(json.dumps({
        "success": True,
        "prediction_age": prediction
    }))
    sys.exit(0)
    
except Exception as e:
    print(json.dumps({
        "success": False,
        "error": str(e)
    }))
    sys.exit(1)
