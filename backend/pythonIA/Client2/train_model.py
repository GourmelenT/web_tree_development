#!/usr/bin/env python3
"""
Script d'entraînement pour Client2 - Prédiction d'âge
Génère les fichiers pkl nécessaires pour le modèle
"""
import os
import sys
import pandas as pd
import joblib
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder

try:
    os.chdir(os.path.dirname(__file__))
    
    # Charger le CSV depuis Client1
    csv_path = os.path.join(os.path.dirname(__file__), '..', 'Client1', 'export_IA.csv')
    if not os.path.exists(csv_path):
        print(f"Erreur: Fichier CSV non trouvé: {csv_path}", file=sys.stderr)
        sys.exit(1)
    
    print(f"Chargement du CSV: {csv_path}")
    df = pd.read_csv(csv_path, encoding='latin-1')
    
    # Créer la variable cible (classe d'age)
    if 'age_estim' in df.columns:
        def classify_age(age):
            if pd.isna(age) or age < 0:
                return 'ADULTE'  # défaut
            if age < 20:
                return 'JEUNE'
            elif age < 60:
                return 'ADULTE'
            else:
                return 'VIEUX'
        
        df['age_classe'] = df['age_estim'].apply(classify_age)
    else:
        print("Erreur: Colonne 'age_estim' non trouvée dans le CSV", file=sys.stderr)
        sys.exit(1)
    
    # Préparer les features
    features_needed = ['tronc_diam', 'haut_tot', 'haut_tronc', 'fk_stadedev', 'fk_port', 'fk_pied']
    missing = [f for f in features_needed if f not in df.columns]
    
    if missing:
        print(f"Erreur: Colonnes manquantes: {missing}", file=sys.stderr)
        print(f"Colonnes disponibles: {list(df.columns)}", file=sys.stderr)
        sys.exit(1)
    
    # Créer le dataset d'entraînement
    X = df[features_needed].copy()
    y = df['age_classe'].copy()
    
    # Remplir les valeurs manquantes
    X = X.fillna(0)
    
    # Encoder les variables qualitatives
    X_encoded = pd.get_dummies(X, columns=['fk_stadedev', 'fk_port', 'fk_pied'], drop_first=True)
    
    print(f"Dataset: {X_encoded.shape[0]} lignes, {X_encoded.shape[1]} colonnes")
    print(f"Classes d'âge: {y.value_counts().to_dict()}")
    
    # Entraîner le modèle
    model = RandomForestClassifier(n_estimators=100, random_state=42, max_depth=10)
    model.fit(X_encoded, y)
    
    # Sauvegarder le modèle
    joblib.dump(model, 'modele_age_classe.pkl')
    joblib.dump(X_encoded.columns.tolist(), 'colonnes_modele_age_classe.pkl')
    
    print("✓ Modèle entraîné et sauvegardé avec succès!")
    print(f"  - modele_age_classe.pkl")
    print(f"  - colonnes_modele_age_classe.pkl")
    
except Exception as e:
    print(f"Erreur: {e}", file=sys.stderr)
    import traceback
    traceback.print_exc(file=sys.stderr)
    sys.exit(1)
