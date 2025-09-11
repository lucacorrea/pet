<?php
// autoErp/public/configuracao/actions/atualizar.php
declare(strict_types=1);

// 1) Sessão e guard
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../../lib/auth_guard.php';

// Aceita apenas POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Requisição inválida.')); exit;
}

// Somente DONO pode editar
guard_empresa_user(['dono']); // bloqueia quem não é dono

// 2) CSRF
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_cfg_empresa']) || !hash_equals($_SESSION['csrf_cfg_empresa'], $csrf)) {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Token inválido. Atualize a página.')); exit;
}

// 3) Conexão PDO ($pdo)
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // deve definir $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  } else {
    header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Conexão indisponível.')); exit;
  }
}

// 4) Helpers
$normDigits = static function (string $s): string {
  return preg_replace('/\D+/', '', $s);
};

// 5) Entrada
$cnpjForm      = $normDigits((string)($_POST['cnpj'] ?? ''));
$cnpjSess      = $normDigits((string)($_SESSION['user_empresa_cnpj'] ?? ''));
$nome_fantasia = trim((string)($_POST['nome_fantasia'] ?? ''));
$razao_social  = trim((string)($_POST['razao_social'] ?? ''));
$email         = trim((string)($_POST['email'] ?? ''));
$telefone      = trim((string)($_POST['telefone'] ?? ''));
$endereco      = trim((string)($_POST['endereco'] ?? ''));
$cep           = $normDigits((string)($_POST['cep'] ?? ''));
$cidade        = trim((string)($_POST['cidade'] ?? ''));
$estado        = strtoupper(substr(trim((string)($_POST['estado'] ?? '')), 0, 2)); // UF

// 6) Validações
$erros = [];

if ($cnpjForm === '' || strlen($cnpjForm) !== 14) {
  $erros[] = 'CNPJ inválido.';
}
if ($cnpjSess === '' || $cnpjForm !== $cnpjSess) {
  $erros[] = 'CNPJ não corresponde ao da sua sessão.';
}
if ($nome_fantasia === '') {
  $erros[] = 'Informe o Nome Fantasia.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $erros[] = 'E-mail inválido.';
}
if ($cep !== '' && strlen($cep) !== 8) {
  $erros[] = 'CEP deve ter 8 dígitos.';
}
if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
  $erros[] = 'UF deve conter 2 letras.';
}

if ($erros) {
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode(implode(' ', $erros))); exit;
}

// 7) Persistência (UPDATE se existir, senão INSERT)
// Obs.: status não é alterado aqui; fica a cargo do admin.
// Se não existir registro ainda (ex.: aprovado sem cadastro completo), criamos como 'ativa'.

try {
  // Existe?
  $st = $pdo->prepare("SELECT id, status FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $cnpjForm]);
  $row = $st->fetch();

  if ($row) {
    // UPDATE
    $up = $pdo->prepare("
      UPDATE empresas_peca
         SET nome_fantasia = :nf,
             razao_social  = :rs,
             email         = :em,
             telefone      = :tel,
             endereco      = :end,
             cidade        = :cid,
             estado        = :uf,
             cep           = :cep
       WHERE cnpj = :cnpj
       LIMIT 1
    ");
    $up->execute([
      ':nf'   => $nome_fantasia,
      ':rs'   => ($razao_social ?: null),
      ':em'   => ($email ?: null),
      ':tel'  => ($telefone ?: null),
      ':end'  => ($endereco ?: null),
      ':cid'  => ($cidade ?: null),
      ':uf'   => ($estado ?: null),
      ':cep'  => ($cep ?: null),
      ':cnpj' => $cnpjForm,
    ]);
  } else {
    // INSERT
    $ins = $pdo->prepare("
      INSERT INTO empresas_peca
        (cnpj, nome_fantasia, razao_social, telefone, email, endereco, cidade, estado, cep, status, criado_em)
      VALUES
        (:cnpj, :nf, :rs, :tel, :em, :end, :cid, :uf, :cep, 'ativa', NOW())
    ");
    $ins->execute([
      ':cnpj' => $cnpjForm,
      ':nf'   => $nome_fantasia,
      ':rs'   => ($razao_social ?: null),
      ':tel'  => ($telefone ?: null),
      ':em'   => ($email ?: null),
      ':end'  => ($endereco ?: null),
      ':cid'  => ($cidade ?: null),
      ':uf'   => ($estado ?: null),
      ':cep'  => ($cep ?: null),
    ]);
  }

  header('Location: ../pages/empresa.php?ok=1&msg=' . urlencode('Informações da empresa salvas com sucesso.')); exit;

} catch (Throwable $e) {
  // Você pode logar $e->getMessage() em um arquivo se quiser depurar
  header('Location: ../pages/empresa.php?err=1&msg=' . urlencode('Falha ao salvar os dados da empresa.')); exit;
}
