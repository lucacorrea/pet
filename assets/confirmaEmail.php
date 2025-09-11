<?php
// autoErp/confirmaEmail.php — Redefinir Senha
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF simples
if (empty($_SESSION['csrf_reset'])) {
    $_SESSION['csrf_reset'] = bin2hex(random_bytes(32));
}

// mensagens de retorno
$ok     = isset($_GET['ok'])  ? (int)$_GET['ok']  : 0;
$err    = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$rawMsg = $_GET['msg'] ?? '';
$msg    = htmlspecialchars($rawMsg, ENT_QUOTES, 'UTF-8');

// pré-preencher e-mail (opcional)
$prefill = isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : '';

// se o erro menciona "email", marcamos o input como inválido
$isInvalidEmail = ($err === 1 && stripos($rawMsg, 'email') !== false);
?>
<!doctype html>
<html lang="pt" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>AutoERP - Redefinir Senha</title>

    <link rel="shortcut icon" href="./public/assets/images/favicon.ico">
    <link rel="stylesheet" href="./public/assets/css/core/libs.min.css">
    <link rel="stylesheet" href="./public/assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="./public/assets/css/custom.min.css?v=4.0.0">
    <link rel="stylesheet" href="./public/assets/css/dark.min.css">
    <link rel="stylesheet" href="./public/assets/css/customizer.min.css">
    <link rel="stylesheet" href="./public/assets/css/rtl.min.css">
</head>

<body class="">
    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white vh-100">
                <!-- Coluna esquerda (imagem) -->
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <img src="./public/assets/images/auth/02.png" class="img-fluid gradient-main animated-scaleX" alt="Redefinir Senha">
                </div>

                <!-- Coluna direita (form) -->
                <div class="col-md-6 p-0">
                    <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                        <div class="card-body">
                            <a href="./index.php" class="navbar-brand d-flex align-items-center mb-3">
                                <div class="logo-main">
                                    <div class="logo-normal">
                                        <img src="./public/assets/images/auth/ode.png" style="width: 100px; margin-top: -20px;" alt="AutoERP">
                                    </div>
                                </div>
                                <h4 class="logo-title ms-2" style="margin-top: -20px; margin-left: -30px !important;">AutoERP</h4>
                            </a>

                            <h2 class="mb-2">Redefinir Senha</h2>
                            <p>Digite seu e-mail. Enviaremos um <strong>código de 6 dígitos</strong> para confirmar a redefinição.</p>

                            <?php if ($ok === 1): ?>
                                <div class="alert alert-success">
                                    Se o e-mail existir no sistema, o código foi enviado. Verifique sua caixa de entrada.
                                </div>
                            <?php elseif ($err === 1): ?>
                                <div class="alert alert-danger">
                                    <?= $msg ?: 'Ocorreu um erro ao processar a solicitação. Tente novamente em instantes.' ?>
                                </div>
                            <?php endif; ?>

                            <form action="./actions/auth_reset_request.php" method="POST" autocomplete="off" novalidate>
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_reset'] ?>">
                                <!-- honeypot -->
                                <input type="text" name="website" value="" style="display:none">

                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="floating-label form-group">
                                            <label for="email" class="form-label">E-mail</label>
                                            <input
                                                type="email"
                                                class="form-control <?= $isInvalidEmail ? 'is-invalid' : '' ?>"
                                                id="email"
                                                name="email"
                                                value="<?=$prefill?>"
                                                placeholder=" "
                                                required>
                                            <?php if ($isInvalidEmail): ?>
                                                <div class="invalid-feedback">
                                                    <?= $msg ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="form-text">Informe o e-mail cadastrado.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">Enviar código</button>
                                <a class="btn btn-link text-success" href="./confirmarCodigo.php?email=<?=$prefill?>">Já tenho o código</a>
                                <a class="btn btn-link text-success" href="./index.php">Voltar</a>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- /col direita -->
            </div>
        </section>
    </div>

    <script src="./public/assets/js/core/libs.min.js"></script>
    <script src="./public/assets/js/core/external.min.js"></script>
    <script src="./public/assets/js/hope-ui.js" defer></script>
</body>
</html>

