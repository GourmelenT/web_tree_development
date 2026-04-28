const totalEl = document.getElementById('kpi-total');
const remarkableEl = document.getElementById('kpi-remarkables');
const statesEl = document.getElementById('kpi-states');
const heightEl = document.getElementById('kpi-height');

async function loadHomeStats() {
  try {
    const payload = await api('arbres.php');
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
