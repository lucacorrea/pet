<?php
// autoErp/public/vendas/pages/orcamentos.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

// CSRF desta tela (para ações rápidas)
if (empty($_SESSION['csrf_orcamentos'])) {
  $_SESSION['csrf_orcamentos'] = bin2hex(random_bytes(32));
}
$csrf_list = $_SESSION['csrf_orcamentos'];

// Conexão
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// Util — nome da empresa
require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Empresa vinculada à sessão
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  die('Empresa não vinculada ao usuário.');
}

/* =================== Filtros =================== */
$status = (string)($_GET['status'] ?? '');        // '', 'aberto','aprovado','rejeitado','expirado'
$range  = strtolower((string)($_GET['range'] ?? '30')); // 'today','yesterday','7','15','30'

$endSql = date('Y-m-d H:i:s');
switch ($range) {
  case 'today':
    $startSql = date('Y-m-d 00:00:00');
    break;
  case 'yesterday':
    $startSql = date('Y-m-d 00:00:00', strtotime('yesterday'));
    $endSql   = date('Y-m-d 00:00:00');
    break;
  case '7':
    $startSql = date('Y-m-d H:i:s', strtotime('-7 days'));
    break;
  case '15':
    $startSql = date('Y-m-d H:i:s', strtotime('-15 days'));
    break;
  case '30':
  default:
    $startSql = date('Y-m-d H:i:s', strtotime('-30 days'));
    $range = '30';
    break;
}

$params = [
  ':c'     => $empresaCnpj,
  ':start' => $startSql,
  ':end'   => $endSql,
];

$sql = "
  SELECT
    o.id,
    o.numero,
    o.cliente_nome,
    o.status,
    DATE_FORMAT(o.validade,'%d/%m/%Y') AS validade,
    DATE_FORMAT(o.criado_em,'%d/%m/%Y %H:%i') AS quando,
    o.total_liquido
  FROM orcamentos_peca o
  WHERE o.empresa_cnpj = :c
    AND o.criado_em >= :start
    AND o.criado_em <  :end
";

if (in_array($status, ['aberto','aprovado','rejeitado','expirado'], true)) {
  $sql .= " AND o.status = :s";
  $params[':s'] = $status;
}

$sql .= " ORDER BY o.criado_em DESC LIMIT 300";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $rows = [];
}

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Orçamentos</title>

  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../assets/images/favicon.ico">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
 <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'vendas-orcamentos'; // ID do menu atual
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content"> 
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>

          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
              </svg>
            </span>
            <input type="search" class="form-control" placeholder="Pesquisar orçamento...">
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Lista de Orçamentos</h1>
              <p>Gerencie orçamentos por período e status.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="card">
        <div class="card-body">
          <!-- Filtros -->
          <form class="row g-2 mb-3" method="get">
            <div class="col-md-3">
              <select class="form-select" name="range" onchange="this.form.submit()">
                <option value="today"     <?= $range==='today'?'selected':''; ?>>Hoje</option>
                <option value="yesterday" <?= $range==='yesterday'?'selected':''; ?>>Ontem</option>
                <option value="7"         <?= $range==='7'?'selected':''; ?>>Últimos 7 dias</option>
                <option value="15"        <?= $range==='15'?'selected':''; ?>>Últimos 15 dias</option>
                <option value="30"        <?= $range==='30'?'selected':''; ?>>Últimos 30 dias</option>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="" <?= $status===''?'selected':''; ?>>Status: Todos</option>
                <option value="aberto"     <?= $status==='aberto'?'selected':''; ?>>Aberto</option>
                <option value="aprovado"   <?= $status==='aprovado'?'selected':''; ?>>Aprovado</option>
                <option value="rejeitado"  <?= $status==='rejeitado'?'selected':''; ?>>Rejeitado</option>
                <option value="expirado"   <?= $status==='expirado'?'selected':''; ?>>Expirado</option>
              </select>
            </div>
            <div class="col-md-6 text-end">
              <a class="btn btn-outline-secondary" href="./orcamentoNovo.php">
                <i class="bi bi-plus-lg me-1"></i> Novo Orçamento
              </a>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Número</th>
                  <th>Cliente</th>
                  <th>Quando</th>
                  <th>Validade</th>
                  <th>Status</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Nenhum orçamento encontrado.</td>
                  </tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td>#<?= (int)($r['numero'] ?? $r['id']) ?></td>
                    <td><?= htmlspecialchars($r['cliente_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['quando'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['validade'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?php
                        $st = (string)($r['status'] ?? 'aberto');
                        $badge = [
                          'aberto'    => 'warning',
                          'aprovado'  => 'success',
                          'rejeitado' => 'secondary',
                          'expirado'  => 'dark',
                        ][$st] ?? 'secondary';
                      ?>
                      <span class="badge bg-<?= $badge ?>"><?= ucfirst($st) ?></span>
                    </td>
                    <td class="text-end">R$ <?= number_format((float)($r['total_liquido'] ?? 0), 2, ',', '.') ?></td>
                    <td class="text-end text-nowrap">
                      <!-- imprimir -->
                      <a class="btn btn-sm btn-outline-secondary" href="./orcamentoPrint.php?id=<?= (int)$r['id'] ?>" target="_blank" title="Imprimir">
                        <i class="bi bi-printer"></i>
                      </a>

                      <?php if ($st === 'aberto'): ?>
                        <!-- aprovar -->
                        <form method="post" action="../actions/orcamentosStatus.php" class="d-inline">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_list, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="aprovado">
                          <button class="btn btn-sm btn-outline-success" title="Aprovar" type="submit">
                            <i class="bi bi-check2-circle"></i>
                          </button>
                        </form>
                        <!-- rejeitar -->
                        <form method="post" action="../actions/orcamentosStatus.php" class="d-inline" onsubmit="return confirm('Marcar como rejeitado?');">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_list, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="rejeitado">
                          <button class="btn btn-sm btn-outline-danger" title="Rejeitar" type="submit">
                            <i class="bi bi-x-circle"></i>
                          </button>
                        </form>
                        <!-- expirar -->
                        <form method="post" action="../actions/orcamentosStatus.php" class="d-inline" onsubmit="return confirm('Marcar como expirado?');">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_list, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="expirado">
                          <button class="btn btn-sm btn-outline-dark" title="Expirar" type="submit">
                            <i class="bi bi-hourglass-bottom"></i>
                          </button>
                        </form>
                      <?php else: ?>
                        <!-- reabrir -->
                        <form method="post" action="../actions/orcamentosStatus.php" class="d-inline" onsubmit="return confirm('Reabrir orçamento?');">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_list, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="aberto">
                          <button class="btn btn-sm btn-outline-warning" title="Reabrir" type="submit">
                            <i class="bi bi-arrow-counterclockwise"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">
          © <script>document.write(new Date().getFullYear())</script>
          <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
        </div>
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
