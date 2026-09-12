<?php
/*
 * Day view — the default screen, and the one that replaces the paper diary.
 */

require_once __DIR__ . '/_layout.php';
staff_require_login();
staff_require_database();

$tz    = new DateTimeZone('Europe/Dublin');
$today = new DateTimeImmutable('today', $tz);

$date = $_GET['date'] ?? $today->format('Y-m-d');
$day  = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
if (!$day) {
    $day  = $today;
    $date = $today->format('Y-m-d');
}

// Status buttons post back here.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
    } else {
        $id     = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id && in_array($status, booking_statuses(), true)) {
            booking_set_status($id, $status, staff_actor());
            staff_flash('Updated.');
        }
    }
    header('Location: index.php?date=' . urlencode($date), true, 303);
    exit;
}

$rows    = bookings_for_date($date);
$summary = bookings_day_summary($date);
$max     = (int) ($booking['max_covers_per_slot'] ?? 0);
$used    = capacity_used($date);
$flash   = staff_flash();

// Group the bookings under their time slot.
$bySlot = [];
foreach ($rows as $row) {
    $bySlot[$row['booking_time']][] = $row;
}
ksort($bySlot);

staff_head($day->format('D j M'), 'day');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<div class="dayhead">
    <a class="nav-arrow" href="?date=<?= e($day->modify('-1 day')->format('Y-m-d')) ?>" aria-label="Previous day">&#8249;</a>
    <div class="dayhead-mid">
        <h1><?= e($day->format('l')) ?><span><?= e($day->format('j F Y')) ?></span></h1>
        <?php if ($date !== $today->format('Y-m-d')): ?>
            <a class="today-link" href="index.php">Back to today</a>
        <?php endif; ?>
    </div>
    <a class="nav-arrow" href="?date=<?= e($day->modify('+1 day')->format('Y-m-d')) ?>" aria-label="Next day">&#8250;</a>
</div>

<div class="totals">
    <div><strong><?= (int) $summary['bookings'] ?></strong><span>bookings</span></div>
    <div><strong><?= (int) $summary['covers'] ?></strong><span>covers</span></div>
    <a class="btn-p btn-add" href="booking-edit.php?date=<?= e($date) ?>">+ Add booking</a>
</div>

<?php if (!$bySlot): ?>
    <div class="card empty">
        <p>Nothing booked for <?= e($day->format('l j F')) ?>.</p>
        <a class="btn-p" href="booking-edit.php?date=<?= e($date) ?>">Add the first one</a>
    </div>
<?php else: ?>
    <?php foreach ($bySlot as $time => $slotRows): ?>
        <section class="slot">
            <h2 class="slot-head">
                <?= e($time) ?>
                <?php if ($max > 0): ?>
                    <span class="slot-cap <?= ($used[$time] ?? 0) >= $max ? 'full' : '' ?>">
                        <?= (int) ($used[$time] ?? 0) ?>/<?= $max ?> covers
                    </span>
                <?php endif; ?>
            </h2>

            <?php foreach ($slotRows as $b): ?>
                <?php staff_booking_card($b); ?>
                <?php if (!in_array($b['status'], ['cancelled', 'completed'], true)): ?>
                    <form class="quick" method="post" action="index.php?date=<?= e($date) ?>">
                        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <?php
                        $next = [
                            'requested' => [['confirmed', 'Confirm'], ['cancelled', 'Cancel']],
                            'confirmed' => [['seated', 'Seated'], ['no_show', 'No show'], ['cancelled', 'Cancel']],
                            'seated'    => [['completed', 'Done']],
                            'no_show'   => [['confirmed', 'Undo']],
                        ][$b['status']] ?? [];
                        foreach ($next as [$value, $label]):
                        ?>
                            <button class="btn-s btn-<?= e($value) ?>" name="status" value="<?= e($value) ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php staff_foot(); ?>
