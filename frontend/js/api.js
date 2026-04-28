function apiUrls(endpoint) {
  const path = String(endpoint || '').replace(/^\/+/, '');

  if (window.location.protocol === 'file:') {
    return [
      `http://localhost:8000/api/${path}`,
      `http://127.0.0.1:8000/api/${path}`,
      `http://localhost:8080/api/${path}`,
      `http://127.0.0.1:8080/api/${path}`,
    ];
  }

  return [
    `../backend/api/${path}`,
    `../api/${path}`,
    `/backend/api/${path}`,
    `/api/${path}`,
    `${window.location.origin}/api/${path}`,
  ];
}

async function fetchApiJson(endpoint, init = {}) {
  let lastError = null;

  for (const url of apiUrls(endpoint)) {
    try {
      const response = await fetch(url, init);
      if (response.status === 404) continue;

      return { response, payload: await response.json() };
    } catch (error) {
      lastError = error;
    }
  }

  throw lastError || new Error('api introuvable');
}

async function api(endpoint, init = {}) {
  const { response, payload } = await fetchApiJson(endpoint, init);
  if (!response.ok || !payload.success) {
    throw new Error(payload.message || `erreur api ${response.status}`);
  }

  return payload;
}

function safeNumber(value, fallback = 0) {
  const n = Number(value);
  return Number.isFinite(n) ? n : fallback;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
