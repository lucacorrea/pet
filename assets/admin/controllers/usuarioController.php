<?php
// autoErp/admin/controllers/usuariosController.php
// Prepara filtros, totais e lista para admin/pages/usuarios.php
declare(strict_types=1);

/* ===== Conexão ($pdo) ===== */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
  if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao; // deve definir $pdo
  } else {
    throw new RuntimeException('Conexão indisponível.');
  }
}

/* ===== Filtros ===== */
$status = strtolower(trim((string)($_GET['status'] ?? 'ativos'))); // ativos | inativos | todos
if (!in_array($status, ['ativos', 'inativos', 'todos'], true)) $status = 'ativos';

$rotuloStatus = [
  'ativos'   => 'Ativos',
  'inativos' => 'Inativos',
  'todos'    => 'Todos',
][$status];

$buscar      = trim((string)($_GET['q'] ?? ''));
$cnpjFilter  = preg_replace('/\D+/', '', (string)($_GET['cnpj'] ?? ''));

// Perfis extras opcionais p/ filtros manuais
$perfilFilter = strtolower(trim((string)($_GET['perfil'] ?? 'todos'))); // super_admin | dono | funcionario | todos
if (!in_array($perfilFilter, ['super_admin','dono','funcionario','todos'], true)) {
  $perfilFilter = 'todos';
}
$tipoFuncFilter = strtolower(trim((string)($_GET['tipo'] ?? 'todos'))); // caixa | estoque | administrativo | lavajato | todos
if (!in_array($tipoFuncFilter, ['caixa','estoque','administrativo','lavajato','todos'], true)) {
  $tipoFuncFilter = 'todos';
}

$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

/* ===== Função que monta a cláusula de PERFIL (inclui DONO) =====
   Regras:
   - Se houver filtro por CNPJ e perfil=todos => inclui Dono + Funcionários (exclui super_admin)
   - Se perfil=funcionario => inclui Dono + Funcionários (pedido do usuário)
   - Se perfil=dono ou super_admin => filtra estritamente por esse perfil
   - Sem CNPJ e perfil=todos => mostra todos, exceto super_admin (padrão mais útil)
*/
function perfilClause(string $perfilFilter, string $cnpjFilter, array &$params, string $alias = 'u'): string {
  if ($perfilFilter === 'dono' || $perfilFilter === 'super_admin') {
    $params[':perfil'] = $perfilFilter;
    return "$alias.perfil = :perfil";
  }
  if ($perfilFilter === 'funcionario') {
    return "$alias.perfil IN ('dono','funcionario')";
  }
  // perfil=todos
  if ($cnpjFilter !== '') {
    // Quando focado em uma empresa, traga Dono + Funcionários
    return "$alias.perfil IN ('dono','funcionario')";
  }
  // Sem CNPJ específico, esconda super_admin por padrão
  return "$alias.perfil <> 'super_admin'";
}

/* ===== WHERE dinâmico para a LISTA ===== */
$where = [];
$params = [];

// status
if ($status === 'ativos')   $where[] = 'u.status = 1';
if ($status === 'inativos') $where[] = 'u.status = 0';

// empresa
if ($cnpjFilter !== '') {
  $where[] = 'u.empresa_cnpj = :cnpj';
  $params[':cnpj'] = $cnpjFilter;
}

// perfil (inclui DONO conforme regra)
$where[] = perfilClause($perfilFilter, $cnpjFilter, $params, 'u');

// tipo funcionario
if ($tipoFuncFilter !== 'todos') {
  $where[] = 'u.tipo_funcionario = :tipo';
  $params[':tipo'] = $tipoFuncFilter;
}

