<?php
// autoErp/public/vendas/controllers/vendaRapida.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// ===== Empresa
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

// ===== CSRF
if (empty($_SESSION['csrf_venda_rapida'])) {
  $_SESSION['csrf_venda_rapida'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_venda_rapida'];

// ===== Operador logado
$operadorCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
$operadorNome = (string)($_SESSION['user_nome'] ?? '');
$userEmail    = (string)($_SESSION['user_email'] ?? '');
$userId       = (string)($_SESSION['user_id'] ?? '');

if ($operadorCpf === '' && $userEmail !== '') {
  try {
    $stOp = $pdo->prepare("SELECT cpf, nome FROM usuarios_peca WHERE email = :e LIMIT 1");
    $stOp->execute([':e' => $userEmail]);
    if ($row = $stOp->fetch(PDO::FETCH_ASSOC)) {
      $operadorCpf  = preg_replace('/\D+/', '', (string)($row['cpf'] ?? ''));
      if ($operadorNome === '' && !empty($row['nome'])) $operadorNome = $row['nome'];
      $_SESSION['user_cpf']  = $operadorCpf;
      if (!empty($row['nome'])) $_SESSION['user_nome'] = $row['nome'];
    }
  } catch (Throwable $e) {}
}
if ($operadorCpf === '') {
  if ($userId !== '') {
    $operadorCpf = 'UID' . preg_replace('/\D+/', '', $userId);
  } else {
    die('Operador inválido.');
  }
}

// ===== Verifica caixa ABERTO e se o operador pode vender
$caixaAberto = null;
$podeVender  = false;

try {
  $stCx = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS quando,
           aberto_por_cpf
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY aberto_em DESC
    LIMIT 1
  ");
  $stCx->execute([':c' => $empresaCnpj]);
  $caixaAberto = $stCx->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($caixaAberto) {
    if ($caixaAberto['tipo'] === 'individual') {
      $podeVender = ($operadorCpf !== '' && $operadorCpf === preg_replace('/\D+/', '', (string)$caixaAberto['aberto_por_cpf']));
    } else {
      $stP = $pdo->prepare("
        SELECT 1
        FROM caixa_participantes_peca
        WHERE caixa_id = :cid AND empresa_cnpj = :c AND operador_cpf = :cpf AND ativo = 1
        LIMIT 1
      ");
      $stP->execute([
        ':cid' => (int)$caixaAberto['id'],
        ':c'   => $empresaCnpj,
        ':cpf' => $operadorCpf,
      ]);
      $podeVender = (bool)$stP->fetchColumn();
    }
  }
} catch (Throwable $e) {
  $caixaAberto = null;
  $podeVender  = false;
}

// ===== Produtos (autocomplete)
$produtos = [];
try {
  $st = $pdo->prepare("
    SELECT id, nome, sku, ean, marca, unidade, preco_venda
    FROM produtos_peca
    WHERE empresa_cnpj = :c AND ativo = 1
    ORDER BY nome
    LIMIT 2000
  ");
  $st->execute([':c' => $empresaCnpj]);
  $produtos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $produtos = [];
}

// ===== Variáveis para a view
$VIEW_VARS = [
  'empresaNome' => $empresaNome,
  'csrf'        => $csrf,
  'produtos'    => $produtos,
  'caixaAberto' => $caixaAberto,
  'podeVender'  => $podeVender,
];

// Renderiza a view
extract($VIEW_VARS, EXTR_SKIP);
require __DIR__ . '/../pages/vendaRapida.php';
