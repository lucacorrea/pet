<?php
// autoErp/public/caixa/actions/caixaFecharPost.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');
$usuarioCpf = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// valida CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (!$csrf || !hash_equals((string)($_SESSION['csrf_fechar_caixa'] ?? ''), $csrf)) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Sessão expirada. Tente novamente.'));
  exit;
}

// entradas
$cxId = (int)($_POST['cx_id'] ?? 0);
$observacoes = trim((string)($_POST['observacoes'] ?? ''));

try {
  $pdo->beginTransaction();

  // Confere se o caixa ainda está aberto e pertence à empresa
  $stSel = $pdo->prepare("
    SELECT id FROM caixas_peca
    WHERE id = :id AND empresa_cnpj = :c AND status = 'aberto'
    FOR UPDATE
  ");
  $stSel->execute([':id' => $cxId, ':c' => $empresaCnpj]);
  $existe = (bool)$stSel->fetchColumn();

  if (!$existe) {
    $pdo->rollBack();
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Caixa não encontrado ou já fechado.'));
    exit;
  }

  // Fecha o caixa
  $stUp = $pdo->prepare("
    UPDATE caixas_peca
    SET status = 'fechado',
        fechado_por_cpf = :cpf,
        fechado_em = NOW(),
        observacoes = :obs
    WHERE id = :id AND empresa_cnpj = :c AND status = 'aberto'
    LIMIT 1
  ");
  $stUp->execute([
    ':cpf' => $usuarioCpf ?: null,
    ':obs' => $observacoes ?: null,
    ':id'  => $cxId,
    ':c'   => $empresaCnpj,
  ]);

  if ($stUp->rowCount() < 1) {
    $pdo->rollBack();
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Não foi possível fechar o caixa.'));
    exit;
  }

  $pdo->commit();

  // limpa token para evitar repost
  unset($_SESSION['csrf_fechar_caixa']);

  header('Location: ../pages/caixaFechar.php?ok=1&msg=' . urlencode('Caixa fechado com sucesso.'));
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Erro inesperado ao fechar caixa.'));
  exit;
}
