#!/usr/bin/env python3
"""
Client1 wrapper batch pour prédiction de clusters
Accepte un CSV depuis stdin et retourne JSON avec clusters pour tous
"""
import sys
import json
import os
import csv

try:
    os.chdir(os.path.dirname(__file__))
    
    import joblib
    import numpy as np
    
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
    
    # Lire le CSV depuis l'argument
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Arguments manquants. Usage: script.py <csv_file>"
        }))
        sys.exit(1)
    
    csv_file = sys.argv[1]
    if not os.path.exists(csv_file):
        print(json.dumps({
            "success": False,
            "error": f"Fichier non trouvé: {csv_file}"
        }))
        sys.exit(1)
    
    # Lire le CSV
    clusters_map = {}
    cluster_names = {0: "PETIT", 1: "MOYEN", 2: "GRAND"}
    
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            try:
                id_arbre = row['id_arbre']
                hauteur = float(row['hauteur_totale'])
                diametre = float(row['diametre_tronc'])
                
                # Préparer et normaliser
                X = np.array([[hauteur, diametre]])
                X_scaled = scaler.transform(X)
                
                # Prédire
                cluster_id = int(kmeans.predict(X_scaled)[0])
                cluster_name = cluster_names.get(cluster_id, "INCONNU")
                
                clusters_map[id_arbre] = {
                    "cluster_id": cluster_id,
                    "cluster_name": cluster_name
                }
            except Exception as e:
                clusters_map[id_arbre] = {
                    "cluster_id": None,
                    "cluster_name": "ERROR",
                    "error": str(e)
                }
    
    # Retourner le résultat
    print(json.dumps({
        "success": True,
        "clusters": clusters_map
    }))
    sys.exit(0)
    
except Exception as e:
    print(json.dumps({
        "success": False,
        "error": str(e)
    }))
    sys.exit(1)
