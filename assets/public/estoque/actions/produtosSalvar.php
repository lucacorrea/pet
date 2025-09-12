<?php
// autoErp/public/estoque/actions/produtosSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post();
guard_empresa_user(['dono','administrativo','estoque']);

$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/produtosNovo.php?err=1&msg=' . urlencode('Conexão indisponível.')); exit;
}

function norm_cnpj(string $c): string { return preg_replace('/\D+/', '', $c); }
function back_to_new(string $msg, bool $ok): void {
  $qs = ($ok ? ['ok'=>1, 'msg'=>$msg] : ['err'=>1, 'msg'=>$msg]);
  header('Location: ../pages/produtosNovo.php?' . http_build_query($qs));
  exit;
}

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_prod_novo']) || !hash_equals($_SESSION['csrf_prod_novo'], $csrf)) {
  back_to_new('Token inválido. Atualize a página.', false);
}

$empresaCnpj = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  back_to_new('Empresa não vinculada ao usuário.', false);
}

// Entrada
$setor           = strtolower((string)($_POST['setor'] ?? 'petshop')); // autopeca|lavajato
$nome            = trim((string)($_POST['nome'] ?? ''));
$fornecedorId    = isset($_POST['fornecedor_id']) && $_POST['fornecedor_id'] !== '' ? (int)$_POST['fornecedor_id'] : null;
$sku             = trim((string)($_POST['sku'] ?? ''));
$ean             = trim((string)($_POST['ean'] ?? ''));
$marca           = trim((string)($_POST['marca'] ?? ''));
$unidade         = trim((string)($_POST['unidade'] ?? 'UN'));
$preco_custo     = (float)str_replace(',', '.', (string)($_POST['preco_custo']   ?? '0'));
$preco_venda     = (float)str_replace(',', '.', (string)($_POST['preco_venda']   ?? '0'));
$estoque_minimo  = (float)str_replace(',', '.', (string)($_POST['estoque_minimo']?? '0'));
$estoque_inicial = (float)str_replace(',', '.', (string)($_POST['estoque_inicial']??'0'));
$ativo           = (int)($_POST['ativo'] ?? 1);

// Valida
$errs = [];
if ($nome === '' || mb_strlen($nome) > 180) $errs[] = 'Informe um nome válido (até 180).';
if (!in_array($setor, ['autopeca','lavajato'], true)) $errs[] = 'Setor inválido.';
if ($preco_venda < 0) $errs[] = 'Preço de venda inválido.';
if ($preco_custo < 0) $errs[] = 'Preço de custo inválido.';
if ($estoque_minimo < 0) $errs[] = 'Estoque mínimo inválido.';
if ($estoque_inicial < 0) $errs[] = 'Estoque inicial inválido.';
if ($errs) back_to_new(implode(' ', $errs), false);

// Empresa ativa?
try {
  $st = $pdo->prepare("SELECT status FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c'=>$empresaCnpj]);
  $emp = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emp || ($emp['status'] ?? '') !== 'ativa') back_to_new('Empresa inexistente ou inativa.', false);
} catch (Throwable $e) {
  back_to_new('Falha ao validar empresa.', false);
}

// SKU único
if ($sku !== '') {
  $st = $pdo->prepare("SELECT id FROM produtos_peca WHERE empresa_cnpj = :c AND sku = :s LIMIT 1");
  $st->execute([':c'=>$empresaCnpj, ':s'=>$sku]);
  if ($st->fetch()) back_to_new('SKU já cadastrado para esta empresa.', false);
}

// Se veio fornecedor, valida que pertence à empresa
if ($fornecedorId) {
  $st = $pdo->prepare("SELECT id FROM fornecedores_peca WHERE id = :id AND empresa_cnpj = :c LIMIT 1");
  $st->execute([':id'=>$fornecedorId, ':c'=>$empresaCnpj]);
  if (!$st->fetch()) {
    $fornecedorId = null; // ignora silenciosamente se não bater
  }
}

// Detecta se a coluna fornecedor_id existe na tabela produtos_peca
$temFornecedorCol = false;
try { $pdo->query("SELECT fornecedor_id FROM produtos_peca LIMIT 0"); $temFornecedorCol = true; } catch (Throwable $e) {}

