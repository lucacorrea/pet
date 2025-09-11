<?php
// autoErp/actions/logout.php
declare(strict_types=1);

// Inicia sessão, se necessário
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Limpa dados de sessão
$_SESSION = [];

// Invalida o cookie de sessão
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    // Compatível com PHP 7.3+
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'] ?? '/',
        'domain'   => $p['domain'] ?? '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

// Destrói a sessão
session_destroy();

// Evita voltar com o botão “voltar” para páginas protegidas
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Descobre URL do login a partir do auth_guard.php (se disponível)
$loginUrl = '../index.php';
$guard = realpath(__DIR__ . '/../lib/auth_guard.php');
if ($guard !== false) {
    require_once $guard;
    if (defined('LOGIN_URL')) {
        $loginUrl = LOGIN_URL;
    }
}

// Redireciona com flag de logout
$sep = (strpos($loginUrl, '?') === false) ? '?' : '&';
header('Location: ' . $loginUrl . $sep . 'logout=1');
exit;
