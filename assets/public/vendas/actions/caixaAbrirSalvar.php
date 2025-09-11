<?php
// autoErp/public/caixa/actions/caixaAbrirSalvar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

// Timezone helpers (usa CEP/UF da empresa p/ fuso)
require_once __DIR__ . '/../../../lib/timezone_br.php';

function back(int $ok, string $msg = ''): never {
  $qs = http_build_query(['ok'=>$ok, 'err'=>$ok?0:1, 'msg'=>$msg]);
  header("Location: ../pages/caixaAbrir.php?$qs");
  exit;
}
function post($k,$d=''){ return $_POST[$k] ?? $d; }

// CSRF
if (!empty($_SESSION['csrf_caixa_abrir'])) {
  $csrf = (string)post('csrf');
  if ($csrf !== $_SESSION['csrf_caixa_abrir']) back(0, 'CSRF inválido.');
}

// Empresa & Operador
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) back(0, 'Empresa não vinculada ao usuário.');

$operadorCpf  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
$operadorNome = (string)($_SESSION['user_nome'] ?? '');
$userEmail    = (string)($_SESSION['user_email'] ?? '');
$userId       = (string)($_SESSION['user_id'] ?? '');

if ($operadorCpf === '' && $userEmail !== '') {
  try {
    $stOp = $pdo->prepare("SELECT cpf, nome FROM usuarios_peca WHERE email=:e LIMIT 1");
    $stOp->execute([':e'=>$userEmail]);
    if ($row = $stOp->fetch(PDO::FETCH_ASSOC)) {
      $operadorCpf  = preg_replace('/\D+/', '', (string)($row['cpf'] ?? ''));
      if ($operadorNome === '' && !empty($row['nome'])) $operadorNome = $row['nome'];
      $_SESSION['user_cpf']  = $operadorCpf;
      if (!empty($row['nome'])) $_SESSION['user_nome'] = $row['nome'];
    }
  } catch (Throwable $e) { /* silencioso */ }
}
if ($operadorCpf === '') {
  if ($userId !== '') {
    $operadorCpf = 'UID'.preg_replace('/\D+/', '', $userId);
  } else {
    back(0, 'Operador inválido.');
  }
}

// Ajusta fuso da sessão MySQL para a empresa (agora em diante NOW()/datas ficam no fuso correto)
mysql_set_time_zone_for_empresa($pdo, $empresaCnpj);

// Op
$op = (string)post('op', 'abrir');

// =====================================================
// ABRIR NOVO CAIXA
// =====================================================
if ($op === 'abrir') {
  $tipo           = (string)post('tipo', 'compartilhado'); // 'individual'|'compartilhado'
  $valorAbertura  = (float)str_replace(',', '.', (string)post('valor_abertura', '0'));
  $observacoes    = trim((string)post('observacoes', ''));
  $terminal       = trim((string)post('terminal', ''));

  if (!in_array($tipo, ['individual','compartilhado'], true)) back(0, 'Tipo de caixa inválido.');

  try {
    // Regra: individual não pode ter outro individual aberto do mesmo operador
    if ($tipo === 'individual') {
      $st = $pdo->prepare("
        SELECT id FROM caixas_peca
        WHERE empresa_cnpj=:c AND tipo='individual' AND status='aberto' AND aberto_por_cpf=:cpf
        LIMIT 1
      ");
      $st->execute([':c'=>$empresaCnpj, ':cpf'=>$operadorCpf]);
      if ($st->fetch()) back(0, 'Você já possui um caixa individual aberto.');
    }

    $pdo->beginTransaction();

    // Hora local da empresa
    $abertoEm = empresa_now($pdo, $empresaCnpj)->format('Y-m-d H:i:s');

    // IMPORTANTE: sua tabela precisa ter AUTO_INCREMENT/PK no id
    $ins = $pdo->prepare("
      INSERT INTO caixas_peca
        (empresa_cnpj, tipo, terminal, aberto_por_cpf, aberto_em, saldo_inicial, status, observacoes)
      VALUES
        (:c, :tipo, :terminal, :cpf, :aberto_em, :saldo, 'aberto', :obs)
    ");
    $ins->execute([
      ':c'         => $empresaCnpj,
      ':tipo'      => $tipo,
      ':terminal'  => $terminal ?: null,
      ':cpf'       => $operadorCpf ?: null,
      ':aberto_em' => $abertoEm,
      ':saldo'     => $valorAbertura,
      ':obs'       => $observacoes ?: null,
    ]);

    $caixaId = (int)$pdo->lastInsertId(); // << pega o ID gerado
    if ($tipo === 'compartilhado') {
      // Opcional: já registra o abridor como participante ativo
      $insP = $pdo->prepare("
        INSERT INTO caixa_participantes_peca
          (caixa_id, empresa_cnpj, operador_cpf, operador_nome, entrou_em, ativo)
        VALUES
          (:cid, :c, :cpf, :nome, :en, 1)
      ");
      $insP->execute([
        ':cid'  => $caixaId,
        ':c'    => $empresaCnpj,
        ':cpf'  => $operadorCpf,
        ':nome' => $operadorNome ?: null,
        ':en'   => $abertoEm,
      ]);
    }

    $pdo->commit();
    back(1, "Caixa aberto com sucesso! (ID #{$caixaId})");

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Para depurar troque pela linha comentada:
    // back(0, 'Falha ao abrir o caixa: '.$e->getMessage());
    back(0, 'Falha ao abrir o caixa.');
  }
}

// =====================================================
// ENTRAR EM UM CAIXA COMPARTILHADO JÁ ABERTO
// =====================================================
if ($op === 'entrar') {
  $caixaId = (int)post('caixa_compartilhado_id', 0);
  if ($caixaId <= 0) back(0, 'Selecione um caixa compartilhado.');

  try {
    // Confirma que é compartilhado e está aberto
    $st = $pdo->prepare("
      SELECT id FROM caixas_peca
      WHERE id=:id AND empresa_cnpj=:c AND tipo='compartilhado' AND status='aberto'
      LIMIT 1
    ");
    $st->execute([':id'=>$caixaId, ':c'=>$empresaCnpj]);
    if (!$st->fetch()) back(0, 'Caixa não encontrado ou não está aberto.');

    // Já está ativo nesse caixa?
    $st2 = $pdo->prepare("
      SELECT id FROM caixa_participantes_peca
      WHERE caixa_id=:id AND empresa_cnpj=:c AND operador_cpf=:cpf AND ativo=1
      LIMIT 1
    ");
    $st2->execute([':id'=>$caixaId, ':c'=>$empresaCnpj, ':cpf'=>$operadorCpf]);
    if ($st2->fetch()) back(0, 'Você já está participando deste caixa.');

    $entrouEm = empresa_now($pdo, $empresaCnpj)->format('Y-m-d H:i:s');

    $insP = $pdo->prepare("
      INSERT INTO caixa_participantes_peca
        (caixa_id, empresa_cnpj, operador_cpf, operador_nome, entrou_em, ativo)
      VALUES
        (:cid, :c, :cpf, :nome, :en, 1)
    ");
    $insP->execute([
      ':cid'  => $caixaId,
      ':c'    => $empresaCnpj,
      ':cpf'  => $operadorCpf,
      ':nome' => $operadorNome ?: null,
      ':en'   => $entrouEm,
    ]);

    back(1, "Você entrou no caixa #{$caixaId}.");

  } catch (Throwable $e) {
    // back(0, 'Falha ao entrar no caixa: '.$e->getMessage());
    back(0, 'Falha ao entrar no caixa.');
  }
}

// Operação inválida
back(0, 'Operação inválida.');
