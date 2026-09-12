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

    // Opening hours are NOT set here. They come from $booking['service_hours']
    // below, and are filled in at the bottom of this file, so the hours shown
    // on the site, the hours Google is told, and the times the booking form
    // offers can never disagree with each other.
    'hours' => [],
];

// Booking form options
$booking = [
    /*
     * Opening hours, per day. [open, close] in 24-hour time, or null if the
     * café is closed that day. This is the single source of truth — it drives
     * the hours on the site, the hours Google shows, and the slots the booking
     * form offers.
     */
    'service_hours' => [
        'mon' => null,                 // closed
        'tue' => ['09:00', '16:00'],
        'wed' => ['09:00', '16:00'],
        'thu' => ['09:00', '16:00'],
        'fri' => ['09:00', '16:00'],
        'sat' => ['09:00', '17:00'],
        'sun' => ['09:00', '17:00'],
    ],

    // How often a slot comes round, in minutes.
    'slot_minutes' => 30,

    // The last booking is this many minutes before closing, so nobody books a
    // table for five minutes before the doors shut. Weekdays close at 16:00,
    // so the last slot is 15:00; weekends close at 17:00, so it is 16:00.
    'last_booking_before_close' => 60,

    // Filled in below from service_hours. Do not edit by hand.
    'times' => [],
    'max_guests'        => 10,  // bigger groups are asked to phone or email
    'days_ahead'        => 60,  // how far in advance people can book
    'seating'           => ['No preference', 'Booth inside', 'Table outside'],

    // Bookings are saved here until the backend is connected.
    // The data folder is blocked from public access by data/.htaccess
    'csv_file'          => __DIR__ . '/../data/bookings.csv',

    // Where booking alerts land. A gmail address is completely fine here —
    // this is a recipient, not a sender.
    'notify_email'      => 'nicholas.rooney2010@gmail.com',

    /*
     * How much the café can take in one time slot.
     *
     * 'tables' counts BOOKINGS — one booking, one table. With 15 tables, 15.
     *          This is how most cafés think about it.
     * 'covers' counts PEOPLE. Use this if you would rather cap total heads,
     *          for example 50 covers regardless of how they are grouped.
     *
     * The trade-off with 'tables': a party of eight is counted as one table
     * even though it probably takes two or three. If big groups start causing
     * trouble, switch to 'covers'.
     *
     * The public form stops taking bookings once a slot is full. Staff can
     * still override, and the override is recorded.
     */
    'capacity_mode' => 'covers',
    'max_per_slot'  => 30,

    // Customer details are anonymised this many months after their last visit.
    'retention_months'    => 24,
];

/*
 * Database. Create it in hPanel > Databases > MySQL Databases, then run
 * db/schema.sql through phpMyAdmin. See docs/database-setup.md.
 *
 * Credentials belong in includes/secrets/db.php, which is gitignored — see the
 * bottom of this file.
 */
$db = [
    'driver'  => 'mysql',
    'host'    => 'localhost',
    'name'    => '',
    'user'    => '',
    'pass'    => '',
    'charset' => 'utf8mb4',
    'path'    => '',   // only used when driver is 'sqlite' (the test suite)
];

/*
 * Staff area at /staff/.
 *
 * Generate the hash by visiting /staff/hash.php, then paste it here. Never put
 * the plain password in this file.
 */
$staff = [
    'password_hash'   => '',
    'session_hours'   => 12,
    'max_attempts'    => 5,
    'lockout_minutes' => 15,
];

/*
 * Outgoing email.
 *
 * Fill in 'from' with a mailbox on this domain — mail claiming to come from a
 * gmail.com address will be treated as forged and binned.
 *
 * SMTP is strongly preferred over bare mail(): create the mailbox in hPanel >
 * Emails, then put its details here. Leave smtp.host empty to use mail().
 * Either way, set SPF and DKIM for the domain or confirmations go to spam.
 */
$mail = [
    /*
     * 'from' MUST be on this domain. A message claiming to come from a
     * gmail.com address but sent through Hostinger fails SPF and gets binned
     * or spam-filed — so the café's own address sends it, and replies are
     * pointed at Nicholas's inbox instead.
     *
     * This address does not need a mailbox to send from. Create one at
     * hPanel > Emails when you want to receive at it too, then fill in the
     * smtp block below.
     */
    'from'      => 'bookings@cilantrocafe.ie',
    'from_name' => 'Cilantro Café',
    'reply_to'  => 'nicholas.rooney2010@gmail.com',
    'log_file'  => __DIR__ . '/../data/mail.log',

    'smtp' => [
        // Hostinger's outgoing mail server. Takes effect once 'user' below is
        // filled in with the mailbox address and the password is set in
        // includes/secrets/db.php.
        'host'     => 'smtp.hostinger.com',
        'port'     => 587,
        'security' => 'tls',        // 'tls' (STARTTLS), 'ssl', or '' for none
        'user'     => '',           // the full email address
        'pass'     => '',           // put this in includes/secrets/db.php
        'timeout'  => 10,
    ],
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

/*
 * Everything below is derived from service_hours. One source of truth: change
 * the hours above and the site, the schema and the booking form all follow.
 */
require_once __DIR__ . '/hours.php';

$site['hours']     = hours_display($booking['service_hours']);
$booking['times']  = hours_all_slots($booking['service_hours']);

/*
 * Credentials live outside this file so they never reach Git.
 *
 * Preferred location is one level above public_html, where the web server
 * cannot serve it at all. includes/secrets/db.php is the fallback; it is
 * gitignored and blocked by .htaccess.
 *
 * The file just overrides what it needs, for example:
 *
 *     <?php
 *     $db['name'] = 'u123_cilantro';
 *     $db['user'] = 'u123_cilantro';
 *     $db['pass'] = '...';
 *     $staff['password_hash'] = '$2y$...';
 */
foreach ([__DIR__ . '/../../private/secrets.php', __DIR__ . '/secrets/db.php'] as $secretsFile) {
    if (is_readable($secretsFile)) {
        require $secretsFile;
        break;
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
