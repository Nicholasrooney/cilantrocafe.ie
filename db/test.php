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

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customers.php';
require_once __DIR__ . '/../includes/bookings.php';
require_once __DIR__ . '/../includes/capacity.php';

$passed = 0;
$failed = 0;

function check(string $label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        echo "  PASS  $label\n";
        $passed++;
    } else {
        echo "  FAIL  $label\n";
        echo "          expected: " . var_export($expected, true) . "\n";
        echo "          actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}

function throws(string $label, string $exceptionClass, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  FAIL  $label (expected $exceptionClass, nothing thrown)\n";
        $failed++;
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            echo "  PASS  $label\n";
            $passed++;
        } else {
            echo "  FAIL  $label (expected $exceptionClass, got " . get_class($e) . ": {$e->getMessage()})\n";
            $failed++;
        }
    }
}

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

/**
 * Builds the schema on SQLite from the MySQL file, so the two cannot drift:
 * if a column is added to schema.sql the tests pick it up automatically.
 */
function fresh_database(): PDO
{
    // In memory: every call is a guaranteed-clean database, nothing to delete
    // afterwards, and no file for Windows to keep locked between runs.
    $pdo = db_connect(['driver' => 'sqlite', 'path' => ':memory:']);

    $sql = (string) file_get_contents(__DIR__ . '/schema.sql');
    $sql = preg_replace('/--[^\n]*/', '', $sql);                        // comments
    $sql = preg_replace('/\)\s*ENGINE=[^;]*;/s', ');', $sql);           // table options
    $sql = str_replace('INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = str_replace('INT UNSIGNED', 'INTEGER', $sql);
    $sql = preg_replace('/^[ \t]*(UNIQUE +)?KEY\b[^\n]*\n/mi', '', $sql);  // inline index definitions
    $sql = preg_replace('/,(\s*)\)/', '$1)', $sql);                     // the comma they left behind

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "Schema translation failed on:\n$statement\n\n" . $e->getMessage() . "\n");
            exit(1);
        }
    }

    // MySQL declared this inline as UNIQUE KEY; SQLite needs it as its own
    // statement. The customer-matching tests depend on it existing.
    $pdo->exec('CREATE UNIQUE INDEX uq_customers_phone_key ON customers(phone_key)');

    db($pdo);
    return $pdo;
}

echo "Cilantro Café — booking data layer tests\n";
echo str_repeat('=', 64) . "\n";

$pdo = fresh_database();
$GLOBALS['booking']['max_per_slot'] = 20;

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
    'seating' => 'Booth inside', 'notes' => 'window if possible', 'source' => 'website',
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

section('Capacity — counting covers');

$pdo = fresh_database();
$GLOBALS['booking']['capacity_mode'] = 'covers';
$GLOBALS['booking']['max_per_slot']  = 20;

booking_create([
    'name' => 'Party A', 'phone' => '0861000001', 'email' => 'a@example.com',
    'date' => '2026-10-02', 'time' => '13:00', 'guests' => 8, 'seating' => '', 'notes' => '',
]);
booking_create([
    'name' => 'Party B', 'phone' => '0861000002', 'email' => 'b@example.com',
    'date' => '2026-10-02', 'time' => '13:00', 'guests' => 8, 'seating' => '', 'notes' => '',
]);

check('covers counted', capacity_used('2026-10-02')['13:00'], 16);
check('remaining seats', capacity_remaining('2026-10-02', '13:00', 20), 4);

throws('overbooking is refused', CapacityExceeded::class, function () {
    booking_create([
        'name' => 'Party C', 'phone' => '0861000003', 'email' => 'c@example.com',
        'date' => '2026-10-02', 'time' => '13:00', 'guests' => 6, 'seating' => '', 'notes' => '',
    ]);
});

$fits = booking_create([
    'name' => 'Party D', 'phone' => '0861000004', 'email' => 'd@example.com',
    'date' => '2026-10-02', 'time' => '13:00', 'guests' => 4, 'seating' => '', 'notes' => '',
]);
check('a party that fits is accepted', $fits > 0, true);
check('slot now full', capacity_remaining('2026-10-02', '13:00', 20), 0);

$override = booking_create([
    'name' => 'Party E', 'phone' => '0861000005', 'email' => 'e@example.com',
    'date' => '2026-10-02', 'time' => '13:00', 'guests' => 4, 'seating' => '', 'notes' => '',
], false, 'staff');
check('staff can deliberately overbook', $override > 0, true);

booking_set_status($fits, 'cancelled', 'staff');
check('a cancellation frees its seats', capacity_remaining('2026-10-02', '13:00', 24), 4);

$avail = capacity_slot_availability('2026-10-02', ['12:00', '13:00'], 20, 2);
check('empty slot offered',  $avail['12:00'], true);
check('full slot withheld',  $avail['13:00'], false);

section('Capacity — counting tables (the café’s actual setting)');

$pdo = fresh_database();
$GLOBALS['booking']['capacity_mode'] = 'tables';
$GLOBALS['booking']['max_per_slot']  = 15;

// Fourteen parties, deliberately of wildly different sizes: in table mode the
// sizes must not matter, only the number of bookings.
for ($i = 1; $i <= 14; $i++) {
    booking_create([
        'name' => "Table $i", 'phone' => '08610001' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        'email' => "t$i@example.com",
        'date' => '2026-10-05', 'time' => '13:00', 'guests' => ($i % 9) + 1,
        'seating' => '', 'notes' => '',
    ]);
}

check('bookings counted, not heads', capacity_used('2026-10-05')['13:00'], 14);
check('one table left',              capacity_remaining('2026-10-05', '13:00', 15), 1);

$last = booking_create([
    'name' => 'Table 15', 'phone' => '0861000115', 'email' => 't15@example.com',
    'date' => '2026-10-05', 'time' => '13:00', 'guests' => 9, 'seating' => '', 'notes' => '',
]);
check('a party of nine still takes just one table', $last > 0, true);
check('now full', capacity_remaining('2026-10-05', '13:00', 15), 0);

throws('the sixteenth is refused', CapacityExceeded::class, function () {
    booking_create([
        'name' => 'Table 16', 'phone' => '0861000116', 'email' => 't16@example.com',
        'date' => '2026-10-05', 'time' => '13:00', 'guests' => 1, 'seating' => '', 'notes' => '',
    ]);
});

check('a single diner costs one table', capacity_units(1), 1);
check('a party of ten also costs one', capacity_units(10), 1);
check('in covers mode a party of ten costs ten', capacity_units(10, 'covers'), 10);
check('the noun follows the mode', capacity_noun('tables', 3), 'tables');
check('and is singular for one',   capacity_noun('tables', 1), 'table');

// Back to the default for whatever follows.
$GLOBALS['booking']['capacity_mode'] = 'tables';

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
