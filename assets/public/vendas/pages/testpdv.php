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
    :root{ --input-lg-h:48px; }

    /* Visor */
    .pdv-visor{
      background:#002f8c; color:#f0f0f0; border-radius:14px; padding:16px 20px;
      font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      display:flex; align-items:center; justify-content:space-between;
      box-shadow:inset 0 0 0 1px #111827, 0 10px 28px rgba(0,0,0,.15);
    }
    .pdv-visor .linha1{ font-size:1rem; opacity:.9 }
    .pdv-visor .linha2{ font-size:2.2rem; font-weight:700; letter-spacing:.5px }
    .pdv-visor .right{ text-align:right }
    /* Nome do produto com ajuste dinâmico */
    #visor-produto{ transition:font-size .12s ease }
    .vp-medium{ font-size:1.8rem !important }
    .vp-small{ font-size:1.4rem !important }

    .pdv-busca{ position:relative }
    .pdv-suggest{
      position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #e5e7eb; border-top:0;
      max-height:320px; overflow:auto; z-index:1060; display:none; border-bottom-left-radius:10px; border-bottom-right-radius:10px;
    }
    .pdv-suggest .item{ padding:.55rem .75rem; cursor:pointer }
    .pdv-suggest .item:hover{ background:#f3f4f6 }
    .pdv-suggest .muted{ color:#6b7280; font-size:.85em }

    .itens-table td,.itens-table th{ vertical-align:middle }
    .totais-card{ border:1px dashed #c7c9d1; border-radius:16px; background:#f9fafb }

    .kbtn{ min-width:84px }
    .kbd{ background:#0b1220; color:#cbd5e1; padding:.15rem .45rem; border-radius:.35rem; font-size:.85rem; border:1px solid #1f2937 }

    .pay-btn{ height:var(--input-lg-h); font-size:1.05rem }
    .pay-btn.active{ box-shadow:0 0 0 3px rgba(37,99,235,.25) inset; border-color:#2563eb !important }

    .money{ font-variant-numeric:tabular-nums }
    .troco-ok{ color:#16a34a } .troco-neg{ color:#dc2626 }

    .form-label{ margin-bottom:.35rem }
    .form-control-lg{ height:var(--input-lg-h); line-height:calc(var(--input-lg-h) - 2px) }
    .input-group-text{ height:var(--input-lg-h); display:flex; align-items:center }
    .btn.kbtn{ height:var(--input-lg-h); display:flex; align-items:center }
    .pdv-help{ min-height:22px }

    @media (max-width:1199px){ .pdv-visor .linha2{ font-size:1.8rem } }

    /* ===== Toast melhorado ===== */
    .sale-toast{z-index:2000;min-width:360px;border:0;border-radius:14px;overflow:hidden;box-shadow:0 16px 40px rgba(0,0,0,.18)}
    .sale-head{display:flex;align-items:center;gap:10px;padding:12px 14px;font-weight:800;letter-spacing:.02rem;color:#fff}
    .sale-head.ok{background:linear-gradient(135deg,#16a34a,#0ea5e9)}
    .sale-head.err{background:linear-gradient(135deg,#ef4444,#f59e0b)}
    .sale-icon{width:34px;height:34px;border-radius:999px;display:grid;place-items:center;background:rgba(255,255,255,.18);box-shadow:inset 0 0 0 2px rgba(255,255,255,.18)}
    .sale-body{background:#fff;padding:10px 12px}
    .sale-actions{display:flex;gap:8px;justify-content:flex-end;padding:8px 12px;background:#f8fafc;border-top:1px solid #eef2f7}
    .sale-actions .btn{border-radius:10px}
    .sale-progress{height:3px}
    .sale-progress .progress-bar{transition:width .2s linear}
    @media (max-width:440px){ .sale-toast{min-width:300px} }
    @media (prefers-reduced-motion:reduce){ .sale-progress .progress-bar,.sale-head,.sale-body,.sale-actions{transition:none} }
  </style>
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$menuAtivo = 'vendas-rapida';
include '../../layouts/sidebar.php';
?>

<main class="main-content">
  <div class="position-relative iq-banner">
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand"><h4 class="logo-title">Mundo Pets</h4></a>
        <div class="ms-auto d-none d-lg-flex align-items-center gap-3">
          <span class="text-muted small">Atalhos:
            <span class="kbd">Enter</span> Adicionar •
            <span class="kbd">F2</span> Quantidade •
            <span class="kbd">F3</span> Desconto •
            <span class="kbd">F6</span> Recebido •
            <span class="kbd">F4</span> Finalizar
          </span>
        </div>
      </div>
    </nav>

    <!-- ===== TOAST Melhorado ===== -->
    <?php if ($ok || $err): ?>
      <div id="toastMsg" class="toast sale-toast show position-fixed top-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="sale-head <?= $ok ? 'ok' : 'err' ?>">
          <div class="sale-icon"><i class="bi <?= $ok ? 'bi-check-lg' : 'bi-x-lg' ?> fs-5 text-white"></i></div>
          <div class="flex-grow-1"><?= $ok ? 'Venda concluída!' : 'Ops, algo deu errado' ?></div>
          <button id="toastClose" type="button" class="btn btn-sm btn-light border-0" data-bs-dismiss="toast" aria-label="Fechar" style="--bs-btn-padding-y:.2rem;--bs-btn-padding-x:.45rem;border-radius:8px;">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="sale-body">
          <div class="fw-semibold text-dark">
            <?= htmlspecialchars($msg ?: ($ok ? 'Venda registrada com sucesso.' : 'Falha ao registrar venda.'), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
        <div class="sale-actions">
          <?php if ($ok && $vendaId > 0): ?>
            <a id="btnDanfe" href="./danfe_nfce.php?venda_id=<?= (int)$vendaId ?>" class="btn btn-success btn-sm">
              <i class="bi bi-printer me-1"></i> Ver DANFE
            </a>
            <a id="btnNova" href="./vendaRapida.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-plus-lg me-1"></i> Nova venda
            </a>
          <?php else: ?>
            <button type="button" class="btn btn-warning btn-sm" data-bs-dismiss="toast">
              <i class="bi bi-arrow-counterclockwise me-1"></i> Tentar novamente
            </button>
          <?php endif; ?>
        </div>
        <div class="progress sale-progress">
          <div id="toastProgress" class="progress-bar <?= $ok ? 'bg-success' : 'bg-danger' ?>" style="width:100%"></div>
        </div>
      </div>
      <script>
        (function(){
          const toastEl = document.getElementById("toastMsg");
          const progress = document.getElementById("toastProgress");
          if(!toastEl) return;
          const base = 1600, extra = Math.min(1800, (<?= json_encode(mb_strlen($msg ?? '')) ?> || 0) * 40);
          const DURATION = base + extra;
          let toast;
          if (window.bootstrap?.Toast) {
            toast = new bootstrap.Toast(toastEl, { delay: DURATION, autohide: true }); toast.show();
          } else { setTimeout(() => toastEl.remove(), DURATION); }
          let width=100, stepMs=20, step=100*stepMs/DURATION;
          const itv=setInterval(()=>{ width=Math.max(0,width-step); if(progress) progress.style.width=width+"%"; if(width<=0) clearInterval(itv); }, stepMs);
          // limpar params da URL
          try{ const url=new URL(window.location.href); ['ok','err','msg','venda_id'].forEach(p=>url.searchParams.delete(p)); history.replaceState({},'',url.toString()); }catch(_){}
          // redireciona pro DANFE ao fechar (se sucesso e tiver vendaId)
          <?php if ($ok && $vendaId > 0): ?>
            const goDanfe = () => { if (!window.__skipDanfeRedirect) window.location.href = './danfe_nfce.php?venda_id=<?= (int)$vendaId ?>'; };
            toastEl.addEventListener('hidden.bs.toast', goDanfe);
            document.getElementById('btnDanfe')?.addEventListener('click', ()=> window.__skipDanfeRedirect = true);
            document.getElementById('btnNova')?.addEventListener('click', ()=> window.__skipDanfeRedirect = true);
          <?php endif; ?>
          // ESC fecha
          document.addEventListener('keydown', e => { if(e.key==='Escape'){ try{ bootstrap.Toast.getOrCreateInstance(toastEl).hide(); }catch(_){ toastEl.remove(); } }});
        })();
      </script>
    <?php endif; ?>

    <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-md-8">
            <h1 class="">Venda Rápida</h1>
            <p>Fluxo de PDV para balcão — leitor de código, busca por nome/SKU/EAN, e finalização rápida.</p>
            <?php if (!$caixaAberto): ?>
              <div style="font-weight:900;color:#f73232;">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Nenhum caixa aberto no momento.
                <a href="./caixaAbrir.php" class="alert-link text-white">Clique aqui</a> para abrir ou entrar em um caixa.
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
            <a href="./orcamentos.php" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> Orçamentos</a>
            <a href="./caixaAbrir.php" class="btn btn-outline-primary"><i class="bi bi-cash-stack"></i> Caixa</a>
          </div>
        </div>
      </div>
      <div class="iq-header-img">
        <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
      </div>
    </div>
  </div>

  <div class="container-fluid content-inner mt-n3 py-0">
    <form method="post" action="../actions/vendaRapidaSalvar.php" id="form-venda" data-caixa="<?= $caixaAberto ? '1' : '0' ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="itens_json" id="itens_json">
      <input type="hidden" name="desconto" id="desconto_hidden" value="0.00">

      <div class="row g-3">
        <!-- ESQUERDA -->
        <div class="col-xxl-8">
          <!-- Visor PDV: último produto + valor do último item -->
          <div class="pdv-visor mb-3">
            <div>
              <div class="linha1">Último produto</div>
              <div class="linha2" id="visor-produto">—</div>
            </div>
            <div class="right">
              <div class="linha1">Valor do item</div>
              <div class="linha2 money" id="visor-ultimo-valor">R$ 0,00</div>
            </div>
          </div>

          <!-- Busca rápida -->
          <div class="card">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-lg-7">
                  <label class="form-label">Código / Nome / SKU / EAN</label>
                  <div class="pdv-busca">
                    <input type="text" class="form-control form-control-lg" id="inp-busca" placeholder="Digite o Nome do Produto" autocomplete="off" <?= $caixaAberto ? '' : 'disabled' ?>>
                    <div class="pdv-suggest" id="box-suggest"></div>
                  </div>
                  <div class="form-text pdv-help">Use leitor de código de barras ou digite e pressione <span class="kbd">Enter</span>.</div>
                </div>

                <div class="col-lg-2">
                  <label class="form-label">Qtd</label>
                  <input type="number" class="form-control form-control-lg text-end" id="inp-qtd" step="0.001" min="0.001" value="1.000" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <div class="pdv-help"></div>
                </div>

                <div class="col-lg-3">
                  <label class="form-label">Vlr. Unit (R$)</label>
                  <input type="number" class="form-control form-control-lg text-end" id="inp-preco" step="0.01" min="0" value="0.00" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <div class="pdv-help"></div>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button type="button" class="btn btn-primary kbtn" id="btn-add" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i>&nbsp;Adicionar <span class="small ms-1 kbd">Enter</span></button>
                  <button type="button" class="btn btn-outline-secondary kbtn" id="btn-qtd" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-123"></i>&nbsp;Qtd <span class="small ms-1 kbd">F2</span></button>
                  <button type="button" class="btn btn-outline-secondary kbtn" id="btn-desc" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-percent"></i>&nbsp;Desconto <span class="small ms-1 kbd">F3</span></button>
                  <button type="button" class="btn btn-outline-danger kbtn" id="btn-clear" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-trash3"></i>&nbsp;Limpar</button>
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

        <!-- DIREITA -->
        <div class="col-xxl-4">
          <div class="card totais-card">
            <div class="card-body">
              <div class="d-flex justify-content-between"><span class="text-muted">Itens</span><strong id="tot-itens">0</strong></div>
              <div class="d-flex justify-content-between mt-2"><span class="text-muted">Subtotal</span><strong class="money" id="tot-subtotal">R$ 0,00</strong></div>
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
            <div class="card-header"><h5 class="mb-0">Pagamento</h5></div>
            <div class="card-body">
              <div class="row g-2">
                <div class="col-6"><button type="button" class="btn btn-success w-100 pay-btn active" data-pay="dinheiro" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Dinheiro</button></div>
                <div class="col-6"><button type="button" class="btn btn-primary w-100 pay-btn" data-pay="pix" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-upc-scan me-1"></i> PIX</button></div>
                <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 pay-btn" data-pay="debito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card-2-back me-1"></i> Débito</button></div>
                <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 pay-btn" data-pay="credito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card me-1"></i> Crédito</button></div>
              </div>

              <div class="mt-3">
                <label class="form-label">Forma de Pagamento</label>
                <select name="forma_pagamento" id="forma_pagamento" class="form-select" <?= $caixaAberto ? '' : 'disabled' ?>>
                  <option value="dinheiro" selected>Dinheiro</option>
                  <option value="pix">PIX</option>
                  <option value="debito">Débito</option>
                  <option value="credito">Crédito</option>
                </select>
              </div>

              <div id="grp-dinheiro" class="mt-3" style="display:block;">
                <label class="form-label">Valor Recebido (Dinheiro)</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input type="number" step="0.01" min="0" class="form-control text-end" id="inp-recebido" name="valor_recebido" placeholder="0,00">
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

  // helpers
  const fmt = v => (Number(v || 0)).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
  const el = sel => document.querySelector(sel);
  const tbody = document.querySelector('#tbl-itens tbody');
  let itens = [];
  const form = document.getElementById('form-venda');
  const temCaixa = form.getAttribute('data-caixa') === '1';
  let forma = 'dinheiro'; // estado corrente dos botões

  function totalGeral(){
    let subtotal=0; itens.forEach(i=> subtotal += i.qtd*i.unit);
    const d = parseFloat(el('#inp-desconto').value||'0');
    return Math.max(subtotal - d, 0);
  }

  function recalc(){
    let subtotal=0, count=0; itens.forEach(i=>{ subtotal += i.qtd*i.unit; count++; });
    const d = parseFloat(el('#inp-desconto').value||'0'), total=Math.max(subtotal-d,0);
    el('#tot-subtotal').textContent = 'R$ '+fmt(subtotal);
    el('#tot-geral').textContent    = 'R$ '+fmt(total);
    el('#tot-itens').textContent    = count;
    el('#desconto_hidden').value    = (d||0).toFixed(2);
    el('#itens_json').value         = JSON.stringify(itens);
    renderTable();
    recalcTroco();
    validateFinalizeButton();
  }

  function renderTable(){
    tbody.innerHTML = itens.map((i, idx)=>`
      <tr>
        <td>${escapeHtml(i.nome)}</td>
        <td class="text-end"><input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end inp-qtd" data-idx="${idx}" value="${i.qtd.toFixed(3)}" ${temCaixa?'':'disabled'}></td>
        <td class="text-end"><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end inp-unit" data-idx="${idx}" value="${i.unit.toFixed(2)}" ${temCaixa?'':'disabled'}></td>
        <td class="text-end money">R$ ${fmt(i.qtd*i.unit)}</td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-idx="${idx}" ${temCaixa?'':'disabled'}><i class="bi bi-x"></i></button></td>
      </tr>`).join('');
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"'`=\/]/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
    }[c]));
  }

  // ==== Visor: último produto + valor do item ====
  function updateVisorUltimo(){
    const vp = el('#visor-produto');
    const vv = el('#visor-ultimo-valor');
    const u  = itens[itens.length-1] || null;
    const nome = u ? (u.nome||'Item') : '—';
    const valor = u ? (u.qtd*u.unit) : 0;
    if (vp) {
      vp.textContent = nome;
      // ajuste de tamanho se muito grande
      vp.classList.remove('vp-medium','vp-small');
      if (nome.length > 64) vp.classList.add('vp-small');
      else if (nome.length > 28) vp.classList.add('vp-medium');
    }
    if (vv) vv.textContent = 'R$ '+fmt(valor);
  }

  // ==== Busca / Sugestões ====
  const box = el('#box-suggest'),
        inpBusca = el('#inp-busca'),
        inpQtd   = el('#inp-qtd'),
        inpPreco = el('#inp-preco');

  function filtra(q){
    q=(q||'').trim().toLowerCase(); if(!q) return [];
    return PRODUTOS.filter(p =>
      (p.nome||'').toLowerCase().includes(q) ||
      (p.sku||'').toLowerCase().includes(q)  ||
      (p.ean||'').toLowerCase().includes(q)  ||
      (p.marca||'').toLowerCase().includes(q)
    ).slice(0,50);
  }

  function showSugestoes(lista){
    if(!lista.length){ box.style.display='none'; box.innerHTML=''; return; }
    box.innerHTML = lista.map(p=>`
      <div class="item" data-id="${p.id}" data-preco="${Number(p.preco_venda||0)}" data-nome="${escapeHtml(p.nome||'')}" data-sku="${escapeHtml(p.sku||'')}" data-ean="${escapeHtml(p.ean||'')}">
        <div><strong>${escapeHtml(p.nome||'-')}</strong></div>
        <div class="muted">${escapeHtml([p.marca,p.sku,p.ean].filter(Boolean).join(' • '))}</div>
        <div class="muted">R$ ${fmt(p.preco_venda||0)} ${p.unidade?(' / '+escapeHtml(p.unidade)) : ''}</div>
      </div>`).join('');
    box.style.display='block';
  }

  // Auto-adicionar: match exato por EAN/SKU ou quando só sobrar 1 sugestão
  let autoAddTimer = null;
  function tryAutoAddFromBusca(q){
    if(!temCaixa) return;
    q=(q||'').trim(); if(!q) return;

    // 1) match exato EAN ou SKU
    const exact = PRODUTOS.find(p => (p.ean && String(p.ean)===q) || (p.sku && String(p.sku).toLowerCase()===q.toLowerCase()));
    if (exact){
      inpPreco.value = Number(exact.preco_venda||0).toFixed(2);
      addItem(exact.nome, Number(inpQtd.value||'1'), Number(inpPreco.value||0), exact.sku||'');
      box.style.display='none';
      return;
    }
    // 2) restou uma sugestão com termo razoável
    const lista = filtra(q);
    if (lista.length===1 && q.length>=4){
      clearTimeout(autoAddTimer);
      autoAddTimer = setTimeout(()=>{
        inpPreco.value = Number(lista[0].preco_venda||0).toFixed(2);
        addItem(lista[0].nome, Number(inpQtd.value||'1'), Number(inpPreco.value||0), lista[0].sku||'');
        box.style.display='none';
      }, 180);
    }
  }

  if (temCaixa){
    inpBusca.addEventListener('input', ()=>{
      const q = inpBusca.value;
      showSugestoes(filtra(q));
      tryAutoAddFromBusca(q);
    });
    document.addEventListener('click',(e)=>{ if(!e.target.closest('.pdv-busca')) box.style.display='none'; });
    box.addEventListener('click',(e)=>{
      const it = e.target.closest('.item'); if(!it) return;
      const nome  = it.getAttribute('data-nome') || 'Item';
      const preco = parseFloat(it.getAttribute('data-preco')||'0');
      inpPreco.value = preco.toFixed(2);
      // adiciona imediatamente
      addItem(nome, Number(inpQtd.value||'1'), preco, it.getAttribute('data-sku')||'');
      box.style.display='none';
    });
  }

  // ==== Adicionar item ====
  function addItem(nome, qtd, unit, sku=''){
    if (!temCaixa) return;
    if (typeof nome!=='string' || !nome.trim()){
      // fallback pelo termo e sugestões
      const termo = (inpBusca.value||'').trim();
      const lista = filtra(termo);
      nome = lista[0]?.nome || termo || 'Item';
      if ((!inpPreco.value || parseFloat(inpPreco.value||'0')<=0) && lista[0]) unit = Number(lista[0].preco_venda||0);
    }
    qtd  = Number(qtd||0);
    unit = Number(unit||0);
    if (qtd<=0 || unit<0) return;

    itens.push({nome, qtd, unit, sku});
    // limpar campos
    inpBusca.value=''; inpQtd.value='1.000'; inpPreco.value='0.00';
    updateVisorUltimo(); // mostra último produto + valor do item
    recalc();
    inpBusca.focus();
  }

  function addItemFromInputs(){
    addItem((el('#visor-produto').textContent||'').trim(), parseFloat(inpQtd.value||'0'), parseFloat(inpPreco.value||'0'));
  }

  if (temCaixa){
    el('#btn-add').addEventListener('click', addItemFromInputs);
    el('#btn-clear').addEventListener('click', ()=>{
      itens=[]; recalc(); inpBusca.value=''; inpQtd.value='1.000'; inpPreco.value='0.00'; updateVisorUltimo();
      inpBusca.focus();
    });
    el('#btn-qtd').addEventListener('click', ()=> inpQtd.select());
    el('#btn-desc').addEventListener('click', ()=> el('#inp-desconto').select());

    tbody.addEventListener('input',(e)=>{
      if (e.target.matches('.inp-qtd')){
        const i=+e.target.dataset.idx, v=parseFloat(e.target.value||'0'); if(itens[i]) itens[i].qtd=Math.max(v,0); recalc(); updateVisorUltimo();
      } else if (e.target.matches('.inp-unit')){
        const i=+e.target.dataset.idx, v=parseFloat(e.target.value||'0'); if(itens[i]) itens[i].unit=Math.max(v,0); recalc(); updateVisorUltimo();
      }
    });
    tbody.addEventListener('click',(e)=>{
      if (e.target.closest('.btn-del')){
        const i=+e.target.closest('.btn-del').dataset.idx; itens.splice(i,1); recalc(); updateVisorUltimo();
      }
    });
    el('#inp-desconto').addEventListener('input', recalc);

    // atalhos
    form.addEventListener('keydown',(e)=>{
      if (e.key==='Enter' && document.activeElement===inpBusca){ e.preventDefault(); addItemFromInputs(); }
    });
    document.addEventListener('keydown',(e)=>{
      if (e.key==='F2'){ e.preventDefault(); inpQtd.select(); }
      if (e.key==='F3'){ e.preventDefault(); el('#inp-desconto').focus(); el('#inp-desconto').select(); }
      if (e.key==='F6'){ e.preventDefault(); setFormaPagamento('dinheiro'); el('#inp-recebido')?.focus(); el('#inp-recebido')?.select(); }
      if (e.key==='F4'){ e.preventDefault(); if(!el('#btn-finalizar').disabled){ form.requestSubmit ? form.requestSubmit(el('#btn-finalizar')) : el('#btn-finalizar').click(); } }
    });
  }

  // ==== Pagamento ====
  const selFP  = el('#forma_pagamento'),
        grpDin = el('#grp-dinheiro'),
        inpRec = el('#inp-recebido'),
        lblTroco = el('#lbl-troco'),
        payBtns = document.querySelectorAll('.pay-btn');

  function toggleDinheiroUI(){
    const isDin = selFP.value==='dinheiro';
    grpDin.style.display = isDin ? 'block' : 'none';
    validateFinalizeButton();
  }
  function recalcTroco(){
    if (selFP.value!=='dinheiro'){ lblTroco.textContent='R$ 0,00'; lblTroco.className='money'; return; }
    const t = totalGeral(), r = parseFloat(inpRec.value||'0'), tr = r - t;
    lblTroco.textContent = 'R$ '+fmt(tr);
    lblTroco.className = 'money ' + (tr>=0 ? 'troco-ok' : 'troco-neg');
    validateFinalizeButton();
  }
  function validateFinalizeButton(){
    const b = el('#btn-finalizar');
    if (!temCaixa || !itens.length){ b.disabled=true; return; }
    if (selFP.value==='dinheiro'){
      const t = totalGeral(), r = parseFloat(inpRec.value||'0');
      b.disabled = !(r >= t);
    } else b.disabled=false;
  }
  function updatePayActive(){
    payBtns.forEach(btn=>{
      const on = btn.dataset.pay === forma;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }
  function setFormaPagamento(v){
    forma = v; selFP.value = v; toggleDinheiroUI(); updatePayActive();
  }

  if (temCaixa){
    document.querySelectorAll('[data-pay]').forEach(btn=>{
      btn.addEventListener('click', ()=> setFormaPagamento(btn.getAttribute('data-pay')));
    });
    selFP.addEventListener('change', ()=> setFormaPagamento(selFP.value));
    inpRec.addEventListener('input', recalcTroco);
  }

  // start
  recalc();
  updateVisorUltimo();  // inicia visor
  setFormaPagamento('dinheiro'); // seleciona default + ativa UI
</script>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const m = document.getElementById('modalCaixaObrigatorio');
    if (m) new bootstrap.Modal(m).show();
  });
</script>
</body>
</html>
