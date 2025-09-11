<?php
// autoErp/public/empresa.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']); // usa CNPJ/CPF da sessão

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao;
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
require_once __DIR__ . '/../controllers/empresaController.php';
$vm = empresa_config_viewmodel($pdo); // agora recebe $pdo e busca pelo CNPJ da sessão
?>

<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mundo Pets — Dados da Empresa</title>

    <link rel="icon" type="image/png" href="../../assets/images/dashboard/logo.png">
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
    <!-- usa o mesmo menu -->
    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'config-empresa'; // sem espaço
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
                                    <h1>Dados da Empresa</h1>
                                    <p>Gerencie as informações cadastrais da sua empresa.</p>
                                </div>
                            </div>

                            <?php if ($vm['ok'] || $vm['err']): ?>
                                <div class="mt-3">
                                    <?php if ($vm['ok']):  ?><div class="alert alert-success py-2 mb-0"><?= $vm['msg'] ?: 'Informações atualizadas com sucesso.' ?></div><?php endif; ?>
                                    <?php if ($vm['err']): ?><div class="alert alert-danger  py-2 mb-0"><?= $vm['msg'] ?: 'Falha ao atualizar informações.' ?></div><?php endif; ?>
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
                        <div class="card-header">
                            <h4 class="card-title mb-0">Informações Cadastrais</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="../actions/atualizar.php" id="form-empresa">
                                <input type="hidden" name="csrf" value="<?= $vm['csrf'] ?>">
                                <input type="hidden" name="cnpj" value="<?= htmlspecialchars($vm['cnpj'], ENT_QUOTES, 'UTF-8') ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome Fantasia</label>
                                        <input type="text" name="nome_fantasia" class="form-control" value="<?= htmlspecialchars($vm['empresa']['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?> required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Razão Social</label>
                                        <input type="text" name="razao_social" class="form-control" value="<?= htmlspecialchars($vm['empresa']['razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">CNPJ</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($vm['cnpjFmt'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($vm['empresa']['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($vm['empresa']['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-8">
                                        <label class="form-label">Endereço</label>
                                        <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($vm['empresa']['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">CEP</label>
                                        <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($vm['empresa']['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($vm['empresa']['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Estado (UF)</label>
                                        <input type="text" name="estado" class="form-control" value="<?= htmlspecialchars($vm['empresa']['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="2" <?= $vm['readonlyAttr'] ?>>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($vm['empresa']['status'] ?? 'ativa', ENT_QUOTES, 'UTF-8') ?>" readonly>
                                    </div>
                                </div>

                                <?php if ($vm['canEdit']): ?>
                                    <div class="mt-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar alterações</button>
                                        <a href="../../dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-4 alert alert-info mb-0">
                                        <i class="bi bi-info-circle"></i> Apenas o <strong>dono</strong> pode editar os dados da empresa.
                                    </div>
                                <?php endif; ?>
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
    <script src="../../assets/js/public/configEmpresaView.js"></script>
</body>

</html>