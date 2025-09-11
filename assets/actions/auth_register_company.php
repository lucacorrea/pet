<?php
// autoErp/actions/AuthRegisterCompany.php
// Registro de solicitação de empresa (somente dados do DONO)
// Obs.: 'nome_fantasia' NÃO é definido aqui — ficará em branco e será cadastrado depois em empresa.php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Aceita apenas POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../criarConta.php'); exit;
}

// CSRF
$csrfForm = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_register_company']) || !hash_equals($_SESSION['csrf_register_company'], $csrfForm)) {
    header('Location: ../criarConta.php?err=1&msg=' . urlencode('Falha de segurança. Atualize a página.')); exit;
}

// Honeypot (anti-bot)
if (!empty($_POST['website'] ?? '')) {
    header('Location: ../criarConta.php?ok=1'); exit;
}

/* ========= Conexão ========= */
require_once __DIR__ . '/../conexao/conexao.php';

// Campos (apenas do usuário/dono e o CNPJ)
$nome   = trim((string)($_POST['proprietario_nome']  ?? ''));
$email  = strtolower(trim((string)($_POST['proprietario_email'] ?? '')));
$cnpj   = preg_replace('/\D+/', '', (string)($_POST['cnpj'] ?? ''));
$aceite = isset($_POST['aceite']) ? 1 : 0;

$senha1 = (string)($_POST['proprietario_senha']  ?? '');
$senha2 = (string)($_POST['proprietario_senha2'] ?? '');

// Validações
if (
    !$aceite ||
    strlen($nome) < 3 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !preg_match('/^\d{14}$/', $cnpj)
) {
    header('Location: ../criarConta.php?err=1&msg=' . urlencode('Preencha os dados corretamente.')); exit;
}
if ($senha1 === '' || $senha2 === '' || $senha1 !== $senha2 || strlen($senha1) < 8) {
    header('Location: ../criarConta.php?err=1&msg=' . urlencode('Senha inválida ou não confere.')); exit;
}
$hash = password_hash($senha1, PASSWORD_DEFAULT);

/* ========= E-mail helper ========= */
function base_url_auth(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Path base do projeto (autoErp)
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    if ($base === '.' || $base === '/') $base = '';
    return rtrim($scheme . '://' . $host . $base, '/');
}

function fmt_cnpj(string $n): string {
    $n = preg_replace('/\D+/', '', $n);
    if (strlen($n) !== 14) return $n;
    return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
}

/* ========= Tenta carregar config de e-mail (opcional) ========= */
$enviarEmailDisponivel = false;
$pathMailCandidates = [
    realpath(__DIR__ . '/../config/mail.php'),
    realpath(__DIR__ . '/../../config/mail.php'),
];
foreach ($pathMailCandidates as $pm) {
    if ($pm && file_exists($pm)) {
        require_once $pm; // deve definir enviar_email($to,$assunto,$html,$fromOptional?)
        if (function_exists('enviar_email')) {
            $enviarEmailDisponivel = true;
        }
        break;
    }
}

/* ========= Verifica coluna proprietario_senha_hash (migrações antigas) ========= */
$temColSenha = false;
try {
    $pdo->query("SELECT proprietario_senha_hash FROM solicitacoes_empresas_peca LIMIT 0");
    $temColSenha = true;
} catch (Throwable $e) {
    $temColSenha = false;
}

