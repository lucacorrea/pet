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

// Somente para formatação (não será usado na prévia)
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
  $stCx->execute([':c'=>$empresaCnpj]);
  $caixaAberto = $stCx->fetch(PDO::FETCH_ASSOC) ?: null;
} catch(Throwable $e){ $caixaAberto=null; }

// Produtos p/ sugestão
$produtos=[];
try{
  $st=$pdo->prepare("SELECT id,nome,sku,ean,marca,unidade,preco_venda FROM produtos_peca WHERE empresa_cnpj=:c AND ativo=1 ORDER BY nome LIMIT 2000");
  $st->execute([':c'=>$empresaCnpj]); $produtos=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}catch(Throwable $e){ $produtos=[]; }
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
/* ===== Cores do print / tema ===== */
:root{
  --bg:#0b1130;
  --brand:#0d2f53;
  --brand2:#123b6a;
  --panel:#173e6d;
  --panel2:#1d4f86;
  --border:#2d5a93;
  --text:#eaf2ff;
  --muted:#c6d6f6;
  --left:360px; --right:360px;
  --ticket-edge:#ced7e6;
}
@media (max-width:1440px){ :root{ --left:350px; --right:340px } }
@media (max-width:1100px){ :root{ --left:1fr; --right:1fr } }

*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Segoe UI,Roboto,Arial;overflow:hidden}

/* Topo */
.topbar{
  height:64px; display:grid; grid-template-columns:1fr; gap:0;
  background:linear-gradient(180deg,var(--brand2),var(--brand));
  border-bottom:1px solid #0b1c33; box-shadow:0 8px 18px rgba(0,0,0,.45);
}
.top-left{display:flex;align-items:center;gap:14px;padding:0 18px}
.top-left .brand{font-weight:800; letter-spacing:.15rem; text-transform:uppercase; font-size:1.05rem;}
.caixa-status{
  background:#0f2f59;border:1px solid #2c538c;border-radius:10px;padding:6px 10px;
  text-align:center;text-transform:uppercase;font-weight:900;letter-spacing:.12rem
}

/* Área principal 3 colunas */
.stage{
  height:calc(100vh - 64px);
  display:grid; grid-template-columns:var(--left) 1fr var(--right); gap:16px; padding:16px;
}
@media (max-width:1100px){ .stage{grid-template-columns:1fr; overflow:auto} }

