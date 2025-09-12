<?php
// autoErp/public/caixa/pages/caixaFechar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');
$usuarioCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// CSRF
if (empty($_SESSION['csrf_caixa_fechar'])) {
  $_SESSION['csrf_caixa_fechar'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_caixa_fechar'];

// Buscar caixa aberto (prioriza compartilhado; se não, individual do próprio usuário)
$caixaAberto = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS aberto_quando,
           COALESCE(saldo_inicial,0) AS saldo_inicial,
           COALESCE(aberto_por_cpf,'') AS aberto_por_cpf
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY aberto_em DESC
  ");
  $st->execute([':c'=>$empresaCnpj]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $isCompart   = fn($t)=> in_array(mb_strtolower(trim((string)$t)), ['compartilhado','compart','comp','coletivo'], true);
  $isIndividual= fn($t)=> in_array(mb_strtolower(trim((string)$t)), ['individual','indiv','ind'], true);
  $onlyDigits  = fn($v)=> preg_replace('/\D+/', '', (string)$v);

  foreach ($rows as $r) { if ($isCompart($r['tipo'])) { $caixaAberto = $r; break; } }
  if (!$caixaAberto) {
    foreach ($rows as $r) {
      if ($isIndividual($r['tipo'])) {
        if ($onlyDigits($r['aberto_por_cpf']) === $onlyDigits($usuarioCpf)) { $caixaAberto = $r; break; }
      }
    }
  }
  // Fallback: se ainda não achou, pega o mais recente (pelo menos exibe)
  if (!$caixaAberto && $rows) $caixaAberto = $rows[0];
} catch(Throwable $e) {
  $caixaAberto = null;
}

?>
<!doctype html>
<html lang="pt-BR" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>AutoERP — Fechar Caixa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

<main class="main-content">
  <div class="position-relative iq-banner">
    <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
      <div class="container-fluid navbar-inner">
        <a href="../../dashboard.php" class="navbar-brand"><h4 class="logo-title">AutoERP</h4></a>
      </div>
    </nav>

    <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
      <div class="container-fluid iq-container">
        <div class="row">
          <div class="col-md-8">
            <h1>Fechar Caixa</h1>
            <p>Confirme os dados e finalize o caixa em aberto.</p>

            <?php if(!$caixaAberto): ?>
              <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Nenhum caixa aberto encontrado para esta empresa.
                <a class="alert-link" href="./caixaAbrir.php">Abrir/Entrar em um caixa</a>
              </div>
            <?php else: ?>
              <div class="card">
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-sm-6">
                      <div class="text-muted small">Caixa</div>
                      <div class="fw-bold">#<?= (int)$caixaAberto['id'] ?></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="text-muted small">Tipo</div>
                      <div class="fw-bold"><?= htmlspecialchars($caixaAberto['tipo'], ENT_QUOTES,'UTF-8') ?></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="text-muted small">Terminal</div>
                      <div class="fw-bold"><?= htmlspecialchars($caixaAberto['terminal'], ENT_QUOTES,'UTF-8') ?></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="text-muted small">Aberto em</div>
                      <div class="fw-bold"><?= htmlspecialchars($caixaAberto['aberto_quando'], ENT_QUOTES,'UTF-8') ?></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="text-muted small">Saldo inicial</div>
                      <div class="fw-bold">R$ <?= number_format((float)$caixaAberto['saldo_inicial'],2,',','.') ?></div>
                    </div>
                  </div>

                  <!-- Se quiser mostrar totais antes de fechar, calcule aqui e mostre:
                  <?php
                  /*
                  $totais = ['vendas'=>0.00,'retiradas'=>0.00,'suprimentos'=>0.00];
                  // EXEMPLO (ajuste nomes de tabelas/colunas):
                  // $s = $pdo->prepare("SELECT SUM(valor_total) FROM vendas WHERE empresa_cnpj=:c AND caixa_id=:id AND status='concluida'");
                  // $s->execute([':c'=>$empresaCnpj, ':id'=>$caixaAberto['id']]);
                  // $totais['vendas'] = (float)$s->fetchColumn();
                  */
                  ?>
                  -->

                  <form method="post" action="../actions/caixaFecharSalvar.php" class="mt-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES,'UTF-8') ?>">
                    <input type="hidden" name="caixa_id" value="<?= (int)$caixaAberto['id'] ?>">

                    <div class="mb-3">
                      <label class="form-label">Observações (opcional)</label>
                      <textarea name="observacoes" class="form-control" rows="3" placeholder="Ex.: conferido com o gerente, sem divergências."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                      <a href="../../vendas/pages/vendaRapida.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                      </a>
                      <button type="submit" class="btn btn-danger">
                        <i class="bi bi-lock-fill"></i> Fechar Caixa
                      </button>
                    </div>
                  </form>
                </div>
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
</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/core/external.min.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>
</body>
</html>
