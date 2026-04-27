import pandas as pd
from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.pipeline import Pipeline
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score, 
    classification_report, 
    confusion_matrix,
    precision_score,
    recall_score,
    f1_score,
    roc_auc_score,
    roc_curve
)
import joblib
import os
import json
from datetime import datetime

def train_model():
    """Entraîne le modèle avec GridSearchCV et le sauvegarde"""
    
    print("Chargement des données...")
    df = pd.read_csv("Data_Arbre_Input.csv")
    
    print(f"   Lignes initiales : {len(df)}")
    print(f"   Colonnes initiales : {df.shape[1]}")
    
    #Nettoyage des données
    print("\nNettoyage des données...")
    print("   Suppression des lignes avec valeurs manquantes...")
    
    # Colonnes réellement utiles pour le besoin client.
    num_cols = ["haut_tot", "tronc_diam", "age_estim"]
    cat_cols = ["fk_stadedev", "fk_port", "fk_pied", "fk_situation", "fk_revetement"]
    target_col = "fk_arb_etat"
    
    # On ne garde que les variables d'entrée et la cible pour éviter de polluer le modèle.
    cols_to_use = num_cols + cat_cols + [target_col]
    df_clean = df[cols_to_use].copy()
    
    # Supprimer les lignes avec NaN dans les colonnes utilisées
    df_clean = df_clean.dropna()
    
    print(f"   Lignes après nettoyage : {len(df_clean)}")
    print(f"   Lignes supprimées : {len(df) - len(df_clean)}")
    
    # Vérifier qu'il reste des données
    if len(df_clean) == 0:
        print("ERREUR : Aucune donnée valide après nettoyage !")
        return None
    
    # Target
    y = df_clean[target_col]
    
    # Features
    X = df_clean.drop(columns=[target_col])
    
    # Prétraitement appliqué automatiquement à chaque fold et sauvegardé dans le pipeline.
    preprocessor = ColumnTransformer([
        ("num", StandardScaler(), num_cols),
        ("cat", OneHotEncoder(handle_unknown="ignore", sparse_output=False), cat_cols)
    ])
    
    # Pipeline complet : prétraitement + modèle de classification.
    pipeline = Pipeline([
        ("preprocessor", preprocessor),
        ("model", RandomForestClassifier(random_state=42, n_jobs=-1))
    ])
    
    # Split
    print("Division train/test (80/20)...")
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    
    # Recherche exhaustive des meilleurs hyperparamètres demandée par le besoin client.
    print("\nOptimisation des hyperparamètres avec GridSearchCV...")
    print("Testage de différentes combinaisons...\n")
    
    param_grid = {
        'model__n_estimators': [50, 100, 200],
        'model__max_depth': [10, 20, None],
        'model__min_samples_split': [2, 5, 10],
        'model__min_samples_leaf': [1, 2, 4]
    }
    
    grid_search = GridSearchCV(
        pipeline,
        param_grid,
        cv=5,  # 5-fold cross-validation
        scoring='accuracy',
        n_jobs=-1,
        verbose=2
    )
    
    # Entraînement avec GridSearchCV
    print("Entraînement du modèle...")
    grid_search.fit(X_train, y_train)
    
    best_model = grid_search.best_estimator_
    best_params = grid_search.best_params_
    best_score = grid_search.best_score_
    
    print(f"\nMeilleur score CV : {best_score:.4f}")
    print(f"Meilleurs hyperparamètres :")
    for param, value in best_params.items():
        print(f"   {param}: {value}")
    
    # Évaluation sur l'ensemble de test
    print("\nÉvaluation sur l'ensemble de test...")
    y_pred = best_model.predict(X_test)
    y_pred_proba = best_model.predict_proba(X_test)
    
    # Calcul des métriques
    accuracy = accuracy_score(y_test, y_pred)
    precision = precision_score(y_test, y_pred, average='weighted', zero_division=0)
    recall = recall_score(y_test, y_pred, average='weighted', zero_division=0)
    f1 = f1_score(y_test, y_pred, average='weighted', zero_division=0)
    
    print(f"\nMétriques de performance :")
    print(f"Précision : {accuracy:.4f}")
    print(f"Précision pondérée : {precision:.4f}")
    print(f"Rappel pondéré : {recall:.4f}")
    print(f"F1-Score pondéré : {f1:.4f}")
    
    # Matrice de confusion
    cm = confusion_matrix(y_test, y_pred)
    print(f"\nMatrice de confusion :\n{cm}")
    
    # Rapport de classification détaillé
    print(
        f"\nRapport de classification détaillé :\n"
        f"{classification_report(y_test, y_pred, zero_division=0)}"
    )
    
    # Sauvegarde du meilleur modèle
    print("\nSauvegarde du modèle et des résultats...")
    joblib.dump(best_model, "model.pkl")
    print("Modèle enregistré : model.pkl")
    
    # Sauvegarde des résultats d'optimisation
    results = {
        "timestamp": datetime.now().isoformat(),
        "best_params": best_params,
        "best_cv_score": float(best_score),
        "test_accuracy": float(accuracy),
        "test_precision": float(precision),
        "test_recall": float(recall),
        "test_f1": float(f1),
        "grid_search_results": {
            "best_index": int(grid_search.best_index_),
            "n_splits": grid_search.cv
        }
    }
    
    with open("training_results.json", "w") as f:
        json.dump(results, f, indent=4)
    print("Résultats d'optimisation : training_results.json")
    
    print("\nEntraînement terminé !")
    return best_model

if __name__ == "__main__":
    train_model()