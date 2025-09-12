<?php
// autoErp/public/caixa/actions/caixaFecharSalvar.php
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
$usuarioCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (!$csrf || empty($_SESSION['csrf_caixa_fechar']) || !hash_equals($_SESSION['csrf_caixa_fechar'], $csrf)) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('CSRF inválido.'));
  exit;
}

$caixaId = (int)($_POST['caixa_id'] ?? 0);
$obs     = trim((string)($_POST['observacoes'] ?? ''));

if ($caixaId <= 0) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Caixa inválido.'));
  exit;
}

try {
  // Confere se o caixa está aberto e pertence à empresa
  $st = $pdo->prepare("
    SELECT id, tipo, empresa_cnpj, status, COALESCE(aberto_por_cpf,'') AS aberto_por_cpf
    FROM caixas_peca
    WHERE id = :id AND empresa_cnpj = :c AND status = 'aberto'
    LIMIT 1
  ");
  $st->execute([':id'=>$caixaId, ':c'=>$empresaCnpj]);
  $cx = $st->fetch(PDO::FETCH_ASSOC);

  if (!$cx) {
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Caixa não está mais aberto.'));
    exit;
  }

  // Regras: se for individual, só o mesmo CPF fecha; se compartilhado, qualquer papel permitido.
  $tipo = mb_strtolower(trim((string)$cx['tipo']));
  if (in_array($tipo, ['individual','indiv','ind'], true)) {
    $abertoPor = preg_replace('/\D+/', '', (string)$cx['aberto_por_cpf']);
    if (!$usuarioCpf || !$abertoPor || $abertoPor !== $usuarioCpf) {
      header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Este caixa individual foi aberto por outro operador.'));
      exit;
    }
  }

  // (opcional) Calcular saldo final aqui se quiser:
  // $saldoFinal = ...; // saldo_inicial + suprimentos + vendas - retiradas - troco
  // Por enquanto, apenas fecha e grava observações.

  $upd = $pdo->prepare("
    UPDATE caixas_peca
       SET status = 'fechado',
           fechado_por_cpf = :cpf,
           fechado_em = NOW(),
           observacoes = NULLIF(:obs, '')
     WHERE id = :id AND empresa_cnpj = :c AND status = 'aberto'
     LIMIT 1
  ");
  $ok = $upd->execute([
    ':cpf' => $usuarioCpf ?: null,
    ':obs' => $obs,
    ':id'  => $caixaId,
    ':c'   => $empresaCnpj
  ]);

  if (!$ok || $upd->rowCount() < 1) {
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Não foi possível fechar o caixa.'));
    exit;
  }

  // sucesso -> volta para Venda Rápida com flag ok
  header('Location: ../../vendas/pages/vendaRapida.php?ok=1&msg=' . urlencode('Caixa fechado com sucesso.'));
  exit;

} catch (Throwable $e) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Falha ao fechar o caixa.'));
  exit;
}
