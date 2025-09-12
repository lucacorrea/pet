<?php
// auto/lib/auth_guard.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/** Idle 3h, absoluto 4h */
function _check_session_lifetime(): void {
    $now = time();
    $created = (int)($_SESSION['created_at'] ?? 0);
    $last    = (int)($_SESSION['last_seen']  ?? 0);
    if ($created && ($now - $created) > 4*3600) {
        session_unset(); session_destroy();
        header('Location: /assets/index.php?erro=1&msg=' . urlencode('Sessão expirada.')); exit;
    }
    if ($last && ($now - $last) > 3*3600) {
        session_unset(); session_destroy();
        header('Location: /assets/index.php?erro=1&msg=' . urlencode('Sessão inativa.')); exit;
    }
    $_SESSION['last_seen'] = $now;
}

function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Location: /assets/index.php?erro=1'); exit;
    }
}

function ensure_logged_in(array $allowedProfiles = []): void {
    _check_session_lifetime();
    $uid    = (int)($_SESSION['user_id'] ?? 0);
    $perfil = (string)($_SESSION['user_perfil'] ?? '');
    if ($uid <= 0 || $perfil === '') {
        header('Location: /assets/index.php?erro=1&msg=' . urlencode('Faça login.')); exit;
    }
    if ($allowedProfiles && !in_array($perfil, $allowedProfiles, true)) {
        // redireciona para o “home” de cada papel
        if ($perfil === 'super_admin') {
            header('Location: /assets/admin/dashboard.php'); exit;
        } else {
            header('Location: /assets/public/dashboard.php'); exit;
        }
    }
}

function guard_super_admin(): void {
    ensure_logged_in(['super_admin']);
}

function guard_empresa_user(array $allowedTypes = ['dono','administrativo','funcionario']): void {
    ensure_logged_in(['dono','funcionario']);
    $perfil = (string)($_SESSION['user_perfil'] ?? '');
    $tipo   = (string)($_SESSION['user_tipo_func'] ?? '');

    // bloqueia lavador
    if ($perfil === 'funcionario' && $tipo === 'lavajato') {
        header('Location: /assets/index.php?erro=1&msg=' . urlencode('Acesso não permitido para lavador.')); exit;
    }
    // checa tipo permitido
    if ($perfil === 'dono') {
        // ok
    } elseif ($perfil === 'funcionario' && !in_array($tipo, $allowedTypes, true)) {
        header('Location: /assets/public/dashboard.php?erro=1&msg=' . urlencode('Permissão insuficiente.')); exit;
    }

    // empresa exigida
    $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if (strlen($cnpj) !== 14) {
        header('Location: /assets/index.php?erro=1&msg=' . urlencode('Empresa não vinculada.')); exit;
    }

    // expõe constantes de conveniência
    if (!defined('CURRENT_CNPJ')) define('CURRENT_CNPJ', $cnpj);
    if (!defined('CURRENT_CPF'))  define('CURRENT_CPF', preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? '')));
}
