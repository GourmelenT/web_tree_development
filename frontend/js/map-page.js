const tableBody = document.getElementById('arbres-body');
const tableCount = document.getElementById('arbres-count');
const mapStatus = document.getElementById('map-status');
const searchInput = document.getElementById('filter-search');
const etatSelect = document.getElementById('filter-etat');
const refreshBtn = document.getElementById('btn-refresh');

let allRows = [];

function getApiCandidates(endpoint) {
  return [
    `../backend/api/${endpoint}`,
    `../api/${endpoint}`,
    `/backend/api/${endpoint}`,
    `/api/${endpoint}`,
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
  if (!container || typeof Plotly === 'undefined') return;

  const validRows = rows.filter((r) => Number.isFinite(Number(r.latitude)) && Number.isFinite(Number(r.longitude)));
  const center = validRows.length
    ? {
        lat: validRows.reduce((sum, r) => sum + Number(r.latitude), 0) / validRows.length,
        lon: validRows.reduce((sum, r) => sum + Number(r.longitude), 0) / validRows.length,
      }
    : { lat: 49.8489, lon: 3.2870 };

  const trace = {
    type: 'scattermapbox',
    mode: 'markers',
    lon: validRows.map((r) => Number(r.longitude)),
    lat: validRows.map((r) => Number(r.latitude)),
    text: validRows.map(
      (r) => `${r.espece}<br>quartier: ${r.quartier}<br>etat: ${r.etat}<br>h totale: ${safeNumber(r.hauteur_totale).toFixed(1)} m`
    ),
    hovertemplate: '%{text}<extra></extra>',
    marker: {
      size: 8,
      color: validRows.map((r) => (safeNumber(r.remarquable) ? '#2f8f4e' : '#1e6f3a')),
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

  Plotly.newPlot(container, [trace], layout, { responsive: true, displayModeBar: false });
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

    if (mapStatus) mapStatus.textContent = 'donnees chargees';
  } catch (error) {
    if (mapStatus) mapStatus.textContent = `erreur: ${error.message}`;
  }
}

searchInput?.addEventListener('input', applyFilters);
etatSelect?.addEventListener('change', applyFilters);
refreshBtn?.addEventListener('click', loadArbres);

loadArbres();
