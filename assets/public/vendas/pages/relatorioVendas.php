<?php
// autoErp/public/vendas/pages/relatorioVendas.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

$hoje = (new DateTime('today'))->format('Y-m-d');

// ===== Filtros (GET) =====
$de     = (string)($_GET['de'] ?? $hoje);
$ate    = (string)($_GET['ate'] ?? $hoje);
$fp     = strtolower((string)($_GET['fp'] ?? ''));            // '', dinheiro, pix, debito, credito, etc
$status = strtolower((string)($_GET['status'] ?? ''));        // '', aberta, fechada, cancelada
$q      = trim((string)($_GET['q'] ?? ''));                   // busca livre (id, vendedor_cpf, origem)
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$csv    = (int)($_GET['csv'] ?? 0);

// Datas normalizadas
try {
  $dtDe  = new DateTime($de . ' 00:00:00');
  $dtAte = new DateTime($ate . ' 23:59:59');
} catch (Throwable $e) {
  $dtDe  = new DateTime($hoje . ' 00:00:00');
  $dtAte = new DateTime($hoje . ' 23:59:59');
}
$deIso  = $dtDe->format('Y-m-d H:i:s');
$ateIso = $dtAte->format('Y-m-d H:i:s');

$filters = [
  'de' => $dtDe->format('Y-m-d'),
  'ate' => $dtAte->format('Y-m-d'),
  'fp' => $fp,
  'status' => $status,
  'q' => $q,
  'page' => $page,
  'limit' => $limit,
  'csv' => $csv,
];

// ===== WHERE comum =====
$where = ["v.empresa_cnpj = :c", "v.criado_em BETWEEN :de AND :ate"];
$params = [':c' => $empresaCnpj, ':de' => $deIso, ':ate' => $ateIso];

if ($status !== '') {
  $where[] = "LOWER(v.status) = :status";
  $params[':status'] = $status;
}
if ($fp !== '') {
  $where[] = "LOWER(v.forma_pagamento) = :fp";
  $params[':fp'] = $fp === 'crédito' ? 'credito' : $fp;
}
if ($q !== '') {
  // busca simples: id, vendedor_cpf, origem
  $where[] = "(
      CAST(v.id AS CHAR) LIKE :q
      OR v.vendedor_cpf LIKE :q
      OR LOWER(v.origem) LIKE :q
    )";
  $params[':q'] = '%' . strtolower($q) . '%';
}

$whereSql = implode(' AND ', $where);

// ===== Totais por forma e geral (apenas vendas fechadas contam no total consolidado) =====
$totais = ['dinheiro'=>0.0,'pix'=>0.0,'debito'=>0.0,'credito'=>0.0,'geral'=>0.0];
try {
  $sqlTot = "
    SELECT LOWER(v.forma_pagamento) AS fp, SUM(v.total_liquido) AS total
    FROM vendas_peca v
    WHERE $whereSql
      AND LOWER(v.status) = 'fechada'
    GROUP BY LOWER(v.forma_pagamento)
  ";
  $st = $pdo->prepare($sqlTot);
  $st->execute($params);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = ($r['fp'] === 'crédito') ? 'credito' : (string)$r['fp'];
    if (isset($totais[$key])) $totais[$key] += (float)$r['total'];
  }
  $totais['geral'] = $totais['dinheiro'] + $totais['pix'] + $totais['debito'] + $totais['credito'];
} catch (Throwable $e) { /* ignore */ }

// ===== Paginação =====
$totalRows = 0;
try {
  $sqlCount = "SELECT COUNT(*) FROM vendas_peca v WHERE $whereSql";
  $st = $pdo->prepare($sqlCount);
  $st->execute($params);
  $totalRows = (int)$st->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

$totalPages = max(1, (int)ceil($totalRows / $limit));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $limit;

$mkUrl = function(array $overrides = []) use ($filters) {
  $q = array_merge($filters, $overrides);
  return '?' . http_build_query($q);
};

// ===== Listagem =====
$rows = [];
try {
  $sql = "
    SELECT 
      v.id,
      v.criado_em,
      v.status,
      v.origem,
      v.vendedor_cpf,
      v.total_bruto,
      v.desconto,
      v.total_liquido,
      v.forma_pagamento
    FROM vendas_peca v
    WHERE $whereSql
    ORDER BY v.criado_em DESC, v.id DESC
    LIMIT :limit OFFSET :offset
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':limit', $limit, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* ignore */ }

// ===== CSV =====
if ($csv) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="relatorio_vendas.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'ID','Data/Hora','Status','Origem','Vendedor (CPF)',
    'Total Bruto','Desconto','Total Líquido','Forma Pagamento'
  ], ';');

  try {
    $sqlAll = "
      SELECT 
        v.id, v.criado_em, v.status, v.origem, v.vendedor_cpf,
        v.total_bruto, v.desconto, v.total_liquido, v.forma_pagamento
      FROM vendas_peca v
      WHERE $whereSql
      ORDER BY v.criado_em DESC, v.id DESC
    ";
    $st = $pdo->prepare($sqlAll);
    $st->execute($params);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [
        (int)$r['id'],
        (new DateTime($r['criado_em']))->format('d/m/Y H:i'),
        (string)$r['status'],
        (string)$r['origem'],
        (string)($r['vendedor_cpf'] ?? ''),
        number_format((float)$r['total_bruto'], 2, ',', '.'),
        number_format((float)$r['desconto'], 2, ',', '.'),
        number_format((float)$r['total_liquido'], 2, ',', '.'),
        (string)($r['forma_pagamento'] ?? ''),
      ], ';');
    }
  } catch (Throwable $e) { /* ignore */ }
  fclose($out);
  exit;
}

