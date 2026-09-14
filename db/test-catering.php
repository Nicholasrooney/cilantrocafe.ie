<?php
/*
 * Tests for event catering: menu-linked prices, estimates, the price card's
 * contact field, and storing enquiries.
 *
 *     php db/test-catering.php
 */

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../includes/catering.php';

echo "Cilantro Café — event catering tests\n";
echo str_repeat('=', 64) . "\n";

// ---------------------------------------------------------------- prices

section('Package prices come from the menu');

$packages = catering_packages();
check('taco bar = cheapest taco trio',              $packages['taco_bar']['price'], 15.5);
check('brunch = cheapest of chilaquiles/burrito',   $packages['brunch_spread']['price'], 14.9);
check('tortas = cheapest torta',                    $packages['torta_platters']['price'], 15.5);
check('feast = cheapest of enchiladas/flautas/birria', $packages['full_feast']['price'], 17.0);
check('coffee & bakery = cheapest bakery + coffee', $packages['coffee_bakery']['price'], 8.0);

check('every package resolves to a price', count(array_filter(array_column($packages, 'price'), 'is_null')), 0);

check('groups are summed from their cheapest dish',
    catering_package_price(['price_from' => [['A', 'B'], ['C']]], ['a' => 5.0, 'b' => 3.0, 'c' => 2.0]), 5.0);
check('dish names match regardless of case',
    catering_package_price(['price_from' => [['STEAK TACO']]]), 16.5);
check('a dish missing from the menu gives no price, not a wrong one',
    catering_package_price(['price_from' => [['A dish we stopped making']]]), null);
check('one missing group sinks the whole package',
    catering_package_price(['price_from' => [['Steak taco'], ['Nope']]]), null);

foreach ($GLOBALS['catering']['occasions'] as $key => $occasion) {
    check("occasion '$key' suggests a real package", isset($packages[$occasion['package']]), true);
}

// ---------------------------------------------------------------- estimates

section('Estimates and display');

check('50 at €15.50 is around €775',          catering_estimate(15.5, 50), 775);
check('rounds to the nearest €5 (298 → 300)', catering_estimate(14.9, 20), 300);
check('rounds to the nearest €5 (264 → 265)', catering_estimate(8.0, 33), 265);
check('no price means no estimate',            catering_estimate(null, 50), null);
check('per-person keeps cents',                catering_money(15.5), '€15.50');
check('totals drop cents',                     catering_money(775.0, false), '€775');
check('large totals get a separator',          catering_money(3100.0, false), '€3,100');

check('guests below the minimum clamp up',   catering_clamp_guests(5), 20);
check('guests above the maximum clamp down', catering_clamp_guests(900), 200);
check('guests in range are left alone',     catering_clamp_guests(60), 60);

// ---------------------------------------------------------------- contact

section('Reading the price card\'s phone-or-email field');

check('an email',                 catering_parse_contact('sam@example.com'), ['phone' => '', 'email' => 'sam@example.com']);
check('a local mobile',           catering_parse_contact('086 123 4567'),    ['phone' => '086 123 4567', 'email' => '']);
check('an international number',  catering_parse_contact('+353 86 123 4567'), ['phone' => '+353 86 123 4567', 'email' => '']);
check('a name alone is refused',  catering_parse_contact('Sam'), null);
check('too few digits is refused', catering_parse_contact('123'), null);
check('words around a number are refused', catering_parse_contact('call me 0861234567'), null);
check('empty is refused',         catering_parse_contact('   '), null);

// ---------------------------------------------------------------- alerts

section('Who gets alerted');

$saved = $GLOBALS['booking']['notify_email'];

check('both configured addresses receive alerts', mail_notify_recipients(),
    ['nicholas.rooney2010@gmail.com', 'Alex@cilantro.ie']);

$GLOBALS['booking']['notify_email'] = 'one@example.com, two@example.com';
check('a comma-separated string also works', mail_notify_recipients(), ['one@example.com', 'two@example.com']);

