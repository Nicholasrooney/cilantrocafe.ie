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
    'address'   => '',   // e.g. '12 Main Street, Deansgrange, Co. Dublin, A94 XXXX'
    'phone'     => '',   // e.g. '01 234 5678'
    'email'     => '',   // e.g. 'hello@cilantro.ie'
    'maps_url'  => '',   // Google Maps share link

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

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
