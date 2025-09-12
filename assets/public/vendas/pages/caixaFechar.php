<?php
// autoErp/public/caixa/pages/caixaFechar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

// Toast via GET
$ok  = isset($_GET['ok'])  ? (int)$_GET['ok']  : 0;
$err = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// CSRF (nome alinhado com o arquivo de POST)
if (empty($_SESSION['csrf_fechar_caixa'])) {
  $_SESSION['csrf_fechar_caixa'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_fechar_caixa'];

/** Busca caixa ABERTO mais recente */
$caixa = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal, aberto_por_cpf, aberto_em,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS aberto_fmt,
           COALESCE(saldo_inicial,0) AS saldo_inicial
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

/** === RESUMO 100% A PARTIR DE caixa_movimentos_peca === */
$resumo = [
  'dinheiro'     => 0.00, // entradas por forma
  'pix'          => 0.00,
  'debito'       => 0.00,
  'credito'      => 0.00,
  'suprimentos'  => 0.00, // entradas SEM forma_pagamento (nula/vazia)
  'sangrias'     => 0.00, // somatório de SAÍDAS
];

if ($caixa) {
  $caixaId = (int)$caixa['id'];

  try {
    // Entradas/saídas agrupadas por forma; forma vazia => suprimento
    $st = $pdo->prepare("
      SELECT
        LOWER(COALESCE(forma_pagamento,'')) AS fp,
        SUM(CASE WHEN tipo='entrada' THEN valor ELSE 0 END) AS entradas,
        SUM(CASE WHEN tipo='saida'   THEN valor ELSE 0 END) AS saidas
      FROM caixa_movimentos_peca
      WHERE empresa_cnpj = :c AND caixa_id = :id
      GROUP BY LOWER(COALESCE(forma_pagamento,''))
    ");
    $st->execute([':c' => $empresaCnpj, ':id' => $caixaId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $fp       = (string)$r['fp']; // 'dinheiro','pix','debito','credito' ou ''
      $entradas = (float)($r['entradas'] ?? 0);
      $saidas   = (float)($r['saidas']   ?? 0);

      if ($fp === '') {
        // Entradas sem forma => suprimentos
        $resumo['suprimentos'] += $entradas;
      } else {
        if (isset($resumo[$fp])) $resumo[$fp] += $entradas;
      }
      // Toda saída conta como sangria
      $resumo['sangrias'] += $saidas;
    }
  } catch (Throwable $e) { /* best-effort */
  }
}

/** >>> Regra: PIX conta como caixa físico (conforme sua necessidade) */
$contarPixComoCaixaFisico = true;

// Totais e saldo esperado (caixa físico)
$saldoInicial  = (float)($caixa['saldo_inicial'] ?? 0);
$totalRecebido = $resumo['dinheiro'] + $resumo['pix'] + $resumo['debito'] + $resumo['credito'];

$suprimentos      = $resumo['suprimentos'];  // entradas sem forma
$sangrias         = $resumo['sangrias'];     // todas as saídas
$entradasDinheiro = $resumo['dinheiro'] + ($contarPixComoCaixaFisico ? $resumo['pix'] : 0);

$saldoEsperado = $saldoInicial + $entradasDinheiro + $suprimentos - $sangrias;

function fmt($v)
{
  return number_format((float)$v, 2, ',', '.');
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <title>Fechar Caixa</title>
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
    .money {
      font-variant-numeric: tabular-nums
    }

    .stat-card {
      height: 100%
    }

    .stat-card .card-body {
      display: flex;
      flex-direction: column;
      gap: .5rem
    }

    @media (min-width:1200px) {
      .aside-sticky {
        position: sticky;
        top: 84px
      }
    }

    @keyframes slideIn {
      from {
        transform: translateX(120%);
        opacity: 0
      }

      to {
        transform: translateX(0);
        opacity: 1
      }
    }

    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1
      }

      to {
        transform: translateX(120%);
        opacity: 0
      }
    }
  </style>
</head>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'caixa-fechar';
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
          <div class="ms-auto small text-muted"></div>
        </div>
      </nav>

      <!-- TOAST 1,3s -->
      <?php if ($ok || $err): ?>
        <div id="toastMsg"
          class="position-fixed top-0 end-0 m-3 shadow-lg"
          style="z-index:2000;min-width:360px;border-radius:12px;overflow:hidden;animation:slideIn .4s ease-out;">
          <div class="<?= $ok ? 'bg-success' : 'bg-danger' ?> text-white p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> fs-3"></i>
              <div class="fw-semibold fs-6">
                <?= htmlspecialchars($msg ?: ($ok ? 'Operação realizada com sucesso!' : 'Falha na operação.'), ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white ms-3" aria-label="Fechar" id="toastCloseBtn"></button>
          </div>
          <div class="progress" style="height:4px;">
            <div id="toastProgress" class="progress-bar <?= $ok ? 'bg-light' : 'bg-warning' ?>" style="width:100%"></div>
          </div>
        </div>
        <script>
          (function() {
            const DURATION = 1300; // 1,3s
            const progress = document.getElementById('toastProgress');
            const toast = document.getElementById('toastMsg');
            const closeBtn = document.getElementById('toastCloseBtn');
            let start = performance.now();

            function tick(t) {
              const p = Math.min(1, (t - start) / DURATION);
              progress.style.width = (100 * (1 - p)) + '%';
              if (p < 1) requestAnimationFrame(tick);
              else slideOut();
            }

            function slideOut() {
              toast.style.animation = 'slideOut .35s ease-in forwards';
              setTimeout(() => toast.remove(), 380);
            }
            requestAnimationFrame(tick);
            closeBtn?.addEventListener('click', slideOut);
          })();
        </script>
      <?php endif; ?>

      <div class="iq-navbar-header" style="height:140px;margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Fechar Caixa</h1>
              <p>Gerencie o fechamento do caixa atual.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <?php if (!$caixa): ?>
        <div class="card" data-aos="fade-up" data-aos-delay="150">
          <div class="card-body">
            <div class="alert alert-warning mb-0">
              Não há caixa aberto para esta empresa.
              <a class="alert-link" href="./caixaAbrir.php">Clique aqui</a> para abrir.
            </div>
          </div>
        </div>
      <?php else: ?>

        <div class="card" data-aos="fade-up" data-aos-delay="150">
          <div class="card-body">
            <div class="row g-3">
              <!-- ESQUERDA -->
              <div class="col-12 col-lg-8">
                <div class="mb-3">
                  <h5 class="mb-0">
                    <i class="bi bi-cash-stack me-1"></i>
                    Caixa #<?= (int)$caixa['id'] ?> — <?= htmlspecialchars($caixa['tipo'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($caixa['terminal'], ENT_QUOTES, 'UTF-8') ?>
                  </h5>
                  <div class="text-muted small">
                    Aberto em <?= htmlspecialchars($caixa['aberto_fmt'], ENT_QUOTES, 'UTF-8') ?> •
                    Saldo inicial: <strong class="money">R$ <?= fmt($saldoInicial) ?></strong>
                  </div>
                </div>

                <div class="row g-3">
                  <!-- Recebido (por forma) - vindo de caixa_movimentos_peca -->
                  <div class="col-md-6">
                    <div class="card stat-card border-0 bg-soft-primary">
                      <div class="card-body">
                        <div class="text-muted small">Recebido (por forma) — <span class="fw-semibold">caixa_movimentos_peca</span></div>
                        <div class="mt-2 small">
                          <div>Dinheiro: <strong class="money">R$ <?= fmt($resumo['dinheiro']) ?></strong></div>
                          <div>PIX: <strong class="money">R$ <?= fmt($resumo['pix']) ?></strong></div>
                          <div>Débito: <strong class="money">R$ <?= fmt($resumo['debito']) ?></strong></div>
                          <div>Crédito: <strong class="money">R$ <?= fmt($resumo['credito']) ?></strong></div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                          <span class="fw-semibold">Total recebido</span>
                          <span class="fs-5 fw-bold money">R$ <?= fmt($totalRecebido) ?></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Movimentações / Saldo esperado -->
                  <div class="col-md-6">
                    <div class="card stat-card border-0 bg-soft-success">
                      <div class="card-body">
                        <div class="text-muted small">Movimentações do Caixa — <span class="fw-semibold">caixa_movimentos_peca</span></div>
                        <div class="mt-2 small">
                          <div>Entradas (suprimento): <strong class="money">R$ <?= fmt($suprimentos) ?></strong></div>
                          <div>Saídas (sangria): <strong class="money">R$ <?= fmt($sangrias) ?></strong></div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                          <span class="fw-semibold">Saldo esperado no caixa</span>
                          <span class="fw-bold money">R$ <?= fmt($saldoEsperado) ?></span>
                        </div>
                        <div class="text-muted small">
                          Saldo inicial + (Dinheiro<?= $contarPixComoCaixaFisico ? ' + PIX' : '' ?> + Suprimentos) − Sangrias
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- DIREITA: Fechamento -->
              <div class="col-12 col-lg-4">
                <form method="post" action="../actions/caixaFecharSalvar.php" class="card h-100">
                  <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lock-fill me-1"></i> Finalizar</h6>
                  </div>
                  <div class="card-body">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="caixa_id" value="<?= (int)$caixa['id'] ?>">

                    <div class="mb-3">
                      <label class="form-label">Dinheiro contado no caixa (R$)</label>
                      <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="number" step="0.01" min="0" class="form-control text-end" name="dinheiro_contado" placeholder="0,00" required>
                      </div>
                      <div class="form-text">Saldo esperado: <strong class="money">R$ <?= fmt($saldoEsperado) ?></strong></div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Observações</label>
                      <textarea class="form-control" name="observacoes" rows="4" placeholder="Anote diferenças, justificativas, etc."></textarea>
                    </div>

                    <div class="alert alert-info small">
                      Ao confirmar, o caixa será marcado como <strong>fechado</strong> e não aceitará novas vendas.
                    </div>

                    <div class="d-grid">
                      <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check2-circle me-1"></i> Fechar Caixa
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div><!-- /row -->
          </div><!-- /card-body -->
        </div><!-- /card -->

      <?php endif; ?>
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