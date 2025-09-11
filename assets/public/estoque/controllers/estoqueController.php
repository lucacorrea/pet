<?php
// autoErp/public/estoque/controllers/estoqueController.php
declare(strict_types=1);

function estoque_viewmodel(PDO $pdo): array
{
  if (session_status() === PHP_SESSION_NONE) session_start();

  $cnpj  = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
  $perfil = strtolower((string)($_SESSION['user_perfil'] ?? 'funcionario')); // dono|administrativo|caixa|estoque|lavajato
  $cpfUsu = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

  // Permissão para mover estoque: dono/administrativo/estoque
  $canMov = in_array($perfil, ['dono','administrativo','estoque'], true);

  // CSRF para o modal de movimentação
  if (empty($_SESSION['csrf_estoque_mov'])) {
    $_SESSION['csrf_estoque_mov'] = bin2hex(random_bytes(32));
  }

  $vm = [
    'canMov'      => $canMov,
    'csrf'        => $_SESSION['csrf_estoque_mov'],
    'flash_ok'    => (int)($_GET['ok'] ?? 0),
    'flash_err'   => (int)($_GET['err'] ?? 0),
    'flash_msg'   => (string)($_GET['msg'] ?? ''),

    // Cards
    'prodAtivos'  => 0,
    'prodInativos'=> 0,
    'zerados'     => 0,
    'abaixoMin'   => 0,
    'skus'        => 0,
    'estoqueTotal'=> 0.0,

    // Movimentações & Produtos para o select
    'movimentos'  => [],
    'prodOptions' => [],
    'usuarioCpf'  => $cpfUsu,
  ];

  if (!preg_match('/^\d{14}$/', $cnpj)) return $vm;

  try {
    // Contadores
    $st = $pdo->prepare("SELECT 
        COUNT(*)                                             AS skus,
        SUM(CASE WHEN ativo=1 THEN 1 ELSE 0 END)            AS ativos,
        SUM(CASE WHEN ativo=0 THEN 1 ELSE 0 END)            AS inativos,
        SUM(CASE WHEN ativo=1 AND estoque_atual<=0 THEN 1 ELSE 0 END) AS zerados,
        SUM(CASE WHEN ativo=1 AND estoque_atual<estoque_minimo THEN 1 ELSE 0 END) AS abaixo_min,
        COALESCE(SUM(estoque_atual),0)                      AS total_qtd
      FROM produtos_peca
      WHERE empresa_cnpj = :c
    ");
    $st->execute([':c'=>$cnpj]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $vm['skus']         = (int)($r['skus'] ?? 0);
    $vm['prodAtivos']   = (int)($r['ativos'] ?? 0);
    $vm['prodInativos'] = (int)($r['inativos'] ?? 0);
    $vm['zerados']      = (int)($r['zerados'] ?? 0);
    $vm['abaixoMin']    = (int)($r['abaixo_min'] ?? 0);
    $vm['estoqueTotal'] = (float)($r['total_qtd'] ?? 0);
  } catch (\Throwable $e) {}

  try {
    // Últimas 12 movimentações
    $st = $pdo->prepare("
      SELECT m.id, m.tipo, m.qtd, m.origem, m.ref_id,
             DATE_FORMAT(m.criado_em, '%d/%m/%Y %H:%i') AS quando,
             p.nome AS produto_nome, p.unidade
      FROM mov_estoque_peca m
      JOIN produtos_peca p ON p.id = m.produto_id
      WHERE m.empresa_cnpj = :c
      ORDER BY m.id DESC
      LIMIT 12
    ");
    $st->execute([':c'=>$cnpj]);
    $vm['movimentos'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (\Throwable $e) {}

  try {
    // Opções de produto (últimos 50 criados)
    $st = $pdo->prepare("
      SELECT id, nome, unidade, estoque_atual
      FROM produtos_peca
      WHERE empresa_cnpj = :c AND ativo = 1
      ORDER BY criado_em DESC, id DESC
      LIMIT 50
    ");
    $st->execute([':c'=>$cnpj]);
    $vm['prodOptions'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (\Throwable $e) {}

  return $vm;
}
