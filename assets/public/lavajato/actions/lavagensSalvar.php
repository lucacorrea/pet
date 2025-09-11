<?php
// autoErp/public/lavajato/actions/lavagensSalvar.php
declare(strict_types=1);

/*
  OBS: sua tabela lavagens_peca exige placa NOT NULL.
  Este action permite "sem placa" gravando string vazia ('').
  Se quiser aceitar NULL de verdade, rode no banco:
    ALTER TABLE lavagens_peca MODIFY placa varchar(10) DEFAULT NULL;
*/

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_post(); // só POST
// dono, administrativo e caixa podem lançar lavagem rápida
guard_empresa_user(['dono', 'administrativo', 'caixa']);

/* ========= Conexão ========= */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/lavagemRapida.php?err=1&msg=' . urlencode('Conexão indisponível.'));
  exit;
}

/* ========= Helpers ========= */
function norm_cnpj(string $c): string
{
  return preg_replace('/\D+/', '', $c);
}
function norm_cpf(?string $c): string
{
  return preg_replace('/\D+/', '', (string)$c);
}
function back_to(string $path, array $qs = []): void
{
  $q = http_build_query($qs);
  header('Location: ' . $path . ($q ? ('?' . $q) : ''));
  exit;
}

/* ========= Roteia ========= */
$op = (string)($_POST['op'] ?? '');

switch ($op) {
  /* ======================= LAVAGEM RÁPIDA ======================= */
  case 'lav_rapida_nova': {
      // CSRF
      $csrf = (string)($_POST['csrf'] ?? '');
      if (empty($_SESSION['csrf_lavagem_rapida'])) {
        $_SESSION['csrf_lavagem_rapida'] = bin2hex(random_bytes(32));
      }
      $vm['csrf'] = $_SESSION['csrf_lavagem_rapida'];


      // Empresa da sessão
      $empresaCnpj = norm_cnpj((string)($_SESSION['user_empresa_cnpj'] ?? ''));
      if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
        back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => 'Empresa não vinculada ao usuário.']);
      }

      // Entrada
      $lavadorCpf = norm_cpf($_POST['lavador_cpf'] ?? '');
      $placa      = strtoupper(trim((string)($_POST['placa'] ?? ''))); // pode vir vazio
      $modelo     = trim((string)($_POST['modelo'] ?? ''));
      $cor        = trim((string)($_POST['cor'] ?? ''));
      $catId      = (string)($_POST['categoria_id'] ?? '');            // pode vir '', '0' ou id
      $catIdInt   = ctype_digit($catId) ? (int)$catId : 0;
      $valor      = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
      $pgto       = trim((string)($_POST['forma_pagamento'] ?? 'dinheiro'));
      $obs        = trim((string)($_POST['observacoes'] ?? ''));

      // Regras leves: lavador CPF obrigatório; valor >= 0
      $errs = [];
      if ($lavadorCpf === '' || !preg_match('/^\d{11}$/', $lavadorCpf)) $errs[] = 'Selecione um lavador válido.';
      if ($valor < 0) $errs[] = 'Valor inválido.';
      if ($errs) {
        back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => implode(' ', $errs)]);
      }

      try {
        // Verifica se lavador existe para a empresa
        $st = $pdo->prepare("
        SELECT nome FROM lavadores_peca
         WHERE empresa_cnpj = :c AND cpf = :cpf AND ativo = 1
         LIMIT 1
      ");
        $st->execute([':c' => $empresaCnpj, ':cpf' => $lavadorCpf]);
        $lavRow = $st->fetch(PDO::FETCH_ASSOC);
        if (!$lavRow) {
          back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => 'Lavador não encontrado ou inativo.']);
        }

        // Busca categoria (opcional)
        $catNome = null;
        if ($catIdInt > 0) {
          $st = $pdo->prepare("
          SELECT nome, valor_padrao
            FROM categorias_lavagem_peca
           WHERE empresa_cnpj = :c AND id = :id AND ativo = 1
           LIMIT 1
        ");
          $st->execute([':c' => $empresaCnpj, ':id' => $catIdInt]);
          $c = $st->fetch(PDO::FETCH_ASSOC);
          if ($c) {
            $catNome = (string)$c['nome'];
            // se valor não informado (>0?), usa padrão
            if ($valor <= 0 && isset($c['valor_padrao'])) {
              $valor = (float)$c['valor_padrao'];
            }
          } else {
            // se não achar, zera o id para não gravar referência inválida
            $catIdInt = 0;
          }
        }

        // Permite sem placa: se não informado, manda string vazia
        if ($placa === '') $placa = '';

        // Defaults
        if ($valor < 0)   $valor = 0.00;
        if ($pgto === '') $pgto = 'dinheiro';

        // Insere
        $ins = $pdo->prepare("
        INSERT INTO lavagens_peca
          (empresa_cnpj, lavador_cpf, placa, modelo, cor,
           categoria_id, categoria_nome, valor, forma_pagamento,
           status, checkin_at, observacoes, criado_em)
        VALUES
          (:c, :lavcpf, :placa, :modelo, :cor,
           :catid, :catnome, :valor, :pgto,
           'aberta', NOW(), :obs, NOW())
      ");
        $ok = $ins->execute([
          ':c'       => $empresaCnpj,
          ':lavcpf'  => $lavadorCpf,
          ':placa'   => $placa,                 // pode ser ''
          ':modelo'  => ($modelo ?: null),
          ':cor'     => ($cor ?: null),
          ':catid'   => ($catIdInt > 0 ? $catIdInt : null),
          ':catnome' => ($catNome ?: null),
          ':valor'   => $valor,
          ':pgto'    => $pgto,
          ':obs'     => ($obs ?: null),
        ]);

        if (!$ok) {
          back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => 'Falha ao salvar lavagem.']);
        }

        back_to('../pages/lavagemRapida.php', ['ok' => 1, 'msg' => 'Lavagem iniciada com sucesso.']);
      } catch (Throwable $e) {
        $msg = 'Erro ao salvar lavagem.';
        if (defined('APP_DEBUG') && APP_DEBUG) $msg .= ' Detalhes: ' . $e->getMessage();
        back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => $msg]);
      }

      break;
    }

  default:
    back_to('../pages/lavagemRapida.php', ['err' => 1, 'msg' => 'Operação inválida.']);
}
