# Prediction de la classe d'age d'un arbre

Ce projet permet de predire la classe d'age d'un arbre a partir de variables quantitatives et qualitatives issues d'un jeu de donnees CSV.

Le workflow est en deux parties :
- `script.py` entraine plusieurs modeles de classification et sauvegarde le meilleur
- `client2.py` charge le modele sauvegarde et fait une prediction a partir d'une saisie utilisateur

## Objectif

Le projet cherche a classer un arbre dans l'une des trois categories suivantes :
- `jeune` si `age_estim < 20`
- `adulte` si `20 <= age_estim < 60`
- `vieux` si `age_estim >= 60`

La variable cible est creee a partir de la colonne `age_estim` du fichier `export_IA.csv`.

## Fichiers du projet

- `export_IA.csv` : jeu de donnees utilise pour l'entrainement
- `script.py` : script d'entrainement et de selection du meilleur modele
- `client2.py` : script de prediction en ligne de commande
- `modele_age_classe.pkl` : modele final sauvegarde avec `joblib`
- `colonnes_modele_age_classe.pkl` : liste des colonnes attendues par le modele apres encodage

## Variables utilisees

Variables quantitatives :
- `tronc_diam`
- `haut_tot`
- `haut_tronc`

Variables qualitatives testees :
- `fk_stadedev`
- `fk_port`
- `fk_pied`

## Methode d'entrainement

Le script `script.py` :

1. charge `export_IA.csv`
2. supprime les lignes ou `age_estim` est manquant
3. cree la cible `classe_age`
4. teste plusieurs configurations de variables
5. encode les variables qualitatives avec `pd.get_dummies(..., drop_first=True)`
6. decoupe les donnees en apprentissage et test avec `train_test_split`
7. entraine un `RandomForestClassifier`
8. optimise les hyperparametres avec `GridSearchCV`
9. compare les modeles et conserve celui avec la meilleure `accuracy` sur le jeu de test
10. sauvegarde le meilleur modele et les colonnes du jeu de donnees final

## Hyperparametres testes

La grille de recherche utilisee dans `script.py` est :

```python
param_grid = {
    "n_estimators": [50, 100],
    "max_depth": [5, 10, None],
    "min_samples_split": [2, 5]
}
```

Le modele sauvegarde dans `modele_age_classe.pkl` est un `RandomForestClassifier` avec notamment :

```python
{
    "class_weight": "balanced",
    "max_depth": None,
    "min_samples_split": 5,
    "n_estimators": 50,
    "random_state": 42
}
```

## Colonnes du modele sauvegarde

Le fichier `colonnes_modele_age_classe.pkl` contient les 26 colonnes attendues par le modele :

- 3 variables numeriques
- 23 colonnes issues de l'encodage des variables qualitatives

Ce fichier est indispensable car `client2.py` reconstitue une ligne utilisateur, applique `get_dummies`, puis realigne les colonnes avec celles du modele grace a :

```python
nouvel_arbre = nouvel_arbre.reindex(columns=model_columns, fill_value=0)
```

Cela garantit que les donnees d'entree ont exactement la meme structure que lors de l'entrainement.

## Utilisation

### 1. Entrainement du modele

Lancer :

```bash
python script.py
```

Ce script :
- affiche les performances des modeles testes
- selectionne le meilleur modele
- cree ou met a jour :
  - `modele_age_classe.pkl`
  - `colonnes_modele_age_classe.pkl`

### 2. Prediction avec le modele entraine

Lancer :

```bash
python client2.py
```

Le programme demande ensuite de saisir :
- le diametre du tronc
- la hauteur totale
- la hauteur du tronc
- le stade de developpement
- le type de port
- le type de pied

Puis il affiche la classe d'age predite.

## Exemple de fonctionnement

Le script `client2.py` :

1. verifie la presence des deux fichiers `.pkl`
2. charge le modele avec `joblib.load`
3. recupere les valeurs saisies par l'utilisateur
4. construit un `DataFrame` d'une seule ligne
5. encode les variables qualitatives
6. aligne les colonnes avec celles du modele
7. lance `model.predict(...)`
8. affiche la classe predite

## Dependances

Le projet utilise principalement :

- `pandas`
- `joblib`
- `scikit-learn`

Installation possible :

```bash
pip install pandas joblib scikit-learn
```

## Points d'attention

- Les valeurs saisies dans `client2.py` pour `fk_stadedev`, `fk_port` et `fk_pied` doivent correspondre aux modalites apprises pendant l'entrainement.
- Si une modalite n'a jamais ete vue, elle ne produira pas de colonne active apres realignement.
- Les fichiers `modele_age_classe.pkl` et `colonnes_modele_age_classe.pkl` doivent rester dans le meme dossier que `client2.py`.

## Resume

Ce projet met en place une chaine simple de machine learning :
- apprentissage d'un classifieur Random Forest sur des donnees d'arbres
- selection automatique du meilleur modele
- sauvegarde du modele entraine
- reutilisation du modele pour une prediction interactive
