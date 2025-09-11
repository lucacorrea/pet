<?php
// autoErp/public/estoque/actions/produtosBuscar.php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','estoque']); // segurança básica

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode(['ok'=>0,'msg'=>'Conexão indisponível.']);
  exit;
}

// Empresa da sessão
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  http_response_code(401);
  echo json_encode(['ok'=>0,'msg'=>'Empresa não vinculada ao usuário.']);
  exit;
}

// Parâmetros
$q      = trim((string)($_GET['q'] ?? ''));
$setor  = strtolower(trim((string)($_GET['setor'] ?? '')));   // '', 'autopeca', 'lavajato'
$ativo  = (string)($_GET['ativo'] ?? '');                     // '', '1', '0'
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 100)));

// Monta a query base
$sql = "
  SELECT
    p.id,
    p.nome,
    p.sku,
    p.ean,
    p.marca,
    p.preco_venda,
    p.estoque_atual,
    p.ativo,
    f.nome AS fornecedor_nome,
    CASE WHEN LOWER(COALESCE(cat.nome,'')) = 'lava jato' THEN 'lavajato' ELSE 'autopeca' END AS setor
  FROM produtos_peca p
  LEFT JOIN categorias_peca cat ON cat.id = p.categoria_id
  LEFT JOIN fornecedores_peca f ON f.id = p.fornecedor_id
  WHERE p.empresa_cnpj = :c
";
$params = [':c' => $empresaCnpj];

// Filtro de texto
if ($q !== '') {
  $sql .= " AND (p.nome LIKE :q OR p.sku LIKE :q OR p.ean LIKE :q OR p.marca LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}

// Filtro setor
if ($setor === 'autopeca') {
  $sql .= " AND (LOWER(COALESCE(cat.nome,'')) <> 'lava jato' OR cat.id IS NULL)";
} elseif ($setor === 'lavajato') {
  $sql .= " AND LOWER(COALESCE(cat.nome,'')) = 'lava jato'";
}

// Filtro ativo
if ($ativo === '1' || $ativo === '0') {
  $sql .= " AND p.ativo = :a";
  $params[':a'] = (int)$ativo;
}

$sql .= " ORDER BY p.nome LIMIT {$limit}";

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Sugestões (apenas nomes distintos)
  $sug = [];
  if ($q !== '') {
    $sq = "
      SELECT DISTINCT p.nome
      FROM produtos_peca p
      LEFT JOIN categorias_peca cat ON cat.id = p.categoria_id
      WHERE p.empresa_cnpj = :c
        AND p.nome LIKE :q
      ORDER BY p.nome
      LIMIT 10
    ";
    $st2 = $pdo->prepare($sq);
    $st2->execute([':c'=>$empresaCnpj, ':q'=>'%'.$q.'%']);
    $sug = array_map(static fn($r) => (string)$r['nome'], $st2->fetchAll(PDO::FETCH_ASSOC) ?: []);
  }

  echo json_encode(['ok'=>1, 'rows'=>$rows, 'suggestions'=>$sug], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>0, 'msg'=>'Erro ao buscar produtos.']);
}
