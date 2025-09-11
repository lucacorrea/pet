<?php
// autoErp/public/vendas/controllers/orcamentoPrintController.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono','administrativo','funcionario']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('ID inválido.');

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) die('Empresa não vinculada ao usuário.');

$vm = [
  'ok'          => 0,
  'err'         => 0,
  'msg'         => '',
  'empresa'     => [
    'nome'       => '',
    'razao'      => '',
    'cnpj'       => $empresaCnpj,
    'telefone'   => '',
    'email'      => '',
    'endereco'   => '',
    'cidade'     => '',
    'estado'     => '',
    'cep'        => '',
    'logo_url'   => '../../assets/images/auth/ode.png', // ajuste se tiver logo da empresa salva
  ],
  'orc'         => null,
  'itens'       => [],
];

try {
  // ===== empresa
  $se = $pdo->prepare("
    SELECT
      nome_fantasia, razao_social, cnpj, telefone, email, endereco, cidade, estado, cep
    FROM empresas_peca
    WHERE cnpj = :c
    LIMIT 1
  ");
  $se->execute([':c'=>$empresaCnpj]);
  if ($emp = $se->fetch(PDO::FETCH_ASSOC)) {
    $vm['empresa']['nome']     = (string)($emp['nome_fantasia'] ?? '');
    $vm['empresa']['razao']    = (string)($emp['razao_social'] ?? '');
    $vm['empresa']['cnpj']     = (string)($emp['cnpj'] ?? $empresaCnpj);
    $vm['empresa']['telefone'] = (string)($emp['telefone'] ?? '');
    $vm['empresa']['email']    = (string)($emp['email'] ?? '');
    $vm['empresa']['endereco'] = (string)($emp['endereco'] ?? '');
    $vm['empresa']['cidade']   = (string)($emp['cidade'] ?? '');
    $vm['empresa']['estado']   = (string)($emp['estado'] ?? '');
    $vm['empresa']['cep']      = (string)($emp['cep'] ?? '');
  }

  // ===== orçamento
  $so = $pdo->prepare("
    SELECT
      o.*,
      DATE_FORMAT(o.criado_em,'%d/%m/%Y %H:%i') AS quando_fmt,
      DATE_FORMAT(o.validade,'%d/%m/%Y')        AS validade_fmt
    FROM orcamentos_peca o
    WHERE o.id = :id AND o.empresa_cnpj = :c
    LIMIT 1
  ");
  $so->execute([':id'=>$id, ':c'=>$empresaCnpj]);
  $orc = $so->fetch(PDO::FETCH_ASSOC);
  if (!$orc) {
    $vm['err'] = 1;
    $vm['msg'] = 'Orçamento não encontrado.';
    return;
  }

  // ===== itens
  // ajuste o nome da tabela se for diferente
  $si = $pdo->prepare("
    SELECT
      item_tipo, descricao, qtd, valor_unit, (qtd*valor_unit) AS total
    FROM orcamento_itens_peca
    WHERE orcamento_id = :id
    ORDER BY id
  ");
  $si->execute([':id'=>$id]);
  $itens = $si->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $vm['orc']   = $orc;
  $vm['itens'] = $itens;
  $vm['ok']    = 1;

} catch (Throwable $e) {
  $vm['err'] = 1;
  $vm['msg'] = 'Falha ao carregar orçamento para impressão.';
}

// Exponha $vm para a view
