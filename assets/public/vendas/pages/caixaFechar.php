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

$usuarioCpf = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// CSRF
if (empty($_SESSION['csrf_fechar_caixa'])) {
  $_SESSION['csrf_fechar_caixa'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_fechar_caixa'];

// feedback
$ok  = (int)($_GET['ok'] ?? 0);
$err = (int)($_GET['err'] ?? 0);
$msg = (string)($_GET['msg'] ?? '');

// caixa aberto atual
$cx = null;
try {
  $st = $pdo->prepare("
    SELECT id, tipo, COALESCE(terminal,'PDV') AS terminal, aberto_por_cpf,
           DATE_FORMAT(aberto_em,'%d/%m/%Y %H:%i') AS aberto_quando,
           aberto_em, saldo_inicial
    FROM caixas_peca
    WHERE empresa_cnpj = :c AND status = 'aberto'
    ORDER BY aberto_em DESC
    LIMIT 1
  ");
  $st->execute([':c' => $empresaCnpj]);
  $cx = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $cx = null;
}

// tentativa de apuração (se existir tabela de vendas)
$apur = [
  'total_geral' => 0.00,
  'dinheiro'    => 0.00,
  'pix'         => 0.00,
  'debito'      => 0.00,
  'credito'     => 0.00,
  'qtd_vendas'  => 0,
];
if ($cx) {
  try {
    // Ajuste o nome/colunas abaixo se sua tabela diferir.
    // Exemplo: vendas_peca(total, forma_pagamento, empresa_cnpj, criado_em)
    $sql = "
      SELECT
        COUNT(*)                            AS qtd_vendas,
        COALESCE(SUM(total),0)              AS total_geral,
        COALESCE(SUM(CASE WHEN forma_pagamento='dinheiro' THEN total END),0) AS dinheiro,
        COALESCE(SUM(CASE WHEN forma_pagamento='pix'      THEN total END),0) AS pix,
        COALESCE(SUM(CASE WHEN forma_pagamento='debito'   THEN total END),0) AS debito,
        COALESCE(SUM(CASE WHEN forma_pagamento='credito'  THEN total END),0) AS credito
      FROM vendas_peca
      WHERE empresa_cnpj = :c
        AND criado_em >= :aberto_em
    ";
    $stV = $pdo->prepare($sql);
    $stV->execute([
      ':c' => $empresaCnpj,
      ':aberto_em' => $cx['aberto_em'], // datetime puro
    ]);
    $row = $stV->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($apur as $k => $v) {
      if (isset($row[$k])) $apur[$k] = (float)$row[$k];
    }
  } catch (Throwable $e) {
    // se a tabela/colunas não existirem, apenas segue sem apuração
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Fechar Caixa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <style>
    .money { font-variant-numeric: tabular-nums; }
    .card-tot { border:1px dashed #d9d9e3; border-radius:14px; background:#fafbff; }
  </style>
</head>
<body>
<?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  $menuAtivo = 'vendas-rapida'; // como você pediu: menu chamado assim
  include __DIR__ . '/../../layouts/sidebar.php';
?>

<main class="main-content">
  <div class="container-fluid content-inner py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-0">Fechar Caixa</h3>
        <div class="text-muted">Finalize o caixa aberto e registre observações.</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary" href="../../vendas/pages/vendaRapida.php">
          <i class="bi bi-arrow-left"></i> Voltar
        </a>
      </div>
    </div>

    <?php if ($ok || $err || $msg): ?>
      <div class="alert <?= $err ? 'alert-danger' : 'alert-success' ?>"><?= htmlspecialchars($msg ?: ($err?'Falha na operação.':'Operação realizada.'), ENT_QUOTES,'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!$cx): ?>
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Não há caixa aberto para esta empresa.
        <a href="./caixaAbrir.php" class="alert-link">Clique aqui</a> para abrir.
      </div>
    <?php else: ?>
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header"><h5 class="mb-0">Dados do Caixa</h5></div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">#ID</dt><dd class="col-sm-8"><?= (int)$cx['id'] ?></dd>
                <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= htmlspecialchars($cx['tipo'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Terminal</dt><dd class="col-sm-8"><?= htmlspecialchars($cx['terminal'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Aberto em</dt><dd class="col-sm-8"><?= htmlspecialchars($cx['aberto_quando'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Operador (CPF)</dt><dd class="col-sm-8"><?= htmlspecialchars($cx['aberto_por_cpf'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Saldo inicial</dt><dd class="col-sm-8 money">R$ <?= number_format((float)$cx['saldo_inicial'], 2, ',', '.') ?></dd>
              </dl>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
              <h5 class="mb-0">Observações do Fechamento</h5>
            </div>
            <div class="card-body">
              <form method="post" action="../actions/caixaFecharPost.php" id="form-fechar">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="cx_id" value="<?= (int)$cx['id'] ?>">
                <div class="mb-3">
                  <label class="form-label">Observações</label>
                  <textarea name="observacoes" class="form-control" rows="4" placeholder="Ex.: contagem conferida, sangria realizada, etc."></textarea>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-danger">
                    <i class="bi bi-lock-fill me-1"></i> Fechar Caixa
                  </button>
                  <a href="../../vendas/pages/vendaRapida.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card card-tot">
            <div class="card-header"><h5 class="mb-0">Resumo (desde a abertura)</h5></div>
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <span class="text-muted">Qtd de vendas</span>
                <strong><?= (int)$apur['qtd_vendas'] ?></strong>
              </div>
              <div class="d-flex justify-content-between mt-2">
                <span class="text-muted">Total geral</span>
                <strong class="money">R$ <?= number_format($apur['total_geral'], 2, ',', '.') ?></strong>
              </div>
              <hr>
              <div class="d-flex justify-content-between">
                <span class="text-muted">Dinheiro</span>
                <strong class="money">R$ <?= number_format($apur['dinheiro'], 2, ',', '.') ?></strong>
              </div>
              <div class="d-flex justify-content-between mt-1">
                <span class="text-muted">PIX</span>
                <strong class="money">R$ <?= number_format($apur['pix'], 2, ',', '.') ?></strong>
              </div>
              <div class="d-flex justify-content-between mt-1">
                <span class="text-muted">Débito</span>
                <strong class="money">R$ <?= number_format($apur['debito'], 2, ',', '.') ?></strong>
              </div>
              <div class="d-flex justify-content-between mt-1">
                <span class="text-muted">Crédito</span>
                <strong class="money">R$ <?= number_format($apur['credito'], 2, ',', '.') ?></strong>
              </div>
              <hr>
              <div class="small text-muted">
                * Se o resumo estiver zerado, é provável que a tabela/colunas de vendas sejam diferentes. O fechamento funciona do mesmo jeito.
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/hope-ui.js"></script>
</body>
</html>
