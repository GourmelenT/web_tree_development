const tableBody = document.getElementById('arbres-body');
const tableCount = document.getElementById('arbres-count');
const mapStatus = document.getElementById('map-status');
const searchInput = document.getElementById('filter-search');
const etatSelect = document.getElementById('filter-etat');
const refreshBtn = document.getElementById('btn-refresh');

let allRows = [];
const MAX_MAP_POINTS = 8000;

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

  throw lastError || new Error('api introuvable');
}

function safeNumber(value, fallback = 0) {
  const n = Number(value);
  return Number.isFinite(n) ? n : fallback;
}

function isValidWgs84(lat, lon) {
  return Number.isFinite(lat) && Number.isFinite(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180;
}

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

  if (!isValidWgs84(point.lat, point.lon)) {
    return null;
  }

  return point;
}

function normalizeGeoPoint(row) {
  const rawLat = Number(row?.latitude);
  const rawLon = Number(row?.longitude);

  if (isValidWgs84(rawLat, rawLon)) {
    return { lat: rawLat, lon: rawLon };
  }

  if (isValidWgs84(rawLon, rawLat)) {
    return { lat: rawLon, lon: rawLat };
  }

  // Saint-Quentin open data uses RGF93 / CC49 projected meters: longitude=x and latitude=y.
  const converted = lambertCc49ToWgs84(rawLon, rawLat);
  if (converted) {
    return converted;
  }

  // Fallback si des lignes ont ete enregistrees avec x/y inverses.
  const convertedSwapped = lambertCc49ToWgs84(rawLat, rawLon);
  if (convertedSwapped) {
    return convertedSwapped;
  }

  return null;
}

function downsampleRows(rows, maxPoints) {
  if (!Array.isArray(rows) || rows.length <= maxPoints) {
    return rows;
  }

  const step = Math.ceil(rows.length / maxPoints);
  const sampled = [];
  for (let i = 0; i < rows.length; i += step) {
    sampled.push(rows[i]);
  }

  return sampled;
}

function renderTableRows(rows) {
  if (!tableBody) return;
  tableBody.innerHTML = '';

  rows.forEach((arbre) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${arbre.id_arbre}</td>
      <td>${arbre.espece}</td>
      <td>${arbre.quartier}</td>
      <td>${arbre.type}</td>
      <td>${safeNumber(arbre.hauteur_totale).toFixed(1)}</td>
      <td>${safeNumber(arbre.hauteur_tronc).toFixed(1)}</td>
      <td>${safeNumber(arbre.diametre_tronc).toFixed(1)}</td>
      <td>${safeNumber(arbre.remarquable) ? 'oui' : 'non'}</td>
      <td>${safeNumber(arbre.latitude).toFixed(5)}</td>
      <td>${safeNumber(arbre.longitude).toFixed(5)}</td>
      <td>${arbre.etat}</td>
      <td>${arbre.stade_developpement}</td>
      <td>${arbre.port}</td>
      <td>${arbre.pied}</td>
      <td>${safeNumber(arbre.age_estime)}</td>
    `;
    tableBody.appendChild(tr);
  });
}

function renderPlotlyMap(rows) {
  const container = document.getElementById('arbres-map-plot');
  if (!container) return;

  if (typeof Plotly === 'undefined') {
    if (mapStatus) {
      mapStatus.textContent = 'erreur: librairie carte indisponible (Plotly non charge)';
    }
    return;
  }

  const normalizedRows = rows
    .map((r) => {
      const point = normalizeGeoPoint(r);
      return point ? { ...r, _lat: point.lat, _lon: point.lon } : null;
    })
    .filter(Boolean);

  const sampledRows = downsampleRows(normalizedRows, MAX_MAP_POINTS);

  const center = sampledRows.length
    ? {
        lat: sampledRows.reduce((sum, r) => sum + Number(r._lat), 0) / sampledRows.length,
        lon: sampledRows.reduce((sum, r) => sum + Number(r._lon), 0) / sampledRows.length,
      }
    : { lat: 49.8489, lon: 3.2870 };

  const trace = {
    type: 'scattermapbox',
    mode: 'markers',
    lon: sampledRows.map((r) => Number(r._lon)),
    lat: sampledRows.map((r) => Number(r._lat)),
    text: sampledRows.map(
      (r) => `${r.espece}<br>quartier: ${r.quartier}<br>etat: ${r.etat}<br>h totale: ${safeNumber(r.hauteur_totale).toFixed(1)} m`
    ),
    hovertemplate: '%{text}<extra></extra>',
    marker: {
      size: 8,
      color: sampledRows.map((r) => (safeNumber(r.remarquable) ? '#2f8f4e' : '#1e6f3a')),
      opacity: 0.85,
    },
  };

  const layout = {
    margin: { l: 0, r: 0, t: 0, b: 0 },
    mapbox: {
      style: 'open-street-map',
      center,
      zoom: 11,
    },
  };

  try {
    Plotly.react(container, [trace], layout, { responsive: true, displayModeBar: false });

    if (mapStatus) {
      if (!normalizedRows.length) {
        mapStatus.textContent = 'carte chargee (aucune coordonnee exploitable)';
      } else if (normalizedRows.length !== rows.length) {
        mapStatus.textContent = `carte chargee (${normalizedRows.length}/${rows.length} coordonnees valides)`;
      } else if (sampledRows.length !== normalizedRows.length) {
        mapStatus.textContent = `carte chargee (${sampledRows.length} points affiches sur ${normalizedRows.length})`;
      }
    }
  } catch (error) {
    if (mapStatus) {
      mapStatus.textContent = `erreur rendu carte: ${error.message}`;
    }
  }
}

function applyFilters() {
  const search = (searchInput?.value || '').trim().toLowerCase();
  const etat = etatSelect?.value || '';

  const filtered = allRows.filter((row) => {
    const bySearch = !search || String(row.espece).toLowerCase().includes(search);
    const byEtat = !etat || row.etat === etat;
    return bySearch && byEtat;
  });

  renderTableRows(filtered);
  renderPlotlyMap(filtered);
  if (tableCount) tableCount.textContent = `${filtered.length} arbres affiches / ${allRows.length}`;
}

function fillEtatFilter(rows) {
  if (!etatSelect) return;
  const etats = Array.from(new Set(rows.map((r) => String(r.etat || '').trim()).filter(Boolean))).sort();
  etatSelect.innerHTML = '<option value="">tous les etats</option>';
  etats.forEach((etat) => {
    const option = document.createElement('option');
    option.value = etat;
    option.textContent = etat;
    etatSelect.appendChild(option);
  });
}

async function loadArbres() {
  if (mapStatus) mapStatus.textContent = 'chargement des donnees...';

  try {
    const { response, payload } = await fetchApiJson('arbres.php');

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'erreur api arbres');
    }

    allRows = Array.isArray(payload.data) ? payload.data : [];
    fillEtatFilter(allRows);
    applyFilters();
  } catch (error) {
    if (mapStatus) mapStatus.textContent = `erreur: ${error.message}`;
  }
}

searchInput?.addEventListener('input', applyFilters);
etatSelect?.addEventListener('change', applyFilters);
refreshBtn?.addEventListener('click', loadArbres);

loadArbres();
