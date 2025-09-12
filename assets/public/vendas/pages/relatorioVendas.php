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
$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

// ====== filtros ======
$hoje = (new DateTime('today'))->format('Y-m-d');
$de   = (string)($_GET['de']   ?? $hoje);
$ate  = (string)($_GET['ate']  ?? $hoje);
$fp   = strtolower((string)($_GET['fp'] ?? '')); // dinheiro|pix|debito|credito|''(todas)
$status = strtolower((string)($_GET['status'] ?? '')); // concluida|finalizada|paga|cancelada|pendente|''(todas)
$q    = trim((string)($_GET['q'] ?? '')); // busca livre (cliente, doc, obs)

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$csv = (int)($_GET['csv'] ?? 0); // export

// período (fechando em 23:59:59 do dia "até")
try {
  $dtDe  = new DateTime($de.' 00:00:00');
  $dtAte = new DateTime($ate.' 23:59:59');
} catch (Throwable $e) {
  $dtDe  = new DateTime($hoje.' 00:00:00');
  $dtAte = new DateTime($hoje.' 23:59:59');
}
$deIso  = $dtDe->format('Y-m-d H:i:s');
$ateIso = $dtAte->format('Y-m-d H:i:s');

// ====== montagem do WHERE ======
$where = ["v.empresa_cnpj = :c", "v.created_at BETWEEN :de AND :ate"];
$params = [':c' => $empresaCnpj, ':de' => $deIso, ':ate' => $ateIso];

if ($status !== '') {
  $where[] = "LOWER(v.status) = :status";
  $params[':status'] = $status;
}
if ($q !== '') {
  $where[] = "(v.cliente_nome LIKE :q OR v.documento LIKE :q OR v.obs LIKE :q)";
  $params[':q'] = '%'.$q.'%';
}
if ($fp !== '') {
  // filtra por venda que tenha a forma informada
  $where[] = "EXISTS (SELECT 1 FROM vendas_pagamentos_peca vp WHERE vp.venda_id = v.id AND LOWER(vp.forma_pagamento) = :fp)";
  $params[':fp'] = $fp === 'crédito' ? 'credito' : $fp;
}
$whereSql = implode(' AND ', $where);

// ====== totais por forma e geral ======
$totais = ['dinheiro'=>0.0,'pix'=>0.0,'debito'=>0.0,'credito'=>0.0,'geral'=>0.0];
try {
  $sqlTot = "
    SELECT LOWER(vp.forma_pagamento) AS fp, SUM(vp.valor) AS total
    FROM vendas_peca v
    JOIN vendas_pagamentos_peca vp ON vp.venda_id = v.id
    WHERE $whereSql
      AND v.status IN ('concluida','finalizada','paga')
    GROUP BY LOWER(vp.forma_pagamento)
  ";
  $st = $pdo->prepare($sqlTot);
  $st->execute($params);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['fp'] === 'crédito' ? 'credito' : (string)$r['fp'];
    if (isset($totais[$key])) $totais[$key] += (float)$r['total'];
  }
  $totais['geral'] = $totais['dinheiro'] + $totais['pix'] + $totais['debito'] + $totais['credito'];
} catch (Throwable $e) {}

// ====== contagem para paginação ======
$totalRows = 0;
try {
  $sqlCount = "SELECT COUNT(*) FROM vendas_peca v WHERE $whereSql";
  $st = $pdo->prepare($sqlCount);
  $st->execute($params);
  $totalRows = (int)$st->fetchColumn();
} catch (Throwable $e) {}

// ====== listagem ======
$rows = [];
try {
  $sql = "
    SELECT 
      v.id, v.created_at, v.status, v.cliente_nome, v.documento, v.total_bruto, v.desconto, v.total_liquido, v.obs,
      -- total pago (somatório dos pagamentos) e concat das formas
      (SELECT COALESCE(SUM(vp.valor),0) FROM vendas_pagamentos_peca vp WHERE vp.venda_id = v.id) AS total_pago,
      (SELECT GROUP_CONCAT(DISTINCT LOWER(vp.forma_pagamento) ORDER BY LOWER(vp.forma_pagamento) SEPARATOR ', ')
         FROM vendas_pagamentos_peca vp WHERE vp.venda_id = v.id) AS formas
    FROM vendas_peca v
    WHERE $whereSql
    ORDER BY v.created_at DESC, v.id DESC
    LIMIT $limit OFFSET $offset
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// ====== CSV export ======
if ($csv) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="relatorio_vendas.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Data/Hora','Status','Cliente','Documento','Total Bruto','Desconto','Total Líquido','Pago','Formas','Obs'], ';');
  // exporta TUDO (ignora paginação)
  try {
    $sqlAll = "
      SELECT 
        v.id, v.created_at, v.status, v.cliente_nome, v.documento, v.total_bruto, v.desconto, v.total_liquido, v.obs,
        (SELECT COALESCE(SUM(vp.valor),0) FROM vendas_pagamentos_peca vp WHERE vp.venda_id = v.id) AS total_pago,
        (SELECT GROUP_CONCAT(DISTINCT LOWER(vp.forma_pagamento) ORDER BY LOWER(vp.forma_pagamento) SEPARATOR ', ')
           FROM vendas_pagamentos_peca vp WHERE vp.venda_id = v.id) AS formas
      FROM vendas_peca v
      WHERE $whereSql
      ORDER BY v.created_at DESC, v.id DESC
    ";
    $st = $pdo->prepare($sqlAll);
    $st->execute($params);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [
        (int)$r['id'],
        (new DateTime($r['created_at']))->format('d/m/Y H:i'),
        (string)$r['status'],
        (string)($r['cliente_nome'] ?? ''),
        (string)($r['documento'] ?? ''),
        number_format((float)$r['total_bruto'], 2, ',', '.'),
        number_format((float)$r['desconto'], 2, ',', '.'),
        number_format((float)$r['total_liquido'], 2, ',', '.'),
        number_format((float)$r['total_pago'], 2, ',', '.'),
        (string)($r['formas'] ?? ''),
        (string)($r['obs'] ?? ''),
      ], ';');
    }
  } catch (Throwable $e) {}
  fclose($out);
  exit;
}

function fmt($v){ return number_format((float)$v, 2, ',', '.'); }
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
