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

// UTF-8
try {
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// ---- Sessão empresa
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

// ---- Filtros GET
$q     = trim((string)($_GET['q'] ?? ''));        // nome/sku/ean
$ativo = (string)($_GET['ativo'] ?? '');          // '', '1', '0'
$page  = max(1, (int)($_GET['page'] ?? 1));
// LIMITE FIXO: 5 por página
$limit = 5;

$filters = [
  'q'     => $q,
  'ativo' => $ativo,
  'page'  => $page,
  'limit' => $limit,
];

// ---- WHERE (usa a coluna gerada/indexada)
$where  = ["empresa_cnpj_num = :c"];
$params = [':c' => (string)$empresaCnpj];

// Placeholders distintos (ATTR_EMULATE_PREPARES = off)
if ($q !== '') {
  $like = '%' . $q . '%';
  $params[':q1'] = $like;
  $params[':q2'] = $like;
  $params[':q3'] = $like;
  $where[] = "(
    COALESCE(nome,'') LIKE CAST(:q1 AS CHAR) COLLATE utf8mb4_unicode_ci
    OR COALESCE(sku, '') LIKE CAST(:q2 AS CHAR) COLLATE utf8mb4_unicode_ci
    OR COALESCE(ean, '') LIKE CAST(:q3 AS CHAR) COLLATE utf8mb4_unicode_ci
  )";
}

if ($ativo !== '' && ($ativo === '0' || $ativo === '1')) {
  $where[] = "ativo = :ativo";
  $params[':ativo'] = (int)$ativo;
}
$whereSql = implode(' AND ', $where);

// ---- DEBUG (?debug=1)
if (($_GET['debug'] ?? '') === '1') {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $emul = null;
    try {
      $emul = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'on' : 'off';
    } catch (Throwable $e) {
    }

    $sqlCnt = "SELECT COUNT(*) FROM produtos_peca WHERE $whereSql";
    $stCnt = $pdo->prepare($sqlCnt);
    foreach ($params as $k => $v) {
      $stCnt->bindValue($k, ($k === ':ativo') ? (int)$v : (string)$v, ($k === ':ativo') ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stCnt->execute();
    $totalDebug = (int)$stCnt->fetchColumn();

    $stSeca = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE empresa_cnpj_num = :c");
    $stSeca->bindValue(':c', (string)$empresaCnpj, PDO::PARAM_STR);
    $stSeca->execute();
    $totalSeco = (int)$stSeca->fetchColumn();

    $um = $pdo->prepare("SELECT id, nome FROM produtos_peca WHERE empresa_cnpj_num = :c ORDER BY id LIMIT 1");
    $um->bindValue(':c', (string)$empresaCnpj, PDO::PARAM_STR);
    $um->execute();
    $exemplo = $um->fetch(PDO::FETCH_ASSOC) ?: [];

    header('Content-Type: text/plain; charset=utf-8');
    echo "DB atual: {$db}\n";
    echo "PDO::ATTR_EMULATE_PREPARES: {$emul}\n";
    echo "CNPJ sessão (limpo): {$empresaCnpj}\n";
    echo "WHERE usado: {$whereSql}\n";
    echo "Parâmetros: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    echo "COUNT usando WHERE da página: {$totalDebug}\n";
    echo "COUNT seco por empresa_cnpj_num: {$totalSeco}\n";
    echo "Exemplo de produto: " . json_encode($exemplo, JSON_UNESCAPED_UNICODE) . "\n";
    exit;
  } catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERRO DEBUG: " . $e->getMessage();
    exit;
  }
}

// ---- COUNT
$totalRows = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE $whereSql");
  foreach ($params as $k => $v) {
    if ($k === ':ativo') $st->bindValue($k, (int)$v, PDO::PARAM_INT);
    else                 $st->bindValue($k, (string)$v, PDO::PARAM_STR);
  }
  $st->execute();
  $totalRows = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $totalRows = 0;
}

$totalPages = max(1, (int)ceil($totalRows / $limit));
$page       = max(1, min($page, $totalPages));
$offset     = ($page - 1) * $limit;

