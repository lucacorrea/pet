<?php
// autoErp/public/vendas/pages/caixaSuprimento.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Empresa / usuário
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$usuarioCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// CSRF
if (empty($_SESSION['csrf_caixa_suprimento'])) {
  $_SESSION['csrf_caixa_suprimento'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_caixa_suprimento'];

// Caixa aberto (mais recente)
$caixa = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS aberto_fmt
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY aberto_em DESC
    LIMIT 1
  ");
  $st->execute([':c' => $empresaCnpj]);
  $caixa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $caixa = null;
}

// flash (opcional, via querystring)
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Suprimento de Caixa</title>
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
  <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'caixa-suprimento';
    include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="#" class="navbar-brand"><h4 class="logo-title">Mundo Pets</h4></a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none"></svg>
            </span>
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <h1 class="mb-0">Suprimento de Caixa</h1>
              <p>Informe o valor de entrada em dinheiro no caixa (troco, reforço, etc.).</p>
              <?php if (!$caixa): ?>
                <div class="alert alert-warning mt-2 mb-0">
                  Não há caixa aberto para esta empresa.
                  <a class="alert-link" href="./caixaAbrir.php">Clique aqui</a> para abrir.
                </div>
              <?php else: ?>
                <div class="text-muted small mt-1">
                  <i class="bi bi-cash-coin me-1"></i>
                  Caixa #<?= (int)$caixa['id'] ?> — <?= htmlspecialchars($caixa['tipo'],ENT_QUOTES,'UTF-8') ?> — <?= htmlspecialchars($caixa['terminal'],ENT_QUOTES,'UTF-8') ?> —
                  aberto em <?= htmlspecialchars($caixa['aberto_fmt'],ENT_QUOTES,'UTF-8') ?>
                </div>
              <?php endif; ?>
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
              <h4 class="card-title mb-0">Dados do Suprimento</h4>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/caixaMovSalvar.php" id="form-suprimento" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tipo" value="suprimento">
                <input type="hidden" name="empresa_cnpj" value="<?= htmlspecialchars($empresaCnpj, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="criado_por_cpf" value="<?= htmlspecialchars($usuarioCpf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="caixa_id" value="<?= (int)($caixa['id'] ?? 0) ?>">

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Valor (R$)</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input type="number" name="valor" step="0.01" min="0.01" class="form-control text-end" required <?= $caixa ? '' : 'disabled' ?>>
                    </div>
                  </div>
                  <div class="col-md-8">
                    <label class="form-label">Observação (opcional)</label>
                    <input type="text" name="observacao" class="form-control" maxlength="240" placeholder="Ex.: Suprimento para troco" <?= $caixa ? '' : 'disabled' ?>>
                  </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-success" <?= $caixa ? '' : 'disabled' ?>>
                    <i class="bi bi-arrow-up-circle me-1"></i> Confirmar Suprimento
                  </button>
                  <a href="./caixaFechar.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                  </a>
                </div>
              </form>

              <?php if ($ok || $err): ?>
                <div class="mt-3">
                  <div class="alert alert-<?= $err ? 'danger' : 'success' ?> py-2 mb-0">
                    <?= htmlspecialchars($msg ?: ($err ? 'Falha ao registrar suprimento.' : 'Suprimento registrado com sucesso.'), ENT_QUOTES, 'UTF-8') ?>
                  </div>
                </div>
              <?php endif; ?>

            </div>
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
