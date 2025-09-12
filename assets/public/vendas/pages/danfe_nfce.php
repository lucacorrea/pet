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

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
    $d = preg_replace('/\D+/', '', (string)$doc);
    if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
    return $doc ? (string)$doc : 'Não informado';
}
function fmt_chave($chave): string
{
    $c = preg_replace('/\D+/', '', (string)$chave);
    return trim(implode(' ', str_split($c, 4)));
}

// ====== Empresa logada (nome + CNPJ; demais campos são opcionais) ======
$empresaNome = empresa_nome_logada($pdo) ?: 'Minha Empresa';
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$empresaEndereco = ''; // se você tiver função/dados, pode preencher aqui

// ====== Parâmetros / dados adicionais da NFC-e (opcionais) ======
$vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$chave     = isset($_GET['chave']) ? (string)$_GET['chave'] : '';                  // chave de acesso (44 dígitos)
$numero    = isset($_GET['numero']) ? (string)$_GET['numero'] : '';                // número NFC-e
$serie     = isset($_GET['serie']) ? (string)$_GET['serie'] : '';                  // série NFC-e
$consDoc   = isset($_GET['consumidor_doc']) ? (string)$_GET['consumidor_doc'] : ''; // CPF/CNPJ consumidor
$consNome  = isset($_GET['consumidor_nome']) ? (string)$_GET['consumidor_nome'] : '';
$pagoIn    = isset($_GET['pago']) ? (float)str_replace(',', '.', $_GET['pago']) : null;
$trocoIn   = isset($_GET['troco']) ? (float)str_replace(',', '.', $_GET['troco']) : null;
$protocolo = isset($_GET['protocolo']) ? (string)$_GET['protocolo'] : '';
$autEm     = isset($_GET['autorizado_em']) ? (string)$_GET['autorizado_em'] : '';