// busca livre (nome, email, cpf, nome empresa, cnpj)
if ($buscar !== '') {
  $like   = '%' . $buscar . '%';
  $cpfLike= '%' . preg_replace('/\D+/', '', $buscar) . '%';
  $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR REPLACE(REPLACE(REPLACE(u.cpf,".",""),"-",""),"/","") LIKE :cpf OR e.nome_fantasia LIKE :q OR u.empresa_cnpj LIKE :q)';
  $params[':q']   = $like;
  $params[':cpf'] = $cpfLike;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ===== Totais por status (usar as MESMAS regras de perfil/busca) ===== */
$baseWhere = [];
$baseParams = [];

// empresa
if ($cnpjFilter !== '') {
  $baseWhere[] = 'u.empresa_cnpj = :cnpj';
  $baseParams[':cnpj'] = $cnpjFilter;
}
// perfil (inclui DONO)
$baseWhere[] = perfilClause($perfilFilter, $cnpjFilter, $baseParams, 'u');

// tipo funcionario
if ($tipoFuncFilter !== 'todos') {
  $baseWhere[] = 'u.tipo_funcionario = :tipo';
  $baseParams[':tipo'] = $tipoFuncFilter;
}
// busca
if ($buscar !== '') {
  $baseWhere[] = '(u.nome LIKE :q OR u.email LIKE :q OR REPLACE(REPLACE(REPLACE(u.cpf,".",""),"-",""),"/","") LIKE :cpf OR e.nome_fantasia LIKE :q OR u.empresa_cnpj LIKE :q)';
  $baseParams[':q']   = '%' . $buscar . '%';
  $baseParams[':cpf'] = '%' . preg_replace('/\D+/', '', $buscar) . '%';
}

$baseWhereSql = $baseWhere ? ('WHERE ' . implode(' AND ', $baseWhere)) : '';

$totais = ['ativos'=>0, 'inativos'=>0, 'todos'=>0];
try {
  // total (todos)
  $sqlTot = "SELECT COUNT(*) 
               FROM usuarios_peca u
          LEFT JOIN empresas_peca e ON e.cnpj = u.empresa_cnpj
              $baseWhereSql";
  $st = $pdo->prepare($sqlTot);
  $st->execute($baseParams);
  $totais['todos'] = (int)$st->fetchColumn();

  // ativos
  $sqlAt = $sqlTot . ($baseWhereSql ? ' AND ' : ' WHERE ') . 'u.status = 1';
  $st = $pdo->prepare($sqlAt);
  $st->execute($baseParams);
  $totais['ativos'] = (int)$st->fetchColumn();

  // inativos
  $sqlIn = $sqlTot . ($baseWhereSql ? ' AND ' : ' WHERE ') . 'u.status = 0';
  $st = $pdo->prepare($sqlIn);
  $st->execute($baseParams);
  $totais['inativos'] = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $totais = ['ativos'=>0, 'inativos'=>0, 'todos'=>0];
}

/* ===== Total da listagem corrente (para paginação) ===== */
$totalRows = 0;
try {
  $sqlCount = "
    SELECT COUNT(*)
      FROM usuarios_peca u
 LEFT JOIN empresas_peca e ON e.cnpj = u.empresa_cnpj
    $whereSql
  ";
  $stc = $pdo->prepare($sqlCount);
  $stc->execute($params);
  $totalRows = (int)$stc->fetchColumn();
} catch (Throwable $e) {
  $totalRows = 0;
}
$pages = max(1, (int)ceil($totalRows / $limit));

/* ===== Lista paginada ===== */
$usuarios = [];
try {
  $sqlList = "
    SELECT
      u.id, u.nome, u.email, u.cpf, u.perfil, u.tipo_funcionario, u.status, u.criado_em,
      u.empresa_cnpj, e.nome_fantasia AS empresa_nome
    FROM usuarios_peca u
    LEFT JOIN empresas_peca e ON e.cnpj = u.empresa_cnpj
    $whereSql
    ORDER BY u.criado_em DESC, u.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sqlList);
  foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
  }
  $st->bindValue(':lim', $limit, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $usuarios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $usuarios = [];
}

// Exporta a página atual
$page = $page;
