<?php
// autoErp/public/vendas/pages/danfe_nfce.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!isset($pdo) || !($pdo instanceof PDO)) die('Conexão indisponível.');
require_once __DIR__ . '/../../../lib/util.php';

/* ------------ helpers ------------ */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function onlynum($s)
{
    return preg_replace('/\D+/', '', (string)$s);
}
function money($v): string
{
    return number_format((float)$v, 2, ',', '.');
}
function moneyR($v): string
{
    return 'R$ ' . money($v);
}
function nf($v, int $d = 3): string
{
    return number_format((float)$v, $d, ',', '.');
}
function fmt_doc($doc): string
{
    $d = onlynum($doc);
    if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
    return $doc ? (string)$doc : 'Não informado';
}
function fmt_chave($chave): string
{
    $c = onlynum($chave);
    return $c ? trim(implode(' ', str_split($c, 4))) : '';
}

/* ------------ empresa ------------ */
$empresaCnpj = onlynum($_SESSION['user_empresa_cnpj'] ?? '');
$emp = ['cnpj' => '', 'nome_fantasia' => '', 'razao_social' => '', 'telefone' => '', 'email' => '', 'endereco' => '', 'cidade' => '', 'estado' => '', 'cep' => ''];
if ($empresaCnpj) {
    try {
        $st = $pdo->prepare("SELECT cnpj,nome_fantasia,razao_social,telefone,email,endereco,cidade,estado,cep
                         FROM empresas_peca WHERE cnpj=:c LIMIT 1");
        $st->execute([':c' => $empresaCnpj]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) foreach ($emp as $k => $_) $emp[$k] = (string)($row[$k] ?? '');
    } catch (Throwable $e) {
    }
    $emp['cnpj'] = $empresaCnpj;
}
$emitNome = $emp['nome_fantasia'] ?: ($emp['razao_social'] ?: 'EMPRESA');

/* ------------ params opcionais ------------ */
$vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$numero    = $_GET['numero']   ?? '';
$serie     = $_GET['serie']    ?? '';
$chave     = $_GET['chave']    ?? '';
$ambiente  = $_GET['ambiente'] ?? '';
$protocolo = $_GET['protocolo'] ?? '';
$autEm     = $_GET['autorizado_em'] ?? '';
$obs       = $_GET['obs'] ?? '';
$lei12741  = isset($_GET['tributos_aprox']) ? (float)str_replace(',', '.', $_GET['tributos_aprox']) : null;
$defaultUn = $_GET['un'] ?? 'un';

/* ------------ consumidor ------------ */
$consDoc  = $_GET['consumidor_doc']  ?? '';
$consNome = $_GET['consumidor_nome'] ?? '';
$consLabel = (strlen(onlynum($consDoc)) === 14 ? 'CNPJ' : 'CPF');

/* ------------ venda + itens ------------ */
$venda = null;
$itens = [];
if ($vendaId > 0 && $empresaCnpj) {
    try {
        $st = $pdo->prepare("SELECT id,empresa_cnpj,vendedor_cpf,total_bruto,desconto,total_liquido,forma_pagamento,
                              DATE_FORMAT(criado_em,'%d/%m/%Y %H:%i:%s') AS criado_quando
                       FROM vendas_peca WHERE id=:id AND empresa_cnpj=:c LIMIT 1");
        $st->execute([':id' => $vendaId, ':c' => $empresaCnpj]);
        $venda = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($venda) {
            $sti = $pdo->prepare("SELECT item_id,descricao,qtd,valor_unit,valor_total
                          FROM venda_itens_peca WHERE venda_id=:v ORDER BY id");
            $sti->execute([':v' => $vendaId]);
            $itens = $sti->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $venda = null;
        $itens = [];
    }
}

/* ------------ totais/pagamentos ------------ */
$qtdTotal = 0.0;
foreach ($itens as $i) $qtdTotal += (float)$i['qtd'];
$subtotal   = (float)($venda['total_bruto'] ?? 0);
$desconto   = (float)($venda['desconto'] ?? 0);
$valorTotal = (float)($venda['total_liquido'] ?? 0);
$formaVenda = strtoupper((string)($venda['forma_pagamento'] ?? ''));
$pgBrutos = (array)($_GET['pg'] ?? []);
$pagamentos = [];
$totalPg = 0;
foreach ($pgBrutos as $raw) {
    [$tipo, $val] = array_map('trim', explode(':', (string)$raw, 2) + ['', '0']);
    $v = (float)str_replace(',', '.', $val);
    if ($tipo !== '' && $v > 0) {
        $pagamentos[] = ['tipo' => $tipo, 'valor' => $v];
        $totalPg += $v;
    }
}
if (!$pagamentos) {
    $pagamentos[] = ['tipo' => strtolower($formaVenda ?: 'dinheiro'), 'valor' => $valorTotal];
    $totalPg = $valorTotal;
}
$troco = max(0.0, $totalPg - $valorTotal);
$emissaoStr = $venda['criado_quando'] ?? date('d/m/Y H:i:s');
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e — Venda #<?= (int)$vendaId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --paper-w-print: 80mm;
            /* mude para 58mm se sua bobina for 58 */
        }

        /* ===== Tela ===== */
        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            padding-bottom: 80px;
            background: #f3f4f6;
            color: #111827;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }

        .wrap {
            max-width: 1100px;
            margin: 18px auto;
            padding: 0 12px
        }

        /* >>> mesma largura/estilo da impressão, mas CENTRALIZADO na tela */
        .cupom {
            width: var(--paper-w-print);
            /* mesma largura da impressão */
            margin: 0 auto;
            /* centraliza na tela */
            background: #fff;
            border: 1px dashed #cbd5e1;
            /* visual de DANFE */
            border-radius: 8px;
            padding: 12px 10px;
            font-family: var(--mono);
            font-size: 12px;
            line-height: 1.38;
            box-shadow: none;
            /* sem cartão/shadow */
        }

        .center {
            text-align: center
        }

        .muted {
            color: #6b7280
        }

        .title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px
        }

        /* linha tracejada larga */
        .linha {
            height: 1px;
            background:
                repeating-linear-gradient(90deg, #cbd5e1 0, #cbd5e1 6px, transparent 6px, transparent 10px);
            margin: 10px 0
        }

        .pill {
            display: inline-block;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 6px 10px;
            background: #f9fafb
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            padding: 6px 0
        }

        th {
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-weight: 700
        }

        td.r {
            text-align: right;
            white-space: nowrap
        }

        .cod {
            width: 44px;
            color: #6b7280
        }

        .totais .row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0
        }

        .totais .grand {
            font-weight: 800
        }

        /* Barra inferior fixa na tela */
        .bottom {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999;
            background: #ffffffdd;
            backdrop-filter: blur(6px);
            border-top: 1px solid #e5e7eb;
            padding: 10px 14px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer
        }

        .btn.primary {
            background: #0ea5e9;
            color: #fff;
            border-color: #0ea5e9
        }

        /* ===== Impressão: ALINHADO À ESQUERDA ===== */
        @page {
            size: var(--paper-w-print) auto;
            margin: 2mm;
        }

        @media print {
            body {
                background: #fff;
                padding: 0
            }

            .wrap {
                margin: 0;
                padding: 0
            }

            .cupom {
                width: var(--paper-w-print);
                margin: 0 !important;
                /* >>> encosta à ESQUERDA no papel */
                border-radius: 0;
                box-shadow: none
            }

            .bottom {
                display: none !important
            }

            .linha {
                margin: 8px 0
            }
        }

        /* fallback: em telas muito estreitas, deixa com margens */
        @media (max-width:420px) {
            .cupom {
                width: calc(100vw - 24px);
            }
        }
    </style>
</head>

<body>

    <div class="wrap">
        <?php if (!$venda): ?>
            <div class="cupom center">
                <div class="title">Venda não encontrada</div>
                <div class="muted">Verifique o código e tente novamente.</div>
                <div class="linha"></div>
                <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        <?php else: ?>
            <div class="cupom">
                <!-- Cabeçalho -->
                <div class="center" style="margin-bottom:6px">
                    <div class="title"><?= h($emitNome) ?></div>
                    <div>CNPJ: <?= h(fmt_doc($emp['cnpj'])) ?></div>
                    <?php if ($emp['endereco']): ?><div class="muted"><?= h($emp['endereco']) ?></div><?php endif; ?>
                    <div class="muted">
                        <?= h(trim(($emp['cidade'] ?: '') . ($emp['estado'] ? ' / ' . $emp['estado'] : '') . ($emp['cep'] ? ' — CEP: ' . $emp['cep'] : ''))) ?>
                    </div>
                    <?php if ($emp['telefone'] || $emp['email']): ?>
                        <div class="muted">
                            <?= $emp['telefone'] ? 'Fone: ' . h($emp['telefone']) : '' ?>
                            <?= ($emp['telefone'] && $emp['email']) ? ' • ' : '' ?>
                            <?= $emp['email'] ? 'E-mail: ' . h($emp['email']) : '' ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="linha"></div>
                <div class="center"><span class="pill">NFC-e não permite aproveitamento de crédito de ICMS</span></div>
                <div class="linha"></div>

                <!-- Itens -->
                <table>
                    <thead>
                        <tr>
                            <th class="cod">Cód</th>
                            <th>Descrição</th>
                            <th class="r">Qtde</th>
                            <th class="r">Un.</th>
                            <th class="r">V. Unit</th>
                            <th class="r">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $it): ?>
                            <tr>
                                <td class="cod"><?= (int)($it['item_id'] ?? 0) ?: '—' ?></td>
                                <td><?= h($it['descricao']) ?></td>
                                <td class="r"><?= nf($it['qtd'], 3) ?></td>
                                <td class="r"><?= h(strtolower($defaultUn)) ?></td>
                                <td class="r"><?= money($it['valor_unit']) ?></td>
                                <td class="r"><?= money($it['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="linha"></div>

                <!-- Totais -->
                <div class="totais">
                    <div class="row"><span>QTD TOTAL DE ITENS</span><span><?= nf($qtdTotal, 3) ?></span></div>
                    <div class="row"><span>SUBTOTAL</span><span><?= moneyR($subtotal) ?></span></div>
                    <div class="row"><span>DESCONTO</span><span><?= moneyR($desconto) ?></span></div>
                    <div class="row grand"><span>TOTAL</span><span><?= moneyR($valorTotal) ?></span></div>
                </div>

                <div class="linha"></div>

                <!-- Pagamentos -->
                <div style="font-weight:700; margin-bottom:4px">FORMA DE PAGAMENTO</div>
                <div>
                    <?php foreach ($pagamentos as $p): ?>
                        <?= strtoupper($p['tipo']) ?> — <?= moneyR($p['valor']) ?><br>
                    <?php endforeach; ?>
                    VALOR PAGO&nbsp; <?= moneyR($totalPg) ?><br>
                    TROCO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <?= moneyR($troco) ?>
                </div>

                <div class="linha"></div>

                <!-- Dados NFC-e -->
                <div>
                    Nº: <?= h($numero ?: '—') ?> &nbsp; Série: <?= h($serie ?: '—') ?> &nbsp; Emissão: <?= h($emissaoStr) ?><br>
                    <?php if ($ambiente): ?>Ambiente: <?= h($ambiente) ?><br><?php endif; ?>
                <?php if ($chave): ?>
                    <div class="linha" style="margin:8px 0"></div>
                    <div class="muted">CHAVE DE ACESSO</div>
                    <?php
                    $cFmt = fmt_chave($chave);
                    $parts = $cFmt ? explode(' ', $cFmt) : [];
                    $lines = $parts ? array_chunk($parts, 11) : [];
                    ?>
                    <?php if ($lines): ?>
                        <div style="line-height:1.6">
                            <?php foreach ($lines as $ln): ?><?= h(implode(' ', $ln)) ?><br><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>

                <div class="linha"></div>

                <!-- Consumidor -->
                <div style="font-weight:700; margin-bottom:4px">CONSUMIDOR</div>
                <div><?= h($consNome ?: 'Não informado') ?></div>
                <div><?= $consLabel ?>: <?= h(fmt_doc($consDoc)) ?></div>

                <?php if ($lei12741 !== null): ?>
                    <div class="linha"></div>
                    Tributos aproximados (Lei 12.741/2012): <strong><?= moneyR($lei12741) ?></strong>
                <?php endif; ?>

                <?php if ($obs): ?>
                    <div class="linha"></div>
                    <div><strong>Observações:</strong><br><?= nl2br(h($obs)) ?></div>
                <?php endif; ?>

                <?php if ($protocolo || $autEm): ?>
                    <div class="linha"></div>
                    <?php if ($protocolo): ?>Protocolo de Autorização: <?= h($protocolo) ?><?php endif; ?>
                    <?php if ($autEm): ?><?= $protocolo ? ' — ' : '' ?><?= h($autEm) ?><?php endif; ?>
                <?php endif; ?>

                <div class="linha"></div>
                <div class="muted center">DANFE NFC-e — Documento Auxiliar. Válido somente com a NFC-e autorizada pela SEFAZ.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- menu inferior -->
    <div class="bottom">
        <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar</a>
        <button class="btn primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>

</body>

</html>