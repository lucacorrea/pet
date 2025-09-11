<?php
// autoErp/actions/authLogin.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Aceita apenas POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../index.php?erro=1'); exit;
}

// Conexão PDO
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao === false || !file_exists($pathConexao)) {
    header('Location: ../index.php?erro=2&msg=' . urlencode('Conexão indisponível')); exit;
}
require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: ../index.php?erro=2&msg=' . urlencode('Falha na conexão com o banco.')); exit;
}

// Entrada
$entrada = trim((string)($_POST['usuario'] ?? ''));
$senha   = (string)($_POST['senha'] ?? '');
if ($entrada === '' || $senha === '') {
    header('Location: ../index.php?erro=1'); exit;
}

// Normalizações
$asEmail = filter_var($entrada, FILTER_VALIDATE_EMAIL) ? strtolower($entrada) : '__no_email__';
$asCPF   = preg_replace('/\D+/', '', $entrada);
$asCPF   = (strlen($asCPF) === 11) ? $asCPF : '__no_cpf__';
$asNome  = $entrada;

// Busca usuário ativo
$sql = "SELECT id, empresa_cnpj, nome, email, cpf, senha, perfil, tipo_funcionario, status
          FROM usuarios_peca
         WHERE status = 1
           AND (email = :e OR cpf = :c OR nome = :n)
         LIMIT 1";

try {
    $st = $pdo->prepare($sql);
    $st->execute([':e' => $asEmail, ':c' => $asCPF, ':n' => $asNome]);
    $user = $st->fetch();

    if (!$user || !password_verify($senha, $user['senha'])) {
        header('Location: ../index.php?erro=1'); exit;
    }

    // Bloqueia lavador
    if (($user['perfil'] ?? '') === 'funcionario' && ($user['tipo_funcionario'] ?? '') === 'lavajato') {
        header('Location: ../index.php?erro=1&msg=' . urlencode('Acesso não permitido para lavador.')); exit;
    }

    // Para qualquer perfil != super_admin, empresa é obrigatória
    if (($user['perfil'] ?? '') !== 'super_admin') {
        $cnpj = preg_replace('/\D+/', '', (string)($user['empresa_cnpj'] ?? ''));
        if (strlen($cnpj) !== 14) {
            header('Location: ../index.php?erro=2&msg=' . urlencode('Usuário sem empresa vinculada.')); exit;
        }
    }

    // Sessão
    session_regenerate_id(true);
    $_SESSION['user_id']           = (int)$user['id'];
    $_SESSION['user_nome']         = (string)$user['nome'];
    $_SESSION['user_email']        = (string)$user['email'];
    $_SESSION['user_perfil']       = (string)$user['perfil'];            // super_admin | dono | funcionario
    $_SESSION['user_tipo_func']    = (string)($user['tipo_funcionario'] ?? ''); // caixa|estoque|administrativo|lavajato
    $_SESSION['user_empresa_cnpj'] = preg_replace('/\D+/', '', (string)($user['empresa_cnpj'] ?? '')); // '' para super_admin
    $_SESSION['user_cpf']          = preg_replace('/\D+/', '', (string)($user['cpf'] ?? ''));          // pode ficar vazio

    // Controle de expiração (3h idle, 4h absoluto)
    $_SESSION['created_at'] = time();
    $_SESSION['last_seen']  = time();

    // Redireciona
    if ($_SESSION['user_perfil'] === 'super_admin') {
        header('Location: ../admin/dashboard.php'); exit;
    }
    header('Location: ../public/dashboard.php'); exit;

} catch (Throwable $e) {
    header('Location: ../index.php?erro=2&msg=' . urlencode('Falha inesperada.')); exit;
}
