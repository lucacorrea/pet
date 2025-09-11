<?php
// autoErp/public/configuracao/actions/salvar.php
declare(strict_types=1);

/* ========= DEBUG (ligue só em desenvolvimento!) ========= */
const APP_DEBUG = true;

/* ========= Boot ========= */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post(); // só POST
// precisa estar logado; dono pode criar usuários
guard_empresa_user(['dono','administrativo','caixa','estoque']);

/* ========= Conexão ========= */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
  require_once $pathConexao; // $pdo
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  } else {
    die('Conexão indisponível.');
  }
}

/* ========= Mail ========= */
$pathMail = realpath(__DIR__ . '/../../../config/mail.php');
if ($pathMail && file_exists($pathMail)) {
  require_once $pathMail; // enviar_email($para, $assunto, $html, $de=null, $bcc=null)
}

/* ========= Utils ========= */
function norm_cnpj(string $c): string { return preg_replace('/\D+/', '', $c); }
function norm_cpf(?string $c): string { return preg_replace('/\D+/', '', (string)$c); }
function base_public_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  // /autoErp/public/configuracao/actions/salvar.php -> /autoErp/public
  $base   = dirname($_SERVER['REQUEST_URI'] ?? '/', 3);
  if ($base === DIRECTORY_SEPARATOR) $base = '';
  return rtrim($scheme . '://' . $host . $base, '/');
}
function app_log(string $msg): void {
  $file = __DIR__ . '/../../../storage/app.log';
  @file_put_contents($file, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
}
function back_to(string $path, array $qs): void {
  $q = http_build_query($qs);
  header('Location: ' . $path . (str_contains($path,'?') ? '&' : '?') . $q);
  exit;
}

/* ========= Roteia ========= */
$op = (string)($_POST['op'] ?? '');

switch ($op) {

  /* ======================= CRIAR USUÁRIO ======================= */
  case 'usuario_novo': {
    // Só DONO cria
    if (($_SESSION['user_perfil'] ?? '') !== 'dono') {
      back_to('../pages/novo.php', ['err'=>1,'msg'=>'Apenas o dono pode cadastrar usuários.']);
    }

    // CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf_cfg_user_new']) || !hash_equals($_SESSION['csrf_cfg_user_new'], $csrf)) {
      back_to('../pages/novo.php', ['err'=>1,'msg'=>'Token inválido. Atualize a página.']);
    }

    // Empresa (da sessão)
    $empresaCnpj = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
      back_to('../pages/novo.php', ['err'=>1,'msg'=>'Empresa não vinculada ao usuário.']);
    }

    // Entrada
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $email  = strtolower(trim((string)($_POST['email'] ?? '')));
    $cpf    = norm_cpf($_POST['cpf'] ?? '');
    $tel    = trim((string)($_POST['telefone'] ?? ''));
    $tipo   = strtolower(trim((string)($_POST['tipo_funcionario'] ?? 'administrativo'))); // administrativo|caixa|estoque|lavajato
    $perfil = 'funcionario';

    // Valida
    $errs = [];
    if (strlen($nome) < 3) $errs[] = 'Informe o nome completo.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'E-mail inválido.';
    if (!in_array($tipo, ['administrativo','caixa','estoque','lavajato'], true)) $errs[] = 'Tipo de funcionário inválido.';
    if ($cpf !== '' && !preg_match('/^\d{11}$/', $cpf)) $errs[] = 'CPF deve conter 11 dígitos numéricos.';
    if ($errs) {
      back_to('../pages/novo.php', ['err'=>1,'msg'=>implode(' ', $errs)]);
    }

    try {
      // Empresa ativa?
      $st = $pdo->prepare("SELECT nome_fantasia, status FROM empresas_peca WHERE cnpj = :c LIMIT 1");
      $st->execute([':c'=>$empresaCnpj]);
      $emp = $st->fetch(PDO::FETCH_ASSOC);
      if (!$emp || ($emp['status'] ?? '') !== 'ativa') {
        back_to('../pages/novo.php', ['err'=>1,'msg'=>'Empresa inexistente ou inativa.']);
      }

      // E-mail único
      $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE email = :e LIMIT 1");
      $st->execute([':e'=>$email]);
      if ($st->fetch()) {
        back_to('../pages/novo.php', ['err'=>1,'msg'=>'E-mail já cadastrado.']);
      }

      // CPF único na empresa
      if ($cpf !== '') {
        $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE empresa_cnpj = :c AND cpf = :cpf LIMIT 1");
        $st->execute([':c'=>$empresaCnpj, ':cpf'=>$cpf]);
        if ($st->fetch()) {
          back_to('../pages/novo.php', ['err'=>1,'msg'=>'CPF já cadastrado para esta empresa.']);
        }
      }

      $pdo->beginTransaction();

      // Senha temp
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
        ':tel'   => ($tel ?: null),
        ':senha' => $hash,
        ':perfil'=> $perfil,
        ':tipo'  => $tipo,
      ]);
      $usuarioId = (int)$pdo->lastInsertId();

      // Token/OTP
      $otp   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $token = bin2hex(random_bytes(32));
      $exp   = date('Y-m-d H:i:s', time() + 15*60);
      $ip    = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua    = $_SERVER['HTTP_USER_AGENT'] ?? null;

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

      /* ========= E-mail para o usuário ========= */
      $basePublic = base_public_url();
      $confirmUrl = $basePublic . '../../confirmarCodigo.php?token=' . urlencode($token) . '&email=' . urlencode($email);

      $assuntoUser = 'Bem-vindo ao AutoERP — Defina sua senha';
      $htmlUser = '
        <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;background:#ffffff;padding:16px">
          <div style="max-width:640px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="background:#0ea5e9;color:#fff;padding:16px 20px">
              <h2 style="margin:0;font-size:20px;font-weight:700;">AutoERP</h2>
            </div>
            <div style="padding:20px">
              <p style="margin:0 0 12px">Olá <strong>'.htmlspecialchars($nome, ENT_QUOTES, 'UTF-8').'</strong>,</p>
              <p style="margin:0 0 12px">Sua conta foi criada para a empresa <strong>'.htmlspecialchars($emp['nome_fantasia'] ?? 'sua empresa', ENT_QUOTES, 'UTF-8').'</strong>.</p>

              <p style="margin:0 0 8px">Use o código abaixo para definir sua senha:</p>
              <p style="font-size:26px;font-weight:bold;letter-spacing:4px;margin:12px 0 16px;">'.htmlspecialchars($otp, ENT_QUOTES, 'UTF-8').'</p>

              <p style="margin:0 0 8px">Ou clique diretamente no link:</p>
              <p style="margin:0 0 16px"><a href="'.htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8').'">Definir minha senha</a></p>

              <p style="margin:0 0 8px"><strong>Validade:</strong> 15 minutos.</p>
              <p style="margin:0;color:#64748b;font-size:12px">Se você não esperava este e-mail, pode ignorá-lo.</p>
            </div>
          </div>
        </div>
      ';

      // Envia para o usuário
      if (function_exists('enviar_email')) {
        @enviar_email($email, $assuntoUser, $htmlUser);
      } else {
        // fallback bem simples
        @mail($email, $assuntoUser, strip_tags($htmlUser));
      }

      // OK
      back_to('../pages/novo.php', ['ok'=>1,'msg'=>'Usuário criado e e-mail enviado.']);
    }
    catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      app_log('ERRO usuario_novo: '.$e->getMessage());
      $msg = 'Falha ao criar usuário.';
      if (APP_DEBUG) $msg .= ' Detalhe: '.$e->getMessage();
      back_to('../pages/novo.php', ['err'=>1,'msg'=>$msg]);
    }
    break;
  }

  /* ======================= EXCLUIR USUÁRIO ======================= */
  case 'usuario_excluir': {
    // Apenas DONO
    if (($_SESSION['user_perfil'] ?? '') !== 'dono') {
      back_to('../pages/listar.php', ['err'=>1,'msg'=>'Apenas o dono pode excluir usuários.']);
    }

    // CSRF da listagem (defina em listar.php como $_SESSION['csrf_cfg_user_list'])
    $csrf = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf_cfg_user_list']) || !hash_equals($_SESSION['csrf_cfg_user_list'], $csrf)) {
      back_to('../pages/listar.php', ['err'=>1,'msg'=>'Token inválido. Atualize a página.']);
    }

    $empresaCnpjSess = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if (!preg_match('/^\d{14}$/', $empresaCnpjSess)) {
      back_to('../pages/listar.php', ['err'=>1,'msg'=>'Empresa não vinculada ao usuário.']);
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      back_to('../pages/listar.php', ['err'=>1,'msg'=>'Usuário inválido.']);
    }

    // Não deixa apagar a si mesmo
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
      back_to('../pages/listar.php', ['err'=>1,'msg'=>'Você não pode excluir seu próprio usuário.']);
    }

    try {
      // Confere se pertence à empresa e é funcionário
      $st = $pdo->prepare("SELECT id FROM usuarios_peca WHERE id = :id AND empresa_cnpj = :cnpj AND perfil = 'funcionario' LIMIT 1");
      $st->execute([':id' => $id, ':cnpj' => $empresaCnpjSess]);
      if (!$st->fetchColumn()) {
        back_to('../pages/listar.php', ['err'=>1,'msg'=>'Usuário não encontrado nesta empresa.']);
      }

      $pdo->beginTransaction();

      // Limpa tokens
      $delTok = $pdo->prepare("DELETE FROM usuarios_redefinicao_senha_peca WHERE usuario_id = :id");
      $delTok->execute([':id' => $id]);

      // Exclui o usuário
      $delUser = $pdo->prepare("DELETE FROM usuarios_peca WHERE id = :id LIMIT 1");
      $delUser->execute([':id' => $id]);

      $pdo->commit();

      back_to('../pages/listar.php', ['ok'=>1,'msg'=>'Usuário excluído com sucesso.']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      app_log('ERRO usuario_excluir: '.$e->getMessage());
      $msg = 'Falha ao excluir usuário.';
      if (APP_DEBUG) $msg .= ' Detalhe: '.$e->getMessage();
      back_to('../pages/listar.php', ['err'=>1,'msg'=>$msg]);
    }
    break;
  }

  default:
    back_to('../pages/novo.php', ['err'=>1,'msg'=>'Operação inválida.']);
}
