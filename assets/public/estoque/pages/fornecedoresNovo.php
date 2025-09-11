<?php
// autoErp/public/estoque/pages/fornecedoresNovo.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']); // podem cadastrar fornecedor

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// CSRF
if (empty($_SESSION['csrf_forn_novo'])) {
  $_SESSION['csrf_forn_novo'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_forn_novo'];

// flash
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Novo Fornecedor</title>
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
  <aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
      <a href="#" class="navbar-brand">
        <div class="logo-main">
          <div class="logo-normal"><img src="../../assets/images/auth/ode.png" alt="logo" class="logo-dashboard"></div>
        </div>
        <h4 class="logo-title title-dashboard">AutoERP</h4>
      </a>
      <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
        <i class="icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </i>
      </div>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
      <div class="sidebar-list">
        <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
          <!-- DASHBOARD -->
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="../../dashboard.php">
              <i class="bi bi-grid icon"></i><span class="item-name">Dashboard</span>
            </a>
          </li>
          <li>
            <hr class="hr-horizontal">
          </li>

          <!-- VENDAS -->
          <li class="nav-item">
            <a class="nav-link" href="../../vendas/pages/vendaRapida.php">
              <i class="bi bi-cash-coin icon"></i><span class="item-name">Venda Rápida</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../../vendas/pages/orcamentos.php">
              <i class="bi bi-file-earmark-text icon"></i><span class="item-name">Orçamentos</span>
            </a>
          </li>

          <!-- LAVA JATO -->
          <li class="nav-item">
            <a class="nav-link" href="../../lavajato/pages/lavagemRapida.php">
              <i class="bi bi-plus-circle icon"></i><span class="item-name">Lavagem Rápida</span>
            </a>
          </li>
          <!--LAVAJATO  -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-lavajato" role="button" aria-expanded="false" aria-controls="sidebar-lavajato">
              <i class="bi bi-droplet icon"></i><span class="item-name">Lava Jato</span><i class="bi bi-chevron-right right-icon"></i>
            </a>
            <ul class="sub-nav collapse" id="sidebar-lavajato" data-bs-parent="#sidebar-menu">
              <li class="nav-item">
                <a class="nav-link" href="../../lavajato/pages/lavagens.php">
                  <i class="bi bi-list icon"></i><span class="item-name">Lista Lavagens</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../lavajato/pages/lavadores.php">
                  <i class="bi bi-people icon"></i><span class="item-name">Lista Lavadores</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../lavajato/pages/servicos.php">
                  <i class="bi bi-wrench icon"></i><span class="item-name">Lista Serviços</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../lavajato/pages/lavadoresNovo.php">
                  <i class="bi bi-person-plus icon"></i><span class="item-name">Add Lavador</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../lavajato/pages/servicosNovo.php">
                  <i class="bi bi-plus-circle icon"></i><span class="item-name">Add Serviço</span>
                </a>
              </li>
            </ul>
          </li>

          <!-- ESTOQUE -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-estoque" role="button" aria-expanded="true" aria-controls="sidebar-estoque">
              <i class="bi bi-truck icon"></i><span class="item-name">Estoque</span><i class="bi bi-chevron-right right-icon"></i>
            </a>
            <ul class="sub-nav collapse show" id="sidebar-estoque" data-bs-parent="#sidebar-menu">
              <li class="nav-item">
                <a class="nav-link" href="./estoque.php">
                  <i class="bi bi-box icon"></i><span class="item-name">Lista Estoque</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./produtos.php">
                  <i class="bi bi-gear icon"></i><span class="item-name">Lista Produtos</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./fornecedores.php">
                  <i class="bi bi-person-check icon"></i><span class="item-name">Lista Fornecedores</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" href="./fornecedoresNovo.php">
                  <i class="bi bi-journal-text icon"></i><span class="item-name">Add Fornecedor</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./produtosNovo.php">
                  <i class="bi bi-arrow-down-circle icon"></i><span class="item-name">Add Produto</span>
                </a>
              </li>
            </ul>
          </li>

          <!-- RELATÓRIOS -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-relatorios" role="button" aria-expanded="false" aria-controls="sidebar-relatorios">
              <i class="bi bi-clipboard-data icon"></i><span class="item-name">Relatórios</span><i class="bi bi-chevron-right right-icon"></i>
            </a>
            <ul class="sub-nav collapse" id="sidebar-relatorios" data-bs-parent="#sidebar-menu">
              <li class="nav-item">
                <a class="nav-link" href="../../vendas/pages/relatorioVendas.php">
                  <i class="bi bi-bar-chart icon"></i><span class="item-name">Vendas</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../vendas/pages/relatorioFinanceiro.php">
                  <i class="bi bi-graph-up-arrow icon"></i><span class="item-name">Financeiro</span>
                </a>
              </li>
            </ul>
          </li>

          <!-- CONFIGURAÇÕES -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-config" role="button" aria-expanded="false" aria-controls="sidebar-config">
              <i class="bi bi-gear icon"></i><span class="item-name">Configurações</span><i class="bi bi-chevron-right right-icon"></i>
            </a>
            <ul class="sub-nav collapse" id="sidebar-config" data-bs-parent="#sidebar-menu">
              <li class="nav-item">
                <a class="nav-link" href="../../configuracao/pages/listar.php">
                  <i class="bi bi-people icon"></i><span class="item-name">Usuários</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../configuracao/pages/novo.php">
                  <i class="bi bi-person-plus icon"></i><span class="item-name">Add Usuários</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../../configuracao/pages/empresa.php">
                  <i class="bi bi-building icon"></i><span class="item-name">Dados da Empresa</span>
                </a>
              </li>
            </ul>
          </li>

          <li>
            <hr class="hr-horizontal">
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../../actions/logout.php">
              <i class="bi bi-box-arrow-right icon"></i><span class="item-name">Sair</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
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

              </svg>
            </span>

          </div>
        </div>
      </nav>
      <div class="iq-navbar-header" style="height: 150px; margin-bottom: 50px ;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <h1 class="mb-0">Cadastrar Fornecedor</h1>
              <p>Informe os dados do fornecedor da sua empresa.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" alt="" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="row">
        <div class="col-12">
          <div class="card" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header">
              <h4 class="card-title mb-0">Dados do Fornecedor</h4>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/fornecedoresSalvar.php" id="form-forn">
                <input type="hidden" name="op" value="fornecedor_novo">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required maxlength="180">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">CNPJ/CPF (opcional)</label>
                    <input type="text" name="cnpj_cpf" class="form-control" maxlength="20" placeholder="Apenas números">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">E-mail (opcional)</label>
                    <input type="email" name="email" class="form-control" maxlength="150">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Telefone (opcional)</label>
                    <input type="text" name="telefone" class="form-control" maxlength="20">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">CEP (opcional)</label>
                    <input type="text" name="cep" class="form-control" maxlength="10">
                  </div>

                  <div class="col-md-8">
                    <label class="form-label">Endereço (opcional)</label>
                    <input type="text" name="endereco" class="form-control">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Cidade (opcional)</label>
                    <input type="text" name="cidade" class="form-control" maxlength="100">
                  </div>
                  <div class="col-md-1">
                    <label class="form-label">UF</label>
                    <input type="text" name="estado" class="form-control" maxlength="2">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Observações (opcional)</label>
                    <textarea name="obs" class="form-control" rows="3"></textarea>
                  </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar</button>
                </div>
              </form>
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