<?php
// autoErp/public/configuracao/controllers/usuariosEmpresaController.php
declare(strict_types=1);

/**
 * Prepara a lista paginada de FUNCIONÁRIOS da EMPRESA logada.
 * Regras:
 * - Escopo por CNPJ da sessão
 * - Exibe somente perfil = 'funcionario'
 * - Filtros: status (ativos|inativos|todos), tipo (administrativo|caixa|estoque|lavajato|todos), busca livre
 * - Paginação: 20 por página
 *
 * Retorna array com:
 *   usuarios[], totais[], status, tipo, buscar, page, pages, empresaNome, canCreate, csrf
 */
function usuarios_empresa_viewmodel(PDO $pdo): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    require_once __DIR__ . '/../../../lib/auth_guard.php';
    // Dono e funcionários (exceto lavador) podem ver
    guard_empresa_user(['dono','administrativo','caixa','estoque']);

    // CSRF para ações futuras (reset, reenviar convite, etc.)
    if (empty($_SESSION['csrf_cfg_user_list'])) {
        $_SESSION['csrf_cfg_user_list'] = bin2hex(random_bytes(32));
    }

    // CNPJ da sessão
    $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if (!preg_match('/^\d{14}$/', $cnpj)) {
        throw new RuntimeException('Empresa não vinculada ao usuário.');
    }

    // Nome da empresa (pode estar vazio se não configurado ainda)
    $stE = $pdo->prepare("SELECT COALESCE(NULLIF(nome_fantasia,''), 'Sua empresa') AS empresa_nome, status FROM empresas_peca WHERE cnpj = :c LIMIT 1");
    $stE->execute([':c' => $cnpj]);
    $emp = $stE->fetch(PDO::FETCH_ASSOC) ?: ['empresa_nome' => 'Sua empresa', 'status' => 'ativa'];
    $empresaNome = (string)($emp['empresa_nome'] ?? 'Sua empresa');

    // Filtros
    $status = strtolower(trim((string)($_GET['status'] ?? 'ativos')));    // ativos|inativos|todos
    if (!in_array($status, ['ativos','inativos','todos'], true)) $status = 'ativos';

    $tipo = strtolower(trim((string)($_GET['tipo'] ?? 'todos')));         // administrativo|caixa|estoque|lavajato|todos
    if (!in_array($tipo, ['administrativo','caixa','estoque','lavajato','todos'], true)) $tipo = 'todos';

    $buscar = trim((string)($_GET['q'] ?? ''));

    $page  = max(1, (int)($_GET['p'] ?? 1));
    $limit = 20;
    $off   = ($page - 1) * $limit;

    // WHERE base (empresa + perfil funcionario)
    $where  = ["u.empresa_cnpj = :cnpj", "u.perfil = 'funcionario'"];
    $params = [':cnpj' => $cnpj];

    // status
    if ($status === 'ativos')   $where[] = 'u.status = 1';
    if ($status === 'inativos') $where[] = 'u.status = 0';

    // tipo
    if ($tipo !== 'todos') {
        $where[] = 'u.tipo_funcionario = :tipo';
        $params[':tipo'] = $tipo;
    }

    // busca (nome, email, cpf)
    if ($buscar !== '') {
        $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR REPLACE(REPLACE(REPLACE(u.cpf,".",""),"-",""),"/","") LIKE :cpf)';
        $params[':q']   = '%'.$buscar.'%';
        $params[':cpf'] = '%'.preg_replace('/\D+/', '', $buscar).'%';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Totais (respeitam todos os filtros, exceto o próprio status)
    $baseWhere  = ["u.empresa_cnpj = :cnpj", "u.perfil = 'funcionario'"];
    $baseParams = [':cnpj' => $cnpj];
    if ($tipo !== 'todos') {
        $baseWhere[] = 'u.tipo_funcionario = :tipo';
        $baseParams[':tipo'] = $tipo;
    }
    if ($buscar !== '') {
        $baseWhere[] = '(u.nome LIKE :q OR u.email LIKE :q OR REPLACE(REPLACE(REPLACE(u.cpf,".",""),"-",""),"/","") LIKE :cpf)';
        $baseParams[':q']   = '%'.$buscar.'%';
        $baseParams[':cpf'] = '%'.preg_replace('/\D+/', '', $buscar).'%';
    }
    $baseSql = 'WHERE ' . implode(' AND ', $baseWhere);

    $totais = ['todos'=>0,'ativos'=>0,'inativos'=>0];
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_peca u $baseSql");
        $st->execute($baseParams);
        $totais['todos'] = (int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_peca u $baseSql AND u.status = 1");
        $st->execute($baseParams);
        $totais['ativos'] = (int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_peca u $baseSql AND u.status = 0");
        $st->execute($baseParams);
        $totais['inativos'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        // mantém zero
    }

    // Total corrente (para paginação)
    $totalRows = 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_peca u $whereSql");
        $st->execute($params);
        $totalRows = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $totalRows = 0;
    }
    $pages = max(1, (int)ceil($totalRows / $limit));

    // Lista
    $usuarios = [];
    try {
        $sql = "
          SELECT u.id, u.nome, u.email, u.cpf, u.tipo_funcionario, u.status, u.criado_em
            FROM usuarios_peca u
          $whereSql
          ORDER BY u.nome ASC, u.id DESC
          LIMIT :lim OFFSET :off
        ";
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $off,   PDO::PARAM_INT);
        $st->execute();
        $usuarios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $usuarios = [];
    }

    $perfilSess = (string)($_SESSION['user_perfil'] ?? '');
    $canCreate  = ($perfilSess === 'dono'); // só o dono pode cadastrar

    return [
        'empresaNome' => $empresaNome,
        'usuarios'    => $usuarios,
        'totais'      => $totais,
        'status'      => $status,
        'tipo'        => $tipo,
        'buscar'      => $buscar,
        'page'        => $page,
        'pages'       => $pages,
        'canCreate'   => $canCreate,
        'csrf'        => $_SESSION['csrf_cfg_user_list'],
    ];
}
