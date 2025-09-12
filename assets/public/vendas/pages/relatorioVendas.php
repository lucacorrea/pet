<?php
// autoErp/public/vendas/pages/relatorioVendas.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

$hoje = (new DateTime('today'))->format('Y-m-d');

/* ========= Filtros ========= */
$de     = (string)($_GET['de'] ?? $hoje);
$ate    = (string)($_GET['ate'] ?? $hoje);
$fp     = strtolower((string)($_GET['fp'] ?? ''));            // '', dinheiro, pix, debito, credito
$status = strtolower((string)($_GET['status'] ?? ''));        // '', aberta, fechada, cancelada
$q      = trim((string)($_GET['q'] ?? ''));                   // id / vendedor_cpf / origem
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$csv    = (int)($_GET['csv'] ?? 0);
$csv_it = (int)($_GET['csv_it'] ?? 0);

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
  'limit' => $limit
];

/* ========= WHERE comum (vendas) ========= */
$where = ["v.empresa_cnpj = :c", "v.criado_em BETWEEN :de AND :ate"];
$params = [':c' => $empresaCnpj, ':de' => $deIso, ':ate' => $ateIso];

if ($status !== '') {
  $where[] = "LOWER(v.status) = :status";
  $params[':status'] = $status;
}
if ($fp !== '') {
  $where[] = "LOWER(v.forma_pagamento) = :fp";
  $params[':fp'] = ($fp === 'crédito') ? 'credito' : $fp;
}
if ($q !== '') {
  $where[] = "(CAST(v.id AS CHAR) LIKE :q OR v.vendedor_cpf LIKE :q OR LOWER(v.origem) LIKE :q)";
  $params[':q'] = '%' . strtolower($q) . '%';
}
$whereSql = implode(' AND ', $where);

/* ========= Totais por forma (apenas vendas FECHADAS) ========= */
$totais = ['dinheiro' => 0.0, 'pix' => 0.0, 'debito' => 0.0, 'credito' => 0.0, 'geral' => 0.0];
try {
  $sqlTot = "
    SELECT LOWER(v.forma_pagamento) AS fp, SUM(v.total_liquido) AS total
    FROM vendas_peca v
    WHERE $whereSql AND LOWER(v.status) = 'fechada'
    GROUP BY LOWER(v.forma_pagamento)
  ";
  $st = $pdo->prepare($sqlTot);
  $st->execute($params);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = ($r['fp'] === 'crédito') ? 'credito' : (string)$r['fp'];
    if (isset($totais[$key])) $totais[$key] += (float)$r['total'];
  }
  $totais['geral'] = $totais['dinheiro'] + $totais['pix'] + $totais['debito'] + $totais['credito'];
} catch (Throwable $e) {
}

/* ========= Movimentos do caixa (suprimento/sangria) na data filtrada ========= */
$mov = ['suprimentos' => 0.0, 'sangrias' => 0.0];
try {
  $sqlMov = "
    SELECT 
      SUM(CASE WHEN cm.tipo = 'entrada' THEN cm.valor ELSE 0 END) AS suprimentos,
      SUM(CASE WHEN cm.tipo = 'saida'   THEN cm.valor ELSE 0 END) AS sangrias
    FROM caixa_movimentos_peca cm
    WHERE cm.empresa_cnpj = :c AND cm.criado_em BETWEEN :de AND :ate
  ";
  $stm = $pdo->prepare($sqlMov);
  $stm->execute([':c' => $empresaCnpj, ':de' => $deIso, ':ate' => $ateIso]);
  $rowm = $stm->fetch(PDO::FETCH_ASSOC) ?: [];
  $mov['suprimentos'] = (float)($rowm['suprimentos'] ?? 0);
  $mov['sangrias']    = (float)($rowm['sangrias'] ?? 0);
} catch (Throwable $e) {
}

/* ========= Itens vendidos (sumário por produto) – apenas vendas FECHADAS ========= */
$itensVendidos = [];
try {
  $sqlItens = "
    SELECT 
      i.item_id,
      i.descricao,
      SUM(i.qtd)            AS qtd_total,
      SUM(i.valor_total)    AS total_itens
    FROM venda_itens_peca i
    INNER JOIN vendas_peca v ON v.id = i.venda_id
    WHERE v.empresa_cnpj = :c
      AND v.criado_em BETWEEN :de AND :ate
      AND LOWER(v.status) = 'fechada'
      AND i.item_tipo = 'produto'
    GROUP BY i.item_id, i.descricao
    ORDER BY total_itens DESC, qtd_total DESC, i.descricao ASC
    LIMIT 1000
  ";
  $sti = $pdo->prepare($sqlItens);
  $sti->execute([':c' => $empresaCnpj, ':de' => $deIso, ':ate' => $ateIso]);
  $itensVendidos = $sti->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
}

