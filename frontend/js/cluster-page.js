/**
 * cluster-page.js
 * Charge les prédictions de clusters et affiche une carte Plotly
 */

function isValidWgs84(lat, lon) {
    return Number.isFinite(lat) && Number.isFinite(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180;
}

// Projection RGF93 / CC49 vers WGS84
function lambertCc49ToWgs84(x, y) {
    if (!Number.isFinite(x) || !Number.isFinite(y)) {
        return null;
    }

    const a = 6378137;
    const e = Math.sqrt(0.00669438002290);
    const toRad = (degrees) => (degrees * Math.PI) / 180;
    const toDeg = (radians) => (radians * 180) / Math.PI;
    const latitudeOfOrigin = toRad(49);
    const centralMeridian = toRad(3);
    const firstStandardParallel = toRad(48.25);
    const secondStandardParallel = toRad(49.75);
    const falseEasting = 1700000;
    const falseNorthing = 8200000;

    const m = (phi) => Math.cos(phi) / Math.sqrt(1 - e * e * Math.sin(phi) ** 2);
    const t = (phi) => {
        const sinPhi = Math.sin(phi);
        return Math.tan(Math.PI / 4 - phi / 2) / (((1 - e * sinPhi) / (1 + e * sinPhi)) ** (e / 2));
    };

    const n =
        (Math.log(m(firstStandardParallel)) - Math.log(m(secondStandardParallel))) /
        (Math.log(t(firstStandardParallel)) - Math.log(t(secondStandardParallel)));
    const f = m(firstStandardParallel) / (n * t(firstStandardParallel) ** n);
    const rho0 = a * f * t(latitudeOfOrigin) ** n;
    const dx = x - falseEasting;
    const dy = rho0 - (y - falseNorthing);
    const rho = Math.sign(n) * Math.sqrt(dx * dx + dy * dy);
    const theta = Math.atan2(dx, dy);
    const projectedT = (rho / (a * f)) ** (1 / n);

    let lat = Math.PI / 2 - 2 * Math.atan(projectedT);
    for (let i = 0; i < 8; i += 1) {
        const sinLat = Math.sin(lat);
        lat =
            Math.PI / 2 -
            2 * Math.atan(projectedT * (((1 - e * sinLat) / (1 + e * sinLat)) ** (e / 2)));
    }

    const lon = centralMeridian + theta / n;
    const point = { lat: toDeg(lat), lon: toDeg(lon) };
    return isValidWgs84(point.lat, point.lon) ? point : null;
}

function normalizeGeoPoint(arbre) {
    const rawLat = Number(arbre?.latitude);
    const rawLon = Number(arbre?.longitude);

    if (isValidWgs84(rawLat, rawLon)) {
        return { lat: rawLat, lon: rawLon };
    }

    if (isValidWgs84(rawLon, rawLat)) {
        return { lat: rawLon, lon: rawLat };
    }

    // Donnees de Saint-Quentin en CC49 (x=longitude, y=latitude)
    const converted = lambertCc49ToWgs84(rawLon, rawLat);
    if (converted) {
        return converted;
    }

    // Fallback si x/y ont ete inverses dans certaines lignes
    return lambertCc49ToWgs84(rawLat, rawLon);
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
function getApiCandidates(endpoint) {
    const normalizedEndpoint = String(endpoint || '').replace(/^\/+/, '');

    if (window.location.protocol === 'file:') {
        return [
            `http://localhost:8000/api/${normalizedEndpoint}`,
            `http://127.0.0.1:8000/api/${normalizedEndpoint}`,
            `http://localhost:8080/api/${normalizedEndpoint}`,
            `http://127.0.0.1:8080/api/${normalizedEndpoint}`,
        ];
    }

    return [
        `../backend/api/${normalizedEndpoint}`,
        `../api/${normalizedEndpoint}`,
        `/backend/api/${normalizedEndpoint}`,
        `/api/${normalizedEndpoint}`,
        `${window.location.origin}/api/${normalizedEndpoint}`,
    ];
}

async function fetchApiJson(endpoint, init = {}) {
    let lastError = null;

    for (const url of getApiCandidates(endpoint)) {
        try {
            const response = await fetch(url, init);
            if (response.status === 404) {
                continue;
            }

            const payload = await response.json();
            return { response, payload };
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError || new Error('API introuvable');
}

function safeNumber(value, fallback = 0) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
}

async function loadClusterPredictions() {
    try {
        const { response, payload: result } = await fetchApiJson('predict_clusters.php');
        if (!response.ok) throw new Error('API error: ' + response.status);

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
    const allPoints = [];
    let validPoints = 0;
    
    arbres.forEach(arbre => {
        const cluster = predictCluster(arbre.hauteur_totale, arbre.diametre_tronc);
        if (!clusters[cluster]) {
            clusters[cluster] = { lat: [], lon: [], text: [], ids: [] };
        }
        
        const wgs84 = normalizeGeoPoint(arbre);
        if (!wgs84) {
            return;
        }
        validPoints += 1;
        allPoints.push(wgs84);
        
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
            size: 16,
            color: clusterColors[cluster],
            opacity: 1,
            symbol: 'circle',
            line: {
                width: 2,
                color: '#ffffff'
            }
        },
        name: clusterNames[cluster],
        hovertemplate: '%{text}<extra></extra>',
        customdata: data.ids
    }));

    const center = allPoints.length
        ? {
            lat: allPoints.reduce((sum, p) => sum + p.lat, 0) / allPoints.length,
            lon: allPoints.reduce((sum, p) => sum + p.lon, 0) / allPoints.length,
        }
        : { lat: 49.8489, lon: 3.2870 };

    const layout = {
        title: 'Clusters K-Means des Arbres',
        mapbox: {
            style: 'open-street-map',
            center,
            zoom: 13
        },
        hovermode: 'closest',
        margin: { r: 0, t: 30, l: 0, b: 0 },
        legend: { x: 0.02, y: 0.98 }
    };

    Plotly.newPlot('cluster-map-plot', traces, layout, {
        responsive: true,
        displayModeBar: true,
        scrollZoom: true,
    });

    const loading = document.getElementById('loading');
    if (loading) {
        if (!validPoints) {
            loading.innerHTML = '<p style="color: #b42318;">Aucun point geographique valide a afficher.</p>';
        } else {
            loading.innerHTML = `<p style="color: #1f7a3d;">${validPoints} points affiches sur la carte.</p>`;
        }
    }
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
                <td>${safeNumber(arbre.latitude).toFixed(2)}</td>
                <td>${safeNumber(arbre.longitude).toFixed(2)}</td>
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
