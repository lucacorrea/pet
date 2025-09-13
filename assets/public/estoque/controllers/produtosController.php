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
    $sql .= " AND (c.nome = 'Pet Shop')";
  } elseif ($setor === 'petshop') {
    $sql .= " AND (c.nome = 'Pet Shop')";
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
