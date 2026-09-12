<?php
/*
 * Opening hours — the single source of truth.
 *
 * Everything derives from $booking['service_hours']: the hours shown on the
 * site, the structured data Google reads, and which time slots the booking
 * form offers on a given day. Change the hours in one place and all three
 * move together.
 *
 * This matters more than it looks. Before this existed the booking form
 * offered the same slots every day, so it would happily take a table for a
 * Monday the café is shut.
 */

/** Days in week order, with the keys used in config. */
function hours_day_names(): array
{
    return [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
        'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];
}

function hours_config(): array
{
    return $GLOBALS['booking']['service_hours'] ?? [];
}

/**
 * Opening and closing time for a date, or null when the café is closed.
 *
 * @return array{0:string,1:string}|null
 */
function hours_for_date(string $date, ?array $service = null): ?array
{
    $service = $service ?? hours_config();

    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Dublin'));
    if (!$day) {
        return null;
    }

    $key   = strtolower($day->format('D'));   // mon, tue, ...
    $entry = $service[$key] ?? null;

    return is_array($entry) && count($entry) === 2 ? $entry : null;
}

function hours_is_open(string $date, ?array $service = null): bool
{
    return hours_for_date($date, $service) !== null;
}

/**
 * The bookable slots for a date.
 *
 * Runs from opening to a set number of minutes before closing, because a table
 * booked five minutes before the doors shut is no use to anybody.
 */
function hours_slots_for_date(string $date, ?array $service = null, ?int $slotMinutes = null, ?int $buffer = null): array
{
    $window = hours_for_date($date, $service);
    if (!$window) {
        return [];
    }

    $slotMinutes = $slotMinutes ?? (int) ($GLOBALS['booking']['slot_minutes'] ?? 30);
    $buffer      = $buffer      ?? (int) ($GLOBALS['booking']['last_booking_before_close'] ?? 60);

    $tz    = new DateTimeZone('Europe/Dublin');
    $open  = new DateTimeImmutable($date . ' ' . $window[0], $tz);
    $close = (new DateTimeImmutable($date . ' ' . $window[1], $tz))->modify("-$buffer minutes");

    $slots = [];
    for ($t = $open; $t <= $close; $t = $t->modify("+$slotMinutes minutes")) {
        $slots[] = $t->format('H:i');
    }

    return $slots;
}

/**
 * Every slot the café ever offers, across all days. The staff form uses this
 * so they can put a booking anywhere, including outside normal service.
 */
function hours_all_slots(?array $service = null): array
{
    $service = $service ?? hours_config();
    $slots   = [];

    // Any Monday-to-Sunday week will do; we only want the times.
    $monday = (new DateTimeImmutable('monday this week', new DateTimeZone('Europe/Dublin')));
    for ($i = 0; $i < 7; $i++) {
        $slots = array_merge($slots, hours_slots_for_date($monday->modify("+$i days")->format('Y-m-d'), $service));
    }

    $slots = array_values(array_unique($slots));
    sort($slots);

    return $slots;
}

/**
 * Hours for display, with runs of identical days grouped:
 *
 *     ['Tuesday to Friday' => '09:00 to 16:00',
 *      'Saturday & Sunday' => '09:00 to 17:00',
 *      'Monday'            => 'Closed']
 */
function hours_display(?array $service = null): array
{
    $service = $service ?? hours_config();
    $names   = hours_day_names();

    // Group consecutive days that share the same hours.
    $groups = [];
    foreach ($names as $key => $name) {
        $entry = $service[$key] ?? null;
        $value = is_array($entry) && count($entry) === 2 ? $entry[0] . ' to ' . $entry[1] : 'Closed';

        if ($groups && end($groups)['value'] === $value) {
            $groups[count($groups) - 1]['days'][] = $name;
        } else {
            $groups[] = ['days' => [$name], 'value' => $value];
        }
    }

    $out = [];
    foreach ($groups as $group) {
        $days  = $group['days'];
        $count = count($days);

        if ($count === 1) {
            $label = $days[0];
        } elseif ($count === 2) {
            $label = $days[0] . ' & ' . $days[1];
        } else {
            $label = $days[0] . ' to ' . $days[$count - 1];
        }

        $out[$label] = $group['value'];
    }

    return $out;
}

/**
 * schema.org OpeningHoursSpecification, built from the same source.
 *
 * Closed days are stated explicitly rather than omitted — Google treats a
 * missing day as unknown, but an explicit closure shows as "Closed" in the
 * search result, which is what stops somebody driving over on a Monday.
 */
function hours_schema(?array $service = null): array
{
    $service = $service ?? hours_config();
    $names   = hours_day_names();
    $out     = [];

    foreach ($names as $key => $name) {
        $entry = $service[$key] ?? null;

        if (is_array($entry) && count($entry) === 2) {
            $out[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $name,
                'opens'     => $entry[0],
                'closes'    => $entry[1],
            ];
        } else {
            $out[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $name,
                'opens'     => '00:00',
                'closes'    => '00:00',
            ];
        }
    }

    return $out;
}

/**
 * The next date the café is actually open, for pointing people somewhere
 * useful when they land on a closed day.
 */
function hours_next_open_date(string $from, ?array $service = null): ?string
{
    $tz  = new DateTimeZone('Europe/Dublin');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $tz);
    if (!$day) {
        return null;
    }

    for ($i = 0; $i < 14; $i++) {
        $candidate = $day->modify("+$i days")->format('Y-m-d');
        if (hours_is_open($candidate, $service)) {
            return $candidate;
        }
    }

    return null;
}
