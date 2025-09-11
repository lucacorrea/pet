<?php
// autoErp/config/mail.php
// Envio de e-mail com PHP mail() (HTML)
// Requer servidor configurado (SPF/DKIM/DMARC no domínio).

/**
 * Envia e-mail HTML simples usando mail()
 *
 * @param string $to        Destinatário
 * @param string $subject   Assunto (UTF-8)
 * @param string $htmlBody  Corpo HTML
 * @param string $from      Remetente (mesmo domínio do site recomendado)
 * @param string $fromName  Nome do remetente
 * @return bool
 */
function enviar_email(
    string $to,
    string $subject,
    string $htmlBody,
    string $from = 'suporte@codegeek.dev.br',
    string $fromName = 'PetERP Suporte'
): bool {
    // Assunto em UTF-8
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Cabeçalhos
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . sprintf('%s <%s>', mb_encode_mimeheader($fromName, 'UTF-8', 'B'), $from);
    $headers[] = 'Reply-To: ' . $from;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    // (opcional) categorização
    $headers[] = 'X-Category: transactional';
    $headersStr = implode("\r\n", $headers);

    // Envelope sender (evita “via” e melhora entregabilidade em Unix)
    $params = '';
    if (stripos(PHP_OS, 'WIN') !== 0) {
        $params = '-f' . $from;
    }

    return @mail($to, $encodedSubject, $htmlBody, $headersStr, $params);
}
