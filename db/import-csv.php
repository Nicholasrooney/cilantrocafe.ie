<?php
/*
 * One-off import of the old data/bookings.csv into the database.
 *
 *     php db/import-csv.php            # show what would happen
 *     php db/import-csv.php --commit   # actually write
 *
 * Safe to run twice: a booking matching an existing one on phone, date and
 * time is skipped rather than duplicated.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bookings.php';
require_once __DIR__ . '/../includes/customers.php';

$commit = in_array('--commit', $argv ?? [], true);
$file   = $booking['csv_file'];

if (!is_readable($file)) {
    exit("No CSV at $file — nothing to import.\n");
}

$handle = fopen($file, 'rb');
$header = fgetcsv($handle);       // Received, Name, Phone, Email, Date, Time, Guests, Seating, Notes

$imported = $skipped = $failed = 0;

echo $commit ? "Importing...\n\n" : "Dry run — nothing will be written. Add --commit to do it for real.\n\n";

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 7) {
        continue;
    }

    [, $name, $phone, $email, $date, $time, $guests] = $row;
    $seating = $row[7] ?? '';
    $notes   = $row[8] ?? '';

    // fputcsv defended against spreadsheet formula injection with a leading
    // apostrophe; strip it back off.
    $phone = ltrim($phone, "'");

    $label = sprintf('%-22s %s %s  %s guests', $name, $date, $time, $guests);

    try {
        $exists = db()->prepare(
            'SELECT b.id FROM bookings b
               JOIN customers c ON c.id = b.customer_id
              WHERE c.phone_key = ? AND b.booking_date = ? AND b.booking_time = ?'
        );
        $exists->execute([customer_phone_key($phone), $date, $time]);

        if ($exists->fetchColumn()) {
            echo "  skip    $label  (already imported)\n";
            $skipped++;
            continue;
        }

        if ($commit) {
            booking_create([
                'name' => $name, 'phone' => $phone, 'email' => $email,
                'date' => $date, 'time' => $time, 'guests' => (int) $guests,
                'seating' => $seating, 'notes' => $notes,
                'source' => 'website', 'status' => 'confirmed',
            ], false, 'import');
        }

        echo "  import  $label\n";
        $imported++;

    } catch (Throwable $e) {
        echo "  FAIL    $label  — " . $e->getMessage() . "\n";
        $failed++;
    }
}

fclose($handle);

echo "\n$imported to import, $skipped already there, $failed failed.\n";

if (!$commit && $imported > 0) {
    echo "\nRun again with --commit to write them.\n";
} elseif ($commit && $failed === 0) {
    echo "\nDone. The CSV can be archived — the site no longer writes to it.\n";
}
