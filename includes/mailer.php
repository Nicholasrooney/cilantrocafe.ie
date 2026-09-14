<?php
/*
 * Outgoing email: a booking confirmation to the customer, an alert to the café.
 *
 * Two transports. SMTP through the domain's own mailbox is the default because
 * mail from shared hosting without authenticated SMTP lands in spam far too
 * often. If SMTP is not configured, or fails, it falls back to mail() rather
 * than losing the message.
 *
 * No Composer here either, so this is a small hand-written SMTP client. It
 * speaks only as much of the protocol as transactional mail needs: EHLO,
 * STARTTLS, AUTH LOGIN, MAIL FROM, RCPT TO, DATA.
 *
 * Nothing in here is allowed to throw into a booking. mail_send() returns a
 * bool and logs its own failures.
 */

require_once __DIR__ . '/config.php';

/**
 * Sends one message. Returns true if it was handed off successfully.
 */
function mail_send(string $to, string $subject, string $body, array $opts = []): bool
{
    global $mail;

    if (trim($to) === '') {
        return false;
    }

    $from     = $opts['from']      ?? ($mail['from']      ?? '');
    $fromName = $opts['from_name'] ?? ($mail['from_name'] ?? 'Cilantro Café');
    $replyTo  = $opts['reply_to']  ?? ($mail['reply_to']  ?? $from);

    if ($from === '') {
        mail_log('SKIPPED', "no from address configured; would have sent \"$subject\" to $to");
        return false;
    }

    try {
        if (!empty($mail['smtp']['host'])) {
            mail_smtp_send($mail['smtp'], $from, $fromName, $to, $subject, $body, $replyTo);
            mail_log('sent', "smtp: \"$subject\" to $to");
            return true;
        }
    } catch (Throwable $e) {
        mail_log('smtp failed', $e->getMessage() . ' — falling back to mail()');
    }

    $headers = [
        'From: ' . mail_address($fromName, $from),
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: cilantro-site',
    ];

    $ok = @mail($to, mail_encode_header($subject), mail_normalise($body), implode("\r\n", $headers));
    mail_log($ok ? 'sent' : 'FAILED', "mail(): \"$subject\" to $to");

    return (bool) $ok;
}

/**
 * Confirmation to the person who booked.
 */
function mail_booking_confirmation(array $b): bool
{
    global $site;

    if (empty($b['email'])) {
        return false;
    }

    $when = mail_when($b['date'], $b['time']);
    $lines = [
        "Hi " . mail_first_name($b['name']) . ",",
        "",
        "Thanks — we have your table request:",
        "",
        "  " . $b['guests'] . ' ' . ($b['guests'] == 1 ? 'guest' : 'guests'),
        "  " . $when,
    ];

    if (!empty($b['seating'])) {
        $lines[] = "  " . $b['seating'];
    }
    if (!empty($b['notes'])) {
        $lines[] = "  Your note: " . $b['notes'];
    }

    $lines[] = "";
    $lines[] = "We'll be in touch if anything needs to change. If your plans change,";
    $lines[] = "just reply to this email or give us a ring and we'll sort it.";
    $lines[] = "";

    if (!empty($site['address'])) {
        $lines[] = $site['name'] . ", " . $site['address'] . (!empty($site['eircode']) ? ', ' . $site['eircode'] : '');
    }
    if (!empty($site['phone'])) {
        $lines[] = $site['phone'];
    }
    $lines[] = "https://" . $site['domain'];

    return mail_send($b['email'], 'Your table at ' . $site['name'] . ' — ' . $when, implode("\n", $lines));
}

/**
 * Everyone who should be told about bookings and catering enquiries.
 *
 * $booking['notify_email'] may be one address or a list. Invalid entries are
 * dropped rather than breaking every alert, and duplicates are removed.
 */
function mail_notify_recipients(): array
{
    global $booking;

    $raw  = $booking['notify_email'] ?? [];
    $list = is_array($raw) ? $raw : preg_split('/[,;\s]+/', (string) $raw);

    $out = [];
    foreach ($list as $address) {
        $address = trim((string) $address);
        // First spelling wins; a later "OK@..." is the same inbox as "ok@...".
        if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) && !isset($out[strtolower($address)])) {
            $out[strtolower($address)] = $address;
        }
    }

    return array_values($out);
}

/**
 * Sends the same alert to each recipient separately, so one bad address or a
 * bounce never stops the others, and nobody sees who else was copied in.
 * Returns true if at least one went.
 */
function mail_send_alert(string $subject, string $body, array $opts = []): bool
{
    $sent = false;
    foreach (mail_notify_recipients() as $to) {
        if (mail_send($to, $subject, $body, $opts)) {
            $sent = true;
        }
    }
    return $sent;
}

/**
 * Alert to the café, so somebody actually knows a booking came in.
 */
