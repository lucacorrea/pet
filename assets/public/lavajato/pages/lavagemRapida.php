<?php
// autoErp/public/lavajato/pages/lavagemRapida.php
declare(strict_types=1);

require_once __DIR__ . '/../controllers/lavagemRapidaController.php';
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavagem Rápida</title>
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
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
  $menuAtivo = 'lavagemRapida'; // ID do menu atual
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

      <div class="iq-navbar-header" style="height: 150px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-md-12">
              <h1 class="mb-0">Lavagem Rápida</h1>
              <p>Registre uma lavagem em poucos cliques.</p>
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
        <!-- Formulário -->
        <div class="col-12">
          <div class="card" data-aos="fade-up">
            <div class="card-header">
              <h4 class="card-title mb-0">Dados da Lavagem</h4>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/lavagensSalvar.php">
                <input type="hidden" name="op" value="lav_rapida_nova">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($vm['csrf'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="row g-3">
                  <div class="col-md-5">
                    <label class="form-label">Serviço</label>
                    <select name="categoria_id" id="categoria_id" class="form-select" required>
                      <option value="">— Escolha —</option>
                      <?php foreach ($vm['servicos'] as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                          data-valor="<?= htmlspecialchars((string)$s['valor_padrao'], ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Lavador</label>
                    <select name="lavador_cpf" class="form-select">
                      <option value="">— Selecionar —</option>
                      <?php foreach ($vm['lavadores'] as $l): ?>
                        <?php $cpf = preg_replace('/\D+/', '', (string)($l['cpf'] ?? '')); ?>
                        <option value="<?= htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($l['nome'], ENT_QUOTES, 'UTF-8') ?><?= $cpf ? ' — CPF ' . $cpf : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-2">
                    <label class="form-label">Valor</label>
                    <input type="number" step="0.01" min="0" name="valor" id="valor" class="form-control" required>
                  </div>

                  <div class="col-md-2">
                    <label class="form-label">Pagamento</label>
                    <select name="forma_pagamento" class="form-select">
                      <option value="dinheiro">Dinheiro</option>
                      <option value="pix">PIX</option>
                      <option value="debito">Débito</option>
                      <option value="credito">Crédito</option>
                    </select>
                  </div>
                </div>

                <div class="row g-3 mt-3">
                  <div class="col-md-3"><label class="form-label">Placa</label><input type="text" name="placa" class="form-control"></div>
                  <div class="col-md-3"><label class="form-label">Modelo</label><input type="text" name="modelo" class="form-control"></div>
                  <div class="col-md-3"><label class="form-label">Cor</label><input type="text" name="cor" class="form-control"></div>
                  <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <option value="aberta">Aberta</option>
                      <option value="concluida">Concluída</option>
                    </select>
                  </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Registrar</button>
                </div>
              </form>

            </div>
          </div>
        </div>



      </div>
    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars($vm['empresaNome'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
      </div>
    </footer>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
  <script>
    document.getElementById('categoria_id')?.addEventListener('change', function() {
      var opt = this.options[this.selectedIndex];
      var v = opt ? opt.getAttribute('data-valor') : '';
      if (v) document.getElementById('valor').value = parseFloat(v).toFixed(2);
    });
  </script>
</body>

</html>