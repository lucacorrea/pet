<?php
// autoErp/criarConta.php
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF simples
if (empty($_SESSION['csrf_register_company'])) {
  $_SESSION['csrf_register_company'] = bin2hex(random_bytes(32));
}

$ok  = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$err = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Criar Conta - AutoERP</title>

  <link rel="shortcut icon" href="./public/assets/images/favicon.ico">
  <link rel="stylesheet" href="./public/assets/css/core/libs.min.css">
  <link rel="stylesheet" href="./public/assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="./public/assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="./public/assets/css/dark.min.css">
  <link rel="stylesheet" href="./public/assets/css/customizer.min.css">
  <link rel="stylesheet" href="./public/assets/css/rtl.min.css">
</head>

<body>
  <div class="wrapper">
    <section class="login-content">
      <div class="row m-0 align-items-center bg-white vh-100">
        <div class="col-md-6 p-0">
          <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
            <div class="card-body">
              <a href="./index.php" class="navbar-brand d-flex align-items-center mb-2">
                <div class="logo-main">
                  <div class="logo-normal">
                    <img src="./public/assets/images/auth/ode.png" style="width: 100px; margin-top:-20px" alt="AutoERP">
                  </div>
                </div>
                <h4 class="logo-title ms-2" style="margin-top:-20px; margin-left:-30px !important;">AutoERP</h4>
              </a>

              <h2 class="mb-2">Solicitar Cadastro</h2>
              <p>Preencha seus dados e o CNPJ da sua autopeças. Vamos analisar e aprovar o acesso.</p>
              <?php if (!empty($ok) || !empty($err)):
                $type    = !empty($ok) ? 'success' : 'danger';
                $title   = !empty($ok) ? 'Sucesso' : 'Erro';
                $message = $msg ?: (!empty($ok)
                  ? 'Solicitação enviada com sucesso! Você será avisado por e-mail quando for aprovada.'
                  : 'Não foi possível enviar sua solicitação. Tente novamente.');
              ?>
                <!-- Modal de feedback -->
                <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0">
                      <div class="modal-header bg-<?= $type ?> text-white">
                        <h5 class="modal-title m-0 text-white"><?= $title ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                      </div>
                      <div class="modal-body">
                        <p class="mb-0" style="font-size:13px;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                      </div>
                      <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-<?= $type ?>" data-bs-dismiss="modal">Ok</button>
                        <a href="index.php" class="btn btn-success">Voltar ao Login</a>
                      </div>


                    </div>
                  </div>
                </div>

                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    var el = document.getElementById('feedbackModal');
                    if (el && window.bootstrap) {
                      var modal = new bootstrap.Modal(el);
                      modal.show();
                    }
                  });
                </script>
              <?php endif; ?>


              <form id="form-solicitacao" action="./actions/auth_register_company.php" method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_register_company'] ?>">
                <!-- honeypot anti-bot -->
                <input type="text" name="website" value="" style="display:none">

                <!-- Proprietário: nome -->
                <div class="row">
                  <div class="col-lg-12">
                    <div class="floating-label form-group">
                      <label for="proprietario_nome" class="form-label">Nome completo *</label>
                      <input type="text" class="form-control" id="proprietario_nome" name="proprietario_nome" placeholder=" " maxlength="150" required>
                      <div class="invalid-feedback">Informe o nome completo.</div>
                    </div>
                  </div>
                </div>

                <!-- Proprietário: e-mail -->
                <div class="row">
                  <div class="col-lg-12">
                    <div class="floating-label form-group">
                      <label for="proprietario_email" class="form-label">E-mail do proprietário *</label>
                      <input type="email" class="form-control" id="proprietario_email" name="proprietario_email" placeholder=" " maxlength="150" required>
                      <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                  </div>
                </div>

                <!-- Senha + Confirmação -->
                <div class="row">
                  <div class="col-lg-12">
                    <div class="floating-label form-group">
                      <label for="senha" class="form-label">Senha (mín. 8) *</label>
                      <input type="password" class="form-control" id="senha" name="proprietario_senha" placeholder=" " minlength="8" required autocomplete="new-password">
                      <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-lg-12">
                    <div class="floating-label form-group">
                      <label for="senha2" class="form-label">Confirmar senha *</label>
                      <input type="password" class="form-control" id="senha2" name="proprietario_senha2" placeholder=" " minlength="8" required autocomplete="new-password">
                      <div class="invalid-feedback">As senhas precisam ser iguais.</div>
                    </div>
                  </div>
                </div>

                <!-- CNPJ -->
                <div class="row">
                  <div class="col-lg-12">
                    <div class="floating-label form-group">
                      <label for="cnpj" class="form-label">CNPJ (somente números) *</label>
                      <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder=" " inputmode="numeric" pattern="^\d{14}$" maxlength="14" required>
                      <div class="invalid-feedback">CNPJ deve ter 14 dígitos numéricos.</div>
                    </div>
                  </div>
                </div>

                <!-- Aceite -->
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="aceite" name="aceite" value="1" required>
                  <label class="form-check-label" for="aceite">
                    Li e aceito os
                    <a href="#" target="_blank" class="text-success text-decoration-underline">Termos de Uso</a>
                    e a
                    <a href="#" target="_blank" class="text-success text-decoration-underline">Política de Privacidade</a>.
                  </label>
                </div>

                <button id="btn-submit" type="submit" class="btn btn-success" disabled>Enviar solicitação</button>
                <a class="btn btn-link text-success" href="./index.php">Voltar ao Login</a>
              </form>
            </div>
          </div>

          <div class="sign-bg">
            <svg width="280" height="230" viewBox="0 0 431 398" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g opacity="0.05">
                <rect x="-157.085" y="193.773" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 -157.085 193.773)" fill="#3B8AFF"></rect>
                <rect x="7.46875" y="358.327" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 7.46875 358.327)" fill="#3B8AFF"></rect>
                <rect x="61.9355" y="138.545" width="310.286" height="77.5714" rx="38.7857" transform="rotate(45 61.9355 138.545)" fill="#3B8AFF"></rect>
                <rect x="62.3154" y="-190.173" width="543" height="77.5714" rx="38.7857" transform="rotate(45 62.3154 -190.173)" fill="#3B8AFF"></rect>
              </g>
            </svg>
          </div>
        </div>
        <div class="col-md-6 d-md-block d-none p-0 "
          style="height: 100vh; display: flex; margin-top:-70px;">
          <img src="./public/assets/images/auth/04.png"
            class=""
            style="object-fit: cover; width: 100%; height: 110%;"
            alt="images">
        </div>

      </div>
    </section>
  </div>

  <script src="./public/assets/js/core/libs.min.js"></script>
  <script src="./public/assets/js/core/external.min.js"></script>
  <script src="./public/assets/js/hope-ui.js" defer></script>

  <script>
    (function() {
      const form = document.getElementById('form-solicitacao');
      const btn = document.getElementById('btn-submit');
      const nome = document.getElementById('proprietario_nome');
      const email = document.getElementById('proprietario_email');
      const cnpj = document.getElementById('cnpj');
      const ace = document.getElementById('aceite');
      const s1 = document.getElementById('senha');
      const s2 = document.getElementById('senha2');

      function onlyDigits(el) {
        el.value = el.value.replace(/\D+/g, '');
      }
      cnpj.addEventListener('input', () => {
        onlyDigits(cnpj);
        toggle();
      });

      function validAll() {
        const vNome = (nome.value.trim().length >= 3);
        const vEmail = !!email.value.trim() && email.validity.valid;
        const vCNPJ = /^\d{14}$/.test(cnpj.value.trim());
        const vS1 = s1.value.length >= 8;
        const vS2 = s2.value.length >= 8 && s2.value === s1.value;
        const vAce = ace.checked;
        return vNome && vEmail && vCNPJ && vS1 && vS2 && vAce;
      }

      function toggle() {
        btn.disabled = !validAll();
      }

      [nome, email, ace, s1, s2].forEach(el => {
        el.addEventListener('input', toggle);
        el.addEventListener('change', toggle);
        el.addEventListener('blur', toggle);
      });

      form.addEventListener('submit', function(e) {
        // feedback nativo bootstrap
        if (!validAll()) {
          e.preventDefault();
          form.classList.add('was-validated');
          // mensagens específicas
          if (s2.value !== s1.value) {
            s2.setCustomValidity('As senhas precisam ser iguais.');
          } else {
            s2.setCustomValidity('');
          }
        }
      });

      toggle();
    })();
  </script>
</body>

</html>