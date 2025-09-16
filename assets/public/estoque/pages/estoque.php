<?php
// autoErp/public/estoque.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque', 'lavajato']); // todos podem ver

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php'; // ajuste caminho conforme a pasta
$empresaNome = empresa_nome_logada($pdo); // nome da empresa logada
// Controller
require_once __DIR__ . '/../controllers/estoqueController.php';
$vm = estoque_viewmodel($pdo);

// Descompacta
$canMov       = $vm['canMov'];
$csrf         = $vm['csrf'];
$flash_ok     = $vm['flash_ok'];
$flash_err    = $vm['flash_err'];
$flash_msg    = $vm['flash_msg'];

$prodAtivos   = (int)$vm['prodAtivos'];
$prodInativos = (int)$vm['prodInativos'];
$zerados      = (int)$vm['zerados'];
$abaixoMin    = (int)$vm['abaixoMin'];
$skus         = (int)$vm['skus'];
$estoqueTotal = (float)$vm['estoqueTotal'];

$movs         = $vm['movimentos'];
$prodOptions  = $vm['prodOptions'];
$usuarioCpf   = $vm['usuarioCpf'];
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Estoque</title>
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
    $menuAtivo = 'estoque-lista'; // ID do menu atual
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
            <input type="search" class="form-control" placeholder="Pesquisar...">
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 170px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h1>Estoque</h1>
                  <p>Visão geral do estoque e últimas movimentações.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n4 py-0">
      <div class="row g-3">

        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Ativos</p>
              <h4 class="mb-0"><?= (int)$prodAtivos ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Inativos</p>
              <h4 class="mb-0"><?= (int)$prodInativos ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Zerados</p>
              <h4 class="mb-0"><?= (int)$zerados ?></h4>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Abaixo do mínimo</p>
              <h4 class="mb-0"><?= (int)$abaixoMin ?></h4>
            </div>
          </div>
        </div>

      </div>

      <div class="row mt-3">
        <div class="col-12">
          <div class="card" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="card-title mb-0">Últimas movimentações</h4>
              <?php if ($canMov): ?>

              <?php endif; ?>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-middle table-striped mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Produto</th>
                      <th>Tipo</th>
                      <th>Origem</th>
                      <th class="text-end">Qtd</th>
                      <th>Quando</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$movs): ?>
                      <tr>
                        <td colspan="6" class="text-center text-muted">Sem movimentações ainda.</td>
                      </tr>
                      <?php else: foreach ($movs as $m):
                        $tipo = (string)$m['tipo'];
                        $badge = 'secondary';
                        if ($tipo === 'entrada') $badge = 'success';
                        elseif ($tipo === 'saida')   $badge = 'danger';
                        elseif ($tipo === 'ajuste')  $badge = 'warning';
                      ?>
                        <tr>
                          <td><?= (int)$m['id'] ?></td>
                          <td><?= htmlspecialchars(($m['produto_nome'] ?? '-') . ' (' . ($m['unidade'] ?? 'UN') . ')', ENT_QUOTES, 'UTF-8') ?></td>
                          <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($tipo) ?></span></td>
                          <td><?= htmlspecialchars((string)$m['origem'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end"><?= number_format((float)$m['qtd'], 0, ',', '.') ?></td>
                          <td><?= htmlspecialchars((string)$m['quando'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">
          © <script>
            document.write(new Date().getFullYear())
          </script>
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