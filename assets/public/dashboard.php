<?php
// autoErp/public/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth_guard.php';
ensure_logged_in(['dono', 'funcionario']);

if (session_status() === PHP_SESSION_NONE) session_start();

// -------------------- Conexão --------------------
$pdo = null;
$pathCon = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;

// -------------------- Sessão / Empresa --------------------
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$nomeUser = (string)($_SESSION['user_nome'] ?? 'Usuário');
$perfil   = strtolower((string)($_SESSION['user_perfil'] ?? 'funcionario'));
$tipo     = strtolower((string)($_SESSION['user_tipo'] ?? ''));

// Util para comparar CNPJ mascarado ou não
$CNPJ_MATCH_SQL = "(empresa_cnpj = :c OR REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c)";

// -------------------- Nome da empresa --------------------
$empresaNome = '—';
if ($pdo instanceof PDO && $cnpjSess) {
    try {
        $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE $CNPJ_MATCH_SQL LIMIT 1");
        $st->execute([':c' => $cnpjSess]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) $empresaNome = (string)$row['nome_fantasia'];
    } catch (Throwable $e) {}
}

// -------------------- Mensagem header --------------------
$rotTipo   = ['administrativo'=>'Administrativo','caixa'=>'Caixa','estoque'=>'Estoque','lavajato'=>'Lava Jato'];
$tipoLabel = $rotTipo[$tipo] ?? 'Colaborador';
$fraseHeader = $perfil === 'dono'
    ? 'Você é o dono. Gerencie sua empresa, cadastre sua equipe e mantenha tudo em dia.'
    : "Você está logado como {$tipoLabel}. " . match ($tipo) {
        'administrativo' => 'Acompanhe o financeiro, cadastre produtos e dê suporte à operação.',
        'caixa'          => 'Abra vendas rápidas, finalize pagamentos e agilize o atendimento.',
        'estoque'        => 'Gerencie entradas e saídas, controle níveis e mantenha o estoque organizado.',
        'lavajato'       => 'Registre lavagens, acompanhe status e mantenha o fluxo do box.',
        default          => 'Bem-vindo ao sistema. Use o menu ao lado para começar.'
    };

// -------------------- Cards (últimos 30 dias) --------------------
$vendasQtde = 0;
$faturamento30d = 0.0;
$itensEstoque = 0;
$despesas30d = 0.0;

