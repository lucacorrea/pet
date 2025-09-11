<?php
// autoErp/public/vendas/actions/orcamentosSalvar.php
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

if ($op !== 'orc_novo') {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Operação inválida.'));
  exit;
}
if (!hash_equals($_SESSION['csrf_orc_novo'] ?? '', $csrf)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('CSRF inválido.'));
  exit;
}

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Empresa não vinculada.'));
  exit;
}

$cliente_nome     = trim((string)($_POST['cliente_nome'] ?? ''));
$cliente_tel      = trim((string)($_POST['cliente_telefone'] ?? ''));
$cliente_email    = trim((string)($_POST['cliente_email'] ?? ''));
$validade         = trim((string)($_POST['validade'] ?? ''));
$observacoes      = trim((string)($_POST['observacoes'] ?? ''));
$desconto         = (float)($_POST['desconto'] ?? 0);

$item_tipo  = $_POST['item_tipo']  ?? [];
$descricao  = $_POST['descricao']  ?? [];
$qtd        = $_POST['qtd']        ?? [];
$valor_unit = $_POST['valor_unit'] ?? [];

if (!is_array($item_tipo) || !is_array($descricao) || !is_array($qtd) || !is_array($valor_unit)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Itens inválidos.'));
  exit;
}

try {
  $pdo->beginTransaction();

  // próximo número
  $st = $pdo->prepare("SELECT COALESCE(MAX(numero),0)+1 FROM orcamentos_peca WHERE empresa_cnpj = :c");
  $st->execute([':c' => $empresaCnpj]);
  $numero = (int)$st->fetchColumn();

  // calcula totais
  $total_bruto = 0.00;
  $linhas = [];
  $N = max(count($item_tipo), count($descricao), count($qtd), count($valor_unit));
  for ($i=0; $i<$N; $i++) {
    $tipo = strtolower(trim((string)($item_tipo[$i] ?? '')));
    $desc = trim((string)($descricao[$i] ?? ''));
    $q    = (float)($qtd[$i] ?? 0);
    $vu   = (float)($valor_unit[$i] ?? 0);
    if ($desc === '' || $q <= 0 || $vu < 0) continue;

    if ($tipo !== 'produto' && $tipo !== 'servico') $tipo = 'servico';
    $vt = $q * $vu;

    $linhas[] = [
      'tipo' => $tipo,
      'desc' => $desc,
      'qtd'  => $q,
      'vu'   => $vu,
      'vt'   => $vt,
    ];
    $total_bruto += $vt;
  }

  if (!$linhas) {
    $pdo->rollBack();
    header('Location: ../pages/orcamentoNovo.php?err=1&msg=' . urlencode('Adicione ao menos um item válido.'));
    exit;
  }

  $total_liquido = max($total_bruto - max($desconto,0), 0);

  // header
  $ins = $pdo->prepare("
    INSERT INTO orcamentos_peca
      (empresa_cnpj, numero, cliente_nome, cliente_telefone, cliente_email,
       validade, status, observacoes, total_bruto, desconto, total_liquido)
    VALUES
      (:c, :n, :cn, :ct, :ce, :val, 'aberto', :obs, :tb, :desc, :tl)
  ");
  $ins->execute([
    ':c'   => $empresaCnpj,
    ':n'   => $numero,
    ':cn'  => $cliente_nome,
    ':ct'  => $cliente_tel,
    ':ce'  => $cliente_email,
    ':val' => ($validade ?: null),
    ':obs' => $observacoes,
    ':tb'  => $total_bruto,
    ':desc'=> $desconto,
    ':tl'  => $total_liquido,
  ]);
  $orcId = (int)$pdo->lastInsertId();

  // itens
  $insI = $pdo->prepare("
    INSERT INTO orcamento_itens_peca
      (orcamento_id, item_tipo, item_id, descricao, qtd, valor_unit, valor_total)
    VALUES
      (:o, :t, NULL, :d, :q, :vu, :vt)
  ");
  foreach ($linhas as $L) {
    $insI->execute([
      ':o'  => $orcId,
      ':t'  => $L['tipo'],
      ':d'  => $L['desc'],
      ':q'  => $L['qtd'],
      ':vu' => $L['vu'],
      ':vt' => $L['vt'],
    ]);
  }

  $pdo->commit();
  unset($_SESSION['csrf_orc_novo']);
  header('Location: ../pages/orcamentos.php?ok=1&msg=' . urlencode('Orçamento #'.$numero.' salvo.'));
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/orcamentoNovo.php?err=1&msg=' . urlencode('Falha ao salvar o orçamento.'));
  exit;
}
