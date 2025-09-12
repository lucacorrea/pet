<?php
// autoErp/public/estoque/pages/fornecedores.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
// Permissões atuais: super_admin, dono, administrativo
guard_empresa_user(['super_admin', 'dono', 'administrativo']);

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Conexão indisponível.');
}

// CSRF para ações (ex.: excluir)
if (empty($_SESSION['csrf_fornecedores'])) {
  $_SESSION['csrf_fornecedores'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_fornecedores'];

// Flash
$ok  = (int)($_GET['ok']  ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// Empresa da sessão
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

// Carrega fornecedores (sem pesquisa/filtros)
$rows = [];
if (preg_match('/^\d{14}$/', $empresaCnpj)) {
  try {
    $st = $pdo->prepare("
      SELECT id, nome, cnpj_cpf, email, telefone, cidade, estado, ativo
        FROM fornecedores_peca
       WHERE empresa_cnpj = :c
       ORDER BY nome ASC
       LIMIT 500
    ");
    $st->execute([':c' => $empresaCnpj]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $err = 1;
    $msg = 'Erro ao carregar fornecedores.';
  }
} else {
  $err = 1;
  $msg = 'Empresa não vinculada ao usuário. Faça login novamente ou cadastre os dados em Configurações » Dados da Empresa.';
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Fornecedores</title>

  <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/icon.png">
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
    $menuAtivo = 'estoque-fornecedores'; // ID do menu atual
    include '../../layouts/sidebar.php';
    ?>


  <main class="main-content">
    <div class="position-relative iq-banner">
      <!-- NAV -->
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">Mundo Pets</h4>
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
              <h1 class="mb-0">Lista de Fornecedores</h1>
              <p>Gerencie seus fornecedores cadastrados.</p>

              <?php if ($ok || $err || $msg): ?>
                <div class="mt-2 alert alert-<?= $err ? 'danger' : 'success' ?> py-2 mb-0">
                  <?= htmlspecialchars($msg ?: ($ok ? 'Operação realizada.' : ''), ENT_QUOTES, 'UTF-8') ?>
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
      <div class="card" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body">
          <div class="mb-3 text-end">
            <a class="btn btn-outline-secondary" href="./fornecedoresNovo.php">
              <i class="bi bi-plus-lg me-1"></i> Novo Fornecedor
            </a>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>CNPJ/CPF</th>
                  <th>E-mail</th>
                  <th>Telefone</th>
                  <th>Cidade/UF</th>
                  <th>Ativo</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Nenhum fornecedor cadastrado.</td>
                  </tr>
                  <?php else: foreach ($rows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)($r['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['cnpj_cpf'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)(($r['cidade'] ?? '-') . '/' . ($r['estado'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <?php $a = (int)($r['ativo'] ?? 0); ?>
                        <span class="badge bg-<?= $a ? 'success' : 'secondary' ?>"><?= $a ? 'Ativo' : 'Inativo' ?></span>
                      </td>
                      <td class="text-end text-nowrap">
                        <form method="post" action="../actions/fornecedoresExcluir.php" class="d-inline"
                          onsubmit="return confirm('Excluir este fornecedor? Esta ação não pode ser desfeita.');">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                            <i class="bi bi-trash"></i>
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

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> AutoERP</div>
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