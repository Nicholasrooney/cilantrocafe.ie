<?php
/*
 * Customer lookup — who is this, have they been before, and is there anything
 * we should know before they sit down.
 *
 * Also carries the GDPR controls: export what we hold, and erase it.
 */

require_once __DIR__ . '/_layout.php';
staff_require_login();
staff_require_database();

$id   = (int) ($_GET['id'] ?? 0);
$term = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: customers.php', true, 303);
        exit;
    }

    $target = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'notes') {
        customer_set_notes($target, substr((string) ($_POST['notes'] ?? ''), 0, 2000));
        staff_flash('Notes saved.');
        header('Location: customers.php?id=' . $target, true, 303);
        exit;
    }

    if ($action === 'consent') {
        customer_set_consent($target, !empty($_POST['consent']));
        staff_flash('Marketing preference updated.');
        header('Location: customers.php?id=' . $target, true, 303);
        exit;
    }

    if ($action === 'export') {
        $data = customer_export($target);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="customer-' . $target . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'erase' && ($_POST['confirm'] ?? '') === 'ERASE') {
        customer_delete($target);
        staff_flash('That customer and their bookings have been erased.');
        header('Location: customers.php', true, 303);
        exit;
    }
}

$flash = staff_flash();
staff_head('Customers', 'customers');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<form class="searchbar" method="get" action="customers.php">
    <label class="sr-only" for="q">Search customers</label>
    <input id="q" name="q" value="<?= e($term) ?>" placeholder="Name, phone or email" autocomplete="off">
    <button class="btn-p" type="submit">Search</button>
</form>

<?php if ($id): ?>
    <?php
    $customer = customer_get($id);
    if (!$customer) {
        echo '<div class="card"><p>That customer no longer exists.</p></div>';
        staff_foot();
        exit;
    }
    $stats   = customer_stats($id);
    $history = customer_history($id);
    ?>

    <div class="card">
        <h1><?= e($customer['name']) ?></h1>
        <p class="sub">
            <?php if ($customer['phone']): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $customer['phone'])) ?>"><?= e($customer['phone']) ?></a>
            <?php endif; ?>
            <?php if ($customer['email']): ?> · <?= e($customer['email']) ?><?php endif; ?>
        </p>

        <p>
            <strong><?= (int) $stats['total'] ?></strong> booking<?= $stats['total'] === 1 ? '' : 's' ?>
            <?php if ($stats['no_shows'] > 0): ?>
                · <strong class="bad"><?= (int) $stats['no_shows'] ?> no-show<?= $stats['no_shows'] === 1 ? '' : 's' ?></strong>
            <?php endif; ?>
            <?php if ($stats['last_visit']): ?>
                · last booked <?= e($stats['last_visit']) ?>
            <?php endif; ?>
        </p>

        <?php if ($customer['anonymised_at']): ?>
            <p class="warn">This record was anonymised on <?= e($customer['anonymised_at']) ?> under the retention policy.</p>
        <?php endif; ?>
    </div>

    <form class="card" method="post" action="customers.php">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="notes">
        <h2>Notes</h2>
        <p class="sub">Allergies, the table they like, anything worth knowing. Staff only — the customer never sees this.</p>
        <textarea name="notes" rows="3"><?= e($customer['notes'] ?? '') ?></textarea>
        <button class="btn-p" type="submit">Save notes</button>
    </form>

    <div class="card">
        <h2>Bookings</h2>
        <?php if (!$history): ?>
            <p class="sub">None yet.</p>
        <?php else: ?>
            <ul class="week-list">
                <?php foreach ($history as $b): ?>
                    <li class="<?= in_array($b['status'], ['cancelled', 'no_show'], true) ? 'is-dead' : '' ?>">
                        <a href="booking-edit.php?id=<?= (int) $b['id'] ?>">
                            <span class="week-time"><?= e($b['booking_date']) ?> <?= e($b['booking_time']) ?></span>
                            <span class="week-name"><?= e(booking_status_label($b['status'])) ?></span>
                            <span class="week-n"><?= (int) $b['guests'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <form class="card" method="post" action="customers.php">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="consent">
        <h2>Marketing</h2>
        <label class="check">
            <input type="checkbox" name="consent" value="1" <?= !empty($customer['marketing_consent']) ? 'checked' : '' ?>>
            They agreed to hear about offers and events
        </label>
        <?php if ($customer['marketing_consent_at']): ?>
            <p class="sub">Consent given <?= e($customer['marketing_consent_at']) ?>.</p>
        <?php endif; ?>
        <button class="btn-s" type="submit">Save</button>
    </form>

    <div class="card danger">
        <h2>Their data</h2>
        <p class="sub">If this person asks what you hold, or asks you to delete it, do it here.</p>

        <form method="post" action="customers.php" class="inline">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="export">
            <button class="btn-s" type="submit">Download everything we hold</button>
        </form>

        <form method="post" action="customers.php" class="inline erase">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="erase">
            <label for="confirm">Type ERASE to confirm permanent deletion</label>
            <input id="confirm" name="confirm" autocomplete="off" placeholder="ERASE">
            <button class="btn-danger" type="submit">Erase this customer</button>
        </form>
    </div>

<?php elseif ($term !== ''): ?>
    <?php $results = customer_search($term); ?>
    <div class="card">
        <h1><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></h1>
        <?php if (!$results): ?>
            <p class="sub">Nobody matching “<?= e($term) ?>”.</p>
        <?php else: ?>
            <ul class="week-list">
                <?php foreach ($results as $c): ?>
                    <li>
                        <a href="customers.php?id=<?= (int) $c['id'] ?>">
                            <span class="week-name"><?= e($c['name']) ?></span>
                            <span class="week-time"><?= e($c['phone']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card empty">
        <p>Search for someone by name, phone number or email.</p>
        <p class="sub">Phone numbers match however they were typed — 086…, +353 86… and 00353 86… all find the same person.</p>
    </div>
<?php endif; ?>

<?php staff_foot(); ?>
