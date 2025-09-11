<?php
// autoErp/lib/bootstrap_debug.php
declare(strict_types=1);

// Ligue quando quiser ver o erro na tela (DESLIGUE em produção!)
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);

if (APP_DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/** Loga erros no storage/app.log */
function app_log_err(Throwable $e, string $ctx = ''): void {
  $file = __DIR__ . '/../storage/app.log';
  $msg  = '['.date('c')."] {$ctx} ".$e->getMessage().' in '.$e->getFile().':'.$e->getLine().PHP_EOL
        . $e->getTraceAsString().PHP_EOL;
  @file_put_contents($file, $msg, FILE_APPEND);
}
