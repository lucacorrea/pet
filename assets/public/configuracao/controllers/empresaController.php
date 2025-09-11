<?php
// autoErp/public/configuracao/controllers/empresaController.php
declare(strict_types=1);

function empresa_config_viewmodel(PDO $pdo): array {
  if (session_status() === PHP_SESSION_NONE) session_start();

  if (empty($_SESSION['csrf_cfg_empresa'])) {
    $_SESSION['csrf_cfg_empresa'] = bin2hex(random_bytes(32));
  }

  // CNPJ/CPF da sessão (Vieram do LOGIN!)
  $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
  if (strlen($cnpj) !== 14) {
    header('Location: /autoErp/public/dashboard.php?err=1&msg=' . urlencode('Empresa não vinculada ao usuário.')); exit;
  }

  // Busca empresa
  $st = $pdo->prepare("SELECT * FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $cnpj]);
  $empresa = $st->fetch(PDO::FETCH_ASSOC);
  if (!$empresa) {
    header('Location: /autoErp/public/dashboard.php?err=1&msg=' . urlencode('Empresa não encontrada.')); exit;
  }

  // Permite edição apenas para dono
  $canEdit = (($_SESSION['user_perfil'] ?? '') === 'dono');

  $cnpjFmt = (function(string $n): string {
    $n = preg_replace('/\D+/', '', $n);
    if (strlen($n) !== 14) return $n;
    return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
  })($cnpj);

  $ok  = (int)($_GET['ok'] ?? 0);
  $err = (int)($_GET['err'] ?? 0);
  $msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');

  return [
    'empresa'      => $empresa,
    'cnpj'         => $cnpj,
    'cnpjFmt'      => $cnpjFmt,
    'canEdit'      => $canEdit,
    'readonlyAttr' => $canEdit ? '' : 'readonly',
    'csrf'         => $_SESSION['csrf_cfg_empresa'],
    'ok'           => $ok,
    'err'          => $err,
    'msg'          => $msg,
  ];
}
