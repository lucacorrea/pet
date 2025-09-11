<?php
// autoErp/public/estoque/actions/produtosExcluir.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== DEBUG (ligue em dev) ===== */
const APP_DEBUG = true;
if (APP_DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); }

/* ===== Helpers ===== */
function back_to(string $relPath, array $qs = []): void {
  $q = $qs ? ('?' . http_build_query($qs)) : '';
  header('Location: ' . $relPath . $q);
  exit;
}
function log_app(string $msg): void {
  @file_put_contents(
    __DIR__ . '/../../../storage/app.log',
    '['.date('Y-m-d H:i:s').'] produtosExcluir: ' . $msg . PHP_EOL,
    FILE_APPEND
  );
}

/* ===== Guard / Método ===== */
require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post();
guard_empresa_user(['super_admin','dono','administrativo','estoque']);

/* ===== Conexão: tenta múltiplos caminhos ===== */
$pdo = null;
$connPaths = [
  realpath(__DIR__ . '/../../../conexao/conexao.php'),
  realpath(__DIR__ . '/../../../conexao.php'),
  realpath(__DIR__ . '/../../conexao/conexao.php'),
  realpath(__DIR__ . '/../../conexao.php'),
];
$loaded = false;
foreach ($connPaths as $p) {
  if ($p && file_exists($p)) {
    require_once $p; // deve definir $pdo
    $loaded = true;
    break;
  }
}
if (!$loaded) {
  log_app('Arquivo de conexão não encontrado.');
  back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Conexão indisponível.']);
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  } else {
    log_app('Instância PDO não disponível.');
    back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Conexão indisponível.']);
  }
}
if (APP_DEBUG) { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

/* ===== CSRF ===== */
$csrfForm = (string)($_POST['csrf'] ?? '');
$csrfSess = (string)($_SESSION['csrf_produtos'] ?? '');
if ($csrfSess === '' || !hash_equals($csrfSess, $csrfForm)) {
  back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Token de segurança inválido.']);
}

/* ===== Empresa da sessão ===== */
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Empresa não vinculada ao usuário.']);
}

/* ===== Entrada ===== */
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Identificador inválido.']);
}

/* ===== Excluir ===== */
try {
  $sql = <<<SQL
DELETE FROM produtos_peca
 WHERE id = :id
   AND empresa_cnpj = :c
 LIMIT 1
SQL;

  $st = $pdo->prepare($sql);
  $st->execute([':id' => $id, ':c' => $empresaCnpj]);

  if ($st->rowCount() < 1) {
    back_to('../pages/produtos.php', ['err'=>1,'msg'=>'Produto não encontrado ou não pertence à sua empresa.']);
  }

  back_to('../pages/produtos.php', ['ok'=>1,'msg'=>'Produto excluído com sucesso.']);

} catch (Throwable $e) {
  log_app('Erro ao excluir: '.$e->getMessage());
  $msg = 'Falha ao excluir produto.';
  if (APP_DEBUG) { $msg .= ' Detalhes: '.$e->getMessage(); }
  back_to('../pages/produtos.php', ['err'=>1,'msg'=>$msg]);
}
