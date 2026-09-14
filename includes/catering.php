<?php
/*
 * Event catering: packages, prices and enquiries.
 *
 * Prices come from the café menu, never from a second copy of the numbers.
 * Each package in $catering['packages'] names the dishes it is built from; the
 * "from" price per person is the cheapest dish in each group, added up.
 *
 * Enquiries are stored before anything else happens to them, so a lead is never
 * lost to an email hiccup.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/* ------------------------------------------------------------ prices */

/**
 * Every menu price keyed by lower-cased dish name.
 */
function catering_menu_prices(): array
{
    static $prices = null;
    if ($prices !== null) {
        return $prices;
    }

    $menu = [];
    require __DIR__ . '/menu-data.php';

    $prices = [];
    foreach ($menu as $section) {
        foreach ($section['items'] as $item) {
            $prices[mb_strtolower(trim($item['name']))] = (float) $item['price'];
        }
    }

    return $prices;
}

/**
 * "From" price per person for one package, or null if any of its groups no
 * longer matches a dish on the menu — better "price on request" than a wrong
 * number.
 */
function catering_package_price(array $package, ?array $prices = null): ?float
{
    $prices = $prices ?? catering_menu_prices();
    $groups = $package['price_from'] ?? [];

    if (!$groups) {
        return null;
    }

    $total = 0.0;
    foreach ($groups as $group) {
        $found = [];
        foreach ($group as $dish) {
            $key = mb_strtolower(trim($dish));
            if (isset($prices[$key])) {
                $found[] = $prices[$key];
            }
        }
        if (!$found) {
            return null;
        }
        $total += min($found);
    }

    return round($total, 2);
}

/**
 * The packages from config, each with its live price attached.
 */
function catering_packages(): array
{
    global $catering;

    $out = [];
    foreach ($catering['packages'] as $key => $package) {
        $out[$key] = $package + [
            'key'   => $key,
            'price' => catering_package_price($package),
        ];
    }

    return $out;
}

/**
 * A rough total for the price card, rounded to the nearest €5 so it reads as
 * the estimate it is rather than a quote.
 */
function catering_estimate(?float $perPerson, int $guests): ?int
{
    if ($perPerson === null || $guests <= 0) {
        return null;
    }
    return (int) (round(($perPerson * $guests) / 5) * 5);
}

/**
 * Euro amount for display. Per-person prices keep their cents (€15.50);
 * estimated totals drop them (€775), because they are rounded anyway.
 */
function catering_money(float $amount, bool $cents = true): string
{
    return '€' . number_format($amount, $cents ? 2 : 0);
}

function catering_clamp_guests(int $guests): int
{
    global $catering;
    return max((int) $catering['min_guests'], min((int) $catering['max_guests'], $guests));
}

/**
 * Reads the single "phone or email" field on the price card.
 *
 * @return array{phone:string,email:string}|null  null if it is neither
 */
function catering_parse_contact(string $value): ?array
{
    $value = trim($value);

    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return ['phone' => '', 'email' => $value];
    }

    $digits = preg_replace('/\D/', '', $value);
    if (strlen($digits) >= 7 && strlen($digits) <= 15 && !preg_match('/[a-z]/i', $value)) {
        return ['phone' => $value, 'email' => ''];
    }

    return null;
}

function catering_statuses(): array
{
    return ['new' => 'New', 'quoted' => 'Quoted', 'booked' => 'Booked', 'lost' => 'Lost'];
}

function catering_occasion_label(string $key): string
{
    global $catering;
    return $catering['occasions'][$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
}

function catering_package_name(string $key): string
{
    global $catering;
    return $catering['packages'][$key]['name'] ?? ucfirst(str_replace('_', ' ', $key));
}

/* ------------------------------------------------------------ storage */

/**
 * Creates the enquiries table if it is not there yet.
 *
 * The live site was set up before catering existed, so rather than asking for
 * another trip into phpMyAdmin the table is created the first time it is
 * needed, from the same statement in db/schema.sql.
 */
function catering_create_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql    = (string) file_get_contents(__DIR__ . '/../db/schema.sql');

    foreach (db_sql_statements($sql, $driver) as $statement) {
        if (stripos($statement, 'catering_enquiries') !== false) {
            $pdo->exec($statement);
        }
    }
}

