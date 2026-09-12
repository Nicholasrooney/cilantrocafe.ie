<?php
/*
 * Booking form handler.
 *
 * Current behaviour: validates the form and saves each booking request as a
 * row in data/bookings.csv (not publicly accessible).
 *
 * BACKEND TODO: when you're ready, replace or add to save_booking() below to
 * send an email notification, write to a database, or post to a booking tool.
 */

require_once __DIR__ . '/config.php';

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

$errors  = [];
$old     = [
    'name' => '', 'phone' => '', 'email' => '', 'date' => '',
    'time' => '', 'guests' => '2', 'seating' => $booking['seating'][0], 'notes' => '',
];
$confirmed = $_SESSION['booking_confirmed'] ?? null;
unset($_SESSION['booking_confirmed']);

$today   = new DateTimeImmutable('today', new DateTimeZone('Europe/Dublin'));
$lastDay = $today->modify('+' . (int) $booking['days_ahead'] . ' days');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $default) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    // Spam checks: hidden field must stay empty, token must match
    $isBot = !empty($_POST['website']);
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

    if (!$errors) {
        if (!$isBot) {
            $saved = save_booking($old, $booking);
            if (!$saved) {
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
}

/**
 * Character count that works with or without the mbstring extension.
 */
function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

/**
 * Saves one booking request. Returns true on success.
 */
function save_booking(array $data, array $booking): bool
{
    $file = $booking['csv_file'];
    $dir  = dirname($file);

    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        return false;
    }

    $isNew  = !file_exists($file);
    $handle = fopen($file, 'ab');
    if (!$handle) {
        return false;
    }

    // Stop spreadsheet apps treating values as formulas
    $safe = static function ($value) {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    };

    flock($handle, LOCK_EX);
    if ($isNew) {
        fputcsv($handle, ['Received', 'Name', 'Phone', 'Email', 'Date', 'Time', 'Guests', 'Seating', 'Notes']);
    }
    $received = (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))->format('Y-m-d H:i');
    fputcsv($handle, array_map($safe, [
        $received, $data['name'], $data['phone'], $data['email'],
        $data['date'], $data['time'], $data['guests'], $data['seating'], $data['notes'],
    ]));
    flock($handle, LOCK_UN);
    fclose($handle);

    // BACKEND TODO: send notification email to $booking['notify_email']

    return true;
}
