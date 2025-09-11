<?php
// Lado "controller": prepara todas as variáveis que a view vai usar

$nomeUser = $_SESSION['user_nome'] ?? 'Super Admin';
$hasDb    = isset($pdo) && ($pdo instanceof PDO);

/* ===== GRÁFICO: EMPRESAS ATIVAS (últimos 6 meses) ===== */
$labels = [];
$serieEmpresas = [];
try {
    $mesesPt = [1=>'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $mapMeses = [];
    $dt = new DateTime('first day of this month');
    $dt->modify('-5 months'); // 6 meses (antigo → atual)
    for ($i = 0; $i < 6; $i++) {
        $key = $dt->format('Y-m');
        $labels[] = $mesesPt[(int)$dt->format('n')] . '/' . $dt->format('Y');
        $mapMeses[$key] = 0;
        $dt->modify('+1 month');
    }
    if ($hasDb) {
        $sqlEmp = "SELECT DATE_FORMAT(criado_em, '%Y-%m') ym, COUNT(*) t
                     FROM empresas_peca
                    WHERE status='ativa'
                 GROUP BY ym";
        foreach ($pdo->query($sqlEmp) as $r) {
            if (isset($mapMeses[$r['ym']])) $mapMeses[$r['ym']] = (int)$r['t'];
        }
    }
    foreach ($mapMeses as $qtd) $serieEmpresas[] = $qtd;
} catch (Throwable $e) {
    $labels = ['-','-','-','-','-','-'];
    $serieEmpresas = [0,0,0,0,0,0];
}

/* ===== CARDS (4) + FILTRO ===== */
$periodo = $_GET['p'] ?? 'month';           // today | 7d | month | 3m | year
$valid   = ['today','7d','month','3m','year'];
if (!in_array($periodo, $valid, true)) $periodo = 'month';

$ini = new DateTime('today 00:00:00');
$fim = new DateTime('now');
switch ($periodo) {
    case 'today': break;
    case '7d':   $ini->modify('-6 days'); break;
    case '3m':   $ini = new DateTime('first day of -2 month 00:00:00'); break;
    case 'year': $ini = new DateTime('first day of january this year 00:00:00'); break;
    case 'month':
    default:     $ini = new DateTime('first day of this month 00:00:00'); break;
}
$labelPeriodo = [
  'today'=>'Hoje','7d'=>'Últimos 7 dias','month'=>'Este mês','3m'=>'Últimos 3 meses','year'=>'Este ano'
][$periodo];

// Métricas
$novasEmpresas = 0;
$solicitacoesPeriodo = 0;
$solicitacoesPendentes = 0;
$empresasAtivasTotal = 0;

if ($hasDb) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM empresas_peca WHERE criado_em BETWEEN :i AND :f");
    $st->execute([':i'=>$ini->format('Y-m-d H:i:s'), ':f'=>$fim->format('Y-m-d H:i:s')]);
    $novasEmpresas = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) c FROM solicitacoes_empresas_peca WHERE criado_em BETWEEN :i AND :f");
    $st->execute([':i'=>$ini->format('Y-m-d H:i:s'), ':f'=>$fim->format('Y-m-d H:i:s')]);
    $solicitacoesPeriodo = (int)$st->fetchColumn();

    $solicitacoesPendentes = (int)$pdo->query("SELECT COUNT(*) FROM solicitacoes_empresas_peca WHERE status='pendente'")->fetchColumn();

    $empresasAtivasTotal  = (int)$pdo->query("SELECT COUNT(*) FROM empresas_peca WHERE status='ativa'")->fetchColumn();
}

// Percentuais dos círculos
$maxVal = max(1, $novasEmpresas, $solicitacoesPeriodo, $solicitacoesPendentes, $empresasAtivasTotal);
$pct = fn($v,$m)=> (int)round(($v/$m)*100);
$pctEmpNovas   = $pct($novasEmpresas,$maxVal);
$pctSolPeriodo = $pct($solicitacoesPeriodo,$maxVal);
$pctSolPend    = $pct($solicitacoesPendentes,$maxVal);
$pctEmpAtivas  = $pct($empresasAtivasTotal,$maxVal);

/* ===== Tabela de solicitações pendentes ===== */
$solicitacoes = [];
if ($hasDb) {
    try {
        $q = $pdo->query("SELECT id, nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, status, criado_em
                            FROM solicitacoes_empresas_peca
                           WHERE status='pendente'
                        ORDER BY criado_em DESC
                           LIMIT 50");
        $solicitacoes = $q->fetchAll();
    } catch (Throwable $e) {
        $solicitacoes = [];
    }
}

// Flash
$ok  = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$err = isset($_GET['err']) ? (int)$_GET['err'] : 0;
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
