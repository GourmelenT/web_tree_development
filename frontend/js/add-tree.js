const form = document.querySelector('.add-tree-form');
const feedback = document.getElementById('form-feedback');

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

function showFeedback(message, ok = true) {
  if (!feedback) return;
  feedback.textContent = message;
  feedback.style.color = ok ? 'var(--ok)' : 'var(--danger)';
}

function fillSelect(selectId, values) {
  const select = document.getElementById(selectId);
  if (!select || !Array.isArray(values)) return;

  select.innerHTML = '<option value="">selectionner...</option>';
  values.forEach((value) => {
    const label = String(value).trim();
    if (!label) return;
    const option = document.createElement('option');
    option.value = label;
    option.textContent = label;
    select.appendChild(option);
  });
}

async function loadOptions() {
  try {
    const { response, payload } = await fetchApiJson('options.php');
    if (!response.ok) throw new Error('api options indisponible');
    if (!payload.success) throw new Error('api options indisponible');

    fillSelect('species', payload.data.especes);
    fillSelect('type', payload.data.types);
    fillSelect('etat', payload.data.etats);
    fillSelect('development_stage', payload.data.stades_developpement);
    fillSelect('crown_type', payload.data.ports);
    fillSelect('base_type', payload.data.pieds);
    fillSelect('situation', payload.data.situations);
  } catch (error) {
    showFeedback(`echec chargement options: ${error.message}`, false);
  }
}

if (form) {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const payload = {
      espece: document.getElementById('species')?.value ?? '',
      type: document.getElementById('type')?.value ?? '',
      hauteur_totale: document.getElementById('total_height')?.value ?? '',
      hauteur_tronc: document.getElementById('trunk_height')?.value ?? '',
      diametre_tronc: document.getElementById('diameter')?.value ?? '',
      remarquable: document.getElementById('remarkable')?.checked ? 1 : 0,
      latitude: document.getElementById('latitude')?.value ?? '',
      longitude: document.getElementById('longitude')?.value ?? '',
      etat: document.getElementById('etat')?.value ?? '',
      stade_developpement: document.getElementById('development_stage')?.value ?? '',
      port: document.getElementById('crown_type')?.value ?? '',
      pied: document.getElementById('base_type')?.value ?? '',
      quartier: document.getElementById('quartier')?.value ?? '',
      secteur: document.getElementById('secteur')?.value ?? '',
      situation: document.getElementById('situation')?.value ?? 'Alignement',
    };

    try {
      const { response, payload: result } = await fetchApiJson('arbres.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'erreur serveur');
      }

      showFeedback(`arbre ajoute avec succes (id ${result.id_arbre})`, true);
      form.reset();
    } catch (error) {
      showFeedback(`echec ajout: ${error.message}`, false);
    }
  });

  loadOptions();
}
