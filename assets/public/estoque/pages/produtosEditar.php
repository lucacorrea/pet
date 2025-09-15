<?php
// autoErp/public/estoque/pages/produtosEditar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'estoque']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
require_once __DIR__ . '/../controllers/produtosController.php';

$empresaNome = empresa_nome_logada($pdo);

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ./produtos.php?err=1&msg=Produto inválido'); exit; }

// CSRF
if (empty($_SESSION['csrf_prod_edit'])) {
  $_SESSION['csrf_prod_edit'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_prod_edit'];

// busca produto
$produto = produto_get_by_id($pdo, $empresaCnpj, $id);
if (!$produto) { header('Location: ./produtos.php?err=1&msg=Produto não encontrado'); exit; }

// flash
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

function v($arr, $k, $def=''){ return htmlspecialchars((string)($arr[$k] ?? $def), ENT_QUOTES, 'UTF-8'); }
function n($arr, $k){ return number_format((float)($arr[$k] ?? 0), 2, ',', '.'); }
function n3($arr, $k){ return number_format((float)($arr[$k] ?? 0), 3, ',', '.'); }
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Editar Produto</title>

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
</head>

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
        <div class="ms-auto"></div>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:150px; margin-bottom: 40px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-md-12">
            <h1 class="mb-0">Editar Produto</h1>
            <p>Atualize as informações do produto abaixo.</p>
            <?php if ($ok || $err): ?>
              <div class="mt-2 alert alert-<?= $err ? 'danger' : 'success' ?> py-2 mb-0">
                <?= htmlspecialchars($msg ?: ($ok ? 'Produto atualizado.' : 'Falha ao atualizar.'), ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="iq-header-img">
        <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
      </div>
    </div>
  </div>

  <div class="container-fluid content-inner mt-n3 py-0">
    <div class="row">
      <div class="col-12">
        <div class="card" data-aos="fade-up" data-aos-delay="150">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Dados do Produto</h4>
            <div class="d-flex gap-2">
              <a href="./produtos.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
          </div>
          <div class="card-body">
            <form method="post" action="../actions/produtosSalvar.php" autocomplete="off">
              <input type="hidden" name="op" value="produto_atualizar">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Nome</label>
                  <input type="text" name="nome" class="form-control" required maxlength="180" value="<?= v($produto,'nome') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Categoria (ID) <span class="text-muted small">(opcional)</span></label>
                  <input type="number" name="categoria_id" class="form-control" min="0" value="<?= (int)($produto['categoria_id'] ?? 0) ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label">SKU <span class="text-muted small">(opcional)</span></label>
                  <input type="text" name="sku" class="form-control" maxlength="60" value="<?= v($produto,'sku') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Código de Barras (EAN) <span class="text-muted small">(opcional)</span></label>
                  <input type="text" name="ean" class="form-control" maxlength="20" value="<?= v($produto,'ean') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Marca <span class="text-muted small">(opcional)</span></label>
                  <input type="text" name="marca" class="form-control" maxlength="80" value="<?= v($produto,'marca') ?>">
                </div>

                <div class="col-md-2">
                  <label class="form-label">Unidade</label>
                  <input type="text" name="unidade" class="form-control" maxlength="10" value="<?= v($produto,'unidade','UN') ?>">
                </div>
                <div class="col-md-5">
                  <label class="form-label">Preço de Custo (R$)</label>
                  <input type="number" step="0.01" min="0" name="preco_custo" class="form-control text-end" value="<?= (float)$produto['preco_custo'] ?>">
                </div>
                <div class="col-md-5">
                  <label class="form-label">Preço de Venda (R$)</label>
                  <input type="number" step="0.01" min="0" name="preco_venda" class="form-control text-end" required value="<?= (float)$produto['preco_venda'] ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Estoque Atual</label>
                  <input type="number" step="0.001" name="estoque_atual" class="form-control text-end" value="<?= (float)$produto['estoque_atual'] ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Estoque Mínimo</label>
                  <input type="number" step="0.001" name="estoque_minimo" class="form-control text-end" value="<?= (float)$produto['estoque_minimo'] ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <div class="form-check form-switch">
                    <?php $ativo = (int)($produto['ativo'] ?? 1); ?>
                    <input class="form-check-input" type="checkbox" id="ativoSwitch" name="ativo" value="1" <?= $ativo ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ativoSwitch">Produto Ativo</label>
                  </div>
                </div>
              </div>

              <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i> Salvar Alterações
                </button>
                <a href="./produtos.php" class="btn btn-outline-secondary">Cancelar</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">
          © <script>document.write(new Date().getFullYear())</script>
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
