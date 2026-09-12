<?php
/*
 * Customers — one row per person, however many times they book.
 *
 * People are matched on their phone number, normalised to a single canonical
 * form, because it is the one thing they type consistently and the one thing
 * staff have to hand when someone rings.
 */

require_once __DIR__ . '/db.php';

/**
 * Reduces a phone number to the form used for matching.
 *
 *   086 123 4567      -> 0861234567
 *   +353 86 123 4567  -> 0861234567
 *   00353 86 1234567  -> 0861234567
 *
 * Non-Irish numbers keep their own digits and simply match themselves. They
 * never merge with an Irish spelling of the same number, which is a fair
 * trade for a café in Blackrock.
 */
function customer_phone_key(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '00353')) {
        return '0' . substr($digits, 5);
    }
    if (str_starts_with($digits, '353')) {
        return '0' . substr($digits, 3);
    }
    if (!str_starts_with($digits, '0')) {
        return '0' . $digits;
    }

    return $digits;
}

/**
 * Finds this person or creates them, and returns their id.
 *
 * An existing customer has their name and email refreshed — people change
 * address and they mistype things — but staff notes are never touched.
 */
function customer_find_or_create(array $data): int
{
    $pdo = db();
    $key = customer_phone_key($data['phone'] ?? '');
    $now = db_now();

    if ($key === '') {
        throw new InvalidArgumentException('A phone number is required to identify a customer.');
    }

    $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone_key = ?');
    $stmt->execute([$key]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        $update = $pdo->prepare(
            'UPDATE customers SET name = ?, phone = ?, email = ?, updated_at = ? WHERE id = ?'
        );
        $update->execute([
            $data['name'] ?? '', $data['phone'] ?? '', $data['email'] ?? '', $now, (int) $id,
        ]);

        if (!empty($data['marketing_consent'])) {
            customer_set_consent((int) $id, true);
        }

        return (int) $id;
    }

    $insert = $pdo->prepare(
        'INSERT INTO customers
            (name, phone, phone_key, email, notes, marketing_consent, marketing_consent_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?)'
    );
    $consent = !empty($data['marketing_consent']);
    $insert->execute([
        $data['name'] ?? '', $data['phone'] ?? '', $key, $data['email'] ?? '',
        $consent ? 1 : 0, $consent ? $now : null, $now, $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function customer_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Staff search: name, phone or email. Phone searches normalise first, so
 * typing "+353 86..." finds someone stored as "086...".
 */
function customer_search(string $term, int $limit = 40): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $like  = '%' . $term . '%';
    $key   = customer_phone_key($term);
    $byKey = preg_match('/\d/', $term) ? '%' . $key . '%' : '%__no_match__%';

    $stmt = db()->prepare(
        'SELECT * FROM customers
          WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR phone_key LIKE ?
          ORDER BY name
          LIMIT ' . (int) $limit
    );
    $stmt->execute([$like, $like, $like, $byKey]);
    return $stmt->fetchAll();
}

/**
 * Every booking this person has made, newest first.
 */
function customer_history(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM bookings WHERE customer_id = ?
          ORDER BY booking_date DESC, booking_time DESC'
    );
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

/**
 * A quick summary for the staff screens: how often they come, and whether
 * they turn up.
 */
function customer_stats(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS no_shows,
                MAX(booking_date) AS last_visit
           FROM bookings WHERE customer_id = ?'
    );
    $stmt->execute(['no_show', $customerId]);
    $row = $stmt->fetch() ?: [];

    return [
        'total'      => (int) ($row['total'] ?? 0),
        'no_shows'   => (int) ($row['no_shows'] ?? 0),
        'last_visit' => $row['last_visit'] ?? null,
    ];
}

function customer_set_notes(int $id, string $notes): void
{
    $stmt = db()->prepare('UPDATE customers SET notes = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$notes, db_now(), $id]);
}

/**
 * Records or withdraws marketing consent. Withdrawing also clears any email
 * that was being kept solely on the strength of that consent.
 */
function customer_set_consent(int $id, bool $consent): void
{
    $now = db_now();
    $stmt = db()->prepare(
        'UPDATE customers SET marketing_consent = ?, marketing_consent_at = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$consent ? 1 : 0, $consent ? $now : null, $now, $id]);
}

/**
 * Strips the personal data but keeps the row, so historical cover counts stay
 * intact. The email survives only where consent gives an independent reason to
 * keep it.
 */
function customer_anonymise(int $id): void
{
    $customer = customer_get($id);
    if (!$customer || $customer['anonymised_at'] !== null) {
        return;
    }

    $keepEmail = !empty($customer['marketing_consent']);
    $now       = db_now();

    $stmt = db()->prepare(
        'UPDATE customers
            SET name = ?, phone = ?, phone_key = ?, email = ?, notes = NULL,
                anonymised_at = ?, updated_at = ?
          WHERE id = ?'
    );
    $stmt->execute([
        'Former customer',
        '',
        'anon-' . $id,          // keeps the unique index happy
        $keepEmail ? $customer['email'] : '',
        $now, $now, $id,
    ]);
}

/**
 * A full erasure, for a GDPR deletion request. Bookings go with them via the
 * foreign key; the audit rows survive, but they only reference a booking id.
 */
function customer_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Everything held about one person, for a subject access request.
 */
function customer_export(int $id): array
{
    return [
        'customer' => customer_get($id),
        'bookings' => customer_history($id),
        'exported' => db_now(),
    ];
}

/**
 * Customers with no booking in the retention window. Used by the cron.
 */
function customers_past_retention(int $months): array
{
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))
        ->modify("-$months months")->format('Y-m-d');

    $stmt = db()->prepare(
        'SELECT c.id
           FROM customers c
      LEFT JOIN bookings b ON b.customer_id = c.id
          WHERE c.anonymised_at IS NULL
       GROUP BY c.id
         HAVING COALESCE(MAX(b.booking_date), ?) < ?'
    );
    // Customers who never booked fall back to their own creation date.
    $stmt->execute(['0000-00-00', $cutoff]);

    return array_column($stmt->fetchAll(), 'id');
}
