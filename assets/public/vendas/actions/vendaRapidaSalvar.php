<?php
// autoErp/public/vendas/actions/vendaRapidaSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

// Conexão
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

// Helpers
function back(string $msg, int $ok = 0): never {
  $qs = http_build_query(['ok'=>$ok, 'err'=>$ok?0:1, 'msg'=>$msg]);
  header("Location: ../pages/vendaRapida.php?$qs");
  exit;
}
function p($k,$d=null){ return $_POST[$k] ?? $d; }
function numBR($v): float {
  // aceita "1234,56" ou "1234.56"
  $s = str_replace(['.',','], ['','.'], (string)$v);
  return (float)$s;
}

// CSRF
if (empty($_SESSION['csrf_venda_rapida']) || p('csrf') !== $_SESSION['csrf_venda_rapida']) {
  back('CSRF inválido.');
}

// Empresa & Operador
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) back('Empresa não vinculada ao usuário.');

$operadorCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
$operadorNome = (string)($_SESSION['user_nome'] ?? '');
$userEmail    = (string)($_SESSION['user_email'] ?? '');
$userId       = (string)($_SESSION['user_id'] ?? '');

if ($operadorCpf === '' && $userEmail !== '') {
  try {
    $st = $pdo->prepare("SELECT cpf, nome FROM usuarios_peca WHERE email=:e LIMIT 1");
    $st->execute([':e'=>$userEmail]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $operadorCpf = preg_replace('/\D+/', '', (string)($row['cpf'] ?? ''));
      if (!$operadorNome && !empty($row['nome'])) $operadorNome = $row['nome'];
      $_SESSION['user_cpf'] = $operadorCpf;
      if (!empty($row['nome'])) $_SESSION['user_nome'] = $row['nome'];
    }
  } catch (Throwable $e) {}
}
if ($operadorCpf === '') {
  if ($userId !== '') {
    $operadorCpf = 'UID' . preg_replace('/\D+/', '', $userId);
  } else {
    back('Operador inválido.');
  }
}

// Verifica caixa aberto (pega o mais recente)
$caixa = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, aberto_por_cpf
    FROM caixas_peca
    WHERE empresa_cnpj=:c AND status='aberto'
    ORDER BY aberto_em DESC
    LIMIT 1
  ");
  $st->execute([':c'=>$empresaCnpj]);
  $caixa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $caixa = null;
}
if (!$caixa) back('Não há caixa aberto para esta empresa.');