if ($pdo instanceof PDO && $cnpjSess) {
    try {
        $st = $pdo->prepare("
            SELECT COUNT(*) qtde, COALESCE(SUM(total_liquido),0) total
            FROM vendas_peca
            WHERE $CNPJ_MATCH_SQL
              AND status='fechada'
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $st->execute([':c'=>$cnpjSess]);
        $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde'=>0,'total'=>0];
        $vendasQtde     = (int)$v['qtde'];
        $faturamento30d = (float)$v['total'];
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE $CNPJ_MATCH_SQL AND ativo=1");
        $st->execute([':c'=>$cnpjSess]);
        $itensEstoque = (int)$st->fetchColumn();
    } catch (Throwable $e) {}
}

// metas fictícias p/ progresso
$limitaPct = static function (float $v, float $m): int {
    if ($m <= 0) return 0;
    $p = (int)round(($v / $m) * 100);
    return max(0, min(100, $p));
};
$vendasPct      = $limitaPct((float)$vendasQtde, 350);
$estoquePct     = $limitaPct((float)$itensEstoque, 3210);
$faturamentoPct = $limitaPct((float)$faturamento30d, 7400.00);
$despesasPct    = $limitaPct((float)$despesas30d, 1250.00);

// -------------------- Gráfico: range e dados --------------------
$range = strtolower((string)($_GET['range'] ?? '6m')); // week | month | 6m | 12m
if (!in_array($range, ['week','month','6m','12m'], true)) $range = '6m';

$rangeLabel = [
    'week' => 'Última semana',
    'month'=> 'Últimos 30 dias',
    '6m'   => 'Últimos 6 meses',
    '12m'  => 'Últimos 12 meses'
][$range] ?? 'Últimos 6 meses';

$chartLabels = [];
$chartSeries = [];

if ($pdo instanceof PDO && $cnpjSess) {
    try {
        if ($range === 'week' || $range === 'month') {
            // --- Diária ---
            $dias  = $range === 'week' ? 7 : 30;
            $today = new DateTime('today');
            $ini   = (clone $today)->modify('-'.($dias-1).' days')->setTime(0,0,0);
            $fim   = (clone $today)->modify('+1 day')->setTime(0,0,0);

            $labels   = [];
            $mapIndex = [];
            for ($i = 0; $i < $dias; $i++) {
                $d = (clone $ini)->modify("+{$i} days");
                $labels[]                 = $d->format('d/m');      // rótulo visível
                $mapIndex[$d->format('Y-m-d')] = $i;                // chave estável
            }
            $vals = array_fill(0, $dias, 0.0);

            $st = $pdo->prepare("
                SELECT DATE_FORMAT(criado_em,'%Y-%m-%d') dia, COALESCE(SUM(total_liquido),0) total
                FROM vendas_peca
                WHERE $CNPJ_MATCH_SQL
                  AND status='fechada'
                  AND criado_em >= :ini AND criado_em < :fim
                GROUP BY DATE(criado_em)
                ORDER BY DATE(criado_em)
            ");
            $st->execute([
                ':c'   => $cnpjSess,
                ':ini' => $ini->format('Y-m-d H:i:s'),
                ':fim' => $fim->format('Y-m-d H:i:s'),
            ]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = (string)$r['dia']; // 'YYYY-MM-DD'
                if (isset($mapIndex[$k])) $vals[$mapIndex[$k]] = (float)$r['total'];
            }
            $chartLabels = $labels;
            $chartSeries = $vals;

        } else {
            // --- Mensal (6m / 12m) ---
            $meses = $range === '12m' ? 12 : 6;
            $first = new DateTime('first day of this month');
            $ini   = (clone $first)->modify('-'.($meses-1).' months')->setTime(0,0,0);
            $fim   = (clone $first)->modify('+1 month')->setTime(0,0,0);

            $labels   = [];
            $mapIndex = [];
            for ($i = $meses - 1; $i >= 0; $i--) {
                $p = (clone $first)->modify("-{$i} months");
                $labels[] = $p->format('m/Y'); // rótulo visível
                $mapIndex[sprintf('%04d-%02d', (int)$p->format('Y'), (int)$p->format('m'))] = count($labels) - 1; // chave Y-m
            }
            $vals = array_fill(0, $meses, 0.0);

            $st = $pdo->prepare("
                SELECT YEAR(criado_em) AS y, MONTH(criado_em) AS m, COALESCE(SUM(total_liquido),0) AS total
                FROM vendas_peca
                WHERE $CNPJ_MATCH_SQL
                  AND status='fechada'
                  AND criado_em >= :ini AND criado_em < :fim
                GROUP BY YEAR(criado_em), MONTH(criado_em)
                ORDER BY YEAR(criado_em), MONTH(criado_em)
            ");
            $st->execute([
                ':c'   => $cnpjSess,
                ':ini' => $ini->format('Y-m-d H:i:s'),
                ':fim' => $fim->format('Y-m-d H:i:s'),
            ]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $key = sprintf('%04d-%02d', (int)$r['y'], (int)$r['m']); // 'YYYY-MM'
                if (isset($mapIndex[$key])) $vals[$mapIndex[$key]] = (float)$r['total'];
            }
            $chartLabels = $labels;
            $chartSeries = $vals;
        }
    } catch (Throwable $e) {}
}

// -------------------- Verificação de cadastro incompleto --------------------
$empresaPendente = false;
$msgCompletar    = '';
$canEditEmpresa  = (($_SESSION['user_perfil'] ?? '') === 'dono');

if ($pdo instanceof PDO && $cnpjSess) {
    try {
        $st = $pdo->prepare("SELECT * FROM empresas_peca WHERE $CNPJ_MATCH_SQL LIMIT 1");
        $st->execute([':c' => $cnpjSess]);
        $empresaRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $obrig = ['nome_fantasia','email','telefone','endereco','cidade','estado','cep'];
        if (!$empresaRow) {
            $empresaPendente = true;
            $msgCompletar = 'Sua empresa ainda não está cadastrada. Complete as informações para aproveitar todos os recursos.';
        } else {
            $falt = [];
            foreach ($obrig as $k) if (trim((string)($empresaRow[$k] ?? '')) === '') $falt[] = $k;
            if ($falt) {
                $empresaPendente = true;
                $msgCompletar = 'Algumas informações da empresa estão faltando: '.implode(', ', $falt).'.';
            }
        }
    } catch (Throwable $e) {}
}
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
  <style>
    #d-main{ min-height:360px; }
    .dropdown-item.active,.dropdown-item:active{ background:#e9ecef; color:#111; }
  </style>
</head>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'dashboard'; // item ativo do sidebar
  include './layouts/dashboard.php';
  ?>

  <main class="main-content">
    <?php if ($empresaPendente): ?>
      <div class="modal fade" id="modalEmpresaIncompleta" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
              <h5 class="modal-title">
                <i class="bi bi-exclamation-triangle me-2"></i>Completar cadastro da empresa
              </h5>
            </div>
            <div class="modal-body">
              <p class="mb-0"><?= htmlspecialchars($msgCompletar, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer text-center">
              <?php if ($canEditEmpresa): ?>
                <a href="./configuracao/pages/empresa.php" class="btn btn-primary w-100">
                  <i class="bi bi-building me-1"></i> Ir para Dados da Empresa
                </a>
              <?php else: ?>
                <button class="btn btn-outline-secondary" disabled>
                  <i class="bi bi-lock me-1"></i> Somente o dono pode editar
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const el = document.getElementById('modalEmpresaIncompleta');
          if (el && window.bootstrap?.Modal) {
            new bootstrap.Modal(el, {backdrop:'static', keyboard:false}).show();
          }
        });
      </script>
    <?php endif; ?>

    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="./dashboard.php" class="navbar-brand"><h4 class="logo-title">Mundo Pets</h4></a>
          <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
            <i class="icon">
              <svg width="20" class="icon-20" viewBox="0 0 24 24">
                <path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z"/>
              </svg>
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
                  <h1>Bem-vindo, <?= htmlspecialchars($nomeUser, ENT_QUOTES, 'UTF-8') ?>!</h1>
                  <p><?= htmlspecialchars($fraseHeader, ENT_QUOTES, 'UTF-8') ?> Empresa:
                    <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong>
                  </p>
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
          <!-- CARDS -->
          <div class="row">
            <div class="overflow-hidden d-slider1">
              <ul class="p-0 m-0 mb-2 swiper-wrapper list-inline" style="gap:6px;">
                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="700">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-01" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$vendasPct ?>" data-type="percent"></div>
                      <div class="progress-detail">
                        <p class="mb-2">Vendas</p>
                        <h4 class="counter"><?= number_format($vendasQtde, 0, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="800">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-02" class="text-center circle-progress-01 circle-progress circle-progress-info"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$estoquePct ?>" data-type="percent"></div>
                      <div class="progress-detail">
                        <p class="mb-2">Itens em Estoque</p>
                        <h4 class="counter"><?= number_format($itensEstoque, 0, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="900">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-03" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$faturamentoPct ?>" data-type="percent"></div>
                      <div class="progress-detail">
                        <p class="mb-2">Faturamento</p>
                        <h4 class="counter">R$ <?= number_format($faturamento30d, 2, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>

                <li class="swiper-slide card card-slide col-lg-3 px-3" data-aos="fade-up" data-aos-delay="1100">
                  <div class="card-body">
                    <div class="progress-widget">
                      <div id="circle-progress-04" class="text-center circle-progress-01 circle-progress circle-progress-primary"
                           data-min-value="0" data-max-value="100" data-value="<?= (int)$despesasPct ?>" data-type="percent"></div>
                      <div class="progress-detail">
                        <p class="mb-2">Despesas</p>
                        <h4 class="counter">R$ <?= number_format($despesas30d, 2, ',', '.') ?></h4>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </div><!-- /slider -->
          </div>
        </div>
      </div>

      <!-- GRÁFICO -->
      <div class="col-md-12 col-lg-12">
        <div class="row">
          <div class="col-md-12">
            <div class="card" data-aos="fade-up" data-aos-delay="800">
              <div class="flex-wrap card-header d-flex justify-content-between align-items-center">
                <div class="header-title">
                  <h4 class="card-title">Gráfico de Vendas</h4>
                  <p class="mb-0"><?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="dropdown">
                  <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?= $range==='week' ? 'active':'' ?>"  href="?range=week">Última semana</a></li>
                    <li><a class="dropdown-item <?= $range==='month'? 'active':'' ?>" href="?range=month">Últimos 30 dias</a></li>
                    <li><a class="dropdown-item <?= $range==='6m'  ? 'active':'' ?>" href="?range=6m">Últimos 6 meses</a></li>
                    <li><a class="dropdown-item <?= $range==='12m' ? 'active':'' ?>" href="?range=12m">Últimos 12 meses</a></li>
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
        <div class="left-panel">© <script>document.write(new Date().getFullYear())</script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
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

  <!-- ApexCharts -->
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

  <!-- Dados p/ gráfico -->
  <script>
    window.DASH_LABELS = <?= json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE) ?>;
    window.DASH_SERIES = <?= json_encode(array_values($chartSeries), JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <!-- Render robusto do gráfico -->
  <script>
  (function() {
    const el = document.getElementById('d-main');
    if (!el) return;

    const LABELS = Array.isArray(window.DASH_LABELS) ? window.DASH_LABELS : [];
    const SERIES = (Array.isArray(window.DASH_SERIES) ? window.DASH_SERIES : []).map(v => Number(v) || 0);

    if (typeof ApexCharts === 'undefined') {
      el.innerHTML = '<div class="text-muted">Biblioteca de gráfico indisponível.</div>';
      return;
    }
    if (!LABELS.length) {
      el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-graph-down"></i><div class="mt-2">Sem dados para o período.</div></div>';
      return;
    }

    let chart = null;
    const isDark = () => document.documentElement.classList.contains('dark');
    const options = {
      theme: { mode: isDark() ? 'dark' : 'light' },
      chart: {
        type: 'area',
        height: 360,
        toolbar: { show: false },
        fontFamily: 'inherit',
        redrawOnParentResize: true,
        redrawOnWindowResize: true,
        animations: { enabled: true }
      },
      series: [{ name: 'Faturamento', data: SERIES }],
      xaxis: { categories: LABELS, tickPlacement: 'on', labels: { rotate: 0 } },
      yaxis: { labels: { formatter: v => 'R$ ' + Number(v||0).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) } },
      // Cores explícitas garantem visibilidade no tema claro/escuro
      colors: ['#3b82f6'],
      stroke: { curve: 'smooth', width: 3, colors: ['#2563eb'] },
      markers: { size: 4, strokeWidth: 2, strokeColors: isDark() ? '#0f172a' : '#ffffff' },
      dataLabels: { enabled: false },
      legend: { position: 'top' },
      grid: { borderColor: 'rgba(0,0,0,.08)' },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0,90,100] } },
      tooltip: {
        y: { formatter: v => 'R$ ' + Number(v||0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
      },
      noData: { text: 'Sem dados' }
    };

    function hasSize(node){ const r=node.getBoundingClientRect(); return r.width>0 && r.height>0; }
    function renderWhenReady(){
      if (chart) return;
      if (!hasSize(el)) return;  // espera card ficar visível
      chart = new ApexCharts(el, options);
      chart.render();
      setTimeout(() => window.dispatchEvent(new Event('resize')), 0);
    }

    const io = new IntersectionObserver((entries)=>{
      entries.forEach(e => { if (e.isIntersecting) { renderWhenReady(); io.disconnect(); }});
    }, {threshold: 0.1});
    io.observe(el);

    window.addEventListener('load', () => {
      setTimeout(renderWhenReady, 0);
      setTimeout(renderWhenReady, 300);
    });

    window.addEventListener('resize', () => { if (chart) chart.updateOptions({}); });

    const mo = new MutationObserver(() => {
      if (chart) chart.updateOptions({
        theme: { mode: isDark() ? 'dark' : 'light' },
        markers: { strokeColors: isDark() ? '#0f172a' : '#ffffff' }
      }, false, true);
    });
    mo.observe(document.documentElement, { attributes:true, attributeFilter:['class'] });

    document.querySelectorAll('[data-toggle="sidebar"]').forEach(btn=>{
      btn.addEventListener('click', ()=> setTimeout(()=> { chart ? chart.updateOptions({}) : renderWhenReady(); }, 250));
    });
  })();
  </script>
</body>
</html>
