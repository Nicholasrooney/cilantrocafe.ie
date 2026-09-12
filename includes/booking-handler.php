<?php
/*
 * Public booking form: validation, then hand off to the data layer.
 *
 * This file's only job is turning an untrusted POST into either a list of
 * errors or one clean booking. Storage lives in bookings.php, capacity in
 * capacity.php, email in mailer.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/capacity.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/calendar-sync.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['booking_token'])) {
    $_SESSION['booking_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$old    = [
    'name' => '', 'phone' => '', 'email' => '', 'date' => '',
    'time' => '', 'guests' => '2', 'seating' => $booking['seating'][0], 'notes' => '',
];
$consent   = false;
$confirmed = $_SESSION['booking_confirmed'] ?? null;
unset($_SESSION['booking_confirmed']);

$today   = new DateTimeImmutable('today', new DateTimeZone('Europe/Dublin'));
$lastDay = $today->modify('+' . (int) $booking['days_ahead'] . ' days');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $default) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $consent = !empty($_POST['marketing_consent']);

    // Spam checks: hidden field must stay empty, token must match
    $isBot   = !empty($_POST['website']);
    $tokenOk = hash_equals($_SESSION['booking_token'], (string) ($_POST['token'] ?? ''));

    if (!$tokenOk) {
        $errors['form'] = 'The form expired. Please check your details and send it again.';
    }

    if (text_length($old['name']) < 2 || text_length($old['name']) > 80) {
        $errors['name'] = 'Enter your name.';
    }

    $phoneDigits = preg_replace('/\D/', '', $old['phone']);
    if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
        $errors['phone'] = 'Enter a phone number we can call if plans change.';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter an email address, like name@example.com.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $old['date'], new DateTimeZone('Europe/Dublin'));
    if (!$date || $date->format('Y-m-d') !== $old['date']) {
        $errors['date'] = 'Choose a date.';
    } elseif ($date < $today || $date > $lastDay) {
        $errors['date'] = 'Choose a date between today and ' . $lastDay->format('j F Y') . '.';
    }

    if (!in_array($old['time'], $booking['times'], true)) {
        $errors['time'] = 'Choose a time.';
    } elseif ($date && !isset($errors['date']) && $date == $today) {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin'));
        if ($old['time'] <= $now->format('H:i')) {
            $errors['time'] = 'That time has passed. Choose a later time.';
        }
    }

    $guests = filter_var($old['guests'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $booking['max_guests']]]);
    if ($guests === false) {
        $errors['guests'] = 'Choose between 1 and ' . $booking['max_guests'] . ' guests.';
    }

    if (!in_array($old['seating'], $booking['seating'], true)) {
        $old['seating'] = $booking['seating'][0];
    }

    if (text_length($old['notes']) > 500) {
        $errors['notes'] = 'Keep notes under 500 characters.';
    }

    if (!$errors && !$isBot) {
        try {
            $bookingId = booking_create([
                'name'              => $old['name'],
                'phone'             => $old['phone'],
                'email'             => $old['email'],
                'date'              => $old['date'],
                'time'              => $old['time'],
                'guests'            => $guests,
                'seating'           => $old['seating'],
                'notes'             => $old['notes'],
                'source'            => 'website',
                'marketing_consent' => $consent,
            ], true, 'website');

            // Everything below is a nice-to-have. None of it may undo a
            // booking that is already saved, so each part fails on its own.
            $row = ['name' => $old['name'], 'phone' => $old['phone'], 'email' => $old['email'],
                    'date' => $old['date'], 'time' => $old['time'], 'guests' => $guests,
                    'seating' => $old['seating'], 'notes' => $old['notes']];

            $eventId = calendar_sync_booking($row, $calendar);
            if ($eventId) {
                booking_set_google_event($bookingId, $eventId);
            }

            mail_booking_confirmation($row);
            mail_booking_alert($row);

        } catch (CapacityExceeded $e) {
            $errors['time'] = $e->getMessage() . ' Please pick another time.';
        } catch (Throwable $e) {
            error_log('Booking failed: ' . $e->getMessage());
            $errors['form'] = 'Your booking could not be saved. Please phone or email us instead.';
        }
    }

    if (!$errors) {
        $_SESSION['booking_confirmed'] = [
            'name'   => $old['name'],
            'date'   => $date->format('l j F'),
            'time'   => $old['time'],
            'guests' => (int) $guests,
        ];
        $_SESSION['booking_token'] = bin2hex(random_bytes(16));
        header('Location: booking.php?sent=1', true, 303);
        exit;
    }
}

/**
 * Which time slots still have room, for the date being shown.
 *
 * This only decides what the form offers. The binding check happens inside the
 * insert transaction, because two people can pass this one in the same second.
 */
function booking_slot_availability(string $date, int $guests): array
{
    global $booking;

    try {
        return capacity_slot_availability(
            $date,
            $booking['times'],
            (int) ($booking['max_covers_per_slot'] ?? 0),
            max(1, $guests)
        );
    } catch (Throwable $e) {
        // No database yet? Offer everything rather than showing an empty form.
        return array_fill_keys($booking['times'], true);
    }
}

/**
 * Character count that works with or without the mbstring extension.
 */
function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}
