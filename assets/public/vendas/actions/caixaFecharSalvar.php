<?php
// autoErp/public/caixa/actions/caixaFecharPost.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Empresa não vinculada ao usuário.'));
  exit;
}

$usuarioCpf = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// ===== valida CSRF (usa a mesma chave da página: csrf_caixa_fechar)
$csrf = (string)($_POST['csrf'] ?? '');
if (!$csrf || !hash_equals((string)($_SESSION['csrf_caixa_fechar'] ?? ''), $csrf)) {
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Sessão expirada. Tente novamente.'));
  exit;
}

// ===== entradas do formulário (nomes alinhados com a página)
$cxId         = (int)($_POST['caixa_id'] ?? 0);
$observacoes  = trim((string)($_POST['observacoes'] ?? ''));
$dinheiroBr   = (string)($_POST['dinheiro_contado'] ?? '');

// normaliza número BR/US (aceita "1.234,56" e "1234.56")
$dinheiroContado = 0.0;
if ($dinheiroBr !== '') {
  $dinheiroContado = (float)str_replace(',', '.', preg_replace('/\./', '', $dinheiroBr));
}

try {
  $pdo->beginTransaction();

  // Confere se o caixa ainda está aberto e pertence à empresa
  $stSel = $pdo->prepare("
    SELECT id, observacoes
    FROM caixas_peca
    WHERE id = :id AND empresa_cnpj = :c AND status = 'aberto'
    FOR UPDATE
  ");
  $stSel->execute([':id' => $cxId, ':c' => $empresaCnpj]);
  $caixaRow = $stSel->fetch(PDO::FETCH_ASSOC);

  if (!$caixaRow) {
    $pdo->rollBack();
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Caixa não encontrado ou já fechado.'));
    exit;
  }

  // Anexa o dinheiro contado nas observações (schema não tem campos próprios pra isso)
  $obsAtual = trim((string)($caixaRow['observacoes'] ?? ''));
  $anexo    = $dinheiroBr !== '' ? 'Dinheiro contado: R$ ' . number_format($dinheiroContado, 2, ',', '.') : '';
  $obsFinal = trim(implode(' | ', array_filter([$obsAtual, $observacoes, $anexo], fn($s) => $s !== '')));

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
    ':obs' => $obsFinal ?: null,
    ':id'  => $cxId,
    ':c'   => $empresaCnpj,
  ]);

  if ($stUp->rowCount() < 1) {
    $pdo->rollBack();
    header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Não foi possível fechar o caixa.'));
    exit;
  }

  $pdo->commit();

  // Evita re-post
  unset($_SESSION['csrf_caixa_fechar']);

  header('Location: ../pages/caixaFechar.php?ok=1&msg=' . urlencode('Caixa fechado com sucesso.'));
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/caixaFechar.php?err=1&msg=' . urlencode('Erro inesperado ao fechar caixa.'));
  exit;
}
?>