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

/* ========================= helpers ========================= */
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
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
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
    if ($c === '') return '';
    return trim(implode(' ', str_split($c, 4)));
}

/* ========================= empresa (empresas_peca) ========================= */
$empresaCnpj = onlynum($_SESSION['user_empresa_cnpj'] ?? '');
$empresa = [
    'cnpj' => $empresaCnpj,
    'nome_fantasia' => '',
    'razao_social' => '',
    'telefone' => '',
    'email' => '',
    'endereco' => '',
    'cidade' => '',
    'estado' => '',
    'cep' => '',
];

if ($empresaCnpj) {
    try {
        $stE = $pdo->prepare("
      SELECT cnpj, nome_fantasia, razao_social, telefone, email, endereco, cidade, estado, cep
      FROM empresas_peca
      WHERE cnpj = :c
      LIMIT 1
    ");
        $stE->execute([':c' => $empresaCnpj]);
        if ($row = $stE->fetch(PDO::FETCH_ASSOC)) {
            foreach ($empresa as $k => $v) {
                if (array_key_exists($k, $row) && $row[$k] !== null) $empresa[$k] = (string)$row[$k];
            }
        }
    } catch (Throwable $e) { /* silencioso */
    }
}

$empresaNome = $empresa['nome_fantasia'] ?: ($empresa['razao_social'] ?: 'Minha Empresa');
$linhaEndereco = trim($empresa['endereco'] ?: '');
$linhaCidadeUf = trim(($empresa['cidade'] ?: '') . ($empresa['estado'] ? ' / ' . $empresa['estado'] : ''));
$linhaCep = $empresa['cep'] ? ('CEP: ' . $empresa['cep']) : '';
$linhaContato = trim(($empresa['telefone'] ? ('Fone: ' . $empresa['telefone']) : '') .
    (($empresa['telefone'] && $empresa['email']) ? ' • ' : '') .
    ($empresa['email'] ? ('E-mail: ' . $empresa['email']) : ''));

/* ========================= params NFC-e / tela ========================= */
$vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$chave     = $_GET['chave']    ?? '';
$numero    = $_GET['numero']   ?? '';
$serie     = $_GET['serie']    ?? '';
$ambiente  = $_GET['ambiente'] ?? ''; // PRODUÇÃO/HOMOLOGAÇÃO
$protocolo = $_GET['protocolo'] ?? '';
$autEm     = $_GET['autorizado_em'] ?? '';
$obs       = $_GET['obs'] ?? '';
$lei12741  = isset($_GET['tributos_aprox']) ? (float)str_replace(',', '.', $_GET['tributos_aprox']) : null;

/* ========================= consumidor ========================= */
$consDoc  = $_GET['consumidor_doc']  ?? '';
$consNome = $_GET['consumidor_nome'] ?? '';
$consLabel = (strlen(onlynum($consDoc)) === 14 ? 'CNPJ' : 'CPF');

/* ========================= venda + itens ========================= */
$venda = null;
$itens = [];
if ($vendaId > 0 && $empresaCnpj) {
    try {
        $st = $pdo->prepare("
      SELECT id, empresa_cnpj, vendedor_cpf, origem, status, total_bruto, desconto, total_liquido, forma_pagamento,
             DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i:%s') AS criado_quando
      FROM vendas_peca
      WHERE id=:id AND empresa_cnpj=:c
      LIMIT 1
    ");
        $st->execute([':id' => $vendaId, ':c' => $empresaCnpj]);
        $venda = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($venda) {
            $sti = $pdo->prepare("
        SELECT item_id, descricao, qtd, valor_unit, valor_total
        FROM venda_itens_peca
        WHERE venda_id=:v
        ORDER BY id
      ");
            $sti->execute([':v' => $vendaId]);
            $itens = $sti->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $venda = null;
        $itens = [];
    }
}

/* ========================= totais/pagamentos ========================= */
$qtdTotal = 0.0;
foreach ($itens as $i) $qtdTotal += (float)$i['qtd'];
$subtotal   = (float)($venda['total_bruto'] ?? 0);
$desconto   = (float)($venda['desconto'] ?? 0);
$valorTotal = (float)($venda['total_liquido'] ?? 0);
$forma      = strtoupper((string)($venda['forma_pagamento'] ?? ''));

$pgBrutos = (array)($_GET['pg'] ?? []); // ex.: pg[]=pix:10.00&pg[]=debito:5.00
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
    $pagamentos[] = ['tipo' => strtolower($forma ?: 'dinheiro'), 'valor' => $valorTotal];
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
        /* ===== TELA ===== */
        :root {
            --paper-w-screen: 460px;
            --paper-w-print: 80mm;
            --txt: 12px;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background: #f5f6f8;
            margin: 0;
            padding-bottom: 80px;
            color: #111827;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .wrap {
            max-width: 1024px;
            margin: 16px auto;
            padding: 0 12px;
        }

        .ticket {
            width: min(var(--paper-w-screen), 96vw);
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 26px rgba(0, 0, 0, .08);
            padding: 16px 14px;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #6b7280;
        }

        .title {
            font-weight: 800;
            font-size: 14px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .subtle {
            font-size: 11px;
        }

        .monospace {
            font-variant-numeric: tabular-nums;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        .hr {
            border-top: 1px dashed #cbd5e1;
            margin: 10px 0;
        }

        .stamp {
            font-size: 11px;
            padding: 6px 10px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            display: inline-block;
            background: #f8fafc;
        }

        .grid-auto {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 6px 10px;
            font-size: var(--txt);
        }

        .lbl {
            color: #6b7280;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--txt);
        }

        th,
        td {
            padding: 6px 0;
        }

        th {
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-weight: 600;
        }

        td.qty,
        td.vu,
        td.tot {
            text-align: right;
            white-space: nowrap;
        }

        td.code {
            width: 48px;
            color: #6b7280;
        }

        .totais .row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }

        .grand {
            font-size: 14px;
            font-weight: 700;
        }

        .chave {
            font-size: 12px;
            letter-spacing: .4px;
            text-align: left;
        }

        .chave .line {
            display: block;
            line-height: 1.6;
        }

        .tiny {
            font-size: 10px;
            line-height: 1.3;
        }

        .obs {
            font-size: 11px;
            white-space: pre-wrap;
        }

        /* Barra fixa */
        .bottom-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffffdd;
            backdrop-filter: blur(6px);
            border-top: 1px solid #e5e7eb;
            padding: 10px 14px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            z-index: 999;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .02);
            font-weight: 600;
        }

        .btn.primary {
            background: #0ea5e9;
            color: #fff;
            border-color: #0ea5e9;
        }

        .btn:hover {
            filter: brightness(0.98);
        }

        /* ===== IMPRESSÃO ===== */
        @page {
            size: var(--paper-w-print) auto;
            margin: 2mm;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .wrap {
                margin: 0;
                padding: 0;
            }

            .ticket {
                width: var(--paper-w-print);
                margin: 0 !important;
                /* alinhado à esquerda */
                border-radius: 0;
                box-shadow: none;
                page-break-inside: avoid;
            }

            .bottom-bar {
                display: none !important;
            }

            .hr {
                margin: 8px 0;
            }
        }
    </style>
</head>

<body>

    <div class="wrap">
        <?php if (!$venda): ?>
            <div class="ticket center">
                <div class="title">Venda não encontrada</div>
                <div class="muted">Verifique o código ou tente novamente.</div>
                <div class="hr"></div>
                <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar para Venda Rápida</a>
            </div>
        <?php else: ?>
            <div class="ticket">

                <!-- EMITENTE (da empresas_peca) -->
                <div class="center" style="margin-bottom:6px;">
                    <div class="title"><?= h($empresaNome) ?></div>
                    <div class="muted">CNPJ: <?= h(fmt_doc($empresa['cnpj'])) ?></div>
                    <?php if ($linhaEndereco): ?><div class="muted subtle"><?= h($linhaEndereco) ?></div><?php endif; ?>
                    <?php if ($linhaCidadeUf || $linhaCep): ?>
                        <div class="muted subtle"><?= h(trim($linhaCidadeUf . ($linhaCidadeUf && $linhaCep ? ' — ' : '') . $linhaCep)) ?></div>
                    <?php endif; ?>
                    <?php if ($linhaContato): ?><div class="muted subtle"><?= h($linhaContato) ?></div><?php endif; ?>
                </div>

                <div class="hr"></div>

                <div class="center"><span class="stamp">NFC-e não permite aproveitamento de crédito de ICMS</span></div>

                <div class="hr"></div>

                <!-- DADOS DA NFC-e -->
                <div class="grid-auto">
                    <div class="lbl">Venda:</div>
                    <div class="monospace">#<?= (int)$venda['id'] ?></div>
                    <div class="lbl">Número:</div>
                    <div class="monospace"><?= h($numero ?: '—') ?></div>
                    <div class="lbl">Série:</div>
                    <div class="monospace"><?= h($serie ?: '—') ?></div>
                    <div class="lbl">Emissão:</div>
                    <div class="monospace"><?= h($emissaoStr) ?></div>
                    <div class="lbl">Ambiente:</div>
                    <div><?= h($ambiente ?: '—') ?></div>
                    <div class="lbl">Vendedor (CPF):</div>
                    <div class="monospace"><?= h(fmt_doc($venda['vendedor_cpf'] ?? '')) ?></div>
                </div>

                <?php if ($chave): ?>
                    <div class="hr"></div>
                    <div>
                        <div class="muted subtle">CHAVE DE ACESSO</div>
                        <?php
                        $cFmt = fmt_chave($chave);
                        $parts = $cFmt ? explode(' ', $cFmt) : [];
                        $lines = $parts ? array_chunk($parts, 11) : [];
                        ?>
                        <?php if ($lines): ?>
                            <div class="chave monospace">
                                <?php foreach ($lines as $ln): ?>
                                    <span class="line"><?= h(implode(' ', $ln)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="hr"></div>

                <!-- CONSUMIDOR -->
                <div class="grid-auto">
                    <div class="lbl">Consumidor:</div>
                    <div><?= h($consNome ?: 'Não informado') ?></div>
                    <div class="lbl"><?= $consLabel ?>:</div>
                    <div class="monospace"><?= h(fmt_doc($consDoc)) ?></div>
                </div>

                <div class="hr"></div>

                <!-- ITENS -->
                <table>
                    <thead>
                        <tr>
                            <th class="code">Cód</th>
                            <th>Descrição</th>
                            <th class="qty">Qtde</th>
                            <th class="vu">V. Unit</th>
                            <th class="tot">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $it): ?>
                            <tr>
                                <td class="code monospace"><?= (int)($it['item_id'] ?? 0) ?: '—' ?></td>
                                <td><?= h($it['descricao']) ?></td>
                                <td class="qty monospace"><?= nf($it['qtd'], 3) ?></td>
                                <td class="vu monospace"><?= money($it['valor_unit']) ?></td>
                                <td class="tot monospace"><?= money($it['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hr"></div>

                <!-- TOTAIS -->
                <div class="totais">
                    <div class="row"><span class="muted">Qtde total de itens</span><span class="monospace"><?= nf($qtdTotal, 3) ?></span></div>
                    <div class="row"><span class="muted">Subtotal</span><span class="monospace"><?= money($subtotal) ?></span></div>
                    <div class="row"><span class="muted">Desconto</span><span class="monospace"><?= money($desconto) ?></span></div>
                    <div class="row grand"><span>TOTAL</span><span class="monospace"><?= money($valorTotal) ?></span></div>
                </div>

                <div class="hr"></div>

                <!-- PAGAMENTOS -->
                <div class="grid-auto">
                    <div class="lbl">Forma(s) de Pagamento:</div>
                    <div>
                        <?php foreach ($pagamentos as $p): ?>
                            <div class="monospace"><?= strtoupper($p['tipo']) ?> — <?= money($p['valor']) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="lbl">Total Pago:</div>
                    <div class="monospace"><?= money($totalPg) ?></div>
                    <div class="lbl">Troco:</div>
                    <div class="monospace"><?= money($troco) ?></div>
                </div>

                <?php if ($lei12741 !== null): ?>
                    <div class="hr"></div>
                    <div class="tiny">Tributos aproximados (Lei 12.741/2012): <strong class="monospace"><?= money($lei12741) ?></strong></div>
                <?php endif; ?>

                <?php if ($obs): ?>
                    <div class="hr"></div>
                    <div class="obs"><strong>Informações Complementares:</strong><br><?= h($obs) ?></div>
                <?php endif; ?>

                <?php if ($protocolo || $autEm): ?>
                    <div class="hr"></div>
                    <div class="tiny">
                        <?php if ($protocolo): ?>
                            Protocolo de Autorização: <span class="monospace"><?= h($protocolo) ?></span>
                        <?php endif; ?>
                        <?php if ($autEm): ?>
                            <?php if ($protocolo) echo ' — '; ?>
                            <?= h($autEm) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="hr"></div>

                <div class="tiny muted">
                    DANFE NFC-e — Documento Auxiliar. Válido somente com a NFC-e autorizada pela SEFAZ.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Barra inferior fixa (tela) -->
    <div class="bottom-bar">
        <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar para Consulta</a>
        <button class="btn primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>

</body>

</html>