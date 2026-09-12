<?php
/*
 * Week view — seven days as vertical timelines running 09:00 to 17:00, so you
 * can see at a glance when each day is busy rather than just how many bookings
 * it has.
 *
 * Bookings are positioned by their start time and sized by how long a table is
 * held. Where two overlap they sit side by side in lanes, the way a calendar
 * does, so nothing is hidden behind anything else.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/hours.php';
staff_require_login();
staff_require_database();

$tz    = new DateTimeZone('Europe/Dublin');
$today = new DateTimeImmutable('today', $tz);

$start = $_GET['from'] ?? $today->format('Y-m-d');
$from  = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz) ?: $today;
$from  = $from->modify('monday this week');
$to    = $from->modify('+6 days');

$rows = bookings_for_range($from->format('Y-m-d'), $to->format('Y-m-d'));
$live = booking_live_statuses();

$byDay = [];
foreach ($rows as $row) {
    $byDay[$row['booking_date']][] = $row;
}

// The timeline runs across the café's widest day, so nothing falls off the end.
$DAY_START = 9 * 60;      // 09:00
$DAY_END   = 17 * 60;     // 17:00
$SPAN      = $DAY_END - $DAY_START;
$HOLD      = (int) ($calendar['duration_minutes'] ?? 90);

function minutes_of(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm) + [1 => '0']);
    return $h * 60 + $m;
}

/**
 * Assigns overlapping bookings to side-by-side lanes.
 *
 * Returns [rows with 'lane' and 'lanes' set]. Without this, two bookings at the
 * same time would sit exactly on top of each other and one would be invisible.
 */
function assign_lanes(array $bookings, int $hold): array
{
    usort($bookings, fn($a, $b) => strcmp($a['booking_time'], $b['booking_time']));

    $laneEnds = [];     // lane index => minute that lane becomes free
    foreach ($bookings as &$b) {
        $begin = minutes_of($b['booking_time']);
        $end   = $begin + $hold;

        $lane = null;
        foreach ($laneEnds as $i => $freeAt) {
            if ($freeAt <= $begin) {
                $lane = $i;
                break;
            }
        }
        if ($lane === null) {
            $lane = count($laneEnds);
        }

        $laneEnds[$lane] = $end;
        $b['lane'] = $lane;
    }
    unset($b);

    $total = max(1, count($laneEnds));
    foreach ($bookings as &$b) {
        $b['lanes'] = $total;
    }
    unset($b);

    return $bookings;
}

staff_head('Week of ' . $from->format('j M'), 'week', true);
?>

<div class="dayhead">
    <a class="nav-arrow" href="?from=<?= e($from->modify('-7 days')->format('Y-m-d')) ?>" aria-label="Previous week">&#8249;</a>
    <div class="dayhead-mid">
        <h1>Week<span><?= e($from->format('j M')) ?> – <?= e($to->format('j M Y')) ?></span></h1>
        <?php if ($from->format('Y-m-d') !== $today->modify('monday this week')->format('Y-m-d')): ?>
            <a class="today-link" href="week.php">This week</a>
        <?php endif; ?>
    </div>
    <a class="nav-arrow" href="?from=<?= e($from->modify('+7 days')->format('Y-m-d')) ?>" aria-label="Next week">&#8250;</a>
</div>

