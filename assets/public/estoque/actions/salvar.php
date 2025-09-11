<?php
// autoErp/public/estoque/actions/salvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post();
guard_empresa_user(['dono','administrativo','estoque']); // só esses podem movimentar

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/estoque.php?err=1&msg=' . urlencode('Conexão indisponível.')); exit;
}

function norm_cnpj(string $c): string { return preg_replace('/\D+/', '', $c); }
function back_to_list(string $msg, bool $ok = true): void {
  header('Location: ../pages/estoque.php?' . http_build_query([$ok ? 'ok' : 'err' => 1, 'msg' => $msg]));
  exit;
}

$op = (string)($_POST['op'] ?? '');
if ($op !== 'mov_novo') {
  back_to_list('Operação inválida.', false);
}

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_estoque_mov']) || !hash_equals($_SESSION['csrf_estoque_mov'], $csrf)) {
  back_to_list('Token inválido. Atualize a página.', false);
}

// Sessão
$empresaCnpj = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
$usuarioCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  back_to_list('Empresa não vinculada ao usuário.', false);
}

// Entrada
$produtoId = (int)($_POST['produto_id'] ?? 0);
$tipo      = strtolower((string)($_POST['tipo'] ?? ''));
$origem    = strtolower((string)($_POST['origem'] ?? ''));
$qtdRaw    = (string)($_POST['qtd'] ?? '0');
$refId     = ($_POST['ref_id'] ?? '') === '' ? null : (int)$_POST['ref_id'];

$qtd = (float)str_replace(',', '.', $qtdRaw);

// Validações
if ($produtoId <= 0) back_to_list('Selecione um produto.', false);
if (!in_array($tipo, ['entrada','saida','ajuste'], true)) back_to_list('Tipo inválido.', false);
if (!in_array($origem, ['compra','venda','ajuste','os'], true)) back_to_list('Origem inválida.', false);
if ($qtd <= 0 && $tipo !== 'ajuste') back_to_list('Quantidade deve ser maior que zero.', false);
// Em ajuste permitimos informar quantidade com sinal via interface? Aqui usamos campo positivo e o sinal é escolhido pelo tipo.
// Para permitir negativo no ajuste, poderia aceitar $qtd<=0; manteremos positivo e o sinal é derivado do tipo.

try {
  // Verifica produto e saldo
  $st = $pdo->prepare("SELECT id, unidade, estoque_atual FROM produtos_peca WHERE id = :id AND empresa_cnpj = :c AND ativo = 1 LIMIT 1");
  $st->execute([':id'=>$produtoId, ':c'=>$empresaCnpj]);
  $prod = $st->fetch(PDO::FETCH_ASSOC);
  if (!$prod) back_to_list('Produto inexistente/indisponível.', false);

  $saldoAtual = (float)$prod['estoque_atual'];

  // Define delta no estoque
  $delta = 0.0;
  if ($tipo === 'entrada') {
    $delta = +$qtd;
  } elseif ($tipo === 'saida') {
    // regra: não permitir ficar negativo
    if ($saldoAtual < $qtd) back_to_list('Estoque insuficiente para saída.', false);
    $delta = -$qtd;
  } else { // ajuste
    // Para ajuste, podemos aceitar refinar depois; aqui um ajuste POSITIVO por padrão (pode adaptar para aceitar campo "sinal")
    $delta = +$qtd;
  }

  $pdo->beginTransaction();

  // Insere movimento (armazenamos qtd sempre positiva, exceto se quiser guardar sinal em ajuste; aqui guardo a POSITIVA)
  $ins = $pdo->prepare("
    INSERT INTO mov_estoque_peca
      (empresa_cnpj, produto_id, tipo, qtd, origem, ref_id, usuario_cpf, criado_em)
    VALUES
      (:c, :pid, :tipo, :qtd, :origem, :ref, :cpf, NOW())
  ");
  $ins->execute([
    ':c'     => $empresaCnpj,
    ':pid'   => $produtoId,
    ':tipo'  => $tipo,
    ':qtd'   => abs($qtd),
    ':origem'=> $origem,
    ':ref'   => $refId,
    ':cpf'   => ($usuarioCpf ?: null),
  ]);

  // Atualiza saldo do produto
  $up = $pdo->prepare("UPDATE produtos_peca SET estoque_atual = estoque_atual + :d WHERE id = :id AND empresa_cnpj = :c");
  $up->execute([':d'=>$delta, ':id'=>$produtoId, ':c'=>$empresaCnpj]);

  $pdo->commit();

  back_to_list('Movimentação registrada com sucesso.', true);

} catch (\Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back_to_list('Falha ao registrar movimentação.', false);
}
