<?php
// autoErp/lib/auth_guard.php
declare(strict_types=1);

/**
 * Ajuste BASE conforme a pasta pública do projeto.
 * Ex.: '/autoErp' ou '/auto'
 */
if (!defined('BASE')) {
    define('BASE', '/autoErp');
}
if (!defined('LOGIN_URL')) {
    define('LOGIN_URL', BASE . '/index.php');
}
if (!defined('DEFAULT_DASHBOARD')) {
    define('DEFAULT_DASHBOARD', BASE . '/public/dashboard.php');
}

/**
 * Inicializa a sessão com parâmetros seguros.
 * - Apenas cookies (sem id em URL)
 * - Cookie válido para todo o site (path = '/')
 * - HttpOnly / Secure (quando HTTPS) / SameSite=Lax
 * - Cache desativado em páginas protegidas
 */
function init_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Força apenas cookies e desabilita trans SID (id em URL)
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');

        // Segurança do cookie
        ini_set('session.cookie_httponly', '1');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($secure) {
            ini_set('session.cookie_secure', '1');
        }

        // Escopo do cookie para todo o site e SameSite=Lax
        session_set_cookie_params([
            'lifetime' => 0,          // expira ao fechar o navegador
            'path'     => '/',        // MUITO importante
            'domain'   => '',         // padrão (host atual)
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Registra momento de criação (para lifetime absoluto)
        $_SESSION['created_at'] = $_SESSION['created_at'] ?? time();
        // Marca momento do último regenerate
        $_SESSION['regen_at']   = $_SESSION['regen_at']   ?? time();
    }

    // Impede cache em páginas autenticadas (evita "voltar" mostrar conteúdo)
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Garante login e (opcionalmente) perfil permitido.
 * Redireciona para o login quando:
 *  - não autenticado,
 *  - sessão expirada por inatividade,
 *  - lifetime absoluto estourado,
 *  - assinatura de sessão inválida.
 */
function ensure_logged_in(array $allowed_profiles = []): void
{
    init_session();

    // 1) Autenticado?
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_perfil'])) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . LOGIN_URL . '?erro=1');
        exit;
    }

    $now = time();

    // 2) Timeout por inatividade (AJUSTE AQUI)
    $maxIdleSeconds = 4 * 60 * 60; // 4 horas
    $last = $_SESSION['last_seen'] ?? $now;
    if (($now - $last) > $maxIdleSeconds) {
        session_unset();
        session_destroy();
        header('Location: ' . LOGIN_URL . '?erro=1&msg=' . urlencode('Sessão expirada por inatividade'));
        exit;
    }
    $_SESSION['last_seen'] = $now;

    // 3) Lifetime absoluto opcional (ex.: 12h). Coloque 0 para desabilitar.
    $absoluteLife = 12 * 60 * 60; // 12 horas
    if ($absoluteLife > 0 && ($now - (int)($_SESSION['created_at'] ?? $now)) > $absoluteLife) {
        session_unset();
        session_destroy();
        header('Location: ' . LOGIN_URL . '?erro=1&msg=' . urlencode('Sessão expirada'));
        exit;
    }

    // 4) Assinatura estável da sessão (apenas User-Agent)
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120);
    $sig = hash('sha256', $ua);
    if (!isset($_SESSION['sig'])) {
        $_SESSION['sig'] = $sig;
    } elseif (!hash_equals($_SESSION['sig'], $sig)) {
        session_unset();
        session_destroy();
        header('Location: ' . LOGIN_URL . '?erro=1&msg=' . urlencode('Sessão inválida'));
        exit;
    }

    // 5) Regenera o ID de sessão periodicamente (anti fixation)
    $regenEvery = 15 * 60; // a cada 15 min
    if (($now - (int)($_SESSION['regen_at'] ?? 0)) >= $regenEvery) {
        session_regenerate_id(true);
        $_SESSION['regen_at'] = $now;
    }

    // 6) Restrições por perfil (se solicitado)
    if ($allowed_profiles && !in_array($_SESSION['user_perfil'], $allowed_profiles, true)) {
        header('Location: ' . DEFAULT_DASHBOARD);
        exit;
    }
}

/** Atalho: exige SUPER ADMIN */
function guard_super_admin(): void
{
    ensure_logged_in(['super_admin']);
}

/**
 * Atalho: exige usuário de empresa (dono ou funcionário)
 * $tipos: ['caixa','estoque','administrativo'] etc.
 */
function guard_empresa_user(array $tipos = []): void
{
    ensure_logged_in(['dono', 'funcionario']);

    if (empty($_SESSION['user_empresa_cnpj'])) {
        header('Location: ' . LOGIN_URL . '?erro=2&msg=' . urlencode('Usuário sem empresa vinculada.'));
        exit;
    }
    if ($tipos && !in_array($_SESSION['user_tipo_func'] ?? '', $tipos, true)) {
        header('Location: ' . DEFAULT_DASHBOARD);
        exit;
    }
}

/** Exige método POST em actions (evita executar por GET) */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Location: ' . DEFAULT_DASHBOARD);
        exit;
    }
}