<div class="timeline-scroll">
<div class="timeline" style="--span: <?= $SPAN ?>;">

    <!-- time axis -->
    <div class="tl-axis">
        <div class="tl-axis-head"></div>
        <div class="tl-axis-body">
            <?php for ($m = $DAY_START; $m <= $DAY_END; $m += 60): ?>
                <span class="tl-hour" style="top: <?= (($m - $DAY_START) / $SPAN) * 100 ?>%">
                    <?= sprintf('%02d:00', intdiv($m, 60)) ?>
                </span>
            <?php endfor; ?>
        </div>
    </div>

    <?php for ($i = 0; $i < 7; $i++): ?>
        <?php
        $day     = $from->modify("+$i days");
        $key     = $day->format('Y-m-d');
        $dayRows = assign_lanes($byDay[$key] ?? [], $HOLD);
        $window  = hours_for_date($key);

        $covers = 0;
        foreach ($dayRows as $r) {
            if (in_array($r['status'], $live, true)) {
                $covers += (int) $r['guests'];
            }
        }

        $isToday = $key === $today->format('Y-m-d');
        ?>
        <div class="tl-day <?= $isToday ? 'is-today' : '' ?> <?= $window ? '' : 'is-closed' ?>">
            <a class="tl-head" href="index.php?date=<?= e($key) ?>">
                <span class="tl-dow"><?= e(strtoupper($day->format('D'))) ?></span>
                <span class="tl-num"><?= e($day->format('j')) ?></span>
                <span class="tl-covers">
                    <?= $window ? $covers . ' cover' . ($covers === 1 ? '' : 's') : 'Closed' ?>
                </span>
            </a>

            <div class="tl-body">
                <?php // hour gridlines ?>
                <?php for ($m = $DAY_START + 60; $m < $DAY_END; $m += 60): ?>
                    <span class="tl-line" style="top: <?= (($m - $DAY_START) / $SPAN) * 100 ?>%"></span>
                <?php endfor; ?>

                <?php // shade the hours the café is shut ?>
                <?php if ($window): ?>
                    <?php
                    $openM  = max($DAY_START, minutes_of($window[0]));
                    $closeM = min($DAY_END,   minutes_of($window[1]));
                    ?>
                    <?php if ($openM > $DAY_START): ?>
                        <span class="tl-shut" style="top:0; height: <?= (($openM - $DAY_START) / $SPAN) * 100 ?>%"></span>
                    <?php endif; ?>
                    <?php if ($closeM < $DAY_END): ?>
                        <span class="tl-shut" style="top: <?= (($closeM - $DAY_START) / $SPAN) * 100 ?>%; bottom: 0"></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="tl-shut" style="top:0; bottom:0"></span>
                <?php endif; ?>

                <?php
                // The red line marking right now, the same cue Google uses.
                if ($isToday) {
                    $nowM = minutes_of((new DateTimeImmutable('now', $tz))->format('H:i'));
                    if ($nowM >= $DAY_START && $nowM <= $DAY_END):
                ?>
                    <span class="tl-now" style="top: <?= round((($nowM - $DAY_START) / $SPAN) * 100, 3) ?>%"></span>
                <?php endif; } ?>

                <?php foreach ($dayRows as $b): ?>
                    <?php
                    $begin  = minutes_of($b['booking_time']);
                    $top    = (($begin - $DAY_START) / $SPAN) * 100;
                    $height = ($HOLD / $SPAN) * 100;
                    // Keep it on the board even if somebody booked outside hours.
                    $top    = max(0, min(100, $top));
                    $height = min($height, 100 - $top);
                    $dead   = in_array($b['status'], ['cancelled', 'no_show'], true);

                    // Stagger rather than slice. Equal columns made the names
                    // unreadable once three bookings overlapped, so each card
                    // starts at its lane but runs wider, sitting over the one
                    // behind it. Every leading edge stays visible.
                    $laneWidth = 100 / $b['lanes'];
                    $left      = $b['lane'] * $laneWidth;
                    $width     = min(100 - $left, $laneWidth * 1.7);
                    ?>
                    <a class="tl-bk tl-<?= e($b['status']) ?> <?= $dead ? 'is-dead' : '' ?>"
                       href="booking-edit.php?id=<?= (int) $b['id'] ?>"
                       style="top: <?= round($top, 2) ?>%; height: <?= round($height, 2) ?>%;
                              left: <?= round($left, 2) ?>%; width: <?= round($width, 2) ?>%;
                              z-index: <?= 5 + (int) $b['lane'] ?>;"
                       title="<?= e($b['booking_time'] . ' · ' . $b['name'] . ' · ' . $b['guests'] . ' guests · ' . booking_status_label($b['status'])) ?>">
                        <span class="tl-bk-name"><?= e($b['name']) ?></span>
                        <span class="tl-bk-time"><?= e($b['booking_time']) ?> · <?= (int) $b['guests'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endfor; ?>

</div>
</div>

<p class="tl-key">
    <span class="tl-key-item"><i class="sw tl-requested"></i> Requested</span>
    <span class="tl-key-item"><i class="sw tl-confirmed"></i> Confirmed</span>
    <span class="tl-key-item"><i class="sw tl-seated"></i> Seated</span>
    <span class="tl-key-item"><i class="sw tl-completed"></i> Completed</span>
    <span class="tl-key-item"><i class="sw sw-dead"></i> Cancelled / no show</span>
</p>

<?php staff_foot(); ?>
