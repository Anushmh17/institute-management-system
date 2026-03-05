<?php
/**
 * Lightweight SMTP mailer with mail() fallback.
 */

require_once __DIR__ . '/auth.php';

function mailer_send(string $to, string $subject, string $body, array $options = []): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = trim((string)($options['from_email'] ?? ''));
    $fromName  = trim((string)($options['from_name'] ?? ''));

    if (!$fromEmail || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if (!$host || !preg_match('/^[a-z0-9.-]+$/i', $host)) {
            $host = 'institute.local';
        }
        $fromEmail = 'noreply@' . $host;
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'noreply@institute.local';
        }
    }

    $displayName = $fromName !== '' ? $fromName : get_setting('institute_name', 'Institute');

    $smtpEnabled = get_setting('smtp_enabled', '0') === '1';
    if ($smtpEnabled) {
        $smtpFromEmail = trim(get_setting('smtp_from_email', ''));
        $smtpFromName = trim(get_setting('smtp_from_name', ''));
        if ($smtpFromEmail !== '' && filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $smtpFromEmail;
        }
        if ($smtpFromName !== '') {
            $displayName = $smtpFromName;
        }

        return smtp_send_mail($to, $subject, $body, [
            'host'       => trim(get_setting('smtp_host', '')),
            'port'       => (int)get_setting('smtp_port', '587'),
            'encryption' => strtolower(trim(get_setting('smtp_encryption', 'tls'))),
            'username'   => trim(get_setting('smtp_username', '')),
            'password'   => get_setting('smtp_password', ''),
            'from_email' => $fromEmail,
            'from_name'  => $displayName,
        ]);
    }

    $fromHeader = $displayName !== ''
        ? sprintf('"%s" <%s>', addcslashes($displayName, "\"\\"), $fromEmail)
        : $fromEmail;

    $headers = "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "From: {$fromHeader}\r\n"
             . "Reply-To: {$fromEmail}\r\n"
             . "X-Mailer: PHP/" . phpversion();

    $mailWarning = null;
    set_error_handler(static function (int $severity, string $message) use (&$mailWarning): bool {
        $mailWarning = $message;
        return true; // prevent warning from rendering in UI
    });

    try {
        $sent = mail($to, $subject, $body, $headers);
    } finally {
        restore_error_handler();
    }

    if (!$sent && $mailWarning) {
        error_log('mail() failed: ' . $mailWarning);
    }

    return $sent;
}

function smtp_send_mail(string $to, string $subject, string $body, array $cfg): bool {
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 587);
    $enc  = (string)($cfg['encryption'] ?? 'tls'); // none|tls|ssl
    $user = (string)($cfg['username'] ?? '');
    $pass = (string)($cfg['password'] ?? '');
    $from = (string)($cfg['from_email'] ?? '');
    $name = (string)($cfg['from_name'] ?? '');

    if ($host === '' || $port <= 0 || $from === '') {
        error_log('SMTP not configured correctly.');
        return false;
    }

    $transportHost = $enc === 'ssl' ? "ssl://{$host}" : $host;
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($transportHost, $port, $errno, $errstr, 20);
    if (!$socket) {
        error_log("SMTP connect failed: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, 220);
        smtp_command_expect($socket, 'EHLO localhost', 250);

        if ($enc === 'tls') {
            smtp_command_expect($socket, 'STARTTLS', 220);
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                throw new RuntimeException('SMTP TLS handshake failed.');
            }
            smtp_command_expect($socket, 'EHLO localhost', 250);
        }

        if ($user !== '') {
            smtp_command_expect($socket, 'AUTH LOGIN', 334);
            smtp_command_expect($socket, base64_encode($user), 334);
            smtp_command_expect($socket, base64_encode($pass), 235);
        }

        smtp_command_expect($socket, "MAIL FROM:<{$from}>", 250);
        smtp_command_expect($socket, "RCPT TO:<{$to}>", [250, 251]);
        smtp_command_expect($socket, 'DATA', 354);

        $fromHeader = $name !== ''
            ? sprintf('"%s" <%s>', addcslashes($name, "\"\\"), $from)
            : $from;

        $headers = [
            "Date: " . date(DATE_RFC2822),
            "From: {$fromHeader}",
            "To: <{$to}>",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $data = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $data);
        fwrite($socket, $data . "\r\n.\r\n");
        smtp_expect($socket, 250);

        smtp_command_expect($socket, 'QUIT', 221);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return false;
    }
}

function smtp_command_expect($socket, string $command, int|array $expected): void {
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $expected);
}

function smtp_expect($socket, int|array $expected): void {
    $valid = (array)$expected;
    $line = '';

    while (($buffer = fgets($socket, 515)) !== false) {
        $line .= $buffer;
        if (strlen($buffer) >= 4 && $buffer[3] === ' ') {
            break;
        }
    }

    if ($line === '') {
        throw new RuntimeException('SMTP empty response.');
    }

    $code = (int)substr($line, 0, 3);
    if (!in_array($code, $valid, true)) {
        throw new RuntimeException("SMTP unexpected response {$code}: " . trim($line));
    }
}
