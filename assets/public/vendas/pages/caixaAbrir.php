<?php
// autoErp/public/caixa/pages/caixaAbrir.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Identidade do operador
$empresaCnpj  = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$operadorCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
$operadorNome = (string)($_SESSION['user_nome'] ?? '');
$userEmail    = (string)($_SESSION['user_email'] ?? '');
$userId       = (string)($_SESSION['user_id'] ?? '');

if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

if ($operadorCpf === '' && $userEmail !== '') {
  try {
    $stOp = $pdo->prepare("SELECT cpf, nome FROM usuarios_peca WHERE email = :e LIMIT 1");
    $stOp->execute([':e' => $userEmail]);
    if ($row = $stOp->fetch(PDO::FETCH_ASSOC)) {
      $operadorCpf  = preg_replace('/\D+/', '', (string)($row['cpf'] ?? ''));
      if ($operadorNome === '' && !empty($row['nome'])) $operadorNome = $row['nome'];
      $_SESSION['user_cpf']  = $operadorCpf;
      if (!empty($row['nome'])) $_SESSION['user_nome'] = $row['nome'];
    }
  } catch (Throwable $e) { /* silencioso */ }
}

if ($operadorCpf === '') {
  if ($userId !== '') {
    $operadorCpf = 'UID' . preg_replace('/\D+/', '', $userId);
  } else {
    die('Operador inválido.');
  }
}

// CSRF
if (empty($_SESSION['csrf_caixa_abrir'])) {
  $_SESSION['csrf_caixa_abrir'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_caixa_abrir'];

// Flash
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// Caixa aberto (qualquer tipo)
$caixaAberto = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, status, DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS quando,
           saldo_inicial, COALESCE(terminal,'PDV') AS terminal
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
  ");
  $st->execute([':c' => $empresaCnpj]);
  $caixaAberto = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $caixaAberto = null; }

// Lista de compartilhados abertos
$compartilhados = [];
try {
  $st2 = $pdo->prepare("
    SELECT id, COALESCE(terminal,'PDV') AS terminal,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS quando,
           saldo_inicial
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND tipo = 'compartilhado' AND status = 'aberto'
    ORDER BY aberto_em DESC
    LIMIT 100
  ");
  $st2->execute([':c' => $empresaCnpj]);
  $compartilhados = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $compartilhados = []; }
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mundo Pets — Abrir Caixa</title>
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/logo.png">
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
  $menuAtivo = 'vendas-abriCaixa'; // ID do menu atual
  include '../../layouts/sidebar.php';
  ?>
  <main class="main-content"> 
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
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

      <div class="iq-navbar-header" style="height: 140px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12">
              <h1 class="mb-0">Abertura de Caixa</h1>
              <p>Abra um novo caixa ou entre em um caixa <strong>compartilhado</strong> já aberto.</p>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="row g-3">
        <!-- Abrir novo caixa -->
        <div class="col-lg-6">
          <div class="card" data-aos="fade-up">
            <div class="card-header">
              <h4 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Abrir Novo Caixa</h4>
              <div class="small text-muted">Operador: <?= htmlspecialchars($operadorNome ?: $operadorCpf, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/caixaAbrirSalvar.php" class="row g-3" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="op" value="abrir">

                <div class="col-md-6">
                  <label class="form-label">Tipo de Caixa</label>
                  <select class="form-select" name="tipo" required>
                    <option value="compartilhado">Compartilhado (vários operadores)</option>
                    <option value="individual">Individual (apenas você)</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Valor de Abertura</label>
                  <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input id="valor_abertura" type="number" step="0.01" min="0" class="form-control" name="valor_abertura" placeholder="0,00" required>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Terminal (opcional)</label>
                  <input type="text" class="form-control" name="terminal" placeholder="Ex.: PDV 01">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Observações</label>
                  <input type="text" class="form-control" name="observacoes" maxlength="180" placeholder="Ex.: Caixa principal • Turno manhã">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cash-coin me-1"></i> Abrir Caixa
                  </button>
                </div>
              </form>
            </div>
            <div class="card-footer text-muted small">
              Após abrir, outros operadores podem participar (se for compartilhado).
            </div>
          </div>
        </div>

        <!-- Entrar em caixa compartilhado -->
        <div class="col-lg-6">
          <div class="card" data-aos="fade-up">
            <div class="card-header">
              <h4 class="card-title mb-0"><i class="bi bi-people me-2"></i>Entrar em Caixa Compartilhado</h4>
              <div class="small text-muted">Selecione um caixa aberto para participar.</div>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/caixaAbrirSalvar.php" class="row g-3">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="op" value="entrar">

                <div class="col-12">
                  <label class="form-label">Caixa Compartilhado Aberto</label>
                  <select class="form-select" name="caixa_compartilhado_id" required>
                    <option value="">— Selecione um caixa —</option>
                    <?php if ($compartilhados): ?>
                      <?php foreach ($compartilhados as $cx): ?>
                        <option value="<?= (int)$cx['id'] ?>">
                          #<?= (int)$cx['id'] ?> — <?= htmlspecialchars($cx['terminal'] ?? 'PDV', ENT_QUOTES, 'UTF-8') ?>
                          — Aberto em <?= htmlspecialchars($cx['quando'], ENT_QUOTES, 'UTF-8') ?>
                          — Saldo R$ <?= number_format((float)$cx['saldo_inicial'], 2, ',', '.') ?>
                        </option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="" disabled>Nenhum caixa compartilhado aberto encontrado</option>
                    <?php endif; ?>
                  </select>
                </div>

                <?php if ($compartilhados): ?>
                <div class="col-12">
                  <div class="small text-muted mb-2">Caixas abertos (até 5):</div>
                  <ul class="list-group">
                    <?php foreach (array_slice($compartilhados,0,5) as $cx): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <strong>#<?= (int)$cx['id'] ?> — <?= htmlspecialchars($cx['terminal'],ENT_QUOTES,'UTF-8') ?></strong>
                          <div class="text-muted small">Aberto em <?= htmlspecialchars($cx['quando'],ENT_QUOTES,'UTF-8') ?></div>
                        </div>
                        <span class="badge bg-light text-dark border">R$ <?= number_format((float)$cx['saldo_inicial'],2,',','.') ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>

                <div class="col-12 d-flex justify-content-end gap-2">
                 
                  <button type="submit" class="btn btn-success" <?= !$compartilhados ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right me-1"></i> Entrar no Caixa
                  </button>
                </div>
              </form>
            </div>
            <div class="card-footer text-muted small">
              Ideal para turnos com múltiplos atendentes no mesmo PDV.
            </div>
          </div>
        </div>
        <!-- /Entrar em caixa compartilhado -->
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

  <script>
    // Foco e normalização do valor
    const valor = document.getElementById('valor_abertura');
    if (valor) {
      setTimeout(()=> valor.focus(), 200);
      valor.addEventListener('blur', () => {
        const n = Number((valor.value || '0').replace(',', '.'));
        valor.value = isNaN(n) ? '' : n.toFixed(2);
      });
    }
  </script>
</body>
</html>
