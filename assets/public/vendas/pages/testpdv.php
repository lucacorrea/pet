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
    /* === SUA PALETA === */
    :root{
      --bg:#0b0f18; --panel:#121824; --text:#e6e9ef; --muted:#a0a8b8;
      --primary:#3b82f6; --primary-800:#1d4ed8; --border:#243042; --focus:#93c5fd;
      --input-lg-h:56px;           /* mais alto como no exemplo */
      --tile-h:92px;               /* altura dos blocos/tiles */
      --right-col:420px;           /* largura da coluna direita */
    }

    /* Tela cheia / layout base */
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Segoe UI,Roboto,Arial;overflow:hidden}
    .pdv-app{height:100vh;width:100vw;display:grid;grid-template-rows:auto 1fr}

    /* Topo compacto */
    .pdv-top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;background:linear-gradient(0deg, rgba(18,24,36,.9), rgba(18,24,36,1));border-bottom:1px solid var(--border)}
    .pdv-brand{display:flex;align-items:center;gap:10px;font-weight:800}
    .pdv-brand i{color:var(--primary)}
    .hint,.status{color:var(--muted);font-size:.9rem}
    .status strong{color:var(--text)}

    /* Palco 2 colunas (igual imagem) */
    .pdv-stage{display:grid;gap:14px;grid-template-columns:1fr var(--right-col);padding:14px;height:100%;box-sizing:border-box}
    @media (max-width:1440px){ :root{ --right-col:380px } }
    @media (max-width:1366px){ :root{ --right-col:360px; --input-lg-h:52px; --tile-h:86px } }
    @media (max-width:1100px){ .pdv-stage{grid-template-columns:1fr} }

    .pdv-left{display:grid;gap:14px;grid-template-rows:auto auto 1fr;min-height:0}
    .pdv-right{min-height:0;display:flex;flex-direction:column;gap:14px}
    .pdv-right-scroll{flex:1 1 auto;overflow:auto;display:flex;flex-direction:column;gap:14px}

    /* Cartões */
    .card-pdv{background:var(--panel);border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 26px rgba(0,0,0,.35)}
    .card-pdv .card-header{padding:12px 16px;border-bottom:1px solid var(--border)}
    .card-pdv .card-body{padding:16px}

    /* VISOR (total grande) */
    .pdv-visor{background:radial-gradient(120% 140% at 0% 0%, rgba(59,130,246,.25) 0%, rgba(59,130,246,0) 55%), var(--panel);border:1px solid var(--border);border-radius:16px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between}
    .pdv-visor .linha1{font-size:.95rem;color:var(--muted)}
    .pdv-visor .linha2{font-size:2.25rem;font-weight:800;letter-spacing:.3px}
    .pdv-visor .right{text-align:right}
    @media (max-width:1366px){ .pdv-visor .linha2{font-size:1.9rem} }

    /* === BLOCO DE CAMPOS (igual ao visual do exemplo) === */
    .grid-campos{display:grid;grid-template-columns:1.4fr .55fr .75fr;gap:12px;align-items:stretch}
    @media (max-width:1366px){ .grid-campos{grid-template-columns:1.2fr .6fr .7fr} }
    @media (max-width:1100px){ .grid-campos{grid-template-columns:1fr 1fr} .col-span-2{grid-column:1/-1} }

    .tile{background:#0e1421;border:1px solid var(--border);border-radius:14px;padding:10px 12px;display:flex;flex-direction:column;gap:6px;height:var(--tile-h)}
    .tile .tile-label{color:var(--muted);font-size:.85rem}
    .tile .tile-value{display:flex;gap:8px;align-items:center;height:100%}
    .tile .tile-input{flex:1 1 auto}
    .tile input{height:calc(var(--tile-h) - 38px);font-size:1.15rem;background:#0e1421;border:1px solid var(--border);color:var(--text);border-radius:10px;padding:0 12px}
    .tile input:focus{border-color:var(--focus);box-shadow:0 0 0 .2rem rgba(147,197,253,.15)}
    .tile .money-prefix{padding:0 10px;border:1px solid var(--border);border-radius:10px;background:#0e1421;color:var(--muted);height:calc(var(--tile-h) - 38px);display:flex;align-items:center}

    /* Sugestões (dark) */
    .pdv-busca{position:relative}
    .pdv-suggest{position:absolute;left:0;right:0;top:100%;background:var(--panel);color:var(--text);border:1px solid var(--border);border-top:0;max-height:320px;overflow:auto;z-index:1060;display:none;border-bottom-left-radius:12px;border-bottom-right-radius:12px;box-shadow:0 10px 22px rgba(0,0,0,.45)}
    .pdv-suggest .item{padding:.6rem .8rem;cursor:pointer}
    .pdv-suggest .item:hover{background:rgba(59,130,246,.08)}
    .pdv-suggest .muted{color:var(--muted);font-size:.85em}

    /* Barra de ações (botões altos) */
    .acoes{display:flex;flex-wrap:wrap;gap:10px}
    .kbtn{height:var(--input-lg-h);min-width:110px;display:flex;align-items:center;justify-content:center;border-radius:12px}
    .btn-primary{background:var(--primary);border-color:var(--primary)}
    .btn-primary:hover{background:var(--primary-800);border-color:var(--primary-800)}
    .btn-outline-light{color:var(--text);border-color:var(--border)}
    .btn-outline-light:hover{background:rgba(255,255,255,.06)}

    /* Itens com rolagem */
    .pdv-itens-card{min-height:0;display:flex;flex-direction:column}
    .pdv-itens-wrap{flex:1 1 auto;overflow:auto}
    .table{color:var(--text)}
    .table thead th{border-bottom:1px solid var(--border)}
    .table-striped>tbody>tr:nth-of-type(odd)>*{background-color:rgba(255,255,255,.02)}
    .table>:not(caption)>*>*{border-bottom:1px solid var(--border)}

    /* Totais (tile grande) */
    .totais-card{background:linear-gradient(180deg, rgba(59,130,246,.06), rgba(59,130,246,.02));border:1px dashed rgba(147,197,253,.35);border-radius:16px}
    .money{font-variant-numeric:tabular-nums}
    .total-num{font-size:2.1rem;font-weight:800}
    @media (max-width:1366px){ .total-num{font-size:1.8rem} }

    /* Pagamento (recebido/troco estilo tile) */
    .pay-btn{height:var(--input-lg-h);font-size:1.05rem;border-radius:12px}
    .btn-success{background:#16a34a;border-color:#16a34a}
    .btn-success:hover{background:#15803d;border-color:#15803d}
    .troco-ok{color:#22c55e} .troco-neg{color:#f87171}
    .text-muted{color:var(--muted)!important}
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

  <div class="pdv-app">
    <!-- Topo -->
    <div class="pdv-top">
      <div class="pdv-brand">
        <i class="bi bi-basket2-fill fs-5"></i>
        <span><?= htmlspecialchars($empresaNome ?: 'PDV', ENT_QUOTES, 'UTF-8') ?> — Venda Rápida</span>
      </div>
      <div class="status d-none d-md-block">
        <?php if (!$caixaAberto): ?>
          <i class="bi bi-exclamation-triangle me-1" style="color:#f87171"></i> Nenhum caixa aberto —
          <a href="./caixaAbrir.php" class="link-light text-decoration-underline">Abrir caixa</a>
        <?php else: ?>
          <i class="bi bi-cash-coin me-1" style="color:#22c55e"></i>
          Caixa <strong>#<?= (int)$caixaAberto['id'] ?></strong> •
          <?= htmlspecialchars($caixaAberto['tipo'], ENT_QUOTES, 'UTF-8') ?> •
          <?= htmlspecialchars($caixaAberto['terminal'], ENT_QUOTES, 'UTF-8') ?> •
          desde <?= htmlspecialchars($caixaAberto['quando'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </div>
      <div class="hint d-none d-sm-block">
        Atalhos: <span class="kbd">Enter</span> Adicionar • <span class="kbd">F2</span> Quantidade • <span class="kbd">F4</span> Finalizar
      </div>
    </div>

    <!-- Palco -->
    <div class="pdv-stage">
      <!-- ESQUERDA -->
      <div class="pdv-left">
        <!-- Visor -->
        <div class="pdv-visor">
          <div>
            <div class="linha1">Produto</div>
            <div class="linha2" id="visor-produto">—</div>
          </div>
          <div class="right">
            <div class="linha1">Total</div>
            <div class="linha2 money" id="visor-subtotal">R$ 0,00</div>
          </div>
        </div>

        <!-- Campos em blocos/tiles -->
        <div class="card-pdv">
          <div class="card-body">
            <div class="grid-campos">
              <!-- BUSCA: ocupa 2 colunas em telas pequenas -->
              <div class="tile col-span-2">
                <div class="tile-label">Código / Nome / SKU / EAN</div>
                <div class="tile-value pdv-busca">
                  <input type="text" id="inp-busca" class="tile-input" placeholder="Digite o nome ou use o leitor" autocomplete="off" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <div class="pdv-suggest" id="box-suggest"></div>
                </div>
              </div>

              <!-- QTD -->
              <div class="tile">
                <div class="tile-label">Quantidade</div>
                <div class="tile-value">
                  <input type="number" id="inp-qtd" class="tile-input text-end" step="0.001" min="0.001" value="1.000" <?= $caixaAberto ? '' : 'disabled' ?>>
                </div>
              </div>

              <!-- PREÇO UNIT -->
              <div class="tile">
                <div class="tile-label">Vlr. Unit (R$)</div>
                <div class="tile-value">
                  <span class="money-prefix">R$</span>
                  <input type="number" id="inp-preco" class="tile-input text-end" step="0.01" min="0" value="0.00" <?= $caixaAberto ? '' : 'disabled' ?>>
                </div>
              </div>
            </div>

            <!-- Ações -->
            <div class="acoes mt-3">
              <button type="button" class="btn btn-primary kbtn" id="btn-add" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i>&nbsp;Adicionar <span class="small ms-1 kbd">Enter</span></button>
              <button type="button" class="btn btn-outline-light kbtn" id="btn-qtd" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-123"></i>&nbsp;Qtd <span class="small ms-1 kbd">F2</span></button>
              <button type="button" class="btn btn-outline-light kbtn" id="btn-desc" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-percent"></i>&nbsp;Desconto</button>
              <button type="button" class="btn btn-outline-danger kbtn" id="btn-clear" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-trash3"></i>&nbsp;Limpar</button>
            </div>
          </div>
        </div>

        <!-- LISTA DE ITENS -->
        <form method="post" action="../actions/vendaRapidaSalvar.php" id="form-venda" class="pdv-itens-card card-pdv" data-caixa="<?= $caixaAberto ? '1' : '0' ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="itens_json" id="itens_json">
          <input type="hidden" name="desconto" id="desconto_hidden" value="0.00">

          <div class="pdv-itens-wrap">
            <div class="card-pdv" style="background:transparent;border:none;box-shadow:none;">
              <div class="card-body pt-0">
                <div class="table-responsive">
                  <table class="table table-striped itens-table align-middle mb-0" id="tbl-itens">
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
                        <td colspan="5" class="text-muted small">Clique no valor para editar; use <i class="bi bi-x"></i> para remover.</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- DIREITA -->
      <div class="pdv-right">
        <div class="pdv-right-scroll">
          <!-- TOTAIS -->
          <div class="card totais-card">
            <div class="card-body">
              <div class="d-flex justify-content-between"><span class="text-muted">Itens</span><strong id="tot-itens">0</strong></div>
              <div class="d-flex justify-content-between mt-2"><span class="text-muted">Subtotal</span><strong class="money" id="tot-subtotal">R$ 0,00</strong></div>
              <div class="d-flex justify-content-between mt-2 align-items-center">
                <span class="text-muted me-2">Desconto</span>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" id="inp-desconto" value="0.00" style="width:130px" <?= $caixaAberto ? '' : 'disabled' ?>>
              </div>
              <hr>
              <div class="d-flex justify-content-between align-items-center">
                <span class="fs-5">TOTAL</span>
                <span class="total-num money" id="tot-geral">R$ 0,00</span>
              </div>
            </div>
          </div>

          <!-- PAGAMENTO -->
          <div class="card-pdv">
            <div class="card-header">
              <h5 class="mb-0">Pagamento</h5>
            </div>
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

              <!-- Recebido/Troco como tiles grandes -->
              <div id="grp-dinheiro" class="mt-3" style="display:none;">
                <div class="row g-2">
                  <div class="col-12">
                    <div class="tile">
                      <div class="tile-label">Valor Recebido</div>
                      <div class="tile-value">
                        <span class="money-prefix">R$</span>
                        <input type="number" step="0.01" min="0" class="tile-input text-end" id="inp-recebido" name="valor_recebido" placeholder="0,00">
                      </div>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="tile" style="align-items:flex-start">
                      <div class="tile-label">Troco</div>
                      <div class="tile-value">
                        <div id="lbl-troco" class="money total-num">R$ 0,00</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-grid mt-3">
                <button type="submit" form="form-venda" class="btn btn-lg btn-success" id="btn-finalizar" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <i class="bi bi-check2-circle me-1"></i> Finalizar Venda <span class="small kbd">F4</span>
                </button>
              </div>
              <div class="d-grid mt-2">
                <a href="../../dashboard.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Voltar</a>
              </div>
            </div>
          </div>
        </div><!-- /scroll -->
      </div><!-- /right -->
    </div><!-- /stage -->
  </div><!-- /app -->

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
        <div class="item" data-id="${p.id}" data-preco="${Number(p.preco_venda||0)}" data-nome="${escapeHtml(p.nome||'')}">
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
        box.style.display='none'; setTimeout(()=>inpQtd.focus(),10);
      });
    }

    // adicionar item
    function addItemFromInputs(){
      if(!temCaixa) return;
      const nomeVisor=(el('#visor-produto').textContent||'').trim();
      const qtd=parseFloat(inpQtd.value||'0'), unit=parseFloat(inpPreco.value||'0'), termo=(inpBusca.value||'').trim();
      let finalNome=nomeVisor;
      if(!finalNome || finalNome==='—'){
        const lista=filtra(termo);
        if(lista[0]){
          finalNome=lista[0].nome||'';
          if(!inpPreco.value || parseFloat(inpPreco.value||'0')<=0) inpPreco.value=Number(lista[0].preco_venda||0).toFixed(2);
        }else finalNome=termo||'Item';
      }
      if(qtd<=0 || unit<0) return;
      itens.push({nome:finalNome,qtd,unit});
      inpBusca.value=''; el('#visor-produto').textContent='—'; inpQtd.value='1.000';
      recalc(); inpBusca.focus();
    }

    if(temCaixa){
      el('#btn-add').addEventListener('click', addItemFromInputs);
      el('#btn-clear').addEventListener('click', ()=>{ itens=[]; recalc(); inpBusca.value=''; inpQtd.value='1.000'; inpPreco.value='0.00'; el('#visor-produto').textContent='—'; inpBusca.focus(); });
      el('#btn-qtd').addEventListener('click', ()=> inpQtd.select());
      el('#btn-desc').addEventListener('click', ()=> el('#inp-desconto').select());
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
      if(selFP.value!=='dinheiro'){ lblTroco.textContent='R$ 0,00'; lblTroco.className='money total-num'; return; }
      const t=totalGeral(), r=parseFloat(inpRec.value||'0'), tr=r-t;
      lblTroco.textContent='R$ '+fmt(tr); lblTroco.className='money total-num '+(tr>=0?'troco-ok':'troco-neg'); validateFinalizeButton();
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
      form.addEventListener('keydown',(e)=>{
        if(e.key==='Enter'){ e.preventDefault(); if(document.activeElement===el('#inp-busca')) addItemFromInputs(); }
      });
      document.addEventListener('keydown',(e)=>{
        if(e.key==='F2'){ e.preventDefault(); el('#inp-qtd').select(); }
        if(e.key==='F4'){ e.preventDefault(); const b=document.getElementById('btn-finalizar'); if(!b.disabled){ form.requestSubmit ? form.requestSubmit(b) : b.click(); } }
      });
    }

    recalc(); toggleDinheiroUI();
  </script>
</body>
</html>
