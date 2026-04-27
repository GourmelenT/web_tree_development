#!/usr/bin/env python3
"""
Client3 wrapper pour prédiction de risque de déracinement
Accepte les paramètres en arguments et retourne JSON
"""
import sys
import json
import os

try:
    os.chdir(os.path.dirname(__file__))
    
    # Ajouter le répertoire courant au chemin Python
    sys.path.insert(0, os.getcwd())
    
    from predict import load_model, predict_tree
    
    # Vérifier les arguments
    if len(sys.argv) < 9:
        print(json.dumps({
            "success": False,
            "error": "Arguments manquants. Usage: script.py haut_tot tronc_diam age_estim stade_dev port pied situation revetement"
        }))
        sys.exit(1)
    
    # Récupérer les arguments
    tree_data = {
        "haut_tot": float(sys.argv[1]),
        "tronc_diam": float(sys.argv[2]),
        "age_estim": float(sys.argv[3]),
        "fk_stadedev": sys.argv[4],
        "fk_port": sys.argv[5],
        "fk_pied": sys.argv[6],
        "fk_situation": sys.argv[7],
        "fk_revetement": sys.argv[8],
    }
    
    # Charger le modèle et prédire (silencieusement)
    model = load_model()
    result = predict_tree(model, tree_data)
    
    if result is None:
        print(json.dumps({
            "success": False,
            "error": "Erreur lors de la prédiction"
        }))
        sys.exit(1)
    
    # Calculer le risque
    classes = list(result["classes"])
    probabilities = result["probabilities"]
    
    if "EN PLACE" in classes:
        en_place_prob = float(probabilities[classes.index("EN PLACE")])
        risk_score = 1.0 - en_place_prob
    else:
        en_place_prob = 0.0
        risk_score = 1.0
    
    is_at_risk = risk_score >= 0.40
    
    print(json.dumps({
        "success": True,
        "prediction_etat": result["prediction"],
        "proba_en_place": en_place_prob,
        "risk_score": risk_score,
        "deracinement_tempete_predit": is_at_risk
    }))
    sys.exit(0)
    
except Exception as e:
    print(json.dumps({
        "success": False,
        "error": str(e)
    }))
    sys.exit(1)
