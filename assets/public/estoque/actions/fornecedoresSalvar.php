<?php
// autoErp/public/estoque/actions/fornecedoresSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post();
guard_empresa_user(['dono','administrativo','estoque']);

$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/fornecedoresNovo.php?err=1&msg=' . urlencode('Conexão indisponível.')); exit;
}

function norm_cnpjcpf(?string $v): string { return preg_replace('/\D+/', '', (string)$v); }
function norm_cnpj(string $c): string { return preg_replace('/\D+/', '', $c); }
function back_to_new(string $msg, bool $ok): void {
  $qs = ($ok ? ['ok'=>1, 'msg'=>$msg] : ['err'=>1, 'msg'=>$msg]);
  header('Location: ../pages/fornecedoresNovo.php?' . http_build_query($qs));
  exit;
}

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_forn_novo']) || !hash_equals($_SESSION['csrf_forn_novo'], $csrf)) {
  back_to_new('Token inválido. Atualize a página.', false);
}

$empresaCnpj = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  back_to_new('Empresa não vinculada ao usuário.', false);
}

// Entrada
$nome     = trim((string)($_POST['nome'] ?? ''));
$doc      = norm_cnpjcpf($_POST['cnpj_cpf'] ?? '');
$email    = trim((string)($_POST['email'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$cep      = trim((string)($_POST['cep'] ?? ''));
$endereco = trim((string)($_POST['endereco'] ?? ''));
$cidade   = trim((string)($_POST['cidade'] ?? ''));
$estado   = strtoupper(trim((string)($_POST['estado'] ?? '')));
$obs      = trim((string)($_POST['obs'] ?? ''));
$ativo    = (int)($_POST['ativo'] ?? 1);

// Validações
$errs = [];
if ($nome === '' || mb_strlen($nome) > 180) $errs[] = 'Informe um nome válido (até 180).';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'E-mail inválido.';
if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) $errs[] = 'UF inválida.';
if ($doc !== '' && !preg_match('/^\d{11}$|^\d{14}$/', $doc)) $errs[] = 'CNPJ/CPF deve ter 11 ou 14 dígitos.';
if ($errs) back_to_new(implode(' ', $errs), false);

// Se houver doc, checa duplicidade (empresa + doc)
if ($doc !== '') {
  $st = $pdo->prepare("SELECT id FROM fornecedores_peca WHERE empresa_cnpj = :c AND cnpj_cpf = :d LIMIT 1");
  $st->execute([':c'=>$empresaCnpj, ':d'=>$doc]);
  if ($st->fetch()) back_to_new('Já existe fornecedor com este CNPJ/CPF.', false);
}

try {
  $ins = $pdo->prepare("
    INSERT INTO fornecedores_peca
      (empresa_cnpj, nome, cnpj_cpf, telefone, email, endereco, cidade, estado, cep, obs, ativo, criado_em)
    VALUES
      (:c, :nome, :doc, :tel, :email, :end, :cid, :uf, :cep, :obs, :ativo, NOW())
  ");
  $ins->execute([
    ':c'    => $empresaCnpj,
    ':nome' => $nome,
    ':doc'  => ($doc ?: null),
    ':tel'  => ($telefone ?: null),
    ':email'=> ($email ?: null),
    ':end'  => ($endereco ?: null),
    ':cid'  => ($cidade ?: null),
    ':uf'   => ($estado ?: null),
    ':cep'  => ($cep ?: null),
    ':obs'  => ($obs ?: null),
    ':ativo'=> $ativo ? 1 : 0,
  ]);

  back_to_new('Fornecedor cadastrado com sucesso.', true);

} catch (Throwable $e) {
  back_to_new('Falha ao salvar fornecedor.', false);
}
