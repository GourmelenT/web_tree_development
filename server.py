#!/usr/bin/env python3
import subprocess
import sys
import time
import webbrowser
from pathlib import Path


def main() -> int:
    project_root = Path(__file__).resolve().parent
    url = "http://localhost:8000/index.html"

    try:
        process = subprocess.Popen(
            ["php", "-S", "localhost:8000", "index.php"],
            cwd=project_root,
        )
    except FileNotFoundError:
        print("Erreur: PHP est introuvable dans le PATH.")
        return 1

    # Laisse le serveur demarrer avant d'ouvrir le navigateur.
    time.sleep(1.0)

    if process.poll() is not None:
        print("Erreur: le serveur PHP n'a pas pu demarrer (port deja utilise ?).")
        return 1

    webbrowser.open(url)
    print("Serveur lance sur http://localhost:8000")
    print(f"Page ouverte: {url}")
    print("Appuyez sur Ctrl+C pour arreter le serveur.")

    try:
        process.wait()
    except KeyboardInterrupt:
        process.terminate()
        process.wait(timeout=5)
        print("Serveur arrete.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