// ===== Helpers =====
function fmt_money($v){ return number_format((float)$v, 2, ',', '.'); }

// ===== Saídas para a View =====
$pagination = [
  'page' => $page,
  'total_rows' => $totalRows,
  'total_pages' => $totalPages,
  'limit' => $limit,
  'offset' => $offset,
  'make_url' => $mkUrl,
];

// A view deve incluir este arquivo e usar:
// $filters, $rows, $totais, $pagination, fmt_money()

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>Mundo Pets — Relatório de Vendas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    .money{font-variant-numeric: tabular-nums}
    .badge-status{text-transform: capitalize;}
    @media (min-width:1200px){ .aside-sticky{position:sticky; top:84px;} }
  </style>
</head>
<body>
  <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'relatorios-vendas';
    include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand"><h4 class="logo-title">Mundo Pets</h4></a>
          <div class="ms-auto"></div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Relatório de Vendas</h1>
              <p>Filtre por período, forma de pagamento e status. Exporte para CSV quando precisar.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="card" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header">
          <h4 class="card-title mb-0">Filtros</h4>
        </div>
        <div class="card-body">
          <form class="row g-3" method="get" action="">
            <div class="col-sm-3">
              <label class="form-label">De</label>
              <input type="date" class="form-control" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Até</label>
              <input type="date" class="form-control" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Forma</label>
              <select class="form-select" name="fp">
                <option value="">Todas</option>
                <?php
                  foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','debito'=>'Débito','credito'=>'Crédito'] as $k=>$v){
                    $sel = $fp===$k ? 'selected' : '';
                    echo "<option value=\"$k\" $sel>$v</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php
                  $opts = [''=>'Todos','concluida'=>'Concluída','finalizada'=>'Finalizada','paga'=>'Paga','pendente'=>'Pendente','cancelada'=>'Cancelada'];
                  foreach ($opts as $k=>$v){ $sel = ($status===$k?'selected':''); echo "<option value=\"$k\" $sel>$v</option>"; }
                ?>
              </select>
            </div>
            <div class="col-lg-9">
              <label class="form-label">Buscar</label>
              <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cliente, documento, observação...">
            </div>
            <div class="col-lg-3 d-flex align-items-end gap-2">
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Filtrar</button>
              <a class="btn btn-outline-secondary" href="?de=<?= urlencode($de) ?>&ate=<?= urlencode($ate) ?>&fp=<?= urlencode($fp) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($q) ?>&csv=1">
                <i class="bi bi-filetype-csv me-1"></i> CSV
              </a>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xxl-8">
          <div class="card" data-aos="fade-up" data-aos-delay="200">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width:110px;"># / Data</th>
                      <th>Cliente</th>
                      <th>Documento</th>
                      <th class="text-end">Bruto</th>
                      <th class="text-end">Desc.</th>
                      <th class="text-end">Líquido</th>
                      <th class="text-end">Pago</th>
                      <th>Formas</th>
                      <th class="text-end">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$rows): ?>
                      <tr><td colspan="9" class="text-center text-muted">Nenhuma venda encontrada para o filtro.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold">#<?= (int)$r['id'] ?></div>
                          <div class="small text-muted"><?= (new DateTime($r['created_at']))->format('d/m/Y H:i') ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)($r['cliente_nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($r['documento'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end money">R$ <?= fmt($r['total_bruto']) ?></td>
                        <td class="text-end money">R$ <?= fmt($r['desconto']) ?></td>
                        <td class="text-end money">R$ <?= fmt($r['total_liquido']) ?></td>
                        <td class="text-end money">R$ <?= fmt($r['total_pago']) ?></td>
                        <td><?= htmlspecialchars((string)($r['formas'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end">
                          <?php
                            $st = strtolower((string)$r['status']);
                            $map = ['paga'=>'success','finalizada'=>'primary','concluida'=>'primary','pendente'=>'warning','cancelada'=>'secondary'];
                            $cls = $map[$st] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?= $cls ?> badge-status"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>

              <?php
                $totalPages = max(1, (int)ceil($totalRows / $limit));
                $mk = function($p) use($de,$ate,$fp,$status,$q){ 
                  return '?de='.urlencode($de).'&ate='.urlencode($ate).'&fp='.urlencode($fp).'&status='.urlencode($status).'&q='.urlencode($q).'&page='.$p;
                };
              ?>
              <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginação">
                  <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $mk(max(1,$page-1)) ?>">«</a></li>
                    <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $totalPages ?></span></li>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= $mk(min($totalPages,$page+1)) ?>">»</a></li>
                  </ul>
                </nav>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <div class="col-12 col-xxl-4">
          <div class="aside-sticky">
            <div class="card" data-aos="fade-up" data-aos-delay="220">
              <div class="card-header"><h5 class="mb-0">Totais do Período</h5></div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Dinheiro</span><strong class="money">R$ <?= fmt($totais['dinheiro']) ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">PIX</span><strong class="money">R$ <?= fmt($totais['pix']) ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Débito</span><strong class="money">R$ <?= fmt($totais['debito']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Crédito</span><strong class="money">R$ <?= fmt($totais['credito']) ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fs-6">TOTAL</span>
                  <span class="fs-5 fw-bold money">R$ <?= fmt($totais['geral']) ?></span>
                </div>
                <div class="mt-3 d-grid gap-2">
                  <a class="btn btn-outline-secondary" href="?de=<?= urlencode($de) ?>&ate=<?= urlencode($ate) ?>&fp=<?= urlencode($fp) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($q) ?>&csv=1">
                    <i class="bi bi-download me-1"></i> Baixar CSV
                  </a>
                  <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Imprimir</button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!--/row-->
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>document.write(new Date().getFullYear())</script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
</body>
</html>
