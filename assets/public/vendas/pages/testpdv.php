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
$empresaCnpjFmt = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $empresaCnpj);

if (empty($_SESSION['csrf_venda_rapida'])) {
  $_SESSION['csrf_venda_rapida'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_venda_rapida'];

$ok       = isset($_GET['ok'])  ? (int)$_GET['ok']  : 0;
$err      = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$msg      = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$vendaId  = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;

// Caixa aberto?
$caixaAberto = null;
try {
  $stCx = $pdo->prepare("SELECT id FROM caixas_peca WHERE empresa_cnpj=:c AND status='aberto' ORDER BY aberto_em DESC LIMIT 1");
  $stCx->execute([':c' => $empresaCnpj]);
  $caixaAberto = $stCx->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $caixaAberto = null;
}

// Produtos p/ sugestão
$produtos = [];
try {
  $st = $pdo->prepare("SELECT id,nome,sku,ean,marca,unidade,preco_venda FROM produtos_peca WHERE empresa_cnpj=:c AND ativo=1 ORDER BY nome LIMIT 2000");
  $st->execute([':c' => $empresaCnpj]);
  $produtos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $produtos = [];
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($empresaNome ?: 'PDV', ENT_QUOTES, 'UTF-8') ?> — PDV</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    /* ===== Tema claro (tons do print) ===== */
    :root {
      /* Base */
      --bg: #f4f7fb;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --shadow: 0 8px 16px rgba(15, 23, 42, .06);
      --ticket-edge: #e2e8f0;

      /* Azul do topo */
      --brand: #0f3fa7;
      --brand2: #2563eb;

      /* Cards */
      --panel: #ffffff;
      --panel2: #ffffff;

      /* Colunas laterais */
      --left: 360px;
      --right: 360px;
    }

    @media (max-width:1440px) {
      :root {
        --left: 350px;
        --right: 340px
      }
    }

    @media (max-width:1100px) {
      :root {
        --left: 1fr;
        --right: 1fr
      }
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: Inter, system-ui, Segoe UI, Roboto, Arial;
      overflow: hidden
    }

    /* Topo */
    .topbar {
      height: 64px;
      display: grid;
      grid-template-columns: 1fr;
      background: linear-gradient(135deg, var(--brand2), var(--brand));
      border-bottom: 1px solid rgba(0, 0, 0, .05);
      box-shadow: var(--shadow);
      color: #fff;
    }

    .top-left {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 0 18px
    }

    .top-left .brand {
      font-weight: 800;
      letter-spacing: .15rem;
      text-transform: uppercase;
      font-size: 1.05rem;
    }

    .caixa-status {
      background: rgba(255, 255, 255, .15);
      border: 1px solid rgba(255, 255, 255, .25);
      border-radius: 10px;
      padding: 6px 10px;
      text-transform: uppercase;
      font-weight: 900;
      letter-spacing: .12rem
    }

    /* Área principal */
    .stage {
      height: calc(100vh - 64px);
      display: grid;
      grid-template-columns: var(--left) 1fr var(--right);
      gap: 16px;
      padding: 16px;
      position: relative;
      z-index: 1;
    }

    @media (max-width:1100px) {
      .stage {
        grid-template-columns: 1fr;
        overflow: auto
      }
    }

    /* Cards genéricos */
    .card-pdv {
      background: linear-gradient(180deg, var(--panel2), var(--panel));
      border: 1px solid var(--border);
      border-radius: 14px;
      color: var(--text);
      box-shadow: var(--shadow);
    }

    .card-pdv .card-header {
      padding: .7rem 1rem;
      border-bottom: 1px solid var(--border);
      font-weight: 700;
      color: #0f1e4a
    }

    .card-pdv .card-body {
      padding: 14px
    }

    /* Coluna esquerda */
    .left {
      display: grid;
      grid-template-rows: auto auto 1fr;
      gap: 16px;
      min-height: 0
    }

    .tile {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 10px;
      box-shadow: var(--shadow)
    }

    .tile .lbl {
      font-size: .8rem;
      text-transform: uppercase;
      color: #334155;
      letter-spacing: .06rem
    }

    .tile .value {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 6px
    }

    /* Input “leitor” */
    .nfce-input {
      position: relative;
      width: 100%
    }

    .nfce-input .inp {
      width: 100%;
      height: 54px;
      padding: 0 12px 0 46px;
      background: #fff;
      color: #0f172a;
      border: 2px dashed #c7d2fe;
      border-radius: 12px;
      font-weight: 700;
    }

    .nfce-input .inp::placeholder {
      color: #94a3b8
    }

    .nfce-input .inp:focus {
      outline: none;
      border-color: #93c5fd;
      box-shadow: 0 0 0 4px rgba(147, 197, 253, .25)
    }

    .nfce-input .inp-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.25rem;
      color: #1e3a8a;
      opacity: .85;
    }

    /* Sugestões */
    #sug {
      top: 58px !important;
      display: none;
      max-height: 300px;
      overflow: auto;
      border: 2px dashed #e2e8f0 !important;
      z-index: 1000
    }

    #sug .sug-item {
      cursor: pointer;
      border-bottom: 1px dotted #e5e7eb
    }

    #sug .sug-item:last-child {
      border-bottom: 0
    }

    #sug .sug-item:hover {
      background: #f8fafc
    }

    #sug .price {
      font-family: ui-monospace, Menlo, Consolas, monospace
    }

    /* Inputs base */
    .inp {
      background: #fff;
      color: #0f172a;
      border: 2px solid #cbd5e1;
      border-radius: 10px;
      height: 50px;
      padding: 0 10px;
      font-weight: 600;
      width: 100%
    }

    .money-wrap {
      display: flex;
      align-items: center
    }

    .money-prefix {
      background: #fff;
      border: 2px solid #cbd5e1;
      border-right: 0;
      border-radius: 10px 0 0 10px;
      height: 50px;
      display: flex;
      align-items: center;
      padding: 0 12px;
      color: #0f172a;
      font-weight: 700
    }

    .money-input {
      border-left: 0;
      border-radius: 0 10px 10px 0
    }

    .money {
      font-variant-numeric: tabular-nums
    }

    /* Centro (visor + lista + subtotal) */
    .center {
      display: grid;
      grid-template-rows: auto 1fr auto;
      gap: 12px;
      min-height: 0
    }

    /* Visor azul */
    .visor {
      background: linear-gradient(135deg, #0f3fa7, #0b2f85);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, .15);
      border-radius: 12px;
      padding: 12px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow)
    }

    .visor .big {
      font-size: 1.95rem;
      font-weight: 900
    }

    /* Lista/Prévia */
    .list-card {
      min-height: 0;
      display: flex;
      flex-direction: column;
    }

    .list-card .card-body {
      flex: 1;
      min-height: 0;
      display: flex;
    }

    .ticket-wrap {
      flex: 1;
      min-height: 0;
      display: flex;
      align-items: stretch;
      justify-content: center
    }

    .ticket {
      width: 100%;
      max-width: none;
      height: 100%;
      background: #fff;
      color: #0f172a;
      border: 1px dashed var(--ticket-edge);
      border-radius: 16px;
      padding: 14px 14px 18px;
      font-family: ui-monospace, Menlo, Consolas, "Liberation Mono", monospace;
      font-size: 12.5px;
      line-height: 1.28;
      display: flex;
      flex-direction: column;
    }

    .t-line {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 6px;
      padding: 6px 0;
      border-bottom: 1px dotted #dde3ee
    }

    .t-line:last-child {
      border-bottom: 0
    }

    .t-desc {
      font-weight: 700;
      color: #0b1323
    }

    .t-meta {
      color: #6b7280
    }

    .t-val {
      font-weight: 800;
      text-align: right
    }

    .t-list {
      flex: 1;
      min-height: 0;
      overflow: auto;
      padding-right: 4px
    }

    /* Subtotal (card branco) */
    .bottom {
      display: grid;
      grid-template-rows: auto;
      gap: 12px;
      min-height: 0
    }

    .subgrid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px
    }

    .box-num {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow)
    }

    .box-num .lab {
      color: #334155
    }

    .box-num .num {
      font-size: 2.2rem;
      font-weight: 900;
      color: #0f1e4a
    }

    /* Direita (cards claros) */
    .right {
      display: grid;
      grid-template-rows: auto auto 1fr;
      gap: 16px;
      min-height: 0
    }

    .totalzao {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px;
      box-shadow: var(--shadow)
    }

    /* Botões no tema claro (corrige outline-light) */
    .btn-outline-light {
      color: #334155;
      border-color: #e2e8f0;
      background: #fff
    }

    .btn-outline-light:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
      color: #111827
    }

    /* Pagamento/caixas */
    .pay .btn {
      height: 52px;
      border-radius: 12px
    }

    .rt {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px;
      box-shadow: var(--shadow)
    }

    .rt .n {
      font-size: 1.8rem;
      font-weight: 900
    }

    .ok {
      color: #16a34a
    }

    .neg {
      color: #ef4444
    }

    /* Atalhos */
    .shortcuts {
      font-size: .92rem;
      color: var(--muted);
      line-height: 1.2
    }

    .kbd {
      background: #0f172a;
      color: #fff;
      border: 1px solid #0f172a;
      border-radius: .35rem;
      padding: .12rem .38rem
    }

    /* “Faixa” antiga desativada no tema claro */
    .side-band {
      display: none;
    }

    /* Container interno flex (não mexe no .topbar existente) */
    .top-inner {
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 0 16px;
    }

    /* Marca / ícone */
    .top-icon {
      font-size: 1.25rem;
      opacity: .95
    }

    .top-left {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .top-left .brand {
      font-weight: 800;
      letter-spacing: .12rem;
      text-transform: uppercase;
      font-size: 1.02rem
    }

    /* Atalhos em chips */
    .top-shortcuts {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255, 255, 255, .12);
      border: 1px solid rgba(255, 255, 255, .25);
      border-radius: 12px;
      padding: 6px 10px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .08);
    }

    .top-shortcuts .sep {
      opacity: .8
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .kbd {
      background: #0f172a;
      color: #fff;
      border: 1px solid #0f172a;
      border-radius: .35rem;
      padding: .12rem .40rem;
      font-weight: 700;
      line-height: 1;
    }

    .chip-lab {
      font-weight: 700;
      letter-spacing: .02rem
    }

    /* Status do caixa + relógio */
    .top-right {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .caixa-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 900;
      letter-spacing: .08rem;
      background: rgba(255, 255, 255, .15);
      border: 1px solid rgba(255, 255, 255, .25);
      text-transform: uppercase;
    }

    .caixa-pill .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(0, 0, 0, .08) inset;
    }

    .caixa-pill.open .dot {
      background: #22c55e
    }

    /* verde */
    .caixa-pill.closed .dot {
      background: #ef4444
    }

    /* vermelho */

    .clock {
      min-width: 72px;
      text-align: right;
      font-variant-numeric: tabular-nums;
      background: rgba(255, 255, 255, .10);
      border: 1px solid rgba(255, 255, 255, .25);
      border-radius: 10px;
      padding: 6px 10px;
      font-weight: 700
    }
  </style>
