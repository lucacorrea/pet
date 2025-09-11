<?php
// autoErp/public/vendas/pages/orcamentoCupom.php
// Impressão de Orçamento no formato CUPOM (bobina 80mm)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('ID inválido.');

// Empresa
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa inválida');

$empresaNome = empresa_nome_logada($pdo);

// Orçamento + Itens
$orc = null; $itens = [];
try {
  $so = $pdo->prepare("
    SELECT o.*, DATE_FORMAT(o.criado_em,'%d/%m/%Y %H:%i') AS quando_fmt,
           DATE_FORMAT(o.validade,'%d/%m/%Y') AS validade_fmt
    FROM orcamentos_peca o
    WHERE o.id=:id AND o.empresa_cnpj=:c
    LIMIT 1
  ");
  $so->execute([':id'=>$id, ':c'=>$empresaCnpj]);
  $orc = $so->fetch(PDO::FETCH_ASSOC);

  if ($orc) {
    $si = $pdo->prepare("
      SELECT descricao, qtd, valor_unit, (qtd*valor_unit) AS total
      FROM orcamento_itens_peca
      WHERE orcamento_id = :id
      ORDER BY id
    ");
    $si->execute([':id'=>$id]);
    $itens = $si->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch(Throwable $e){}

if (!$orc) die('Orçamento não encontrado.');

$numero   = (int)($orc['numero'] ?? $orc['id']);
$subtotal = 0.0; foreach($itens as $i){ $subtotal += (float)$i['total']; }
$desconto = (float)($orc['desconto'] ?? 0);
$total    = (float)($orc['total_liquido'] ?? max($subtotal - $desconto,0));
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Orçamento Cupom #<?= $numero ?></title>
<style>
body { font-family: monospace, monospace; font-size: 13px; width: 80mm; margin:0 auto; color:#000; }
.center { text-align:center }
.right  { text-align:right }
.line   { border-top:1px dashed #000; margin:4px 0 }
table { width:100%; border-collapse:collapse }
td,th{ padding:2px 0 }
footer{ margin-top:10px; text-align:center; font-size:11px }
@media print {
  body { margin:0 }
  .no-print { display:none }
}
/* Botões */
.no-print { display:flex; justify-content:flex-end; gap:8px; margin-bottom:8px; }
.btn {
  padding:6px 12px;
  font-size:13px;
  border:none;
  border-radius:4px;
  cursor:pointer;
}
.btn-print { background:#2563eb; color:#fff; }
.btn-print:hover { background:#1d4ed8; }
.btn-back { background:#6b7280; color:#fff; }
.btn-back:hover { background:#4b5563; }
</style>
</head>
<body>
<div class="no-print">
  <button class="btn btn-back" onclick="window.location.href='./orcamentos.php'">⬅️ Voltar</button>
  <button class="btn btn-print" onclick="window.print()">🖨️ Imprimir</button>
</div>

<div class="center">
  <strong><?= h($empresaNome) ?></strong><br>
  CNPJ: <?= h($empresaCnpj) ?><br>
  ------------------------------
  <div><strong>ORÇAMENTO Nº <?= $numero ?></strong></div>
  Emitido em <?= h($orc['quando_fmt']) ?><br>
  Validade: <?= h($orc['validade_fmt']) ?>
</div>

<div class="line"></div>
Cliente: <?= h($orc['cliente_nome'] ?: '-') ?><br>
<?php if (!empty($orc['cliente_telefone'])): ?>
Fone: <?= h($orc['cliente_telefone']) ?><br>
<?php endif; ?>
<?php if (!empty($orc['cliente_email'])): ?>
Email: <?= h($orc['cliente_email']) ?><br>
<?php endif; ?>

<div class="line"></div>
<table>
  <thead>
    <tr>
      <th>DESCRIÇÃO</th>
      <th class="right">QTD</th>
      <th class="right">VL.UN</th>
      <th class="right">TOTAL</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$itens): ?>
      <tr><td colspan="4">*** Sem itens ***</td></tr>
    <?php else: foreach($itens as $i): ?>
      <tr>
        <td><?= h($i['descricao']) ?></td>
        <td class="right"><?= number_format((float)$i['qtd'],3,',','.') ?></td>
        <td class="right"><?= number_format((float)$i['valor_unit'],2,',','.') ?></td>
        <td class="right"><?= number_format((float)$i['total'],2,',','.') ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
<div class="line"></div>
<div class="right">Subtotal: R$ <?= number_format($subtotal,2,',','.') ?></div>
<?php if ($desconto > 0): ?>
<div class="right">Desconto: R$ <?= number_format($desconto,2,',','.') ?></div>
<?php endif; ?>
<div class="right"><strong>Total: R$ <?= number_format($total,2,',','.') ?></strong></div>
<div class="line"></div>

<?php if (!empty($orc['observacoes'])): ?>
Obs: <?= h($orc['observacoes']) ?><br>
<div class="line"></div>
<?php endif; ?>

<footer>
  *** ORÇAMENTO — NÃO É DOCUMENTO FISCAL ***<br>
  Desenvolvido por Lucas de S. Correa
</footer>
</body>
</html>
