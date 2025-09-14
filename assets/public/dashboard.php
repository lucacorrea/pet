<?php
// autoErp/public/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth_guard.php';
ensure_logged_in(['dono', 'funcionario']);

if (session_status() === PHP_SESSION_NONE) session_start();

// Sessão
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$cpfSess  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // define $pdo
}

/* =========================
   CONTROLLER INTERNO
   ========================= */
$empresaCnpj = $cnpjSess;
$nomeUser    = $_SESSION['user_nome'] ?? 'Usuário';
$empresaNome = '—';

// Nome da empresa
if ($pdo instanceof PDO && $empresaCnpj !== '') {
  try {
    $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
    $st->execute([':c' => $empresaCnpj]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) $empresaNome = (string)$r['nome_fantasia'];
  } catch (Throwable $e) {}
}

/* Métricas (30 dias) */
$vendasQtde = 0;
$faturamento30d = 0.0;
$itensEstoque = 0;
$despesas30d = 0.0; // placeholder

if ($pdo instanceof PDO && $empresaCnpj !== '') {
  // Vendas 30 dias
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*) AS qtde, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c
        AND status = 'fechada'
        AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $st->execute([':c' => $empresaCnpj]);
    $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde'=>0,'total'=>0];
    $vendasQtde = (int)$v['qtde'];
    $faturamento30d = (float)$v['total'];
  } catch (Throwable $e) {}

  // Itens ativos
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE empresa_cnpj = :c AND ativo = 1");
    $st->execute([':c' => $empresaCnpj]);
    $itensEstoque = (int)$st->fetchColumn();
  } catch (Throwable $e) {}
}

/* Metas dos círculos (ajuste se quiser) */
$vendasMeta=350; $estoqueMeta=3210; $faturamentoMeta=7400; $despesasMeta=1250;
$__pct = fn(float $v, float $m): int => $m > 0 ? max(0, min(100, (int)round(($v/$m)*100))) : 0;
$vendasPct = $__pct((float)$vendasQtde, (float)$vendasMeta);
$estoquePct = $__pct((float)$itensEstoque, (float)$estoqueMeta);
$faturamentoPct = $__pct((float)$faturamento30d, (float)$faturamentoMeta);
$despesasPct = $__pct((float)$despesas30d, (float)$despesasMeta);

/* ========== GRÁFICO ==========
   Filtros aceitos:
   - 1w  => última semana (7 dias, diário)
   - 1m  => último mês (30 dias, diário)
   - 6m  => últimos 6 meses (mensal)
   - 12m => últimos 12 meses (mensal)
*/
$filtro = $_GET['filtro'] ?? '6m';
$chartLabels = [];
$chartSeries = [];

