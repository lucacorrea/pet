<?php
// autoErp/admin/actions/empresaAtivar.php
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
  // Ativa a empresa. (Por padrão, NÃO reativa usuários automaticamente)
  $upEmp = $pdo->prepare("UPDATE empresas_peca SET status='ativa' WHERE id=:id LIMIT 1");
  $upEmp->execute([':id' => $id]);

  header('Location: ../pages/empresa.php?ok=1&msg=' . urlencode('Empresa ativada. Os usuários permanecem com o status atual.'));
  exit;
} catch (Throwable $e) {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Falha ao ativar empresa.'));
  exit;
}
