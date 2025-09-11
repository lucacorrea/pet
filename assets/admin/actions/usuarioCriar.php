<?php
// autoErp/admin/actions/usuarioCriar.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin();   // exige estar logado como super_admin
require_post();        // só aceita POST

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ========= CSRF ========= */
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], $csrf)) {
  header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('Token inválido. Atualize a página.')); exit;
}

/* ========= Conexão (PDO) ========= */
$__pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
if ($__pathConexao && file_exists($__pathConexao)) {
  require_once $__pathConexao; // define $pdo
} else {
  header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('Conexão indisponível.')); exit;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('Falha ao inicializar PDO.')); exit;
}
/** @var PDO $pdo */

/* ========= Mail ========= */
$__pathMail = realpath(__DIR__ . '/../../config/mail.php');
if ($__pathMail && file_exists($__pathMail)) {
  require_once $__pathMail; // define enviar_email()
}

/* ========= Helpers ========= */
function norm_cnpj(string $c): string {
  return preg_replace('/\D+/', '', $c);
}
function norm_cpf(?string $c): string {
  return preg_replace('/\D+/', '', (string)$c);
}
function base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  // Se você tem a constante BASE no auth_guard, usa; senão tenta detectar
  $base   = defined('BASE') ? BASE : rtrim(dirname(dirname(dirname($_SERVER['REQUEST_URI'] ?? '/'))), '/');
  if ($base === '' || $base === '.') $base = '';
  return rtrim($scheme . '://' . $host . $base, '/');
}

/* ========= Entrada ========= */
$empresaCnpj = norm_cnpj($_POST['empresa_cnpj'] ?? '');
$nome        = trim((string)($_POST['nome'] ?? ''));
$email       = strtolower(trim((string)($_POST['email'] ?? '')));
$cpf         = norm_cpf($_POST['cpf'] ?? '');
$telefone    = trim((string)($_POST['telefone'] ?? ''));
$perfil      = strtolower(trim((string)($_POST['perfil'] ?? 'funcionario'))); // dono|funcionario
$tipo        = strtolower(trim((string)($_POST['tipo_funcionario'] ?? 'administrativo'))); // caixa|estoque|administrativo|lavajato

// Força tipo para Dono
if ($perfil === 'dono') {
  $tipo = 'administrativo';
}

/* ========= Validações ========= */
$erros = [];
if (!preg_match('/^\d{14}$/', $empresaCnpj)) $erros[] = 'Empresa inválida.';
if (strlen($nome) < 3)                        $erros[] = 'Informe o nome completo.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
if (!in_array($perfil, ['dono','funcionario'], true)) $erros[] = 'Perfil inválido.';
if (!in_array($tipo, ['administrativo','caixa','estoque','lavajato'], true)) $erros[] = 'Tipo de funcionário inválido.';
if ($cpf !== '' && !preg_match('/^\d{11}$/', $cpf)) $erros[] = 'CPF deve ter 11 dígitos numéricos.';

if ($erros) {
  header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode(implode(' ', $erros))); exit;
}

