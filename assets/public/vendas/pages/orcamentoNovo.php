<?php
// autoErp/public/vendas/pages/orcamentoNovo.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

if (empty($_SESSION['csrf_orc_novo'])) $_SESSION['csrf_orc_novo'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_orc_novo'];

// próximo número
$proxNumero = 1;
try {
  $st = $pdo->prepare("SELECT COALESCE(MAX(numero),0)+1 FROM orcamentos_peca WHERE empresa_cnpj = :c");
  $st->execute([':c' => $empresaCnpj]);
  $proxNumero = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $proxNumero = 1;
}

// === Carrega produtos ativos da empresa (para o auto-complete) ===
$produtos = [];
try {
  $stp = $pdo->prepare("
    SELECT
      id,
      nome,
      sku,
      ean,
      marca,
      unidade,
      preco_venda
    FROM produtos_peca
    WHERE empresa_cnpj = :c AND ativo = 1
    ORDER BY nome
    LIMIT 2000
  ");
  $stp->execute([':c' => $empresaCnpj]);
  $produtos = $stp->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $produtos = [];
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Novo Orçamento</title>
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/logo.png">
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
    .itens-table td {
      vertical-align: middle;
    }

    .search-box {
      position: relative;
    }

    /* Sugestões: ficam por cima de tudo */
    .suggest-box {
      position: absolute;
      left: 0;
      right: 0;
      top: 100%;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-top: 0;
      max-height: 260px;
      overflow-y: auto;
      display: none;

      z-index: 50000;
      /* acima de botões/headers */
      box-shadow: 0 10px 24px rgba(0, 0, 0, .12);
    }

    .suggest-item {
      padding: .5rem .75rem;
      cursor: pointer;
    }

    .suggest-item:hover {
      background: #f3f4f6;
    }

    .muted {
      color: #6b7280;
      font-size: .85em;
    }

    /* evitar corte por containers do tema */
    .card,
    .card-body,
    .table-responsive {
      overflow: visible !important;
    }

    .itens-table {
      position: relative;
      z-index: 1;
    }
  </style>
</head>

<body>

  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'vendas-orcamentos';
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">Mundo Pets</h4>
          </a>

          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">

              </svg>
            </span>

          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 150px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Novo Orçamento</h1>
              <p>Cadastre um orçamento com itens de produto/serviço.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <form method="post" action="../actions/orcamentosSalvar.php" id="form-orc">
        <input type="hidden" name="op" value="orc_novo">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div class="card mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-2">
                <label class="form-label">Número</label>
                <input type="text" class="form-control" value="<?= (int)$proxNumero ?>" disabled>
              </div>
              <div class="col-md-4">
                <label class="form-label">Cliente</label>
                <input type="text" name="cliente_nome" class="form-control" maxlength="150">
              </div>
              <div class="col-md-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="cliente_telefone" class="form-control" maxlength="20">
              </div>
              <div class="col-md-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="cliente_email" class="form-control" maxlength="150">
              </div>
              <div class="col-md-3">
                <label class="form-label">Validade</label>
                <input type="date" name="validade" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Desconto (R$)</label>
                <input type="number" name="desconto" step="0.01" min="0" class="form-control" value="0.00">
              </div>
              <div class="col-md-6">
                <label class="form-label">Observações</label>
                <input type="text" name="observacoes" class="form-control" maxlength="255">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            <h5 class="mb-0">Itens</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table itens-table" id="itens-table">
                <thead>
                  <tr>
                    <th style="width: 12rem;">Tipo</th>
                    <th>Descrição / Produto</th>
                    <th style="width: 8rem;">Qtd</th>
                    <th style="width: 10rem;">Vlr. Unit</th>
                    <th style="width: 10rem;">Vlr. Total</th>
                    <th style="width: 4rem;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary" id="btn-add-item"><i class="bi bi-plus-lg me-1"></i>Adicionar item</button>
          </div>
        </div>

        <div class="card">
          <div class="card-body d-flex justify-content-between align-items-center">
            <div>
              <strong>Subtotal:</strong> R$ <span id="subtotal">0,00</span>
              <span class="mx-2">•</span>
              <strong>Total:</strong> R$ <span id="total">0,00</span>
            </div>
            <div class="d-flex gap-2">

              <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Salvar</button>
            </div>
          </div>
        </div>
      </form>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <!-- JS -->
  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>

  <script>
    // ===== Produtos vindos do banco =====
    const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // Portal global para “fixed dropdown” (evita cortes por overflow/transform)
    let SUGGEST_PORTAL = document.getElementById('suggest-portal');
    if (!SUGGEST_PORTAL) {
      SUGGEST_PORTAL = document.createElement('div');
      SUGGEST_PORTAL.id = 'suggest-portal';
      document.body.appendChild(SUGGEST_PORTAL);
    }

    const tbody = document.querySelector('#itens-table tbody');
    const btnAdd = document.getElementById('btn-add-item');
    const fmt = (v) => (Number(v || 0)).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    function rowTemplate({
      desc = '',
      qtd = '1.000',
      unit = '0.00',
      tipo = 'produto',
      prodId = ''
    }) {
      return `
        <tr>
          <td>
            <select name="item_tipo[]" class="form-select item-tipo">
              <option value="produto" ${tipo==='produto'?'selected':''}>Produto</option>
              <option value="servico" ${tipo==='servico'?'selected':''}>Serviço</option>
            </select>
          </td>
          <td>
            <div class="search-box">
              <input type="hidden" name="item_id[]" value="${prodId}">
              <input type="text" name="descricao[]" class="form-control item-desc" required value="${desc}" placeholder="${tipo==='produto'?'Digite para buscar produto...':'Descrição do serviço'}" autocomplete="off">
              <div class="suggest-box"></div>
              <div class="muted mt-1 hint" style="display:${tipo==='produto'?'block':'none'}">Dica: pesquise por nome, SKU, EAN ou marca.</div>
            </div>
          </td>
          <td><input type="number" step="0.001" min="0" name="qtd[]" class="form-control text-end" value="${qtd}"></td>
          <td><input type="number" step="0.01" min="0" name="valor_unit[]" class="form-control text-end" value="${unit}"></td>
          <td class="text-end align-middle"><span class="vlr-total">0,00</span></td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del"><i class="bi bi-x"></i></button></td>
        </tr>
      `;
    }

    function addRow(preset = {}) {
      const tr = document.createElement('tr');
      tr.innerHTML = rowTemplate(preset);
      tbody.appendChild(tr);
      recalc();
      wireRow(tr);
    }

    function recalc() {
      let subtotal = 0;
      tbody.querySelectorAll('tr').forEach(tr => {
        const qtd = parseFloat(tr.querySelector('input[name="qtd[]"]').value || '0');
        const unit = parseFloat(tr.querySelector('input[name="valor_unit[]"]').value || '0');
        const tot = qtd * unit;
        tr.querySelector('.vlr-total').textContent = fmt(tot);
        subtotal += tot;
      });
      document.getElementById('subtotal').textContent = fmt(subtotal);
      const desconto = parseFloat(document.querySelector('input[name="desconto"]').value || '0');
      document.getElementById('total').textContent = fmt(Math.max(subtotal - desconto, 0));
    }

    function filtraProdutos(q) {
      q = (q || '').trim().toLowerCase();
      if (!q) return [];
      return PRODUTOS.filter(p => {
        return (p.nome || '').toLowerCase().includes(q) ||
          (p.sku || '').toLowerCase().includes(q) ||
          (p.ean || '').toLowerCase().includes(q) ||
          (p.marca || '').toLowerCase().includes(q);
      }).slice(0, 30);
    }

    function renderSugestoesFixed(box, anchorInput, lista) {
      if (!lista.length) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
      }

      box.innerHTML = lista.map(p => `
        <div class="suggest-item" data-id="${p.id}">
          <div><strong>${escapeHtml(p.nome||'-')}</strong></div>
          <div class="muted">${escapeHtml([p.marca, p.sku, p.ean].filter(Boolean).join(' • '))}</div>
          <div class="muted">R$ ${fmt(p.preco_venda||0)} ${p.unidade?(' / '+escapeHtml(p.unidade)) : ''}</div>
        </div>
      `).join('');

      if (box.parentNode !== SUGGEST_PORTAL) SUGGEST_PORTAL.appendChild(box);

      const r = anchorInput.getBoundingClientRect();
      box.style.position = 'fixed';
      box.style.left = (r.left) + 'px';
      box.style.top = (r.bottom) + 'px';
      box.style.width = (r.width) + 'px';
      box.style.display = 'block';
    }

    function wireRow(tr) {
      const tipoSel = tr.querySelector('.item-tipo');
      const descInp = tr.querySelector('.item-desc');
      const idInp = tr.querySelector('input[name="item_id[]"]');
      const unitInp = tr.querySelector('input[name="valor_unit[]"]');
      const hint = tr.querySelector('.hint');
      const box = tr.querySelector('.suggest-box'); // será movida para o portal

      tipoSel.addEventListener('change', () => {
        if (tipoSel.value === 'produto') {
          hint.style.display = 'block';
          descInp.placeholder = 'Digite para buscar produto...';
        } else {
          hint.style.display = 'none';
          descInp.placeholder = 'Descrição do serviço';
          idInp.value = '';
        }
        box.style.display = 'none';
      });

      descInp.addEventListener('input', () => {
        if (tipoSel.value !== 'produto') {
          box.style.display = 'none';
          return;
        }
        const lista = filtraProdutos(descInp.value);
        renderSugestoesFixed(box, descInp, lista);
      });

      box.addEventListener('click', (e) => {
        const item = e.target.closest('.suggest-item');
        if (!item) return;
        const id = parseInt(item.getAttribute('data-id'));
        const p = PRODUTOS.find(x => Number(x.id) === id);
        if (!p) return;

        idInp.value = p.id;
        descInp.value = p.nome || '';
        unitInp.value = Number(p.preco_venda || 0).toFixed(2); // <<< PREÇO VEM AUTOMÁTICO
        box.style.display = 'none';
        recalc();
      });

      // Fecha ao clicar fora / rolar
      document.addEventListener('click', (e) => {
        if (!tr.contains(e.target) && e.target !== box && !box.contains(e.target)) {
          box.style.display = 'none';
        }
      }, {
        capture: true
      });

      window.addEventListener('scroll', () => {
        box.style.display = 'none';
      }, {
        passive: true
      });

      tr.addEventListener('input', (e) => {
        if (e.target.matches('input')) recalc();
      });
    }

    function escapeHtml(s) {
      return String(s || '').replace(/[&<>"'`=\/]/g, function(c) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
          '/': '&#x2F;',
          '`': '&#x60;',
          '=': '&#x3D;'
        })[c];
      });
    }

    // Eventos de tabela
    btnAdd.addEventListener('click', () => addRow());
    tbody.addEventListener('click', (e) => {
      if (e.target.closest('.btn-del')) {
        e.target.closest('tr').remove();
        recalc();
      }
    });
    document.querySelector('input[name="desconto"]').addEventListener('input', recalc);

    // Linha inicial
    addRow();
  </script>
</body>

</html>