function mail_booking_alert(array $b): bool
{
    global $site;

    if (!mail_notify_recipients()) {
        return false;
    }

    $lines = [
        "New booking from the website.",
        "",
        "When:    " . mail_when($b['date'], $b['time']),
        "Guests:  " . $b['guests'],
        "Name:    " . $b['name'],
        "Phone:   " . $b['phone'],
        "Email:   " . ($b['email'] ?: '—'),
        "Seating: " . ($b['seating'] ?: 'No preference'),
    ];

    if (!empty($b['notes'])) {
        $lines[] = "Notes:   " . $b['notes'];
    }

    $lines[] = "";
    $lines[] = "Open the diary: https://" . $site['domain'] . "/staff/";

    return mail_send_alert(
        sprintf('Booking: %s, %s for %d', $b['name'], mail_when($b['date'], $b['time']), (int) $b['guests']),
        implode("\n", $lines),
        ['reply_to' => $b['email'] ?: null]
    );
}

/* ------------------------------------------------------------------ SMTP */

/**
 * Minimal authenticated SMTP. Throws on any unexpected response so the caller
 * can fall back.
 */
function mail_smtp_send(array $c, string $from, string $fromName, string $to, string $subject, string $body, ?string $replyTo): void
{
    $host    = $c['host'];
    $port    = (int) ($c['port'] ?? 587);
    $timeout = (int) ($c['timeout'] ?? 10);
    $secure  = $c['security'] ?? 'tls';    // 'tls' (STARTTLS), 'ssl', or ''

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, $timeout);

    if (!$socket) {
        throw new RuntimeException("Cannot connect to $target: $errstr");
    }

    stream_set_timeout($socket, $timeout);

    try {
        mail_smtp_expect($socket, 220);
        mail_smtp_cmd($socket, 'EHLO ' . mail_smtp_helo_name(), 250);

        if ($secure === 'tls') {
            mail_smtp_cmd($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            mail_smtp_cmd($socket, 'EHLO ' . mail_smtp_helo_name(), 250);
        }

        if (!empty($c['user'])) {
            mail_smtp_cmd($socket, 'AUTH LOGIN', 334);
            mail_smtp_cmd($socket, base64_encode($c['user']), 334);
            mail_smtp_cmd($socket, base64_encode($c['pass'] ?? ''), 235);
        }

        mail_smtp_cmd($socket, 'MAIL FROM:<' . $from . '>', 250);
        mail_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', 250);
        mail_smtp_cmd($socket, 'DATA', 354);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . mail_address($fromName, $from),
            'To: ' . $to,
            'Subject: ' . mail_encode_header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . mail_smtp_helo_name() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . mail_dot_stuff(mail_normalise($body));

        fwrite($socket, $message . "\r\n.\r\n");
        mail_smtp_expect($socket, 250);

        mail_smtp_cmd($socket, 'QUIT', 221);
    } finally {
        @fclose($socket);
    }
}

function mail_smtp_cmd($socket, string $command, int $expect): string
{
    fwrite($socket, $command . "\r\n");
    return mail_smtp_expect($socket, $expect);
}

/**
 * Reads a reply, following multi-line continuations ("250-" then "250 ").
 */
function mail_smtp_expect($socket, int $expect): string
{
    $reply = '';

    while (($line = fgets($socket, 515)) !== false) {
        $reply .= $line;
        // A space in the 4th position marks the final line.
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    $code = (int) substr(ltrim($reply), 0, 3);
    if ($code !== $expect) {
        throw new RuntimeException("SMTP expected $expect, got: " . trim($reply));
    }

    return $reply;
}

function mail_smtp_helo_name(): string
{
    global $site;
    return $site['domain'] ?? 'localhost';
}

/* ------------------------------------------------------------- helpers */

/**
 * Normalises to CRLF, which is what SMTP requires.
 */
function mail_normalise(string $body): string
{
    return preg_replace('/\r\n|\r|\n/', "\r\n", $body);
}

/**
 * A line consisting of a single dot ends the DATA block, so any real line
 * starting with a dot has to be doubled.
 */
function mail_dot_stuff(string $body): string
{
    return preg_replace('/^\./m', '..', $body);
}

/**
 * RFC 2047 encoding, so accented names and the é in Café survive.
 */
function mail_encode_header(string $value): string
{
    if (preg_match('/[\x80-\xFF]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

function mail_address(string $name, string $email): string
{
    return $name !== '' ? mail_encode_header($name) . ' <' . $email . '>' : $email;
}

function mail_first_name(string $full): string
{
    $parts = preg_split('/\s+/', trim($full));
    return $parts[0] ?? $full;
}

function mail_when(string $date, string $time): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Dublin'));
    return ($dt ? $dt->format('l j F') : $date) . ' at ' . $time;
}

function mail_log(string $result, string $message): void
{
    global $mail;

    $file = $mail['log_file'] ?? '';
    if ($file === '') {
        return;
    }

    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }

    @file_put_contents(
        $file,
        sprintf("[%s] %-12s %s\n", date('Y-m-d H:i:s'), $result, $message),
        FILE_APPEND | LOCK_EX
    );
}