if ($pdo instanceof PDO && $empresaCnpj !== '') {
  if ($filtro === '1w') {
    // 7 dias (hoje - 6)
    $labels = []; $vals = [];
    for ($i=6; $i>=0; $i--) {
      $d = (new DateTime('today'))->modify("-{$i} day");
      $labels[] = $d->format('d/m');
      $vals[] = 0.0;
    }
    $st = $pdo->prepare("
      SELECT DATE(criado_em) AS dia, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(criado_em)
      ORDER BY dia
    ");
    $st->execute([':c' => $empresaCnpj]);
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $key = (new DateTime($r['dia']))->format('d/m');
      $idx = array_search($key, $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  } elseif ($filtro === '1m') {
    // 30 dias (hoje - 29)
    $labels = []; $vals = [];
    for ($i=29; $i>=0; $i--) {
      $d = (new DateTime('today'))->modify("-{$i} day");
      $labels[] = $d->format('d/m');
      $vals[] = 0.0;
    }
    $st = $pdo->prepare("
      SELECT DATE(criado_em) AS dia, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(criado_em)
      ORDER BY dia
    ");
    $st->execute([':c' => $empresaCnpj]);
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $key = (new DateTime($r['dia']))->format('d/m');
      $idx = array_search($key, $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  } elseif ($filtro === '12m') {
    // 12 meses
    $labels=[]; $vals=[];
    $now = new DateTime('first day of this month');
    for ($i=11; $i>=0; $i--) {
      $p = (clone $now)->modify("-{$i} months");
      $labels[] = $p->format('m/Y'); $vals[] = 0.0;
    }
    $st = $pdo->prepare("
      SELECT DATE_FORMAT(criado_em,'%m/%Y') AS mes, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj=:c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
      GROUP BY YEAR(criado_em), MONTH(criado_em)
      ORDER BY YEAR(criado_em), MONTH(criado_em)
    ");
    $st->execute([':c'=>$empresaCnpj]);
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $idx = array_search($r['mes'], $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  } else {
    // 6 meses (default)
    $labels=[]; $vals=[];
    $now = new DateTime('first day of this month');
    for ($i=5; $i>=0; $i--) {
      $p = (clone $now)->modify("-{$i} months");
      $labels[] = $p->format('m/Y'); $vals[] = 0.0;
    }
    $st = $pdo->prepare("
      SELECT DATE_FORMAT(criado_em,'%m/%Y') AS mes, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj=:c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      GROUP BY YEAR(criado_em), MONTH(criado_em)
      ORDER BY YEAR(criado_em), MONTH(criado_em)
    ");
    $st->execute([':c'=>$empresaCnpj]);
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $idx = array_search($r['mes'], $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  }

  $chartLabels = $labels;
  $chartSeries = $vals;
}

/* ====== Verificação cadastro empresa (mantido) ====== */
$empresaPendente=false; $msgCompletar=''; $canEditEmpresa=(($_SESSION['user_perfil'] ?? '') === 'dono');
$empresaRow=null;
if (!empty($empresaCnpj) && $pdo instanceof PDO) {
  $st = $pdo->prepare("SELECT * FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $empresaCnpj]);
  $empresaRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$camposObrigatorios=['nome_fantasia'=>'Nome Fantasia','email'=>'E-mail','telefone'=>'Telefone','endereco'=>'Endereço','cidade'=>'Cidade','estado'=>'UF','cep'=>'CEP'];
$faltando=[];
if (!$empresaRow) { $empresaPendente=true; $msgCompletar='Sua empresa ainda não está cadastrada. Complete as informações para aproveitar todos os recursos.'; }
else {
  foreach ($camposObrigatorios as $k=>$rot) if (trim((string)($empresaRow[$k] ?? ''))==='') $faltando[]=$rot;
  if ($faltando) { $empresaPendente=true; $msgCompletar='Algumas informações da empresa estão faltando: '.implode(', ',$faltando).'.'; }
}

/* Frase header */
$perfil = strtolower($_SESSION['user_perfil'] ?? 'funcionario');
$tipo   = strtolower($_SESSION['user_tipo']   ?? '');
$rotTipo=['administrativo'=>'Administrativo','caixa'=>'Caixa','estoque'=>'Estoque','lavajato'=>'Lava Jato'];
$tipoLabel = $rotTipo[$tipo] ?? 'Colaborador';
$fraseHeader = ($perfil==='dono')
  ? 'Você é o dono. Gerencie sua empresa, cadastre sua equipe e mantenha tudo em dia.'
  : "Você está logado como {$tipoLabel}. " .
    match($tipo){
      'administrativo'=>'Acompanhe o financeiro, cadastre produtos e dê suporte à operação.',
      'caixa'=>'Abra vendas rápidas, finalize pagamentos e agilize o atendimento.',
      'estoque'=>'Gerencie entradas e saídas, controle níveis e mantenha o estoque organizado.',
      'lavajato'=>'Registre lavagens, acompanhe status e mantenha o fluxo do box.',
      default=>'Bem-vindo ao sistema. Use o menu ao lado para começar.',
    };

// Rótulo do filtro atual (para UI)
$filtroLabel = match($filtro){
  '1w' => 'Última semana',
  '1m' => 'Último mês (30 dias)',
  '12m'=> 'Últimos 12 meses',
  default=>'Últimos 6 meses'
};
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Mundo Pets - Dashboard</title>
  <link rel="icon" type="image/png" sizes="512x512" href="./assets/images/dashboard/logo.png">
  <link rel="shortcut icon" href="./assets/images/favicon.ico">
  <link rel="stylesheet" href="./assets/css/core/libs.min.css">
  <link rel="stylesheet" href="./assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="./assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="./assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="./assets/css/dark.min.css">
  <link rel="stylesheet" href="./assets/css/customizer.min.css">
  <link rel="stylesheet" href="./assets/css/customizer.css">
  <link rel="stylesheet" href="./assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>#d-main{min-height:360px;}</style>
</head>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'dashboard';
  include './layouts/dashboard.php';
  ?>

  <main class="main-content">
    <?php if (!empty($empresaPendente)): ?>
      <div class="modal fade" id="modalEmpresaIncompleta" tabindex="-1" aria-labelledby="modalEmpresaIncompletaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
              <h5 class="modal-title" id="modalEmpresaIncompletaLabel">
                <i class="bi bi-exclamation-triangle me-2"></i> Completar cadastro da empresa
              </h5>
            </div>
            <div class="modal-body"><p class="mb-0"><?= htmlspecialchars($msgCompletar, ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="modal-footer text-center">
              <?php if (!empty($canEditEmpresa)): ?>
                <a href="./configuracao/pages/empresa.php" class="btn btn-primary w-100"><i class="bi bi-building me-1"></i> Ir para Dados da Empresa</a>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled title="Peça ao dono para completar o cadastro">
                  <i class="bi bi-lock me-1"></i> Somente o dono pode editar
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <script>
        document.addEventListener('DOMContentLoaded', function(){
          var el = document.getElementById('modalEmpresaIncompleta');
          if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
          new bootstrap.Modal(el, {backdrop:'static', keyboard:false}).show();
        });
      </script>
    <?php endif; ?>

    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="./dashboard.php" class="navbar-brand"><h4 class="logo-title">AutoERP</h4></a>
          <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
            <i class="icon">
              <svg width="20px" class="icon-20" viewBox="0 0 24 24"><path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z"/></svg>
            </i>
          </div>
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

      <div class="iq-navbar-header" style="height:215px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <div class="flex-wrap d-flex justify-content-between align-items-center">
                <div>
                  <div class="flex-wrap d-flex justify-content-between align-items-center">
                    <div>
                      <h1>Bem-vindo, <?= htmlspecialchars($nomeUser, ENT_QUOTES, 'UTF-8') ?>!</h1>
                      <p><?= htmlspecialchars($fraseHeader, ENT_QUOTES, 'UTF-8') ?> Empresa: <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="./assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n5 py-0">
      <div class="row">
        <div class="col-md-12 col-lg-12">
          <!-- Cards -->
          <div class="row">
            <div class="overflow-hidden d-slider1">
              <ul class="p-0 m-0 mb-2 swiper-wrapper list-inline" style="gap:6px;">
                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="700">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-01" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$vendasPct ?>" data-type="percent">
                        <svg class="card-slie-arrow icon-24" width="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5,17.59L15.59,7H9V5H19V15H17V8.41L6.41,19L5,17.59Z"/></svg>
                      </div>
                      <div class="progress-detail">
                        <p class="mb-2">Vendas</p>
                        <h4 class="counter"><?= number_format((float)$vendasQtde, 0, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="800">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-02" class="text-center circle-progress-01 circle-progress circle-progress-info"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$estoquePct ?>" data-type="percent">
                        <svg class="card-slie-arrow icon-24" width="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19,6.41L17.59,5L7,15.59V9H5V19H15V17H8.41L19,6.41Z"/></svg>
                      </div>
                      <div class="progress-detail">
                        <p class="mb-2">Itens em Estoque</p>
                        <h4 class="counter"><?= number_format((float)$itensEstoque, 0, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="900">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-03" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$faturamentoPct ?>" data-type="percent">
                        <svg class="card-slie-arrow icon-24" width="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19,6.41L17.59,5L7,15.59V9H5V19H15V17H8.41L19,6.41Z"/></svg>
                      </div>
                      <div class="progress-detail">
                        <p class="mb-2">Faturamento</p>
                        <h4 class="counter">R$ <?= number_format((float)$faturamento30d, 2, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3 px-3" data-aos="fade-up" data-aos-delay="1100">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-04" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$despesasPct ?>" data-type="percent">
                        <svg class="card-slie-arrow icon-24" width="24px" viewBox="0 0 24 24"><path fill="currentColor" d="M5,17.59L15.59,7H9V5H19V15H17V8.41L6.41,19L5,17.59Z"/></svg>
                      </div>
                      <div class="progress-detail">
                        <p class="mb-2">Despesas</p>
                        <h4 class="counter">R$ <?= number_format((float)$despesas30d, 2, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </div> <!-- /slider -->
          </div>
        </div>
      </div>

      <div class="col-md-12 col-lg-12">
        <div class="row">
          <!-- GRÁFICO -->
          <div class="col-md-12">
            <div class="card" data-aos="fade-up" data-aos-delay="800">
              <div class="flex-wrap card-header d-flex justify-content-between align-items-center">
                <div class="header-title">
                  <h4 class="card-title">Gráfico de Vendas</h4>
                  <p class="mb-0"><?= htmlspecialchars($filtroLabel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="dropdown">
                  <a href="#" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars($filtroLabel, ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?filtro=1w">Última semana</a></li>
                    <li><a class="dropdown-item" href="?filtro=1m">Último mês (30 dias)</a></li>
                    <li><a class="dropdown-item" href="?filtro=6m">Últimos 6 meses</a></li>
                    <li><a class="dropdown-item" href="?filtro=12m">Últimos 12 meses</a></li>
                  </ul>
                </div>
              </div>
              <div class="card-body">
                <div id="d-main" class="d-main"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /container -->

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">
          © <script>document.write(new Date().getFullYear())</script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <!-- LIBS -->
  <script src="./assets/js/core/libs.min.js"></script>
  <script src="./assets/js/core/external.min.js"></script>
  <script src="./assets/js/charts/widgetcharts.js"></script>
  <script src="./assets/js/charts/vectore-chart.js"></script>
  <script src="./assets/js/plugins/fslightbox.js"></script>
  <script src="./assets/js/plugins/setting.js"></script>
  <script src="./assets/js/plugins/slider-tabs.js"></script>
  <script src="./assets/js/plugins/form-wizard.js"></script>
  <script src="./assets/vendor/aos/dist/aos.js"></script>
  <script src="./assets/js/hope-ui.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

  <script>
    window.DASH_LABELS = <?= json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE) ?>;
    window.DASH_SERIES = <?= json_encode(array_values($chartSeries), JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script>
    (function() {
      const el = document.getElementById('d-main');
      if (!el) return;

      const LABELS = Array.isArray(window.DASH_LABELS) ? window.DASH_LABELS : [];
      const SERIES = (Array.isArray(window.DASH_SERIES) ? window.DASH_SERIES : []).map(Number);

      if (typeof ApexCharts === 'undefined') {
        el.innerHTML = '<div class="text-muted">Biblioteca de gráfico indisponível.</div>';
        return;
      }

      if (!LABELS.length) {
        el.innerHTML = `
          <div class="text-center text-muted py-5">
            <i class="bi bi-graph-down"></i>
            <div class="mt-2">Ainda não há vendas para o período selecionado.</div>
          </div>`;
        return;
      }

      new ApexCharts(el, {
        chart: { type: 'area', height: 360, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Faturamento', data: SERIES }],
        xaxis: { categories: LABELS, tickPlacement: 'on', labels: { rotate: 0 } },
        yaxis: { labels: { formatter: (v) => 'R$ ' + Number(v||0).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) } },
        stroke: { width: 3, curve: 'smooth' },
        markers: { size: 3 },
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        grid: { borderColor: 'rgba(0,0,0,.08)' },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0, stops: [0,90,100] } },
        tooltip: {
          y: { formatter: (v) => 'R$ ' + Number(v||0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
        }
      }).render();
    })();
  </script>
</body>
</html>