/* Cards */
.card-pdv{
  background:linear-gradient(180deg,var(--panel2),var(--panel));
  border:1px solid var(--border); border-radius:14px; color:var(--text);
  box-shadow:0 10px 26px rgba(0,0,0,.35);
}
.card-pdv .card-header{padding:.55rem .9rem;border-bottom:1px solid #2b5186;font-weight:700}
.card-pdv .card-body{padding:12px}

/* Coluna esquerda */
.left{display:grid;grid-template-rows:auto auto 1fr;gap:16px;min-height:0}
.tile{background:#0f2f59;border:1px solid #2c538c;border-radius:12px;padding:8px 10px;margin-bottom:10px}
.tile .lbl{font-size:.8rem;text-transform:uppercase;color:#d5e4ff;letter-spacing:.06rem}
.tile .value{display:flex;align-items:center;gap:8px;margin-top:6px}

/* ===== Input estilo NFC (PDV) ===== */
.nfce-input{position:relative;width:100%}
.nfce-input .inp{
  width:100%; height:58px; padding:0 12px 0 46px;
  background:#f8fbff; color:#0e1b2a;
  border:2px dashed #7fb2ff; border-radius:12px;
  font-weight:700; letter-spacing:.02rem;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.75);
}
.nfce-input .inp::placeholder{color:#7a8aa6}
.nfce-input .inp:focus{
  outline:none; border-color:#a7c6ff; box-shadow:0 0 0 4px rgba(127,178,255,.22);
}
.nfce-input .inp-icon{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  font-size:1.25rem; color:#315986; opacity:.9;
}

/* Inputs base */
.inp{
  background:#fff;color:#0e1b2a;border:2px solid #7fb2ff;border-radius:10px;height:50px;padding:0 10px;font-weight:600;width:100%
}
.money-wrap{display:flex;align-items:center}
.money-prefix{
  background:#fff;border:2px solid #7fb2ff;border-right:0;border-radius:10px 0 0 10px;height:50px;display:flex;align-items:center;padding:0 12px;color:#304562;font-weight:700
}
.money-input{border-left:0;border-radius:0 10px 10px 0}
.money{font-variant-numeric:tabular-nums}

/* Centro (lista) */
.center{display:grid;grid-template-rows:auto 1fr auto;gap:12px;min-height:0}
.visor{background:#123c6b;border:1px solid #2b5288;border-radius:12px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
.visor .big{font-size:1.95rem;font-weight:900}

/* LISTA: ocupa o 1fr e rola dentro */
.list-card{min-height:0; display:flex; flex-direction:column;}
.table-wrap{min-height:0; overflow:auto}
table{color:#eaf2ff}
#tbl{width:100%}
#tbl thead th{
  position:sticky; top:0;
  z-index:1;
  background:#0f2f59;border-bottom:1px solid #2b5288;
  font-size:.75rem; letter-spacing:.06rem; text-transform:uppercase;
}
.table>:not(caption)>*>*{border-bottom:1px dotted #2b5288}
.table-striped>tbody>tr:nth-of-type(odd)>*{background:#0b2a55}
#tbl .form-control-sm{
  background:transparent; color:var(--text);
  border:1px solid #3a5f92; border-radius:8px; height:34px;
}
#tbl .form-control-sm:focus{
  border-color:#7fb2ff; box-shadow:0 0 0 .2rem rgba(127,178,255,.15);
}

/* Subtotais + prévia embaixo */
.bottom{display:grid; grid-template-rows:auto auto; gap:12px; min-height:0}
.subgrid{display:grid;grid-template-columns:1fr 300px;gap:12px}
.box-num{background:#0f2f59;border:1px solid #2b5288;border-radius:12px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
.box-num .lab{opacity:.9}
.box-num .num{font-size:2.2rem;font-weight:900}

/* Direita */
.right{display:grid;grid-template-rows:auto 1fr;gap:16px;min-height:0}
.totalzao{background:linear-gradient(180deg,#215a9d33,#1a4d8b22);border:1px dashed #7fb2ff;border-radius:14px;padding:12px}
.pay .btn{height:52px;border-radius:12px}
.rt{background:#0f2f59;border:1px solid #2b5288;border-radius:12px;padding:12px}
.rt .n{font-size:1.8rem;font-weight:900}
.ok{color:#22c55e} .neg{color:#ff8c8c}

/* Atalhos */
.shortcuts{font-size:.92rem;color:var(--muted);line-height:1.2}
.kbd{background:#0f2140;border:1px solid #1f355c;border-radius:.35rem;padding:.12rem .38rem;color:#dbe7ff}

/* ===== Prévia de Cupom NFC-e (somente itens) ===== */
.ticket-wrap{display:flex;justify-content:center}
.ticket{
  width:340px; max-width:100%; margin:0 auto;
  background:#fff; color:#0f172a;
  border:1px dashed var(--ticket-edge); border-radius:16px;
  padding:14px 14px 18px;
  font-family:ui-monospace,Menlo,Consolas,"Liberation Mono",monospace;
  font-size:12.5px; line-height:1.28;
}
.t-line{ display:grid; grid-template-columns:1fr auto; gap:6px; padding:4px 0; border-bottom:1px dotted #ccd6e5 }
.t-line:last-child{border-bottom:0}
.t-desc{ font-weight:700; color:#0b1323 }
.t-meta{ color:#475569 }
.t-val{ font-weight:800; text-align:right }
.t-list{ max-height:280px; overflow:auto; padding-right:2px }

/* Faixa lateral (estética) */
.side-band{
  position:fixed;top:64px;right:0;width:420px;height:calc(100vh - 64px);
  background:linear-gradient(180deg,#2a3344 10%, #2a3344 90%);opacity:.45;pointer-events:none;
}
</style>
</head>
<body>

<div class="topbar">
  <div class="top-left">
    <div class="brand">Mundo Pets – PDV</div>
    <div class="caixa-status">CAIXA <?= $caixaAberto ? 'ABERTO' : 'FECHADO' ?></div>
  </div>
</div>

<div class="side-band"></div>

<div class="stage">
  <!-- ESQUERDA -->
  <div class="left">
    <div class="card-pdv">
      <div class="card-body">
        <div class="tile">
          <div class="lbl d-flex align-items-center gap-2">
            <span>CÓDIGO DE BARRAS</span>
          </div>
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
        </div>

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
        <div class="shortcuts mt-3">
          <div><span class="kbd">F2</span> – Alterar Qtd &nbsp; • &nbsp; <span class="kbd">Enter</span> – Adicionar &nbsp; • &nbsp; <span class="kbd">F4</span> – Finalizar</div>
        </div>
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

    <!-- LISTA DE PRODUTOS (OCUPA O 1fr E ROLA POR DENTRO) -->
    <div class="card-pdv list-card">
      <div class="card-header">LISTA DE ITENS</div>
      <div class="card-body table-wrap">
        <table id="tbl" class="table table-sm table-striped align-middle">
          <thead>
            <tr>
              <th style="width:56px">#</th>
              <th style="width:120px">SKU</th>
              <th>Produto</th>
              <th style="width:140px" class="text-end">Qtd</th>
              <th style="width:140px" class="text-end">Unit.</th>
              <th style="width:140px" class="text-end">Total</th>
              <th style="width:64px" class="text-end">Rem.</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- PRÉVIA NFC-e (SOMENTE ITENS) + SUBTOTAIS EMBAIXO -->
    <div class="bottom">
      <div class="card-pdv" style="min-height:0">
        <div class="card-header d-flex align-items-center gap-2">
          <span>Prévia NFC-e (itens)</span>
          <span class="badge text-bg-warning text-dark">Somente pré-visualização</span>
        </div>
        <div class="card-body ticket-wrap">
          <div class="ticket" id="ticket"><!-- render via JS --></div>
        </div>
      </div>

      <div class="subgrid">
        <div class="box-num">
          <div class="lab">SUBTOTAL</div>
          <div id="tot-subtotal" class="num money">R$ 0,00</div>
        </div>
        <div class="box-num">
          <div class="lab me-2">DESCONTO</div>
          <div class="money-wrap" style="width:180px">
            <span class="money-prefix">R$</span>
            <input id="inp-desc" type="number" step="0.01" min="0" value="0.00" class="inp money-input text-end">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- DIREITA -->
  <div class="right">
    <div class="totalzao">
      <div class="d-flex justify-content-between">
        <span class="">Itens</span><strong id="tot-itens">0</strong>
      </div>
      <hr class="border-light ">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fs-6">TOTAL</span>
        <span id="tot-geral" class="fs-1 fw-bold money">R$ 0,00</span>
      </div>
    </div>

    <div class="card-pdv pay" style="min-height:0">
      <div class="card-header">PAGAMENTO</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6"><button class="btn btn-success w-100" type="button" data-pay="dinheiro" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Dinheiro</button></div>
          <div class="col-6"><button class="btn btn-primary w-100" type="button" data-pay="pix" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-upc-scan me-1"></i> PIX</button></div>
          <div class="col-6"><button class="btn btn-outline-light w-100" type="button" data-pay="debito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card-2-back me-1"></i> Débito</button></div>
          <div class="col-6"><button class="btn btn-outline-light w-100" type="button" data-pay="credito" <?= $caixaAberto ? '' : 'disabled' ?>><i class="bi bi-credit-card me-1"></i> Crédito</button></div>
        </div>

        <div id="grp-din" class="mt-3" style="display:none">
          <div class="row g-2">
            <div class="col-7">
              <div class="rt">
                <div class="opacity-75 mb-1">TOTAL RECEBIDO</div>
                <div class="money-wrap">
                  <span class="money-prefix">R$</span>
                  <input id="inp-recebido" name="valor_recebido" type="number" step="0.01" min="0" class="inp money-input text-end" placeholder="0,00">
                </div>
              </div>
            </div>
            <div class="col-5">
              <div class="rt h-100 d-flex flex-column">
                <div class="opacity-75">TROCO</div>
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
          <div class="mt-2 d-grid">
            <a href="../../dashboard.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Voltar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

const el=s=>document.querySelector(s);
const tbody=el('#tbl tbody');
let itens=[];
const temCaixa = (document.getElementById('form-venda')?.dataset.caixa==='1');

const fmt=v=>(Number(v||0)).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});

function itemTotal(){return (parseFloat(el('#inp-qtd').value||'0')*parseFloat(el('#inp-preco').value||'0')); }
function upItemTile(){ el('#tile-item-total').textContent='R$ '+fmt(itemTotal()); }

function recalc(){
  let sub=0; itens.forEach(i=> sub+=i.qtd*i.unit);
  const desc=parseFloat(el('#inp-desc').value||'0'); const total=Math.max(sub-desc,0);
  el('#tot-subtotal').textContent='R$ '+fmt(sub);
  el('#tot-geral').textContent='R$ '+fmt(total);
  el('#visor-subtotal').textContent='R$ '+fmt(total);
  el('#tot-itens').textContent=itens.length;
  el('#desconto_hidden').value=(desc||0).toFixed(2);
  el('#itens_json').value=JSON.stringify(itens);
  paint(); troco(); validateBtn(); renderTicket();
}
function paint(){
  tbody.innerHTML = itens.map((i,ix)=>`
    <tr>
      <td>${ix+1}</td>
      <td>${esc(i.sku||'-')}</td>
      <td>${esc(i.nome)}</td>
      <td class="text-end"><input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end q" data-i="${ix}" value="${i.qtd.toFixed(3)}" ${temCaixa?'':'disabled'}></td>
      <td class="text-end"><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end u" data-i="${ix}" value="${i.unit.toFixed(2)}" ${temCaixa?'':'disabled'}></td>
      <td class="text-end money">R$ ${fmt(i.qtd*i.unit)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger d" data-i="${ix}" type="button" ${temCaixa?'':'disabled'}><i class="bi bi-x"></i></button></td>
    </tr>
  `).join('');
}
function esc(s){return String(s||'').replace(/[&<>"'`=\/]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));}

const sug=el('#sug'), busca=el('#inp-busca'), qtd=el('#inp-qtd'), preco=el('#inp-preco');
function filtra(q){
  q=(q||'').trim().toLowerCase(); if(!q) return [];
  return PRODUTOS.filter(p=>(p.nome||'').toLowerCase().includes(q)||(p.sku||'').toLowerCase().includes(q)||(p.ean||'').toLowerCase().includes(q)||(p.marca||'').toLowerCase().includes(q)).slice(0,50);
}
function showSug(list){
  if(!list.length){sug.style.display='none';sug.innerHTML='';return;}
  sug.innerHTML = list.map(p=>`<div class="p-2 sug-item" data-preco="${Number(p.preco_venda||0)}" data-nome="${esc(p.nome||'')}" data-sku="${esc(p.sku||'')}">
    <div class="d-flex justify-content-between">
      <strong>${esc(p.nome||'-')}</strong>
      <span class="price">R$ ${fmt(p.preco_venda||0)}</span>
    </div>
    <div class="text-secondary small">${esc([p.marca,p.sku,p.ean].filter(Boolean).join(' • '))}${p.unidade?(' • '+esc(p.unidade)):''}</div>
  </div>`).join('');
  sug.style.display='block';
}
if(temCaixa){
  busca.addEventListener('input',()=>showSug(filtra(busca.value)));
  document.addEventListener('click',(e)=>{ if(!e.target.closest('#sug') && !e.target.closest('#inp-busca')) sug.style.display='none'; });
  sug.addEventListener('click',(e)=>{
    const it=e.target.closest('.sug-item'); if(!it) return;
    el('#visor-produto').textContent=it.dataset.nome||'—';
    preco.value=parseFloat(it.dataset.preco||'0').toFixed(2);
    el('#inp-sku').value=it.dataset.sku||'';
    upItemTile(); sug.style.display='none'; setTimeout(()=>qtd.focus(),20);
  });
}

function addItem(){
  if(!temCaixa) return;
  const nome=(el('#visor-produto').textContent||'').trim() || (busca.value||'').trim() || 'Item';
  const q=parseFloat(qtd.value||'0'), u=parseFloat(preco.value||'0'), sku=(el('#inp-sku').value||'').trim();
  if(q<=0 || u<0) return;
  itens.push({nome,qtd:q,unit:u,sku});
  busca.value=''; el('#inp-sku').value=''; el('#visor-produto').textContent='—'; qtd.value='1.000'; preco.value='0.00'; upItemTile();
  recalc(); busca.focus();
}

if(temCaixa){
  document.getElementById('btn-add').addEventListener('click', addItem);
  document.getElementById('btn-clear').addEventListener('click', ()=>{itens=[]; recalc();});
  qtd.addEventListener('input', upItemTile); preco.addEventListener('input', upItemTile);
  el('#tbl').addEventListener('input',(e)=>{
    if(e.target.classList.contains('q')){ const i=+e.target.dataset.i; itens[i].qtd=Math.max(parseFloat(e.target.value||'0'),0); recalc(); }
    if(e.target.classList.contains('u')){ const i=+e.target.dataset.i; itens[i].unit=Math.max(parseFloat(e.target.value||'0'),0); recalc(); }
  });
  el('#tbl').addEventListener('click',(e)=>{ if(e.target.closest('.d')){ const i=+e.target.closest('.d').dataset.i; itens.splice(i,1); recalc(); }});
  document.addEventListener('keydown',(e)=>{ if(e.key==='F2'){e.preventDefault(); qtd.select();} if(e.key==='F4'){e.preventDefault(); const b=document.getElementById('btn-finalizar'); if(!b.disabled) (document.getElementById('form-venda').requestSubmit?document.getElementById('form-venda').requestSubmit(b):b.click()); }});
  busca.addEventListener('keydown',(e)=>{ if(e.key==='Enter'){e.preventDefault(); addItem(); }});
}

const selBtns=document.querySelectorAll('[data-pay]'), grpDin=el('#grp-din'), inpRec=el('#inp-recebido'), lblTroco=el('#lbl-troco');
let forma='dinheiro';
selBtns.forEach(b=>b.addEventListener('click',()=>{ forma=b.dataset.pay; toggleDin(); }));
function toggleDin(){ const isDin=forma==='dinheiro'; grpDin.style.display=isDin?'block':'none'; validateBtn(); }
function troco(){
  if(forma!=='dinheiro'){ lblTroco.textContent='R$ 0,00'; lblTroco.className='n money'; return; }
  const total=(el('#tot-geral').textContent.replace(/[^\d,.-]/g,'')||'0').replace('.','').replace(',','.');
  const tr=(parseFloat(inpRec.value||'0') - (Number(total)||0));
  lblTroco.textContent='R$ '+fmt(tr); lblTroco.className='n money '+(tr>=0?'ok':'neg');
}
function validateBtn(){
  const b=document.getElementById('btn-finalizar');
  if(!temCaixa || itens.length===0){ b.disabled=true; return; }
  if(forma==='dinheiro'){
    const t=itens.reduce((s,i)=>s+i.qtd*i.unit,0) - parseFloat(el('#inp-desc').value||'0');
    b.disabled = (parseFloat(inpRec.value||'0') < Math.max(t,0));
  }else b.disabled=false;
}
el('#inp-desc').addEventListener('input', recalc);
if(inpRec){ inpRec.addEventListener('input', ()=>{ troco(); validateBtn(); }); }

function syncHidden(){ document.getElementById('desconto_hidden').value=(parseFloat(el('#inp-desc').value||'0')||0).toFixed(2); }
el('#inp-desc').addEventListener('input', syncHidden);

/* ===== Prévia NFC-e (somente itens – sem cabeçalho, sem total, sem QR) ===== */
function renderTicket(){
  const t = el('#ticket'); if(!t) return;
  const linhas = itens.map(i=>`
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

upItemTile(); recalc(); toggleDin(); renderTicket();
</script>
</body>
</html>
