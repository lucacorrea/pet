<?php
// autoErp/lib/util.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retorna o nome da empresa da sessão ou do banco.
 *
 * @param PDO|null $pdo Opcional: conexão PDO para buscar no banco se não estiver em sessão.
 * @return string Nome fantasia da empresa ou "Empresa não definida".
 */
function empresa_nome_logada(?PDO $pdo = null): string
{
    // 1. Primeiro tenta da sessão
    if (!empty($_SESSION['empresa_nome'])) {
        return (string)$_SESSION['empresa_nome'];
    }

    // 2. Tenta buscar pelo CNPJ salvo na sessão
    $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if ($pdo && preg_match('/^\d{14}$/', $cnpj)) {
        try {
            $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE cnpj = :c LIMIT 1");
            $st->execute([':c' => $cnpj]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome_fantasia'])) {
                $_SESSION['empresa_nome'] = $row['nome_fantasia']; // cache na sessão
                return (string)$row['nome_fantasia'];
            }
        } catch (Throwable $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("empresa_nome_logada erro: " . $e->getMessage());
            }
        }
    }

    return "Empresa não definida";
}
