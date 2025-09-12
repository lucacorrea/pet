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

// Empresa logada
$empresaNome = empresa_nome_logada($pdo) ?: 'Minha Empresa';
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

// Parâmetro
$vendaId = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// Busca venda
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
        SELECT descricao, qtd, valor_unit, valor_total
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
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e — Venda #<?= (int)$vendaId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Layout para bobina 80mm e A4 */
        :root {
            --paper-w: 80mm;
            --txt: 12px;
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background: #f5f6f8;
            margin: 0;
        }

        .wrap {
            max-width: 900px;
            margin: 18px auto;
            padding: 0 12px;
        }

        .toolbar {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn.primary {
            background: #0ea5e9;
            color: #fff;
            border-color: #0ea5e9;
        }

        .btn:hover {
            filter: brightness(0.98);
        }

        .ticket {
            width: var(--paper-w);
            margin: 0 auto;
            background: #fff;
            color: #111827;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
            padding: 14px;
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
        }

        .hr {
            border-top: 1px dashed #cbd5e1;
            margin: 8px 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px;
            font-size: var(--txt);
        }

        .monospace {
            font-variant-numeric: tabular-nums;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
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
        }

        td.qty,
        td.vu,
        td.tot {
            text-align: right;
            white-space: nowrap;
        }

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

        .qrcode {
            height: 110px;
            border: 1px dashed #94a3b8;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .tiny {
            font-size: 10px;
            line-height: 1.3;
        }

        @media print {
            body {
                background: #fff;
            }

            .wrap {
                margin: 0;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .ticket {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>

<body>

    <div class="wrap">
        <div class="toolbar">
            <button class="btn primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        <?php if (!$venda): ?>
            <div class="ticket">
                <div class="center">
                    <div class="title">Venda não encontrada</div>
                    <div class="muted">Verifique o código ou tente novamente.</div>
                    <div class="hr"></div>
                    <a class="btn" href="./vendaRapida.php"><i class="bi bi-arrow-left"></i> Voltar para Venda Rápida</a>
                </div>
            </div>
        <?php else: ?>
            <div class="ticket">
                <!-- Cabeçalho -->
                <div class="center">
                    <div class="title"><?= h($empresaNome) ?></div>
                    <div class="muted">CNPJ: <?= h(preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '\1.\2.\3/\4-\5', $empresaCnpj)) ?></div>
                </div>

                <div class="hr"></div>

                <div class="grid muted">
                    <div>Venda: <strong class="monospace">#<?= (int)$venda['id'] ?></strong></div>
                    <div><?= h($venda['criado_quando']) ?></div>
                    <div>Operador:</div>
                    <div><?= h($venda['vendedor_cpf']) ?></div>
                </div>

                <div class="hr"></div>

                <!-- Itens -->
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th class="qty">Qtd</th>
                            <th class="vu">Vl. Unit</th>
                            <th class="tot">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $it): ?>
                            <tr>
                                <td><?= h($it['descricao']) ?></td>
                                <td class="qty monospace"><?= number_format((float)$it['qtd'], 3, ',', '.') ?></td>
                                <td class="vu monospace"><?= money($it['valor_unit']) ?></td>
                                <td class="tot monospace"><?= money($it['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hr"></div>

                <!-- Totais -->
                <div class="totais">
                    <div class="row"><span class="muted">Subtotal</span><span class="monospace"><?= money($venda['total_bruto']) ?></span></div>
                    <div class="row"><span class="muted">Desconto</span><span class="monospace"><?= money($venda['desconto']) ?></span></div>
                    <div class="row grand"><strong>TOTAL</strong><strong class="monospace"><?= money($venda['total_liquido']) ?></strong></div>
                    <div class="row"><span class="muted">Forma de Pagamento</span><span class="monospace" style="text-transform:uppercase;"><?= h($venda['forma_pagamento']) ?></span></div>
                </div>

                <div class="hr"></div>

                <!-- DANFE / QRCode (placeholder) -->
                <div class="center">
                    <div class="qrcode tiny">
                        QR Code da NFC-e (quando autorizada)
                    </div>
                    <div class="tiny muted" style="margin-top:6px;">
                        DANFE NFC-e — Documento Auxiliar. <br>
                        Válido somente com a NFC-e autorizada pela SEFAZ.
                    </div>
                </div>

                <div class="hr"></div>

                <div class="center tiny">Obrigado pela preferência!</div>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>