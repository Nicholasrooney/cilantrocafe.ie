<?php
/*
 * Turns a booking into a Google Calendar event.
 *
 * This is the only file that knows both sides. google-calendar.php talks to
 * Google and knows nothing about bookings; the booking handler calls in here
 * and knows nothing about Google.
 *
 * Rule: syncing must never cost a booking. Every entry point swallows its
 * errors, writes them to the log and returns null, so a Google outage or a
 * misconfigured key is invisible to the customer.
 */

require_once __DIR__ . '/google-calendar.php';

/**
 * Pushes a booking to the calendar. Returns the Google event id, or null if
 * sync is off or the attempt failed.
 *
 * @param array $data    name, phone, email, date, time, guests, seating, notes
 * @param array $config  the $calendar array from config.php
 */
function calendar_sync_booking(array $data, array $config): ?string
{
    if (empty($config['enabled'])) {
        return null;
    }

    try {
        $eventId = gcal_insert_event($config, calendar_build_event($data, $config));
        calendar_log($config, 'created', sprintf(
            '%s for %s on %s at %s (event %s)',
            $data['guests'] . ' guest' . ($data['guests'] == 1 ? '' : 's'),
            $data['name'], $data['date'], $data['time'], $eventId
        ));
        return $eventId;
    } catch (Throwable $e) {
        calendar_log($config, 'FAILED', $e->getMessage());
        return null;
    }
}

/**
 * Updates an already-synced booking. Unused until bookings become editable,
 * but kept here so the staff app has it ready.
 */
function calendar_update_booking(string $eventId, array $data, array $config): bool
{
    if (empty($config['enabled'])) {
        return false;
    }

    try {
        gcal_update_event($config, $eventId, calendar_build_event($data, $config));
        calendar_log($config, 'updated', "event $eventId");
        return true;
    } catch (Throwable $e) {
        calendar_log($config, 'FAILED', $e->getMessage());
        return false;
    }
}

/**
 * Removes a cancelled booking from the calendar.
 */
function calendar_cancel_booking(string $eventId, array $config): bool
{
    if (empty($config['enabled'])) {
        return false;
    }

    try {
        gcal_delete_event($config, $eventId);
        calendar_log($config, 'deleted', "event $eventId");
        return true;
    } catch (Throwable $e) {
        calendar_log($config, 'FAILED', $e->getMessage());
        return false;
    }
}

/**
 * Builds the Google event body for a booking.
 */
function calendar_build_event(array $data, array $config): array
{
    $tz    = new DateTimeZone($config['timezone']);
    $start = new DateTimeImmutable($data['date'] . ' ' . $data['time'], $tz);
    $end   = $start->modify('+' . (int) $config['duration_minutes'] . ' minutes');

    $guests = (int) $data['guests'];
    $title  = sprintf('%d guest%s — %s', $guests, $guests === 1 ? '' : 's', $data['name']);

    $lines = [
        'Guests:  ' . $guests,
        'Name:    ' . $data['name'],
        'Phone:   ' . $data['phone'],
        'Email:   ' . $data['email'],
        'Seating: ' . $data['seating'],
    ];
    if (trim((string) ($data['notes'] ?? '')) !== '') {
        $lines[] = '';
        $lines[] = 'Notes: ' . $data['notes'];
    }
    $lines[] = '';
    $lines[] = 'Booked through the website.';

    return [
        'summary'     => $title,
        'description' => implode("\n", $lines),
        'start'       => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $config['timezone']],
        'end'         => ['dateTime' => $end->format('Y-m-d\TH:i:s'),   'timeZone' => $config['timezone']],
        // Lets us recognise our own events later, and never touch ones staff
        // added to the calendar by hand.
        'extendedProperties' => ['private' => ['source' => 'cilantro-website']],
    ];
}

/**
 * Appends a line to the sync log. Lives in data/, which .htaccess blocks from
 * the public. Never throws — logging must not be able to break a booking.
 */
function calendar_log(array $config, string $result, string $message): void
{
    if (empty($config['log_file'])) {
        return;
    }

    $dir = dirname($config['log_file']);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }

    $stamp = (new DateTimeImmutable('now', new DateTimeZone($config['timezone'])))->format('Y-m-d H:i:s');
    @file_put_contents(
        $config['log_file'],
        sprintf("[%s] %-8s %s\n", $stamp, $result, $message),
        FILE_APPEND | LOCK_EX
    );
}
