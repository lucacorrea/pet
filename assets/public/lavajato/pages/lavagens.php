<?php
// autoErp/public/lavajato/pages/lavagens.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']); // ajuste se quiser

// conexão
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// nome da empresa (opcional)
require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// controller
require_once __DIR__ . '/../controllers/lavagensController.php';
$vm = lavagens_list_viewmodel($pdo);

// helper dos botões
$range = $vm['range'] ?? '7';
$btn = function (string $r, string $label) use ($range) {
  $active = ($range === $r) ? 'active' : '';
  return '<a href="?range=' . $r . '" class="btn btn-sm btn-outline-primary ' . $active . '">' . $label . '</a>';
};
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavagens</title>
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
</head>

<body>
  <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

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

              </svg>
            </span>

          </div>
        </div>
      </nav>
      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Lavagens</h1>
              <p>Filtro rápido por período.</p>
              <?php if ($vm['ok'] || $vm['err'] || $vm['msg']): ?>
                <div class="mt-2 alert alert-<?= $vm['err'] ? 'danger' : 'success' ?> py-2 mb-0">
                  <?= htmlspecialchars($vm['msg'] ?: ($vm['ok'] ? 'Operação realizada.' : ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
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
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?= $btn('today', 'Hoje') ?>
            <?= $btn('yesterday', 'Ontem') ?>
            <?= $btn('7', '7 dias') ?>
            <?= $btn('15', '15 dias') ?>
            <?= $btn('30', '30 dias') ?>
          </div>

          <?php if (!empty($vm['resumo'])): ?>
            <div class="mb-3 small text-muted">
              <strong><?= (int)$vm['resumo']['qtd'] ?></strong> lavagens •
              Total: <strong>R$ <?= number_format((float)$vm['resumo']['total'], 2, ',', '.') ?></strong>
            </div>
          <?php endif; ?>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Quando</th>
                  <th>Serviço</th>
                  <th>Veículo</th>
                  <th>Lavador</th>
                  <th>Pagamento</th>
                  <th class="text-end">Valor</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$vm['rows']): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Nenhuma lavagem encontrada.</td>
                  </tr>
                  <?php else: foreach ($vm['rows'] as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['quando'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['servico'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['veiculo'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['lavador'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="text-end">R$ <?= number_format((float)$r['valor'], 2, ',', '.') ?></td>
                      <td>
                        <span class="badge bg-<?= $r['status'] === 'concluida' ? 'success' : ($r['status'] === 'cancelada' ? 'secondary' : 'warning') ?>">
                          <?= ucfirst($r['status']) ?>
                        </span>
                      </td>
                    </tr>
                <?php endforeach;
                endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
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