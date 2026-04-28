# Setup rapide (Windows + PowerShell)

## 1) Lancer l'application

### Terminal (tunnel SSH)

```powershell
ssh -L 8000:localhost:8000 t_macari@t-macari.projets.isen-ouest.info
```
Le mot de passe est celui fourni par le professeur lors de la première connexion.

puis

```
python server.py
```



Cette commande ouvre le tunnel et fait la liaison client/serveur sur le port `8000`.

Ensuite, ouvrir l'application dans le navigateur: `http://localhost:8000/index.html`. y9RLU6iqY5e2F8zS
