<?php
// autoErp/public/caixa/pages/caixaFechar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

// Caixa aberto
$caixa = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal, aberto_por_cpf,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS aberto_fmt,
           saldo_inicial
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY id DESC LIMIT 1
  ");
  $st->execute([':c' => $empresaCnpj]);
  $caixa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $caixa = null; }

if (empty($_SESSION['csrf_caixa_fechar'])) {
  $_SESSION['csrf_caixa_fechar'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_caixa_fechar'];

$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>Mundo Pets — Fechar Caixa</title>
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="shortcut icon" href="../../assets/images/dashboard/logo.png" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
  <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand"><h4 class="logo-title">Mundo Pets</h4></a>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Fechar Caixa</h1>
              <p>Encerre o caixa aberto e registre o saldo final.</p>
              <?php if ($ok || $err || $msg): ?>
                <div class="mt-2 alert alert-<?= $err ? 'danger':'success' ?>">
                  <?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="card">
        <div class="card-body">
          <?php if (!$caixa): ?>
            <div class="alert alert-warning">Nenhum caixa aberto no momento.</div>
          <?php else: ?>
            <h5>Caixa #<?= (int)$caixa['id'] ?> — <?= htmlspecialchars($caixa['terminal']) ?></h5>
            <p>Aberto em <?= $caixa['aberto_fmt'] ?> com saldo inicial R$ <?= number_format((float)$caixa['saldo_inicial'],2,',','.') ?></p>

            <form method="post" action="../actions/caixaFecharSalvar.php" class="row g-3">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
              <input type="hidden" name="caixa_id" value="<?= (int)$caixa['id'] ?>">

              <div class="col-md-6">
                <label class="form-label">Saldo Final</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input type="number" step="0.01" name="saldo_final" class="form-control" required>
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Observações</label>
                <input type="text" class="form-control" name="observacoes" maxlength="180">
              </div>

              <div class="col-12 d-flex justify-content-end gap-2">
                <a href="../../dashboard.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-danger">
                  <i class="bi bi-door-closed"></i> Fechar Caixa
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
