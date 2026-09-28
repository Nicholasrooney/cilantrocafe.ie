<?php
/*
 * Test suite for the booking data layer.
 *
 * Runs against a throwaway SQLite database so it can be run anywhere, with no
 * MySQL and no risk to real data:
 *
 *     php db/test.php
 *
 * The application code is driver-agnostic, so what passes here is the same
 * code path that runs on MySQL in production.
 */

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../includes/customers.php';
require_once __DIR__ . '/../includes/bookings.php';
require_once __DIR__ . '/../includes/capacity.php';

echo "Cilantro Café — booking data layer tests\n";
echo str_repeat('=', 64) . "\n";

$pdo = fresh_database();
$GLOBALS['booking']['max_bookings_per_hour'] = 2;

// ---------------------------------------------------------------- phone keys

section('Phone normalisation (the customer matching rule)');

check('local format',            customer_phone_key('086 123 4567'),      '0861234567');
check('international +353',      customer_phone_key('+353 86 123 4567'),  '0861234567');
check('international 00353',     customer_phone_key('00353 86 1234567'),  '0861234567');
check('no leading zero',         customer_phone_key('86 123 4567'),       '0861234567');
check('punctuation stripped',    customer_phone_key('(086) 123-4567'),    '0861234567');
check('landline kept',           customer_phone_key('01 234 5678'),       '012345678');
check('empty stays empty',       customer_phone_key(''),                  '');

// ---------------------------------------------------------------- customers

section('Customers are one row per person');

$a = customer_find_or_create(['name' => 'Nicholas Rooney', 'phone' => '086 878 6928', 'email' => 'nick@example.com']);
$b = customer_find_or_create(['name' => 'Nicholas Rooney', 'phone' => '+353 86 878 6928', 'email' => 'nick@example.com']);
check('same person via two phone formats', $b, $a);

$c = customer_find_or_create(['name' => 'Someone Else', 'phone' => '086 111 2222', 'email' => 'else@example.com']);
check('different person gets a new row', $c !== $a, true);

customer_find_or_create(['name' => 'Nick Rooney', 'phone' => '0868786928', 'email' => 'new@example.com']);
$updated = customer_get($a);
check('name refreshed on return visit',  $updated['name'],  'Nick Rooney');
check('email refreshed on return visit', $updated['email'], 'new@example.com');

customer_set_notes($a, 'Allergic to nuts');
customer_find_or_create(['name' => 'Nick Rooney', 'phone' => '0868786928', 'email' => 'new@example.com']);
check('staff notes survive a rebooking', customer_get($a)['notes'], 'Allergic to nuts');

throws('a booking without a phone is rejected', InvalidArgumentException::class, function () {
    customer_find_or_create(['name' => 'No Phone', 'phone' => '', 'email' => 'x@example.com']);
});

// ---------------------------------------------------------------- bookings

section('Bookings');

$id = booking_create([
    'name' => 'Nick Rooney', 'phone' => '0868786928', 'email' => 'new@example.com',
    'date' => '2026-10-01', 'time' => '13:00', 'guests' => 4,
    'seating' => 'Stools inside', 'notes' => 'window if possible', 'source' => 'website',
]);
check('booking created', $id > 0, true);

$row = booking_get($id);
check('joins the customer on',   $row['name'],   'Nick Rooney');
check('starts as requested',     $row['status'], 'requested');
check('records its source',      $row['source'], 'website');
check('guests stored',           (int) $row['guests'], 4);

check('status change succeeds',  booking_set_status($id, 'confirmed', 'staff'), true);
check('status actually changed', booking_get($id)['status'], 'confirmed');
check('no-op status change returns false', booking_set_status($id, 'confirmed', 'staff'), false);

throws('unknown status rejected', InvalidArgumentException::class, function () use ($id) {
    booking_set_status($id, 'teleported', 'staff');
});

$history = booking_history($id);
check('audit trail recorded creation and the change', count($history) >= 2, true);
check('audit names the actor', $history[0]['actor'], 'staff');

booking_update($id, ['date' => '2026-10-01', 'time' => '14:00', 'guests' => 6, 'seating' => 'Table outside', 'notes' => ''], 'staff');
$row = booking_get($id);
check('edit applied (time)',   $row['booking_time'], '14:00');
check('edit applied (guests)', (int) $row['guests'], 6);
check('edit is audited',       str_contains(booking_history($id)[0]['action'], 'edited'), true);

// ---------------------------------------------------------------- capacity

section('Capacity — two bookings an hour');

$pdo = fresh_database();
$GLOBALS['booking']['max_bookings_per_hour'] = 2;

