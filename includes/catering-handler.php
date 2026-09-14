<?php
/*
 * Catering page forms: the price card and the full enquiry form.
 *
 * Turns an untrusted POST into either errors or one stored, emailed enquiry.
 * Prices, storage and email live in catering.php.
 *
 * A lead must never be lost quietly. The enquiry is saved first and the café is
 * emailed second; only if BOTH fail is the visitor told to try another way.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/catering.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['catering_token'])) {
    $_SESSION['catering_token'] = bin2hex(random_bytes(16));
}

$tz      = new DateTimeZone('Europe/Dublin');
$today   = new DateTimeImmutable('today', $tz);
$lastDay = $today->modify('+365 days');

$cat_packages = catering_packages();
$cat_errors   = [];

// What the price card shows before anyone touches it.
$cat_card = [
    'occasion' => 'birthday',
    'guests'   => 50,
    'package'  => 'taco_bar',
    'location' => '',
    'contact'  => '',
];

$cat_old = [
    'name' => '', 'phone' => '', 'email' => '', 'occasion' => '', 'event_date' => '',
    'guests' => '', 'package' => '', 'fulfilment' => '', 'delivery_address' => '', 'notes' => '',
];
$cat_consent = false;

$cat_sent = isset($_GET['sent']) ? ($_SESSION['catering_sent'] ?? null) : null;
unset($_SESSION['catering_sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $which   = (string) ($_POST['form'] ?? '');
    $isBot   = !empty($_POST['website']);
    $tokenOk = hash_equals($_SESSION['catering_token'], (string) ($_POST['token'] ?? ''));
    $record  = null;

    if (!in_array($which, ['price_card', 'full_form'], true)) {
        $which = 'full_form';
    }

    if (!$tokenOk) {
        $cat_errors['form_' . $which] = 'This form expired. Please check your details and send it again.';
    }

    if ($which === 'price_card') {
        $occasion = (string) ($_POST['occasion'] ?? '');
        if (!isset($catering['occasions'][$occasion])) {
            $occasion = 'other';
        }

        $package = (string) ($_POST['package'] ?? '');
        if (!isset($cat_packages[$package])) {
            $package = $catering['occasions'][$occasion]['package'];
        }

        $guests   = catering_clamp_guests((int) ($_POST['guests'] ?? 50));
        $location = trim(preg_replace('/\s+/', ' ', (string) ($_POST['location'] ?? '')));
        $raw      = trim((string) ($_POST['contact'] ?? ''));
        $contact  = catering_parse_contact($raw);

        $cat_card = [
            'occasion' => $occasion, 'guests' => $guests, 'package' => $package,
            'location' => $location, 'contact' => $raw,
        ];

        if ((function_exists('mb_strlen') ? mb_strlen($location, 'UTF-8') : strlen($location)) > 200) {
            $cat_errors['location'] = 'Keep the location under 200 characters.';
        }

        if (!$contact) {
            $cat_errors['contact'] = 'Enter a phone number or an email address so we can send you a quote.';
        } elseif (!$cat_errors) {
            // Only record a price if the visitor was actually shown one.
            $price  = show_prices() ? $cat_packages[$package]['price'] : null;
            $record = [
                'source'           => 'price_card',
                'phone'            => $contact['phone'],
                'email'            => $contact['email'],
                'occasion'         => $occasion,
                'guests'           => $guests,
                'location'         => $location,
                'package'          => $package,
                'price_per_person' => $price,
                'estimate_total'   => catering_estimate($price, $guests),
            ];
        }
    } else {
        foreach ($cat_old as $field => $_) {
            $cat_old[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        $cat_consent = !empty($_POST['marketing_consent']);

        $len = fn(string $v): int => function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);

        if ($len($cat_old['name']) < 2 || $len($cat_old['name']) > 80) {
            $cat_errors['name'] = 'Enter your name.';
        }

        $digits = preg_replace('/\D/', '', $cat_old['phone']);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $cat_errors['phone'] = 'Enter a phone number we can call you on.';
        }

        if (!filter_var($cat_old['email'], FILTER_VALIDATE_EMAIL)) {
            $cat_errors['email'] = 'Enter an email address, like name@example.com.';
        }

        if (!isset($catering['occasions'][$cat_old['occasion']])) {
            $cat_errors['occasion'] = 'Choose the occasion.';
        }

        if (!isset($cat_packages[$cat_old['package']])) {
            $cat_errors['package'] = 'Choose a package, or "Something else" and tell us in the notes.';
        }

        $guests = filter_var($cat_old['guests'], FILTER_VALIDATE_INT);
        if ($guests === false) {
            $cat_errors['guests'] = 'How many guests?';
        } elseif ($guests < (int) $catering['min_guests']) {
            $cat_errors['guests'] = 'We cater for ' . (int) $catering['min_guests'] . ' or more. For a smaller group, book a table instead.';
        } elseif ($guests > (int) $catering['max_guests']) {
            $cat_errors['guests'] = 'Online enquiries go up to ' . (int) $catering['max_guests'] . ' guests. For more, put your numbers in the notes and message us on Instagram.';
        }

        if ($cat_old['event_date'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $cat_old['event_date'], $tz);
            if (!$date || $date->format('Y-m-d') !== $cat_old['event_date']) {
                $cat_errors['event_date'] = 'That date does not look right.';
            } elseif ($date < $today || $date > $lastDay) {
                $cat_errors['event_date'] = 'Choose a date between today and ' . $lastDay->format('j F Y') . '.';
            }
        }

        if (!in_array($cat_old['fulfilment'], ['pickup', 'delivery'], true)) {
            $cat_errors['fulfilment'] = 'Choose pickup or delivery.';
        } elseif ($cat_old['fulfilment'] === 'delivery' && $len($cat_old['delivery_address']) < 5) {
            $cat_errors['delivery_address'] = 'Tell us where to deliver.';
        }

        if ($len($cat_old['delivery_address']) > 300) {
            $cat_errors['delivery_address'] = 'Keep the address under 300 characters.';
        }
        if ($len($cat_old['notes']) > 1000) {
            $cat_errors['notes'] = 'Keep notes under 1000 characters.';
        }

        if (!$cat_errors) {
            $price  = show_prices() ? $cat_packages[$cat_old['package']]['price'] : null;
            $record = [
                'source'            => 'full_form',
                'name'              => $cat_old['name'],
                'phone'             => $cat_old['phone'],
                'email'             => $cat_old['email'],
                'occasion'          => $cat_old['occasion'],
                'event_date'        => $cat_old['event_date'],
                'guests'            => (int) $guests,
                'package'           => $cat_old['package'],
                'price_per_person'  => $price,
                'estimate_total'    => catering_estimate($price, (int) $guests),
                'fulfilment'        => $cat_old['fulfilment'],
                'delivery_address'  => $cat_old['fulfilment'] === 'delivery' ? $cat_old['delivery_address'] : '',
                'notes'             => $cat_old['notes'],
                'marketing_consent' => $cat_consent,
            ];
        }
    }

    if (!$cat_errors && $record && !$isBot) {
        $saved = false;
        try {
            catering_save($record);
            $saved = true;
        } catch (Throwable $e) {
            error_log('Catering enquiry not saved: ' . $e->getMessage());
        }

        $alerted = catering_mail_alert($record);

        if (!$saved && !$alerted) {
            $cat_errors['form_' . $which] = 'Sorry, your enquiry could not be sent just now. '
                . 'Please message us on Instagram and we will get straight back to you.';
        } else {
            catering_mail_confirmation($record);
        }
    }

    if (!$cat_errors) {
        $_SESSION['catering_sent'] = [
            'source'   => $which,
            'guests'   => (int) ($record['guests'] ?? 0),
            'package'  => $record['package'] ?? '',
            'occasion' => $record['occasion'] ?? '',
        ];
        $_SESSION['catering_token'] = bin2hex(random_bytes(16));
        header('Location: event-catering.php?sent=1#' . ($which === 'price_card' ? 'price' : 'quote'), true, 303);
        exit;
    }
}
