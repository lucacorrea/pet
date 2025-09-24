<?php
// autoErp/public/vendas/actions/vendaRapidaSalvar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');

function back(string $msg, int $ok = 0, array $extra = []): never {
  $params = array_merge(['ok'=>$ok, 'err'=>$ok?0:1, 'msg'=>$msg], $extra);
  $q = http_build_query($params);
  header("Location: ../pages/vendaRapida.php?$q");
  exit;
}

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (!$csrf || !hash_equals($_SESSION['csrf_venda_rapida'] ?? '', $csrf)) {
  back('Sessão expirada. Recarregue a página e tente novamente.');
}

$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
  back('Empresa inválida na sessão.');
}

$vendedorCpf = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));
if (!$vendedorCpf) {
  back('Usuário sem CPF vinculado.');
}

// forma de pagamento (compatibilidade com 2 nomes)
$forma = strtolower(trim((string)($_POST['forma_pagamento'] ?? $_POST['pagamento_tipo'] ?? '')));
if ($forma === '') $forma = 'dinheiro';
$formasValidas = ['dinheiro','pix','debito','credito'];
if (!in_array($forma, $formasValidas, true)) $forma = 'dinheiro';

// itens + desconto
$itens = json_decode((string)($_POST['itens_json'] ?? '[]'), true);
if (!is_array($itens) || count($itens) === 0) {
  back('Sem itens na venda.');
}

$descontoRaw = (string)($_POST['desconto'] ?? '0');
$desconto = (float) str_replace(['.',','], ['','.' ], $descontoRaw);

// Recalcula total no servidor
$subtotal = 0.0;
foreach ($itens as $it) {
  $qtd  = (float)($it['qtd']  ?? 0);
  $unit = (float)($it['unit'] ?? 0);
  if ($qtd <= 0 || $unit < 0) continue;
  $subtotal += $qtd * $unit;
}
$total = max(0, $subtotal - max(0, $desconto));

// Se for dinheiro, exige valor_recebido
$valorRecebido = null;
if ($forma === 'dinheiro') {
  $rawReceb = (string)($_POST['valor_recebido'] ?? '');
  $norm = str_replace(['.', ','], ['', '.'], $rawReceb); // 1.234,56 -> 1234.56
  if ($norm === '' || !is_numeric($norm)) {
    back('Informe o valor recebido em dinheiro.');
  }
  $valorRecebido = (float)$norm;
  if ($valorRecebido + 1e-9 < $total) {
    back('Valor recebido insuficiente.');
  }
}

// Verifica caixa aberto (para lançar movimento em dinheiro)
$caixaAberto = null;
try {
  $stCx = $pdo->prepare("SELECT id FROM caixas_peca WHERE empresa_cnpj=:c AND status='aberto' ORDER BY aberto_em DESC LIMIT 1");
  $stCx->execute([':c' => $empresaCnpj]);
  $caixaAberto = $stCx->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  // segue nulo
}

