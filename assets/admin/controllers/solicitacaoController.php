<?php
// autoErp/admin/controllers/solicitacaoController.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin(); // só super_admin acessa

// token CSRF p/ formulários de ação
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

$nomeUser = $_SESSION['user_nome'] ?? 'Super Admin';

// Conexão
$pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
$hasDb = false;
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao;
  $hasDb = isset($pdo) && ($pdo instanceof PDO);
}

$status = $_GET['status'] ?? 'pendente';              // pendente|aprovada|recusada|todas
$buscar = trim($_GET['q'] ?? '');                     // busca texto
$validStatus = ['pendente','aprovada','recusada','todas'];
if (!in_array($status, $validStatus, true)) $status = 'pendente';

// contadores rápidos
$totais = ['pendente'=>0,'aprovada'=>0,'recusada'=>0,'todas'=>0];
$solicitacoes = [];

if ($hasDb) {
  try {
    // totais
    $sqlCountBase = "SELECT status, COUNT(*) c FROM solicitacoes_empresas_peca GROUP BY status";
    foreach ($pdo->query($sqlCountBase) as $r) {
      $st = $r['status'];
      $totais[$st] = (int)$r['c'];
      $totais['todas'] += (int)$r['c'];
    }

    // listagem
    $where = [];
    $params = [];
    if ($status !== 'todas') {
      $where[] = "status = :st";
      $params[':st'] = $status;
    }
    if ($buscar !== '') {
      $where[] = "(nome_fantasia LIKE :q OR cnpj LIKE :q OR proprietario_nome LIKE :q OR email LIKE :q)";
      $params[':q'] = "%{$buscar}%";
    }
    $sql = "SELECT id, nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, status, criado_em
              FROM solicitacoes_empresas_peca";
    if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
    $sql .= ' ORDER BY criado_em DESC LIMIT 100';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $solicitacoes = [];
  }
}

// rótulo status
$rotuloStatus = [
  'pendente' => 'Pendentes',
  'aprovada' => 'Aprovadas',
  'recusada' => 'Recusadas',
  'todas'    => 'Todas'
][$status];

