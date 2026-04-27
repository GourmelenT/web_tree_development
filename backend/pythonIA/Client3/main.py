#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Lancement automatique de la prédiction batch sur le CSV
Le script génère directement les fichiers de sortie sans saisie utilisateur.
"""

import os
import sys
def main():
    """Lance directement la prédiction batch sur le CSV principal."""

    from predict import load_model, predict_csv_batch

    print("Lancement automatique de la détection des arbres à risque...")

    model = load_model()
    if model is None:
        return

    input_csv = "Data_Arbre_Input.csv"
    output_csv = "predictions_arbres.csv"
    threshold = 0.40

    predict_csv_batch(
        model=model,
        input_csv=input_csv,
        output_csv=output_csv,
        risk_threshold=threshold,
    )

    print("\n Terminé. Les fichiers de sortie ont été générés automatiquement.")

if __name__ == "__main__":
    main()