try {
  $pdo->beginTransaction();

  // Grava venda
  $stVenda = $pdo->prepare(
    "INSERT INTO vendas_peca
     (empresa_cnpj, vendedor_cpf, origem, status, total_bruto, desconto, total_liquido, forma_pagamento)
     VALUES
     (:cnpj, :cpf, 'balcao', 'fechada', :bruto, :desc, :liq, :forma)"
  );
  $stVenda->execute([
    ':cnpj'  => $empresaCnpj,
    ':cpf'   => $vendedorCpf,
    ':bruto' => number_format($subtotal,2,'.',''),
    ':desc'  => number_format(max(0,$desconto),2,'.',''),
    ':liq'   => number_format($total,2,'.',''),
    ':forma' => $forma,
  ]);
  $vendaId = (int)$pdo->lastInsertId();
  if ($vendaId <= 0) throw new RuntimeException('Falha ao criar venda.');

  // Prepara buscas de produto
  $stFindSku = $pdo->prepare("SELECT id FROM produtos_peca WHERE empresa_cnpj=:c AND sku=:sku LIMIT 1");
  $stFindEan = $pdo->prepare("SELECT id FROM produtos_peca WHERE empresa_cnpj=:c AND ean=:ean LIMIT 1");
  $stFindNome= $pdo->prepare("SELECT id FROM produtos_peca WHERE empresa_cnpj=:c AND nome=:n LIMIT 1");

  // Insere itens
  $stItem = $pdo->prepare(
    "INSERT INTO venda_itens_peca
     (venda_id, item_tipo, item_id, descricao, qtd, valor_unit, valor_total)
     VALUES
     (:venda, 'produto', :item_id, :desc, :qtd, :vu, :vt)"
  );

  foreach ($itens as $it) {
    $nome = trim((string)($it['nome'] ?? ''));
    $sku  = trim((string)($it['sku']  ?? ''));
    $qtd  = (float)($it['qtd']  ?? 0);
    $unit = (float)($it['unit'] ?? 0);
    if ($qtd <= 0 || $unit < 0) continue;

    // mapeia produto: SKU > EAN (se veio como sku) > Nome
    $produtoId = null;
    if ($sku !== '') {
      $stFindSku->execute([':c'=>$empresaCnpj, ':sku'=>$sku]);
      $row = $stFindSku->fetch(PDO::FETCH_ASSOC);
      if ($row) $produtoId = (int)$row['id'];
      if (!$produtoId) { // tenta como EAN
        $stFindEan->execute([':c'=>$empresaCnpj, ':ean'=>$sku]);
        $row = $stFindEan->fetch(PDO::FETCH_ASSOC);
        if ($row) $produtoId = (int)$row['id'];
      }
    }
    if (!$produtoId && $nome !== '') {
      $stFindNome->execute([':c'=>$empresaCnpj, ':n'=>$nome]);
      $row = $stFindNome->fetch(PDO::FETCH_ASSOC);
      if ($row) $produtoId = (int)$row['id'];
    }

    if (!$produtoId) {
      // IMPORTANTE: para não violar NOT NULL, paramos e pedimos para cadastrar/mapear o produto
      throw new RuntimeException("Produto não localizado (\"$nome\" / SKU \"$sku\"). Cadastre o produto ou informe o SKU.");
      // Alternativa (se desejar): criar "ITEM AVULSO" e usar id dele.
    }

    $vt = $qtd * $unit;

    $stItem->execute([
      ':venda'   => $vendaId,
      ':item_id' => $produtoId,
      ':desc'    => ($nome !== '' ? $nome : 'Item'),
      ':qtd'     => number_format($qtd,3,'.',''),
      ':vu'      => number_format($unit,2,'.',''),
      ':vt'      => number_format($vt,2,'.',''),
    ]);

    // (Opcional) baixar estoque aqui se desejar, usando mov_estoque_peca
  }

  // Movimento de caixa apenas se for dinheiro e tiver caixa aberto
  if ($forma === 'dinheiro' && $caixaAberto && !empty($caixaAberto['id'])) {
    $stMov = $pdo->prepare(
      "INSERT INTO caixa_movimentos_peca
       (empresa_cnpj, caixa_id, tipo, forma_pagamento, valor, descricao)
       VALUES
       (:cnpj, :caixa, 'entrada', :forma, :valor, :desc)"
    );
    $stMov->execute([
      ':cnpj'  => $empresaCnpj,
      ':caixa' => (int)$caixaAberto['id'],
      ':forma' => 'dinheiro',
      ':valor' => number_format($total,2,'.',''), // registra o líquido
      ':desc'  => "Venda #$vendaId (dinheiro)"
    ]);

    // (Opcional) registrar saída de troco:
    // if ($valorRecebido !== null && $valorRecebido > $total) {
    //   $troco = $valorRecebido - $total;
    //   $stTroco = $pdo->prepare(
    //     "INSERT INTO caixa_movimentos_peca
    //      (empresa_cnpj, caixa_id, tipo, forma_pagamento, valor, descricao)
    //      VALUES
    //      (:cnpj, :caixa, 'saida', :forma, :valor, :desc)"
    //   );
    //   $stTroco->execute([
    //     ':cnpj'=>$empresaCnpj, ':caixa'=>(int)$caixaAberto['id'],
    //     ':forma'=>'dinheiro', ':valor'=>number_format($troco,2,'.',''),
    //     ':desc'=>"Troco da venda #$vendaId"
    //   ]);
    // }
  }

  $pdo->commit();

  // sucesso
  back('Venda registrada com sucesso!', 1, ['venda_id'=>$vendaId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back('Falha ao registrar venda: ' . $e->getMessage());
}
