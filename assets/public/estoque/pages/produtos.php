<?php
// autoErp/public/estoque/pages/produtos.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']);

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');
if (empty($_SESSION['csrf_produtos'])) {
    $_SESSION['csrf_produtos'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_produtos'];

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Controller
require_once __DIR__ . '/../controllers/produtosController.php';
$vm = produtos_list_viewmodel($pdo);

// Garantia de arrays
$vm['rows'] = is_array($vm['rows'] ?? null) ? $vm['rows'] : [];
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mundo Pets — Produtos</title>

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

    <style>
        /* Dropdown de sugestões abaixo do input */
        .suggest-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-top: 0;
            max-height: 240px;
            overflow-y: auto;
            display: none
        }

        .suggest-item {
            padding: .5rem .75rem;
            cursor: pointer
        }

        .suggest-item:hover {
            background: #f3f4f6
        }

        .search-wrapper {
            position: relative;
            width: 100%
        }
    </style>

</head>

<body>
    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'estoque-produtos';
    include '../../layouts/sidebar.php';
    ?>

    <main class="main-content">
        <!-- helper para o JS ler o CSRF -->
        <div id="produtos-config" data-csrf="<?= htmlspecialchars($vm['csrf'], ENT_QUOTES, 'UTF-8') ?>" hidden></div>

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
                        <input id="busca" type="search" class="form-control" placeholder="Pesquisar por nome, SKU ou EAN...">
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-12">
                            <h1 class="mb-0">Lista de Produtos</h1>
                            <p>Pesquise, filtre e gerencie seus produtos.</p>
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
                    <!-- Filtros (mudou? já recarrega) -->
                    <div class="row g-2 mb-3">
                        <div class="col-lg-3">
                            <select id="select-setor" class="form-select">
                                <option value="">Setor: Todos</option>
                                <option value="petshop" <?= ($vm['setor'] ?? '') === 'petshop' ? 'selected' : ''; ?>>Pets Shop</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <select id="select-ativo" class="form-select">
                                <option value="">Status: Todos</option>
                                <option value="1" <?= ($vm['ativo'] ?? '') === '1' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="0" <?= ($vm['ativo'] ?? '') === '0' ? 'selected' : ''; ?>>Inativos</option>
                            </select>
                        </div>
                        <div class="col-lg-6 text-end">
                            <a class="btn btn-outline-secondary" href="./produtosNovo.php"><i class="bi bi-plus-lg me-1"></i> Novo Produto</a>
                        </div>
                    </div>

                    <!-- Paginador (topo) -->
                    <div id="produtos-pager-top" class="d-flex flex-wrap justify-content-between align-items-center mb-2"></div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Setor</th>
                                    <th>SKU</th>
                                    <th>Código de Barras</th>
                                    <th>Marca</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Estoque</th>
                                    <th>Ativo</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="produtos-tbody">
                                <!-- renderizado via JS -->
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Carregando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginador (rodapé) -->
                    <div id="produtos-pager-bottom" class="d-flex flex-wrap justify-content-between align-items-center mt-2"></div>

                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-body d-flex justify-content-between align-items-center">
                <div class="left-panel">
                    © <script>
                        document.write(new Date().getFullYear())
                    </script>
                    <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
            </div>
        </footer>
    </main>

    <script src="../../assets/js/core/libs.min.js"></script>
    <script src="../../assets/js/core/external.min.js"></script>
    <script src="../../assets/vendor/aos/dist/aos.js"></script>
    <script src="../../assets/js/hope-ui.js" defer></script>

   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- JS da página: paginação em client-side (8 por página) -->
   <!-- Gráfico de Vendas: loader + render com fallback -->
<script>
  // Dados já vindo do PHP:
  window.DASH_LABELS = <?= json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE) ?>;
  window.DASH_SERIES = <?= json_encode(array_values($chartSeries), JSON_UNESCAPED_UNICODE) ?>;

  (function () {
    // garante largura/altura mínimas
    (function ensureCSS(){
      const styleId = 'chart-d-main-autocss';
      if (document.getElementById(styleId)) return;
      const s = document.createElement('style');
      s.id = styleId;
      s.textContent = '#d-main{min-height:360px;width:100%}';
      document.head.appendChild(s);
    })();

    const LABELS = Array.isArray(window.DASH_LABELS) ? window.DASH_LABELS : [];
    const SERIES = (Array.isArray(window.DASH_SERIES) ? window.DASH_SERIES : []).map(v => Number(v) || 0);

    function loadApex(next) {
      if (window.ApexCharts) return next();
      // 1) tenta CDN
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
      s.async = true;
      s.onload = next;
      s.onerror = function () {
        // 2) fallback local
        const s2 = document.createElement('script');
        s2.src = './assets/vendor/apexcharts/apexcharts.min.js';
        s2.async = true;
        s2.onload = next;
        s2.onerror = function () {
          const el = document.getElementById('d-main');
          if (el) el.innerHTML = '<div class="text-muted">Biblioteca de gráfico indisponível.</div>';
        };
        document.head.appendChild(s2);
      };
      document.head.appendChild(s);
    }

    function isDark() {
      return document.documentElement.classList.contains('dark');
    }

    function renderChart() {
      const el = document.getElementById('d-main');
      if (!el) return;

      function hasSize(node) {
        const r = node.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
      }

      if (!LABELS.length) {
        // Ainda renderiza, mas com um único ponto zero
        LABELS.push('');
        SERIES.push(0);
      }

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
        yaxis: {
          min: 0,
          labels: {
            formatter: v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 })
          }
        },
        colors: ['#3b82f6'],
        stroke: { curve: 'smooth', width: 3, colors: ['#2563eb'] },
        markers: { size: 4, strokeWidth: 2, strokeColors: isDark() ? '#0f172a' : '#ffffff' },
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        grid: { borderColor: 'rgba(0,0,0,.08)' },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] } },
        tooltip: {
          y: {
            formatter: v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          }
        },
        noData: { text: 'Sem dados' }
      };

      let chart = null;

      function init() {
        if (chart) return;
        if (!hasSize(el)) return;
        chart = new ApexCharts(el, options);
        chart.render();
        // Força ajuste após primeiro paint
        setTimeout(() => window.dispatchEvent(new Event('resize')), 0);
      }

      // Render quando ficar visível e tiver largura
      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { init(); io.disconnect(); } });
      }, { threshold: 0.1 });
      io.observe(el);

      // Tentativas extras (caso não dispare o IO)
      window.addEventListener('load', () => { setTimeout(init, 0); setTimeout(init, 300); });
      window.addEventListener('resize', () => { if (chart) chart.updateOptions({}); });

      // Atualiza tema live
      const mo = new MutationObserver(() => {
        if (chart) chart.updateOptions({
          theme: { mode: isDark() ? 'dark' : 'light' },
          markers: { strokeColors: isDark() ? '#0f172a' : '#ffffff' }
        }, false, true);
      });
      mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

      // Recalcula após toggle da sidebar
      document.querySelectorAll('[data-toggle="sidebar"]').forEach(btn => {
        btn.addEventListener('click', () => setTimeout(() => { chart ? chart.updateOptions({}) : init(); }, 250));
      });
    }

    loadApex(renderChart);
  })();
</script>

</body>

</html>