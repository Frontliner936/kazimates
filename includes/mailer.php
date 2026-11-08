<?php
declare(strict_types=1);

/*
 * Minimal outgoing-email helper (no Composer needed).
 *
 * Two ways to send, picked from settings (environment variable first, then config.php):
 *   1. Resend HTTPS API   RESEND_API_KEY + MAIL_FROM        (works on every Railway plan)
 *   2. SMTP               SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, SMTP_SECURE (tls|ssl|none) + MAIL_FROM
 *
 * Note: Railway blocks outbound SMTP on its Free/Trial/Hobby plans, so use the HTTPS API there.
 */

function mail_setting(string $key): ?string {
    global $config;
    $env = config_env(strtoupper($key));
    if ($env !== null) return $env;
    $value = $config[strtolower($key)] ?? null;
    return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
}

function mail_provider(): ?string {
    if (!mail_setting('mail_from')) return null;
    if (mail_setting('resend_api_key')) return 'resend';
    if (mail_setting('smtp_host')) return 'smtp';
    return null;
}

function mail_configured(): bool { return mail_provider() !== null; }

function app_url(): string {
    $configured = mail_setting('app_url');
    if ($configured !== null && preg_match('~^https?://~i', $configured)) return rtrim($configured, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) $host = 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function mail_clean_header(string $value): string { return trim(preg_replace('/[\r\n\x00]+/', ' ', $value) ?? ''); }

/** Splits "Name <addr@example.com>" or "addr@example.com" into [name, address]. */
function mail_parse_address(string $value): array {
    $value = mail_clean_header($value);
    if (preg_match('/^(.*)<([^<>]+)>$/', $value, $m)) return [trim($m[1], " \t\""), trim($m[2])];
    return ['', $value];
}

function send_mail(string $to, string $subject, string $text, string $html): bool {
    $provider = mail_provider();
    if ($provider === null) { error_log('KaziMates mail: no provider configured (set RESEND_API_KEY or SMTP_HOST, and MAIL_FROM).'); return false; }
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $subject = mail_clean_header($subject);
    try {
        return $provider === 'resend' ? mail_send_resend($to, $subject, $text, $html) : mail_send_smtp($to, $subject, $text, $html);
    } catch (Throwable $exception) {
        error_log('KaziMates mail error (' . $provider . '): ' . $exception->getMessage());
        return false;
    }
}

function mail_send_resend(string $to, string $subject, string $text, string $html): bool {
    $payload = json_encode([
        'from' => mail_clean_header((string) mail_setting('mail_from')),
        'to' => [$to], 'subject' => $subject, 'text' => $text, 'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 15, 'ignore_errors' => true,
        'header' => "Authorization: Bearer " . mail_clean_header((string) mail_setting('resend_api_key')) . "\r\nContent-Type: application/json\r\nUser-Agent: KaziMates/1.0\r\n",
        'content' => $payload,
    ]]);
    $body = @file_get_contents('https://api.resend.com/emails', false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) { if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) $status = (int) $m[1]; }
    if ($status >= 200 && $status < 300) return true;
    error_log('KaziMates mail: Resend API returned HTTP ' . $status . ' ' . substr((string) $body, 0, 300));
    return false;
}

function mail_smtp_expect($socket, array $okCodes): string {
    $reply = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $reply .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') break; // last line of a (multi-line) reply
    }
    $code = (int) substr($reply, 0, 3);
    if (!in_array($code, $okCodes, true)) throw new RuntimeException('SMTP unexpected reply: ' . trim($reply));
    return $reply;
}

function mail_smtp_command($socket, string $command, array $okCodes): string {
    fwrite($socket, $command . "\r\n");
    return mail_smtp_expect($socket, $okCodes);
}

function mail_send_smtp(string $to, string $subject, string $text, string $html): bool {
    $host = (string) mail_setting('smtp_host');
    $secure = strtolower((string) (mail_setting('smtp_secure') ?? 'tls'));
    $port = (int) (mail_setting('smtp_port') ?? ($secure === 'ssl' ? 465 : 587));
    [$fromName, $fromAddress] = mail_parse_address((string) mail_setting('mail_from'));
    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('MAIL_FROM is not a valid address.');

    $socket = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$socket) throw new RuntimeException("SMTP connection failed: $errstr ($errno)");
    stream_set_timeout($socket, 15);
    try {
        mail_smtp_expect($socket, [220]);
        $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.\-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost');
        mail_smtp_command($socket, $ehlo, [250]);
        if ($secure === 'tls') {
            mail_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed.');
            mail_smtp_command($socket, $ehlo, [250]);
        }
        $user = mail_setting('smtp_user');
        if ($user !== null) {
            mail_smtp_command($socket, 'AUTH LOGIN', [334]);
            mail_smtp_command($socket, base64_encode($user), [334]);
            mail_smtp_command($socket, base64_encode((string) mail_setting('smtp_password')), [235]);
        }
        mail_smtp_command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
        mail_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        mail_smtp_command($socket, 'DATA', [354]);

        $boundary = 'km_' . bin2hex(random_bytes(12));
        $fromHeader = ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $fromAddress . '>';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($fromAddress, '@'), 1) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $part = static fn(string $type, string $content): string =>
            "--$boundary\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content), 76, "\r\n");
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $part('text/plain', $text) . $part('text/html', $html) . "--$boundary--\r\n";
        $message = preg_replace('/^\./m', '..', $message); // dot-stuffing
        fwrite($socket, $message . ".\r\n");
        mail_smtp_expect($socket, [250]);
        try { mail_smtp_command($socket, 'QUIT', [221]); } catch (Throwable $ignored) { /* message already accepted */ }
        return true;
    } finally {
        fclose($socket);
    }
}
