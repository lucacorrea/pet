<?php
// autoErp/public/vendas/actions/vendasSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$op   = (string)($_POST['op'] ?? '');
$csrf = (string)($_POST['csrf'] ?? '');

if ($op !== 'venda_rapida' || empty($_SESSION['csrf_venda_rapida']) || !hash_equals($_SESSION['csrf_venda_rapida'], $csrf)) {
  header('Location: ../pages/vendaRapida.php?err=1&msg=' . urlencode('Operação inválida.'));
  exit;
}

// empresa / vendedor
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/vendaRapida.php?err=1&msg=' . urlencode('Empresa não vinculada.'));
  exit;
}
$vendedorCpf = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? '')); // se não tiver, pode ficar vazio

// dados venda
$cliente_nome    = trim((string)($_POST['cliente_nome'] ?? ''));
$forma_pagamento = (string)($_POST['forma_pagamento'] ?? 'dinheiro');
$status          = (string)($_POST['status'] ?? 'fechada');
$desconto        = (float)($_POST['desconto'] ?? 0);

// itens
$item_tipo  = $_POST['item_tipo']  ?? [];
$descricao  = $_POST['descricao']  ?? [];
$qtd        = $_POST['qtd']        ?? [];
$valor_unit = $_POST['valor_unit'] ?? [];
$item_id    = $_POST['item_id']    ?? []; // produto_id quando tipo=produto

// valida itens
$linhas = [];
for ($i=0; $i < count($descricao); $i++) {
  $tipo = ($item_tipo[$i] ?? 'produto') === 'servico' ? 'servico' : 'produto';
  $desc = trim((string)($descricao[$i] ?? ''));
  $q    = (float)($qtd[$i] ?? 0);
  $vu   = (float)($valor_unit[$i] ?? 0);
  $pid  = (int)($item_id[$i] ?? 0);

  if ($desc === '' || $q <= 0 || $vu < 0) continue;

  $linhas[] = [
    'tipo' => $tipo,
    'desc' => $desc,
    'qtd'  => $q,
    'unit' => $vu,
    'pid'  => $tipo==='produto' ? $pid : null,
  ];
}

if (!$linhas) {
  header('Location: ../pages/vendaRapida.php?err=1&msg=' . urlencode('Inclua ao menos um item válido.'));
  exit;
}

$total_bruto = 0.0;
foreach ($linhas as $L) $total_bruto += $L['qtd'] * $L['unit'];
$total_liquido = max($total_bruto - $desconto, 0);

try {
  $pdo->beginTransaction();

  // cria venda
  $ins = $pdo->prepare("
    INSERT INTO vendas_peca
      (empresa_cnpj, vendedor_cpf, origem, status, total_bruto, desconto, total_liquido, forma_pagamento)
    VALUES
      (:c, :v, 'balcao', :s, :tb, :d, :tl, :fp)
  ");
  $ins->execute([
    ':c'  => $empresaCnpj,
    ':v'  => $vendedorCpf ?: null,
    ':s'  => in_array($status, ['aberta','fechada','cancelada'], true) ? $status : 'fechada',
    ':tb' => $total_bruto,
    ':d'  => $desconto,
    ':tl' => $total_liquido,
    ':fp' => $forma_pagamento,
  ]);
  $vendaId = (int)$pdo->lastInsertId();

  // itens + movimentação de estoque (quando produto e status != cancelada)
  $insItem = $pdo->prepare("
    INSERT INTO venda_itens_peca
      (venda_id, item_tipo, item_id, descricao, qtd, valor_unit, valor_total)
    VALUES
      (:vid, :t, :iid, :d, :q, :vu, :vt)
  ");

  $mov = $pdo->prepare("
    INSERT INTO mov_estoque_peca
      (empresa_cnpj, produto_id, tipo, qtd, origem, ref_id, usuario_cpf)
    VALUES
      (:c, :pid, 'saida', :q, 'venda', :ref, :u)
  ");

  $updEst = $pdo->prepare("
    UPDATE produtos_peca
       SET estoque_atual = estoque_atual - :q
     WHERE id = :pid AND empresa_cnpj = :c
  ");

  foreach ($linhas as $L) {
    $vt = $L['qtd'] * $L['unit'];

    $insItem->execute([
      ':vid' => $vendaId,
      ':t'   => $L['tipo'],
      ':iid' => $L['tipo']==='produto' ? ($L['pid'] ?: 0) : 0,
      ':d'   => $L['desc'],
      ':q'   => $L['qtd'],
      ':vu'  => $L['unit'],
      ':vt'  => $vt,
    ]);

    if ($L['tipo'] === 'produto' && $status !== 'cancelada' && $L['pid']) {
      // baixa estoque
      $updEst->execute([
        ':q'   => $L['qtd'],
        ':pid' => $L['pid'],
        ':c'   => $empresaCnpj,
      ]);
      // movimento
      $mov->execute([
        ':c'   => $empresaCnpj,
        ':pid' => $L['pid'],
        ':q'   => $L['qtd'],
        ':ref' => $vendaId,
        ':u'   => $vendedorCpf ?: null,
      ]);
    }
  }

  $pdo->commit();
  unset($_SESSION['csrf_venda_rapida']); // força novo token
  header('Location: ../pages/vendaRapida.php?ok=1&msg=' . urlencode('Venda registrada!'));
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/vendaRapida.php?err=1&msg=' . urlencode('Erro ao salvar a venda.'));
  exit;
}
