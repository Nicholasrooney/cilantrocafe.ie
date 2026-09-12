<?php
/*
 * Which time slots still have room on a given date.
 *
 * Called by the booking form whenever the date or party size changes, so the
 * times update without a page reload. Returns only availability — no customer
 * data, nothing worth harvesting.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bookings.php';
require_once __DIR__ . '/includes/capacity.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$tz     = new DateTimeZone('Europe/Dublin');
$date   = (string) ($_GET['date'] ?? '');
$guests = max(1, min((int) ($_GET['guests'] ?? 1), (int) $booking['max_guests']));

$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'bad date']);
    exit;
}

// Stay inside the window the form itself allows.
$today   = new DateTimeImmutable('today', $tz);
$lastDay = $today->modify('+' . (int) $booking['days_ahead'] . ' days');
if ($parsed < $today || $parsed > $lastDay) {
    http_response_code(400);
    echo json_encode(['error' => 'date out of range']);
    exit;
}

try {
    $available = capacity_slot_availability(
        $date,
        $booking['times'],
        (int) ($booking['max_covers_per_slot'] ?? 0),
        $guests
    );
} catch (Throwable $e) {
    // No database yet — don't block anybody from booking.
    $available = array_fill_keys($booking['times'], true);
}

// Past times today are no use to anyone either.
if ($date === $today->format('Y-m-d')) {
    $now = (new DateTimeImmutable('now', $tz))->format('H:i');
    foreach ($available as $time => $free) {
        if ($time <= $now) {
            $available[$time] = false;
        }
    }
}

echo json_encode(['date' => $date, 'guests' => $guests, 'slots' => $available]);
