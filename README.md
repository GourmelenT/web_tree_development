# Setup rapide (Windows + PowerShell)

## 1) Lancer l'application (2 terminaux obligatoires)

### Terminal 1 (a la racine du projet)

```powershell
python server.py
```

### Terminal 2 (tunnel SSH)

```powershell
ssh -L 8000:localhost:8000 t_macari@t-macari.projets.isen-ouest.info
```

Le mot de passe est celui fourni par le professeur.

Cette commande ouvre le tunnel et fait la liaison client/serveur sur le port `8000`.

Ensuite, ouvrir l'application dans le navigateur: `http://localhost:8000/index.html`.
