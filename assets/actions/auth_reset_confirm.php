<?php
// autoErp/actions/auth_ResetConfirm.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Aceita apenas POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../confirmaEmail.php'); exit;
}

// (opcional) CSRF se você gerou um csrf_confirm no formulário
$csrfForm = $_POST['csrf'] ?? '';
if (!empty($_SESSION['csrf_confirm']) && !hash_equals($_SESSION['csrf_confirm'], $csrfForm)) {
    header('Location: ../confirmarCodigo.php?err=1&msg=' . urlencode('Falha de segurança. Atualize a página.')); exit;
}

require_once __DIR__ . '/../conexao/conexao.php';

// Coleta e valida entradas
$email   = strtolower(trim($_POST['email'] ?? ''));
$otp     = preg_replace('/\D+/', '', $_POST['code'] ?? '');       // 6 dígitos
$token   = trim($_POST['token'] ?? '');                           // opcional, mas preferível
$senha1  = (string)($_POST['senha1'] ?? '');
$senha2  = (string)($_POST['senha2'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('E-mail inválido.')); exit;
}
if (strlen($otp) !== 6) {
    header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Código inválido.')); exit;
}
if ($senha1 === '' || $senha2 === '' || $senha1 !== $senha2) {
    header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('As senhas não conferem.')); exit;
}

try {
    // 1) Busca o registro de redefinição mais recente (preferindo o token, se informado)
    if ($token !== '') {
        $st = $pdo->prepare("
            SELECT id, otp, expiracao, usado_em
              FROM usuarios_redefinicao_senha_peca
             WHERE email = :e AND token = :t
             ORDER BY id DESC
             LIMIT 1
        ");
        $st->execute([':e' => $email, ':t' => $token]);
    } else {
        $st = $pdo->prepare("
            SELECT id, otp, expiracao, usado_em
              FROM usuarios_redefinicao_senha_peca
             WHERE email = :e
             ORDER BY id DESC
             LIMIT 1
        ");
        $st->execute([':e' => $email]);
    }
    $reset = $st->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Solicitação de redefinição não encontrada.')); exit;
    }

    // 2) Verifica expiração/uso
    if (!empty($reset['usado_em']) || strtotime($reset['expiracao']) < time()) {
        header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Código expirado ou já utilizado.')); exit;
    }

    // 3) Confere OTP
    if (!hash_equals((string)$reset['otp'], (string)$otp)) {
        header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Código incorreto.')); exit;
    }

    // 4) Atualiza senha do usuário ativo
    $hash = password_hash($senha1, PASSWORD_DEFAULT);
    $up = $pdo->prepare("
        UPDATE usuarios_peca
           SET senha = :s, precisa_redefinir_senha = 0, senha_atualizada_em = NOW()
         WHERE email = :e AND status = 1
         LIMIT 1
    ");
    $up->execute([':s' => $hash, ':e' => $email]);

    if ($up->rowCount() < 1) {
        header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Usuário não encontrado ou inativo.')); exit;
    }

    // 5) Marca o reset como usado
    $upd = $pdo->prepare("UPDATE usuarios_redefinicao_senha_peca SET usado_em = NOW() WHERE id = :id LIMIT 1");
    $upd->execute([':id' => (int)$reset['id']]);

    // Sucesso
    header('Location: ../confirmarCodigo.php?ok=1'); exit;

} catch (Throwable $e) {
    // Você pode logar $e->getMessage() no servidor
    header('Location: ../confirmarCodigo.php?err=1&email=' . urlencode($email) . '&msg=' . urlencode('Falha ao processar a solicitação.')); exit;
}
