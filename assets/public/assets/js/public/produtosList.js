// autoErp/public/assets/js/public/produtosList.js
(function () {
  function $(sel) { return document.querySelector(sel); }
  const $q     = $('#search-q');
  const $sug   = $('#q-suggest');
  const $setor = $('#select-setor');
  const $ativo = $('#select-ativo');
  const $tbody = $('#produtos-tbody');
  const $cfg   = $('#produtos-config'); // hidden div com data-csrf

  if (!$q || !$sug || !$setor || !$ativo || !$tbody) return;

  const CSRF = ($cfg && $cfg.dataset && $cfg.dataset.csrf) ? $cfg.dataset.csrf : '';

  let typingTimer = 0;

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }
  function escapeAttr(s) {
    return String(s).replace(/"/g, '&quot;');
  }
  function formatMoney(v) {
    try { return (Number(v||0)).toLocaleString('pt-BR', { minimumFractionDigits: 2 }); }
    catch(e){ return '0,00'; }
  }
  function formatQty(v) {
    try { return (Number(v||0)).toLocaleString('pt-BR', { minimumFractionDigits: 3 }); }
    catch(e){ return '0,000'; }
  }

  function renderRows(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      $tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Nenhum produto encontrado.</td></tr>';
      return;
    }
    const html = rows.map(r => {
      const setorNome   = (String(r.setor||'') === 'lavajato') ? 'Lava Jato' : 'Auto Peças';
      const fornecedor  = r.fornecedor_nome ? `<div class="small text-muted">Fornecedor: ${escapeHtml(r.fornecedor_nome)}</div>` : '';
      const ativoBadge  = (Number(r.ativo||0) ? `<span class="badge bg-success">Ativo</span>` : `<span class="badge bg-secondary">Inativo</span>`);
      const id          = Number(r.id)||0;
      return `
        <tr>
          <td>${escapeHtml(r.nome||'-')}${fornecedor}</td>
          <td>${setorNome}</td>
          <td>${escapeHtml(r.sku||'-')}</td>
          <td>${escapeHtml(r.ean||'-')}</td>
          <td>${escapeHtml(r.marca||'-')}</td>
          <td class="text-end">R$ ${formatMoney(r.preco_venda)}</td>
          <td class="text-end">${formatQty(r.estoque_atual)}</td>
          <td>${ativoBadge}</td>
          <td class="text-end text-nowrap">
            <form method="post" action="../actions/produtosExcluir.php" class="d-inline"
                  onsubmit="return confirm('Excluir este produto? Esta ação não pode ser desfeita.');">
              <input type="hidden" name="csrf" value="${escapeAttr(CSRF)}">
              <input type="hidden" name="id" value="${id}">
              <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      `;
    }).join('');
    $tbody.innerHTML = html;
  }

  function renderSuggestions(list) {
    if (!Array.isArray(list) || list.length === 0 || !$q.value.trim()) {
      $sug.style.display = 'none';
      $sug.innerHTML = '';
      return;
    }
    $sug.innerHTML = list.map(t => `<div class="suggest-item" data-text="${escapeAttr(t)}">${escapeHtml(t)}</div>`).join('');
    $sug.style.display = 'block';
  }

  function fetchProducts() {
    const params = new URLSearchParams({
      q: $q.value.trim(),
      setor: $setor.value,
      ativo: $ativo.value,
      limit: '100'
    });
    // caminho relativo ao HTML (produtos.php)
    fetch(`../actions/produtosBuscar.php?${params.toString()}`, {
      headers: {'X-Requested-With': 'fetch'}
    })
      .then(r => r.json())
      .then(data => {
        if (!data || data.ok !== 1) return;
        renderRows(data.rows || []);
        renderSuggestions(data.suggestions || []);
      })
      .catch(() => {
        renderSuggestions([]);
      });
  }

  function hideSuggestSoon() {
    setTimeout(() => { $sug.style.display = 'none'; }, 150);
  }

  // Eventos
  $q.addEventListener('input', () => {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(fetchProducts, 250);
  });
  $q.addEventListener('focus', () => {
    if ($q.value.trim()) fetchProducts();
  });
  $q.addEventListener('blur', hideSuggestSoon);

  $sug.addEventListener('mousedown', (ev) => {
    const item = ev.target.closest('.suggest-item');
    if (!item) return;
    const txt = item.getAttribute('data-text') || '';
    $q.value = txt;
    $sug.style.display = 'none';
    fetchProducts();
  });

  $setor.addEventListener('change', fetchProducts);
  $ativo.addEventListener('change', fetchProducts);

  // opcional: primeira sincronização com filtros atuais
  // fetchProducts();
})();
