<?php
// autoErp/public/configuracao/pages/listar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
// Dono e funcionários (exceto lavador)
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

// Conexão PDO ($pdo)
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao; // define $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        die('Conexão indisponível.');
    }
}

require_once __DIR__ . '/../../../lib/util.php'; // ajuste caminho conforme a pasta
$empresaNome = empresa_nome_logada($pdo); // nome da empresa logada
// Controller
require_once __DIR__ . '/../controllers/usuariosEmpresaController.php';
$vm = usuarios_empresa_viewmodel($pdo);

// Helpers locais
function badge_tipo(string $t): string
{
    $rot = [
        'administrativo' => 'Administrativo',
        'caixa'          => 'Caixa',
        'estoque'        => 'Estoque',
        'lavajato'       => 'Lavador',
    ][$t] ?? ucfirst($t);
    return '<span class="badge bg-info text-dark">' . htmlspecialchars($rot, ENT_QUOTES, 'UTF-8') . '</span>';
}
function badge_status(int $s): string
{
    return $s === 1
        ? '<span class="badge bg-success">Ativo</span>'
        : '<span class="badge bg-secondary">Inativo</span>';
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP — Usuários da Empresa</title>

    <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
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
</head>

<body class="">
   <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'config-usuarios'; // sem espaço
    include '../../layouts/sidebar.php';
    ?>


    <main class="main-content">
        <div class="position-relative iq-banner">
            <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="../../dashboard.php" class="navbar-brand">
                        <h4 class="logo-title">AutoERP</h4>
                    </a>
                    <div class="input-group search-input">
                        <span class="input-group-text" id="search-input">
                            <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
                            </svg>
                        </span>
                        <form class="d-flex" method="get">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($vm['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="tipo" value="<?= htmlspecialchars($vm['tipo'],   ENT_QUOTES, 'UTF-8') ?>">
                            <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($vm['buscar'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome, e-mail ou CPF">
                        </form>
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height: 180px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1>Usuários — Funcionários</h1>
                                    <p>Empresa: <strong><?= htmlspecialchars($vm['empresaNome'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                                </div>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm <?= $vm['status'] === 'ativos'   ? 'btn-success'        : 'btn-outline-success' ?>" href="?status=ativos&q=<?= urlencode($vm['buscar']) ?>&tipo=<?= urlencode($vm['tipo']) ?>">Ativos (<?= (int)$vm['totais']['ativos'] ?>)</a>
                                    <a class="btn btn-sm <?= $vm['status'] === 'inativos' ? 'btn-secondary'      : 'btn-outline-secondary' ?>" href="?status=inativos&q=<?= urlencode($vm['buscar']) ?>&tipo=<?= urlencode($vm['tipo']) ?>">Inativos (<?= (int)$vm['totais']['inativos'] ?>)</a>
                                    <a class="btn btn-sm <?= $vm['status'] === 'todos'    ? 'btn-dark'           : 'btn-outline-dark' ?>" href="?status=todos&q=<?= urlencode($vm['buscar']) ?>&tipo=<?= urlencode($vm['tipo']) ?>">Todos (<?= (int)$vm['totais']['todos'] ?>)</a>
                                </div>
                            </div>

                            <?php if (!empty($_GET['ok']) || !empty($_GET['err'])): ?>
                                <div class="mt-3">
                                    <?php if (!empty($_GET['ok'])):  ?><div class="alert alert-success  py-2 mb-0"><?= htmlspecialchars($_GET['msg'] ?? 'Operação realizada com sucesso.', ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                    <?php if (!empty($_GET['err'])): ?><div class="alert alert-danger   py-2 mb-0"><?= htmlspecialchars($_GET['msg'] ?? 'Falha na operação.', ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="iq-header-img">
                    <img src="../../assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
                </div>
            </div>
        </div>

        <div class="container-fluid content-inner mt-n4 py-0">
            <div class="row">
                <div class="col-12">
                    <div class="card" data-aos="fade-up" data-aos-delay="150">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Lista de Funcionários</h4>
                            <?php if ($vm['canCreate']): ?>
                                <a href="./novo.php" class="btn btn-sm btn-primary mb-2"><i class="bi bi-person-plus"></i> Novo usuário</a>
                            <?php endif; ?>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width:70px">#</th>
                                            <th>Usuário</th>
                                            <th>Tipo</th>
                                            <th>Status</th>
                                            <th>Criado em</th>
                                            <th class="text-end" style="width:160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$vm['usuarios']): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Nenhum usuário encontrado.</td>
                                            </tr>
                                            <?php else: foreach ($vm['usuarios'] as $u): ?>
                                                <tr>
                                                    <td class="text-nowrap"><?= (int)$u['id'] ?></td>
                                                    <td class="text-nowrap">
                                                        <div class="fw-semibold"><?= htmlspecialchars($u['nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                        <div class="small text-muted">
                                                            <?= htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if (!empty($u['cpf'])): ?> · CPF: <?= htmlspecialchars($u['cpf'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td><?= badge_tipo((string)($u['tipo_funcionario'] ?? '')) ?></td>
                                                    <td class="text-nowrap"><?= badge_status((int)($u['status'] ?? 0)) ?></td>
                                                    <td class="text-nowrap"><?= htmlspecialchars($u['criado_em'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end text-nowrap">
                                                        <?php if ($vm['canCreate'] && (int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                                            <form method="post" action="../actions/salvar.php" class="d-inline"
                                                                onsubmit="return confirm('Excluir este usuário? Esta ação é irreversível.');">
                                                                <input type="hidden" name="op" value="usuario_excluir">
                                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($vm['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir usuário">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>

                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if ($vm['pages'] > 1): ?>
                            <div class="card-footer">
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php
                                        $qsBase = [
                                            'status' => $vm['status'],
                                            'tipo'   => $vm['tipo'],
                                        ];
                                        if ($vm['buscar'] !== '') $qsBase['q'] = $vm['buscar'];
                                        $qsStr = http_build_query($qsBase);
                                        for ($p = 1; $p <= $vm['pages']; $p++):
                                            $active = ($p === (int)$vm['page']) ? ' active' : '';
                                        ?>
                                            <li class="page-item<?= $active ?>">
                                                <a class="page-link" href="?<?= $qsStr ?>&p=<?= $p ?>"><?= $p ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
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
</body>

</html>