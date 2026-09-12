<?php
/*
 * Cilantro Café: site settings
 * Edit the values below. Anything left as '' (empty) is hidden on the site.
 */

$site = [
    'name'      => 'Cilantro Café',
    'domain'    => 'cilantrocafe.ie',
    'instagram' => 'https://www.instagram.com/cilantrocafe_dublin/',

    // Contact details (fill these in, they appear in the footer and on the home page)
    'address'   => 'Unit 7, Newpark Centre, Newtownpark Avenue, Blackrock, Co. Dublin',
    'eircode'   => 'A94 W956',
    'phone'     => '',   // e.g. '01 234 5678'
    'email'     => '',   // e.g. 'hello@cilantro.ie'
    'maps_url'  => 'https://www.google.com/maps/search/?api=1&query=Cilantro+Cafe%2C+Newpark+Centre%2C+Blackrock%2C+A94+W956',

    // Optional but recommended: the exact pin, as "latitude,longitude".
    // Get it by right-clicking the café in Google Maps and copying the numbers.
    // When set, directions use this instead of the address text, which stops
    // map apps guessing at the wrong unit in the shopping centre.
    'coords'    => '',

    // Opening hours. Leave the array empty to hide the hours block.
    // Example: 'Mon to Fri' => '9:00 to 16:00',
    'hours' => [
    ],
];

// Booking form options
$booking = [
    // Time slots shown in the booking form. Match these to your opening hours.
    'times' => [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30',
        '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
    ],
    'max_guests'        => 10,  // bigger groups are asked to phone or email
    'days_ahead'        => 60,  // how far in advance people can book
    'seating'           => ['No preference', 'Booth inside', 'Table outside'],

    // Bookings are saved here until the backend is connected.
    // The data folder is blocked from public access by data/.htaccess
    'csv_file'          => __DIR__ . '/../data/bookings.csv',

    // For later: address that should receive booking notifications
    'notify_email'      => '',
];

/*
 * Google Calendar sync (one way: website -> Google).
 *
 * Setup is documented in docs/google-calendar-setup.md. In short: create a
 * service account, put its JSON key at the path below, then share the calendar
 * with the service account's email address giving it "Make changes to events".
 *
 * Leave 'enabled' false until the key file is in place. Sync failures never
 * block a booking either way — they are written to the log file instead.
 */
$calendar = [
    'enabled'          => false,
    'calendar_id'      => '',   // from Google Calendar > Settings > Integrate calendar
    'key_file'         => __DIR__ . '/secrets/google-service-account.json',
    'token_cache'      => __DIR__ . '/../data/.google-token.json',
    'log_file'         => __DIR__ . '/../data/calendar-sync.log',
    'timezone'         => 'Europe/Dublin',
    'duration_minutes' => 90,   // how long a table is held in the calendar

    // Token for calendar-test.php. Set to a long random string to run the test
    // page, then blank it again when you are finished.
    'test_token'       => '',
];

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
