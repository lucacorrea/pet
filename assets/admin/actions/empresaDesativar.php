<?php
// autoErp/admin/actions/empresaDesativar.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin();
require_post();

if (session_status() === PHP_SESSION_NONE) session_start();

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], $csrf)) {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Token inválido')); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Empresa inválida')); exit;
}

require_once __DIR__ . '/../../conexao/conexao.php';

try {
  $pdo->beginTransaction();

  // pega CNPJ
  $st = $pdo->prepare("SELECT cnpj FROM empresas_peca WHERE id = :id LIMIT 1");
  $st->execute([':id' => $id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['cnpj'])) {
    throw new RuntimeException('Empresa não encontrada.');
  }
  $cnpj = preg_replace('/\D+/', '', (string)$row['cnpj']);

  // desativa empresa
  $upEmp = $pdo->prepare("UPDATE empresas_peca SET status='inativa' WHERE id=:id LIMIT 1");
  $upEmp->execute([':id' => $id]);

  // desativa TODOS os usuários da empresa
  $upUsers = $pdo->prepare("UPDATE usuarios_peca SET status=0 WHERE empresa_cnpj=:c");
  $upUsers->execute([':c' => $cnpj]);

  $pdo->commit();

  header('Location: ../pages/empresa.php?ok=1&msg=' . urlencode('Empresa inativada e usuários desativados.'));
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Falha ao inativar empresa.'));
  exit;
}
