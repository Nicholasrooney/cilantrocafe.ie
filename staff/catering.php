<?php
/*
 * Catering enquiries — every lead from the catering page, newest first.
 *
 * Statuses follow a sale: new → quoted → booked, or lost. The emailed alert
 * gets things moving; this screen is where they are kept track of, so nothing
 * falls through the cracks of an inbox.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/catering.php';
staff_require_login();
staff_require_database();

$filter = (string) ($_GET['status'] ?? 'new');
if ($filter !== 'all' && !isset(catering_statuses()[$filter])) {
    $filter = 'new';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'catering.php?status=' . urlencode($filter);

    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: ' . $back, true, 303);
        exit;
    }

    $id     = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($id && $action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (isset(catering_statuses()[$status])) {
            catering_set_status($id, $status);
            staff_flash('Marked as ' . strtolower(catering_statuses()[$status]) . '.');
        }
    } elseif ($id && $action === 'notes') {
        catering_set_staff_notes($id, mb_substr(trim((string) ($_POST['staff_notes'] ?? '')), 0, 2000));
        staff_flash('Notes saved.');
    } elseif ($id && $action === 'delete') {
        catering_delete($id);
        staff_flash('Enquiry deleted.');
    }

    header('Location: ' . $back . ($action === 'notes' && $id ? '#enquiry-' . $id : ''), true, 303);
    exit;
}

$counts = catering_counts();
$rows   = catering_list($filter === 'all' ? null : $filter);
$flash  = staff_flash();

staff_head('Catering', 'catering');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<h1>Catering enquiries</h1>

<nav class="tabs" aria-label="Filter by status">
    <?php foreach (catering_statuses() as $key => $label): ?>
        <a href="?status=<?= e($key) ?>" class="<?= $filter === $key ? 'on' : '' ?>">
            <?= e($label) ?> <span class="count"><?= (int) $counts[$key] ?></span>
        </a>
    <?php endforeach; ?>
    <a href="?status=all" class="<?= $filter === 'all' ? 'on' : '' ?>">All <span class="count"><?= array_sum($counts) ?></span></a>
</nav>

<?php if (!$rows): ?>
    <div class="card empty">
        <p><?= $filter === 'new' ? 'No new catering enquiries.' : 'Nothing here.' ?></p>
        <p class="sub">Enquiries from the catering page land here, and you are emailed when one arrives.</p>
    </div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
    <?php
    $received = new DateTimeImmutable($r['created_at'], new DateTimeZone('Europe/Dublin'));
    $who      = $r['name'] !== '' ? $r['name'] : ($r['phone'] !== '' ? $r['phone'] : $r['email']);
    ?>
    <article class="card enquiry" id="enquiry-<?= (int) $r['id'] ?>">
        <header class="enquiry-head">
            <div>
                <h2><?= e($who) ?></h2>
                <p class="sub">
                    <?= e($received->format('D j M, H:i')) ?>
                    · <?= $r['source'] === 'price_card' ? 'Quick price request' : 'Full enquiry' ?>
                </p>
            </div>
            <span class="tag tag-<?= e($r['status']) ?>"><?= e(catering_statuses()[$r['status']] ?? $r['status']) ?></span>
        </header>

        <dl class="enquiry-facts">
            <div><dt>Occasion</dt><dd><?= e(catering_occasion_label($r['occasion'])) ?></dd></div>
            <div><dt>Guests</dt><dd><?= (int) $r['guests'] ?></dd></div>
            <?php if (!empty($r['location'])): ?>
                <div><dt>Location</dt><dd><?= e($r['location']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Package</dt><dd><?= e(catering_package_name($r['package'])) ?></dd></div>
            <?php if ($r['price_per_person'] !== null): ?>
                <div><dt>Shown</dt><dd>from <?= e(catering_money((float) $r['price_per_person'])) ?> pp<?php
                    if ($r['estimate_total'] !== null) { echo ' · around ' . e(catering_money((float) $r['estimate_total'], false)); }
                ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($r['event_date'])): ?>
                <div><dt>Date</dt><dd><?= e((new DateTimeImmutable($r['event_date']))->format('D j M Y')) ?></dd></div>
            <?php endif; ?>
            <?php if ($r['fulfilment'] !== ''): ?>
                <div><dt>Getting it there</dt><dd><?= $r['fulfilment'] === 'delivery' ? 'Delivery' : 'Pickup' ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($r['delivery_address'])): ?>
                <div class="wide"><dt>Deliver to</dt><dd><?= e($r['delivery_address']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($r['notes'])): ?>
                <div class="wide"><dt>Their notes</dt><dd><?= nl2br(e($r['notes'])) ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="enquiry-contact">
            <?php if ($r['phone'] !== ''): ?>
                <a class="btn-s btn-call" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['phone'])) ?>">Call <?= e($r['phone']) ?></a>
            <?php endif; ?>
            <?php if ($r['email'] !== ''): ?>
                <a class="btn-s" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Your catering enquiry — ' . $site['name']) ?>">Email <?= e($r['email']) ?></a>
            <?php endif; ?>
        </div>

        <form method="post" action="catering.php?status=<?= e($filter) ?>" class="status-do">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="status">
            <?php foreach (catering_statuses() as $key => $label): ?>
                <?php if ($key !== $r['status']): ?>
                    <button class="btn-s" name="status" value="<?= e($key) ?>">
                        <?= $key === 'new' ? 'Back to new' : 'Mark ' . e(strtolower($label)) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </form>

        <form method="post" action="catering.php?status=<?= e($filter) ?>" class="enquiry-notes">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="notes">
            <label for="notes-<?= (int) $r['id'] ?>">Staff notes</label>
            <textarea id="notes-<?= (int) $r['id'] ?>" name="staff_notes" rows="2"
                      placeholder="Quote sent, deposit, follow-up…"><?= e($r['staff_notes'] ?? '') ?></textarea>
            <div class="enquiry-notes-do">
                <button class="btn-s" type="submit">Save notes</button>
            </div>
        </form>

        <form method="post" action="catering.php?status=<?= e($filter) ?>" class="enquiry-delete">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button class="link-danger" type="submit" data-confirm="Delete this enquiry for good?">Delete enquiry</button>
        </form>
    </article>
<?php endforeach; ?>

<?php staff_foot(); ?>