// Regras: se for individual, precisa ser do mesmo operador
if (($caixa['tipo'] ?? '') === 'individual') {
  $donoCpf = preg_replace('/\D+/', '', (string)($caixa['aberto_por_cpf'] ?? ''));
  if ($donoCpf !== $operadorCpf) back('O caixa individual aberto pertence a outro operador.');
} else {
  // se compartilhado, garante participação (se não existir, entra automaticamente)
  try {
    $stp = $pdo->prepare("
      SELECT id FROM caixa_participantes_peca
      WHERE caixa_id=:cid AND empresa_cnpj=:c AND operador_cpf=:cpf AND ativo=1
      LIMIT 1
    ");
    $stp->execute([':cid'=>$caixa['id'], ':c'=>$empresaCnpj, ':cpf'=>$operadorCpf]);
    if (!$stp->fetch()) {
      $ins = $pdo->prepare("
        INSERT INTO caixa_participantes_peca
          (caixa_id, empresa_cnpj, operador_cpf, operador_nome, entrou_em, ativo)
        VALUES
          (:cid, :c, :cpf, :nome, NOW(), 1)
      ");
      $ins->execute([
        ':cid'=>$caixa['id'], ':c'=>$empresaCnpj,
        ':cpf'=>$operadorCpf, ':nome'=>$operadorNome ?: null
      ]);
    }
  } catch (Throwable $e) {
    // não bloqueia a venda se falhar inserir participação
  }
}

// Entrada do formulário
$forma = (string)p('forma_pagamento','dinheiro'); // dinheiro|pix|debito|credito
$desconto = numBR(p('desconto','0'));
$itensJson = (string)p('itens_json','[]');
$valorRecebido = numBR(p('valor_recebido','0')); // só se dinheiro (precisa name="valor_recebido" na view)

$itens = json_decode($itensJson, true);
if (!is_array($itens)) $itens = [];

if (empty($itens)) back('Adicione ao menos um item.');

// Calcula totais
$subtotal = 0.0;
foreach ($itens as $i) {
  $qtd  = (float)($i['qtd']  ?? 0);
  $unit = (float)($i['unit'] ?? 0);
  if ($qtd <= 0 || $unit < 0) back('Item inválido (quantidade/valor).');
  $subtotal += $qtd * $unit;
}
$desconto = max((float)$desconto, 0.0);
$total = max($subtotal - $desconto, 0.0);

// Valida dinheiro/troco no servidor
if ($forma === 'dinheiro') {
  if ($valorRecebido <= 0) back('Informe o valor recebido em dinheiro.');
  if ($valorRecebido + 1e-9 < $total) back('Valor recebido menor que o total.');
}
$troco = ($forma === 'dinheiro') ? max($valorRecebido - $total, 0.0) : 0.0;

try {
  $pdo->beginTransaction();

  // 1) Insere venda_peca
  $insVenda = $pdo->prepare("
    INSERT INTO vendas_peca
      (empresa_cnpj, vendedor_cpf, origem, status, total_bruto, desconto, total_liquido, forma_pagamento, criado_em)
    VALUES
      (:c, :cpf, 'balcao', 'fechada', :bruto, :desc, :liq, :fp, NOW())
  ");
  $insVenda->execute([
    ':c'    => $empresaCnpj,
    ':cpf'  => $operadorCpf,
    ':bruto'=> $subtotal,
    ':desc' => $desconto,
    ':liq'  => $total,
    ':fp'   => $forma,
  ]);
  $vendaId = (int)$pdo->lastInsertId();

  // 2) Insere itens (nota: como a sua view atual não envia ID do produto, usamos 0)
  $insItem = $pdo->prepare("
    INSERT INTO venda_itens_peca
      (venda_id, item_tipo, item_id, descricao, qtd, valor_unit, valor_total)
    VALUES
      (:venda, 'produto', :item_id, :desc, :qtd, :unit, :tot)
  ");

  foreach ($itens as $i) {
    $desc = (string)($i['nome'] ?? 'Item');
    $qtd  = (float)($i['qtd']  ?? 0);
    $unit = (float)($i['unit'] ?? 0);
    $tot  = $qtd * $unit;

    // Se quiser tentar resolver ID do produto pelo nome/sku/ean,
    // este é o ponto para buscar no BD. Por ora, vai com 0 (permitido pelo schema).
    $itemId = 0;

    $insItem->execute([
      ':venda'   => $vendaId,
      ':item_id' => $itemId,
      ':desc'    => $desc,
      ':qtd'     => $qtd,
      ':unit'    => $unit,
      ':tot'     => $tot,
    ]);
  }

  // 3) Movimento de caixa (entrada)
  $descMov = sprintf('Venda rápida #%d (%s)', $vendaId, strtoupper($forma));
  $insMov = $pdo->prepare("
    INSERT INTO caixa_movimentos_peca
      (empresa_cnpj, caixa_id, tipo, forma_pagamento, valor, descricao, criado_em)
    VALUES
      (:c, :cx, 'entrada', :fp, :val, :desc, NOW())
  ");
  $insMov->execute([
    ':c'   => $empresaCnpj,
    ':cx'  => (int)$caixa['id'],
    ':fp'  => $forma,
    ':val' => $total,
    ':desc'=> $descMov,
  ]);

  $pdo->commit();

  // sucesso
  back("Venda #$vendaId registrada com sucesso!", 1);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // Para debug, troque a linha abaixo por: back('Falha ao salvar: '.$e->getMessage());
  back('Falha ao salvar a venda.');
}