/* ========= Paginação + lista de vendas ========= */
$totalRows = 0;
try {
  $sqlCount = "SELECT COUNT(*) FROM vendas_peca v WHERE $whereSql";
  $stc = $pdo->prepare($sqlCount);
  $stc->execute($params);
  $totalRows = (int)$stc->fetchColumn();
} catch (Throwable $e) {
}

$totalPages = max(1, (int)ceil($totalRows / $limit));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $limit;

$rows = [];
try {
  $sql = "
    SELECT 
      v.id, v.criado_em, v.status, v.origem, v.vendedor_cpf,
      v.total_bruto, v.desconto, v.total_liquido, v.forma_pagamento
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
} catch (Throwable $e) {
}

/* ========= CSV (vendas) ========= */
if ($csv) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="relatorio_vendas.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID', 'Data/Hora', 'Status', 'Origem', 'Vendedor (CPF)', 'Bruto', 'Desconto', 'Líquido', 'Forma Pagamento'], ';');
  $sqlAll = "
    SELECT v.id, v.criado_em, v.status, v.origem, v.vendedor_cpf,
           v.total_bruto, v.desconto, v.total_liquido, v.forma_pagamento
    FROM vendas_peca v
    WHERE $whereSql
    ORDER BY v.criado_em DESC, v.id DESC
  ";
  $sta = $pdo->prepare($sqlAll);
  $sta->execute($params);
  while ($r = $sta->fetch(PDO::FETCH_ASSOC)) {
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
  fclose($out);
  exit;
}

/* ========= CSV (itens vendidos) ========= */
if ($csv_it) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="relatorio_itens_vendidos.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Item ID', 'Descrição', 'Qtd Total', 'Total Itens (R$)'], ';');
  foreach ($itensVendidos as $it) {
    fputcsv($out, [
      (int)$it['item_id'],
      (string)$it['descricao'],
      number_format((float)$it['qtd_total'], 3, ',', '.'),
      number_format((float)$it['total_itens'], 2, ',', '.'),
    ], ';');
  }
  fclose($out);
  exit;
}

