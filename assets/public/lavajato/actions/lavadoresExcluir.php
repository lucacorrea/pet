<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post();
guard_empresa_user(['super_admin','dono','administrativo']);

$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_lavadores_list']) || !hash_equals($_SESSION['csrf_lavadores_list'], $csrf)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Token inválido.')); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('ID inválido.')); exit;
}

$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

try {
  if ($perfil === 'super_admin') {
    $st = $pdo->prepare("DELETE FROM lavadores_peca WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
  } else {
    if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
      header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Empresa não vinculada.')); exit;
    }
    $st = $pdo->prepare("DELETE FROM lavadores_peca WHERE id = :id AND empresa_cnpj = :c LIMIT 1");
    $st->execute([':id'=>$id, ':c'=>$empresaCnpj]);
  }

  header('Location: ../pages/lavadores.php?ok=1&msg=' . urlencode('Lavador excluído.'));
  exit;
} catch (Throwable $e) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Erro ao excluir.')); exit;
}