function party(string $time, int $guests = 2, bool $enforce = true, string $phoneTail = ''): int
{
    static $n = 0;
    $n++;
    return booking_create([
        'name' => "Party $n", 'phone' => '08610000' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
        'email' => "p$n@example.com", 'date' => '2026-10-02', 'time' => $time,
        'guests' => $guests, 'seating' => 'Stools inside', 'notes' => '',
    ], $enforce, 'website');
}

$first = party('13:00', 4);
check('first booking of the hour', $first > 0, true);

$second = party('13:30', 1);
check('13:30 shares the 13:00 hour and is the second', $second > 0, true);
check('the hour is now counted as two', capacity_used('2026-10-02')['13'], 2);
check('nothing left in that hour', capacity_remaining('2026-10-02', '13:00', 2), 0);
check('and the half past is just as full', capacity_remaining('2026-10-02', '13:30', 2), 0);

throws('a third booking at 13:00 is refused', CapacityExceeded::class, function () {
    party('13:00', 2);
});
throws('a third at 13:30 is refused too — same hour', CapacityExceeded::class, function () {
    party('13:30', 2);
});

check('the next hour is free', capacity_remaining('2026-10-02', '14:00', 2), 2);
$third = party('14:00', 2);
check('14:00 books fine', $third > 0, true);

// Party size must not matter: two singles fill an hour as surely as two fours.
check('a single diner still costs a whole booking', capacity_remaining('2026-10-02', '14:00', 2), 1);

$override = party('13:00', 3, false);
check('staff can still overbook deliberately', $override > 0, true);

booking_set_status($first, 'cancelled', 'staff');
check('a cancellation frees one of the hour', capacity_remaining('2026-10-02', '13:00', 2), 0);
booking_set_status($override, 'cancelled', 'staff');
check('and another frees the next', capacity_remaining('2026-10-02', '13:30', 2), 1);

$avail = capacity_slot_availability('2026-10-02', ['13:00', '13:30', '15:00'], 2);
check('half-full hour still offered',  $avail['13:00'], true);
check('same for its half past',        $avail['13:30'], true);
check('an untouched hour is offered',  $avail['15:00'], true);

party('15:00', 2);
party('15:30', 2);
$avail = capacity_slot_availability('2026-10-02', ['15:00', '15:30', '16:00'], 2);
check('a full hour is withheld',            $avail['15:00'], false);
check('including its half past',            $avail['15:30'], false);
check('the hour after is still offered',    $avail['16:00'], true);

check('hour of 09:30 is 09', capacity_hour('09:30'), '09');
check('hour of 16:00 is 16', capacity_hour('16:00'), '16');
check('the noun is plural by default', capacity_noun(2), 'bookings');
check('and singular for one',          capacity_noun(1), 'booking');

// ---------------------------------------------------------------- GDPR

section('Retention and erasure');

$pdo = fresh_database();

$keep = customer_find_or_create(['name' => 'Consented', 'phone' => '0862000001', 'email' => 'keep@example.com', 'marketing_consent' => true]);
$drop = customer_find_or_create(['name' => 'Not Consented', 'phone' => '0862000002', 'email' => 'drop@example.com']);

check('consent recorded',     (int) customer_get($keep)['marketing_consent'], 1);
check('consent timestamped',  customer_get($keep)['marketing_consent_at'] !== null, true);
check('no consent by default', (int) customer_get($drop)['marketing_consent'], 0);

customer_anonymise($drop);
$row = customer_get($drop);
check('name removed',           $row['name'],  'Former customer');
check('phone removed',          $row['phone'], '');
check('email removed',          $row['email'], '');
check('anonymisation stamped',  $row['anonymised_at'] !== null, true);

customer_anonymise($keep);
$row = customer_get($keep);
check('name removed even with consent', $row['name'], 'Former customer');
check('email kept where consent justifies it', $row['email'], 'keep@example.com');

customer_set_consent($keep, false);
check('withdrawing consent clears the flag', (int) customer_get($keep)['marketing_consent'], 0);

$erase = customer_find_or_create(['name' => 'Erase Me', 'phone' => '0862000003', 'email' => 'erase@example.com']);
booking_create([
    'name' => 'Erase Me', 'phone' => '0862000003', 'email' => 'erase@example.com',
    'date' => '2026-10-03', 'time' => '12:00', 'guests' => 2, 'seating' => '', 'notes' => '',
]);
$export = customer_export($erase);
check('export includes their bookings', count($export['bookings']), 1);

customer_delete($erase);
check('customer erased', customer_get($erase), null);
check('their bookings went too', count(bookings_for_date('2026-10-03')), 0);

// ---------------------------------------------------------------- result

echo "\n" . str_repeat('=', 64) . "\n";
echo $failed === 0
    ? "ALL $passed TESTS PASSED\n"
    : "$passed passed, $failed FAILED\n";

exit($failed === 0 ? 0 : 1);
