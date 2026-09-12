<?php
/*
 * SEO: titles, meta, canonicals, Open Graph and structured data.
 *
 * Built to match the pattern used on soundsystemhire.ie and towingireland.ie,
 * which rank well: a keyword-led title, a real description, a canonical URL,
 * Open Graph tags, and a JSON-LD @graph on every page.
 *
 * The difference here is the business type. A café is a Restaurant in
 * schema.org terms, not a LocalBusiness, and Google treats the two very
 * differently — Restaurant is what earns the opening hours, price range,
 * cuisine and "reserve a table" treatment in search results. The menu gets
 * its own Menu graph built from the real menu data, so the dishes themselves
 * can surface.
 */

require_once __DIR__ . '/config.php';

function seo_base_url(): string
{
    global $site;
    return 'https://' . $site['domain'];
}

/**
 * Absolute URL for a path like 'menu.php'.
 */
function seo_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return seo_base_url() . '/' . ($path === 'index.php' ? '' : $path);
}

/**
 * The canonical URL for the page being rendered.
 */
function seo_canonical(): string
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return seo_url($script);
}

/**
 * The café as schema.org Restaurant. Every page carries this.
 */
function seo_restaurant_node(): array
{
    global $site, $booking;

    $node = [
        '@type'        => ['Restaurant', 'CafeOrCoffeeShop'],
        '@id'          => seo_base_url() . '/#restaurant',
        'name'         => $site['name'],
        'url'          => seo_base_url(),
        'image'        => seo_url('images/storefront.jpg'),
        'servesCuisine' => ['Mexican', 'Brunch', 'Coffee'],
        'priceRange'   => '€€',
        'currenciesAccepted' => 'EUR',
        'description'  => 'Mexican café in Blackrock, Dublin. Tacos, birria, flautas, tortas, '
                        . 'brunch plates and fresh conchas, with coffee and a short wine list.',
        'hasMenu'      => seo_url('menu.php'),
        'acceptsReservations' => seo_url('booking.php'),
    ];

    if (!empty($site['address'])) {
        // "Unit 7, Newpark Centre, Newtownpark Avenue, Blackrock, Co. Dublin"
        $node['address'] = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Unit 7, Newpark Centre, Newtownpark Avenue',
            'addressLocality' => 'Blackrock',
            'addressRegion'   => 'Co. Dublin',
            'postalCode'      => $site['eircode'] ?? '',
            'addressCountry'  => 'IE',
        ];
    }

    if (!empty($site['coords'])) {
        [$lat, $lng] = array_map('trim', explode(',', $site['coords']) + [1 => '']);
        if ($lat !== '' && $lng !== '') {
            $node['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }
    }

    if (!empty($site['phone'])) {
        $node['telephone'] = $site['phone'];
    }
    if (!empty($site['email'])) {
        $node['email'] = $site['email'];
    }
    if (!empty($site['instagram'])) {
        $node['sameAs'] = [$site['instagram']];
    }

    // Opening hours come from $booking['service_hours'] via hours.php, the same
    // source the site and the booking form use, so the three cannot disagree.
    // Closed days are published explicitly — Google reads a missing day as
    // unknown, but an explicit closure shows as "Closed", which is what stops
    // somebody driving over on a Monday.
    if (!empty($booking['service_hours'])) {
        require_once __DIR__ . '/hours.php';
        $node['openingHoursSpecification'] = hours_schema($booking['service_hours']);
    }

    return $node;
}

/**
 * Turns the human-readable hours in config into schema.org specifications.
 *
 * Understands the shapes the config invites, for example:
 *     'Mon to Fri' => '9:00 to 16:00'
 *     'Saturday'   => '09:00 - 17:00'
 * Anything it cannot parse is skipped rather than guessed at.
 */
function seo_opening_hours(array $hours): array
{
    $days = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];
    $order = array_values($days);
    $out   = [];

    foreach ($hours as $label => $time) {
        if (!preg_match('/(\d{1,2}[:.]\d{2})\s*(?:to|–|—|-)\s*(\d{1,2}[:.]\d{2})/u', (string) $time, $m)) {
            continue;
        }
        $open  = str_replace('.', ':', $m[1]);
        $close = str_replace('.', ':', $m[2]);
        if (strlen($open) === 4)  { $open  = '0' . $open; }
        if (strlen($close) === 4) { $close = '0' . $close; }

        // Which days does this label cover?
        $found = [];
        if (preg_match_all('/(mon|tue|wed|thu|fri|sat|sun)/i', (string) $label, $dm)) {
            $names = array_map(fn($d) => $days[strtolower($d)], $dm[1]);

            // "Mon to Fri" means the range, "Mon, Wed" means just those.
            if (count($names) === 2 && preg_match('/\b(to|–|—|-)\b/iu', (string) $label)) {
                $from = array_search($names[0], $order, true);
                $to   = array_search($names[1], $order, true);
                if ($from !== false && $to !== false) {
                    for ($i = $from; ; $i = ($i + 1) % 7) {
                        $found[] = $order[$i];
                        if ($i === $to) { break; }
                    }
                }
            } else {
                $found = $names;
            }
        }

        if (!$found) {
            continue;
        }

        $out[] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => array_values(array_unique($found)),
            'opens'     => $open,
            'closes'    => $close,
        ];
    }

    return $out;
}

