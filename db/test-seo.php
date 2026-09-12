<?php
/*
 * Tests for the SEO helpers — mainly the opening-hours parser, which turns the
 * free text in config.php into schema.org specifications. Wrong hours in a
 * Google result send people to a locked door, so this one matters.
 *
 *     php db/test-seo.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/seo.php';

$passed = $failed = 0;

function check(string $label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        echo "  PASS  $label\n";
        $passed++;
    } else {
        echo "  FAIL  $label\n";
        echo "          expected: " . json_encode($expected) . "\n";
        echo "          actual:   " . json_encode($actual) . "\n";
        $failed++;
    }
}

echo "SEO helpers\n" . str_repeat('=', 64) . "\n\nOpening hours parsing\n---------------------\n";

$r = seo_opening_hours(['Mon to Fri' => '9:00 to 16:00']);
check('weekday range expands to five days', count($r[0]['dayOfWeek']), 5);
check('first day is Monday',   $r[0]['dayOfWeek'][0], 'Monday');
check('last day is Friday',    $r[0]['dayOfWeek'][4], 'Friday');
check('open time zero-padded', $r[0]['opens'],  '09:00');
check('close time kept',       $r[0]['closes'], '16:00');

$r = seo_opening_hours(['Saturday' => '09:00 - 17:00']);
check('single day',            $r[0]['dayOfWeek'], ['Saturday']);
check('dash separator works',  $r[0]['closes'], '17:00');

$r = seo_opening_hours(['Sat and Sun' => '10.00 to 18.00']);
check('two listed days',       $r[0]['dayOfWeek'], ['Saturday', 'Sunday']);
check('dots in time handled',  $r[0]['opens'], '10:00');

$r = seo_opening_hours(['Sat to Mon' => '10:00 to 16:00']);
check('range wrapping the week', $r[0]['dayOfWeek'], ['Saturday', 'Sunday', 'Monday']);

$r = seo_opening_hours(['Mon to Fri' => '9:00 to 16:00', 'Sunday' => '11:00 to 15:00']);
check('several rules kept separate', count($r), 2);

check('unparseable text is skipped, not guessed', seo_opening_hours(['Mon' => 'all day']), []);
check('no hours means no claim',                  seo_opening_hours([]), []);

echo "\nURLs and schema\n---------------\n";

check('home canonical has no index.php', seo_url('index.php'), 'https://cilantrocafe.ie/');
check('inner page url',                  seo_url('menu.php'),  'https://cilantrocafe.ie/menu.php');

$node = seo_restaurant_node();
check('typed as both Restaurant and café', $node['@type'], ['Restaurant', 'CafeOrCoffeeShop']);
check('locality is Blackrock',             $node['address']['addressLocality'], 'Blackrock');
check('eircode carried through',           $node['address']['postalCode'], 'A94 W956');
check('opening hours published', count($node['openingHoursSpecification']), 7);

$byDay = [];
foreach ($node['openingHoursSpecification'] as $spec) {
    $byDay[basename($spec['dayOfWeek'])] = $spec['opens'] . '-' . $spec['closes'];
}
check('Monday published as closed',  $byDay['Monday'],   '00:00-00:00');
check('weekday hours',               $byDay['Wednesday'], '09:00-16:00');
check('weekend hours run later',     $byDay['Saturday'],  '09:00-17:00');

echo "
Opening hours as the single source
----------------------------------
";

check('Monday is closed',          hours_is_open('2026-09-14'), false);
check('Tuesday is open',           hours_is_open('2026-09-15'), true);
check('no slots on a closed day',  hours_slots_for_date('2026-09-14'), []);

$tue = hours_slots_for_date('2026-09-15');
$sat = hours_slots_for_date('2026-09-19');
check('weekday opens at 09:00',    $tue[0], '09:00');
check('weekday last slot is 15:00 (an hour before a 16:00 close)', end($tue), '15:00');
check('weekend last slot is 16:00 (an hour before a 17:00 close)', end($sat), '16:00');
check('weekend has two more slots than a weekday', count($sat) - count($tue), 2);

check('next open day after Monday is Tuesday', hours_next_open_date('2026-09-14'), '2026-09-15');

$display = hours_display();
check('closed day shown as Closed',  $display['Monday'], 'Closed');
check('consecutive days grouped',    isset($display['Tuesday to Friday']), true);
check('a pair joined with &',        isset($display['Saturday & Sunday']), true);

// The staff form offers every slot the cafe ever runs, so they can take a
// booking outside normal service if they want to.
check('staff slot list spans both schedules', in_array('16:00', $booking['times'], true), true);

require __DIR__ . '/../includes/menu-data.php';
$menuNode = seo_menu_node($menu);
$items = array_sum(array_map(fn($s) => count($s['hasMenuItem']), $menuNode['hasMenuSection']));
check('every menu section in schema', count($menuNode['hasMenuSection']), count($menu));
check('every dish in schema',         $items, 68);
check('prices carried in euro',       $menuNode['hasMenuSection'][0]['hasMenuItem'][0]['offers']['priceCurrency'], 'EUR');

$crumbs = seo_breadcrumbs(['Menu' => 'menu.php']);
check('breadcrumb starts at home', $crumbs['itemListElement'][0]['name'], 'Home');
check('breadcrumb positions',      $crumbs['itemListElement'][1]['position'], 2);

echo "\n" . str_repeat('=', 64) . "\n";
echo $failed === 0 ? "ALL $passed TESTS PASSED\n" : "$passed passed, $failed FAILED\n";
exit($failed === 0 ? 0 : 1);
