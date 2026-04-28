const form = document.querySelector('.add-tree-form');
const feedback = document.getElementById('form-feedback');
const formSteps = Array.from(document.querySelectorAll('.form-step'));
const stepPills = Array.from(document.querySelectorAll('[data-step-pill]'));
const prevStepBtn = document.getElementById('btn-prev-step');
const nextStepBtn = document.getElementById('btn-next-step');
const submitBtn = document.getElementById('btn-submit-form');
const insertRandomBtn = document.getElementById('btn-insert-random');
const reviewBox = document.getElementById('form-review');
const speciesInput = document.getElementById('species');
const speciesList = document.getElementById('species-list');
const speciesSuggestions = document.getElementById('species-suggestions');

let currentStep = 1;
const MAX_STEP = 4;
let speciesCatalog = [];
const LONGITUDE_RANGE = { min: 1720320.1079, max: 1721757 };
const LATITUDE_RANGE = { min: 8294619, max: 8295873 };

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

function showFeedback(message, ok = true) {
  if (!feedback) return;
  feedback.textContent = message;
  feedback.style.color = ok ? 'var(--ok)' : 'var(--danger)';
}

function normalizeDisplayValue(value) {
  const normalized = String(value ?? '').trim();
  return normalized ? normalized : '-';
}

function getSpeciesValue() {
  return String(speciesInput?.value ?? '').trim();
}

function normalizeForCompare(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .trim();
}

function isExistingSpecies(value) {
  const needle = normalizeForCompare(value);
  return speciesCatalog.some((item) => normalizeForCompare(item) === needle);
}

function getSpeciesMatches(value) {
  const needle = normalizeForCompare(value);
  if (!needle) return [];

  const startsWithMatches = [];
  const containsMatches = [];

  speciesCatalog.forEach((item) => {
    const normalizedItem = normalizeForCompare(item);
    if (normalizedItem === needle) return;

    if (normalizedItem.startsWith(needle)) {
      startsWithMatches.push(item);
      return;
    }

    if (normalizedItem.includes(needle)) {
      containsMatches.push(item);
    }
  });

  return [...startsWithMatches, ...containsMatches].slice(0, 6);
}

function renderSpeciesSuggestions(value) {
  if (!speciesSuggestions) return;

  const rawValue = String(value ?? '').trim();
  if (!rawValue) {
    speciesSuggestions.innerHTML = '';
    speciesSuggestions.classList.remove('has-items');
    return;
  }

  const matches = getSpeciesMatches(rawValue);
  if (!matches.length) {
    speciesSuggestions.innerHTML = '<span class="species-suggestion-empty">Aucune proposition.</span>';
    speciesSuggestions.classList.add('has-items');
    return;
  }

  speciesSuggestions.innerHTML = '';
  speciesSuggestions.classList.add('has-items');
  const title = document.createElement('span');
  title.className = 'species-suggestion-title';
  title.textContent = 'Suggestions:';
  speciesSuggestions.appendChild(title);

  matches.forEach((match) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'species-suggestion-item';
    button.textContent = match;
    button.addEventListener('click', () => {
      if (!speciesInput) return;
      speciesInput.value = match;
      updateSpeciesHint(match);
      renderSpeciesSuggestions(match);
      speciesInput.focus();
    });
    speciesSuggestions.appendChild(button);
  });
}

function updateSpeciesHint(value) {
  if (!feedback || !value || currentStep !== 1) return;

  if (isExistingSpecies(value)) {
    showFeedback('Espece existante detectee: elle sera reutilisee.', true);
    return;
  }

  showFeedback('Aucune espece existante exacte: cette nouvelle espece sera creee.', true);
}

function updateReview() {
  if (!reviewBox) return;

  const remarkable = document.getElementById('remarkable')?.checked ? 'Oui' : 'Non';
  const speciesValue = getSpeciesValue();

  reviewBox.innerHTML = `
    <strong>Espece :</strong> ${normalizeDisplayValue(speciesValue)}<br>
    <strong>Type :</strong> ${normalizeDisplayValue(document.getElementById('type')?.value)}<br>
    <strong>Etat :</strong> ${normalizeDisplayValue(document.getElementById('etat')?.value)}<br>
    <strong>Stade :</strong> ${normalizeDisplayValue(document.getElementById('development_stage')?.value)}<br>
    <strong>Port :</strong> ${normalizeDisplayValue(document.getElementById('crown_type')?.value)}<br>
    <strong>Pied :</strong> ${normalizeDisplayValue(document.getElementById('base_type')?.value)}<br>
    <strong>Hauteur totale :</strong> ${normalizeDisplayValue(document.getElementById('total_height')?.value)} m<br>
    <strong>Hauteur tronc :</strong> ${normalizeDisplayValue(document.getElementById('trunk_height')?.value)} m<br>
    <strong>Diametre :</strong> ${normalizeDisplayValue(document.getElementById('diameter')?.value)} cm<br>
    <strong>Latitude :</strong> ${normalizeDisplayValue(document.getElementById('latitude')?.value)}<br>
    <strong>Longitude :</strong> ${normalizeDisplayValue(document.getElementById('longitude')?.value)}<br>
    <strong>Quartier :</strong> ${normalizeDisplayValue(document.getElementById('quartier')?.value)}<br>
    <strong>Secteur :</strong> ${normalizeDisplayValue(document.getElementById('secteur')?.value)}<br>
    <strong>Situation :</strong> ${normalizeDisplayValue(document.getElementById('situation')?.value)}<br>
    <strong>Age estime :</strong> ${normalizeDisplayValue(document.getElementById('age_estime')?.value || '0')} ans<br>
    <strong>Remarquable :</strong> ${remarkable}
  `;
}

