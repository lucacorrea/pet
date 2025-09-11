<?php
// autoErp/public/lavajato/controllers/lavagemRapidaController.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['super_admin','dono','administrativo','caixa']);

require_once __DIR__ . '/../../../lib/util.php';

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!$pdo instanceof PDO) die('Conexão indisponível.');

// Nome empresa
$empresaNome = empresa_nome_logada($pdo) ?: 'Sua empresa';

// Sessão
$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if ($perfil !== 'super_admin') {
  if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) die('Empresa não vinculada ao usuário.');
}

// CSRF
if (empty($_SESSION['csrf_lavagem_rapida'])) {
  $_SESSION['csrf_lavagem_rapida'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_lavagem_rapida'];

// Flash
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// Serviços
$servicos = [];
try {
  if ($perfil !== 'super_admin' || preg_match('/^\d{14}$/', $empresaCnpjSess)) {
    $st = $pdo->prepare("
      SELECT id, nome, COALESCE(valor_padrao,0) AS valor_padrao
      FROM categorias_lavagem_peca
      WHERE empresa_cnpj = :c AND ativo = 1
      ORDER BY nome
    ");
    $st->execute([':c' => $empresaCnpjSess]);
    $servicos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) { $servicos = []; }

// Lavadores
$lavadores = [];
try {
  if ($perfil !== 'super_admin' || preg_match('/^\d{14}$/', $empresaCnpjSess)) {
    $st = $pdo->prepare("
      SELECT id, nome, cpf
      FROM lavadores_peca
      WHERE empresa_cnpj = :c AND ativo = 1
      ORDER BY nome
    ");
    $st->execute([':c' => $empresaCnpjSess]);
    $lavadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) { $lavadores = []; }


// ViewModel
$vm = [
  'empresaNome' => $empresaNome,
  'csrf'        => $csrf,
  'ok'          => $ok,
  'err'         => $err,
  'msg'         => $msg,
  'servicos'    => $servicos,
  'lavadores'   => $lavadores,
  'lavagens'    => $lavagens,
];
