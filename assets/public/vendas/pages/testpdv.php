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

// ===== toast (ok/err/msg) via GET =====
$ok       = isset($_GET['ok'])  ? (int)$_GET['ok']  : 0;
$err      = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$msg      = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$vendaId  = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;

// ===== Verifica se existe caixa ABERTO =====
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
  <title><?= htmlspecialchars($empresaNome ?: 'PDV', ENT_QUOTES, 'UTF-8') ?> — Venda Rápida</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/logo.png">

  <!-- libs/tema -->
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
    /* ======= SUA PALETA (dark) ======= */
    :root{
      --bg:#0b0f18; --panel:#121824; --text:#e6e9ef; --muted:#a0a8b8;
      --primary:#3b82f6; --primary-800:#1d4ed8; --border:#243042; --focus:#93c5fd;
      --blue-900:#0d2748; --blue-800:#10305b; --blue-700:#133a6f; /* toms para lembrar o layout de referência */
      --left-col:360px;        /* largura coluna esquerda */
      --right-col:380px;       /* largura coluna direita */
      --tile-h:90px;           /* altura tile */
      --input-lg-h:54px;
    }

    html,body{height:100%}
    body{
      margin:0;background:var(--bg);color:var(--text);
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial;overflow:hidden;
    }

    /* ======= CABEÇALHO (CAIXA ABERTO) ======= */
    .pdv-top{
      display:grid;grid-template-columns:1fr 280px;
      gap:12px; align-items:center;
      padding:12px 16px;
      background:linear-gradient(0deg, var(--blue-800), var(--blue-900));
      border-bottom:1px solid var(--border);
      box-shadow:0 6px 24px rgba(0,0,0,.35);
    }
    .top-title{
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.08);
      border-radius:10px; padding:10px 14px; font-weight:800; letter-spacing:.5px;
      text-transform:uppercase; text-align:center;
    }
    .top-search input{
      height:40px;width:100%;border-radius:10px;border:1px solid var(--border);
      background:#0e1421;color:var(--text);padding:0 12px;
    }
    .top-search input::placeholder{color:#95a2b8}

    /* ======= GRADE PRINCIPAL ======= */
    .pdv-stage{
      display:grid;height:calc(100vh - 70px);
      grid-template-columns: var(--left-col) 1fr var(--right-col);
      gap:14px; padding:14px; box-sizing:border-box;
    }
    @media (max-width:1440px){
      :root{ --right-col:360px }
    }
    @media (max-width:1366px){
      :root{ --left-col:330px; --right-col:340px; --tile-h:84px; --input-lg-h:50px }
    }
    @media (max-width:1100px){
      .pdv-stage{ grid-template-columns: 1fr; }
    }

    /* ======= CARDS BASE ======= */
    .card-pdv{
      background:linear-gradient(180deg, var(--blue-900), var(--blue-800));
      border:1px solid rgba(255,255,255,.08);
      border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.35);
      color:var(--text);
    }
    .card-pdv .card-header{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.08)}
    .card-pdv .card-body{padding:14px}

    /* ======= COLUNA ESQUERDA ======= */
    .left{display:grid;grid-template-rows: auto auto auto 1fr; gap:14px; min-height:0}
    .logo-box{
      display:grid;grid-template-columns:120px 1fr; gap:12px; align-items:center;
    }
    .logo-box .logo{
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      border-radius:12px; height:110px; display:flex; align-items:center; justify-content:center;
    }
    .logo-box .logo img{ max-width:96px; max-height:88px; opacity:.9 }
    .tile{background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; height:var(--tile-h); display:flex; flex-direction:column; gap:6px}
    .tile .label{font-size:.85rem;color:#c8d2e6;text-transform:uppercase;letter-spacing:.3px}
    .tile .value{display:flex;align-items:center;gap:8px;height:100%; font-variant-numeric:tabular-nums}
    .tile .value strong{font-size:1.4rem}
    .tile input{
      height:calc(var(--tile-h) - 38px); width:100%;
      border-radius:10px; border:1px solid var(--border); background:#0e1421; color:var(--text);
      padding:0 12px; font-size:1.05rem;
    }
    .tile input:focus{border-color:var(--focus); box-shadow:0 0 0 .2rem rgba(147,197,253,.15)}
    .money-prefix{padding:0 10px;border:1px solid var(--border);border-radius:10px;background:#0e1421;color:#9fb2ce;height:calc(var(--tile-h) - 38px);display:flex;align-items:center}

    .help-list{font-size:.88rem;color:#d6deeb}
    .help-list .muted{color:#a7b5cc}

    /* ======= CENTRO (LISTA PRODUTOS) ======= */
    .center{min-height:0;display:grid;grid-template-rows:auto 1fr auto; gap:14px}
    .visor{
      background:linear-gradient(180deg, var(--blue-800), var(--blue-700));
      border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:14px 16px;
      display:flex; align-items:center; justify-content:space-between;
    }
    .visor .l1{font-size:.95rem;color:#c8d2e6}
    .visor .big{font-size:2rem;font-weight:800}
    @media (max-width:1366px){ .visor .big{font-size:1.8rem} }

    .table-wrap{min-height:0; overflow:auto}
    table{color:#e9eef9}
    thead th{background:rgba(255,255,255,.06); border-bottom:1px solid rgba(255,255,255,.12)}
    .table>:not(caption)>*>*{border-bottom:1px solid rgba(255,255,255,.08)}
    .table-striped>tbody>tr:nth-of-type(odd)>*{background:rgba(255,255,255,.03)}

    .subtotal{
      display:grid; grid-template-columns:1fr 280px; gap:12px;
      align-items:center;
    }
    .subtotal .box{
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      border-radius:12px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;
    }
    .subtotal .box .label{color:#c8d2e6}
    .subtotal .box .num{font-size:2rem;font-weight:800}
    @media (max-width:1366px){ .subtotal .box .num{font-size:1.7rem} }

    /* ======= DIREITA (PAGAMENTO) ======= */
    .right{min-height:0;display:grid;grid-template-rows:auto 1fr; gap:14px}
    .totais.card-pdv{background:linear-gradient(180deg, rgba(59,130,246,.08), rgba(59,130,246,.03)); border:1px dashed rgba(147,197,253,.4)}
    .pay-btn{height:var(--input-lg-h); font-size:1.05rem; border-radius:12px}
    .btn-success{background:#16a34a;border-color:#16a34a}
    .btn-success:hover{background:#15803d;border-color:#15803d}
    .btn-primary{background:var(--primary);border-color:var(--primary)}
    .btn-primary:hover{background:var(--primary-800);border-color:var(--primary-800)}
    .btn-outline-light{color:var(--text);border-color:rgba(255,255,255,.2)}
    .btn-outline-light:hover{background:rgba(255,255,255,.06)}

    .recebido-troco{display:grid; grid-template-columns:1fr 1fr; gap:12px}
    .rt-tile{background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:12px}
    .rt-tile .label{color:#c8d2e6; margin-bottom:6px}
    .rt-tile .num{font-size:1.6rem;font-weight:800}
    .rt-tile input{
      height:48px;border-radius:10px; border:1px solid var(--border); background:#0e1421; color:var(--text);
      padding:0 12px; font-size:1.05rem;
    }
    .troco-ok{color:#22c55e} .troco-neg{color:#f87171}
    .text-muted{color:#b9c7dc!important}
    .kbd{background:#0b1220;color:#cbd5e1;padding:.15rem .45rem;border-radius:.35rem;border:1px solid #1f2937}
  </style>
</head>
<body>

  <!-- TOAST -->
  <?php if ($ok || $err): ?>
    <div id="toastMsg" class="toast show align-items-center border-0 position-fixed top-0 end-0 m-3 shadow-lg"
      role="alert" aria-live="assertive" aria-atomic="true"
      style="z-index:2000;min-width:340px;border-radius:12px;overflow:hidden;">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2 text-white fw-semibold <?= $ok ? 'bg-success' : 'bg-danger' ?>">
          <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> fs-4"></i>
          <?= htmlspecialchars($msg ?: ($ok ? 'Venda registrada com sucesso!' : 'Falha ao registrar venda.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <button id="toastClose" type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
      </div>
      <div class="progress" style="height:3px;">
        <div id="toastProgress" class="progress-bar <?= $ok ? 'bg-light' : 'bg-warning' ?>" style="width:100%"></div>
      </div>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const toastEl = document.getElementById("toastMsg");
        const progress = document.getElementById("toastProgress");
        if (!toastEl) return;
        const DURATION = 1400;
        const toast = new bootstrap.Toast(toastEl, { delay: DURATION, autohide: true });
        toast.show();
        let width=100, stepMs=20, step=100*stepMs/DURATION;
        const itv=setInterval(()=>{ width=Math.max(0,width-step); if(progress) progress.style.width=width+"%"; if(width<=0) clearInterval(itv); }, stepMs);
        <?php if ($ok && $vendaId > 0): ?>
        toastEl.addEventListener('hidden.bs.toast', ()=>{ window.location.href='./danfe_nfce.php?venda_id=<?= (int)$vendaId ?>'; });
        <?php endif; ?>
      });
    </script>
  <?php endif; ?>

  <!-- HEADER -->
  <div class="pdv-top">
    <div class="top-title">CAIXA <?= $caixaAberto ? 'ABERTO' : 'FECHADO' ?></div>
    <div class="top-search d-none d-sm-block">
      <input type="text" placeholder="Observação / Identificação da venda (opcional)">
    </div>
  </div>

  <!-- STAGE -->
  <div class="pdv-stage">
    <!-- ESQUERDA -->
    <div class="left">
      <!-- bloco logo + Código de barras -->
      <div class="card-pdv">
        <div class="card-body">
          <div class="logo-box">
            <div class="logo">
              <!-- use seu logo se quiser -->
              <img src="../../assets/images/dashboard/logo.png" alt="logo">
            </div>
            <div>
              <div class="tile">
                <div class="label">CÓDIGO DE BARRAS</div>
                <div class="value pdv-busca">
                  <input type="text" id="inp-busca" placeholder="Passe o leitor ou digite o nome" autocomplete="off" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <div class="pdv-suggest" id="box-suggest"></div>
                </div>
              </div>

              <div class="tile" style="margin-top:10px">
                <div class="label">CÓDIGO</div>
                <div class="value">
                  <input type="text" placeholder="SKU / interno (opcional)" id="inp-sku" />
                </div>
              </div>
            </div>
          </div>

          <div class="tile" style="margin-top:12px">
            <div class="label">VALOR UNITÁRIO</div>
            <div class="value">
              <span class="money-prefix">R$</span>
              <input type="number" id="inp-preco" class="text-end" step="0.01" min="0" value="0.00" <?= $caixaAberto ? '' : 'disabled' ?>>
            </div>
          </div>

          <div class="tile" style="margin-top:10px">
            <div class="label">TOTAL DO ITEM</div>
            <div class="value"><strong id="tile-item-total">R$ 0,00</strong></div>
          </div>
        </div>
      </div>

      <!-- atalhos + quantidade -->
      <div class="card-pdv">
        <div class="card-body">
          <div class="tile" style="margin-bottom:10px">
            <div class="label">QUANTIDADE</div>
            <div class="value">
              <input type="number" id="inp-qtd" class="text-end" step="0.001" min="0.001" value="1.000" <?= $caixaAberto ? '' : 'disabled' ?>>
            </div>
          </div>
          <div class="help-list">
            <div class="muted">F2 — Alterar Quantidade • Enter — Adicionar • F4 — Finalizar</div>
            <div class="mt-2 d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-primary btn-sm" id="btn-add" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i>&nbsp;Adicionar</button>
              <button type="button" class="btn btn-outline-light btn-sm" id="btn-desc" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-percent"></i>&nbsp;Desconto</button>
              <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-trash3"></i>&nbsp;Limpar</button>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /left -->

    <!-- CENTRO -->
    <div class="center">
      <!-- visor produto/total -->
      <div class="visor">
        <div>
          <div class="l1">Produto</div>
          <div class="big" id="visor-produto">—</div>
        </div>
        <div class="text-end">
          <div class="l1">Total</div>
          <div class="big money" id="visor-subtotal">R$ 0,00</div>
        </div>
      </div>

      <!-- tabela de itens -->
      <div class="card-pdv" style="min-height:0">
        <div class="card-header"><strong>LISTA DE PRODUTOS</strong></div>
        <div class="card-body table-wrap">
          <table class="table table-striped align-middle mb-0" id="tbl-itens">
            <thead>
              <tr>
                <th>#Item</th>
                <th>Código</th>
                <th>Descrição</th>
                <th class="text-end" style="width:8rem">Qtd</th>
                <th class="text-end" style="width:9rem">Vlr. Unit</th>
                <th class="text-end" style="width:9rem">Total</th>
                <th class="text-end" style="width:3rem"></th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr><td colspan="7" class="text-muted small">Dica: edite Qtd/Vlr direto na tabela; clique no <i class="bi bi-x"></i> para remover.</td></tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- subtotal grande -->
      <div class="subtotal">
        <div class="box">
          <span class="label">SUBTOTAL</span>
          <span class="num money" id="tot-subtotal">R$ 0,00</span>
        </div>
        <div class="box">
          <span class="label">DESCONTO</span>
          <input type="number" step="0.01" min="0" class="form-control text-end" id="inp-desconto" value="0.00" style="max-width:140px">
        </div>
      </div>
    </div><!-- /center -->

    <!-- DIREITA -->
    <div class="right">
      <form method="post" action="../actions/vendaRapidaSalvar.php" id="form-venda" data-caixa="<?= $caixaAberto ? '1' : '0' ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="itens_json" id="itens_json">
        <input type="hidden" name="desconto" id="desconto_hidden" value="0.00">

        <!-- totais -->
        <div class="card-pdv totais">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <span class="text-muted">Itens</span><strong id="tot-itens">0</strong>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
              <span class="fs-6">TOTAL</span>
              <span class="fs-2 fw-bold money" id="tot-geral">R$ 0,00</span>
            </div>
          </div>
        </div>

        <!-- formas pagamento -->
        <div class="card-pdv" style="min-height:0">
          <div class="card-header"><strong>PAGAMENTO</strong></div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-6"><button type="button" class="btn btn-success w-100 pay-btn" data-pay="dinheiro" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Dinheiro</button></div>
              <div class="col-6"><button type="button" class="btn btn-primary w-100 pay-btn" data-pay="pix" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-upc-scan me-1"></i> PIX</button></div>
              <div class="col-6"><button type="button" class="btn btn-outline-light w-100 pay-btn" data-pay="debito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card-2-back me-1"></i> Débito</button></div>
              <div class="col-6"><button type="button" class="btn btn-outline-light w-100 pay-btn" data-pay="credito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card me-1"></i> Crédito</button></div>
            </div>

            <div class="mt-3">
              <label class="form-label">Forma de Pagamento</label>
              <select name="forma_pagamento" id="forma_pagamento" class="form-select" <?= $caixaAberto ? '' : 'disabled' ?>>
                <option value="dinheiro">Dinheiro</option>
                <option value="pix">PIX</option>
                <option value="debito">Débito</option>
                <option value="credito">Crédito</option>
              </select>
            </div>

            <!-- total recebido / troco -->
            <div id="grp-dinheiro" class="mt-3" style="display:none;">
              <div class="recebido-troco">
                <div class="rt-tile">
                  <div class="label">TOTAL RECEBIDO</div>
                  <input type="number" step="0.01" min="0" class="form-control text-end" id="inp-recebido" name="valor_recebido" placeholder="0,00">
                </div>
                <div class="rt-tile">
                  <div class="label">TROCO</div>
                  <div id="lbl-troco" class="num money">R$ 0,00</div>
                </div>
              </div>
            </div>

            <div class="d-grid mt-3">
              <button type="submit" class="btn btn-lg btn-success" id="btn-finalizar" <?= $caixaAberto ? '' : 'disabled' ?>>
                <i class="bi bi-check2-circle me-1"></i> Finalizar Venda <span class="small kbd">F4</span>
              </button>
            </div>
            <div class="d-grid mt-2">
              <a href="../../dashboard.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
          </div>
        </div>
      </form>
    </div><!-- /right -->
  </div><!-- /stage -->

  <!-- scripts -->
  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
  <script>
    const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // helpers
    const fmt = v => (Number(v||0)).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    const el = sel => document.querySelector(sel);
    const tbody = document.querySelector('#tbl-itens tbody');
    let itens = [];
    const form = document.getElementById('form-venda');
    const temCaixa = form.getAttribute('data-caixa') === '1';

    // item total (tile)
    function itemTotal(){
      const q = parseFloat(el('#inp-qtd').value||'0');
      const u = parseFloat(el('#inp-preco').value||'0');
      return q*u;
    }
    function updateItemTotalTile(){
      el('#tile-item-total').textContent = 'R$ ' + fmt(itemTotal());
    }

    function totalGeral(){ let s=0; itens.forEach(i=>s+=i.qtd*i.unit); const d=parseFloat(el('#inp-desconto').value||'0'); return Math.max(s-d,0); }
    function recalc(){
      let subtotal=0,count=0; itens.forEach(i=>{subtotal+=i.qtd*i.unit;count++});
      const d=parseFloat(el('#inp-desconto').value||'0'), total=Math.max(subtotal-d,0);
      el('#tot-subtotal').textContent='R$ '+fmt(subtotal);
      el('#tot-geral').textContent='R$ '+fmt(total);
      el('#tot-itens').textContent=count;
      el('#visor-subtotal').textContent='R$ '+fmt(total);
      el('#desconto_hidden').value=(d||0).toFixed(2);
      el('#itens_json').value=JSON.stringify(itens);
      renderTable(); recalcTroco(); validateFinalizeButton();
    }
    function renderTable(){
      tbody.innerHTML = itens.map((i,idx)=>`
        <tr>
          <td>${idx+1}</td>
          <td>${escapeHtml(i.sku||'-')}</td>
          <td>${escapeHtml(i.nome)}</td>
          <td class="text-end"><input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end inp-qtd" data-idx="${idx}" value="${i.qtd.toFixed(3)}" ${temCaixa?'':'disabled'}></td>
          <td class="text-end"><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end inp-unit" data-idx="${idx}" value="${i.unit.toFixed(2)}" ${temCaixa?'':'disabled'}></td>
          <td class="text-end money">R$ ${fmt(i.qtd*i.unit)}</td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-idx="${idx}" ${temCaixa?'':'disabled'}><i class="bi bi-x"></i></button></td>
        </tr>`).join('');
    }
    function escapeHtml(s){ return String(s||'').replace(/[&<>"'`=\/]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }

    // busca
    const box=el('#box-suggest'), inpBusca=el('#inp-busca'), inpQtd=el('#inp-qtd'), inpPreco=el('#inp-preco');
    function filtra(q){ q=(q||'').trim().toLowerCase(); if(!q) return []; return PRODUTOS.filter(p=>(p.nome||'').toLowerCase().includes(q)||(p.sku||'').toLowerCase().includes(q)||(p.ean||'').toLowerCase().includes(q)||(p.marca||'').toLowerCase().includes(q)).slice(0,50); }
    function showSugestoes(lista){
      if(!lista.length){ box.style.display='none'; box.innerHTML=''; return; }
      box.innerHTML = lista.map(p=>`
        <div class="item" data-id="${p.id}" data-preco="${Number(p.preco_venda||0)}" data-nome="${escapeHtml(p.nome||'')}" data-sku="${escapeHtml(p.sku||'')}">
          <div><strong>${escapeHtml(p.nome||'-')}</strong></div>
          <div class="muted">${escapeHtml([p.marca,p.sku,p.ean].filter(Boolean).join(' • '))}</div>
          <div class="muted">R$ ${fmt(p.preco_venda||0)} ${p.unidade?(' / '+escapeHtml(p.unidade)) : ''}</div>
        </div>`).join('');
      box.style.display='block';
    }
    if(temCaixa){
      inpBusca.addEventListener('input',()=>showSugestoes(filtra(inpBusca.value)));
      document.addEventListener('click',(e)=>{ if(!e.target.closest('.pdv-busca')) box.style.display='none'; });
      box.addEventListener('click',(e)=>{
        const it=e.target.closest('.item'); if(!it) return;
        el('#visor-produto').textContent=it.getAttribute('data-nome')||'—';
        inpPreco.value=parseFloat(it.getAttribute('data-preco')||'0').toFixed(2);
        document.getElementById('inp-sku').value = it.getAttribute('data-sku')||'';
        updateItemTotalTile();
        box.style.display='none'; setTimeout(()=>inpQtd.focus(),10);
      });
    }

    // adicionar item
    let itensArr = []; // só para compor sku em render
    function addItemFromInputs(){
      if(!temCaixa) return;
      const nomeVisor=(el('#visor-produto').textContent||'').trim();
      const qtd=parseFloat(inpQtd.value||'0'), unit=parseFloat(inpPreco.value||'0'), termo=(inpBusca.value||'').trim();
      const sku=(document.getElementById('inp-sku').value||'').trim();
      let finalNome=nomeVisor;
      if(!finalNome || finalNome==='—'){
        const lista=filtra(termo);
        if(lista[0]){
          finalNome=lista[0].nome||'';
          if(!inpPreco.value || parseFloat(inpPreco.value||'0')<=0) inpPreco.value=Number(lista[0].preco_venda||0).toFixed(2);
        }else finalNome=termo||'Item';
      }
      if(qtd<=0 || unit<0) return;
      itens.push({nome:finalNome,qtd,unit,sku});
      inpBusca.value=''; el('#visor-produto').textContent='—'; inpQtd.value='1.000';
      document.getElementById('inp-sku').value='';
      updateItemTotalTile();
      recalc(); inpBusca.focus();
    }

    if(temCaixa){
      el('#btn-add').addEventListener('click', addItemFromInputs);
      el('#btn-clear').addEventListener('click', ()=>{
        itens=[]; recalc(); inpBusca.value=''; inpQtd.value='1.000'; inpPreco.value='0.00'; el('#visor-produto').textContent='—'; document.getElementById('inp-sku').value=''; updateItemTotalTile(); inpBusca.focus();
      });
      el('#btn-desc').addEventListener('click', ()=> el('#inp-desconto').select());
      inpQtd.addEventListener('input', updateItemTotalTile);
      inpPreco.addEventListener('input', updateItemTotalTile);
      tbody.addEventListener('input',(e)=>{
        if(e.target.matches('.inp-qtd')){ const i=+e.target.dataset.idx, v=parseFloat(e.target.value||'0'); if(itens[i]) itens[i].qtd=Math.max(v,0); recalc(); }
        else if(e.target.matches('.inp-unit')){ const i=+e.target.dataset.idx, v=parseFloat(e.target.value||'0'); if(itens[i]) itens[i].unit=Math.max(v,0); recalc(); }
      });
      tbody.addEventListener('click',(e)=>{
        if(e.target.closest('.btn-del')){ const i=+e.target.closest('.btn-del').dataset.idx; itens.splice(i,1); recalc(); }
      });
      el('#inp-desconto').addEventListener('input', recalc);
    }

    // pagamento
    const selFP=el('#forma_pagamento'), grpDin=el('#grp-dinheiro'), inpRec=el('#inp-recebido'), lblTroco=el('#lbl-troco');
    function toggleDinheiroUI(){ const isDin=selFP.value==='dinheiro'; grpDin.style.display=isDin?'block':'none'; if(isDin) setTimeout(()=>inpRec.focus(),50); validateFinalizeButton(); }
    function recalcTroco(){
      if(selFP.value!=='dinheiro'){ lblTroco.textContent='R$ 0,00'; lblTroco.className='num money'; return; }
      const t=totalGeral(), r=parseFloat(inpRec.value||'0'), tr=r-t;
      lblTroco.textContent='R$ '+fmt(tr); lblTroco.className='num money '+(tr>=0?'troco-ok':'troco-neg'); validateFinalizeButton();
    }
    function validateFinalizeButton(){
      const b=document.getElementById('btn-finalizar');
      if(!temCaixa || !itens.length){ b.disabled=true; return; }
      if(selFP.value==='dinheiro'){ const t=totalGeral(), r=parseFloat(inpRec.value||'0'); b.disabled=!(r>=t); } else b.disabled=false;
    }
    if(temCaixa){
      document.querySelectorAll('[data-pay]').forEach(btn=>{
        btn.addEventListener('click',()=>{ selFP.value=btn.getAttribute('data-pay'); toggleDinheiroUI(); });
      });
      selFP.addEventListener('change', toggleDinheiroUI);
      inpRec.addEventListener('input', recalcTroco);
    }

    // atalhos
    if(temCaixa){
      document.addEventListener('keydown',(e)=>{
        if(e.key==='F2'){ e.preventDefault(); el('#inp-qtd').select(); }
        if(e.key==='F4'){ e.preventDefault(); const b=document.getElementById('btn-finalizar'); if(!b.disabled){ form.requestSubmit ? form.requestSubmit(b) : b.click(); } }
      });
      // Enter para adicionar quando está no campo de busca
      el('#inp-busca').addEventListener('keydown', (e)=>{
        if(e.key==='Enter'){ e.preventDefault(); addItemFromInputs(); }
      });
    }

    // start
    recalc(); toggleDinheiroUI(); updateItemTotalTile();
  </script>
</body>
</html>
