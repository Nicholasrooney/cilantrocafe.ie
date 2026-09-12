<?php
/*
 * Week view — seven days at a glance, for spotting the quiet nights and the
 * ones that need another pair of hands.
 */

require_once __DIR__ . '/_layout.php';
staff_require_login();
staff_require_database();

$tz    = new DateTimeZone('Europe/Dublin');
$today = new DateTimeImmutable('today', $tz);

$start = $_GET['from'] ?? $today->format('Y-m-d');
$from  = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz) ?: $today;
// Always start the week on Monday.
$from  = $from->modify('monday this week');
$to    = $from->modify('+6 days');

$rows = bookings_for_range($from->format('Y-m-d'), $to->format('Y-m-d'));
$live = booking_live_statuses();

$byDay = [];
foreach ($rows as $row) {
    $byDay[$row['booking_date']][] = $row;
}

staff_head('Week of ' . $from->format('j M'), 'week');
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

<div class="week">
    <?php for ($i = 0; $i < 7; $i++): ?>
        <?php
        $day     = $from->modify("+$i days");
        $key     = $day->format('Y-m-d');
        $dayRows = $byDay[$key] ?? [];
        $covers  = 0;
        foreach ($dayRows as $r) {
            if (in_array($r['status'], $live, true)) {
                $covers += (int) $r['guests'];
            }
        }
        $isToday = $key === $today->format('Y-m-d');
        ?>
        <section class="week-day <?= $isToday ? 'is-today' : '' ?>">
            <a class="week-day-head" href="index.php?date=<?= e($key) ?>">
                <span class="week-dow"><?= e($day->format('D')) ?></span>
                <span class="week-date"><?= e($day->format('j M')) ?></span>
                <span class="week-covers"><?= $covers ?> cover<?= $covers === 1 ? '' : 's' ?></span>
            </a>

            <?php if (!$dayRows): ?>
                <p class="week-empty">—</p>
            <?php else: ?>
                <ul class="week-list">
                    <?php foreach ($dayRows as $r): ?>
                        <li class="<?= in_array($r['status'], ['cancelled', 'no_show'], true) ? 'is-dead' : '' ?>">
                            <a href="booking-edit.php?id=<?= (int) $r['id'] ?>">
                                <span class="week-time"><?= e($r['booking_time']) ?></span>
                                <span class="week-name"><?= e($r['name']) ?></span>
                                <span class="week-n"><?= (int) $r['guests'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endfor; ?>
</div>

<?php staff_foot(); ?>
