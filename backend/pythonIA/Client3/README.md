# Projet Prediction d'Etat des Arbres

Ce projet predit l'etat d'arbres et identifie les arbres a risque de deracinement en tempete.

## Prerequis
- Python 3.12+
- Dependances installees via requirements.txt

## Installation
```bash
pip install -r requirements.txt
```

## Fichiers principaux
```text
IA/
|- train.py                          # Entrainement du modele (GridSearchCV)
|- predict.py                        # Prediction unitaire (exemple) et batch CSV
|- predict_deracinement_single.py    # Prediction unitaire interactive (console)
|- main.py                           # Lancement batch automatique sur tout le CSV
|- Data_Arbre_Input.csv              # Donnees source
|- model.pkl                         # Modele entraine (genere apres train.py)
|- training_results.json             # Metriques d'entrainement
|- predictions_arbres.csv            # Predictions sur tout le dataset
`- predictions_arbres_a_risque.csv   # Sous-ensemble des arbres a risque
```

## Demarrage rapide

1. Entrainer le modele
```bash
python train.py
```

2. Choisir un mode de lancement

### 1) Lancement unitaire (1 arbre)
Mode interactif recommande:
```bash
python predict_deracinement_single.py
```

Option seuil personnalise:
```bash
python predict_deracinement_single.py --threshold 0.50
```

Le script demande les 8 caracteristiques d'un arbre dans la console puis affiche:
- l'etat predit
- la probabilite d'etre EN PLACE
- le score de risque
- la decision finale de deracinement tempete selon le seuil

### 2) Lancement sur toutes les donnees (batch CSV)
Mode automatique (sans parametre), sur Data_Arbre_Input.csv:
```bash
python main.py
```

Ce mode genere:
- predictions_arbres.csv
- predictions_arbres_a_risque.csv

Alternative batch avec parametres:
```bash
python predict.py --batch --input Data_Arbre_Input.csv --output predictions_arbres.csv --threshold 0.40
```

## Important si le CSV change
Si vous modifiez Data_Arbre_Input.csv, relancez dans cet ordre:

1. `python train.py` (met a jour model.pkl)
2. `python main.py` (regenere les fichiers de prediction)

Sinon vous utiliserez un ancien modele avec de nouvelles donnees.

## Logique de risque
Le modele predit une probabilite pour chaque classe (EN PLACE, SUPPRIME, ABATTU, etc.).

Le score de risque est calcule ainsi:

`risk_score = 1 - P(EN PLACE)`

Un arbre est marque a risque tempete si:

`risk_score >= seuil`

Seuil par defaut: 0.40 (40%).

## Colonnes de sortie utiles (batch)
Dans predictions_arbres.csv et predictions_arbres_a_risque.csv:
- prediction_etat: etat predit
- risk_score: score de risque
- a_risque_tempete: booleen de decision
- risk_reason: explication textuelle du risque

## Details techniques (entrainement)
Dans train.py:
- selection des colonnes utiles
- suppression des lignes avec NaN sur ces colonnes
- StandardScaler (numeriques) + OneHotEncoder (categoriques)
- RandomForestClassifier
- optimisation des hyperparametres avec GridSearchCV (5-fold)
- sauvegarde de model.pkl et training_results.json

## Depannage
- model.pkl absent: lancer `python train.py`
- Data_Arbre_Input.csv absent: placer le fichier dans le dossier IA
- resultats inchanges apres modification CSV: relancer `python train.py` puis `python main.py`

Auteur: Tom Macario Projet IA - ISEN FISA4 S8  
Date: 2026
