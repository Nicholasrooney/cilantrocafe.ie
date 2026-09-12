<?php
/*
 * Add or edit one booking. This is where phone and walk-in bookings get in,
 * which is most of them in a real café.
 *
 * Staff can exceed the per-slot limit here, unlike the public form: they can
 * see the room and the computer cannot. Doing so is recorded in the audit
 * trail as a capacity override.
 */

require_once __DIR__ . '/_layout.php';
staff_require_login();
staff_require_database();

$tz      = new DateTimeZone('Europe/Dublin');
$id      = (int) ($_GET['id'] ?? 0);
$existing = $id ? booking_get($id) : null;

if ($id && !$existing) {
    staff_flash('That booking no longer exists.');
    header('Location: index.php', true, 303);
    exit;
}

/*
 * The same customer-data controls as the customers screen, because this is
 * where staff already are when somebody rings up asking what is held about
 * them or to be forgotten. Handled before the booking form so an erase does
 * not fall through into "save changes" on a booking that no longer exists.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '' && $existing) {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: booking-edit.php?id=' . $id, true, 303);
        exit;
    }

    $customerId = (int) $existing['customer_id'];

    if ($_POST['action'] === 'export') {
        $data = customer_export($customerId);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="customer-' . $customerId . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_POST['action'] === 'erase' && ($_POST['confirm'] ?? '') === 'ERASE') {
        $day = $existing['booking_date'];
        customer_delete($customerId);
        // This booking went with them, so there is nothing to come back to.
        staff_flash('That customer and all of their bookings have been erased.');
        header('Location: index.php?date=' . urlencode($day), true, 303);
        exit;
    }

    if ($_POST['action'] === 'erase') {
        staff_flash('Type ERASE exactly to confirm. Nothing was deleted.');
        header('Location: booking-edit.php?id=' . $id, true, 303);
        exit;
    }
}

$max    = (int) ($booking['max_per_slot'] ?? 0);
$errors = [];
$warn   = '';

$form = [
    'name'    => $existing['name']         ?? '',
    'phone'   => $existing['phone']        ?? '',
    'email'   => $existing['email']        ?? '',
    'date'    => $existing['booking_date'] ?? ($_GET['date'] ?? (new DateTimeImmutable('today', $tz))->format('Y-m-d')),
    'time'    => $existing['booking_time'] ?? ($booking['times'][0] ?? '12:00'),
    'guests'  => (string) ($existing['guests'] ?? 2),
    'seating' => $existing['seating']      ?? ($booking['seating'][0] ?? ''),
    'notes'   => $existing['notes']        ?? '',
    'source'  => $existing['source']       ?? 'phone',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === '') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        $errors['form'] = 'That form expired. Check the details and send it again.';
    }

    foreach ($form as $field => $_) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($form['name'] === '')  { $errors['name']  = 'Enter a name.'; }
    if (customer_phone_key($form['phone']) === '') { $errors['phone'] = 'Enter a phone number.'; }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email does not look right.';
    }
    if (!DateTimeImmutable::createFromFormat('!Y-m-d', $form['date'], $tz)) {
        $errors['date'] = 'Choose a date.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $form['time'])) {
        $errors['time'] = 'Choose a time.';
    }
    $guests = filter_var($form['guests'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
    if ($guests === false) {
        $errors['guests'] = 'How many people?';
    }

    $override = !empty($_POST['override']);

    if (!$errors) {
        try {
            if ($max > 0 && !$override) {
                capacity_assert_room($form['date'], $form['time'], (int) $guests, $max, $id ?: null);
            }

            if ($existing) {
                booking_update($id, $form + ['guests' => $guests], staff_actor());
                if ($override) {
                    booking_audit($id, 'capacity_override', $existing['status'], $existing['status'], staff_actor());
                }
                staff_flash('Booking updated.');
            } else {
                $newId = booking_create(
                    $form + ['guests' => $guests, 'status' => 'confirmed'],
                    false,                      // capacity already checked above
                    staff_actor()
                );
                if ($override) {
                    booking_audit($newId, 'capacity_override', null, 'confirmed', staff_actor());
                }
                staff_flash('Booking added.');
            }

            header('Location: index.php?date=' . urlencode($form['date']), true, 303);
            exit;

        } catch (CapacityExceeded $e) {
            // Not an error — an ask. Staff decide.
            $warn = $e->getMessage() . ' You can add it anyway.';
        }
    }
}

$history = $existing ? booking_history($id) : [];
$flash   = staff_flash();
staff_head($existing ? 'Edit booking' : 'Add booking');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<form class="card form" method="post" action="booking-edit.php<?= $id ? '?id=' . $id : '' ?>">
    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">

    <h1><?= $existing ? 'Edit booking' : 'Add a booking' ?></h1>

    <?php if (isset($errors['form'])): ?>
        <p class="alert" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <?php if ($warn): ?>
        <div class="warn" role="alert">
            <p><?= e($warn) ?></p>
            <label class="check">
                <input type="checkbox" name="override" value="1" checked>
                Add it anyway — I can fit them in
            </label>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= e($form['name']) ?>" required autocomplete="off">
            <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($form['phone']) ?>" required autocomplete="off">
            <?php if (isset($errors['phone'])): ?><p class="err"><?= e($errors['phone']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label for="email">Email <span class="opt">(optional for phone bookings)</span></label>
        <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" autocomplete="off">
        <?php if (isset($errors['email'])): ?><p class="err"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="row">
        <div class="field">
            <label for="date">Date</label>
            <input id="date" name="date" type="date" value="<?= e($form['date']) ?>" required>
            <?php if (isset($errors['date'])): ?><p class="err"><?= e($errors['date']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="time">Time</label>
            <select id="time" name="time" required>
                <?php foreach ($booking['times'] as $t): ?>
                    <option value="<?= e($t) ?>" <?= $t === $form['time'] ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
                <?php if (!in_array($form['time'], $booking['times'], true)): ?>
                    <option value="<?= e($form['time']) ?>" selected><?= e($form['time']) ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="field field-small">
            <label for="guests">Guests</label>
            <input id="guests" name="guests" type="number" min="1" max="60" inputmode="numeric"
                   value="<?= e($form['guests']) ?>" required>
            <?php if (isset($errors['guests'])): ?><p class="err"><?= e($errors['guests']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="field">
            <label for="seating">Seating</label>
            <select id="seating" name="seating">
                <?php foreach ($booking['seating'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $s === $form['seating'] ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="source">Came in by</label>
            <select id="source" name="source">
                <?php foreach (booking_sources() as $s): ?>
                    <option value="<?= e($s) ?>" <?= $s === $form['source'] ? 'selected' : '' ?>>
                        <?= e(ucfirst(str_replace('_', ' ', $s))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3"><?= e($form['notes']) ?></textarea>
    </div>

    <div class="form-do">
        <button class="btn-p" type="submit"><?= $existing ? 'Save changes' : 'Add booking' ?></button>
        <a class="btn-s" href="index.php?date=<?= e($form['date']) ?>">Cancel</a>
    </div>
</form>

<?php if ($existing): ?>
    <div class="card">
        <h2>This customer</h2>
        <?php $stats = customer_stats((int) $existing['customer_id']); ?>
        <p>
            <?= (int) $stats['total'] ?> booking<?= $stats['total'] === 1 ? '' : 's' ?>
            <?php if ($stats['no_shows'] > 0): ?>
                · <strong class="bad"><?= (int) $stats['no_shows'] ?> no-show<?= $stats['no_shows'] === 1 ? '' : 's' ?></strong>
            <?php endif; ?>
        </p>
        <?php if (!empty($existing['customer_notes'])): ?>
            <p class="bk-note bk-note-staff">★ <?= e($existing['customer_notes']) ?></p>
        <?php endif; ?>
        <a class="btn-s" href="customers.php?id=<?= (int) $existing['customer_id'] ?>">Open customer</a>
    </div>

    <div class="card danger">
        <h2>Their data</h2>
        <p class="sub">If <?= e($existing['name']) ?> asks what you hold about them, or asks
           you to delete it, do it here.</p>

        <form method="post" action="booking-edit.php?id=<?= $id ?>" class="inline">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="action" value="export">
            <button class="btn-s" type="submit">Download everything we hold</button>
        </form>

        <form method="post" action="booking-edit.php?id=<?= $id ?>" class="inline erase">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="action" value="erase">
            <label for="confirm">Type ERASE to confirm permanent deletion</label>
            <p class="sub">This removes the customer <strong>and every booking they have
               made</strong>, including this one. It cannot be undone.</p>
            <input id="confirm" name="confirm" autocomplete="off" placeholder="ERASE">
            <button class="btn-danger" type="submit">Erase this customer</button>
        </form>
    </div>

    <div class="card">
        <h2>History</h2>
        <ul class="audit">
            <?php foreach ($history as $h): ?>
                <li>
                    <span class="audit-when"><?= e($h['at']) ?></span>
                    <span class="audit-what"><?= e($h['action']) ?><?php
                        if ($h['old_status'] && $h['new_status'] && $h['old_status'] !== $h['new_status']) {
                            echo ': ' . e(booking_status_label($h['old_status'])) . ' → ' . e(booking_status_label($h['new_status']));
                        }
                    ?></span>
                    <span class="audit-who"><?= e($h['actor']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php staff_foot(); ?>
