<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

function lavadores_list_viewmodel(PDO $pdo): array {
  $vm = [
    'csrf'  => '',
    'ok'    => (int)($_GET['ok'] ?? 0),
    'err'   => (int)($_GET['err'] ?? 0),
    'msg'   => (string)($_GET['msg'] ?? ''),
    'ativo' => (string)($_GET['ativo'] ?? ''), // '', '1', '0'
    'rows'  => [],
  ];

  if (empty($_SESSION['csrf_lavadores_list'])) {
    $_SESSION['csrf_lavadores_list'] = bin2hex(random_bytes(32));
  }
  $vm['csrf'] = $_SESSION['csrf_lavadores_list'];

  $perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
  $empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

  try {
    $params = [];
    $sql = "SELECT id, empresa_cnpj, nome, cpf, telefone, email, ativo,
                   DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS criado
              FROM lavadores_peca";

    if ($perfil === 'super_admin') {
      $where = " WHERE 1=1";
    } else {
      if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
        $vm['err'] = 1;
        $vm['msg'] = 'Empresa não vinculada ao usuário.';
        return $vm;
      }
      $where = " WHERE empresa_cnpj = :c";
      $params[':c'] = $empresaCnpj;
    }

    if ($vm['ativo'] === '1' || $vm['ativo'] === '0') {
      $where .= " AND ativo = :a";
      $params[':a'] = (int)$vm['ativo'];
    }

    $sql .= $where . " ORDER BY nome ASC LIMIT 500";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $vm['rows'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $vm['err'] = 1;
    $vm['msg'] = 'Erro ao carregar lavadores' . (APP_DEBUG ? ': '.$e->getMessage() : '');
  }
  return $vm;
}
