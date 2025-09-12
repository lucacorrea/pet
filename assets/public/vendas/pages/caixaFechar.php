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

// CSRF
if (empty($_SESSION['csrf_caixa_fechar'])) {
  $_SESSION['csrf_caixa_fechar'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_caixa_fechar'];

/** Busca caixa ABERTO mais recente */
$caixa = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal, aberto_em,
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

/** Resumo financeiro (best-effort) */
$resumo = [
  'suprimentos' => 0.00,
  'sangrias'    => 0.00,
  'dinheiro'    => 0.00,
  'pix'         => 0.00,
  'debito'      => 0.00,
  'credito'     => 0.00,
];

if ($caixa) {
  $caixaId = (int)$caixa['id'];

  // Movimentações (suprimento / sangria)
  try {
    $st = $pdo->prepare("
      SELECT 
        SUM(CASE WHEN tipo IN ('suprimento','SUPRIMENTO') THEN valor ELSE 0 END) AS suprimentos,
        SUM(CASE WHEN tipo IN ('sangria','SANGRIA')       THEN valor ELSE 0 END) AS sangrias
      FROM caixa_mov_peca
      WHERE empresa_cnpj = :c AND caixa_id = :id
    ");
    $st->execute([':c' => $empresaCnpj, ':id' => $caixaId]);
    $m = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumo['suprimentos'] = (float)($m['suprimentos'] ?? 0);
    $resumo['sangrias']    = (float)($m['sangrias'] ?? 0);
  } catch (Throwable $e) {}

  // Recebimentos por forma
  try {
    $st = $pdo->prepare("
      SELECT LOWER(vp.forma_pagamento) AS fp, SUM(vp.valor) AS total
      FROM vendas_pagamentos_peca vp
      INNER JOIN vendas_peca v ON v.id = vp.venda_id
      WHERE v.empresa_cnpj = :c
        AND v.created_at >= :ini
        AND v.status IN ('concluida','finalizada','paga')
      GROUP BY LOWER(vp.forma_pagamento)
    ");
    $st->execute([':c' => $empresaCnpj, ':ini' => $caixa['aberto_em']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $fp = (string)($r['fp'] ?? '');
      if ($fp === 'crédito') $fp = 'credito';
      if (isset($resumo[$fp])) $resumo[$fp] += (float)$r['total'];
    }
  } catch (Throwable $e) {}
}

$saldoInicial  = (float)($caixa['saldo_inicial'] ?? 0);
$totalRecebido = $resumo['dinheiro'] + $resumo['pix'] + $resumo['debito'] + $resumo['credito'];
$entradasCx    = $resumo['suprimentos'] + $resumo['dinheiro']; // entra no caixa físico
$saidasCx      = $resumo['sangrias'];                          // sai do caixa físico
$saldoEsperado = $saldoInicial + $entradasCx - $saidasCx;

function fmt($v) { return number_format((float)$v, 2, ',', '.'); }
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
    .money{font-variant-numeric:tabular-nums}
    .stat-card{height:100%}
    .stat-card .card-body{display:flex;flex-direction:column;gap:.5rem}
    @media (min-width:1200px){.aside-sticky{position:sticky;top:84px}}
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
      <!-- NAV padrão -->
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
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

      <!-- HEADER azul padrão -->
      <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
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
              <a class="alert-link" href="../../caixa/pages/caixaAbrir.php">Clique aqui</a> para abrir.
            </div>
          </div>
        </div>
      <?php else: ?>

      <!-- *** CARD ÚNICO (mesma largura visual da tabela do exemplo) *** -->
      <div class="card" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body">
          <div class="row g-3">
            <!-- COL ESQUERDA: título e 2 cards de resumo lado a lado -->
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
                <!-- Recebido -->
                <div class="col-md-6">
                  <div class="card stat-card border-0 bg-soft-primary">
                    <div class="card-body">
                      <div class="text-muted small">Recebido (por forma)</div>
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

                <!-- Movimentações -->
                <div class="col-md-6">
                  <div class="card stat-card border-0 bg-soft-success">
                    <div class="card-body">
                      <div class="text-muted small">Movimentações de Caixa</div>
                      <div class="mt-2 small">
                        <div>Suprimentos: <strong class="money">R$ <?= fmt($resumo['suprimentos']) ?></strong></div>
                        <div>Sangrias: <strong class="money">R$ <?= fmt($resumo['sangrias']) ?></strong></div>
                      </div>
                      <hr class="my-2">
                      <div class="d-flex justify-content-between">
                        <span class="fw-semibold">Saldo esperado</span>
                        <span class="fw-bold money">R$ <?= fmt($saldoEsperado) ?></span>
                      </div>
                      <div class="text-muted small">Saldo inicial + (Dinheiro + Suprimentos) − Sangrias</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- COL DIREITA: card de finalizar -->
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
      </div><!-- /card (container padrão) -->

      <?php endif; ?>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>document.write(new Date().getFullYear())</script> AutoERP</div>
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
