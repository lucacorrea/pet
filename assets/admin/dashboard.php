<?php

// autoErp/admin/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth_guard.php';
guard_super_admin(); // garante login e perfil

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ===== Conexão PDO ($pdo) ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // aqui dentro o arquivo deve definir $pdo (PDO)
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  // opcional: redirecionar/avisar se o banco estiver indisponível
  // header('Location: ../index.php?erro=2&msg=' . urlencode('Banco indisponível'));
  // exit;
}

/* ===== Controller (usa $pdo para montar variáveis do dashboard) ===== */
require_once __DIR__ . '/controllers/dashboardController.php';

/* ===== Flash ===== */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');

/* ===== Helper opcional ===== */
function badge_status(string $s): string
{
  return $s === 'ativa'
    ? '<span class="badge bg-success">Ativa</span>'
    : '<span class="badge bg-secondary">Inativa</span>';
}

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>AutoERP - Dashboard (Admin)</title>
  <link rel="icon" type="image/png" sizes="512x512" href="../public/assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../public/assets/images/favicon.ico">
  <link rel="stylesheet" href="../public/assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../public/assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../public/assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../public/assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../public/assets/css/dark.min.css">
  <link rel="stylesheet" href="../public/assets/css/customizer.min.css">
  <link rel="stylesheet" href="../public/assets/css/customizer.css">
  <link rel="stylesheet" href="../public/assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    /* ajustes tipográficos e espaçamentos dos cards */
    #inicio {
      margin-top: -60px !important;
    }

    .k-mini {
      font-size: 1.2rem;
    }

    .k-mini .dropdown-item {
      font-size: 1.2rem;
    }

    .k-card p {
      font-size: 1rem;
      margin-bottom: .25rem;
    }

    .k-card h4 {
      font-size: 1.5rem;
    }

    .k-gap .swiper-wrapper {
      gap: .5rem;
    }
  </style>
</head>

