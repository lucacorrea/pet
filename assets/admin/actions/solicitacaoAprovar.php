<?php
// autoErp/admin/actions/solicitacaoAprovar.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth_guard.php';
guard_super_admin();
require_post();

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], $csrf)) {
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Token inválido')); exit;
}

$solId = (int)($_POST['sol_id'] ?? 0);
$cnpj  = preg_replace('/\D+/', '', (string)($_POST['cnpj'] ?? ''));

if ($solId <= 0) {
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Solicitação inválida.')); exit;
}

require_once __DIR__ . '/../../conexao/conexao.php';
require_once __DIR__ . '/../../config/mail.php';

/** Formata CNPJ 14 dígitos em 00.000.000/0000-00 */
function fmt_cnpj(string $n): string {
  $n = preg_replace('/\D+/', '', $n);
  if (strlen($n) !== 14) return $n;
  return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
}

/** Monta URL absoluta baseada no host atual + BASE (se definida em auth_guard) */
function base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = defined('BASE') ? BASE : '/autoErp';
  return rtrim($scheme . '://' . $host . $base, '/');
}

try {
  // pega a solicitação
  $st = $pdo->prepare("SELECT * FROM solicitacoes_empresas_peca WHERE id = :id LIMIT 1");
  $st->execute([':id' => $solId]);
  $sol = $st->fetch(PDO::FETCH_ASSOC);

  if (!$sol) {
    header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Solicitação não encontrada.')); exit;
  }
  if (($sol['status'] ?? '') !== 'pendente') {
    header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Solicitação já processada.')); exit;
  }

  // se não veio CNPJ no POST, usa o da solicitação
  if ($cnpj === '') $cnpj = preg_replace('/\D+/', '', (string)$sol['cnpj']);
  if (strlen($cnpj) !== 14) {
    header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('CNPJ obrigatório para aprovar.')); exit;
  }

  $pdo->beginTransaction();

  // Cria/ativa empresa se não existir
  $emp = $pdo->prepare("SELECT id FROM empresas_peca WHERE cnpj = :c LIMIT 1");
  $emp->execute([':c' => $cnpj]);
  $empresa = $emp->fetch(PDO::FETCH_ASSOC);

  if (!$empresa) {
    $ins = $pdo->prepare("
      INSERT INTO empresas_peca (cnpj, nome_fantasia, email, telefone, status, criado_em)
      VALUES (:cnpj, :nome, :email, :tel, 'ativa', NOW())
    ");
    $ins->execute([
      ':cnpj' => $cnpj,
      ':nome' => $sol['nome_fantasia'] ?? '',
      ':email'=> $sol['email'] ?? null,
      ':tel'  => $sol['telefone'] ?? null,
    ]);
  } else {
    $upEmp = $pdo->prepare("UPDATE empresas_peca SET status='ativa' WHERE id = :id");
    $upEmp->execute([':id' => (int)$empresa['id']]);
  }

  // marca solicitação como aprovada + congela CNPJ utilizado
  $upSol = $pdo->prepare("UPDATE solicitacoes_empresas_peca SET status='aprovada', cnpj=:cnpj WHERE id = :id");
  $upSol->execute([':id' => $solId, ':cnpj' => $cnpj]);

  // ======= CRIA/ATUALIZA O DONO EM usuarios_peca =======
  $ownerEmail = trim((string)($sol['proprietario_email'] ?? ''));
  $ownerNome  = trim((string)($sol['proprietario_nome']  ?? 'Proprietário(a)'));
  $ownerTel   = trim((string)($sol['telefone'] ?? ''));
  $senhaHashDaSolicitacao = trim((string)($sol['proprietario_senha_hash'] ?? ''));

  // Se não houver senha salva na solicitação, cria uma temporária e força redefinição
  $precisaRedefinir = 0;
  $senhaParaGravar  = $senhaHashDaSolicitacao;
  if ($senhaParaGravar === '') {
    $senhaTemporaria = bin2hex(random_bytes(6)); // 12 chars
    $senhaParaGravar = password_hash($senhaTemporaria, PASSWORD_DEFAULT);
    $precisaRedefinir = 1;
  }

  if ($ownerEmail !== '') {
    // Já existe um usuário com este e-mail?
    $stU = $pdo->prepare("SELECT id FROM usuarios_peca WHERE email = :e LIMIT 1");
    $stU->execute([':e' => $ownerEmail]);
    $existe = $stU->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
      // Atualiza perfil/cnpj/status para dono ativo desta empresa
      $upU = $pdo->prepare("
        UPDATE usuarios_peca
           SET empresa_cnpj = :cnpj,
               perfil       = 'dono',
               tipo_funcionario = NULL,
               status       = 1,
               precisa_redefinir_senha = :pr
         WHERE id = :id
      ");
      $upU->execute([
        ':cnpj' => $cnpj,
        ':pr'   => $precisaRedefinir,
        ':id'   => (int)$existe['id'],
      ]);

      // Opcional: só atualize a senha se desejar sobrescrever
      $upPwd = $pdo->prepare("UPDATE usuarios_peca SET senha = :s WHERE id = :id");
      $upPwd->execute([':s' => $senhaParaGravar, ':id' => (int)$existe['id']]);

    } else {
      // Cria o dono
      $insU = $pdo->prepare("
        INSERT INTO usuarios_peca
          (empresa_cnpj, nome, email, cpf, telefone, senha, perfil, tipo_funcionario, status, precisa_redefinir_senha, criado_em)
        VALUES
          (:cnpj, :nome, :email, NULL, :tel, :senha, 'dono', NULL, 1, :pr, NOW())
      ");
      $insU->execute([
        ':cnpj'  => $cnpj,
        ':nome'  => $ownerNome !== '' ? $ownerNome : 'Proprietário',
        ':email' => $ownerEmail,
        ':tel'   => $ownerTel ?: null,
        ':senha' => $senhaParaGravar,
        ':pr'    => $precisaRedefinir,
      ]);
    }
  }

  $pdo->commit();

  // --------- E-mail de boas-vindas / aprovação ---------
  if ($ownerEmail !== '') {
    $empresaNome = (string)($sol['nome_fantasia'] ?? '');
    $cnpjFmt     = fmt_cnpj($cnpj);
    $base        = base_url();
    $loginUrl    = $base . '/index.php';
    $criarSenha  = $base . '/confirmaEmail.php?email=' . urlencode($ownerEmail);

    $assunto = 'Bem-vindo ao AutoERP — Solicitação Aprovada';
    $passoSenha = ($senhaHashDaSolicitacao === '')
      ? '<li style="margin-bottom:6px">Clique em <a href="' . htmlspecialchars($criarSenha, ENT_QUOTES, 'UTF-8') . '">Criar/Redefinir Senha</a> e informe seu e-mail para definir sua senha.</li>'
      : '<li style="margin-bottom:6px">Acesse com o e-mail informado. Se preferir trocar a senha, use <a href="' . htmlspecialchars($criarSenha, ENT_QUOTES, 'UTF-8') . '">Esqueci minha senha</a>.</li>';

    $html = '
      <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;background:#ffffff;padding:16px">
        <div style="max-width:640px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
          <div style="background:#0ea5e9;color:#fff;padding:16px 20px">
            <h2 style="margin:0;font-size:20px;font-weight:700;">AutoERP</h2>
          </div>
          <div style="padding:20px">
            <p style="margin:0 0 12px">Olá <strong>' . htmlspecialchars($ownerNome ?: 'proprietário(a)', ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p style="margin:0 0 12px">Sua solicitação para acesso ao <strong>AutoERP</strong> foi <span style="color:#16a34a;font-weight:700">APROVADA</span>! 🎉</p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;margin:14px 0">
              <p style="margin:0 0 6px"><strong>Empresa:</strong> ' . htmlspecialchars($empresaNome ?: '—', ENT_QUOTES, 'UTF-8') . '</p>
              <p style="margin:0"><strong>CNPJ:</strong> ' . htmlspecialchars($cnpjFmt, ENT_QUOTES, 'UTF-8') . '</p>
            </div>

            <p style="margin:0 0 10px">Para entrar no sistema, siga estes passos:</p>
            <ol style="margin:0 0 14px 20px;padding:0">
              ' . $passoSenha . '
              <li style="margin-bottom:6px">Depois, acesse o sistema em <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">AutoERP — Login</a>.</li>
            </ol>

            <p style="margin:0 0 12px">Se precisar de ajuda, basta responder este e-mail.</p>
            <p style="margin:0;color:#64748b;font-size:12px">Atenciosamente,<br>Equipe AutoERP</p>
          </div>
        </div>
      </div>
    ';

    // Usa o sender configurado no seu mail.php
    @enviar_email($ownerEmail, $assunto, $html);
  }

  header('Location: ../pages/solicitacao.php?ok=1&msg=' . urlencode('Solicitação aprovada. Dono criado/atualizado e e-mail enviado.')); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ../pages/solicitacao.php?err=1&msg=' . urlencode('Falha ao aprovar: '.$e->getMessage())); exit;
}
