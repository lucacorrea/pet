<?php
// autoErp/public/estoque/actions/produtoEditar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']);

// Conexão
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// ---------- helpers ----------
function go_back(int $id, array $qs = []): void {
  $base = '../pages/produtosEditar.php?id=' . $id;
  if ($qs) $base .= '&' . http_build_query($qs);
  header('Location: ' . $base);
  exit;
}
function clean_cnpj(string $s): string { return preg_replace('/\D+/', '', $s); }
/** Converte "1.234,56" -> "1234.56" e fixa escala. */
function to_decimal($v, int $scale = 2): string {
  $s = trim((string)$v);
  if ($s === '') return number_format(0, $scale, '.', '');
  $s = preg_replace('/[^\d,.\-]/', '', $s);
  if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } elseif (strpos($s, ',') !== false) {
    $s = str_replace(',', '.', $s);
  }
  return number_format((float)$s, $scale, '.', '');
}

// ---------- validações básicas ----------
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ../pages/produtos.php?err=1&msg=Metodo+invalido');
  exit;
}

// CSRF
$csrfForm = (string)($_POST['csrf'] ?? '');
$csrfSess = (string)($_SESSION['csrf_prod_edit'] ?? '');
if (!$csrfForm || !$csrfSess || !hash_equals($csrfSess, $csrfForm)) {
  header('Location: ../pages/produtos.php?err=1&msg=CSRF+invalido');
  exit;
}

// empresa da sessão
$empresaCnpj = clean_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/produtos.php?err=1&msg=Empresa+nao+vinculada');
  exit;
}

// id do produto
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: ../pages/produtos.php?err=1&msg=Produto+invalido');
  exit;
}

// Confere se o produto pertence à empresa (multi-tenant seguro)
$stChk = $pdo->prepare("SELECT id FROM produtos_peca WHERE id = :id AND empresa_cnpj_num = :c LIMIT 1");
$stChk->bindValue(':id', $id, PDO::PARAM_INT);
$stChk->bindValue(':c',  $empresaCnpj, PDO::PARAM_STR);
$stChk->execute();
if (!$stChk->fetchColumn()) {
  header('Location: ../pages/produtos.php?err=1&msg=Produto+nao+encontrado');
  exit;
}

// ---------- coleta & saneamento do POST ----------
$nome           = trim((string)($_POST['nome'] ?? ''));
$categoria_id   = trim((string)($_POST['categoria_id'] ?? ''));
$sku            = trim((string)($_POST['sku'] ?? ''));
$ean            = trim((string)($_POST['ean'] ?? ''));
$marca          = trim((string)($_POST['marca'] ?? ''));
$unidade        = trim((string)($_POST['unidade'] ?? 'UN'));

$preco_custo    = to_decimal($_POST['preco_custo']    ?? '', 2);
$preco_venda    = to_decimal($_POST['preco_venda']    ?? '', 2);
$estoque_atual  = to_decimal($_POST['estoque_atual']  ?? '', 3);
$estoque_minimo = to_decimal($_POST['estoque_minimo'] ?? '', 3);

$ativo          = isset($_POST['ativo']) ? 1 : 0;

// Regras mínimas
if ($nome === '') go_back($id, ['err' => 1, 'msg' => 'Informe o nome do produto.']);
if ($unidade === '') $unidade = 'UN';

// categoria pode ser null
$categoria_id_val = ($categoria_id !== '' ? (int)$categoria_id : null);

// ---------- update ----------
$sql = "
  UPDATE produtos_peca
     SET nome           = :nome,
         categoria_id   = :categoria_id,
         sku            = :sku,
         ean            = :ean,
         marca          = :marca,
         unidade        = :unidade,
         preco_custo    = :preco_custo,
         preco_venda    = :preco_venda,
         estoque_atual  = :estoque_atual,
         estoque_minimo = :estoque_minimo,
         ativo          = :ativo
   WHERE id = :id AND empresa_cnpj_num = :c
   LIMIT 1
";

try {
  $st = $pdo->prepare($sql);

  // binds principais
  $st->bindValue(':nome', $nome, PDO::PARAM_STR);
  if ($categoria_id_val === null) $st->bindValue(':categoria_id', null, PDO::PARAM_NULL);
  else $st->bindValue(':categoria_id', $categoria_id_val, PDO::PARAM_INT);

  // campos opcionais permitem NULL
  foreach ([':sku' => $sku, ':ean' => $ean, ':marca' => $marca] as $k => $v) {
    if ($v === '') $st->bindValue($k, null, PDO::PARAM_NULL);
    else           $st->bindValue($k, $v, PDO::PARAM_STR);
  }

  $st->bindValue(':unidade', $unidade, PDO::PARAM_STR);

  // decimais como string (evita locale)
  $st->bindValue(':preco_custo',    $preco_custo,    PDO::PARAM_STR);
  $st->bindValue(':preco_venda',    $preco_venda,    PDO::PARAM_STR);
  $st->bindValue(':estoque_atual',  $estoque_atual,  PDO::PARAM_STR);
  $st->bindValue(':estoque_minimo', $estoque_minimo, PDO::PARAM_STR);

  $st->bindValue(':ativo', (int)$ativo, PDO::PARAM_INT);
  $st->bindValue(':id', (int)$id, PDO::PARAM_INT);
  $st->bindValue(':c', $empresaCnpj, PDO::PARAM_STR);

  $st->execute();

  go_back($id, ['ok' => 1, 'msg' => 'Produto atualizado com sucesso.']);
} catch (Throwable $e) {
  go_back($id, ['err' => 1, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
}
