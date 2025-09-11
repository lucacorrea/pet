<?php
// autoErp/admin/pages/solicitacao.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin(); // exige login e perfil super_admin

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ===== CSRF para ações do admin (aprovar/recusar) ===== */
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

/* ===== Conexão PDO ($pdo) ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // deve definir $pdo
}

/* ===== Controller: carrega filtros, totais e lista ($status, $rotuloStatus, $buscar, $totais, $solicitacoes) ===== */
require_once __DIR__ . '/../controllers/solicitacaoController.php';

/* ===== Flash ===== */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');

/* ===== Helpers opcionais ===== */
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Solicitações</title>

  <link rel="icon" type="image/png" href="../../public/assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../public/assets/images/favicon.ico">

  <link rel="stylesheet" href="../../public/assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../public/assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../public/assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../public/assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../public/assets/css/dark.min.css">
  <link rel="stylesheet" href="../../public/assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../public/assets/css/customizer.css">
  <link rel="stylesheet" href="../../public/assets/css/rtl.min.css">
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
          <div class="logo-normal"><img src="../../public/assets/images/auth/ode.png" alt="logo" class="logo-dashboard"></div>
        </div>
        <h4 class="logo-title title-dashboard">AutoERP</h4>
      </a>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
      <div class="sidebar-list">
        <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
          <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-grid icon"></i><span class="item-name">Dashboard</span></a></li>
          <li>
            <hr class="hr-horizontal">
          </li>
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-check2-square icon"></i><span class="item-name">Solicitações</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./empresa.php"><i class="bi bi-building icon"></i><span class="item-name">Empresas</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./cadastrarUsuario.php"><i class="bi bi-person-plus icon"></i><span class="item-name">Cadastrar Usuário</span></a></li>
          <li>
            <hr class="hr-horizontal">
          </li>
          <li class="nav-item"><a class="nav-link" href="../../actions/logout.php"><i class="bi bi-box-arrow-right icon"></i><span class="item-name">Sair</span></a></li>
        </ul>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
              </svg>
            </span>
            <form class="d-flex" method="get">
              <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
              <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por empresa, CNPJ, proprietário...">
            </form>
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 180px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h1>Solicitações de Empresas — <?= htmlspecialchars($rotuloStatus, ENT_QUOTES, 'UTF-8') ?></h1>
                  <p>Gerencie os pedidos de cadastro enviados pelos donos de autopeças.</p>
                </div>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm <?= $status === 'pendente' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?status=pendente">Pendentes (<?= (int)$totais['pendente'] ?>)</a>
                  <a class="btn btn-sm <?= $status === 'aprovada' ? 'btn-success' : 'btn-outline-success' ?>" href="?status=aprovada">Aprovadas (<?= (int)$totais['aprovada'] ?>)</a>
                  <a class="btn btn-sm <?= $status === 'recusada' ? 'btn-danger' : 'btn-outline-danger' ?>" href="?status=recusada">Recusadas (<?= (int)$totais['recusada'] ?>)</a>
                  <a class="btn btn-sm <?= $status === 'todas' ? 'btn-dark' : 'btn-outline-dark' ?>" href="?status=todas">Todas (<?= (int)$totais['todas'] ?>)</a>
                </div>
              </div>

              <?php if ($ok || $err): ?>
                <div class="mt-3">
                  <?php if ($ok): ?><div class="alert alert-success py-2 mb-0"><?= $msg ?: 'Operação realizada com sucesso.' ?></div><?php endif; ?>
                  <?php if ($err): ?><div class="alert alert-danger py-2  mb-0"><?= $msg ?: 'Falha na operação.' ?></div><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../public/assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n4 py-0">
      <div class="row">
        <div class="col-12">
          <div class="card" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header">
              <h4 class="card-title mb-0">Lista de Solicitações</h4>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle table-responsive">
                  <thead>
                    <tr>
                      <th style="width:70px">#</th>
                      <th>Empresa</th>
                      <th>CNPJ</th>
                      <th>Proprietário</th>
                      <th>Contato</th>
                      <th>Solicitada em</th>
                      <th class="text-end" style="width:220px">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$solicitacoes): ?>
                      <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                      </tr>
                      <?php else: foreach ($solicitacoes as $r): ?>
                        <tr>
                          <td class="text-nowrap"><?= (int)$r['id'] ?></td>
                          <td class="text-nowrap">
                            <div class="fw-semibold"><?= htmlspecialchars($r['nome_fantasia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($r['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                          </td>
                          <td class="text-nowrap"><?= htmlspecialchars($r['cnpj'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-nowrap">
                            <div class="fw-semibold"><?= htmlspecialchars($r['proprietario_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($r['proprietario_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                          </td>
                          <td class="text-nowrap"><?= htmlspecialchars($r['telefone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-nowrap"><?= htmlspecialchars($r['criado_em'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end">
                            <?php if ($r['status'] === 'pendente'): ?>
                              <form class="d-inline" action="../actions/solicitacaoRecusar.php" method="post">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_admin'] ?>">
                                <input type="hidden" name="sol_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                  <i class="bi bi-x-circle"></i> Recusar
                                </button>
                              </form>
                              <form class="d-inline sa-approve-form" action="../actions/solicitacaoAprovar.php" method="post">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_admin'] ?>">
                                <input type="hidden" name="sol_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="cnpj" value="<?= htmlspecialchars(preg_replace('/\D+/', '', (string)($r['cnpj'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                <button
                                  class="btn btn-sm btn-success sa-approve-btn"
                                  type="submit"
                                  data-need-cnpj="<?= empty(preg_replace('/\D+/', '', (string)($r['cnpj'] ?? ''))) ? '1' : '0' ?>"
                                  data-empresa="<?= htmlspecialchars($r['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                  <i class="bi bi-check2-circle"></i> Aprovar
                                </button>
                              </form>
                            <?php elseif ($r['status'] === 'aprovada'): ?>
                              <span class="badge bg-success">Aprovada</span>
                            <?php else: ?>
                              <span class="badge bg-danger">Recusada</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div> <!-- /card -->
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> AutoERP</div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../public/assets/js/core/libs.min.js"></script>
  <script src="../../public/assets/js/core/external.min.js"></script>
  <script src="../../public/assets/vendor/aos/dist/aos.js"></script>
  <script src="../../public/assets/js/hope-ui.js" defer></script>

  <!-- JS específico da página -->
  <script src="../../public/assets/js/admin/solicitacao.js"></script>
</body>

</html>