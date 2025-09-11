<?php
// autoErp/admin/controllers/cadastrarUsuarioController.php
declare(strict_types=1);

// Garante $pdo vindo da página
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
  if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao; // define $pdo
  } else {
    throw new RuntimeException('Conexão indisponível.');
  }
}

/** Carrega empresas ATIVAS para o <select> */
$empresas = [];
try {
  $st = $pdo->query("SELECT cnpj, nome_fantasia FROM empresas_peca WHERE status = 'ativa' ORDER BY nome_fantasia ASC");
  $empresas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $empresas = [];
}
