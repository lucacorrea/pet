<?php
// autoErp/public/lavajato/controllers/lavagensController.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

/**
 * Carrega lavagens por período rápido.
 * range: today | yesterday | 7 | 15 | 30 (padrão: 7)
 */
function lavagens_list_viewmodel(PDO $pdo): array
{
  $vm = [
    'rows'   => [],
    'range'  => (string)($_GET['range'] ?? '7'),
    'ok'     => (int)($_GET['ok'] ?? 0),
    'err'    => (int)($_GET['err'] ?? 0),
    'msg'    => (string)($_GET['msg'] ?? ''),
    'resumo' => ['qtd' => 0, 'total' => 0.00],
  ];

  // empresa da sessão
  $empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
  if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
    $vm['err'] = 1;
    $vm['msg'] = 'Empresa não vinculada ao usuário.';
    return $vm;
  }

  // período
  $endSql = date('Y-m-d H:i:s');
  $range  = strtolower($vm['range']);
  switch ($range) {
    case 'today':
    case 'hoje':
      $startSql   = date('Y-m-d 00:00:00');
      $vm['range'] = 'today';
      break;
    case 'yesterday':
    case 'ontem':
      $startSql   = date('Y-m-d 00:00:00', strtotime('yesterday'));
      $endSql     = date('Y-m-d 00:00:00'); // 00:00 hoje
      $vm['range'] = 'yesterday';
      break;
    case '15':
      $startSql   = date('Y-m-d H:i:s', strtotime('-15 days'));
      $vm['range'] = '15';
      break;
    case '30':
      $startSql   = date('Y-m-d H:i:s', strtotime('-30 days'));
      $vm['range'] = '30';
      break;
    case '7':
    default:
      $startSql   = date('Y-m-d H:i:s', strtotime('-7 days'));
      $vm['range'] = '7';
      break;
  }

  try {
    // lista
    $sql = "
      SELECT
        COALESCE(l.categoria_nome,'Serviço') AS servico,
        CONCAT(
          TRIM(COALESCE(l.modelo,'')),
          CASE WHEN COALESCE(l.modelo,'') <> '' AND COALESCE(l.cor,'') <> '' THEN ' ' ELSE '' END,
          TRIM(COALESCE(l.cor,'')),
          CASE
            WHEN (COALESCE(l.modelo,'') <> '' OR COALESCE(l.cor,'') <> '') AND COALESCE(l.placa,'') <> '' THEN ' '
            ELSE ''
          END,
          TRIM(COALESCE(l.placa,''))
        ) AS veiculo,
        l.valor,
        l.forma_pagamento,
        l.status,
        DATE_FORMAT(l.criado_em, '%d/%m/%Y %H:%i') AS quando,
        lav.nome AS lavador
      FROM lavagens_peca l
      LEFT JOIN lavadores_peca lav
        ON lav.cpf = l.lavador_cpf AND lav.empresa_cnpj = l.empresa_cnpj
      WHERE l.empresa_cnpj = :c
        AND l.criado_em >= :start
        AND l.criado_em <  :end
      ORDER BY l.criado_em DESC
      LIMIT 500
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':c'     => $empresaCnpj,
      ':start' => $startSql,
      ':end'   => $endSql,
    ]);
    $vm['rows'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // resumo
    $st2 = $pdo->prepare("
      SELECT COUNT(*) AS qtd, COALESCE(SUM(valor),0) AS total
      FROM lavagens_peca
      WHERE empresa_cnpj = :c
        AND criado_em >= :start
        AND criado_em <  :end
    ");
    $st2->execute([
      ':c'     => $empresaCnpj,
      ':start' => $startSql,
      ':end'   => $endSql,
    ]);
    $res = $st2->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
    $vm['resumo']['qtd']   = (int)$res['qtd'];
    $vm['resumo']['total'] = (float)$res['total'];

  } catch (Throwable $e) {
    $vm['err'] = 1;
    $vm['msg'] = 'Erro ao carregar lavagens.';
    if (APP_DEBUG) $vm['msg'] .= ' Detalhes: '.$e->getMessage();
  }

  return $vm;
}