/* ========= Helpers ========= */
function fmt($v)
{
  return number_format((float)$v, 2, ',', '.');
}
function fmt3($v)
{
  return number_format((float)$v, 3, ',', '.');
}

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
    .money {
      font-variant-numeric: tabular-nums
    }

    .badge-status {
      text-transform: capitalize;
    }

    @media (min-width:1200px) {
      .aside-sticky {
        position: sticky;
        top: 84px;
      }
    }
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
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">Mundo Pets</h4>
          </a>
          <div class="ms-auto"></div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Relatório de Vendas</h1>
              <p>Resumo de vendas, itens vendidos, suprimentos e sangrias no período.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">

      <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header border-0 bg-body d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h4 class="card-title mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-funnel"></i> Filtros
          </h4>
          <!-- Atalhos rápidos -->
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-soft-primary btn-sm" data-range="hoje"><i class="bi bi-lightning-charge"></i> Hoje</button>
            <button type="button" class="btn btn-soft-primary btn-sm" data-range="ontem"><i class="bi bi-arrow-left"></i> Ontem</button>
            <button type="button" class="btn btn-soft-primary btn-sm" data-range="semana"><i class="bi bi-calendar-week"></i> Semana</button>
            <button type="button" class="btn btn-soft-primary btn-sm" data-range="mes"><i class="bi bi-calendar3"></i> Mês</button>
          </div>
        </div>

        <div class="card-body pt-3">
          <form id="filtros-form" class="row g-3" method="get" action="">
            <!-- ESQUERDA: campos -->
            <div class="col-12 col-xl-9">
              <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xxl-3">
                  <label class="form-label">De</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                    <input id="inp-de" type="date" class="form-control" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                </div>
                <div class="col-12 col-md-6 col-xxl-3">
                  <label class="form-label">Até</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar2-event"></i></span>
                    <input id="inp-ate" type="date" class="form-control" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                </div>
                <div class="col-12 col-md-6 col-xxl-3">
                  <label class="form-label">Forma</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-wallet2"></i></span>
                    <select class="form-select" name="fp" aria-label="Forma de pagamento">
                      <option value="">Todas</option>
                      <?php foreach (['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Débito', 'credito' => 'Crédito'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $fp === $k ? 'selected' : '' ?>><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-12 col-md-6 col-xxl-3">
                  <label class="form-label">Status</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-check2-square"></i></span>
                    <select class="form-select" name="status" aria-label="Status da venda">
                      <?php foreach (['' => 'Todos', 'aberta' => 'Aberta', 'fechada' => 'Fechada', 'cancelada' => 'Cancelada'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label">Buscar</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="inp-q" type="text" class="form-control" name="q"
                      value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                      placeholder="ID, CPF do vendedor ou origem (balcao/lavajato/orcamento)">
                    <button class="btn btn-outline-secondary" type="button" id="btn-clear-q" title="Limpar busca">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                  <div class="form-text mt-1">Dica: você pode buscar por <strong>ID</strong>, <strong>CPF</strong> do vendedor ou <strong>origem</strong>.</div>
                </div>
              </div>
            </div>

            <!-- DIREITA: ações -->
            <div class="col-12 col-xl-3">
              <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-search me-1"></i> Aplicar filtros
                </button>
                <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($filters, ['csv' => 1])) ?>">
                  <i class="bi bi-filetype-csv me-1"></i> CSV Vendas
                </a>
                <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($filters, ['csv_it' => 1])) ?>">
                  <i class="bi bi-filetype-csv me-1"></i> CSV Itens
                </a>
                <a class="btn btn-soft-danger" href="?de=&ate=&fp=&status=&q=" title="Limpar todos os filtros">
                  <i class="bi bi-eraser"></i> Limpar Filtros
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Estilos suaves / micro-ajustes -->
      <style>
        .btn-soft-primary {
          background: rgba(13, 110, 253, .08);
          border-color: transparent;
          color: #0d6efd
        }

        .btn-soft-primary:hover {
          background: rgba(13, 110, 253, .14);
          color: #0b5ed7
        }

        .btn-soft-danger {
          background: rgba(220, 53, 69, .08);
          border-color: transparent;
          color: #dc3545
        }

        .btn-soft-danger:hover {
          background: rgba(220, 53, 69, .14);
          color: #bb2d3b
        }

        .input-group>.input-group-text {
          background: #f8fafc
        }

        .card-header.bg-body {
          background: var(--bs-body-bg, #fff)
        }

        @media (max-width: 575.98px) {
          .card-header .btn {
            padding: .35rem .6rem
          }
        }
      </style>

      <script>
        (function() {
          const form = document.getElementById('filtros-form');
          const de = document.getElementById('inp-de');
          const ate = document.getElementById('inp-ate');
          const qInp = document.getElementById('inp-q');

          // Limpar busca e enviar
          document.getElementById('btn-clear-q')?.addEventListener('click', () => {
            if (!qInp) return;
            qInp.value = '';
            qInp.focus();
            form?.requestSubmit(); // aplica limpeza
          });

          // Sincronizar min/max entre De/Até
          const syncBounds = () => {
            if (de && ate) {
              if (de.value) ate.min = de.value;
              else ate.removeAttribute('min');
              if (ate.value) de.max = ate.value;
              else de.removeAttribute('max');
            }
          };
          de?.addEventListener('change', syncBounds);
          ate?.addEventListener('change', syncBounds);
          syncBounds();

          // Helpers de data
          const pad = n => String(n).padStart(2, '0');
          const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
          const setRange = (d1, d2) => {
            if (de) de.value = fmt(d1);
            if (ate) ate.value = fmt(d2);
            syncBounds();
          };

          // Atalhos com auto-submit
          document.querySelectorAll('[data-range]').forEach(btn => {
            btn.addEventListener('click', () => {
              const now = new Date();
              const base = new Date(now.getFullYear(), now.getMonth(), now.getDate());
              const tipo = btn.getAttribute('data-range');

              if (tipo === 'hoje') {
                setRange(base, base);
              } else if (tipo === 'ontem') {
                const y = new Date(base);
                y.setDate(y.getDate() - 1);
                setRange(y, y);
              } else if (tipo === 'semana') {
                const dow = base.getDay(); // 0=Dom
                const ini = new Date(base);
                ini.setDate(base.getDate() - (dow === 0 ? 6 : dow - 1)); // segunda
                const fim = new Date(ini);
                fim.setDate(ini.getDate() + 6);
                setRange(ini, fim);
              } else if (tipo === 'mes') {
                const ini = new Date(base.getFullYear(), base.getMonth(), 1);
                const fim = new Date(base.getFullYear(), base.getMonth() + 1, 0);
                setRange(ini, fim);
              }
              form?.requestSubmit();
            });
          });

          // Enter no campo de busca foca no submit padrão do form (já nativo),
          // mas se quiser aplicar ao digitar, descomente abaixo:
          // let t; qInp?.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=>form?.requestSubmit(), 600); });

        })();
      </script>

      <div class="row g-3">
        <!-- Lista de vendas -->
        <div class="col-12 col-xxl-8">
          <div class="card" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header">
              <h5 class="mb-0">Vendas</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width:120px;"># / Data</th>
                      <th>Origem</th>
                      <th>Vendedor (CPF)</th>
                      <th class="text-end">Bruto</th>
                      <th class="text-end">Desc.</th>
                      <th class="text-end">Líquido</th>
                      <th>Forma</th>
                      <th class="text-end">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="8" class="text-center text-muted">Nenhuma venda encontrada.</td>
                      </tr>
                      <?php else: foreach ($rows as $r): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold">#<?= (int)$r['id'] ?></div>
                            <div class="small text-muted"><?= (new DateTime($r['criado_em']))->format('d/m/Y H:i') ?></div>
                          </td>
                          <td><?= htmlspecialchars((string)$r['origem'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars((string)($r['vendedor_cpf'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end money">R$ <?= fmt($r['total_bruto']) ?></td>
                          <td class="text-end money">R$ <?= fmt($r['desconto']) ?></td>
                          <td class="text-end money">R$ <?= fmt($r['total_liquido']) ?></td>
                          <td><?= htmlspecialchars((string)($r['forma_pagamento'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end">
                            <?php $st = strtolower((string)$r['status']);
                            $map = ['fechada' => 'success', 'aberta' => 'warning', 'cancelada' => 'secondary']; ?>
                            <span class="badge bg-<?= $map[$st] ?? 'secondary' ?> badge-status"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                          </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>

              <?php if ($totalPages > 1): ?>
                <?php $mk = fn($p) => '?' . http_build_query(array_merge($filters, ['page' => $p])); ?>
                <nav aria-label="Paginação">
                  <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $mk(max(1, $page - 1)) ?>">«</a></li>
                    <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $totalPages ?></span></li>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $mk(min($totalPages, $page + 1)) ?>">»</a></li>
                  </ul>
                </nav>
              <?php endif; ?>
            </div>
          </div>

          <!-- Itens vendidos -->
          <div class="card mt-3" data-aos="fade-up" data-aos-delay="230">
            <div class="card-header">
              <h5 class="mb-0">Itens Vendidos (Produtos) — Período</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width:110px;">Item ID</th>
                      <th>Descrição</th>
                      <th class="text-end">Qtd</th>
                      <th class="text-end">Total Itens (R$)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$itensVendidos): ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted">Nenhum item vendido no período.</td>
                      </tr>
                      <?php else: foreach ($itensVendidos as $it): ?>
                        <tr>
                          <td>#<?= (int)$it['item_id'] ?></td>
                          <td><?= htmlspecialchars((string)$it['descricao'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end"><?= fmt3($it['qtd_total']) ?></td>
                          <td class="text-end money">R$ <?= fmt($it['total_itens']) ?></td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>

        <!-- Cards de totais -->
        <div class="col-12 col-xxl-4">
          <div class="aside-sticky">

            <!-- Totais por forma -->
            <div class="card" data-aos="fade-up" data-aos-delay="220">
              <div class="card-header">
                <h5 class="mb-0">Totais — Vendas Fechadas</h5>
              </div>
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
                  <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($filters, ['csv' => 1])) ?>">
                    <i class="bi bi-download me-1"></i> CSV Vendas
                  </a>
                  <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($filters, ['csv_it' => 1])) ?>">
                    <i class="bi bi-download me-1"></i> CSV Itens
                  </a>
                  <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Imprimir</button>
                </div>
              </div>
            </div>

            <!-- Suprimentos / Sangrias -->
            <div class="card mt-3" data-aos="fade-up" data-aos-delay="240">
              <div class="card-header">
                <h5 class="mb-0">Movimentações de Caixa</h5>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Suprimentos (Entradas)</span>
                  <strong class="money">R$ <?= fmt($mov['suprimentos']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Sangrias (Saídas)</span>
                  <strong class="money">R$ <?= fmt($mov['sangrias']) ?></strong>
                </div>
                <hr>
                <?php
                // Caixa físico do período (aproximação): dinheiro de vendas fechadas + suprimentos - sangrias
                $caixaFisico = $totais['dinheiro'] + $mov['suprimentos'] - $mov['sangrias'];
                ?>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fs-6">Estimativa Caixa (Dinheiro)</span>
                  <span class="fs-6 fw-bold money">R$ <?= fmt($caixaFisico) ?></span>
                </div>
                <div class="form-text mt-1">Estimativa: Dinheiro (vendas fechadas) + Suprimentos − Sangrias</div>
              </div>
            </div>

          </div>
        </div>
      </div><!-- /row -->

    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
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