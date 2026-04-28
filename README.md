# Setup rapide (Windows + PowerShell)

## 1) Configurer la connexion MySQL

Compléter vos informations de connexion MySQL dans le fichier `.env` dans le dossier `backend` :

```powershell
DB_HOST= lien_vers_mysql
DB_PORT=3306
DB_NAME= votre_nom_de_base_de_donnees
DB_USER= votre_utilisateur_mysql
DB_PASSWORD= votre_mot_de_passe_mysql
```

## 2) Creer la base de donnees

Commande MySQL (adapter utilisateur/mot de passe):

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS grp1tr3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

ou créer la base de données via l'outil graphique phpMyAdmin.

## 3) Creer les tables

Depuis la racine du projet:

```powershell
php .\backend\create_database.php
```

## 4) Inserer les donnees

Depuis la racine du projet:
```powershell
php .\backend\insert_data.php
```

## 5) Lancer le serveur de développement PHP

Deux options:

Depuis la racine du projet:

```powershell
python ./server.py
```

ou 
```powershell
php -S localhost:8000
```

Si tout est fonctionnel, le formulaire s'ouvre dans le navigateur sur le lien `http://localhost:8000/index.html`.
