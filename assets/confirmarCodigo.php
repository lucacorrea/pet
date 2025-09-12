<?php
// mensagens de retorno
$ok   = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$err  = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$sent = isset($_GET['sent']) ? (int)$_GET['sent'] : 0;

// e-mail pré-preenchido (ex.: veio do link após enviar código)
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$emailSafe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Mundo Pets - Confirmar Código</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="./public/assets/images/favicon.ico">

    <!-- Biblioteca / Plugin Css Build -->
    <link rel="stylesheet" href="./public/assets/css/core/libs.min.css">

    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="./public/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Css Personalizado -->
    <link rel="stylesheet" href="./public/assets/css/custom.min.css?v=4.0.0">

    <!-- Css Escuro -->
    <link rel="stylesheet" href="./public/assets/css/dark.min.css">

    <!-- Css Customizador -->
    <link rel="stylesheet" href="./public/assets/css/customizer.min.css">
    <link rel="stylesheet" href="./public/assets/css/customizer.css">

    <!-- Css RTL -->
    <link rel="stylesheet" href="./public/assets/css/rtl.min.css">
</head>

<style>
@media (max-width: 767.98px) {
    .logo-icon {
        display: block !important;
        width: 6rem !important;
        height: auto !important;
        margin: 0 auto 1rem -2rem !important;
    }

    .logo-title {
        display: block !important;
        font-size: 1.2rem !important;
        margin: 0 auto 1rem -1rem !important;
        color: #000 !important;
        text-align: center;
    }

    .bg-primary { display: none !important; }
    .vh-100 { min-height: 100vh !important; height: auto !important; }
}
</style>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white vh-100">
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <img src="./public/assets/images/auth/02.png"
                         class="img-fluid gradient-main animated-scaleX" alt="imagens">
                </div>

                <div class="col-md-6 p-0">
                    <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                        <div class="card-body">
                            <a href="#" class="navbar-brand d-flex align-items-center mb-3">
                                <div class="logo-main">
                                    <div class="logo-normal">
                                        <img src="./public/assets/images/dashboard/logo.png" class="logo-icon"
                                             alt="logo"
                                             style="width: 8rem; height: 6rem; margin-left: -9rem;">
                                    </div>
                                </div>
                                <h4 class="logo-title" style="margin-left: -3rem; margin-top: -1rem;">Mundo Pets</h4>
                            </a>

                            <h2 class="mb-2">Confirmar Código</h2>
                            <p>
                                Digite o <strong>código de 6 dígitos</strong> enviado para
                                <strong><?= $emailSafe ?: 'seu e-mail' ?></strong> e crie uma nova senha.
                            </p>

                            <?php if ($ok === 1): ?>
                                <div class="alert alert-success">
                                    Senha alterada com sucesso! Você já pode entrar.
                                </div>
                                <a href="./index.php" class="btn btn-primary">Ir para o login</a>
                            <?php else: ?>
                                <?php if ($sent === 1): ?>
                                    <div class="alert alert-info">Código enviado! Verifique sua caixa de entrada.</div>
                                <?php endif; ?>
                                <?php if ($err === 1): ?>
                                    <div class="alert alert-danger">Código inválido ou expirado. Tente novamente.</div>
                                <?php endif; ?>

                                <form action="./actions/auth_reset_confirm.php" method="POST" autocomplete="off" novalidate>
                                    <input type="hidden" name="email" value="<?= $emailSafe ?>">

                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="floating-label form-group">
                                                <label for="code" class="form-label">Código de 6 dígitos</label>
                                                <input type="text"
                                                       class="form-control"
                                                       id="code"
                                                       name="code"
                                                       inputmode="numeric"
                                                       pattern="\d{6}"
                                                       minlength="6" maxlength="6"
                                                       placeholder=" "
                                                       required>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="floating-label form-group">
                                                <label for="senha1" class="form-label">Nova senha</label>
                                                <input type="password"
                                                       class="form-control"
                                                       id="senha1"
                                                       name="senha1"
                                                       minlength="6"
                                                       placeholder=" "
                                                       required>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="floating-label form-group">
                                                <label for="senha2" class="form-label">Confirme a nova senha</label>
                                                <input type="password"
                                                       class="form-control"
                                                       id="senha2"
                                                       name="senha2"
                                                       minlength="6"
                                                       placeholder=" "
                                                       required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Salvar nova senha</button>
                                    <a class="btn btn-link" href="./confirmaEmail.php?email=<?= urlencode($email) ?>">
                                        Reenviar código
                                    </a>
                                    <a class="btn btn-link" href="./index.php">Voltar</a>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- Scripts -->
    <script src="./public/assets/js/core/libs.min.js"></script>
    <script src="./public/assets/js/core/external.min.js"></script>
    <script src="./public/assets/js/hope-ui.js" defer></script>
</body>
</html>
