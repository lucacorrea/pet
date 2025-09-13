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

require_once __DIR__ . '/../../../lib/util.php'; // ajuste caminho conforme a pasta
$empresaNome = empresa_nome_logada($pdo); // nome da empresa logada
// Controller
require_once __DIR__ . '/../controllers/produtosController.php';
$vm = produtos_list_viewmodel($pdo);
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
            display: none;
        }

        .suggest-item {
            padding: .5rem .75rem;
            cursor: pointer;
        }

        .suggest-item:hover {
            background: #f3f4f6;
        }

        .search-wrapper {
            position: relative;
            /* para posicionar o suggest */
            width: 100%;
        }
    </style>
</head>

<body>
    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'estoque-produtos'; // ID do menu atual
    include '../../layouts/sidebar.php';
    ?>

    <main class="main-content">
        <!-- helper para o JS externo ler o CSRF -->
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
                        <input type="search" class="form-control" placeholder="Pesquisar...">
                    </div>
                </div>
            </nav>


            <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
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
                                <option value="petshop" <?= $vm['setor'] === 'autopeca' ? 'selected' : ''; ?>>Pets Shop</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <select id="select-ativo" class="form-select">
                                <option value="">Status: Todos</option>
                                <option value="1" <?= $vm['ativo'] === '1' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="0" <?= $vm['ativo'] === '0' ? 'selected' : ''; ?>>Inativos</option>
                            </select>
                        </div>
                        <div class="col-lg-6 text-end">
                            <a class="btn btn-outline-secondary" href="./produtosNovo.php"><i class="bi bi-plus-lg me-1"></i> Novo Produto</a>
                        </div>
                    </div>

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
                                <?php if (!$vm['rows']): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Nenhum produto encontrado.</td>
                                    </tr>
                                    <?php else: foreach ($vm['rows'] as $r):
                                        $setorNome = (strtolower((string)($r['categoria_nome'] ?? '')) === 'lava jato') ? 'Lava Jato' : 'Auto Peças';
                                    ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($r['nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($r['fornecedor_nome'])): ?>
                                                    <div class="small text-muted">Fornecedor: <?= htmlspecialchars($r['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>Pets Shop</td>
                                            <td><?= htmlspecialchars($r['sku']   ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($r['ean']   ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($r['marca'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end">R$ <?= number_format((float)($r['preco_venda'] ?? 0), 2, ',', '.') ?></td>
                                            <td class="text-end"><?= number_format((float)($r['estoque_atual'] ?? 0), 3, ',', '.') ?></td>
                                            <td>
                                                <?php $a = (int)($r['ativo'] ?? 0); ?>
                                                <span class="badge bg-<?= $a ? 'success' : 'secondary' ?>"><?= $a ? 'Ativo' : 'Inativo' ?></span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <form method="post" action="../actions/produtosExcluir.php" class="d-inline"
                                                    onsubmit="return confirm('Excluir este produto? Esta ação não pode ser desfeita.');">
                                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>

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

    <!-- JS da página (separado) -->
    <script src="../../assets/js/public/produtosList.js"></script>



</body>

</html>