<?php
// autoErp/lib/timezone_br.php
declare(strict_types=1);

/** CEP (somente dígitos) → UF (2 letras) */
function uf_by_cep(?string $cep): ?string {
  $cep = preg_replace('/\D+/', '', (string)$cep);
  if (strlen($cep) < 2) return null;
  $d2 = (int)substr($cep, 0, 2);

  // Faixas IBGE/Correios (2 dígitos iniciais → UF)
  $map = [
    // 01–06: SP
    [1,6,'SP'],
    // 07–09: SP interior ainda (mantemos SP)
    [7,9,'SP'],
    // 10–19: SP (todo SP cobre 01–19)
    [10,19,'SP'],
    // 20–28: RJ
    [20,28,'RJ'],
    // 29–29: ES
    [29,29,'ES'],
    // 30–39: MG
    [30,39,'MG'],
    // 40–48: BA
    [40,48,'BA'],
    // 49–49: SE
    [49,49,'SE'],
    // 50–56: PE
    [50,56,'PE'],
    // 57–57: AL
    [57,57,'AL'],
    // 58–58: PB
    [58,58,'PB'],
    // 59–59: RN
    [59,59,'RN'],
    // 60–69: CE (60–63 Fortaleza/CE; 64–69 interior CE – simples)
    [60,69,'CE'],
    // 70–73: DF/GO (70–73 Brasília/Entorno: tratamos como DF)
    [70,73,'DF'],
    // 74–76: GO
    [74,76,'GO'],
    // 77–79: TO
    [77,79,'TO'],
    // 80–87: PR
    [80,87,'PR'],
    // 88–89: SC
    [88,89,'SC'],
    // 90–99: RS
    [90,99,'RS'],
    // NORTE / CENTRO-OESTE restantes:
    // 68–69: AC/RO/AM/RR/PA/AP/MT/MS/MA/PI/PA etc. (simplificação abaixo)
  ];

  foreach ($map as [$a,$b,$uf]) {
    if ($d2 >= $a && $d2 <= $b) return $uf;
  }

  // Cobertura simplificada para Norte/Centro-Oeste por faixas mais comuns:
  if ($d2 >= 66 && $d2 <= 67) return 'MS';
  if ($d2 >= 64 && $d2 <= 65) return 'MT';
  if ($d2 == 69) return 'RO';
  if ($d2 == 68) return 'AC';
  if ($d2 == 69) return 'RO';
  if ($d2 == 69) return 'RO';
  if ($d2 == 69) return 'RO';
  if ($d2 >= 77 && $d2 <= 79) return 'TO';
  if ($d2 >= 66 && $d2 <= 68) return 'AM'; // (aproximação prática p/ AM)
  if ($d2 >= 68 && $d2 <= 68) return 'AC';
  if ($d2 >= 68 && $d2 <= 69) return 'AM'; // fallback

  // Se não bater, retorna null
  return null;
}

/** UF → timezone IANA do Brasil */
function br_timezone_by_uf(?string $uf): string {
  $uf = strtoupper(trim((string)$uf));
  $map = [
    'AC'=>'America/Rio_Branco',
    'AM'=>'America/Manaus',
    'RR'=>'America/Boa_Vista',
    'RO'=>'America/Porto_Velho',
    'MT'=>'America/Cuiaba',
    'MS'=>'America/Campo_Grande',
    'PA'=>'America/Belem',
    'AP'=>'America/Belem',
    'MA'=>'America/Fortaleza',
    'PI'=>'America/Fortaleza',
    'CE'=>'America/Fortaleza',
    'RN'=>'America/Fortaleza',
    'PB'=>'America/Fortaleza',
    'PE'=>'America/Recife',
    'AL'=>'America/Maceio',
    'SE'=>'America/Maceio',
    'BA'=>'America/Bahia',
    'TO'=>'America/Araguaina',
    'DF'=>'America/Sao_Paulo',
    'GO'=>'America/Sao_Paulo',
    'MG'=>'America/Sao_Paulo',
    'ES'=>'America/Sao_Paulo',
    'RJ'=>'America/Sao_Paulo',
    'SP'=>'America/Sao_Paulo',
    'PR'=>'America/Sao_Paulo',
    'SC'=>'America/Sao_Paulo',
    'RS'=>'America/Sao_Paulo',
  ];
  return $map[$uf] ?? 'America/Sao_Paulo';
}

/** Descobre a timezone da empresa a partir de: estado (se existir) ou CEP */
function empresa_timezone(PDO $pdo, string $empresaCnpj): DateTimeZone {
  $empresaCnpj = preg_replace('/\D+/', '', $empresaCnpj);
  $uf = null; $cep=null;
  try {
    $st = $pdo->prepare("SELECT estado, cep FROM empresas_peca WHERE cnpj = :c LIMIT 1");
    $st->execute([':c' => $empresaCnpj]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $uf  = $row['estado'] ?? null;
      $cep = $row['cep'] ?? null;
    }
  } catch (Throwable $e) { /* silencioso */ }

  if (!$uf && $cep) $uf = uf_by_cep($cep);
  $tzId = br_timezone_by_uf($uf);
  return new DateTimeZone($tzId);
}

/** “Agora” no fuso da empresa */
function empresa_now(PDO $pdo, string $empresaCnpj): DateTime {
  return new DateTime('now', empresa_timezone($pdo, $empresaCnpj));
}

/** Converte datetime (string 'Y-m-d H:i:s' ou DateTime) para texto no fuso da empresa */
function empresa_format_datetime(PDO $pdo, string $empresaCnpj, $dbDatetime, string $format='d/m/Y H:i'): string {
  // Troque para 'UTC' se o banco grava UTC:
  $serverTz = new DateTimeZone(date_default_timezone_get());
  $empTz    = empresa_timezone($pdo, $empresaCnpj);

  if ($dbDatetime instanceof DateTimeInterface) {
    $dt = new DateTime($dbDatetime->format('Y-m-d H:i:s'), $serverTz);
  } else {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$dbDatetime, $serverTz);
    if (!$dt) return (string)$dbDatetime;
  }
  $dt->setTimezone($empTz);
  return $dt->format($format);
}

/** Ajusta a sessão do MySQL para o offset atual da timezone da empresa */
function mysql_set_time_zone_for_empresa(PDO $pdo, string $empresaCnpj): void {
  $tz = empresa_timezone($pdo, $empresaCnpj);
  $now = new DateTime('now', $tz);
  $offsetSeconds = $tz->getOffset($now); // ex.: -10800
  $sign = $offsetSeconds >= 0 ? '+' : '-';
  $abs  = abs($offsetSeconds);
  $hh = str_pad((string)floor($abs/3600), 2, '0', STR_PAD_LEFT);
  $mm = str_pad((string)floor(($abs%3600)/60), 2, '0', STR_PAD_LEFT);
  $offset = "{$sign}{$hh}:{$mm}"; // ex.: -03:00
  try { $pdo->exec("SET time_zone = '{$offset}'"); } catch (Throwable $e) { /* ignora */ }
}
