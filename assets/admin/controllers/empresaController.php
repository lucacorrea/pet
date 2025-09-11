<?php
// autoErp/admin/controllers/empresaController.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

// Conexão
$pdo = $pdo ?? null;
if (!$pdo) {
  $path = realpath(__DIR__ . '/../../conexao/conexao.php');
  if ($path) require_once $path;
}

// Filtros
$status  = $_GET['status'] ?? 'ativa';        // ativa | inativa | todas
$buscar  = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$allowed = ['ativa','inativa','todas'];
if (!in_array($status, $allowed, true)) $status = 'ativa';

$rotuloStatus = [
  'ativa'   => 'Ativas',
  'inativa' => 'Inativas',
  'todas'   => 'Todas'
][$status];

// Totais por status
$totais = ['ativa' => 0, 'inativa' => 0, 'todas' => 0];
if ($pdo) {
  $totais['ativa']   = (int)$pdo->query("SELECT COUNT(*) FROM empresas_peca WHERE status='ativa'")->fetchColumn();
  $totais['inativa'] = (int)$pdo->query("SELECT COUNT(*) FROM empresas_peca WHERE status='inativa'")->fetchColumn();
  $totais['todas']   = $totais['ativa'] + $totais['inativa'];
}

// WHERE dinâmico
$wheres = [];
$params = [];

if ($status !== 'todas') {
  $wheres[] = "status = :st";
  $params[':st'] = $status;
}
if ($buscar !== '') {
  $num = preg_replace('/\D+/', '', $buscar);
  $wheres[] = "(nome_fantasia LIKE :q OR email LIKE :q OR telefone LIKE :q OR cnpj LIKE :qNum)";
  $params[':q']    = "%{$buscar}%";
  $params[':qNum'] = "%{$num}%";
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Count total p/ paginação
$totalRows = 0;
$empresas  = [];
if ($pdo) {
  $stC = $pdo->prepare("SELECT COUNT(*) FROM empresas_peca $whereSql");
  $stC->execute($params);
  $totalRows = (int)$stC->fetchColumn();

  $st = $pdo->prepare("
    SELECT id, cnpj, nome_fantasia, email, telefone, status, criado_em
      FROM empresas_peca
      $whereSql
     ORDER BY criado_em DESC
     LIMIT :lim OFFSET :off
  ");
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset,  PDO::PARAM_INT);
  $st->execute();
  $empresas = $st->fetchAll(PDO::FETCH_ASSOC);
}
$pages = max(1, (int)ceil($totalRows / $perPage));
