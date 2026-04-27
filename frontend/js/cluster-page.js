/**
 * cluster-page.js
 * Charge les prédictions de clusters et affiche une carte Plotly
 */

// Projection Lambert-93 (EPSG:2154) vers WGS84 (EPSG:4326)
function lambertCc49ToWgs84(x, y) {
    // Constantes pour la conversion
    const xs = 700000;
    const ys = 12655612;
    const lambda = 3;
    const phi0 = 52;
    const c = 11745793.39;
    const n = 0.7256077650532670;
    const phi1 = 49;
    const phi2 = 50.79590927304922;

    const degreeToRad = Math.PI / 180;
    const radToDegree = 180 / Math.PI;

    const rho = Math.sqrt(Math.pow(x - xs, 2) + Math.pow(y - ys, 2));
    const gamma = Math.atan2(x - xs, ys - y);
    const lambda_deg = (gamma / degreeToRad / n) + lambda;

    let phi = 2 * Math.atan(Math.pow(c / rho, 1 / n) * Math.pow(Math.E, 0)) - 90;
    for (let i = 0; i < 8; i++) {
        const sinPhi = Math.sin(phi * degreeToRad);
        const numerator = Math.pow(1 - 0.0072292 * sinPhi * sinPhi, 0.5) * Math.tan(phi * degreeToRad);
        const denominator = 1 - 0.0072292 * sinPhi * sinPhi;
        phi = 2 * Math.atan(Math.pow(c / rho, 1 / n) * Math.pow(Math.pow(numerator / denominator, n), 1)) - 90;
    }

    return { lat: phi, lon: lambda_deg };
}

// Déterminer le cluster label basé sur hauteur_totale et diametre_tronc
function predictCluster(hauteur, diametre) {
    const area = hauteur * diametre;
    if (area < 1000) return 'PETIT';
    if (area < 3000) return 'MOYEN';
    return 'GRAND';
}

// Couleurs pour les clusters
const clusterColors = {
    PETIT: '#2f8f4e',   // vert
    MOYEN: '#ff9800',   // orange
    GRAND: '#d32f2f'    // rouge
};

const clusterNames = {
    PETIT: 'Petit (hauteur × diamètre < 1000)',
    MOYEN: 'Moyen (1000 ≤ hauteur × diamètre < 3000)',
    GRAND: 'Grand (hauteur × diamètre ≥ 3000)'
};

// Récupérer les prédictions de clusters
async function loadClusterPredictions() {
    try {
        const response = await fetch('/api/predict_clusters.php');
        if (!response.ok) throw new Error('API error: ' + response.status);
        
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Erreur de prédiction');
        
        return result.data || [];
    } catch (error) {
        console.error('Erreur chargement clusters:', error);
        document.getElementById('loading').innerHTML = `<p style="color: red;">Erreur: ${error.message}</p>`;
        return [];
    }
}

// Afficher les statistiques des clusters
function displayClusterStats(arbres) {
    const statsHtml = arbres.reduce((clusters, arbre) => {
        const cluster = predictCluster(arbre.hauteur_totale, arbre.diametre_tronc);
        if (!clusters[cluster]) clusters[cluster] = 0;
        clusters[cluster]++;
        return clusters;
    }, {});

    const statsDiv = document.getElementById('cluster-stats');
    statsDiv.innerHTML = Object.entries(statsHtml).map(([cluster, count]) => `
        <div class="cluster-card cluster-${cluster.toLowerCase()}">
            <div style="font-size: 24px; margin-bottom: 4px;">${count}</div>
            <div style="font-size: 12px;">${cluster}</div>
        </div>
    `).join('');
}

// Afficher la carte Plotly avec clusters
function displayClusterMap(arbres) {
    const clusters = {};
    
    arbres.forEach(arbre => {
        const cluster = predictCluster(arbre.hauteur_totale, arbre.diametre_tronc);
        if (!clusters[cluster]) {
            clusters[cluster] = { lat: [], lon: [], text: [], ids: [] };
        }
        
        // Convertir de CC49 à WGS84
        const wgs84 = lambertCc49ToWgs84(arbre.longitude, arbre.latitude);
        
        clusters[cluster].lat.push(wgs84.lat);
        clusters[cluster].lon.push(wgs84.lon);
        clusters[cluster].text.push(
            `<b>${arbre.espece}</b><br>` +
            `Hauteur: ${arbre.hauteur_totale}m<br>` +
            `Diamètre: ${arbre.diametre_tronc}cm<br>` +
            `${arbre.quartier}`
        );
        clusters[cluster].ids.push(arbre.id_arbre);
    });

    const traces = Object.entries(clusters).map(([cluster, data]) => ({
        type: 'scattermapbox',
        lat: data.lat,
        lon: data.lon,
        text: data.text,
        mode: 'markers',
        marker: {
            size: 10,
            color: clusterColors[cluster],
            opacity: 0.8,
            sizemode: 'diameter'
        },
        name: clusterNames[cluster],
        hovertemplate: '%{text}<extra></extra>',
        customdata: data.ids
    }));

    const layout = {
        title: 'Clusters K-Means des Arbres',
        mapbox: {
            style: 'open-street-map',
            center: { lat: 49.86, lon: 3.30 },
            zoom: 12
        },
        hovermode: 'closest',
        margin: { r: 0, t: 30, l: 0, b: 0 },
        legend: { x: 0.02, y: 0.98 }
    };

    Plotly.newPlot('cluster-map-plot', traces, layout, { responsive: true });
}

// Afficher le tableau des clusters
function displayClusterTable(arbres) {
    const tbody = document.getElementById('cluster-table-body');
    tbody.innerHTML = arbres.map(arbre => {
        const cluster = predictCluster(arbre.hauteur_totale, arbre.diametre_tronc);
        return `
            <tr>
                <td>${arbre.id_arbre}</td>
                <td>${arbre.espece}</td>
                <td>${arbre.quartier}</td>
                <td>${arbre.hauteur_totale}</td>
                <td>${arbre.diametre_tronc}</td>
                <td><span style="background: ${clusterColors[cluster]}; color: white; padding: 2px 6px; border-radius: 3px;">${cluster}</span></td>
                <td>${arbre.latitude.toFixed(2)}</td>
                <td>${arbre.longitude.toFixed(2)}</td>
            </tr>
        `;
    }).join('');
}

// Main
(async () => {
    const arbres = await loadClusterPredictions();
    if (arbres.length === 0) return;
    
    displayClusterStats(arbres);
    displayClusterMap(arbres);
    displayClusterTable(arbres);
})();