// Resolve categoria por setor
$catNome = ($setor === 'lavajato') ? 'Lava Jato' : 'Auto Peças';
try {
  $st = $pdo->prepare("SELECT id FROM categorias_produto_peca WHERE empresa_cnpj = :c AND nome = :n LIMIT 1");
  $st->execute([':c'=>$empresaCnpj, ':n'=>$catNome]);
  $catId = $st->fetchColumn();
  if (!$catId) {
    $ins = $pdo->prepare("INSERT INTO categorias_produto_peca (empresa_cnpj, nome, criado_em) VALUES (:c,:n,NOW())");
    $ins->execute([':c'=>$empresaCnpj, ':n'=>$catNome]);
    $catId = (int)$pdo->lastInsertId();
  } else {
    $catId = (int)$catId;
  }
} catch (Throwable $e) {
  back_to_new('Falha ao localizar/criar categoria do setor.', false);
}

// Insere
try {
  $pdo->beginTransaction();

  if ($temFornecedorCol) {
    $ins = $pdo->prepare("
      INSERT INTO produtos_peca
        (empresa_cnpj, categoria_id, fornecedor_id, nome, sku, ean, marca, unidade,
         preco_custo, preco_venda, estoque_atual, estoque_minimo, ativo, criado_em)
      VALUES
        (:c, :cat, :forn, :nome, :sku, :ean, :marca, :unid,
         :pc, :pv, 0.000, :emin, :ativo, NOW())
    ");
    $ins->execute([
      ':c'    => $empresaCnpj,
      ':cat'  => $catId,
      ':forn' => $fornecedorId ?: null,
      ':nome' => $nome,
      ':sku'  => ($sku   ?: null),
      ':ean'  => ($ean   ?: null),
      ':marca'=> ($marca ?: null),
      ':unid' => ($unidade ?: 'UN'),
      ':pc'   => $preco_custo,
      ':pv'   => $preco_venda,
      ':emin' => $estoque_minimo,
      ':ativo'=> $ativo ? 1 : 0,
    ]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO produtos_peca
        (empresa_cnpj, categoria_id, nome, sku, ean, marca, unidade,
         preco_custo, preco_venda, estoque_atual, estoque_minimo, ativo, criado_em)
      VALUES
        (:c, :cat, :nome, :sku, :ean, :marca, :unid,
         :pc, :pv, 0.000, :emin, :ativo, NOW())
    ");
    $ins->execute([
      ':c'    => $empresaCnpj,
      ':cat'  => $catId,
      ':nome' => $nome,
      ':sku'  => ($sku   ?: null),
      ':ean'  => ($ean   ?: null),
      ':marca'=> ($marca ?: null),
      ':unid' => ($unidade ?: 'UN'),
      ':pc'   => $preco_custo,
      ':pv'   => $preco_venda,
      ':emin' => $estoque_minimo,
      ':ativo'=> $ativo ? 1 : 0,
    ]);
  }

  $produtoId = (int)$pdo->lastInsertId();

  if ($estoque_inicial > 0) {
    $insm = $pdo->prepare("
      INSERT INTO mov_estoque_peca
        (empresa_cnpj, produto_id, tipo, qtd, origem, ref_id, usuario_cpf, criado_em)
      VALUES
        (:c, :pid, 'entrada', :qtd, 'ajuste', NULL, :cpf, NOW())
    ");
    $insm->execute([
      ':c'   => $empresaCnpj,
      ':pid' => $produtoId,
      ':qtd' => $estoque_inicial,
      ':cpf' => preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? '')) ?: null,
    ]);

    $up = $pdo->prepare("UPDATE produtos_peca SET estoque_atual = estoque_atual + :q WHERE id = :id AND empresa_cnpj = :c");
    $up->execute([':q'=>$estoque_inicial, ':id'=>$produtoId, ':c'=>$empresaCnpj]);
  }

  $pdo->commit();
  back_to_new('Produto cadastrado com sucesso.', true);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back_to_new('Falha ao salvar produto.', false);
}
