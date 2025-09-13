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

        /* Barra da paginação */
        .pager-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .6rem .8rem;
            background: var(--bs-body-bg, #fff);
            border: 1px solid var(--bs-border-color, #e5e7eb);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .pager-left {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap
        }

        .pager-right {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap
        }

        /* Numeração mais “clicável” */
        .pagination .page-link {
            min-width: 2rem;
            text-align: center;
            border-radius: 8px !important;
        }

        .pagination .page-item.active .page-link {
            font-weight: 600;
            box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .15);
        }

        /* Seletor "por página" compacto e elegante */
        .pager-pagesize {
            display: flex;
            align-items: center;
            gap: .4rem
        }

        .pager-pagesize select {
            width: auto
        }

        /* Responsivo */
        @media (max-width:576px) {
            .pager-bar {
                padding: .5rem;
                gap: .5rem
            }

            .pagination {
                margin-top: .25rem
            }

            .pager-left .small {
                display: block
            }
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

    <!-- Dados para o JS -->
    <script>
        // Dados vindos do PHP (todos os registros já filtrados no backend atual)
        window.__PRODUTOS_FULL__ = <?= json_encode($vm['rows'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__CSRF__ = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <!-- JS da página: paginação em client-side (8 por página) -->
    <script>
        /* ==== Substituir no seu script existente ==== */

        /* contador “Mostrando X–Y de Z” */
        function renderCounter(page) {
            const start = state.total ? (page - 1) * state.pageSize + 1 : 0;
            const end = Math.min(page * state.pageSize, state.total);
            return `<div class="small text-muted">
    Mostrando <strong>${start}</strong>–<strong>${end}</strong> de <strong>${state.total}</strong> itens
  </div>`;
        }

        /* gera lista de páginas com reticências */
        function buildPageList(current, total) {
            const pages = [];
            const push = n => pages.push({
                type: 'num',
                n
            });
            const dots = () => pages.push({
                type: 'dots'
            });

            if (total <= 7) {
                for (let i = 1; i <= total; i++) push(i);
                return pages;
            }
            // sempre mostra 1,2 ... current-1,current,current+1 ... total-1,total
            const addWindow = (from, to) => {
                for (let i = from; i <= to; i++) push(i);
            };

            push(1);
            push(2);
            if (current > 4) dots();
            addWindow(Math.max(3, current - 1), Math.min(total - 2, current + 1));
            if (current < total - 3) dots();
            push(total - 1);
            push(total);

            // Remove duplicados (caso se encostem)
            const dedup = [];
            let prev = null;
            for (const p of pages) {
                const key = p.type === 'num' ? `n${p.n}` : 'dots';
                if (key !== prev) dedup.push(p);
                prev = key;
            }
            return dedup;
        }

        /* paginação bonita: primeira/anterior + números + próxima/última + seletor de tamanho */
        function renderPager(page) {
            const prevDis = page <= 1 ? 'disabled' : '';
            const nextDis = page >= state.totalPages ? 'disabled' : '';

            const nums = buildPageList(page, state.totalPages).map(p => {
                if (p.type === 'dots') return `<li class="page-item disabled"><span class="page-link">…</span></li>`;
                const active = (p.n === page) ? 'active' : '';
                return `<li class="page-item ${active}">
      <a class="page-link" href="#" data-page="${p.n}">${p.n}</a>
    </li>`;
            }).join('');

            return `
  <div class="pager-bar">
    <div class="pager-left">
      ${renderCounter(page)}
      <div class="pager-pagesize text-muted small">
        <span>Por página</span>
        <select class="form-select form-select-sm" id="page-size">
          ${[8,16,32,64].map(n=>`<option value="${n}" ${state.pageSize===n?'selected':''}>${n}</option>`).join('')}
        </select>
      </div>
    </div>

    <div class="pager-right">
      <nav aria-label="Paginação">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item ${prevDis}">
            <a class="page-link" href="#" data-nav="first" title="Primeira">««</a>
          </li>
          <li class="page-item ${prevDis}">
            <a class="page-link" href="#" data-nav="prev" title="Anterior">«</a>
          </li>
          ${nums}
          <li class="page-item ${nextDis}">
            <a class="page-link" href="#" data-nav="next" title="Próxima">»</a>
          </li>
          <li class="page-item ${nextDis}">
            <a class="page-link" href="#" data-nav="last" title="Última">»»</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>`;
        }

        /* monta paginadores (topo e rodapé) e liga eventos */
        function mountPagers(page) {
            const html = renderPager(page);
            if (pagerTop) pagerTop.innerHTML = html;
            if (pagerBottom) pagerBottom.innerHTML = html;

            [pagerTop, pagerBottom].forEach(wrap => {
                if (!wrap) return;

                // Navegação básica
                wrap.querySelector('[data-nav="first"]')?.addEventListener('click', e => {
                    e.preventDefault();
                    goTo(1, true);
                });
                wrap.querySelector('[data-nav="prev"]')?.addEventListener('click', e => {
                    e.preventDefault();
                    goTo(state.page - 1, true);
                });
                wrap.querySelector('[data-nav="next"]')?.addEventListener('click', e => {
                    e.preventDefault();
                    goTo(state.page + 1, true);
                });
                wrap.querySelector('[data-nav="last"]')?.addEventListener('click', e => {
                    e.preventDefault();
                    goTo(state.totalPages, true);
                });

                // Clicar em números
                wrap.querySelectorAll('[data-page]').forEach(a => {
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        const n = parseInt(a.getAttribute('data-page') || '1', 10);
                        goTo(n, true);
                    });
                });

                // Tamanho da página
                wrap.querySelector('#page-size')?.addEventListener('change', (ev) => {
                    state.pageSize = parseInt(ev.target.value, 10) || state.pageSize;
                    state.page = 1;
                    renderAll();
                });
            });
        }

        function goTo(p, smooth = false) {
            state.page = Math.max(1, Math.min(p, state.totalPages));
            renderRows(state.page);
            mountPagers(state.page);
            if (smooth) {
                try {
                    tbody.closest('.card-body').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                } catch {}
            }
        }

        function renderAll() {
            renderRows(state.page);
            mountPagers(state.page);
        }
    </script>

</body>

</html>