try {
    // Evita duplicar muito: se já tem pendente com mesmo email+cnpj, só avisa OK
    $st = $pdo->prepare("
        SELECT id
          FROM solicitacoes_empresas_peca
         WHERE status = 'pendente'
           AND proprietario_email = :pe
           AND (cnpj = :c OR cnpj IS NULL)
         ORDER BY id DESC
         LIMIT 1
    ");
    $st->execute([':pe' => $email, ':c' => $cnpj]);
    $ja = $st->fetch();

    if ($ja) {
        // Já existe pendente — não envia e-mail novamente para evitar spam
        header('Location: ../criarConta.php?ok=1&msg=' . urlencode('Sua solicitação já está pendente de aprovação.')); exit;
    }

    // IMPORTANTE: nome_fantasia vazio (''), pois será cadastrado depois em empresa.php
    $nomeFantasiaVazio = '';

    if ($temColSenha) {
        $ins = $pdo->prepare("
            INSERT INTO solicitacoes_empresas_peca
                (nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, proprietario_senha_hash, status, token_aprovacao, criado_em)
            VALUES
                (:nf, :cnpj, NULL, NULL, :pn, :pe, :ph, 'pendente', NULL, NOW())
        ");
        $ins->execute([
            ':nf'   => $nomeFantasiaVazio,
            ':cnpj' => $cnpj,
            ':pn'   => $nome,
            ':pe'   => $email,
            ':ph'   => $hash,
        ]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO solicitacoes_empresas_peca
                (nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, status, token_aprovacao, criado_em)
            VALUES
                (:nf, :cnpj, NULL, NULL, :pn, :pe, 'pendente', NULL, NOW())
        ");
        $ins->execute([
            ':nf'   => $nomeFantasiaVazio,
            ':cnpj' => $cnpj,
            ':pn'   => $nome,
            ':pe'   => $email,
        ]);
    }

    // ===== Envia e-mail para o administrador avisando da NOVA solicitação =====
    $solicitacaoId = (int)$pdo->lastInsertId();
    $adminEmail    = 'suportelucacorrea@gmail.com';
    $agoraBr       = date('d/m/Y H:i');
    $ip            = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    $ua            = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';

    $base    = base_url_auth();
    // Link para a lista de solicitações no admin (ajuste o caminho se necessário)
    $linkAdm = $base . '/../admin/pages/solicitacao.php?status=pendente';

    $assunto = 'Nova solicitação de empresa — #' . $solicitacaoId;
    $htmlMsg = '
      <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;background:#ffffff;padding:16px">
        <div style="max-width:700px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
          <div style="background:#0ea5e9;color:#fff;padding:14px 18px">
            <h2 style="margin:0;font-size:18px;font-weight:700;">AutoERP — Nova solicitação de empresa</h2>
          </div>
          <div style="padding:18px">
            <p style="margin:0 0 10px">Uma nova solicitação de cadastro foi enviada.</p>
            <ul style="margin:0 0 12px;padding-left:18px">
              <li><strong>ID:</strong> ' . htmlspecialchars((string)$solicitacaoId, ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>Proprietário:</strong> ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>CNPJ:</strong> ' . htmlspecialchars(fmt_cnpj($cnpj), ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>Data/Hora:</strong> ' . htmlspecialchars($agoraBr, ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>IP:</strong> ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</li>
              <li><strong>Navegador:</strong> ' . htmlspecialchars($ua, ENT_QUOTES, 'UTF-8') . '</li>
            </ul>
            <p style="margin:10px 0 16px">
              <a href="' . htmlspecialchars($linkAdm, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;padding:10px 14px;border-radius:6px;font-weight:600">
                Abrir solicitações pendentes
              </a>
            </p>
            <p style="margin:0;color:#64748b;font-size:12px">Este e-mail foi enviado automaticamente pelo AutoERP.</p>
          </div>
        </div>
      </div>
    ';

    // Preferência: função enviar_email() de config/mail.php
    if ($enviarEmailDisponivel) {
        @enviar_email($adminEmail, $assunto, $htmlMsg);
    } else {
        // Fallback simples usando mail()
        $from   = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: AutoERP <{$from}>\r\n";
        @mail($adminEmail, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $htmlMsg, $headers);
    }

    // Redireciona com sucesso
    header('Location: ../criarConta.php?ok=1'); exit;

} catch (Throwable $e) {
    // Não vaza erro sensível
    header('Location: ../criarConta.php?err=1&msg=' . urlencode('Erro ao salvar sua solicitação.')); exit;
}
