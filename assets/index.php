<?php
// autoErp/index.php — Login (padrão Hope UI)
if (session_status() === PHP_SESSION_NONE) session_start();

$mensagemErro = '';
if (isset($_GET['erro'])) {
   if ($_GET['erro'] == '1') {
      $mensagemErro = 'Usuário ou senha inválidos.';
   } elseif ($_GET['erro'] == '2') {
      $det = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
      $mensagemErro = 'Erro no sistema' . ($det ? ': ' . $det : '.');
   }
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
   <title>PetERP — Login</title>

   <!-- Favicon -->
   <link rel="shortcut icon" href="./public/assets/images/favicon.ico">

   <!-- Library / Plugin Css Build -->
   <link rel="stylesheet" href="./public/assets/css/core/libs.min.css">

   <!-- Hope Ui Design System Css -->
   <link rel="stylesheet" href="./public/assets/css/hope-ui.min.css?v=4.0.0">

   <!-- Custom Css -->
   <link rel="stylesheet" href="./public/assets/css/custom.min.css?v=4.0.0">

   <!-- Dark / Customizer / RTL -->
   <link rel="stylesheet" href="./public/assets/css/dark.min.css">
   <link rel="stylesheet" href="./public/assets/css/customizer.min.css">
   <link rel="stylesheet" href="./public/assets/css/rtl.min.css">

   <!-- Bootstrap Icons (para o olho da senha) -->
   <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
   <!-- loader Start -->
   <div id="loading">
      <div class="loader simple-loader">
         <div class="loader-body"></div>
      </div>
   </div>
   <!-- loader END -->

   <div class="wrapper">
      <section class="login-content">
         <div class="row m-0 align-items-center bg-white vh-100">
            <!-- Coluna esquerda (form) -->
            <div class="col-md-6 p-0">
               <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                  <div class="card-body">
                     <a href="./index.php" class="navbar-brand d-flex align-items-center mb-3">
                        <!-- Logo -->
                        <div class="logo-main">
                           <div class="logo-normal">
                              <img src="./public/assets/images/dashboard/logo.png" style="width: 80px; margin-top: -20px;"
                                 alt="AutoERP">
                           </div>
                           <div class="logo-mini">
                              <img src="./public/assets/images/dashboard/logo.png" class="icon-30" alt="AutoERP">
                           </div>
                        </div>
                        <!-- /Logo -->
                        <!-- /Logo -->
                        <h4 class="logo-title ms-2" style="margin-top: -20px; margin-left: -15px !important;">Mundo Pets</h4>
                     </a>


                     <h2 class="mb-2">Entrar</h2>
                     <p>Use seu e-mail, CPF ou nome para acessar.</p>

                     <?php if ($mensagemErro): ?>
                        <div class="alert alert-danger text-center" role="alert">
                           <?= $mensagemErro ?>
                        </div>
                     <?php endif; ?>
                     <?php if (!empty($_GET['logout'])): ?>
                        <div class="alert alert-success text-center">Você saiu com segurança.</div>
                     <?php endif; ?>


                     <form id="loginForm" action="./actions/auth_login.php" method="POST" autocomplete="off" novalidate>
                        <!-- Usuário -->
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="floating-label form-group">
                                 <label for="usuario" class="form-label">E-mail, CPF ou Nome</label>
                                 <input type="text" class="form-control" id="usuario" name="usuario" placeholder=" "
                                    required autocomplete="username" autofocus>
                                 <div class="invalid-feedback">Informe seu e-mail, CPF ou nome.</div>
                              </div>
                           </div>
                        </div>

                        <!-- Senha (com toggle) -->
                        <div class="row">
                           <div class="col-lg-12">
                              <div class="floating-label form-group position-relative">
                                 <label for="senha" class="form-label">Senha</label>
                                 <input type="password" class="form-control pe-5" id="senha" name="senha"
                                    placeholder=" " required autocomplete="current-password">
                                 <button type="button"
                                    class="btn btn-link p-0 position-absolute top-50 end-0 translate-middle-y me-2"
                                    onclick="togglePwd()" tabindex="-1" aria-label="Mostrar/ocultar senha">
                                   
                                 </button>
                                 <div class="invalid-feedback">Informe sua senha.</div>
                              </div>
                           </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 text-success">
                           <a href="./confirmaEmail.php" class="text-primary">Esqueceu a senha?</a>
                        </div>

                        <button id="btnLogin" type="submit" class="btn btn-primary w-100">Entrar</button>

                        <div class="text-center mt-3">
                           <span>Não tem conta?
                              <a href="./criarConta.php" class="text-primary fw-bold">Cadastre-se</a>
                           </span>
                        </div>
                     </form>
                  </div>
               </div>

               <!-- shapes de fundo (igual ao criar conta) -->
               <div class="sign-bg">
               </div>
            </div>

            <!-- Coluna direita (imagem) -->
            <div class="col-md-6 d-none d-md-block bg-primary p-0 mt-n1 vh-100 overflow-hidden">
               <img src="./public/assets/images/auth/03.png" class="img-fluid gradient-main animated-scaleX"
                  alt="Login">
            </div>
         </div>
      </section>
   </div>

   <!-- Scripts -->
   <script src="./public/assets/js/core/libs.min.js"></script>
   <script src="./public/assets/js/core/external.min.js"></script>
   <script src="./public/assets/js/hope-ui.js" defer></script>

   <script>
      function togglePwd() {
         const input = document.getElementById('senha');
         const eye = document.getElementById('eyeIcon');
         if (input.type === 'password') {
            input.type = 'text';
            eye.classList.replace('bi-eye', 'bi-eye-slash');
         } else {
            input.type = 'password';
            eye.classList.replace('bi-eye-slash', 'bi-eye');
         }
      }

      // validação básica (HTML5 + feedback visual)
      (function() {
         const form = document.getElementById('loginForm');
         form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
               e.preventDefault();
               form.classList.add('was-validated');
            }
         });
      })();
   </script>
</body>

</html>