function validateStep(step) {
  const section = formSteps.find((item) => Number(item.dataset.step) === step);
  if (!section) return true;

  if (step === 1) {
    const speciesValue = getSpeciesValue();
    if (!speciesValue) {
      showFeedback('Veuillez saisir une espece.', false);
      speciesInput?.focus();
      return false;
    }
  }

  const requiredFields = Array.from(section.querySelectorAll('input[required], select[required], textarea[required]'));
  for (const field of requiredFields) {
    if (!field.value || !String(field.value).trim()) {
      const label = section.querySelector(`label[for="${field.id}"]`)?.textContent || field.id || 'champ';
      showFeedback(`Veuillez renseigner: ${label}.`, false);
      field.focus();
      return false;
    }
  }

  if (step === 3) {
    const longitudeField = document.getElementById('longitude');
    const latitudeField = document.getElementById('latitude');
    const longitudeValue = Number(longitudeField?.value);
    const latitudeValue = Number(latitudeField?.value);

    if (Number.isNaN(longitudeValue) || longitudeValue < LONGITUDE_RANGE.min || longitudeValue > LONGITUDE_RANGE.max) {
      showFeedback(`Longitude invalide: valeur attendue entre ${LONGITUDE_RANGE.min} et ${LONGITUDE_RANGE.max}.`, false);
      longitudeField?.focus();
      return false;
    }

    if (Number.isNaN(latitudeValue) || latitudeValue < LATITUDE_RANGE.min || latitudeValue > LATITUDE_RANGE.max) {
      showFeedback(`Latitude invalide: valeur attendue entre ${LATITUDE_RANGE.min} et ${LATITUDE_RANGE.max}.`, false);
      latitudeField?.focus();
      return false;
    }
  }

  showFeedback('');
  return true;
}

function renderStep() {
  formSteps.forEach((section) => {
    section.classList.toggle('is-active', Number(section.dataset.step) === currentStep);
  });

  stepPills.forEach((pill) => {
    pill.classList.toggle('is-active', Number(pill.dataset.stepPill) === currentStep);
  });

  if (prevStepBtn) {
    prevStepBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
  }

  if (nextStepBtn) {
    nextStepBtn.style.display = currentStep < MAX_STEP ? 'inline-flex' : 'none';
  }

  if (submitBtn) {
    submitBtn.style.display = currentStep === MAX_STEP ? 'inline-flex' : 'none';
  }

  if (currentStep === MAX_STEP) {
    updateReview();
  }
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

function fillSpeciesDatalist(values) {
  if (!speciesList || !Array.isArray(values)) return;

  speciesCatalog = values
    .map((value) => String(value).trim())
    .filter((value) => value.length > 0);

  speciesList.innerHTML = '';
  speciesCatalog.forEach((value) => {
    const option = document.createElement('option');
    option.value = value;
    speciesList.appendChild(option);
  });
}

async function loadOptions() {
  try {
    const { response, payload } = await fetchApiJson('options.php');
    if (!response.ok) throw new Error('api options indisponible');
    if (!payload.success) throw new Error('api options indisponible');

    fillSpeciesDatalist(payload.data.especes);
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
  speciesInput?.addEventListener('input', () => {
    const value = getSpeciesValue();
    updateSpeciesHint(value);
    renderSpeciesSuggestions(value);
  });

  prevStepBtn?.addEventListener('click', () => {
    if (currentStep > 1) {
      currentStep -= 1;
      renderStep();
    }
  });

  nextStepBtn?.addEventListener('click', () => {
    if (!validateStep(currentStep)) {
      return;
    }

    if (currentStep < MAX_STEP) {
      currentStep += 1;
      renderStep();
    }
  });

  insertRandomBtn?.addEventListener('click', async () => {
    insertRandomBtn.disabled = true;
    showFeedback('Insertion de 5 arbres aleatoires en cours...', true);

    try {
      const { response, payload } = await fetchApiJson('insert_random.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ count: 5 }),
      });

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'erreur serveur');
      }

      showFeedback(payload.message || '5 arbres aleatoires inseres.', true);
      await loadOptions();
    } catch (error) {
      showFeedback(`echec insertion aleatoire: ${error.message}`, false);
    } finally {
      insertRandomBtn.disabled = false;
    }
  });

  renderStep();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!validateStep(currentStep)) {
      return;
    }

    const payload = {
      espece: getSpeciesValue(),
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
      age_estime: Number(document.getElementById('age_estime')?.value) || 0,
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
      currentStep = 1;
      renderStep();
    } catch (error) {
      showFeedback(`echec ajout: ${error.message}`, false);
    }
  });

  loadOptions();
}
