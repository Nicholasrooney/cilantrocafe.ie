<?php
/*
 * One-off tidy-up: rewrite old seating wording on existing bookings.
 *
 * Bookings taken before the options were renamed still say "Booth inside",
 * "Stools inside" or "Table outside". The site already displays those
 * correctly through booking_seating_label(), so this is housekeeping rather
 * than a fix — it just makes what is stored match what is shown.
 *
 *     php db/relabel-seating.php            # show what would change
 *     php db/relabel-seating.php --commit   # actually change it
 *
 * Safe to run twice: rows that already read correctly are left alone.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$commit = in_array('--commit', $argv ?? [], true);

try {
    $rows = db()->query('SELECT id, seating FROM bookings ORDER BY id')->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not read bookings: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $commit ? "Relabelling...\n\n" : "Dry run — nothing will change. Add --commit to do it for real.\n\n";

$update  = db()->prepare('UPDATE bookings SET seating = ? WHERE id = ?');
$changed = 0;
$same    = 0;

foreach ($rows as $row) {
    $now  = (string) $row['seating'];
    $want = booking_seating_label($now);

    // An empty seating stays empty: "No preference" is how it reads, not a
    // value worth writing into every old row.
    if ($now === '' || $now === $want) {
        $same++;
        continue;
    }

    printf("  #%-4d %-16s -> %s\n", $row['id'], $now, $want);
    if ($commit) {
        $update->execute([$want, $row['id']]);
    }
    $changed++;
}

echo "\n" . ($commit ? "Relabelled $changed" : "$changed to relabel")
   . ", $same already fine.\n";

if (!$commit && $changed > 0) {
    echo "\nRun again with --commit to write them.\n";
}
