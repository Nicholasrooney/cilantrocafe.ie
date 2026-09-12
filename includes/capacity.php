<?php
/*
 * Capacity — how many covers the café can take in one slot.
 *
 * The public form uses this twice: once when it renders, to grey out full
 * slots, and once inside the insert transaction, to settle the race when two
 * people book the last table at the same moment. The second check is the one
 * that actually protects the café.
 */

require_once __DIR__ . '/db.php';

/**
 * What one booking costs against the slot limit: one table, or its heads.
 */
function capacity_units(int $guests, ?string $mode = null): int
{
    $mode = $mode ?? ($GLOBALS['booking']['capacity_mode'] ?? 'tables');
    return $mode === 'covers' ? max(1, $guests) : 1;
}

/**
 * The SQL fragment that totals a slot, which depends on what we are counting.
 */
function capacity_sum_expression(?string $mode = null): string
{
    $mode = $mode ?? ($GLOBALS['booking']['capacity_mode'] ?? 'tables');
    return $mode === 'covers' ? 'COALESCE(SUM(guests), 0)' : 'COUNT(*)';
}

function capacity_noun(?string $mode = null, int $n = 2): string
{
    $mode = $mode ?? ($GLOBALS['booking']['capacity_mode'] ?? 'tables');
    if ($mode === 'covers') {
        return $n === 1 ? 'seat' : 'seats';
    }
    return $n === 1 ? 'table' : 'tables';
}

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
 * Covers already booked on a date, keyed by time.
 *
 * @return array<string,int>
 */
function capacity_used(string $date, ?PDO $pdo = null, ?int $ignoreBookingId = null): array
{
    $pdo  = $pdo ?? db();
    $live = booking_live_statuses();

    $sql = 'SELECT booking_time, ' . capacity_sum_expression() . ' AS covers
              FROM bookings
             WHERE booking_date = ?
               AND status IN (' . implode(',', array_fill(0, count($live), '?')) . ')';
    $args = array_merge([$date], $live);

    if ($ignoreBookingId !== null) {
        $sql .= ' AND id <> ?';
        $args[] = $ignoreBookingId;
    }

    $sql .= ' GROUP BY booking_time';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    $used = [];
    foreach ($stmt->fetchAll() as $row) {
        $used[(string) $row['booking_time']] = (int) $row['covers'];
    }

    return $used;
}

/**
 * Seats still free in one slot.
 */
function capacity_remaining(string $date, string $time, int $max, ?PDO $pdo = null, ?int $ignoreBookingId = null): int
{
    $used = capacity_used($date, $pdo, $ignoreBookingId);
    return max(0, $max - (int) ($used[$time] ?? 0));
}

/**
 * Which of the café's slots can still take a party of this size.
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

    $cost = capacity_units($guests);
    foreach ($times as $time) {
        $avail[$time] = ((int) ($used[$time] ?? 0) + $cost) <= $max;
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

    $sql = 'SELECT ' . capacity_sum_expression() . ' AS covers
              FROM bookings
             WHERE booking_date = ? AND booking_time = ?
               AND status IN (' . implode(',', array_fill(0, count($live), '?')) . ')';
    $args = array_merge([$date, $time], $live);

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
    $cost      = capacity_units($guests);

    if ($used + $cost > $max) {
        throw new CapacityExceeded(
            $available === 0
                ? 'That time is fully booked.'
                : 'That time only has ' . $available . ' ' . capacity_noun(null, $available) . ' left.',
            $available
        );
    }
}
