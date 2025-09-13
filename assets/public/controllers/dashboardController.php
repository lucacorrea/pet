<?php
// autoErp/public/controllers/dashboardController.php
declare(strict_types=1);

/**
 * Controller do Dashboard
 * - KPIs (últimos 30 dias)
 * - Gráfico de vendas (últimos 6 meses, mês atual incluso)
 * - Lavagens recentes (se a tabela existir)
 *
 * Variáveis entregues à View:
 *   $nomeUser, $empresaNome
 *   $vendasQtde, $faturamento30d, $itensEstoque, $despesas30d
 *   $vendasPct, $estoquePct, $faturamentoPct, $despesasPct
 *   $chartLabels, $chartSeries
 *   $lavagens
 */

// ==== Sessão / identidade ====
$nomeUser    = $_SESSION['user_nome'] ?? 'Usuário';
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$empresaNome = '—';

// ==== Defaults ====
$vendasQtde     = 0;
$faturamento30d = 0.0;
$itensEstoque   = 0;
$despesas30d    = 0.0;    // placeholder
$vendasPct      = 0;
$estoquePct     = 0;
$faturamentoPct = 0;
$despesasPct    = 0;
$chartLabels    = [];
$chartSeries    = [];
$lavagens       = [];

// Sem conexão/CNPJ? mantém defaults
if (!isset($pdo) || !($pdo instanceof PDO) || $empresaCnpj === '') {
  return;
}

// ==== Nome da empresa ====
try {
  $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $empresaCnpj]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) $empresaNome = (string)$row['nome_fantasia'];
} catch (Throwable $e) {}

// ==== KPIs (últimos 30 dias) ====
try {
  $dini = (new DateTime('today -29 days 00:00:00'))->format('Y-m-d H:i:s');
  $dfim = (new DateTime('today 23:59:59'))->format('Y-m-d H:i:s');
  $st = $pdo->prepare("
    SELECT COUNT(*) AS qtde, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
     WHERE empresa_cnpj = :c
       AND LOWER(status) = 'fechada'
       AND criado_em BETWEEN :dini AND :dfim
  ");
  $st->execute([':c' => $empresaCnpj, ':dini' => $dini, ':dfim' => $dfim]);
  $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde' => 0, 'total' => 0];
  $vendasQtde     = (int)($v['qtde'] ?? 0);
  $faturamento30d = (float)($v['total'] ?? 0);
} catch (Throwable $e) {}

try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE empresa_cnpj = :c AND ativo = 1");
  $st->execute([':c' => $empresaCnpj]);
  $itensEstoque = (int)$st->fetchColumn();
} catch (Throwable $e) {}

// Metas (ajuste conforme sua realidade)
$vendasMeta      = 350.0;     // unidades
$estoqueMeta     = 3210.0;    // itens
$faturamentoMeta = 7400.00;   // R$
$despesasMeta    = 1250.00;   // R$

$__pct = static function (float $valor, float $meta): int {
  if ($meta <= 0) return 0;
  $p = (int)round(($valor / $meta) * 100);
  return max(0, min(100, $p));
};

$vendasPct      = $__pct((float)$vendasQtde,     (float)$vendasMeta);
$estoquePct     = $__pct((float)$itensEstoque,   (float)$estoqueMeta);
$faturamentoPct = $__pct((float)$faturamento30d, (float)$faturamentoMeta);
$despesasPct    = $__pct((float)$despesas30d,    (float)$despesasMeta);

// ==== Gráfico: Vendas por mês (últimos 6 meses, mês atual incluso) ====

// Janela fechada: 1º dia de 5 meses atrás até o último dia do mês atual
$ini6 = (new DateTime('first day of -5 months 00:00:00'));
$fim6 = (new DateTime('last day of this month 23:59:59'));

$iniIso = $ini6->format('Y-m-d H:i:s');
$fimIso = $fim6->format('Y-m-d H:i:s');

// Labels m/Y (ordem cronológica)
$labels = [];
$it = clone $ini6;
while ($it <= $fim6) {
  $labels[] = $it->format('m/Y');
  $it->modify('first day of next month');
}
$vals = array_fill(0, count($labels), 0.0);

// Agregação por mês de vendas FECHADAS
try {
  $st = $pdo->prepare("
    SELECT DATE_FORMAT(v.criado_em, '%m/%Y') AS mes,
           COALESCE(SUM(v.total_liquido),0)   AS total
      FROM vendas_peca v
     WHERE v.empresa_cnpj = :c
       AND LOWER(v.status) = 'fechada'
       AND v.criado_em BETWEEN :ini AND :fim
     GROUP BY YEAR(v.criado_em), MONTH(v.criado_em)
     ORDER BY YEAR(v.criado_em), MONTH(v.criado_em)
  ");
  $st->execute([':c' => $empresaCnpj, ':ini' => $iniIso, ':fim' => $fimIso]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $mes = (string)$r['mes'];          // ex.: 09/2025
    $idx = array_search($mes, $labels, true);
    if ($idx !== false) $vals[$idx] = (float)$r['total'];
  }
} catch (Throwable $e) {}

$chartLabels = $labels;
$chartSeries = $vals;

// ==== Lavagens recentes (10) — se a tabela existir ====
try {
  $st = $pdo->prepare("
    SELECT
      COALESCE(l.categoria_nome,'Serviço') AS servico,
      TRIM(CONCAT_WS(' ', COALESCE(l.modelo,''), COALESCE(l.cor,''), COALESCE(l.placa,''))) AS veiculo,
      l.valor,
      l.status,
      DATE_FORMAT(l.criado_em, '%d/%m/%Y %H:%i') AS quando,
      u.nome AS lavador
    FROM lavagens_peca l
    LEFT JOIN usuarios_peca u ON u.cpf = l.lavador_cpf
    WHERE l.empresa_cnpj = :c
    ORDER BY l.criado_em DESC
    LIMIT 10
  ");
  $st->execute([':c' => $empresaCnpj]);
  $lavagens = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  // tabela pode não existir — ignore
}
