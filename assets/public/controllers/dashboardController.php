<?php
// autoErp/public/controllers/dashboardController.php
declare(strict_types=1);

/**
 * Controller do Dashboard
 * - Prepara KPIs (últimos 30 dias)
 * - Prepara dados do gráfico de vendas (últimos 6 meses)
 * - Carrega lavagens recentes (se existir a tabela)
 *
 * Variáveis entregues à View:
 *   $nomeUser, $empresaNome
 *   $vendasQtde, $faturamento30d, $itensEstoque, $despesas30d
 *   $vendasPct, $estoquePct, $faturamentoPct, $despesasPct
 *   $chartLabels, $chartSeries
 *   $lavagens
 */

// ==== Garantias mínimas de sessão/identidade ====
$nomeUser    = $_SESSION['user_nome'] ?? 'Usuário';
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$empresaNome = '—';

// ==== Defaults (se não houver conexão ou CNPJ) ====
$vendasQtde     = 0;
$faturamento30d = 0.0;
$itensEstoque   = 0;
$despesas30d    = 0.0;    // placeholder (sem tabela de despesas)
$vendasPct      = 0;
$estoquePct     = 0;
$faturamentoPct = 0;
$despesasPct    = 0;
$chartLabels    = [];
$chartSeries    = [];
$lavagens       = [];

// Se não tiver $pdo/cnpj, devolve só defaults
if (!isset($pdo) || !($pdo instanceof PDO) || $empresaCnpj === '') {
  return;
}

// ==== Nome da empresa ====
try {
  $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $empresaCnpj]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) $empresaNome = (string)$row['nome_fantasia'];
} catch (Throwable $e) {
  // silencioso
}

// ==== KPIs (últimos 30 dias) ====
try {
  // Vendas (quantidade e total líquido) nos últimos 30 dias
  $st = $pdo->prepare("
    SELECT COUNT(*) AS qtde, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
     WHERE empresa_cnpj = :c
       AND LOWER(status) = 'fechada'
       AND criado_em BETWEEN :dini AND :dfim
  ");
  $dini = (new DateTime('today -29 days 00:00:00'))->format('Y-m-d H:i:s');
  $dfim = (new DateTime('today 23:59:59'))->format('Y-m-d H:i:s');
  $st->execute([':c' => $empresaCnpj, ':dini' => $dini, ':dfim' => $dfim]);
  $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde' => 0, 'total' => 0];
  $vendasQtde     = (int)($v['qtde'] ?? 0);
  $faturamento30d = (float)($v['total'] ?? 0);
} catch (Throwable $e) {
  // noop
}

try {
  // Itens em estoque (contagem de produtos ativos)
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtos_peca WHERE empresa_cnpj = :c AND ativo = 1");
  $st->execute([':c' => $empresaCnpj]);
  $itensEstoque = (int)$st->fetchColumn();
} catch (Throwable $e) {
  // noop
}

// despesas30d permanece 0.00 até existir uma tabela de despesas

// ==== Metas para os círculos (ajuste conforme sua realidade) ====
$vendasMeta      = 350.0;     // unidades
$estoqueMeta     = 3210.0;    // itens
$faturamentoMeta = 7400.00;   // R$
$despesasMeta    = 1250.00;   // R$

$__pct = static function (float $valor, float $meta): int {
  if ($meta <= 0) return 0;
  $p = (int)round(($valor / $meta) * 100);
  return max(0, min(100, $p));
};

// Percentuais dos cards
$vendasPct      = $__pct((float)$vendasQtde,     (float)$vendasMeta);
$estoquePct     = $__pct((float)$itensEstoque,   (float)$estoqueMeta);
$faturamentoPct = $__pct((float)$faturamento30d, (float)$faturamentoMeta);
$despesasPct    = $__pct((float)$despesas30d,    (float)$despesasMeta);

// ==== Gráfico: Vendas por mês (últimos 6 meses, mês atual incluso) ====

// Janela de agregação
$ini6 = (new DateTime('first day of -5 months 00:00:00'));
$fim6 = (new DateTime('last day of this month 23:59:59'));

$iniIso = $ini6->format('Y-m-d H:i:s');
$fimIso = $fim6->format('Y-m-d H:i:s');

// Labels (mês/ano) do mais antigo -> atual
$labels = [];
$it = clone $ini6;
while ($it <= $fim6) {
  $labels[] = $it->format('m/Y');
  $it->modify('first day of next month');
}
$vals = array_fill(0, count($labels), 0.0);

// Soma das vendas fechadas por mês dentro do intervalo
try {
  $st = $pdo->prepare("
    SELECT DATE_FORMAT(criado_em, '%m/%Y') AS mes, COALESCE(SUM(total_liquido),0) AS total
      FROM vendas_peca
     WHERE empresa_cnpj = :c
       AND LOWER(status) = 'fechada'
       AND criado_em BETWEEN :ini AND :fim
     GROUP BY YEAR(criado_em), MONTH(criado_em)
     ORDER BY YEAR(criado_em), MONTH(criado_em)
  ");
  $st->execute([':c' => $empresaCnpj, ':ini' => $iniIso, ':fim' => $fimIso]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $mes = (string)$r['mes'];
    $idx = array_search($mes, $labels, true);
    if ($idx !== false) $vals[$idx] = (float)$r['total'];
  }
} catch (Throwable $e) {
  // noop
}

$chartLabels = $labels;
$chartSeries = $vals;

// ==== Lavagens recentes (10) - apenas se a tabela existir no seu schema ====
try {
  // Se a tabela não existir, esta consulta vai falhar — por isso o try/catch.
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
  // noop (mantém $lavagens = [])
}