<body class="">
  <!-- Sidebar -->
  <aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all ">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
      <a href="#" class="navbar-brand">
        <div class="logo-main">
          <div class="logo-normal"><img src="../public/assets/images/auth/ode.png" alt="logo" class="logo-dashboard"></div>
        </div>
        <h4 class="logo-title title-dashboard">AutoERP</h4>
      </a>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
      <div class="sidebar-list">
        <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-grid icon"></i><span class="item-name">Dashboard</span></a></li>
          <li>
            <hr class="hr-horizontal">
          </li>
          <li class="nav-item"><a class="nav-link" href="./pages/solicitacao.php"><i class="bi bi-check2-square icon"></i><span class="item-name">Solicitações</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./pages/empresa.php"><i class="bi bi-building icon"></i><span class="item-name">Empresas</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./pages/cadastrarUsuario.php"><i class="bi bi-person-plus icon"></i><span class="item-name">Cadastrar Usuário</span></a></li>
          <li>
            <hr class="hr-horizontal">
          </li>
          <li class="nav-item"><a class="nav-link" href="../actions/logout.php" class="bi bi-box-arrow-right icon"></i><span class="item-name">Sair</span></a></li>
        </ul>
      </div>
    </div>
    <div class="sidebar-footer"></div>
  </aside>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="#" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </span>
            <input type="search" class="form-control" placeholder="Pesquisar...">
          </div>
        </div>
      </nav>
      <div class="iq-navbar-header" style="height: 215px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <div class="flex-wrap d-flex justify-content-between align-items-center">
                <div>
                  <h1>Bem-vindo, <?= htmlspecialchars($nomeUser, ENT_QUOTES, 'UTF-8') ?>!</h1>
                  <p>Gerencie empresas, usuários e aprove solicitações dos donos aqui no painel do Admin.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../public/assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner py-0">
      <div class="row">
        <div class="col-md-12 col-lg-12">

          <!-- FILTRO DE PERÍODO -->
          <div class="d-flex justify-content-end align-items-center mb-2 k-mini" id="inicio">
            <div class="dropdown">
              <a href="#" class="text-gray dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <?= htmlspecialchars($labelPeriodo, ENT_QUOTES, 'UTF-8') ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?p=today#cards">Hoje</a></li>
                <li><a class="dropdown-item" href="?p=7d#cards">Últimos 7 dias</a></li>
                <li><a class="dropdown-item" href="?p=month#cards">Este mês</a></li>
                <li><a class="dropdown-item" href="?p=3m#cards">Últimos 3 meses</a></li>
                <li><a class="dropdown-item" href="?p=year#cards">Este ano</a></li>
              </ul>
            </div>
          </div>

          <!-- CARDS (apenas 3 no exemplo do usuário) -->
          <div id="cards" class="overflow-hidden d-slider1 k-gap">
            <ul class="p-0 m-0 mb-2 swiper-wrapper list-inline">

              <!-- Solicitações recebidas (período) -->
              <li class="card card-slide col-12 col-sm-6 col-xl-4 mb-3 k-card" data-aos="fade-up" data-aos-delay="800">
                <div class="card-body">
                  <div class="progress-widget">
                    <div id="circle-sol-periodo" class="text-center circle-progress-01 circle-progress circle-progress-info"
                      data-min-value="0" data-max-value="100" data-value="<?= $pctSolPeriodo ?>" data-type="percent">
                      <svg class="card-slie-arrow icon-24" width="20" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19,6.41L17.59,5L7,15.59V9H5V19H15V17H8.41L19,6.41Z" />
                      </svg>
                    </div>
                    <div class="progress-detail">
                      <p class="mb-1">Solicitações recebidas — <span class="text-muted"><?= htmlspecialchars($labelPeriodo, ENT_QUOTES, 'UTF-8') ?></span></p>
                      <h4 class="counter mb-0"><?= number_format($solicitacoesPeriodo, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                </div>
              </li>

              <!-- Solicitações pendentes (agora) -->
              <li class="card card-slide col-12 col-sm-6 col-xl-4 mb-3 k-card" data-aos="fade-up" data-aos-delay="900">
                <div class="card-body">
                  <div class="progress-widget">
                    <div id="circle-sol-pend" class="text-center circle-progress-01 circle-progress circle-progress-warning"
                      data-min-value="0" data-max-value="100" data-value="<?= $pctSolPend ?>" data-type="percent">
                      <svg class="card-slie-arrow icon-24" width="20" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M5,17.59L15.59,7H9V5H19V15H17V8.41L6.41,19L5,17.59Z" />
                      </svg>
                    </div>
                    <div class="progress-detail">
                      <p class="mb-1">Solicitações pendentes — <span class="text-muted">agora</span></p>
                      <h4 class="counter mb-0"><?= number_format($solicitacoesPendentes, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                </div>
              </li>

              <!-- Empresas ativas (total) -->
              <li class="card card-slide col-12 col-sm-6 col-xl-4 mb-3 k-card" data-aos="fade-up" data-aos-delay="1000">
                <div class="card-body">
                  <div class="progress-widget">
                    <div id="circle-empr-ativas" class="text-center circle-progress-01 circle-progress circle-progress-success"
                      data-min-value="0" data-max-value="100" data-value="<?= $pctEmpAtivas ?>" data-type="percent">
                      <svg class="card-slie-arrow icon-24" width="20" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19,6.41L17.59,5L7,15.59V9H5V19H15V17H8.41L19,6.41Z" />
                      </svg>
                    </div>
                    <div class="progress-detail">
                      <p class="mb-1">Empresas ativas — <span class="text-muted">total</span></p>
                      <h4 class="counter mb-0"><?= number_format($empresasAtivasTotal, 0, ',', '.') ?></h4>
                    </div>
                  </div>
                </div>
              </li>

            </ul>
          </div>

        </div>

        <div class="col-md-12 col-lg-12">
          <div class="row">
            <!-- GRÁFICO -->
            <div class="col-md-12">
              <div class="card" data-aos="fade-up" data-aos-delay="800">
                <div class="flex-wrap card-header d-flex justify-content-between align-items-center">
                  <div class="header-title">
                    <h4 class="card-title">Panorama — Empresas Ativas</h4>
                    <p class="mb-0">Últimos 6 meses</p>
                  </div>
                  <div class="dropdown">
                    <a href="#" class="text-gray dropdown-toggle" data-bs-toggle="dropdown">Últimos 6 meses</a>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><span class="dropdown-item">Últimos 6 meses</span></li>
                    </ul>
                  </div>
                </div>
                <div class="card-body">
                  <div id="sa-main-chart" class="d-main"></div>
                </div>
              </div>
            </div>

            <!-- SOLICITAÇÕES PENDENTES -->
            <div class="col-md-12 col-lg-12" id="solicitacoes">
              <div class="overflow-hidden card" data-aos="fade-up" data-aos-delay="600">
                <div class="flex-wrap card-header d-flex justify-content-between">
                  <div class="header-title">
                    <h4 class="mb-2 card-title">Solicitações Pendentes</h4>
                    <p class="mb-0">Aprovar ou recusar cadastros de donos de autopeças</p>
                  </div>
                  <?php if ($ok || $err): ?>
                    <div class="ms-2">
                      <?php if ($ok): ?><div class="alert alert-success py-1 px-2 mb-0"><?= $msg ?: 'Operação realizada com sucesso.' ?></div><?php endif; ?>
                      <?php if ($err): ?><div class="alert alert-danger py-1 px-2 mb-0"><?= $msg ?: 'Falha na operação.' ?></div><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="p-0 card-body">
                  <div class="mt-4 table-responsive">
                    <table class="table mb-0 table-striped align-middle">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Empresa</th>
                          <th>CNPJ</th>
                          <th>Proprietário</th>
                          <th>Contato</th>
                          <th>Solicitado em</th>
                          <th class="text-end">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!$solicitacoes): ?>
                          <tr>
                            <td colspan="7" class="text-center text-muted">Nenhuma solicitação pendente.</td>
                          </tr>
                          <?php else: foreach ($solicitacoes as $r): ?>
                            <tr>
                              <td><?= (int)$r['id'] ?></td>
                              <td>
                                <div class="fw-semibold"><?= htmlspecialchars($r['nome_fantasia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($r['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                              </td>
                              <td><?= htmlspecialchars($r['cnpj'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                              <td>
                                <div class="fw-semibold"><?= htmlspecialchars($r['proprietario_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($r['proprietario_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                              </td>
                              <td><?= htmlspecialchars($r['telefone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                              <td><?= htmlspecialchars($r['criado_em'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                              <td class="text-end">
                                <form class="d-inline" action="./actions/solicitacoesRecusar.php" method="post">
                                  <input type="hidden" name="sol_id" value="<?= (int)$r['id'] ?>">
                                  <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i> Recusar</button>
                                </form>
                                <form class="d-inline" action="./actions/solicitacoesAprovar.php" method="post" onsubmit="return aprovarHandler(this);">
                                  <input type="hidden" name="sol_id" value="<?= (int)$r['id'] ?>">
                                  <input type="hidden" name="cnpj" value="<?= htmlspecialchars($r['cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                  <button class="btn btn-sm btn-success" type="submit"
                                    data-need-cnpj="<?= empty($r['cnpj']) ? '1' : '0' ?>"
                                    data-empresa="<?= htmlspecialchars($r['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-check2-circle"></i> Aprovar
                                  </button>
                                </form>
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
            <!-- /Solicitações -->
          </div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script>
        </div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <!-- JS -->
  <script src="../public/assets/js/core/libs.min.js"></script>
  <script src="../public/assets/js/core/external.min.js"></script>
  <script src="../public/assets/js/charts/widgetcharts.js"></script>
  <script src="../public/assets/js/charts/vectore-chart.js"></script>
  <script src="../public/assets/js/plugins/fslightbox.js"></script>
  <script src="../public/assets/js/plugins/setting.js"></script>
  <script src="../public/assets/js/plugins/slider-tabs.js"></script>
  <script src="../public/assets/js/plugins/form-wizard.js"></script>
  <script src="../public/assets/vendor/aos/dist/aos.js"></script>
  <script src="../public/assets/js/hope-ui.js" defer></script>
  <!-- ApexCharts local -->
  <script src="../public/assets/js/charts/apexcharts.js"></script>

  <!-- Dados do gráfico (vindos do PHP) -->
  <script>
    window.DASHBOARD_LABELS = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    window.DASHBOARD_SERIES = <?= json_encode($serieEmpresas, JSON_NUMERIC_CHECK) ?>;
  </script>

  <script>
    // dashboard_View.js

    function aprovarHandler(form) {
      const btn = form.querySelector('button[type="submit"]');
      if (btn && btn.dataset.needCnpj === '1') {
        const empresa = btn.dataset.empresa || 'a empresa';
        let cnpj = prompt('Informe o CNPJ para aprovar ' + empresa + ' (somente números):');
        if (!cnpj) return false;
        cnpj = cnpj.replace(/\D+/g, '');
        if (cnpj.length < 14) {
          alert('CNPJ inválido.');
          return false;
        }
        form.querySelector('input[name="cnpj"]').value = cnpj;
      }
      return true;
    }

    // Gráfico único: Empresas ativas (linha conectando os pontos)
    (function() {
      const el = document.getElementById('sa-main-chart');
      if (!el) return;

      const LABELS = window.DASHBOARD_LABELS || [];
      const SERIES_EMP = (window.DASHBOARD_SERIES || []).map(v => (v == null ? null : Number(v)));

      if (typeof ApexCharts === 'undefined') {
        el.innerHTML = '<pre>Empresas ativas: ' + SERIES_EMP.join(', ') + '</pre>';
        return;
      }

      const options = {
        chart: {
          type: 'line',
          height: 360,
          toolbar: {
            show: false
          }
        },
        series: [{
          name: 'Empresas ativas',
          data: SERIES_EMP,
          // Mantém a linha contínua mesmo com pontos nulos:
          connectNulls: true
        }],
        xaxis: {
          categories: LABELS
        },
        stroke: {
          width: 3,
          curve: 'smooth'
        }, // largura > 0 garante a linha
        markers: {
          size: 3
        },
        dataLabels: {
          enabled: false
        },
        legend: {
          position: 'top'
        },
        grid: {
          borderColor: 'rgba(0,0,0,0.1)'
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.3,
            opacityTo: 0.0
          }
        }
      };

      new ApexCharts(el, options).render();
    })();
  </script>


</body>

</html>