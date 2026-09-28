<?php
/*
 * Capacity — how many bookings the café takes in one hour.
 *
 * Two bookings an hour, whatever their size, and at most four people on a
 * booking. Slots come round every half hour, so 13:00 and 13:30 share the
 * 13:00 hour and together use up that hour's two bookings.
 *
 * The public form uses this twice: once when it renders, to grey out full
 * times, and once inside the insert transaction, to settle the race when two
 * people book the last slot at the same moment. The second check is the one
 * that actually protects the café.
 */

require_once __DIR__ . '/db.php';

class CapacityExceeded extends RuntimeException
{
    public int $available;

    public function __construct(string $message, int $available)
    {
        parent::__construct($message);
        $this->available = $available;
    }
}

/**
 * The hour a time belongs to: "13:30" -> "13".
 */
function capacity_hour(string $time): string
{
    return substr($time, 0, 2);
}

function capacity_noun(int $n = 2): string
{
    return $n === 1 ? 'booking' : 'bookings';
}

/**
 * Bookings already taken on a date, keyed by hour ("13" => 2).
 *
 * @return array<string,int>
 */
function capacity_used(string $date, ?PDO $pdo = null, ?int $ignoreBookingId = null): array
{
    $pdo  = $pdo ?? db();
    $live = booking_live_statuses();

    $sql = 'SELECT booking_time
              FROM bookings
             WHERE booking_date = ?
               AND status IN (' . implode(',', array_fill(0, count($live), '?')) . ')';
    $args = array_merge([$date], $live);

    if ($ignoreBookingId !== null) {
        $sql .= ' AND id <> ?';
        $args[] = $ignoreBookingId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    $used = [];
    foreach ($stmt->fetchAll() as $row) {
        $hour = capacity_hour((string) $row['booking_time']);
        $used[$hour] = ($used[$hour] ?? 0) + 1;
    }

    return $used;
}

/**
 * Bookings still free in the hour this time falls in.
 */
function capacity_remaining(string $date, string $time, int $max, ?PDO $pdo = null, ?int $ignoreBookingId = null): int
{
    $used = capacity_used($date, $pdo, $ignoreBookingId);
    return max(0, $max - (int) ($used[capacity_hour($time)] ?? 0));
}

/**
 * Which times can still be booked.
 *
 * Party size makes no difference here — every booking costs one of the hour's
 * two slots — but the argument stays so callers read the same as before.
 *
 * @return array<string,bool>  time => has room
 */
function capacity_slot_availability(string $date, array $times, int $max, int $guests = 1): array
{
    if ($max <= 0) {
        return array_fill_keys($times, true);
    }

    $used  = capacity_used($date);
    $avail = [];

    foreach ($times as $time) {
        $avail[$time] = ((int) ($used[capacity_hour($time)] ?? 0) + 1) <= $max;
    }

    return $avail;
}

/**
 * The authoritative check, called inside the insert transaction.
 *
 * On MySQL this takes a locking read so a second request waits rather than
 * reading a stale total. SQLite serialises write transactions anyway, so the
 * test suite gets the same guarantee without the syntax.
 *
 * @throws CapacityExceeded
 */
function capacity_assert_room(
    string $date,
    string $time,
    int $guests,
    int $max,
    ?int $ignoreBookingId = null,
    ?PDO $pdo = null
): void {
    $pdo  = $pdo ?? db();
    $live = booking_live_statuses();

    // Everything inside the same clock hour counts, so 13:00 and 13:30 share.
    $sql = 'SELECT COUNT(*)
              FROM bookings
             WHERE booking_date = ? AND booking_time LIKE ?
               AND status IN (' . implode(',', array_fill(0, count($live), '?')) . ')';
    $args = array_merge([$date, capacity_hour($time) . ':%'], $live);

    if ($ignoreBookingId !== null) {
        $sql .= ' AND id <> ?';
        $args[] = $ignoreBookingId;
    }

    if (db_is_mysql($pdo)) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    $used      = (int) $stmt->fetchColumn();
    $available = max(0, $max - $used);

    if ($used + 1 > $max) {
        throw new CapacityExceeded(
            $available === 0
                ? 'That hour is fully booked.'
                : 'That hour only has ' . $available . ' ' . capacity_noun($available) . ' left.',
            $available
        );
    }
}
