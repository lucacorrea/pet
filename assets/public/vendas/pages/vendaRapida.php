<?php
// autoErp/public/vendas/pages/vendaRapida.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

if (empty($_SESSION['csrf_venda_rapida'])) {
  $_SESSION['csrf_venda_rapida'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_venda_rapida'];

// ===== Verifica se existe caixa ABERTO para a empresa =====
$caixaAberto = null;
try {
  $stCx = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS quando
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY aberto_em DESC
    LIMIT 1
  ");
  $stCx->execute([':c' => $empresaCnpj]);
  $caixaAberto = $stCx->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $caixaAberto = null;
}

// Produtos (para busca rápida)
$produtos = [];
try {
  $st = $pdo->prepare("
    SELECT id, nome, sku, ean, marca, unidade, preco_venda
    FROM produtos_peca
    WHERE empresa_cnpj = :c AND ativo = 1
    ORDER BY nome
    LIMIT 2000
  ");
  $st->execute([':c' => $empresaCnpj]);
  $produtos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $produtos = [];
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <title>Mundo Pets — Venda Rápida</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
    /* ——— Look & feel PDV supermercado ——— */
    .pdv-visor {
      background: #002c83ff;
      color: #f0f0f0;
      border-radius: 14px;
      padding: 16px 20px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: inset 0 0 0 1px #111827, 0 10px 28px rgba(0, 0, 0, .15);
    }

    .pdv-visor .linha1 {
      font-size: 1rem;
      opacity: .9
    }

    .pdv-visor .linha2 {
      font-size: 2.2rem;
      font-weight: 700;
      letter-spacing: .5px
    }

    .pdv-visor .right {
      text-align: right
    }

    .pdv-busca {
      position: relative;
    }

    .pdv-suggest {
      position: absolute;
      left: 0;
      right: 0;
      top: 100%;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-top: 0;
      max-height: 320px;
      overflow: auto;
      z-index: 1060;
      display: none;
      border-bottom-left-radius: 10px;
      border-bottom-right-radius: 10px;
    }

    .pdv-suggest .item {
      padding: .55rem .75rem;
      cursor: pointer;
    }

    .pdv-suggest .item:hover {
      background: #f3f4f6;
    }

    .pdv-suggest .muted {
      color: #6b7280;
      font-size: .85em
    }

    .itens-table td,
    .itens-table th {
      vertical-align: middle;
    }

    .totais-card {
      border: 1px dashed #c7c9d1;
      border-radius: 16px;
      background: #f9fafb;
    }

    .kbtn {
      min-width: 84px;
    }

    .kbd {
      background: #0b1220;
      color: #cbd5e1;
      padding: .15rem .45rem;
      border-radius: .35rem;
      font-size: .85rem;
      border: 1px solid #1f2937;
    }

    .pay-btn {
      height: 56px;
      font-size: 1.05rem;
    }

    .money {
      font-variant-numeric: tabular-nums;
    }

    .troco-ok {
      color: #16a34a;
    }

    .troco-neg {
      color: #dc2626;
    }

    @media (max-width: 1199px) {
      .pdv-visor .linha2 {
        font-size: 1.8rem;
      }
    }
  </style>
</head>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'vendas-rapida'; // ID do menu atual
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">Mundo Pets</h4>
          </a>
          <div class="ms-auto d-none d-lg-flex align-items-center gap-3">
            <span class="text-muted small">Atalhos: <span class="kbd">Enter</span> Adicionar • <span class="kbd">F2</span> Quantidade • <span class="kbd">F4</span> Finalizar</span>
          </div>
        </div>
      </nav>
      <!-- Modal: Caixa obrigatório -->

      <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-8">
              <h1 class="">Venda Rápida</h1>
              <p>Fluxo de PDV para balcão — leitor de código, busca por nome/SKU/EAN, e finalização rápida.</p>
              <?php if (!$caixaAberto): ?>

                <div  style="font-weight: 900; color: #f73232ff;">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  Nenhum caixa aberto no momento.
                  <a href="../../caixa/pages/caixaAbrir.php" class="alert-link text-white">Clique aqui</a> para abrir ou entrar em um caixa.
                </div>
              <?php else: ?>


                <i class="bi bi-cash-coin me-1"></i>
                Caixa aberto:
                <strong>#<?= (int)$caixaAberto['id'] ?></strong> —
                <?= htmlspecialchars($caixaAberto['tipo'], ENT_QUOTES, 'UTF-8') ?> —
                <?= htmlspecialchars($caixaAberto['terminal'], ENT_QUOTES, 'UTF-8') ?> —
                desde <?= htmlspecialchars($caixaAberto['quando'], ENT_QUOTES, 'UTF-8') ?>

              <?php endif; ?>

            </div>
            <div class="col-md-4 text-md-end">
              <a href="../pages/orcamentos.php" class="btn btn-outline-secondary">
                <i class="bi bi-receipt"></i> Orçamentos
              </a>
              <a href="./caixaAbrir.php" class="btn btn-outline-primary">
                <i class="bi bi-cash-stack"></i> Caixa
              </a>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <form method="post" action="../actions/vendasSalvar.php" id="form-venda" data-caixa="<?= $caixaAberto ? '1' : '0' ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="itens_json" id="itens_json">
        <input type="hidden" name="desconto" id="desconto_hidden" value="0.00">

        <div class="row g-3">
          <!-- ESQUERDA: visor, busca, itens -->
          <div class="col-xxl-8">
            <!-- Visor PDV -->
            <div class="pdv-visor mb-3">
              <div>
                <div class="linha1">Produto</div>
                <div class="linha2" id="visor-produto">—</div>
              </div>
              <div class="right">
                <div class="linha1">Total</div>
                <div class="linha2 money" id="visor-subtotal">R$ 0,00</div>
              </div>
            </div>

            <!-- Busca rápida -->
            <div class="card">
              <div class="card-body">
                <div class="row g-2 align-items-end">
                  <div class="col-lg-7">
                    <label class="form-label">Código / Nome / SKU / EAN</label>
                    <div class="pdv-busca">
                      <input type="text" class="form-control form-control-lg" id="inp-busca" placeholder="Digite o Nome do Produto" autocomplete="off" <?= $caixaAberto ? '' : 'disabled' ?>>
                      <div class="pdv-suggest" id="box-suggest"></div>
                    </div>
                    <div class="form-text">Use leitor de código de barras ou digite e pressione <span class="kbd">Enter</span>.</div>
                  </div>
                  <div class="col-lg-2">
                    <label class="form-label">Qtd</label>
                    <input type="number" class="form-control form-control-lg text-end" id="inp-qtd" step="0.001" min="0.001" <?= $caixaAberto ? '' : 'disabled' ?>>
                  </div>
                  <div class="col-lg-3">
                    <label class="form-label">Vlr. Unit (R$)</label>
                    <input type="number" class="form-control form-control-lg text-end" id="inp-preco" step="0.01" min="0" <?= $caixaAberto ? '' : 'disabled' ?>>
                  </div>

                  <div class="col-12 d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-primary kbtn" id="btn-add" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i> Adicionar <span class="small ms-1 kbd">Enter</span></button>
                    <button type="button" class="btn btn-outline-secondary kbtn" id="btn-qtd" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-123"></i> Qtd <span class="small ms-1 kbd">F2</span></button>
                    <button type="button" class="btn btn-outline-secondary kbtn" id="btn-desc" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-percent"></i> Desconto</button>
                    <button type="button" class="btn btn-outline-danger kbtn" id="btn-clear" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-trash3"></i> Limpar</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Itens -->
            <div class="card mt-3">
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped itens-table align-middle" id="tbl-itens">
                    <thead>
                      <tr>
                        <th>Descrição</th>
                        <th style="width:9rem;" class="text-end">Qtd</th>
                        <th style="width:10rem;" class="text-end">Vlr. Unit</th>
                        <th style="width:10rem;" class="text-end">Total</th>
                        <th style="width:4rem;" class="text-end"></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                      <tr>
                        <td colspan="5" class="text-muted small">Dica: clique no valor para editar; use o ícone <i class="bi bi-x"></i> para remover.</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- DIREITA: totais e pagamentos -->
          <div class="col-xxl-4">
            <div class="card totais-card">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Itens</span>
                  <strong id="tot-itens">0</strong>
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <span class="text-muted">Subtotal</span>
                  <strong class="money" id="tot-subtotal">R$ 0,00</strong>
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <span class="text-muted">Desconto</span>
                  <div class="d-flex align-items-center gap-2">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" id="inp-desconto" value="0.00" style="width:110px" <?= $caixaAberto ? '' : 'disabled' ?>>
                  </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                  <span class="fs-5">TOTAL</span>
                  <span class="fs-4 fw-bold money" id="tot-geral">R$ 0,00</span>
                </div>
              </div>
            </div>

            <div class="card mt-3">
              <div class="card-header">
                <h5 class="mb-0">Pagamento</h5>
              </div>
              <div class="card-body">
                <!-- Botões rápidos -->
                <div class="row g-2">
                  <div class="col-6">
                    <button type="button" class="btn btn-success w-100 pay-btn" data-pay="dinheiro" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Dinheiro</button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-primary w-100 pay-btn" data-pay="pix" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-upc-scan me-1"></i> PIX</button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-outline-dark w-100 pay-btn" data-pay="debito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card-2-back me-1"></i> Débito</button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-outline-dark w-100 pay-btn" data-pay="credito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card me-1"></i> Crédito</button>
                  </div>
                </div>

                <!-- Select + Dinheiro (Recebido/Troco) -->
                <div class="mt-3">
                  <label class="form-label">Forma de Pagamento</label>
                  <select name="forma_pagamento" id="forma_pagamento" class="form-select" <?= $caixaAberto ? '' : 'disabled' ?>>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="debito">Débito</option>
                    <option value="credito">Crédito</option>
                  </select>
                </div>

                <div id="grp-dinheiro" class="mt-3" style="display:none;">
                  <label class="form-label">Valor Recebido (Dinheiro)</label>
                  <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="number" step="0.01" min="0" class="form-control text-end" id="inp-recebido" placeholder="0,00">
                  </div>
                  <div class="d-flex justify-content-between mt-2">
                    <span class="text-muted">Troco</span>
                    <strong id="lbl-troco" class="money">R$ 0,00</strong>
                  </div>
                </div>

                <div class="d-grid mt-3">
                  <button type="submit" class="btn btn-lg btn-success" id="btn-finalizar" <?= $caixaAberto ? '' : 'disabled' ?>>
                    <i class="bi bi-check2-circle me-1"></i> Finalizar Venda <span class="small kbd">F4</span>
                  </button>
                </div>
                <div class="d-grid mt-2">
                  <a href="../../dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
                </div>
              </div>
            </div>

          </div>
        </div>
      </form>
    </div>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
  <script>
    const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // ===== helpers
    const fmt = v => (Number(v || 0)).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    const fmt3 = v => (Number(v || 0)).toLocaleString('pt-BR', {
      minimumFractionDigits: 3,
      maximumFractionDigits: 3
    });
    const el = sel => document.querySelector(sel);
    const tbody = document.querySelector('#tbl-itens tbody');
    let itens = []; // {id?, nome, qtd, unit}
    const form = document.getElementById('form-venda');
    const temCaixa = form.getAttribute('data-caixa') === '1';

    function totalGeral() {
      let subtotal = 0;
      itens.forEach(i => {
        subtotal += i.qtd * i.unit;
      });
      const desconto = parseFloat(el('#inp-desconto').value || '0');
      return Math.max(subtotal - desconto, 0);
    }

    function recalc() {
      let subtotal = 0,
        itensCount = 0;
      itens.forEach(i => {
        subtotal += i.qtd * i.unit;
        itensCount += 1;
      });
      const desconto = parseFloat(el('#inp-desconto').value || '0');
      const total = Math.max(subtotal - desconto, 0);
      el('#tot-subtotal').textContent = 'R$ ' + fmt(subtotal);
      el('#tot-geral').textContent = 'R$ ' + fmt(total);
      el('#tot-itens').textContent = itensCount;
      el('#visor-subtotal').textContent = 'R$ ' + fmt(total);
      el('#desconto_hidden').value = (desconto || 0).toFixed(2);
      el('#itens_json').value = JSON.stringify(itens);
      renderTable();
      recalcTroco(); // mantém troco atualizado quando total muda
    }

    function renderTable() {
      tbody.innerHTML = itens.map((i, idx) => `
        <tr>
          <td>${escapeHtml(i.nome)}</td>
          <td class="text-end">
            <input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end inp-qtd" data-idx="${idx}" value="${i.qtd.toFixed(3)}" ${temCaixa?'':'disabled'}>
          </td>
          <td class="text-end">
            <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end inp-unit" data-idx="${idx}" value="${i.unit.toFixed(2)}" ${temCaixa?'':'disabled'}>
          </td>
          <td class="text-end money">R$ ${fmt(i.qtd * i.unit)}</td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-idx="${idx}" ${temCaixa?'':'disabled'}><i class="bi bi-x"></i></button></td>
        </tr>
      `).join('');
    }

    function escapeHtml(s) {
      return String(s || '').replace(/[&<>"'`=\/]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#x60;',
        '=': '&#x3D;'
      } [c]));
    }

    // ===== busca / autocomplete
    const box = el('#box-suggest'),
      inpBusca = el('#inp-busca'),
      inpQtd = el('#inp-qtd'),
      inpPreco = el('#inp-preco');

    function filtra(q) {
      q = (q || '').trim().toLowerCase();
      if (!q) return [];
      return PRODUTOS.filter(p =>
        (p.nome || '').toLowerCase().includes(q) ||
        (p.sku || '').toLowerCase().includes(q) ||
        (p.ean || '').toLowerCase().includes(q) ||
        (p.marca || '').toLowerCase().includes(q)
      ).slice(0, 50);
    }

    function showSugestoes(lista) {
      if (!lista.length) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
      }
      box.innerHTML = lista.map(p => `
        <div class="item" data-id="${p.id}" data-preco="${Number(p.preco_venda||0)}" data-nome="${escapeHtml(p.nome||'')}">
          <div><strong>${escapeHtml(p.nome||'-')}</strong></div>
          <div class="muted">${escapeHtml([p.marca,p.sku,p.ean].filter(Boolean).join(' • '))}</div>
          <div class="muted">R$ ${fmt(p.preco_venda||0)} ${p.unidade?(' / '+escapeHtml(p.unidade)) : ''}</div>
        </div>
      `).join('');
      box.style.display = 'block';
    }
    if (temCaixa) {
      inpBusca.addEventListener('input', () => {
        const lista = filtra(inpBusca.value);
        showSugestoes(lista);
      });
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.pdv-busca')) {
          box.style.display = 'none';
        }
      });
      box.addEventListener('click', (e) => {
        const it = e.target.closest('.item');
        if (!it) return;
        const nome = it.getAttribute('data-nome');
        const preco = parseFloat(it.getAttribute('data-preco') || '0');
        el('#visor-produto').textContent = nome || '—';
        inpPreco.value = preco.toFixed(2);
        box.style.display = 'none';
        setTimeout(() => inpQtd.focus(), 10);
      });
    }

    // ===== adicionar item
    function addItemFromInputs() {
      if (!temCaixa) return;
      const nomeVisor = (el('#visor-produto').textContent || '').trim();
      const qtd = parseFloat(inpQtd.value || '0');
      const unit = parseFloat(inpPreco.value || '0');
      const termo = (inpBusca.value || '').trim();

      // se não escolheu pelo suggest, tenta achar por termo direto
      let finalNome = nomeVisor;
      if (!finalNome || finalNome === '—') {
        const lista = filtra(termo);
        if (lista[0]) {
          finalNome = lista[0].nome || '';
          if (!inpPreco.value || parseFloat(inpPreco.value || '0') <= 0)
            inpPreco.value = Number(lista[0].preco_venda || 0).toFixed(2);
        } else {
          finalNome = termo || 'Item';
        }
      }

      if (qtd <= 0 || unit < 0) return;
      itens.push({
        nome: finalNome,
        qtd,
        unit
      });

      // limpar busca e preparar próxima leitura
      inpBusca.value = '';
      el('#visor-produto').textContent = '—';
      inpQtd.value = '1.000';
      recalc();
      inpBusca.focus();
    }

    if (temCaixa) {
      el('#btn-add').addEventListener('click', addItemFromInputs);
      el('#btn-clear').addEventListener('click', () => {
        itens = [];
        recalc();
        inpBusca.value = '';
        inpQtd.value = '1.000';
        inpPreco.value = '0.00';
        el('#visor-produto').textContent = '—';
        inpBusca.focus();
      });
      el('#btn-qtd').addEventListener('click', () => inpQtd.select());
      el('#btn-desc').addEventListener('click', () => el('#inp-desconto').select());

      // edições inline
      tbody.addEventListener('input', (e) => {
        if (e.target.matches('.inp-qtd')) {
          const idx = +e.target.dataset.idx;
          const v = parseFloat(e.target.value || '0');
          if (itens[idx]) itens[idx].qtd = Math.max(v, 0);
          recalc();
        } else if (e.target.matches('.inp-unit')) {
          const idx = +e.target.dataset.idx;
          const v = parseFloat(e.target.value || '0');
          if (itens[idx]) itens[idx].unit = Math.max(v, 0);
          recalc();
        }
      });
      tbody.addEventListener('click', (e) => {
        if (e.target.closest('.btn-del')) {
          const idx = +e.target.closest('.btn-del').dataset.idx;
          itens.splice(idx, 1);
          recalc();
        }
      });
      el('#inp-desconto').addEventListener('input', recalc);
    }

    // ===== Pagamento — Dinheiro (Recebido/Troco)
    const selFP = el('#forma_pagamento');
    const grpDin = el('#grp-dinheiro');
    const inpRec = el('#inp-recebido');
    const lblTroco = el('#lbl-troco');

    function toggleDinheiroUI() {
      const isDinheiro = selFP.value === 'dinheiro';
      grpDin.style.display = isDinheiro ? 'block' : 'none';
      if (isDinheiro) setTimeout(() => inpRec.focus(), 50);
      validateFinalizeButton();
    }

    function recalcTroco() {
      if (selFP.value !== 'dinheiro') {
        lblTroco.textContent = 'R$ 0,00';
        lblTroco.className = 'money';
        return;
      }
      const total = totalGeral();
      const recebido = parseFloat(inpRec.value || '0');
      const troco = recebido - total;
      lblTroco.textContent = 'R$ ' + fmt(troco);
      lblTroco.className = 'money ' + (troco >= 0 ? 'troco-ok' : 'troco-neg');
      validateFinalizeButton();
    }

    function validateFinalizeButton() {
      const btn = el('#btn-finalizar');
      if (!temCaixa) {
        btn.disabled = true;
        return;
      }
      // precisa ter itens para finalizar
      if (!itens.length) {
        btn.disabled = true;
        return;
      }
      // se dinheiro, precisa recebido >= total
      if (selFP.value === 'dinheiro') {
        const total = totalGeral();
        const recebido = parseFloat(inpRec.value || '0');
        btn.disabled = !(recebido >= total);
      } else {
        btn.disabled = false;
      }
    }

    if (temCaixa) {
      // botões de pagamento rápido
      document.querySelectorAll('[data-pay]').forEach(btn => {
        btn.addEventListener('click', () => {
          selFP.value = btn.getAttribute('data-pay');
          toggleDinheiroUI();
        });
      });
      selFP.addEventListener('change', toggleDinheiroUI);
      inpRec.addEventListener('input', recalcTroco);
    }

    // ===== atalhos teclado (Enter só adiciona item na busca; F4 finaliza)
    if (temCaixa) {
      form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (document.activeElement === el('#inp-busca')) addItemFromInputs();
        }
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') {
          e.preventDefault();
          el('#inp-qtd').select();
        }
        if (e.key === 'F4') {
          e.preventDefault();
          if (!el('#btn-finalizar').disabled) {
            form.requestSubmit ?
              form.requestSubmit(el('#btn-finalizar')) :
              el('#btn-finalizar').click();
          }
        }
      });
    }

    // valida antes de enviar (caixa + itens + dinheiro suficiente)
    form.addEventListener('submit', (e) => {
      if (!temCaixa) {
        e.preventDefault();
        alert('Abra um caixa para finalizar a venda.');
        return;
      }
      if (!itens.length) {
        e.preventDefault();
        alert('Adicione ao menos 1 item.');
        return;
      }
      if (selFP.value === 'dinheiro') {
        const total = totalGeral();
        const recebido = parseFloat(inpRec.value || '0');
        if (!(recebido >= total)) {
          e.preventDefault();
          alert('Valor recebido insuficiente para pagamento em dinheiro.');
          return;
        }
      }
    });

    // start
    recalc();
    toggleDinheiroUI(); // configura UI inicial conforme select
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const m = document.getElementById('modalCaixaObrigatorio');
      if (m) {
        const modal = new bootstrap.Modal(m);
        modal.show();
      }
    });
  </script>

</body>

</html>