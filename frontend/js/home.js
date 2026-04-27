const totalEl = document.getElementById('kpi-total');
const remarkableEl = document.getElementById('kpi-remarkables');
const statesEl = document.getElementById('kpi-states');
const heightEl = document.getElementById('kpi-height');

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

function safeNumber(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

async function loadHomeStats() {
  try {
    const { response, payload } = await fetchApiJson('arbres.php');
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'erreur api');
    }

    const rows = Array.isArray(payload.data) ? payload.data : [];
    const remarkable = rows.filter((r) => safeNumber(r.remarquable) === 1).length;
    const states = new Set(rows.map((r) => String(r.etat || '').trim()).filter(Boolean));

    const avgHeight = rows.length
      ? rows.reduce((sum, r) => sum + safeNumber(r.hauteur_totale), 0) / rows.length
      : 0;

    if (totalEl) totalEl.textContent = String(rows.length);
    if (remarkableEl) remarkableEl.textContent = String(remarkable);
    if (statesEl) statesEl.textContent = String(states.size);
    if (heightEl) heightEl.textContent = avgHeight.toFixed(1);
  } catch (error) {
    if (totalEl) totalEl.textContent = 'n/a';
    if (remarkableEl) remarkableEl.textContent = 'n/a';
    if (statesEl) statesEl.textContent = 'n/a';
    if (heightEl) heightEl.textContent = 'n/a';
  }
}

loadHomeStats();