</head>

<body>

  <div class="topbar">
    <div class="top-inner">
      <!-- Esquerda: marca -->
      <div class="top-left">
        <i class="bi bi-shop-window top-icon"></i>
        <div class="brand">Mundo Pets – PDV</div>
      </div>

      <!-- Centro: atalhos (chips). Some em telas pequenas -->
      <div class="top-shortcuts">
        <span class="chip"><span class="kbd">F2</span><span class="chip-lab">Quantidade</span></span>
        <span class="sep">•</span>
        <span class="chip"><span class="kbd">F3</span><span class="chip-lab">Desconto</span></span>
        <span class="sep">•</span>
        <span class="chip"><span class="kbd">Enter</span><span class="chip-lab">Adicionar</span></span>
        <span class="sep">•</span>
        <span class="chip"><span class="kbd">F4</span><span class="chip-lab">Finalizar</span></span>
        <span class="sep">•</span>
        <span class="chip"><span class="kbd">F6</span><span class="chip-lab">Recebido</span></span>
      </div>

      <!-- Direita: status do caixa + relógio -->
      <div class="top-right">
        <div class="caixa-pill <?= $caixaAberto ? 'open' : 'closed' ?>" title="Status do caixa" data-bs-toggle="tooltip">
          <span class="dot"></span>
          CAIXA <?= $caixaAberto ? 'ABERTO' : 'FECHADO' ?>
        </div>

        <div class="clock-wrap" role="timer" aria-live="polite" title="Horário atual" data-bs-toggle="tooltip">
          <i class="bi bi-clock"></i>
          <span id="clock">--:--</span>
        </div>
         <div class="caixa-pill text-white">
          <a href=".././../dashboard.php"
            id="btn-logout"
            class="btn top-logout"
            title="Sair (Alt+S)"
            data-bs-toggle="tooltip"
            aria-label="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline ms-1">Voltar</span>
          </a>
      </div>

    </div>
  </div>

  <div class="stage">
    <!-- ESQUERDA -->
    <div class="left">
      <div class="card-pdv">
        <div class="card-body">
          <div class="tile">
            <div class="lbl d-flex align-items-center gap-2"><span>CÓDIGO DE BARRAS</span></div>
            <div class="value nfce-input">
              <i class="bi bi-upc-scan inp-icon"></i>
              <input class="inp" id="inp-busca" placeholder="Passe o leitor ou digite" autocomplete="off" <?= $caixaAberto ? '' : 'disabled' ?>>
              <div id="sug" class="position-absolute w-100 bg-white text-dark rounded-2 shadow"></div>
            </div>
          </div>

          <div class="tile">
            <div class="lbl">CÓDIGO</div>
            <div class="value"><input class="inp" id="inp-sku" placeholder="SKU / interno (opcional)"></div>
          </div>

          <div class="tile">
            <div class="lbl">VALOR UNITÁRIO</div>
            <div class="value money-wrap">
              <span class="money-prefix">R$</span>
              <input type="number" id="inp-preco" class="inp money-input text-end" step="0.01" min="0" value="0.00" <?= $caixaAberto ? '' : 'disabled' ?>>
            </div>
          </div>´

          <div class="tile">
            <div class="lbl">TOTAL DO ITEM</div>
            <div class="value"><strong id="tile-item-total" class="money fs-4">R$ 0,00</strong></div>
          </div>

          <div class="tile">
            <div class="lbl">QUANTIDADE</div>
            <div class="value"><input id="inp-qtd" type="number" step="0.001" min="0.001" value="1.000" class="inp text-end" <?= $caixaAberto ? '' : 'disabled' ?>></div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button id="btn-add" class="btn btn-primary btn-sm" type="button" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i> Adicionar</button>
            <button id="btn-clear" class="btn btn-outline-light btn-sm" type="button" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-trash3"></i> Limpar</button>
          </div>

          <!-- Atalhos atualizados -->

        </div>
      </div>
    </div>

    <!-- CENTRO -->
    <div class="center">
      <div class="visor">
        <div>
          <div class="opacity-75">Produto</div>
          <div id="visor-produto" class="big">—</div>
        </div>
        <div class="text-end">
          <div class="opacity-75">Total</div>
          <div id="visor-subtotal" class="big money">R$ 0,00</div>
        </div>
      </div>

      <!-- LISTA ÚNICA -->
      <div class="card-pdv list-card" style="min-height:0;">
        <div class="card-header">LISTA DE ITENS</div>
        <div class="card-body ticket-wrap">
          <div class="ticket" id="ticket"><!-- render via JS --></div>
        </div>
      </div>

      <!-- SUBTOTAL -->
      <div class="bottom">
        <div class="subgrid">
          <div class="box-num">
            <div class="lab">SUBTOTAL</div>
            <div id="tot-subtotal" class="num money">R$ 0,00</div>
          </div>
        </div>
      </div>
    </div>

    <!-- DIREITA -->
    <div class="right">
      <!-- Total geral para referência visual -->
      <div class="box-num">
        <div class="lab">TOTAL</div>
        <div id="tot-geral" class="num money">R$ 0,00</div>
      </div>

      <div class="box-num">
        <div class="lab me-2">DESCONTO</div>
        <div class="money-wrap" style="width:210px">
          <span class="money-prefix">R$</span>
          <input id="inp-desc" type="number" step="0.01" min="0" value="0.00" class="inp money-input text-end">
        </div>
      </div>

      <div class="card-pdv pay" style="min-height:0">
        <div class="card-header">Pagamento</div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-6"><button class="btn btn-success w-100" type="button" data-pay="dinheiro" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Dinheiro</button></div>
            <div class="col-6"><button class="btn btn-primary w-100" type="button" data-pay="pix" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-upc-scan me-1"></i> PIX</button></div>
            <div class="col-6"><button class="btn btn-outline-light w-100" type="button" data-pay="debito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card-2-back me-1"></i> Débito</button></div>
            <div class="col-6"><button class="btn btn-outline-light w-100" type="button" data-pay="credito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card me-1"></i> Crédito</button></div>
          </div>

          <div id="grp-din" class="mt-3" style="display:none">
            <div class="row g-2">
              <div class="col-12">
                <div class="rt">
                  <div class="mb-1 fw-semibold">TOTAL RECEBIDO</div>
                  <div class="money-wrap">
                    <span class="money-prefix">R$</span>
                    <input id="inp-recebido" name="valor_recebido" type="number" step="0.01" min="0" class="inp money-input text-end" placeholder="0,00">
                  </div>
                </div>
              </div>
              <div class="col-12">
                <div class="rt d-flex flex-column">
                  <div class="fw-semibold">TROCO</div>
                  <div id="lbl-troco" class="n money mt-auto">R$ 0,00</div>
                </div>
              </div>
            </div>
          </div>

          <form method="post" action="../actions/vendaRapidaSalvar.php" id="form-venda" data-caixa="<?= $caixaAberto ? '1' : '0' ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="itens_json" id="itens_json">
            <input type="hidden" name="desconto" id="desconto_hidden" value="0.00">
            <div class="mt-3 d-grid">
              <button id="btn-finalizar" class="btn btn-success btn-lg" type="submit" <?= $caixaAberto ? '' : 'disabled' ?>>
                <i class="bi bi-check2-circle me-1"></i> Finalizar Venda <span class="ms-1 small kbd">F4</span>
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const el = s => document.querySelector(s);
    let itens = [];
    const temCaixa = (document.getElementById('form-venda')?.dataset.caixa === '1');

    const fmt = v => (Number(v || 0)).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    function itemTotal() {
      return (parseFloat(el('#inp-qtd').value || '0') * parseFloat(el('#inp-preco').value || '0'));
    }

    function upItemTile() {
      const t = el('#tile-item-total');
      if (t) t.textContent = 'R$ ' + fmt(itemTotal());
    }

    function totalAtual() {
      let sub = 0;
      itens.forEach(i => sub += i.qtd * i.unit);
      const desc = parseFloat(el('#inp-desc').value || '0');
      return Math.max(sub - (desc || 0), 0);
    }

    function recalc() {
      let sub = 0;
      itens.forEach(i => sub += i.qtd * i.unit);
      const desc = parseFloat(el('#inp-desc').value || '0');
      const total = Math.max(sub - desc, 0);

      el('#tot-subtotal') && (el('#tot-subtotal').textContent = 'R$ ' + fmt(sub));
      el('#tot-geral') && (el('#tot-geral').textContent = 'R$ ' + fmt(total));
      el('#visor-subtotal') && (el('#visor-subtotal').textContent = 'R$ ' + fmt(total));
      el('#tot-itens') && (el('#tot-itens').textContent = itens.length);
      el('#desconto_hidden') && (el('#desconto_hidden').value = (desc || 0).toFixed(2));
      el('#itens_json') && (el('#itens_json').value = JSON.stringify(itens));

      troco();
      validateBtn();
      renderTicket();
    }

    function esc(s) {
      return String(s || '').replace(/[&<>"'`=\/]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        '\'': '&#39;',
        '/': '&#x2F;',
        '`': '&#x60',
        '=': '&#x3D;'
      } [c]));
    }

    const sug = el('#sug'),
      busca = el('#inp-busca'),
      qtd = el('#inp-qtd'),
      preco = el('#inp-preco');

    function filtra(q) {
      q = (q || '').trim().toLowerCase();
      if (!q) return [];
      return PRODUTOS.filter(p => (p.nome || '').toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q) || (p.ean || '').toLowerCase().includes(q) || (p.marca || '').toLowerCase().includes(q)).slice(0, 50);
    }

    function showSug(list) {
      if (!list.length) {
        sug.style.display = 'none';
        sug.innerHTML = '';
        return;
      }
      sug.innerHTML = list.map(p => `<div class="p-2 sug-item" data-preco="${Number(p.preco_venda||0)}" data-nome="${esc(p.nome||'')}" data-sku="${esc(p.sku||'')}" data-ean="${esc(p.ean||'')}">
    <div class="d-flex justify-content-between">
      <strong>${esc(p.nome||'-')}</strong>
      <span class="price">R$ ${fmt(p.preco_venda||0)}</span>
    </div>
    <div class="text-secondary small">${esc([p.marca,p.sku,p.ean].filter(Boolean).join(' • '))}${p.unidade?(' • '+esc(p.unidade)):''}</div>
  </div>`).join('');
      sug.style.display = 'block';
    }

    /* ==== Seleção/Scanner: auto-adicionar ==== */
    function setProdutoSelecionado(data) {
      // data: {nome, preco_venda, sku}
      el('#visor-produto') && (el('#visor-produto').textContent = data.nome || 'Item');
      preco && (preco.value = parseFloat(data.preco_venda || 0).toFixed(2));
      el('#inp-sku') && (el('#inp-sku').value = data.sku || '');
      upItemTile();
    }

    let autoAddTimer = null;

    function tryAutoAddFromBusca(q) {
      if (!temCaixa) return;
      q = (q || '').trim();
      if (!q) return;

      // 1) Match exato por EAN ou SKU
      const exact = PRODUTOS.find(p => (p.ean && String(p.ean) === q) || (p.sku && String(p.sku).toLowerCase() === q.toLowerCase()));
      if (exact) {
        setProdutoSelecionado({
          nome: exact.nome,
          preco_venda: exact.preco_venda,
          sku: exact.sku
        });
        sug.style.display = 'none';
        // pequeno delay para garantir valores setados
        setTimeout(() => addItem(), 0);
        return;
      }

      // 2) Se restar só 1 sugestão e já tem um termo razoável, auto-adiciona após leve pausa
      const list = filtra(q);
      if (list.length === 1 && q.length >= 4) {
        clearTimeout(autoAddTimer);
        autoAddTimer = setTimeout(() => {
          setProdutoSelecionado({
            nome: list[0].nome,
            preco_venda: list[0].preco_venda,
            sku: list[0].sku
          });
          sug.style.display = 'none';
          addItem();
        }, 180);
      }
    }

    if (temCaixa) {
      busca && busca.addEventListener('input', () => {
        const q = busca.value;
        showSug(filtra(q));
        tryAutoAddFromBusca(q);
      });
      document.addEventListener('click', (e) => {
        if (!e.target.closest('#sug') && !e.target.closest('#inp-busca')) sug.style.display = 'none';
      });

      // Clique na sugestão => seleciona e já adiciona automaticamente
      sug && sug.addEventListener('click', (e) => {
        const it = e.target.closest('.sug-item');
        if (!it) return;
        const data = {
          nome: it.dataset.nome || 'Item',
          preco_venda: parseFloat(it.dataset.preco || '0'),
          sku: it.dataset.sku || ''
        };
        setProdutoSelecionado(data);
        sug.style.display = 'none';
        // adiciona imediatamente
        setTimeout(() => addItem(), 0);
      });
    }

    function addItem() {
      if (!temCaixa) return;
      const nome = (el('#visor-produto')?.textContent || '').trim() || (busca?.value || '').trim() || 'Item';
      const q = parseFloat(qtd?.value || '0'),
        u = parseFloat(preco?.value || '0'),
        sku = (el('#inp-sku')?.value || '').trim();
      if (q <= 0 || u < 0) return;
      itens.push({
        nome,
        qtd: q,
        unit: u,
        sku
      });

      // Limpa campos para próxima leitura
      if (busca) busca.value = '';
      if (el('#inp-sku')) el('#inp-sku').value = '';
      if (el('#visor-produto')) el('#visor-produto').textContent = '—';
      if (qtd) qtd.value = '1.000';
      if (preco) preco.value = '0.00';
      upItemTile();
      recalc();
      busca && busca.focus();
    }

    if (temCaixa) {
      document.getElementById('btn-add')?.addEventListener('click', addItem);
      document.getElementById('btn-clear')?.addEventListener('click', () => {
        itens = [];
        recalc();
      });
      qtd && qtd.addEventListener('input', upItemTile);
      preco && preco.addEventListener('input', upItemTile);

      document.addEventListener('keydown', (e) => {
        // F2: Alterar quantidade
        if (e.key === 'F2') {
          e.preventDefault();
          qtd && (qtd.select(), qtd.scrollIntoView({
            block: 'center'
          }));
        }

        // F3: Ir para DESCONTO
        if (e.key === 'F3') {
          e.preventDefault();
          const d = el('#inp-desc');
          if (d) {
            d.focus();
            d.select();
            d.scrollIntoView({
              block: 'center'
            });
          }
        }

        // F6: Ir para TOTAL RECEBIDO (ativa Dinheiro, mostra grupo e foca)
        if (e.key === 'F6') {
          e.preventDefault();
          forma = 'dinheiro';
          toggleDin();
          const r = el('#inp-recebido');
          if (r) {
            r.focus();
            r.select();
            r.scrollIntoView({
              block: 'center'
            });
          }
        }

        // F4: Finalizar
        if (e.key === 'F4') {
          e.preventDefault();
          const f = document.getElementById('form-venda');
          const b = document.getElementById('btn-finalizar');
          if (b && !b.disabled) {
            f?.requestSubmit ? f.requestSubmit(b) : b.click();
          }
        }
      });

      // Enter no campo de busca ainda adiciona (compat com leitores que mandam Enter)
      busca && busca.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          addItem();
        }
      });
    }

    /* Pagamento / troco */
    const selBtns = document.querySelectorAll('[data-pay]'),
      grpDin = el('#grp-din'),
      inpRec = el('#inp-recebido'),
      lblTroco = el('#lbl-troco');
    let forma = 'dinheiro';
    selBtns.forEach(b => b.addEventListener('click', () => {
      forma = b.dataset.pay;
      toggleDin();
    }));

    function toggleDin() {
      const isDin = forma === 'dinheiro';
      if (grpDin) grpDin.style.display = isDin ? 'block' : 'none';
      validateBtn();
    }

    function troco() {
      if (forma !== 'dinheiro') {
        if (lblTroco) {
          lblTroco.textContent = 'R$ 0,00';
          lblTroco.className = 'n money';
        }
        return;
      }
      const tr = (parseFloat(inpRec?.value || '0') - totalAtual());
      if (lblTroco) {
        lblTroco.textContent = 'R$ ' + fmt(tr);
        lblTroco.className = 'n money ' + (tr >= 0 ? 'ok' : 'neg');
      }
    }

    function validateBtn() {
      const b = document.getElementById('btn-finalizar');
      if (!b) return;
      if (!temCaixa || itens.length === 0) {
        b.disabled = true;
        return;
      }
      if (forma === 'dinheiro') {
        const t = totalAtual();
        b.disabled = (parseFloat(inpRec?.value || '0') < Math.max(t, 0));
      } else b.disabled = false;
    }
    el('#inp-desc')?.addEventListener('input', () => {
      recalc();
    });
    inpRec && inpRec.addEventListener('input', () => {
      troco();
      validateBtn();
    });

    function syncHidden() {
      const d = el('#desconto_hidden');
      const i = el('#inp-desc');
      if (d && i) d.value = (parseFloat(i.value || '0') || 0).toFixed(2);
    }
    el('#inp-desc')?.addEventListener('input', syncHidden);

    /* Render da lista (somente itens) */
    function renderTicket() {
      const t = el('#ticket');
      if (!t) return;
      const linhas = itens.map(i => `
    <div class="t-line">
      <div>
        <div class="t-desc">${esc(i.nome)}</div>
        <div class="t-meta">${i.qtd.toFixed(3)} × ${fmt(i.unit)}</div>
      </div>
      <div class="t-val">R$ ${fmt(i.qtd*i.unit)}</div>
    </div>
  `).join('') || `<div class="text-muted">Sem itens</div>`;
      t.innerHTML = `<div class="t-list">${linhas}</div>`;
    }

    upItemTile();
    recalc();
    toggleDin();
    renderTicket();
  </script>
</body>

</html>