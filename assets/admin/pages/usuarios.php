<?php
// autoErp/admin/pages/usuarios.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin(); // exige login e perfil super_admin

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ===== DEBUG OPCIONAL (desligue em produção) ===== */
// ini_set('display_errors', '1'); error_reporting(E_ALL);

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

/* ===== Conexão PDO ($pdo) ===== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // deve definir $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Falha na conexão com o banco de dados.');
}

/* ===== Controller (lista/contadores/filtros) ===== */
$ctrlOk = false;
$ctrlPlural   = __DIR__ . '/../controllers/usuariosController.php';
$ctrlSingular = __DIR__ . '/../controllers/usuarioController.php';

if (file_exists($ctrlPlural)) {
  require_once $ctrlPlural;   // define: $status, $rotuloStatus, $buscar, $cnpjFilter, $totais, $usuarios, $pages, $page
  $ctrlOk = true;
} elseif (file_exists($ctrlSingular)) {
  require_once $ctrlSingular; // compatibilidade se o arquivo estiver no singular
  $ctrlOk = true;
}

if (!$ctrlOk) {
  http_response_code(500);
  die('Controller de usuários não encontrado (usuariosController.php).');
}

/* ===== Flash ===== */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');

/* ===== Helpers ===== */
function badge_user(string $perfil, ?string $tipo): string {
  $perfil = strtolower($perfil);
  $tipo   = $tipo ? strtolower($tipo) : '';
  if ($perfil === 'super_admin') return '<span class="badge bg-dark">Super Admin</span>';
  if ($perfil === 'dono')        return '<span class="badge bg-primary">Dono</span>';
  $rot = [
    'caixa' => 'Caixa',
    'estoque' => 'Estoque',
    'administrativo' => 'Administrativo',
    'lavajato' => 'Lavador'
  ][$tipo] ?? 'Funcionário';
  return '<span class="badge bg-info text-dark">' . htmlspecialchars($rot, ENT_QUOTES, 'UTF-8') . '</span>';
}
function badge_status_user(int $s): string {
  return $s === 1 ? '<span class="badge bg-success">Ativo</span>'
                  : '<span class="badge bg-secondary">Inativo</span>';
}
function fmt_cnpj(?string $c): string {
  $n = preg_replace('/\D+/', '', (string)$c);
  if (strlen($n) !== 14) return htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8');
  return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Usuários</title>

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
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-people icon"></i><span class="item-name">Usuários</span></a></li>
          <li class="nav-item"><a class="nav-link" href="./cadastrarUsuario.php"><i class="bi bi-person-plus icon"></i><span class="item-name">Cadastrar Usuário</span></a></li>
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
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
              </svg>
            </span>
            <form class="d-flex" method="get">
              <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
              <?php if (!empty($cnpjFilter)): ?>
                <input type="hidden" name="cnpj" value="<?= htmlspecialchars($cnpjFilter, ENT_QUOTES, 'UTF-8') ?>">
              <?php endif; ?>
              <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome, e-mail, CPF, empresa...">
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
                  <h1>Usuários — <?= htmlspecialchars($rotuloStatus, ENT_QUOTES, 'UTF-8') ?></h1>
                  <p>
                    Visualize os usuários do sistema. Para bloquear acessos, inative a empresa correspondente.
                    <?php if (!empty($cnpjFilter)): ?>
                      <br><small class="text-muted">Filtro por empresa: <?= fmt_cnpj($cnpjFilter) ?></small>
                    <?php endif; ?>
                  </p>
                </div>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm <?= $status==='ativos'   ? 'btn-success'        : 'btn-outline-success'?>"   href="?status=ativos<?= $cnpjFilter ? '&cnpj='.urlencode($cnpjFilter) : '' ?>">Ativos (<?= (int)$totais['ativos'] ?>)</a>
                  <a class="btn btn-sm <?= $status==='inativos' ? 'btn-secondary'      : 'btn-outline-secondary'?>" href="?status=inativos<?= $cnpjFilter ? '&cnpj='.urlencode($cnpjFilter) : '' ?>">Inativos (<?= (int)$totais['inativos'] ?>)</a>
                  <a class="btn btn-sm <?= $status==='todos'    ? 'btn-dark'           : 'btn-outline-dark'?>"      href="?status=todos<?= $cnpjFilter ? '&cnpj='.urlencode($cnpjFilter) : '' ?>">Todos (<?= (int)$totais['todos'] ?>)</a>
                </div>
              </div>

              <?php if ($ok || $err): ?>
                <div class="mt-3">
                  <?php if ($ok):  ?><div class="alert alert-success  py-2 mb-0"><?= $msg ?: 'Operação realizada com sucesso.' ?></div><?php endif; ?>
                  <?php if ($err): ?><div class="alert alert-danger   py-2 mb-0"><?= $msg ?: 'Falha na operação.' ?></div><?php endif; ?>
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
              <h4 class="card-title mb-0">Lista de Usuários</h4>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                  <thead>
                    <tr>
                      <th class="text-nowrap" style="width:70px">#</th>
                      <th class="text-nowrap">Usuário</th>
                      <th class="text-nowrap">Perfil / Tipo</th>
                      <th class="text-nowrap">Empresa</th>
                      <th class="text-nowrap">Status</th>
                      <th class="text-nowrap">Criado em</th>
                      <th class="text-end text-nowrap" style="width:220px">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($usuarios)): ?>
                      <tr><td colspan="7" class="text-center text-muted py-4">Nenhum usuário encontrado.</td></tr>
                    <?php else: foreach ($usuarios as $u): ?>
                      <tr>
                        <td class="text-nowrap"><?= (int)$u['id'] ?></td>
                        <td class="text-nowrap">
                          <div class="fw-semibold"><?= htmlspecialchars($u['nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted">
                            <?= htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($u['cpf'])): ?> · CPF: <?= htmlspecialchars($u['cpf'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                          </div>
                        </td>
                        <td class="text-nowrap"><?= badge_user((string)$u['perfil'], $u['tipo_funcionario'] ?? null) ?></td>
                        <td class="text-nowrap">
                          <div class="fw-semibold"><?= htmlspecialchars($u['empresa_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted"><?= fmt_cnpj($u['empresa_cnpj'] ?? '') ?></div>
                        </td>
                        <td class="text-nowrap"><?= badge_status_user((int)$u['status']) ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars($u['criado_em'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end text-nowrap">
                          <a class="btn btn-sm btn-outline-primary" href="./empresa.php?cnpj=<?= urlencode((string)$u['empresa_cnpj']) ?>">
                            <i class="bi bi-building"></i> Ver Empresa
                          </a>
                          <!-- Desativação é pela EMPRESA -->
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <?php if (!empty($pages) && $pages > 1): ?>
            <div class="card-footer">
              <nav>
                <ul class="pagination mb-0">
                  <?php
                    $qsBase = ['status'=>$status];
                    if (!empty($buscar))     $qsBase['q']    = $buscar;
                    if (!empty($cnpjFilter)) $qsBase['cnpj'] = $cnpjFilter;
                    $qsBaseStr = http_build_query($qsBase);
                    for ($p = 1; $p <= $pages; $p++):
                      $active = ($p === ($page ?? 1)) ? ' active' : '';
                  ?>
                    <li class="page-item<?= $active ?>">
                      <a class="page-link" href="?<?= $qsBaseStr ?>&p=<?= $p ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            </div>
            <?php endif; ?>
          </div>
        </div>
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
  <script src="../../public/assets/vendor/aos/dist/aos.js"></script>
  <script src="../../public/assets/js/hope-ui.js" defer></script>
</body>
</html>