// ---- LISTA
$rows = [];
try {
  $limitInt  = (int)$limit;
  $offsetInt = (int)$offset;

  $sql = "
    SELECT id, nome, sku, ean, marca, preco_venda, estoque_atual, ativo
    FROM produtos_peca
    WHERE $whereSql
    ORDER BY nome COLLATE utf8mb4_unicode_ci ASC, id DESC
    LIMIT $limitInt OFFSET $offsetInt
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    if ($k === ':ativo') $st->bindValue($k, (int)$v, PDO::PARAM_INT);
    else                 $st->bindValue($k, (string)$v, PDO::PARAM_STR);
  }
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $rows = [];
}

// ---- Helpers
function fmt_money($v)
{
  return number_format((float)$v, 2, ',', '.');
}
$mk = function (array $overrides = []) use ($filters) {
  $q = array_merge($filters, $overrides);
  return '?' . http_build_query($q);
};

// ---- Render helpers (para AJAX)
function render_rows_html(array $rows): string
{
  ob_start();
  if (!$rows): ?>
    <tr>
      <td colspan="8" class="text-center text-muted">Nenhum produto encontrado.</td>
    </tr>
    <?php else:
    foreach ($rows as $r): ?>
      <tr>
        <td class="fw-semibold"><?= htmlspecialchars((string)$r['nome'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '<span class="text-muted">—</span>' ?></td>
        <td><?= htmlspecialchars((string)($r['ean'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '<span class="text-muted">—</span>' ?></td>
        <td><?= htmlspecialchars((string)($r['marca'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '<span class="text-muted">—</span>' ?></td>
        <td class="text-end money">R$ <?= fmt_money($r['preco_venda']) ?></td>
        <td class="text-end"><?= number_format((float)$r['estoque_atual'], 3, ',', '.') ?></td>
        <td>
          <?php $a = (int)($r['ativo'] ?? 0); ?>
          <span class="badge bg-<?= $a ? 'success' : 'secondary' ?>"><?= $a ? 'Ativo' : 'Inativo' ?></span>
        </td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-primary" href="./produtosEditar.php?id=<?= (int)$r['id'] ?>" title="Editar">
            <i class="bi bi-pencil"></i>
          </a>
        </td>
      </tr>
  <?php endforeach;
  endif;
  return (string)ob_get_clean();
}

/**
 * Paginação numerada (bonita) com janela de 5 páginas.
 */
function render_pagination_html(int $page, int $totalPages, callable $mk, int $window = 2): string
{
  if ($totalPages <= 1) return '';
  $start = max(1, $page - $window);
  $end   = min($totalPages, $page + $window);
  // Ajusta janela se no começo/final
  if ($page <= $window) $end = min($totalPages, 1 + 2 * $window);
  if ($page > $totalPages - $window) $start = max(1, $totalPages - 2 * $window);

  ob_start(); ?>
  <ul class="pagination justify-content-end mb-0 pagination-rounded shadow-sm">
    <!-- Primeira -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <?php if ($page <= 1): ?>
        <span class="page-link" aria-label="Primeira"><i class="bi bi-chevron-double-left"></i></span>
      <?php else: ?>
        <a class="page-link" href="<?= $mk(['page' => 1]) ?>" aria-label="Primeira"><i class="bi bi-chevron-double-left"></i></a>
      <?php endif; ?>
    </li>
    <!-- Anterior -->
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <?php if ($page <= 1): ?>
        <span class="page-link" aria-label="Anterior"><i class="bi bi-chevron-left"></i></span>
      <?php else: ?>
        <a class="page-link" href="<?= $mk(['page' => max(1, $page - 1)]) ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
    </li>

    <?php if ($start > 1): ?>
      <li class="page-item"><a class="page-link" href="<?= $mk(['page' => 1]) ?>">1</a></li>
      <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <?php if ($i === $page): ?>
          <span class="page-link"><?= $i ?></span>
        <?php else: ?>
          <a class="page-link" href="<?= $mk(['page' => $i]) ?>"><?= $i ?></a>
        <?php endif; ?>
      </li>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
      <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <li class="page-item"><a class="page-link" href="<?= $mk(['page' => $totalPages]) ?>"><?= $totalPages ?></a></li>
    <?php endif; ?>

    <!-- Próxima -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <?php if ($page >= $totalPages): ?>
        <span class="page-link" aria-label="Próxima"><i class="bi bi-chevron-right"></i></span>
      <?php else: ?>
        <a class="page-link" href="<?= $mk(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Próxima"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </li>
    <!-- Última -->
    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <?php if ($page >= $totalPages): ?>
        <span class="page-link" aria-label="Última"><i class="bi bi-chevron-double-right"></i></span>
      <?php else: ?>
        <a class="page-link" href="<?= $mk(['page' => $totalPages]) ?>" aria-label="Última"><i class="bi bi-chevron-double-right"></i></a>
      <?php endif; ?>
    </li>
  </ul>
<?php
  return (string)ob_get_clean();
}

// ---- Resposta AJAX
if ((string)($_GET['ajax'] ?? '') === '1') {
  $tbody = render_rows_html($rows);
  $pagination = render_pagination_html($page, $totalPages, $mk);
  $summary = $totalRows . ' resultado' . ($totalRows === 1 ? '' : 's')
    . ' • Página ' . $page . ' de ' . $totalPages
    . ' • ' . (int)$limit . ' por página';

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'tbody'      => $tbody,
    'pagination' => $pagination,
    'summary'    => $summary,
  ]);
  exit;
}

$ok  = (int)($_GET['ok']  ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
$danfeVendaId = (int)($_GET['danfe'] ?? 0);
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Produtos</title>

  <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/logo.png">
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
    .money {
      font-variant-numeric: tabular-nums
    }

    .table thead th {
      white-space: nowrap
    }

    .search-mini {
      max-width: 420px
    }

    /* Paginação bonitinha */
    .pagination-rounded .page-link {
      border-radius: .65rem;
      border: 1px solid rgba(0, 0, 0, .075);
    }

    .pagination-rounded .page-item.active .page-link {
      box-shadow: 0 .25rem .5rem rgba(0, 0, 0, .08);
      font-weight: 600;
    }

    .pagination .page-link {
      transition: all .15s ease-in-out;
    }

    .pagination .page-link:hover {
      transform: translateY(-1px);
    }
  </style>
</head>
<!-- TOAST (1,4s) + REDIRECIONA PARA DANFE QUANDO SUCESSO -->
<?php if ($ok || $err): ?>
  <div id="toastMsg" class="toast show align-items-center border-0 position-fixed top-0 end-0 m-3 shadow-lg <?= $ok ? 'bg-success' : 'bg-danger' ?>"
    role="alert" aria-live="assertive" aria-atomic="true"
    style="z-index:2000;min-width:340px;border-radius:12px;overflow:hidden;">
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2 text-white fw-semibold ">
        <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> fs-4"></i>
        <?= htmlspecialchars($msg ?: ($ok ? 'Operação realizada com sucesso!' : 'Falha ao executar operação.'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <button id="toastClose" type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
    </div>
    <div class="progress" style="height:3px;">
      <div id="toastProgress" class="progress-bar <?= $ok ? 'bg-light' : 'bg-warning' ?>" style="width:100%"></div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const toastEl = document.getElementById("toastMsg");
      const progress = document.getElementById("toastProgress");
      if (!toastEl) return;

      const DURATION = 2400; // 1.4s
      const toast = new bootstrap.Toast(toastEl, {
        delay: DURATION,
        autohide: true
      });
      toast.show();

      // barra de tempo sincronizada
      let width = 100;
      const stepMs = 50,
        step = 100 * stepMs / DURATION;
      const itv = setInterval(() => {
        width = Math.max(0, width - step);
        if (progress) progress.style.width = width + "%";
        if (width <= 0) clearInterval(itv);
      }, stepMs);

      
    });
  </script>
<?php endif; ?>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'estoque-produtos';
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
          <form method="get" action="" class="ms-auto d-none d-lg-block" onsubmit="return false;">
            <div class="input-group search-input search-mini">
              <span class="input-group-text" id="search-input">
                <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                  <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                  <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
                </svg>
              </span>
              <input id="searchTop" type="search" name="q" class="form-control" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome, SKU ou EAN...">
              <input type="hidden" name="ativo" value="<?= htmlspecialchars($ativo, ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </form>
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
      <div class="card" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body">
          <!-- Filtros -->
          <form id="filtrosForm" method="get" action="" class="row g-2 align-items-end mb-3" onsubmit="return false;">
            <div class="col-md-6">
              <label class="form-label">Buscar</label>
              <input id="searchMain" type="text" name="q" class="form-control" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome, SKU ou EAN">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select id="filterAtivo" name="ativo" class="form-select">
                <option value="">Todos</option>
                <option value="1" <?= $ativo === '1' ? 'selected' : ''; ?>>Ativos</option>
                <option value="0" <?= $ativo === '0' ? 'selected' : ''; ?>>Inativos</option>
              </select>
            </div>
            <div class="col-auto ms-auto">
              <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="?" title="Limpar filtros">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <a class="btn btn-outline-secondary" href="./produtosNovo.php">
                  <i class="bi bi-plus-lg me-1"></i> Novo Produto
                </a>
              </div>
            </div>
          </form>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div id="summaryText" class="text-muted small">
              <?= $totalRows ?> resultado<?= $totalRows === 1 ? '' : 's' ?> • Página <?= $page ?> de <?= $totalPages ?> • <?= (int)$limit ?> por página
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>SKU</th>
                  <th>Código de Barras</th>
                  <th>Marca</th>
                  <th class="text-end">Preço</th>
                  <th class="text-end">Estoque</th>
                  <th>Ativo</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody id="produtosBody">
                <?= render_rows_html($rows) ?>
              </tbody>
            </table>
          </div>

          <nav id="paginationNav" aria-label="Paginação" class="mt-3">
            <?= render_pagination_html($page, $totalPages, $mk) ?>
          </nav>

        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">
          © <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>

  <script>
    function debounce(fn, delay) {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
      }
    }
    const searchTop = document.getElementById('searchTop');
    const searchMain = document.getElementById('searchMain');
    const selectAtivo = document.getElementById('filterAtivo');
    const summaryText = document.getElementById('summaryText');
    const tbody = document.getElementById('produtosBody');
    const pagNav = document.getElementById('paginationNav');
    const formFiltros = document.getElementById('filtrosForm');

    function syncSearch(from, to) {
      if (from && to && to.value !== from.value) to.value = from.value;
    }

    function buildUrl(extra = {}) {
      const params = new URLSearchParams(window.location.search);
      if (searchMain) params.set('q', searchMain.value || '');
      if (selectAtivo) {
        const v = selectAtivo.value;
        if (v === '' || v === null) params.delete('ativo');
        else params.set('ativo', v);
      }
      if (extra.page) params.set('page', extra.page);
      else params.set('page', '1');
      params.set('limit', '<?= (int)$limit ?>'); // sempre 5
      params.set('ajax', '1');
      return window.location.pathname + '?' + params.toString();
    }

    async function runSearch(extra = {}) {
      try {
        const url = buildUrl(extra);
        const res = await fetch(url, {
          headers: {
            'X-Requested-With': 'fetch'
          }
        });
        if (!res.ok) throw new Error('Erro ao buscar');
        const data = await res.json();
        if (typeof data.tbody === 'string') tbody.innerHTML = data.tbody;
        if (typeof data.pagination === 'string') pagNav.innerHTML = data.pagination || '';
        if (typeof data.summary === 'string') summaryText.textContent = data.summary;
        const newQs = new URL(url, window.location.origin).search.replace(/(&|\?)ajax=1(&|$)/, '$1').replace(/[?&]$/, '');
        const newUrl = window.location.pathname + (newQs ? '?' + newQs : '');
        window.history.replaceState(null, '', newUrl);
      } catch (e) {
        console.error(e);
      }
    }

    const runSearchDebounced = debounce(() => runSearch(), 250);
    if (searchTop) searchTop.addEventListener('input', e => {
      syncSearch(searchTop, searchMain);
      runSearchDebounced();
    });
    if (searchMain) searchMain.addEventListener('input', e => {
      syncSearch(searchMain, searchTop);
      runSearchDebounced();
    });
    if (selectAtivo) selectAtivo.addEventListener('change', () => runSearch());
    if (formFiltros) formFiltros.addEventListener('submit', (e) => {
      e.preventDefault();
      runSearch();
    });

    // Captura todos os links da paginação (numerados, primeira/última, etc.)
    document.addEventListener('click', (e) => {
      const a = e.target.closest('#paginationNav a.page-link');
      if (!a) return;
      e.preventDefault();
      try {
        const u = new URL(a.getAttribute('href'), window.location.origin);
        const page = u.searchParams.get('page') || '1';
        runSearch({
          page
        });
      } catch (err) {
        console.error(err);
      }
    });
  </script>
</body>

</html>