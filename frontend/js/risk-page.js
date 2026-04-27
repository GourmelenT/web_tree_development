/**
 * risk-page.js
 * Charge les arbres et affiche les prédictions d'âge/risque
 */

let allTrees = [];

// Récupérer les arbres
async function loadTrees() {
    try {
        const response = await fetch('/api/arbres.php');
        if (!response.ok) throw new Error('API error: ' + response.status);
        
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Erreur chargement arbres');
        
        allTrees = result.data || [];
        populateTreeSelect();
    } catch (error) {
        console.error('Erreur chargement arbres:', error);
        document.getElementById('loading').innerHTML = `<p style="color: red;">Erreur: ${error.message}</p>`;
    }
}

// Remplir le select avec les arbres
function populateTreeSelect() {
    const select = document.getElementById('tree-select');
    select.innerHTML = '<option value="">-- Sélectionnez un arbre --</option>' +
        allTrees.map(tree => `
            <option value="${tree.id_arbre}">
                #${tree.id_arbre} - ${tree.espece} (${tree.quartier})
            </option>
        `).join('');
}

// Prédire pour l'arbre sélectionné
async function predictTree() {
    const select = document.getElementById('tree-select');
    const id_arbre = select.value;
    
    if (!id_arbre) {
        alert('Sélectionnez un arbre');
        return;
    }

    const loading = document.getElementById('loading');
    const results = document.getElementById('results');
    
    loading.innerHTML = '<p>Chargement des prédictions...</p>';
    results.style.display = 'none';

    try {
        // Récupérer l'arbre
        const tree = allTrees.find(t => t.id_arbre == id_arbre);
        if (!tree) throw new Error('Arbre non trouvé');

        // Appeler predict_age.php
        const ageResponse = await fetch('/api/predict_age.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_arbre })
        });
        const ageResult = await ageResponse.json();

        // Appeler predict_risque.php
        const risqueResponse = await fetch('/api/predict_risque.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_arbre })
        });
        const risqueResult = await risqueResponse.json();

        // Afficher les résultats
        displayResults(tree, ageResult, risqueResult);
        loading.innerHTML = '';
        results.style.display = 'grid';

    } catch (error) {
        console.error('Erreur prédiction:', error);
        loading.innerHTML = `<p style="color: red;">Erreur: ${error.message}</p>`;
    }
}

// Afficher les résultats
function displayResults(tree, ageResult, risqueResult) {
    // Âge
    const agePrediction = ageResult.prediction_age || 'Inconnu';
    document.getElementById('age-result').textContent = agePrediction;
    document.getElementById('age-confidence').textContent = ageResult.output ? 'Basé sur RandomForestClassifier' : 'Pas de confiance calculée';

    // Risque
    if (risqueResult.prediction_risque) {
        const risk = risqueResult.prediction_risque;
        const riskScore = (risk.risk_score * 100).toFixed(1);
        
        let riskClass = 'risk-low';
        if (riskScore > 60) riskClass = 'risk-high';
        else if (riskScore > 30) riskClass = 'risk-medium';

        document.getElementById('risk-result').innerHTML = 
            `<span class="${riskClass}">${riskScore}%</span>`;
        document.getElementById('risk-confidence').textContent = 
            `État prédit: ${risk.etat || 'inconnu'} | À risque: ${risk.a_risque || 'inconnu'}`;
    }

    // Infos arbre
    document.getElementById('tree-espece').textContent = tree.espece;
    document.getElementById('tree-hauteur').textContent = tree.hauteur_totale;
    document.getElementById('tree-diametre').textContent = tree.diametre_tronc;
    document.getElementById('tree-etat').textContent = tree.etat || 'Inconnu';
    document.getElementById('tree-quartier').textContent = tree.quartier || 'Inconnu';
}

// Charger les arbres au démarrage
loadTrees();
