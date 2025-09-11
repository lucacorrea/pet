<?php
// autoErp/public/vendas/actions/orcamentosStatus.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Empresa não vinculada.'));
  exit;
}

// valida CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['csrf_orcamentos'] ?? '', $csrf)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('CSRF inválido.'));
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$novo = (string)($_POST['status'] ?? '');

$permitidos = ['aberto','aprovado','rejeitado','expirado'];
if ($id <= 0 || !in_array($novo, $permitidos, true)) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Parâmetros inválidos.'));
  exit;
}

try {
  // confere dono
  $st = $pdo->prepare("SELECT id, status FROM orcamentos_peca WHERE id=:id AND empresa_cnpj=:c LIMIT 1");
  $st->execute([':id'=>$id, ':c'=>$empresaCnpj]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Orçamento não encontrado.'));
    exit;
  }

  // atualiza status
  $up = $pdo->prepare("UPDATE orcamentos_peca SET status=:s WHERE id=:id AND empresa_cnpj=:c");
  $up->execute([':s'=>$novo, ':id'=>$id, ':c'=>$empresaCnpj]);

  header('Location: ../pages/orcamentos.php?ok=1&msg=' . urlencode('Status atualizado para ' . $novo . '.'));
  exit;

} catch (Throwable $e) {
  header('Location: ../pages/orcamentos.php?err=1&msg=' . urlencode('Falha ao atualizar status.'));
  exit;
}
