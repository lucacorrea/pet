<?php
// autoErp/public/caixa/pages/caixaSuprimento.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$usuarioCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

// CSRF exclusivo
if (empty($_SESSION['csrf_suprimento'])) {
  $_SESSION['csrf_suprimento'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_suprimento'];

// caixa aberto
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
} catch (Throwable $e) { $caixa = null; }

// feedback
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>Suprimento — Caixa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
  <style>
    .toast-wrap{position:fixed; top:16px; right:16px; z-index:2000; min-width:360px}
    .toast-card{border-radius:12px; overflow:hidden; box-shadow:0 10px 28px rgba(0,0,0,.18); animation:slideIn .35s ease-out}
    @keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
    @keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(120%);opacity:0}}
  </style>
</head>
<body>
<?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'caixa-fechar'; // agrupa no mesmo menu de Caixa
  include '../../layouts/sidebar.php';
?>
<main class="main-content">
  <div class="position-relative iq-banner">
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand"><h4 class="logo-title">AutoERP</h4></a>
        <div class="ms-auto"></div>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:140px; margin-bottom:30px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-12">
            <h1 class="mb-0">Suprimento do Caixa</h1>
            <p>Registre a entrada de dinheiro no caixa aberto para troco/ajustes.</p>
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
        <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
      </div>
    </div>
  </div>

  <div class="container-fluid content-inner mt-n3 py-0">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-7">
        <form method="post" action="../actions/caixaSuprimentoSalvar.php" class="card" autocomplete="off">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-1"></i> Novo Suprimento</h5>
            <div class="d-flex gap-2">
              <a href="./caixaSangria.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-arrow-down-circle"></i> Sangria</a>
              <a href="./caixaFechar.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-lock"></i> Fechar Caixa</a>
            </div>
          </div>
          <div class="card-body">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="empresa_cnpj" value="<?= htmlspecialchars($empresaCnpj, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="criado_por_cpf" value="<?= htmlspecialchars($usuarioCpf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="caixa_id" value="<?= (int)($caixa['id'] ?? 0) ?>">

            <div class="mb-3">
              <label class="form-label">Valor (R$)</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="number" step="0.01" min="0.01" name="valor" class="form-control text-end" placeholder="0,00" required <?= $caixa?'':'disabled' ?>>
              </div>
              <div class="form-text">Entrada de dinheiro no caixa (ex.: reforço de troco).</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Observação</label>
              <textarea name="observacao" class="form-control" rows="3" placeholder="Ex.: suprimento para troco" <?= $caixa?'':'disabled' ?>></textarea>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-success" <?= $caixa?'':'disabled' ?>>
                <i class="bi bi-check2-circle me-1"></i> Confirmar Suprimento
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($ok || $err): ?>
    <div id="toastWrap" class="toast-wrap">
      <div class="toast-card <?= $err ? 'bg-danger text-white' : 'bg-success text-white' ?>">
        <div class="p-3 d-flex align-items-center gap-3">
          <i class="bi <?= $err ? 'bi-x-circle-fill' : 'bi-check-circle-fill' ?> fs-3"></i>
          <div class="fw-semibold">
            <?= htmlspecialchars($msg ?: ($err ? 'Falha ao registrar suprimento.' : 'Suprimento registrado com sucesso!'), ENT_QUOTES, 'UTF-8') ?>
          </div>
          <button class="btn-close btn-close-white ms-auto" onclick="closeToast()" aria-label="Fechar"></button>
        </div>
        <div class="progress" style="height:4px;">
          <div id="toastBar" class="progress-bar bg-light" style="width:100%; transition: width 5s linear;"></div>
        </div>
      </div>
    </div>
    <script>
      function closeToast(){const w=document.getElementById('toastWrap');if(!w)return;w.style.animation='slideOut .35s ease-in forwards';setTimeout(()=>w.remove(),350);}
      (function(){const b=document.getElementById('toastBar');setTimeout(()=>b&&(b.style.width='0%'),50);setTimeout(closeToast,5000);})();
    </script>
  <?php endif; ?>
</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/core/external.min.js"></script>
<script src="../../assets/vendor/aos/dist/aos.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>
</body>
</html>
