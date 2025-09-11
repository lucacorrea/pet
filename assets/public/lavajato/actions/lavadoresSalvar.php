<?php
// autoErp/public/lavajato/actions/lavadoresSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../conexao/conexao.php';
require_once __DIR__ . '/../../../lib/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../pages/lavadoresNovo.php?err=1&msg=Requisi%C3%A7%C3%A3o+inv%C3%A1lida');
  exit;
}

$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) {
  header('Location: ../pages/lavadoresNovo.php?err=1&msg=Empresa+n%C3%A3o+vinculada');
  exit;
}

$nome = trim((string)($_POST['nome'] ?? ''));
$cpf = preg_replace('/\D+/', '', (string)($_POST['cpf'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$ativo = isset($_POST['ativo']) && $_POST['ativo'] == '0' ? 0 : 1;

if ($nome === '') {
  header('Location: ../pages/lavadoresNovo.php?err=1&msg=Nome+obrigat%C3%B3rio');
  exit;
}

try {
  $st = $pdo->prepare("INSERT INTO lavadores_peca (empresa_cnpj, nome, cpf, telefone, email, ativo)
                      VALUES (:empresa_cnpj, :nome, :cpf, :telefone, :email, :ativo)");
  $st->execute([
    ':empresa_cnpj' => $empresaCnpjSess,
    ':nome' => $nome,
    ':cpf' => $cpf ?: null,
    ':telefone' => $telefone ?: null,
    ':email' => $email ?: null,
    ':ativo' => $ativo,
  ]);

  header('Location: ../pages/lavadoresNovo.php?ok=1&msg=Lavador+cadastrado+com+sucesso');
  exit;
} catch (Throwable $e) {
  header('Location: ../pages/lavadoresNovo.php?err=1&msg=Erro+ao+cadastrar+lavador');
  exit;
}
