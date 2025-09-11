// autoErp/public/assets/js/public/fornecedoresList.js
(() => {
  const inputQ   = document.getElementById('search-q');
  const box      = document.getElementById('q-suggest');
  const selAtivo = document.getElementById('select-ativo');

  let abortCtrl = null;
  const cfgEl = document.getElementById('fornecedores-config');
  const CSRF  = cfgEl ? cfgEl.dataset.csrf : '';

  function buildURL(params) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => {
      if (v === '' || v == null) url.searchParams.delete(k);
      else url.searchParams.set(k, v);
    });
    return url.toString();
  }

  function navigateWith(params) {
    window.location.href = buildURL(params);
  }

  function hideSuggest() {
    if (box) box.style.display = 'none';
  }

  function showSuggest(items = []) {
    if (!box) return;
    if (!items.length) { hideSuggest(); return; }
    box.innerHTML = items.map(s =>
      `<div class="suggest-item" data-value="${s.replace(/"/g,'&quot;')}">${s}</div>`
    ).join('');
    box.style.display = 'block';
  }

  async function fetchSuggest(q) {
    try {
      if (abortCtrl) abortCtrl.abort();
      abortCtrl = new AbortController();

      const resp = await fetch(`../actions/fornecedoresBuscar.php?term=${encodeURIComponent(q)}`, {
        method: 'GET',
        headers: {'Accept': 'application/json'},
        signal: abortCtrl.signal
      });
      if (!resp.ok) throw new Error('HTTP '+resp.status);
      const data = await resp.json();
      const arr = Array.isArray(data) ? data : [];
      showSuggest(arr.slice(0, 10));
    } catch (e) {
      // silencia
    }
  }

  if (inputQ) {
    inputQ.addEventListener('input', (ev) => {
      const q = (ev.target.value || '').trim();
      if (q.length < 2) { hideSuggest(); return; }
      fetchSuggest(q);
    });

    inputQ.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') {
        navigateWith({ q: inputQ.value.trim() });
      }
    });
  }

  if (box) {
    box.addEventListener('click', (ev) => {
      const el = ev.target.closest('.suggest-item');
      if (!el) return;
      const val = el.getAttribute('data-value') || '';
      inputQ.value = val;
      navigateWith({ q: val });
    });
    document.addEventListener('click', (ev) => {
      if (!box.contains(ev.target) && ev.target !== inputQ) hideSuggest();
    });
  }

  if (selAtivo) {
    selAtivo.addEventListener('change', () => {
      navigateWith({ ativo: selAtivo.value });
    });
  }
})();
