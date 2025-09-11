<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['super_admin','dono','administrativo','caixa']);

$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
require_once __DIR__ . '/../controllers/lavadoresController.php';

$empresaNome = empresa_nome_logada($pdo) ?: 'Sua empresa';
$vm = lavadores_list_viewmodel($pdo);
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavadores</title>
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
  $menuAtivo = 'lavajato-lavadores'; // sem espaço
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

      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Lista de Lavadores</h1>
              <p>Gerencie os colaboradores do Lava Jato (sem acesso ao sistema).</p>

              
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
          <form class="row g-2 mb-3" method="get">
            <div class="col-md-3">
              <select class="form-select" name="ativo" onchange="this.form.submit()">
                <option value="">Status: Todos</option>
                <option value="1" <?= ($vm['ativo']==='1')?'selected':''; ?>>Ativos</option>
                <option value="0" <?= ($vm['ativo']==='0')?'selected':''; ?>>Inativos</option>
              </select>
            </div>
            <div class="col-md-9 text-end">
              <a class="btn btn-outline-secondary" href="./lavadoresNovo.php">
                <i class="bi bi-person-plus me-1"></i> Novo Lavador
              </a>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>CPF</th>
                  <th>Telefone</th>
                  <th>E-mail</th>
                  <th>Status</th>
                  <th>Criado em</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$vm['rows']): ?>
                  <tr><td colspan="7" class="text-center text-muted">Nenhum lavador encontrado.</td></tr>
                <?php else: foreach ($vm['rows'] as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['cpf'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['telefone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?php $a = (int)($r['ativo'] ?? 0); ?>
                      <span class="badge bg-<?= $a ? 'success':'secondary' ?>"><?= $a ? 'Ativo':'Inativo' ?></span>
                    </td>
                    <td><?= htmlspecialchars($r['criado'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end text-nowrap">
                      <form method="post" action="../actions/lavadoresExcluir.php" class="d-inline"
                            onsubmit="return confirm('Excluir este lavador?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($vm['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
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