// ====== Busca venda + itens ======
$venda = null;
$itens = [];
if ($vendaId > 0 && $empresaCnpj) {
    try {
        $st = $pdo->prepare("
      SELECT id, empresa_cnpj, vendedor_cpf, origem, status, total_bruto, desconto, total_liquido, forma_pagamento,
             DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i:%s') AS criado_quando, criado_em
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

// Derivados para impressão
$qtdTotal = 0.0;
foreach ($itens as $i) $qtdTotal += (float)$i['qtd'];
$valorTotal = $venda ? (float)$venda['total_liquido'] : 0.0;
$valorPago  = $pagoIn !== null ? $pagoIn : $valorTotal;
$troco      = $trocoIn !== null ? $trocoIn : max(0.0, $valorPago - $valorTotal);
$emissaoStr = $venda['criado_quando'] ?? date('d/m/Y H:i:s');
$forma      = strtoupper((string)($venda['forma_pagamento'] ?? ''));
$consLabel  = strlen(preg_replace('/\D+/', '', $consDoc)) === 14 ? 'CNPJ' : 'CPF';
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e — Venda #<?= (int)$vendaId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ---------- Layout base (tela) ---------- */
        :root {
            --paper-w-screen: 420px;
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
            padding-bottom: 76px;
            /* espaço para a barra fixa */
            color: #111827;
        }

        .wrap {
            max-width: 980px;
            margin: 18px auto;
            padding: 0 12px;
        }

        .ticket {
            width: min(92vw, var(--paper-w-screen));
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 22px rgba(0, 0, 0, .09);
            padding: 16px 14px;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #6b7280;
        }

        .title {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .3px;
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

        /* Cabeçalho “carimbo” */
        .stamp {
            font-size: 11px;
            padding: 6px 10px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            display: inline-block;
            background: #f8fafc;
        }

        /* Informações de emissão (grid 2 colunas) */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px;
            font-size: var(--txt);
        }

        .info-grid .lbl {
            color: #6b7280;
        }

        /* Tabela de itens */
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
        td.un,
        td.vu,
        td.tot {
            text-align: right;
            white-space: nowrap;
        }

        td.code {
            width: 44px;
            color: #6b7280;
        }

        /* Totais */
        .totais .row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }

        .totais .row strong {
            font-weight: 700;
        }

        .grand {
            font-size: 14px;
        }

        /* Chave de acesso formatada em blocos */
        .chave {
            font-size: 12px;
            letter-spacing: .4px;
            display: block;
            text-align: center;
        }

        .chave .line {
            display: block;
            line-height: 1.6;
        }

        .tiny {
            font-size: 10px;
            line-height: 1.3;
        }

        /* Barra inferior fixa */
        .bottom-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffffcc;
            backdrop-filter: blur(6px);
            border-top: 1px solid #e5e7eb;
            padding: 10px 14px;
            display: flex;
            gap: 10px;
            justify-content: center;
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

        /* ---------- Impressão (80mm) ---------- */
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
                box-shadow: none;
                border-radius: 0;
            }

            .bottom-bar {
                display: none !important;
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
                <!-- Cabeçalho -->
                <div class="center">
                    <div class="title" style="text-transform:uppercase;"><?= h($empresaNome) ?></div>
                    <div class="muted">CNPJ: <?= h(fmt_doc($empresaCnpj)) ?></div>
                    <?php if ($empresaEndereco): ?>
                        <div class="muted subtle"><?= h($empresaEndereco) ?></div>
                    <?php endif; ?>
                </div>

                <div class="hr"></div>

                <div class="center"><span class="stamp">NFC-e não permite aproveitamento de crédito de ICMS</span></div>

                <div class="hr"></div>

                <!-- Itens -->
                <table>
                    <thead>
                        <tr>
                            <th class="code">Cód</th>
                            <th>Descrição</th>
                            <th class="qty">Qtde</th>
                            <th class="un">Un.</th>
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
                                <td class="un">—</td>
                                <td class="vu monospace"><?= money($it['valor_unit']) ?></td>
                                <td class="tot monospace"><?= money($it['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hr"></div>

                <!-- Resumo / Totais -->
                <div class="totais">
                    <div class="row"><span class="muted">Qtde total de itens</span><span class="monospace"><?= nf($qtdTotal, 3) ?></span></div>
                    <div class="row"><span class="muted">Valor total R$</span><span class="monospace"><?= money($venda['total_bruto']) ?></span></div>
                    <div class="row"><span class="muted">Desconto</span><span class="monospace"><?= money($venda['desconto']) ?></span></div>
                    <div class="row grand"><strong>TOTAL</strong><strong class="monospace"><?= money($valorTotal) ?></strong></div>
                    <div class="row"><span class="muted">Forma de pagamento</span><span class="monospace"><?= h($forma ?: '—') ?></span></div>
                    <div class="row"><span class="muted">Valor pago</span><span class="monospace"><?= money($valorPago) ?></span></div>
                    <div class="row"><span class="muted">Troco</span><span class="monospace"><?= money($troco) ?></span></div>
                </div>

                <div class="hr"></div>

                <!-- Dados fiscais / Emissão -->
                <div class="info-grid">
                    <div><span class="lbl">Nº:</span> <span class="monospace"><?= h($numero ?: '—') ?></span></div>
                    <div><span class="lbl">Série:</span> <span class="monospace"><?= h($serie ?: '—') ?></span></div>
                    <div><span class="lbl">Emissão:</span> <span class="monospace"><?= h($emissaoStr) ?></span></div>
                    <div><span class="lbl">Vendedor (CPF):</span> <span class="monospace"><?= h(fmt_doc($venda['vendedor_cpf'] ?? '')) ?></span></div>
                </div>

                <?php if ($chave): ?>
                    <div class="hr"></div>
                    <div class="center">
                        <div class="muted subtle">CHAVE DE ACESSO</div>
                        <?php
                        $chFmt = fmt_chave($chave);
                        // quebra em 4 linhas para lembrar o modelo do seu print
                        $parts = explode(' ', $chFmt);
                        $lines = array_chunk($parts, 11); // ~44 dígitos => 11 blocos de 4
                        ?>
                        <div class="chave monospace">
                            <?php foreach ($lines as $ln): ?>
                                <span class="line"><?= h(implode(' ', $ln)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="hr"></div>

                <!-- Consumidor -->
                <div class="info-grid">
                    <div><span class="lbl">Consumidor:</span> <span><?= h($consNome ?: 'Não informado') ?></span></div>
                    <div><span class="lbl"><?= $consLabel ?>:</span> <span class="monospace"><?= h(fmt_doc($consDoc)) ?></span></div>
                </div>

                <?php if ($protocolo || $autEm): ?>
                    <div class="hr"></div>
                    <div class="center tiny">
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

                <div class="center tiny muted">DANFE NFC-e — Documento Auxiliar<br>Válido somente com a NFC-e autorizada pela SEFAZ.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Barra fixa inferior -->
    <div class="bottom-bar">
        <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar para Consulta</a>
        <button class="btn primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>

</body>

</html>