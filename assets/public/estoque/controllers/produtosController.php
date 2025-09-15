<?php
// autoErp/public/estoque/controllers/produtosController.php
declare(strict_types=1);

function produtos_list_viewmodel(PDO $pdo): array
{
  if (session_status() === PHP_SESSION_NONE) session_start();

  // CSRF para excluir
  if (empty($_SESSION['csrf_produtos_list'])) {
    $_SESSION['csrf_produtos_list'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_produtos_list'];

  // Empresa da sessão
  $empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
  if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
    throw new RuntimeException('Empresa não vinculada ao usuário.');
  }

  // Filtros
  $q     = trim((string)($_GET['q'] ?? ''));
  $setor = strtolower((string)($_GET['setor'] ?? '')); // '', autopeca, lavajato
  $ativo = (string)($_GET['ativo'] ?? '');             // '', '1', '0'

  // Detecta fornecedor_id
  $temFornecedorCol = false;
  try { $pdo->query("SELECT fornecedor_id FROM produtos_peca LIMIT 0"); $temFornecedorCol = true; } catch (Throwable $e) {}

  // Query
  $sql = "
    SELECT
      p.id, p.nome, p.sku, p.ean, p.marca, p.preco_venda, p.estoque_atual, p.ativo,
      c.nome AS categoria_nome
      ".($temFornecedorCol ? ", f.nome AS fornecedor_nome" : "")."
    FROM produtos_peca p
    LEFT JOIN categorias_produto_peca c ON c.id = p.categoria_id
    ".($temFornecedorCol ? "LEFT JOIN fornecedores_peca f ON f.id = p.fornecedor_id" : "")."
    WHERE p.empresa_cnpj = :c
  ";
  $params = [':c' => $empresaCnpj];

  if ($q !== '') {
    $sql .= " AND (p.nome LIKE :q OR p.sku LIKE :q OR p.ean LIKE :q OR p.marca LIKE :q)";
    $params[':q'] = "%{$q}%";
  }
  if ($setor === 'petshop') {
    $sql .= " AND (c.nome = 'Pets Shop')";
  } elseif ($setor === 'petshop') {
    $sql .= " AND (c.nome = 'Pets Shop')";
  }
  if ($ativo === '1' || $ativo === '0') {
    $sql .= " AND p.ativo = :a";
    $params[':a'] = (int)$ativo;
  }

  $sql .= " ORDER BY p.nome LIMIT 300";

  $rows = [];
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $rows = [];
  }

  // Flash
  $ok  = (int)($_GET['ok'] ?? 0);
  $err = (int)($_GET['err'] ?? 0);
  $msg = (string)($_GET['msg'] ?? '');

  return [
    'csrf'   => $csrf,
    'q'      => $q,
    'setor'  => $setor,
    'ativo'  => $ativo,
    'rows'   => $rows,
    'ok'     => $ok,
    'err'    => $err,
    'msg'    => $msg,
  ];
}

// autoErp/public/estoque/controllers/produtosController.php
// ... (mantenha o que você já tem)

function produto_get_by_id(PDO $pdo, string $empresaCnpj, int $id): ?array {
  $st = $pdo->prepare("
    SELECT *
    FROM produtos_peca
    WHERE id = :id AND empresa_cnpj = :c
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':c'=>$empresaCnpj]);
  $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  return $r ?: null;
}

function produto_update(PDO $pdo, string $empresaCnpj, array $data): bool {
  $sql = "
    UPDATE produtos_peca
       SET categoria_id   = :categoria_id,
           nome           = :nome,
           sku            = :sku,
           ean            = :ean,
           marca          = :marca,
           unidade        = :unidade,
           preco_custo    = :preco_custo,
           preco_venda    = :preco_venda,
           estoque_atual  = :estoque_atual,
           estoque_minimo = :estoque_minimo,
           ativo          = :ativo
     WHERE id = :id AND empresa_cnpj = :c
     LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  return $st->execute([
    ':categoria_id'   => $data['categoria_id'] ?? null,
    ':nome'           => (string)$data['nome'],
    ':sku'            => $data['sku'] ?? null,
    ':ean'            => $data['ean'] ?? null,
    ':marca'          => $data['marca'] ?? null,
    ':unidade'        => $data['unidade'] ?? 'UN',
    ':preco_custo'    => (float)($data['preco_custo'] ?? 0),
    ':preco_venda'    => (float)$data['preco_venda'],
    ':estoque_atual'  => (float)($data['estoque_atual'] ?? 0),
    ':estoque_minimo' => (float)($data['estoque_minimo'] ?? 0),
    ':ativo'          => (int)($data['ativo'] ?? 0),
    ':id'             => (int)$data['id'],
    ':c'              => $empresaCnpj,
  ]);
}
