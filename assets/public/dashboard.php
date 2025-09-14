<?php
// autoErp/public/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth_guard.php';
ensure_logged_in(['dono', 'funcionario']);
if (session_status() === PHP_SESSION_NONE) session_start();

// Sessão
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$cpfSess  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

// Conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // define $pdo
}

// ================= CONTROLLER INTERNO =================
$nomeUser     = $_SESSION['user_nome'] ?? 'Usuário';
$empresaNome  = '—';
$empresaCnpj  = $cnpjSess;

// Nome da empresa
if ($pdo instanceof PDO && $empresaCnpj !== '') {
  try {
    $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
    $st->execute([':c' => $empresaCnpj]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $empresaNome = (string)$row['nome_fantasia'];
    }
  } catch (Throwable $e) {}
}

// Métricas (30 dias)
$vendasQtde = $faturamento30d = $itensEstoque = $despesas30d = 0.0;

if ($pdo instanceof PDO && $empresaCnpj !== '') {
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*) AS qtde, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c
        AND status = 'fechada'
        AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $st->execute([':c' => $empresaCnpj]);
    $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde'=>0,'total'=>0];
    $vendasQtde     = (int)$v['qtde'];
    $faturamento30d = (float)$v['total'];
  } catch (Throwable $e) {}

  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE empresa_cnpj = :c AND ativo = 1");
    $st->execute([':c' => $empresaCnpj]);
    $itensEstoque = (int)$st->fetchColumn();
  } catch (Throwable $e) {}
}

// Metas
$vendasMeta = 350; $estoqueMeta = 3210; $faturamentoMeta = 7400; $despesasMeta = 1250;
$__pct = fn(float $v, float $m): int => $m > 0 ? max(0, min(100, (int)round(($v/$m)*100))) : 0;
$vendasPct = $__pct($vendasQtde, $vendasMeta);
$estoquePct = $__pct($itensEstoque, $estoqueMeta);
$faturamentoPct = $__pct($faturamento30d, $faturamentoMeta);
$despesasPct = $__pct($despesas30d, $despesasMeta);

// ======= GRÁFICO =======
$chartLabels = $chartSeries = [];
$filtro = $_GET['filtro'] ?? '6m'; // default 6 meses

if ($pdo instanceof PDO && $empresaCnpj !== '') {
  if ($filtro === '1w') {
    // Última semana (7 dias)
    $labels = []; $vals = [];
    for ($i = 6; $i >= 0; $i--) {
      $dia = (new DateTime())->modify("-$i day");
      $labels[] = $dia->format('d/m');
      $vals[]   = 0.0;
    }
    $st = $pdo->prepare("
      SELECT DATE(criado_em) AS dia, SUM(total_liquido) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(criado_em)
    ");
    $st->execute([':c' => $empresaCnpj]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $d = (new DateTime($r['dia']))->format('d/m');
      $idx = array_search($d, $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  }
  elseif ($filtro === '1m') {
    // Últimas 4 semanas (~1 mês)
    $labels = ['Semana 1','Semana 2','Semana 3','Semana 4']; $vals = [0,0,0,0];
    $st = $pdo->prepare("
      SELECT WEEK(criado_em,1) AS semana, SUM(total_liquido) AS total
      FROM vendas_peca
      WHERE empresa_cnpj = :c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
      GROUP BY WEEK(criado_em,1)
      ORDER BY semana
    ");
    $st->execute([':c' => $empresaCnpj]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $i=0; foreach($rows as $r){ if($i<4){ $vals[$i]=(float)$r['total']; } $i++; }
  }
  elseif ($filtro === '12m') {
    // Últimos 12 meses
    $labels=[]; $vals=[];
    $now = new DateTime('first day of this month');
    for ($i=11;$i>=0;$i--){
      $p=(clone $now)->modify("-$i months");
      $labels[]=$p->format('m/Y'); $vals[]=0.0;
    }
    $st=$pdo->prepare("
      SELECT DATE_FORMAT(criado_em,'%m/%Y') AS mes, SUM(total_liquido) AS total
      FROM vendas_peca
      WHERE empresa_cnpj=:c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
      GROUP BY YEAR(criado_em),MONTH(criado_em)
    ");
    $st->execute([':c'=>$empresaCnpj]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $idx=array_search($r['mes'],$labels,true);
      if($idx!==false) $vals[$idx]=(float)$r['total'];
    }
  }
  else {
    // Últimos 6 meses (default)
    $labels=[]; $vals=[];
    $now=new DateTime('first day of this month');
    for($i=5;$i>=0;$i--){ $p=(clone $now)->modify("-$i months"); $labels[]=$p->format('m/Y'); $vals[]=0.0; }
    $st=$pdo->prepare("
      SELECT DATE_FORMAT(criado_em,'%m/%Y') AS mes, SUM(total_liquido) AS total
      FROM vendas_peca
      WHERE empresa_cnpj=:c AND status='fechada'
        AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      GROUP BY YEAR(criado_em),MONTH(criado_em)
    ");
    $st->execute([':c'=>$empresaCnpj]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $idx=array_search($r['mes'],$labels,true);
      if($idx!==false) $vals[$idx]=(float)$r['total'];
    }
  }
  $chartLabels=$labels; $chartSeries=$vals;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Mundo Pets - Dashboard</title>
  <link rel="stylesheet" href="./assets/css/core/libs.min.css">
  <link rel="stylesheet" href="./assets/css/hope-ui.min.css?v=4.0.0">
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>
  <main class="main-content container">
    <h1>Bem-vindo, <?=htmlspecialchars($nomeUser)?>!</h1>
    <p>Empresa: <strong><?=htmlspecialchars($empresaNome)?></strong></p>

    <div class="row">
      <div class="col">Vendas: <?=$vendasQtde?></div>
      <div class="col">Itens Estoque: <?=$itensEstoque?></div>
      <div class="col">Faturamento: R$ <?=number_format($faturamento30d,2,',','.')?></div>
    </div>

    <!-- Filtro -->
    <div class="dropdown my-3">
      <a class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" href="#">
        <?php
        echo match($filtro){
          '1w'=>'Última Semana','1m'=>'Último Mês','12m'=>'Últimos 12 Meses',default=>'Últimos 6 Meses'
        };
        ?>
      </a>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="?filtro=1w">Última Semana</a></li>
        <li><a class="dropdown-item" href="?filtro=1m">Último Mês</a></li>
        <li><a class="dropdown-item" href="?filtro=6m">Últimos 6 Meses</a></li>
        <li><a class="dropdown-item" href="?filtro=12m">Últimos 12 Meses</a></li>
      </ul>
    </div>

    <div id="d-main"></div>
  </main>

<script>
const el=document.querySelector("#d-main");
const LABELS=<?=json_encode($chartLabels,JSON_UNESCAPED_UNICODE)?>;
const SERIES=<?=json_encode($chartSeries,JSON_UNESCAPED_UNICODE)?>;
if(LABELS.length){
  new ApexCharts(el,{
    chart:{type:'area',height:360},
    series:[{name:'Faturamento',data:SERIES}],
    xaxis:{categories:LABELS},
    stroke:{curve:'smooth',width:3},
    tooltip:{y:{formatter:(val)=>'R$ '+Number(val).toLocaleString('pt-BR',{minimumFractionDigits:2})}}
  }).render();
}else{
  el.innerHTML="<p class='text-muted'>Sem dados para o período.</p>";
}
</script>
</body>
</html>
