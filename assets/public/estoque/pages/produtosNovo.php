<?php
// autoErp/public/estoque/pages/produtosNovo.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']); // só esses podem cadastrar produto
require_once __DIR__ . '/../../../lib/util.php'; // ajuste caminho conforme a pasta
$empresaNome = empresa_nome_logada($pdo); // nome da empresa logada
// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// CNPJ da sessão
$empresaCnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) {
  die('Empresa não vinculada ao usuário.');
}

// CSRF para este form
if (empty($_SESSION['csrf_prod_novo'])) {
  $_SESSION['csrf_prod_novo'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_prod_novo'];

// Fornecedores da empresa (para o select)
$fornecedores = [];
try {
  $st = $pdo->prepare("SELECT id, nome FROM fornecedores_peca WHERE empresa_cnpj = :c AND ativo = 1 ORDER BY nome");
  $st->execute([':c' => $empresaCnpjSess]);
  $fornecedores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $fornecedores = [];
}

// flash
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Novo Produto</title>
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

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'estoque-add-produto'; // ID do menu atual
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="#" class="navbar-brand">
            <h4 class="logo-title">Mundo Pets</h4>
          </a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">

              </svg>
            </span>

          </div>
        </div>
      </nav>
      <!-- card de exibição de status de erros ou sucesso -->
      <?php if ($ok || $err): ?>
        <div
          id="toastMsg"
          class="position-fixed top-0 end-0 m-3 shadow-lg"
          style="z-index: 2000; min-width: 360px; border-radius: 12px; overflow: hidden; animation: slideIn .4s ease-out;">
          <div class="bg-success text-white p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <i class="bi bi-check-circle-fill fs-3"></i>
              <div class="fw-semibold fs-6">
                <?= htmlspecialchars($msg ?: 'Operação realizada com sucesso!', ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="toast" aria-label="Fechar"></button>
          </div>
          <div class="progress" style="height: 4px;">
            <div id="toastProgress" class="progress-bar bg-light" style="width: 100%; transition: width 5s linear;"></div>
          </div>
        </div>

        <style>
          @keyframes slideIn {
            from {
              transform: translateX(120%);
              opacity: 0;
            }

            to {
              transform: translateX(0);
              opacity: 1;
            }
          }

          @keyframes slideOut {
            from {
              transform: translateX(0);
              opacity: 1;
            }

            to {
              transform: translateX(120%);
              opacity: 0;
            }
          }
        </style>

        <script>
          document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.getElementById("toastMsg");
            const progress = document.getElementById("toastProgress");

            if (toastEl) {
              // anima barra
              setTimeout(() => progress.style.width = "0%", 50);

              // remove após 5s
              setTimeout(() => {
                toastEl.style.animation = "slideOut .4s ease-in forwards";
                setTimeout(() => toastEl.remove(), 400);
              }, 5000);
            }
          });
        </script>
      <?php endif; ?>

      <div class="iq-navbar-header" style="height: 150px; margin-bottom: 80px ;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <h1 class="mb-0">Cadastrar Produto</h1>
              <p>Informe os dados do produto e escolha o setor (Auto Peças ou Lava Jato).</p>


            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" alt="" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="row">
        <div class="col-12">
          <div class="card" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header">
              <h4 class="card-title mb-0">Dados do Produto</h4>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/produtosSalvar.php" id="form-produto">
                <input type="hidden" name="op" value="produto_novo">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Setor</label>
                    <select name="setor" class="form-select" required>
                      <option value="petshop">Pet Shop</option>

                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required maxlength="180">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Fornecedor (opcional)</label>
                    <select name="fornecedor_id" class="form-select">
                      <option value="">— Selecione —</option>
                      <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">SKU (opcional)</label>
                    <input type="text" name="sku" class="form-control" maxlength="60">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Código De Barras (opcional)</label>
                    <input type="text" name="ean" class="form-control" maxlength="20">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Marca (opcional)</label>
                    <input type="text" name="marca" class="form-control" maxlength="80">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Unidade</label>
                    <input type="text" name="unidade" class="form-control" maxlength="10">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Preço de Custo</label>
                    <input type="number" name="preco_custo" step="0.01" min="0" class="form-control">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Preço de Venda</label>
                    <input type="number" name="preco_venda" step="0.01" min="0" class="form-control" required>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Estoque Mínimo</label>
                    <input type="number" name="estoque_minimo" step="0.001" min="0" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Estoque Inicial</label>
                    <input type="number" name="estoque_inicial" step="0.001" min="0" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Ativo?</label>
                    <select name="ativo" class="form-select">
                      <option value="1" selected>Sim</option>
                      <option value="0">Não</option>
                    </select>
                  </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar</button>
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