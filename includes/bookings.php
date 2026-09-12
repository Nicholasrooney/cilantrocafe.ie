<?php
/*
 * Bookings — creating them, moving them through their statuses, and reading
 * them back for the staff calendar.
 *
 * Every change that matters is written to booking_audit. With a shared staff
 * password that trail is the only record of what happened, so nothing here
 * changes a booking without logging it.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/capacity.php';

/**
 * A booking counts against a slot unless it is in one of the dead statuses.
 */
function booking_statuses(): array
{
    return ['requested', 'confirmed', 'seated', 'completed', 'no_show', 'cancelled'];
}

function booking_live_statuses(): array
{
    return ['requested', 'confirmed', 'seated', 'completed'];
}

function booking_sources(): array
{
    return ['website', 'phone', 'walk_in'];
}

function booking_status_label(string $status): string
{
    return [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'seated'    => 'Seated',
        'completed' => 'Completed',
        'no_show'   => 'No show',
        'cancelled' => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

/**
 * Creates a booking, and the customer behind it if they are new.
 *
 * The capacity check and the insert happen in one transaction: checking when
 * the form renders is not enough, because two people can pass that check in
 * the same second and both take the last seats.
 *
 * @param array $data  name, phone, email, date, time, guests, seating, notes,
 *                     marketing_consent, source
 * @param bool  $enforceCapacity  false lets staff overbook deliberately
 * @throws CapacityExceeded when the slot is full and capacity is enforced
 */
function booking_create(array $data, bool $enforceCapacity = true, string $actor = 'website'): int
{
    global $booking;

    $max = (int) ($booking['max_per_slot'] ?? 0);

    return db_transaction(function (PDO $pdo) use ($data, $enforceCapacity, $actor, $max) {
        $guests = (int) $data['guests'];

        if ($enforceCapacity && $max > 0) {
            capacity_assert_room($data['date'], $data['time'], $guests, $max, null, $pdo);
        }

        $customerId = customer_find_or_create($data);
        $now        = db_now();
        $source     = in_array($data['source'] ?? '', booking_sources(), true) ? $data['source'] : 'website';

        $stmt = $pdo->prepare(
            'INSERT INTO bookings
                (customer_id, booking_date, booking_time, guests, seating, notes,
                 status, source, changed_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customerId,
            $data['date'],
            $data['time'],
            $guests,
            $data['seating'] ?? '',
            ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            $data['status'] ?? 'requested',
            $source,
            $actor,
            $now,
            $now,
        ]);

        $id = (int) $pdo->lastInsertId();
        booking_audit($id, 'created', null, $data['status'] ?? 'requested', $actor);

        return $id;
    });
}

/**
 * One booking, with the customer's details joined on — which is what every
 * screen actually needs.
 */
function booking_get(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT b.*, c.name, c.phone, c.email, c.notes AS customer_notes, c.id AS customer_id
           FROM bookings b
           JOIN customers c ON c.id = b.customer_id
          WHERE b.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Every booking on one day, in time order.
 */
function bookings_for_date(string $date, bool $includeDead = true): array
{
    return bookings_for_range($date, $date, $includeDead);
}

/**
 * Bookings between two dates inclusive.
 */
function bookings_for_range(string $from, string $to, bool $includeDead = true): array
{
    $sql = 'SELECT b.*, c.name, c.phone, c.email, c.notes AS customer_notes
              FROM bookings b
              JOIN customers c ON c.id = b.customer_id
             WHERE b.booking_date BETWEEN ? AND ?';
    $args = [$from, $to];

    if (!$includeDead) {
        $live = booking_live_statuses();
        $sql .= ' AND b.status IN (' . implode(',', array_fill(0, count($live), '?')) . ')';
        $args = array_merge($args, $live);
    }

    $sql .= ' ORDER BY b.booking_date, b.booking_time, b.id';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/**
 * Moves a booking to a new status.
 */
function booking_set_status(int $id, string $status, string $actor = 'staff'): bool
{
    if (!in_array($status, booking_statuses(), true)) {
        throw new InvalidArgumentException("Unknown booking status: $status");
    }

    $current = booking_get($id);
    if (!$current || $current['status'] === $status) {
        return false;
    }

    $stmt = db()->prepare('UPDATE bookings SET status = ?, changed_by = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$status, $actor, db_now(), $id]);

    booking_audit($id, 'status', $current['status'], $status, $actor);
    return true;
}

/**
 * Edits the booking itself — time, size, seating, notes.
 */
function booking_update(int $id, array $data, string $actor = 'staff'): bool
{
    $current = booking_get($id);
    if (!$current) {
        return false;
    }

    $stmt = db()->prepare(
        'UPDATE bookings
            SET booking_date = ?, booking_time = ?, guests = ?, seating = ?, notes = ?,
                changed_by = ?, updated_at = ?
          WHERE id = ?'
    );
    $stmt->execute([
        $data['date'], $data['time'], (int) $data['guests'], $data['seating'] ?? '',
        ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
        $actor, db_now(), $id,
    ]);

    $changed = [];
    foreach (['booking_date' => 'date', 'booking_time' => 'time', 'guests' => 'guests'] as $col => $field) {
        if ((string) $current[$col] !== (string) $data[$field]) {
            $changed[] = "$field: {$current[$col]} -> {$data[$field]}";
        }
    }

    booking_audit($id, 'edited' . ($changed ? ' (' . implode(', ', $changed) . ')' : ''), $current['status'], $current['status'], $actor);
    return true;
}

function booking_set_google_event(int $id, ?string $eventId): void
{
    $stmt = db()->prepare('UPDATE bookings SET google_event_id = ? WHERE id = ?');
    $stmt->execute([$eventId, $id]);
}

/**
 * Writes one line of history. Truncated to fit the column rather than throwing,
 * because failing to log must never fail the thing being logged.
 */
function booking_audit(int $bookingId, string $action, ?string $old, ?string $new, string $actor): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO booking_audit (booking_id, action, old_status, new_status, actor, ip, at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $bookingId,
            substr($action, 0, 40),
            $old,
            $new,
            substr($actor, 0, 60),
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            db_now(),
        ]);
    } catch (Throwable $e) {
        // Deliberately swallowed.
    }
}

function booking_history(int $bookingId): array
{
    $stmt = db()->prepare('SELECT * FROM booking_audit WHERE booking_id = ? ORDER BY id DESC');
    $stmt->execute([$bookingId]);
    return $stmt->fetchAll();
}

/**
 * Headline numbers for the top of the day view.
 */
function bookings_day_summary(string $date): array
{
    $rows   = bookings_for_date($date);
    $live   = booking_live_statuses();
    $covers = 0;

    foreach ($rows as $row) {
        if (in_array($row['status'], $live, true)) {
            $covers += (int) $row['guests'];
        }
    }

    return [
        'bookings' => count($rows),
        'covers'   => $covers,
    ];
}
