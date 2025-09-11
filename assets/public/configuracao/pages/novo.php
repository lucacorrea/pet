<?php
// autoErp/public/novo.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
// Dono/funcionários podem abrir a tela; só o DONO envia
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ========= Conexão ========= */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao; // deve definir $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        die('Conexão indisponível.');
    }
}

/* ========= CSRF ========= */
if (empty($_SESSION['csrf_cfg_user_new'])) {
    $_SESSION['csrf_cfg_user_new'] = bin2hex(random_bytes(32));
}

/* ========= Empresa da sessão (para mostrar no topo) ========= */
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$empresaNome = '—';
$cnpjFmt = $cnpjSess;
if (preg_match('/^\d{14}$/', $cnpjSess)) {
    // formata CNPJ
    $cnpjFmt = substr($cnpjSess, 0, 2) . '.' . substr($cnpjSess, 2, 3) . '.' . substr($cnpjSess, 5, 3) . '/' . substr($cnpjSess, 8, 4) . '-' . substr($cnpjSess, 12, 2);
    try {
        $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
        $st->execute([':c' => $cnpjSess]);
        $empresaNome = (string)($st->fetchColumn() ?: '—');
        if ($empresaNome === '') $empresaNome = '—';
    } catch (Throwable $e) { /* sem fatal */
    }
}

require_once __DIR__ . '/../../../lib/util.php'; // ajuste caminho conforme a pasta
$empresaNome = empresa_nome_logada($pdo); // nome da empresa logada

/* ========= Permissão para criar ========= */
$canCreate = (($_SESSION['user_perfil'] ?? '') === 'dono');

/* ========= Flash ========= */
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP — Cadastrar Usuário</title>

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
    $menuAtivo = 'config-add-usuario'; // sem espaço
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

                            </svg>
                        </span>

                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height: 180px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1>Cadastrar Usuário</h1>
                                    <p>Empresa: <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong> · CNPJ <strong><?= htmlspecialchars($cnpjFmt, ENT_QUOTES, 'UTF-8') ?></strong></p>
                                </div>
                            </div>

                            <?php if ($ok || $err): ?>
                                <div class="mt-3">
                                    <?php if ($ok):  ?><div class="alert alert-success  py-2 mb-0"><?= $msg ?: 'Usuário criado com sucesso.' ?></div><?php endif; ?>
                                    <?php if ($err): ?><div class="alert alert-danger   py-2 mb-0"><?= $msg ?: 'Falha ao criar usuário.' ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!$canCreate): ?>
                                <div class="mt-3 alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i> Somente o <strong>dono</strong> pode cadastrar usuários.</div>
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
                        <div class="card-header">
                            <h4 class="card-title mb-0">Dados do Novo Usuário</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="../actions/salvar.php" id="form-user">
                                <input type="hidden" name="op" value="usuario_novo">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_cfg_user_new'] ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome completo</label>
                                        <input type="text" name="nome" class="form-control" required <?= $canCreate ? '' : 'disabled' ?>>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" class="form-control" required <?= $canCreate ? '' : 'disabled' ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">CPF (opcional)</label>
                                        <input type="text" name="cpf" maxlength="11" pattern="\d{11}" class="form-control" placeholder="Somente números" <?= $canCreate ? '' : 'disabled' ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Telefone (opcional)</label>
                                        <input type="text" name="telefone" class="form-control" <?= $canCreate ? '' : 'disabled' ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Tipo de funcionário</label>
                                        <select name="tipo_funcionario" class="form-select" required <?= $canCreate ? '' : 'disabled' ?>>
                                            <option value="administrativo">Administrativo</option>
                                            <option value="caixa">Caixa</option>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-3">
                                    O novo usuário receberá um e-mail com código e link para <strong>definir a própria senha</strong>.
                                </small>
                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" <?= $canCreate ? '' : 'disabled' ?>>
                                        <i class="bi bi-person-plus"></i> Cadastrar
                                    </button>
                                </div>
                            </form>

                        </div>
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