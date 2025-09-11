<?php
// autoErp/admin/pages/cadastrarUsuario.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin(); // exige login e perfil super_admin

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/** CSRF para telas do admin */
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

/** Conexão PDO ($pdo) */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // define $pdo
} else {
  die('Conexão indisponível.');
}

/** Controller carrega $empresas */
require_once __DIR__ . '/../controllers/cadastrarUsuarioController.php';

/** Flash */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Cadastrar Usuário</title>

  <link rel="icon" type="image/png" href="../../public/assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../public/assets/images/favicon.ico">

  <link rel="stylesheet" href="../../public/assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../public/assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../public/assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../public/assets/css/dark.min.css">
  <link rel="stylesheet" href="../../public/assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../public/assets/css/customizer.css">
  <link rel="stylesheet" href="../../public/assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="">
  <!-- Sidebar -->
  <aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
      <a href="../dashboard.php" class="navbar-brand">
        <div class="logo-main"><div class="logo-normal"><img src="../../public/assets/images/auth/ode.png" alt="logo" class="logo-dashboard"></div></div>
        <h4 class="logo-title title-dashboard">AutoERP</h4>
      </a>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
      <div class="sidebar-list">
        <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
          <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-grid icon"></i><span class="item-name">Dashboard</span></a></li>
          <li><hr class="hr-horizontal"></li>
          <li class="nav-item"><a class="nav-link" href="./solicitacao.php"><i class="bi bi-check2-square icon"></i><span class="item-name">Solicitações</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./empresa.php"><i class="bi bi-building icon"></i><span class="item-name">Empresas</span></a></li>
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-person-plus icon"></i><span class="item-name">Cadastrar Usuário</span></a></li>
          <li><hr class="hr-horizontal"></li>
          <li class="nav-item"><a class="nav-link" href="../../actions/logout.php"><i class="bi bi-box-arrow-right icon"></i><span class="item-name">Sair</span></a></li>
        </ul>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../dashboard.php" class="navbar-brand"><h4 class="logo-title">AutoERP</h4></a>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 160px; margin-bottom:35px !important;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h1>Cadastrar Usuário</h1>
                  <p>Crie um usuário para uma empresa ativa. O usuário receberá um e-mail para definir a senha.</p>
                </div>
              </div>

              <?php if ($ok || $err): ?>
                <div class="mt-3">
                  <?php if ($ok):  ?><div class="alert alert-success py-2 mb-0"><?= $msg ?: 'Usuário criado e e-mail enviado.' ?></div><?php endif; ?>
                  <?php if ($err): ?><div class="alert alert-danger  py-2 mb-0"><?= $msg ?: 'Falha ao criar usuário.' ?></div><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="iq-header-img" >
          <img src="../../public/assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner  py-0 mt-4">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Dados do Usuário</h4></div>
            <div class="card-body">
              <form action="../actions/usuarioCriar.php" method="post" autocomplete="off" id="form-cad-user" novalidate>
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_admin'] ?>">

                <div class="mb-3">
                  <label class="form-label">Empresa *</label>
                  <select name="empresa_cnpj" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($empresas as $e): ?>
                      <option value="<?= htmlspecialchars(preg_replace('/\D+/', '', (string)$e['cnpj']), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(($e['nome_fantasia'] ?: '—') . ' — ' . $e['cnpj'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Apenas empresas ativas aparecem na lista.</div>
                </div>

                <div class="row">
                  <div class="col-md-7 mb-3">
                    <label class="form-label">Nome completo *</label>
                    <input type="text" name="nome" class="form-control" maxlength="120" required>
                  </div>
                  <div class="col-md-5 mb-3">
                    <label class="form-label">CPF (opcional)</label>
                    <input type="text" name="cpf" class="form-control" maxlength="14" placeholder="somente números">
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-7 mb-3">
                    <label class="form-label">E-mail *</label>
                    <input type="email" name="email" class="form-control" maxlength="150" required>
                  </div>
                  <div class="col-md-5 mb-3">
                    <label class="form-label">Telefone (opcional)</label>
                    <input type="text" name="telefone" class="form-control" maxlength="20">
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Perfil *</label>
                    <select name="perfil" id="perfil" class="form-select" required>
                      <option value="dono">Dono</option>
                      <option value="funcionario">Funcionário</option>
                      <!-- Super Admin não é criado aqui -->
                    </select>
                    <div class="form-text">Dono tem acesso total à empresa.</div>
                  </div>
                  <div class="col-md-6 mb-3" id="box-tipo">
                    <label class="form-label">Tipo de Funcionário *</label>
                    <select name="tipo_funcionario" id="tipo_funcionario" class="form-select">
                      <option value="administrativo">Administrativo</option>
                      <option value="caixa">Caixa</option>
                      <option value="estoque">Estoque</option>
                      <option value="lavajato">Lavador</option>
                    </select>
                    <div class="form-text">Para Dono usaremos “Administrativo”.</div>
                  </div>
                </div>

                <div class="alert alert-info">
                  <i class="bi bi-info-circle"></i>
                  Não definimos senha aqui. O usuário receberá um e-mail para criar a própria senha (código de 6 dígitos, válido por 15 min).
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Criar Usuário</button>
                  <a href="./usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
              </form>
            </div>
          </div>
        </div> <!-- col -->
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>document.write(new Date().getFullYear())</script> AutoERP</div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../public/assets/js/core/libs.min.js"></script>
  <script src="../../public/assets/js/core/external.min.js"></script>
  <script src="../../public/assets/js/hope-ui.js" defer></script>

  <!-- JS específico (separado da página) -->
  <script src="../../public/assets/js/admin/cadastrarUsuario.js"></script>
</body>
</html>
