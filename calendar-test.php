<?php
/*
 * Google Calendar connection test.
 *
 * Visit:  https://yourdomain.ie/calendar-test.php?token=YOUR_TEST_TOKEN
 * The token comes from $calendar['test_token'] in includes/config.php.
 *
 * Blank that token again once you are happy — this page is a diagnostic, not
 * part of the site.
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/calendar-sync.php';

header('Content-Type: text/plain; charset=utf-8');

$token = (string) ($calendar['test_token'] ?? '');
if ($token === '' || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    echo "Not found.\n";
    exit;
}

$pass = 0;
$fail = 0;

function step(string $label, callable $check): void
{
    global $pass, $fail;
    echo str_pad($label, 46, '.');
    try {
        $detail = $check();
        echo " OK" . ($detail ? "  ($detail)" : "") . "\n";
        $pass++;
    } catch (Throwable $e) {
        echo " FAILED\n";
        echo "    " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "Cilantro Café — Google Calendar check\n";
echo str_repeat('=', 64) . "\n\n";

step('PHP openssl extension', function () {
    if (!function_exists('openssl_sign')) {
        throw new RuntimeException('openssl is not available. Contact Hostinger support.');
    }
    return 'available';
});

step('PHP curl extension', function () {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('curl is not available. Contact Hostinger support.');
    }
    return 'available';
});

step('Sync enabled in config', function () use ($calendar) {
    if (empty($calendar['enabled'])) {
        throw new RuntimeException("\$calendar['enabled'] is false. Bookings will not reach Google until it is true.");
    }
    return 'yes';
});

step('Calendar ID set', function () use ($calendar) {
    if (empty($calendar['calendar_id'])) {
        throw new RuntimeException("\$calendar['calendar_id'] is empty. Google Calendar > Settings > your calendar > Integrate calendar.");
    }
    return $calendar['calendar_id'];
});

$key = null;
step('Service account key file', function () use ($calendar, &$key) {
    $key = gcal_load_key($calendar['key_file']);
    return $key['client_email'];
});

step('Data folder writable (token cache)', function () use ($calendar) {
    $dir = dirname($calendar['token_cache']);
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException("Cannot create $dir");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("$dir is not writable");
    }
    return 'yes';
});

step('Access token from Google', function () use ($calendar, &$key) {
    if (!$key) {
        throw new RuntimeException('skipped — no key file');
    }
    $t = gcal_access_token($key, $calendar['token_cache']);
    return strlen($t) . ' chars';
});

step('Calendar visible to service account', function () use ($calendar) {
    $cal = gcal_get_calendar($calendar);
    return ($cal['summary'] ?? 'unnamed') . ' / ' . ($cal['timeZone'] ?? '?');
});

$eventId = null;
step('Create a test event (tomorrow 15:00)', function () use ($calendar, &$eventId) {
    $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone($calendar['timezone'])))->format('Y-m-d');
    $eventId  = gcal_insert_event($calendar, calendar_build_event([
        'name'    => 'TEST BOOKING — safe to delete',
        'phone'   => '000',
        'email'   => 'test@example.com',
        'date'    => $tomorrow,
        'time'    => '15:00',
        'guests'  => 2,
        'seating' => 'No preference',
        'notes'   => 'Created by calendar-test.php. Delete me.',
    ], $calendar));
    return $eventId;
});

if ($eventId) {
    step('Delete the test event again', function () use ($calendar, $eventId) {
        gcal_delete_event($calendar, $eventId);
        return 'cleaned up';
    });
}

echo "\n" . str_repeat('=', 64) . "\n";
echo $fail === 0
    ? "ALL $pass CHECKS PASSED — bookings will appear on your calendar.\n"
    : "$pass passed, $fail FAILED — see the messages above.\n";

if ($fail === 0) {
    echo "\nA test event was created and removed, so check nothing is left behind.\n";
    echo "Now blank \$calendar['test_token'] in includes/config.php.\n";
}

$log = $calendar['log_file'] ?? '';
if ($log && is_readable($log)) {
    echo "\nLast few sync log lines:\n";
    $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -10);
    foreach ($lines as $line) {
        echo "  $line\n";
    }
}
