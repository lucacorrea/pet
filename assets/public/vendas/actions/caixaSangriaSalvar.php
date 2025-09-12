<?php
// autoErp/public/caixa/actions/caixaSangriaSalvar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_sangria']) || !hash_equals($_SESSION['csrf_sangria'], $csrf)) {
  header('Location: ../pages/caixaSangria.php?err=1&msg=Token inválido'); exit;
}

$empresaCnpj = preg_replace('/\D+/', '', (string)($_POST['empresa_cnpj'] ?? ''));
$caixaId     = (int)($_POST['caixa_id'] ?? 0);
$valor       = (float)($_POST['valor'] ?? 0);
$obs         = trim((string)($_POST['observacao'] ?? ''));
$criadoCpf   = preg_replace('/\D+/', '', (string)($_POST['criado_por_cpf'] ?? ''));

if ($valor <= 0 || !$empresaCnpj || !$caixaId) {
  header('Location: ../pages/caixaSangria.php?err=1&msg=Dados inválidos'); exit;
}

// valida caixa aberto
try {
  $st = $pdo->prepare("SELECT id FROM caixas_peca WHERE id=:id AND empresa_cnpj=:c AND status='aberto' LIMIT 1");
  $st->execute([':id'=>$caixaId, ':c'=>$empresaCnpj]);
  if (!$st->fetch()) { header('Location: ../pages/caixaSangria.php?err=1&msg=Caixa não está aberto'); exit; }
} catch (Throwable $e) {
  header('Location: ../pages/caixaSangria.php?err=1&msg=Falha ao validar caixa'); exit;
}

// insere
try {
  $ins = $pdo->prepare("
    INSERT INTO caixa_mov_peca (empresa_cnpj, caixa_id, tipo, valor, observacao, criado_por_cpf)
    VALUES (:c,:id,'sangria',:v,:o,:cpf)
  ");
  $ins->execute([
    ':c'=>$empresaCnpj, ':id'=>$caixaId, ':v'=>$valor,
    ':o'=>$obs ?: null, ':cpf'=>$criadoCpf ?: null
  ]);
  header('Location: ../pages/caixaSangria.php?ok=1&msg=' . urlencode('Sangria registrada!')); exit;
} catch (Throwable $e) {
  header('Location: ../pages/caixaSangria.php?err=1&msg=' . urlencode('Falha ao registrar sangria')); exit;
}