/**
 * Columns added to catering_enquiries after it first went live. Each one is
 * added the first time a query trips over it being missing, so the live
 * database never needs a manual migration.
 */
function catering_column_migrations(): array
{
    return [
        'location' => "ALTER TABLE catering_enquiries ADD COLUMN location VARCHAR(200) NOT NULL DEFAULT ''",
    ];
}

/**
 * Runs a query, creating the table or a newer column once and retrying if
 * either is missing.
 */
function catering_query(callable $work)
{
    $pdo = db();

    try {
        return $work($pdo);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $missing = stripos($msg, 'catering_enquiries') !== false
            && (stripos($msg, "doesn't exist") !== false || stripos($msg, 'no such table') !== false);

        if ($missing) {
            catering_create_table($pdo);
            return $work($pdo);
        }

        foreach (catering_column_migrations() as $column => $ddl) {
            $mentions = stripos($msg, $column) !== false
                && (stripos($msg, 'unknown column') !== false
                    || stripos($msg, 'no such column') !== false
                    || stripos($msg, 'has no column') !== false);
            if ($mentions) {
                $pdo->exec($ddl);
                return $work($pdo);
            }
        }

        throw $e;
    }
}

/**
 * Stores one enquiry. Returns its id.
 */
function catering_save(array $d): int
{
    return catering_query(function (PDO $pdo) use ($d) {
        $now = db_now();
        $stmt = $pdo->prepare(
            'INSERT INTO catering_enquiries
                (source, name, phone, email, occasion, event_date, guests, location, package,
                 price_per_person, estimate_total, fulfilment, delivery_address, notes,
                 marketing_consent, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['source'] ?? 'full_form',
            $d['name'] ?? '',
            $d['phone'] ?? '',
            $d['email'] ?? '',
            $d['occasion'] ?? '',
            ($d['event_date'] ?? '') !== '' ? $d['event_date'] : null,
            (int) $d['guests'],
            mb_substr(trim((string) ($d['location'] ?? '')), 0, 200),
            $d['package'] ?? '',
            $d['price_per_person'] ?? null,
            $d['estimate_total'] ?? null,
            $d['fulfilment'] ?? '',
            ($d['delivery_address'] ?? '') !== '' ? $d['delivery_address'] : null,
            ($d['notes'] ?? '') !== '' ? $d['notes'] : null,
            !empty($d['marketing_consent']) ? 1 : 0,
            'new',
            $now,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    });
}

function catering_get(int $id): ?array
{
    return catering_query(function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('SELECT * FROM catering_enquiries WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    });
}

/**
 * Enquiries newest first, optionally only one status.
 */
function catering_list(?string $status = null, int $limit = 200): array
{
    return catering_query(function (PDO $pdo) use ($status, $limit) {
        $sql  = 'SELECT * FROM catering_enquiries';
        $args = [];
        if ($status !== null && isset(catering_statuses()[$status])) {
            $sql   .= ' WHERE status = ?';
            $args[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    });
}

/**
 * How many enquiries sit in each status, for the tabs on the staff screen.
 */
function catering_counts(): array
{
    $counts = array_fill_keys(array_keys(catering_statuses()), 0);

    $rows = catering_query(function (PDO $pdo) {
        return $pdo->query('SELECT status, COUNT(*) AS n FROM catering_enquiries GROUP BY status')->fetchAll();
    });

    foreach ($rows as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['n'];
        }
    }

    return $counts;
}

function catering_set_status(int $id, string $status): bool
{
    if (!isset(catering_statuses()[$status])) {
        throw new InvalidArgumentException("Unknown catering status: $status");
    }

    return catering_query(function (PDO $pdo) use ($id, $status) {
        $stmt = $pdo->prepare('UPDATE catering_enquiries SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, db_now(), $id]);
        return $stmt->rowCount() > 0;
    });
}

function catering_set_staff_notes(int $id, string $notes): void
{
    catering_query(function (PDO $pdo) use ($id, $notes) {
        $stmt = $pdo->prepare('UPDATE catering_enquiries SET staff_notes = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$notes !== '' ? $notes : null, db_now(), $id]);
    });
}

function catering_delete(int $id): void
{
    catering_query(function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('DELETE FROM catering_enquiries WHERE id = ?');
        $stmt->execute([$id]);
    });
}

/**
 * Deletes enquiries untouched for longer than the retention period. Used by
 * the nightly cron. Returns how many went (or would go, on a dry run).
 */
function catering_purge_old(int $months, bool $dryRun = false): int
{
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))
        ->modify("-$months months")->format('Y-m-d H:i:s');

    return catering_query(function (PDO $pdo) use ($cutoff, $dryRun) {
        if ($dryRun) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM catering_enquiries WHERE updated_at < ?');
            $stmt->execute([$cutoff]);
            return (int) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('DELETE FROM catering_enquiries WHERE updated_at < ?');
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    });
}

/* ------------------------------------------------------------ email */

/**
 * The details of an enquiry as plain lines, shared by both emails.
 */
function catering_summary_lines(array $d): array
{
    $lines = [
        'Occasion: ' . catering_occasion_label($d['occasion'] ?? ''),
        'Guests:   ' . (int) $d['guests'],
        'Package:  ' . catering_package_name($d['package'] ?? ''),
    ];

    if (!empty($d['location'])) {
        $lines[] = 'Location: ' . $d['location'];
    }

    if (!empty($d['price_per_person'])) {
        $est = !empty($d['estimate_total']) ? ' (around ' . catering_money((float) $d['estimate_total'], false) . ' total)' : '';
        $lines[] = 'Price:    from ' . catering_money((float) $d['price_per_person']) . ' per person' . $est;
    }
    if (!empty($d['event_date'])) {
        $lines[] = 'Date:     ' . (new DateTimeImmutable($d['event_date']))->format('l j F Y');
    }
    if (!empty($d['fulfilment'])) {
        $lines[] = 'Getting it there: ' . ($d['fulfilment'] === 'delivery' ? 'Delivery' : 'Pickup from the café');
    }
    if (!empty($d['delivery_address'])) {
        $lines[] = 'Deliver to: ' . $d['delivery_address'];
    }
    if (!empty($d['notes'])) {
        $lines[] = '';
        $lines[] = 'Notes: ' . $d['notes'];
    }

    return $lines;
}

/**
 * Tells the café a catering lead has arrived.
 */
function catering_mail_alert(array $d): bool
{
    global $site;

    if (!mail_notify_recipients()) {
        return false;
    }

    $who = trim(($d['name'] ?? '') !== '' ? $d['name'] : (($d['phone'] ?? '') ?: ($d['email'] ?? '')));

    $lines = array_merge(
        [
            'New catering enquiry from the website'
                . (($d['source'] ?? '') === 'price_card' ? ' (quick price request).' : '.'),
            '',
            'Name:     ' . (($d['name'] ?? '') !== '' ? $d['name'] : '—'),
            'Phone:    ' . (($d['phone'] ?? '') !== '' ? $d['phone'] : '—'),
            'Email:    ' . (($d['email'] ?? '') !== '' ? $d['email'] : '—'),
            '',
        ],
        catering_summary_lines($d),
        ['', 'See all enquiries: https://' . $site['domain'] . '/staff/catering.php']
    );

    return mail_send_alert(
        sprintf('Catering enquiry: %s, %d guests', $who !== '' ? $who : 'new lead', (int) $d['guests']),
        implode("\n", $lines),
        ['reply_to' => ($d['email'] ?? '') !== '' ? $d['email'] : null]
    );
}

/**
 * Confirmation to the person enquiring, when they gave an email.
 */
function catering_mail_confirmation(array $d): bool
{
    global $site;

    if (empty($d['email'])) {
        return false;
    }

    $hello = ($d['name'] ?? '') !== '' ? 'Hi ' . mail_first_name($d['name']) . ',' : 'Hi,';

    $lines = array_merge(
        [
            $hello,
            '',
            'Thanks for asking us about catering. Here is what you sent:',
            '',
        ],
        array_map(fn($l) => '  ' . $l, catering_summary_lines($d)),
        [
            '',
            "We'll come back to you with a quote for your event.",
            '',
            $site['name'] . ', ' . $site['address'] . (!empty($site['eircode']) ? ', ' . $site['eircode'] : ''),
            'https://' . $site['domain'],
        ]
    );

    return mail_send($d['email'], 'Your catering enquiry — ' . $site['name'], implode("\n", $lines));
}
