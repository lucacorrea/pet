<?php
// autoErp/actions/auth_ResetRequest.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Aceita apenas POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../confirmaEmail.php'); exit;
}

// CSRF
$csrfForm = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_reset']) || !hash_equals($_SESSION['csrf_reset'], $csrfForm)) {
    header('Location: ../confirmaEmail.php?err=1&msg=' . urlencode('Falha de segurança. Atualize a página.')); exit;
}

// Honeypot (anti-bot)
if (!empty($_POST['website'] ?? '')) {
    header('Location: ../confirmaEmail.php?ok=1'); exit;
}

require_once __DIR__ . '/../conexao/conexao.php';
require_once __DIR__ . '/../config/mail.php';

$email = strtolower(trim($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../confirmaEmail.php?err=1&msg=' . urlencode('Informe um e-mail válido.')); exit;
}

try {
    // 1) Verifica se o e-mail existe e está ativo
    $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE email = :e AND status = 1 LIMIT 1");
    $st->execute([':e' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // NÃO envia nada se o e-mail não existir/inativo
        header('Location: ../confirmaEmail.php?err=1&msg=' . urlencode('E-mail não encontrado ou inativo.')); exit;
    }

    $usuarioId = (int)$user['id'];

    // 2) Rate limit simples (60s)
    $st = $pdo->prepare("
        SELECT 1
          FROM usuarios_redefinicao_senha_peca
         WHERE email = :e
           AND criado_em >= (NOW() - INTERVAL 60 SECOND)
         LIMIT 1
    ");
    $st->execute([':e' => $email]);
    $recent = (bool)$st->fetchColumn();

    if (!$recent) {
        // 3) Gera OTP + token e grava solicitação
        $otp        = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token      = bin2hex(random_bytes(32)); // 64 chars
        $expiracao  = date('Y-m-d H:i:s', time() + 15 * 60);

        $ip = $_SERVER['REMOTE_ADDR']     ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $ins = $pdo->prepare("
            INSERT INTO usuarios_redefinicao_senha_peca
                (usuario_id, email, token, otp, expiracao, ip_solicitante, user_agent, criado_em)
            VALUES
                (:uid, :email, :token, :otp, :exp, :ip, :ua, NOW())
        ");
        $ins->execute([
            ':uid'   => $usuarioId,
            ':email' => $email,
            ':token' => $token,
            ':otp'   => $otp,
            ':exp'   => $expiracao,
            ':ip'    => $ip,
            ':ua'    => $ua,
        ]);

        // 4) E-mail
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
              . ($_SERVER['HTTP_HOST'] ?? '');
        $path = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/');
        $link = $base . $path . '/../confirmarCodigo.php?token=' . urlencode($token) . '&email=' . urlencode($email);

        $assunto = 'Código de confirmação - AutoERP';
        $html = '
            <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#222">
              <p>Olá,</p>
              <p>Use o código abaixo para redefinir sua senha no <strong>AutoERP</strong>:</p>
              <p style="font-size:26px;font-weight:bold;letter-spacing:4px;margin:16px 0;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>
              <p>Ou clique no link: <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Confirmar redefinição</a></p>
              <p>Validade: <strong>15 minutos</strong>.</p>
              <p>Se você não solicitou, ignore este e-mail.</p>
              <hr>
              <small>AutoERP</small>
            </div>
        ';

        enviar_email($email, $assunto, $html);
    }

    // 5) Vai para a tela de confirmação (com o e-mail preenchido)
    header('Location: ../confirmarCodigo.php?email=' . urlencode($email) . '&sent=1'); exit;

} catch (Throwable $e) {
    header('Location: ../confirmaEmail.php?err=1&msg=' . urlencode('Falha ao processar a solicitação.')); exit;
}
