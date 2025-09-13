<?php
// autoErp/public/estoque/pages/produtos.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']);

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');
if (empty($_SESSION['csrf_produtos'])) {
    $_SESSION['csrf_produtos'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_produtos'];

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Controller
require_once __DIR__ . '/../controllers/produtosController.php';
$vm = produtos_list_viewmodel($pdo);

// Garantia de arrays
$vm['rows'] = is_array($vm['rows'] ?? null) ? $vm['rows'] : [];
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Produtos</title>

  <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../assets/images/favicon.ico">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    /* Dropdown de sugestões abaixo do input */
    .suggest-box{position:absolute;top:100%;left:0;right:0;z-index:1050;background:#fff;border:1px solid #e5e7eb;border-top:0;max-height:240px;overflow-y:auto;display:none}
    .suggest-item{padding:.5rem .75rem;cursor:pointer}
    .suggest-item:hover{background:#f3f4f6}
    .search-wrapper{position:relative;width:100%}
  </style>
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$menuAtivo = 'estoque-produtos';
include '../../layouts/sidebar.php';
?>

<main class="main-content">
  <!-- helper para o JS ler o CSRF -->
  <div id="produtos-config" data-csrf="<?= htmlspecialchars($vm['csrf'], ENT_QUOTES, 'UTF-8') ?>" hidden></div>

  <div class="position-relative iq-banner">
    <!-- NAV -->
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand">
          <h4 class="logo-title">Mundo Pets</h4>
        </a>
        <div class="input-group search-input">
          <span class="input-group-text" id="search-input">
            <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
              <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
              <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
            </svg>
          </span>
          <input id="busca" type="search" class="form-control" placeholder="Pesquisar por nome, SKU ou EAN...">
        </div>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-12">
            <h1 class="mb-0">Lista de Produtos</h1>
            <p>Pesquise, filtre e gerencie seus produtos.</p>
          </div>
        </div>
      </div>
      <div class="iq-header-img">
        <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
      </div>
    </div>
  </div>

  <div class="container-fluid content-inner mt-n3 py-0">
    <div class="card">
      <div class="card-body">
        <!-- Filtros (mudou? já recarrega) -->
        <div class="row g-2 mb-3">
          <div class="col-lg-3">
            <select id="select-setor" class="form-select">
              <option value="">Setor: Todos</option>
              <option value="petshop" <?= ($vm['setor'] ?? '') === 'petshop' ? 'selected' : ''; ?>>Pets Shop</option>
            </select>
          </div>
          <div class="col-lg-3">
            <select id="select-ativo" class="form-select">
              <option value="">Status: Todos</option>
              <option value="1" <?= ($vm['ativo'] ?? '') === '1' ? 'selected' : ''; ?>>Ativos</option>
              <option value="0" <?= ($vm['ativo'] ?? '') === '0' ? 'selected' : ''; ?>>Inativos</option>
            </select>
          </div>
          <div class="col-lg-6 text-end">
            <a class="btn btn-outline-secondary" href="./produtosNovo.php"><i class="bi bi-plus-lg me-1"></i> Novo Produto</a>
          </div>
        </div>

        <!-- Paginador (topo) -->
        <div id="produtos-pager-top" class="d-flex flex-wrap justify-content-between align-items-center mb-2"></div>

        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Setor</th>
                <th>SKU</th>
                <th>Código de Barras</th>
                <th>Marca</th>
                <th class="text-end">Preço</th>
                <th class="text-end">Estoque</th>
                <th>Ativo</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody id="produtos-tbody">
              <!-- renderizado via JS -->
              <tr><td colspan="9" class="text-center text-muted">Carregando...</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Paginador (rodapé) -->
        <div id="produtos-pager-bottom" class="d-flex flex-wrap justify-content-between align-items-center mt-2"></div>

      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-body d-flex justify-content-between align-items-center">
      <div class="left-panel">
        © <script>document.write(new Date().getFullYear())</script>
        <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
    </div>
  </footer>
</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/core/external.min.js"></script>
<script src="../../assets/vendor/aos/dist/aos.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>

<!-- Dados para o JS -->
<script>
  // Dados vindos do PHP (todos os registros já filtrados no backend atual)
  window.__PRODUTOS_FULL__ = <?= json_encode($vm['rows'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  window.__CSRF__ = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>

<!-- JS da página: paginação em client-side (8 por página) -->
<script>
(function(){
  "use strict";

  const PAGE_SIZE = 8; // 8 por página (pode mudar)
  const ALL_ORIG = Array.isArray(window.__PRODUTOS_FULL__) ? window.__PRODUTOS_FULL__ : [];
  let ALL = ALL_ORIG.slice(); // pode ser filtrado por busca
  const CSRF = window.__CSRF__ || "";

  const tbody = document.getElementById("produtos-tbody");
  const pagerTop = document.getElementById("produtos-pager-top");
  const pagerBottom = document.getElementById("produtos-pager-bottom");
  const selSetor = document.getElementById("select-setor");
  const selAtivo = document.getElementById("select-ativo");
  const busca = document.getElementById("busca");

  const state = {
    page: 1,
    pageSize: PAGE_SIZE,
    get total(){ return ALL.length; },
    get totalPages(){ return Math.max(1, Math.ceil(this.total / this.pageSize)); }
  };

  const fmtMoney = v => "R$ " + (Number(v)||0).toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2});
  const fmtQtd   = v => (Number(v)||0).toLocaleString("pt-BR",{minimumFractionDigits:3,maximumFractionDigits:3});
  const esc = s => String(s??"").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#039;");

  function aplicaFiltrosBackendLike(){
    const setor = (selSetor?.value||"").toLowerCase();
    const ativo = selAtivo?.value ?? "";
    const q = (busca?.value||"").toLowerCase().trim();

    ALL = ALL_ORIG.filter(r=>{
      const setorOK = !setor || String(r?.setor||"").toLowerCase() === setor;
      const ativoOK = (ativo==='' ? true : (Number(r?.ativo)?'1':'0')===ativo);
      const qOK = !q || [r?.nome, r?.sku, r?.ean].some(v=>String(v||"").toLowerCase().includes(q));
      return setorOK && ativoOK && qOK;
    });
    state.page = 1;
  }

  function renderRows(page){
    const start = (page-1)*state.pageSize;
    const end = Math.min(start + state.pageSize, state.total);
    const slice = ALL.slice(start, end);

    if(!slice.length){
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Nenhum produto encontrado.</td></tr>';
      return;
    }

    tbody.innerHTML = slice.map(r=>{
      const nome = esc(r?.nome??'-');
      const setor = esc(r?.setor ?? 'Pets Shop');
      const sku = esc(r?.sku ?? '-');
      const ean = esc(r?.ean ?? '-');
      const marca = esc(r?.marca ?? '-');
      const preco = fmtMoney(r?.preco_venda);
      const estoque = fmtQtd(r?.estoque_atual);
      const ativo = Number(r?.ativo) ? 1 : 0;
      const fornecedor = r?.fornecedor_nome ? esc(r.fornecedor_nome) : '';

      return `
        <tr>
          <td>
            ${nome}
            ${fornecedor ? `<div class="small text-muted">Fornecedor: ${fornecedor}</div>` : ""}
          </td>
          <td>${setor}</td>
          <td>${sku}</td>
          <td>${ean}</td>
          <td>${marca}</td>
          <td class="text-end">${preco}</td>
          <td class="text-end">${estoque}</td>
          <td><span class="badge ${ativo ? 'bg-success' : 'bg-secondary'}">${ativo ? 'Ativo' : 'Inativo'}</span></td>
          <td class="text-end text-nowrap">
            <form method="post" action="../actions/produtosExcluir.php" class="d-inline"
                  onsubmit="return confirm('Excluir este produto? Esta ação não pode ser desfeita.');">
              <input type="hidden" name="csrf" value="${esc(CSRF)}">
              <input type="hidden" name="id" value="${Number(r?.id)||0}">
              <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      `;
    }).join('');
  }

  function renderCounter(page){
    const start = state.total ? (page-1)*state.pageSize + 1 : 0;
    const end = Math.min(page*state.pageSize, state.total);
    return `
      <div class="small text-muted">
        Mostrando <strong>${start}</strong>–<strong>${end}</strong> de <strong>${state.total}</strong> itens
      </div>
    `;
  }

  function renderPager(page){
    const prevDis = page<=1 ? 'disabled' : '';
    const nextDis = page>=state.totalPages ? 'disabled' : '';
    return `
      <div class="d-flex align-items-center gap-2">
        <div class="text-muted small">Por página</div>
        <select class="form-select form-select-sm" id="page-size" style="width:auto">
          ${[8,16,32,64].map(n=>`<option value="${n}" ${state.pageSize===n?'selected':''}>${n}</option>`).join('')}
        </select>
        <nav aria-label="Paginação">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item ${prevDis}"><a class="page-link" href="#" data-nav="prev">«</a></li>
            <li class="page-item disabled"><span class="page-link">Pág. ${page} / ${state.totalPages}</span></li>
            <li class="page-item ${nextDis}"><a class="page-link" href="#" data-nav="next">»</a></li>
          </ul>
        </nav>
      </div>
    `;
  }

  function mountPagers(page){
    const counter = renderCounter(page);
    const pager = renderPager(page);
    if (pagerTop) pagerTop.innerHTML = counter + pager;
    if (pagerBottom) pagerBottom.innerHTML = counter + pager;

    [pagerTop, pagerBottom].forEach(wrap=>{
      if(!wrap) return;
      wrap.querySelectorAll('[data-nav="prev"]').forEach(a=>{
        a.addEventListener('click', (e)=>{ e.preventDefault(); if(state.page>1){ goTo(state.page-1,true); } });
      });
      wrap.querySelectorAll('[data-nav="next"]').forEach(a=>{
        a.addEventListener('click', (e)=>{ e.preventDefault(); if(state.page<state.totalPages){ goTo(state.page+1,true); } });
      });
      const sel = wrap.querySelector('#page-size');
      sel?.addEventListener('change', ()=>{
        state.pageSize = parseInt(sel.value,10)||PAGE_SIZE;
        state.page = 1;
        renderAll();
      });
    });
  }

  function goTo(p, smooth=false){
    state.page = Math.max(1, Math.min(p, state.totalPages));
    renderRows(state.page);
    mountPagers(state.page);
    if(smooth){ try{ tbody.closest('.card-body').scrollIntoView({behavior:'smooth', block:'start'}); }catch{} }
  }

  function renderAll(){
    renderRows(state.page);
    mountPagers(state.page);
  }

  // Filtros simples (client-side)
  selSetor?.addEventListener('change', ()=>{ aplicaFiltrosBackendLike(); renderAll(); });
  selAtivo?.addEventListener('change', ()=>{ aplicaFiltrosBackendLike(); renderAll(); });
  let t=null;
  busca?.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=>{ aplicaFiltrosBackendLike(); renderAll(); }, 250); });

  // Inicializa
  aplicaFiltrosBackendLike();
  renderAll();
})();
</script>
</body>
</html>
