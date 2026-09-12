<?php
/*
 * Daily retention job.
 *
 * Anonymises customers whose most recent booking is older than the retention
 * period. Name and phone always go; the email survives only where marketing
 * consent gives an independent reason to keep it.
 *
 * Set up in hPanel > Advanced > Cron Jobs, once a day:
 *     php /home/USER/domains/cilantrocafe.ie/public_html/cron/retention.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customers.php';

// Only ever run from the command line — this is not a web page.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$months = (int) ($booking['retention_months'] ?? 24);
$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    $ids = customers_past_retention($months);
} catch (Throwable $e) {
    fwrite(STDERR, 'Retention job could not run: ' . $e->getMessage() . "\n");
    exit(1);
}

echo date('Y-m-d H:i') . "  retention: $months months, " . count($ids) . " customer(s) past it\n";

$done = 0;
foreach ($ids as $id) {
    if (!$dryRun) {
        customer_anonymise((int) $id);
    }
    $done++;
}

echo $dryRun
    ? "Dry run — $done would have been anonymised.\n"
    : "Anonymised $done.\n";