/* ========= Regras de negócio =========
   - empresa precisa existir e estar ATIVA
   - e-mail único (índice uq_usuarios_email)
   - (empresa_cnpj, cpf) único se cpf informado
*/
try {
  // Empresa ativa?
  $st = $pdo->prepare("SELECT nome_fantasia, status FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $st->execute([':c' => $empresaCnpj]);
  $emp = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emp || ($emp['status'] ?? '') !== 'ativa') {
    header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('Empresa inexistente ou inativa.')); exit;
  }

  // E-mail único
  $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE email = :e LIMIT 1");
  $st->execute([':e' => $email]);
  if ($st->fetch()) {
    header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('E-mail já cadastrado.')); exit;
  }

  // CPF único dentro da empresa (se informado)
  if ($cpf !== '') {
    $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE empresa_cnpj = :c AND cpf = :cpf LIMIT 1");
    $st->execute([':c' => $empresaCnpj, ':cpf' => $cpf]);
    if ($st->fetch()) {
      header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('CPF já cadastrado para esta empresa.')); exit;
    }
  }

  $pdo->beginTransaction();

  // Senha temporária aleatória (o usuário vai redefinir)
  $tmpPass = bin2hex(random_bytes(16));
  $hash    = password_hash($tmpPass, PASSWORD_DEFAULT);

  // Cria usuário
  $ins = $pdo->prepare("
    INSERT INTO usuarios_peca
      (empresa_cnpj, nome, email, cpf, telefone, senha, perfil, tipo_funcionario, status, precisa_redefinir_senha, criado_em)
    VALUES
      (:cnpj, :nome, :email, :cpf, :tel, :senha, :perfil, :tipo, 1, 1, NOW())
  ");
  $ins->execute([
    ':cnpj'  => $empresaCnpj,
    ':nome'  => $nome,
    ':email' => $email,
    ':cpf'   => ($cpf ?: null),
    ':tel'   => ($telefone ?: null),
    ':senha' => $hash,
    ':perfil'=> $perfil,
    ':tipo'  => $tipo,
  ]);
  $usuarioId = (int)$pdo->lastInsertId();

  // Gera token/OTP para criar senha (fluxo igual ao reset)
  $otp   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $token = bin2hex(random_bytes(32)); // 64 chars
  $exp   = date('Y-m-d H:i:s', time() + 15 * 60);

  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $insReset = $pdo->prepare("
    INSERT INTO usuarios_redefinicao_senha_peca
      (usuario_id, email, token, otp, expiracao, ip_solicitante, user_agent, criado_em)
    VALUES
      (:uid, :email, :token, :otp, :exp, :ip, :ua, NOW())
  ");
  $insReset->execute([
    ':uid'   => $usuarioId,
    ':email' => $email,
    ':token' => $token,
    ':otp'   => $otp,
    ':exp'   => $exp,
    ':ip'    => $ip,
    ':ua'    => $ua,
  ]);

  $pdo->commit();

  /* ========= E-mail de boas-vindas / criar senha ========= */
  $base     = base_url(); // ex.: https://seu-dominio.com/autoErp
  $confirm  = $base . '/confirmarCodigo.php?token=' . urlencode($token) . '&email=' . urlencode($email);

  $assunto = 'Bem-vindo ao AutoERP — Defina sua senha';
  $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;background:#ffffff;padding:16px">
      <div style="max-width:640px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
        <div style="background:#0ea5e9;color:#fff;padding:16px 20px">
          <h2 style="margin:0;font-size:20px;font-weight:700;">AutoERP</h2>
        </div>
        <div style="padding:20px">
          <p style="margin:0 0 12px">Olá <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
          <p style="margin:0 0 12px">Sua conta foi criada para a empresa <strong>' . htmlspecialchars($emp['nome_fantasia'] ?? '-', ENT_QUOTES, 'UTF-8') . '</strong>.</p>

          <p style="margin:0 0 8px">Use o código abaixo para definir sua senha:</p>
          <p style="font-size:26px;font-weight:bold;letter-spacing:4px;margin:12px 0 16px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>

          <p style="margin:0 0 8px">Ou clique diretamente no link:</p>
          <p style="margin:0 0 16px"><a href="' . htmlspecialchars($confirm, ENT_QUOTES, 'UTF-8') . '">Definir minha senha</a></p>

          <p style="margin:0 0 8px"><strong>Validade:</strong> 15 minutos.</p>
          <p style="margin:0;color:#64748b;font-size:12px">Se você não esperava este e-mail, pode ignorá-lo.</p>
        </div>
      </div>
    </div>
  ';

  if (function_exists('enviar_email')) {
    @enviar_email($email, $assunto, $html);
  }

  // Volta para a lista filtrada por empresa (melhor UX)
  header('Location: ../pages/usuarios.php?ok=1&msg=' . urlencode('Usuário criado e e-mail enviado.') . '&cnpj=' . urlencode($empresaCnpj)); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/cadastrarUsuario.php?err=1&msg=' . urlencode('Falha ao criar usuário.')); exit;
}
