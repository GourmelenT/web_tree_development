#!/usr/bin/env python3
"""
Client1 wrapper pour prédiction de clusters
Accepte les paramètres en arguments et retourne JSON
"""
import sys
import json
import os

try:
    os.chdir(os.path.dirname(__file__))
    
    import joblib
    import numpy as np
    
    # Vérifier les arguments
    if len(sys.argv) < 3:
        print(json.dumps({
            "success": False,
            "error": "Arguments manquants. Usage: script.py hauteur_totale diametre_tronc"
        }))
        sys.exit(1)
    
    # Charger le modèle et le scaler
    try:
        kmeans = joblib.load('kmeans_3.pkl')
        scaler = joblib.load('scaler.pkl')
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": f"Erreur chargement modèle: {str(e)}"
        }))
        sys.exit(1)
    
    # Récupérer les données
    hauteur_totale = float(sys.argv[1])
    diametre_tronc = float(sys.argv[2])
    
    # Préparer les données pour la prédiction
    X = np.array([[hauteur_totale, diametre_tronc]])
    
    # Normaliser
    X_scaled = scaler.transform(X)
    
    # Prédire le cluster
    cluster = int(kmeans.predict(X_scaled)[0])
    
    # Mapper le cluster aux noms
    cluster_names = {0: "PETIT", 1: "MOYEN", 2: "GRAND"}
    cluster_name = cluster_names.get(cluster, "INCONNU")
    
    # Retourner le résultat
    print(json.dumps({
        "success": True,
        "cluster_id": cluster,
        "cluster_name": cluster_name,
        "hauteur": hauteur_totale,
        "diametre": diametre_tronc
    }))
    sys.exit(0)
    
except Exception as e:
    print(json.dumps({
        "success": False,
        "error": str(e)
    }))
    sys.exit(1)
