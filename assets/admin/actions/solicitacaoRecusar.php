<?php
// autoErp/admin/actions/solicitacaoRecusar.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin();
require_post();

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], $csrf)) {
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Token inválido')); exit;
}

$solId = (int)($_POST['sol_id'] ?? 0);
if ($solId <= 0) {
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Solicitação inválida.')); exit;
}

require_once __DIR__ . '/../../conexao/conexao.php';

try {
  $up = $pdo->prepare("UPDATE solicitacoes_empresas_peca SET status='recusada' WHERE id=:id AND status='pendente' LIMIT 1");
  $up->execute([':id' => $solId]);

  if ($up->rowCount() < 1) {
    header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Não foi possível recusar (talvez já processada).')); exit;
  }
  header('Location: ../pages/solicitacao.php?ok=1&msg=' . urlencode('Solicitação recusada.')); exit;
} catch (Throwable $e) {
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Falha ao recusar.')); exit;
}