/**
 * The menu as structured data, built from the same data that renders the page.
 * One source of truth: change a price and both move together.
 */
function seo_menu_node(array $menu): array
{
    global $site;

    $sections = [];

    foreach ($menu as $section) {
        $items = [];
        foreach ($section['items'] as $item) {
            $entry = [
                '@type' => 'MenuItem',
                'name'  => $item['name'],
                'offers' => [
                    '@type'         => 'Offer',
                    'price'         => $item['price'],
                    'priceCurrency' => 'EUR',
                ],
            ];
            if (!empty($item['desc'])) {
                $entry['description'] = $item['desc'];
            }
            $items[] = $entry;
        }

        $sections[] = [
            '@type'           => 'MenuSection',
            'name'            => $section['title'],
            'hasMenuItem'     => $items,
        ];
    }

    return [
        '@type'          => 'Menu',
        '@id'            => seo_url('menu.php') . '#menu',
        'name'           => $site['name'] . ' menu',
        'inLanguage'     => 'en-IE',
        'hasMenuSection' => $sections,
    ];
}

/**
 * Breadcrumbs for an inner page.
 */
function seo_breadcrumbs(array $trail): array
{
    $items = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => seo_base_url(),
    ]];

    $position = 2;
    foreach ($trail as $name => $path) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $name,
            'item'     => seo_url($path),
        ];
    }

    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/**
 * An FAQ block. Only ever built from answers that are actually true of the
 * café — a wrong answer in a rich result is worse than no rich result.
 */
function seo_faq(array $questions): array
{
    $entities = [];

    foreach ($questions as $q => $a) {
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
        ];
    }

    return ['@type' => 'FAQPage', 'mainEntity' => $entities];
}

/**
 * Prints the whole JSON-LD graph for a page.
 *
 * @param array $extra  additional nodes (menu, FAQ, breadcrumbs)
 */
function seo_render_schema(array $extra = []): void
{
    global $site;

    $graph = [
        seo_restaurant_node(),
        [
            '@type'       => 'WebSite',
            '@id'         => seo_base_url() . '/#website',
            'url'         => seo_base_url(),
            'name'        => $site['name'],
            'inLanguage'  => 'en-IE',
            'publisher'   => ['@id' => seo_base_url() . '/#restaurant'],
        ],
    ];

    foreach ($extra as $node) {
        if ($node) {
            $graph[] = $node;
        }
    }

    echo '<script type="application/ld+json">'
       . json_encode(['@context' => 'https://schema.org', '@graph' => $graph],
                     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
       . '</script>' . "\n";
}