$GLOBALS['booking']['notify_email'] = ['ok@example.com', 'not an email', '', 'OK@example.com'];
check('invalid entries dropped, duplicates removed', mail_notify_recipients(), ['ok@example.com']);

$GLOBALS['booking']['notify_email'] = '';
check('nobody configured means nobody', mail_notify_recipients(), []);

$GLOBALS['booking']['notify_email'] = $saved;

// ---------------------------------------------------------------- storage

section('Storing enquiries');

fresh_database();

$id = catering_save([
    'source' => 'price_card', 'phone' => '086 123 4567',
    'occasion' => 'birthday', 'guests' => 50, 'package' => 'taco_bar',
    'price_per_person' => 15.5, 'estimate_total' => 775,
]);
check('saved', $id > 0, true);

$row = catering_get($id);
check('starts as new',             $row['status'], 'new');
check('keeps the price shown',     (float) $row['price_per_person'], 15.5);
check('keeps the estimate shown',  (int) $row['estimate_total'], 775);
check('no date given stays null',  $row['event_date'], null);

$full = catering_save([
    'source' => 'full_form', 'name' => 'Aoife Byrne', 'phone' => '0861112222', 'email' => 'aoife@example.com',
    'occasion' => 'wedding', 'event_date' => '2026-11-14', 'guests' => 120, 'package' => 'full_feast',
    'fulfilment' => 'delivery', 'delivery_address' => 'Blackrock Park', 'notes' => '3 vegetarians',
    'marketing_consent' => true, 'price_per_person' => 17.0, 'estimate_total' => 2040,
]);
$row = catering_get($full);
check('full form keeps delivery address', $row['delivery_address'], 'Blackrock Park');
check('full form keeps consent',          (int) $row['marketing_consent'], 1);

check('newest first', array_column(catering_list(), 'id'), [$full, $id]);

check('status change',  catering_set_status($id, 'quoted'), true);
check('filter by status', array_column(catering_list('quoted'), 'id'), [$id]);
check('counts per status', catering_counts(), ['new' => 1, 'quoted' => 1, 'booked' => 0, 'lost' => 0]);

throws('unknown status refused', InvalidArgumentException::class, function () use ($id) {
    catering_set_status($id, 'maybe');
});

catering_set_staff_notes($id, 'Rang back, sending quote Friday');
check('staff notes saved', catering_get($id)['staff_notes'], 'Rang back, sending quote Friday');

catering_delete($id);
check('deleted', catering_get($id), null);

// ---------------------------------------------------------------- first use

section('Table is created on first use');

// The live site was set up before catering existed. Make sure an enquiry still
// lands when the table is missing.
db()->exec('DROP TABLE catering_enquiries');
$again = catering_save(['source' => 'price_card', 'email' => 'x@example.com', 'guests' => 20, 'package' => 'taco_bar']);
check('saved into a freshly created table', $again > 0, true);
check('and it reads back', catering_get($again)['email'], 'x@example.com');

// ---------------------------------------------------------------- retention

section('Retention');

fresh_database();
$old  = catering_save(['source' => 'full_form', 'name' => 'Old', 'guests' => 30, 'package' => 'taco_bar']);
$keep = catering_save(['source' => 'full_form', 'name' => 'Recent', 'guests' => 30, 'package' => 'taco_bar']);
db()->prepare('UPDATE catering_enquiries SET updated_at = ? WHERE id = ?')->execute(['2020-01-01 10:00:00', $old]);

check('dry run counts without deleting', catering_purge_old(24, true), 1);
check('still there after dry run',       catering_get($old) !== null, true);
check('purge removes the old one',       catering_purge_old(24), 1);
check('old enquiry gone',                catering_get($old), null);
check('recent enquiry kept',             catering_get($keep) !== null, true);

finish();
