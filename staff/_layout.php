<?php
/*
 * Shared chrome for the staff screens.
 *
 * Deliberately separate from the public header: this is a tool used one-handed
 * behind a counter, not a page to be admired. Big tap targets, no hero images,
 * nothing that needs a second hand.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bookings.php';
require_once __DIR__ . '/../includes/customers.php';
require_once __DIR__ . '/../includes/capacity.php';

function staff_head(string $title, string $active = '', bool $wide = false): void
{
    ?><!DOCTYPE html>
<html lang="en-IE">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Cilantro staff</title>
<link rel="stylesheet" href="<?= e(asset('staff/staff.css')) ?>">
</head>
<body>
<header class="bar">
    <a class="bar-brand" href="index.php">Cilantro <span>staff</span></a>
    <nav class="bar-nav">
        <a href="index.php"     class="<?= $active === 'day' ? 'on' : '' ?>">Today</a>
        <a href="week.php"      class="<?= $active === 'week' ? 'on' : '' ?>">Week</a>
        <a href="catering.php"  class="<?= $active === 'catering' ? 'on' : '' ?>">Catering</a>
        <a href="customers.php" class="<?= $active === 'customers' ? 'on' : '' ?>">Customers</a>
        <a href="logout.php" class="bar-out">Log out</a>
    </nav>
</header>
<main class="wrap<?= $wide ? ' wrap-wide' : '' ?>">
<?php
}

function staff_foot(): void
{
    ?>
</main>
<script src="<?= e(asset('staff/staff.js')) ?>" defer></script>
</body>
</html><?php
}

/**
 * Shows a message passed through a redirect.
 */
function staff_flash(?string $set = null): ?string
{
    staff_session_start();
    if ($set !== null) {
        $_SESSION['staff_flash'] = $set;
        return null;
    }
    $msg = $_SESSION['staff_flash'] ?? null;
    unset($_SESSION['staff_flash']);
    return $msg;
}

/**
 * The "database isn't set up yet" screen, so a fresh install explains itself
 * instead of throwing a stack trace at whoever opened it.
 */
function staff_require_database(): void
{
    if (db_ready()) {
        return;
    }

    staff_head('Setup needed');
    ?>
    <div class="card notice">
        <h1>The database is not set up yet</h1>
        <p>The staff calendar needs its database before it can do anything.</p>
        <ol>
            <li>In hPanel, create a MySQL database and user.</li>
            <li>Import <code>db/schema.sql</code> through phpMyAdmin.</li>
            <li>Put the credentials in <code>includes/secrets/db.php</code>.</li>
        </ol>
        <p>The full walkthrough is in <code>docs/database-setup.md</code>.</p>
    </div>
    <?php
    staff_foot();
    exit;
}

/**
 * A booking card, used by both the day and week views.
 */
function staff_booking_card(array $b): void
{
    $dead = in_array($b['status'], ['cancelled', 'no_show'], true);
    ?>
    <article class="bk <?= $dead ? 'bk-dead' : '' ?> bk-<?= e($b['status']) ?>">
        <div class="bk-when">
            <span class="bk-time"><?= e($b['booking_time']) ?></span>
            <span class="bk-guests"><?= (int) $b['guests'] ?></span>
        </div>

        <div class="bk-who">
            <h3><?= e($b['name']) ?></h3>
            <p class="bk-meta">
                <?= e($b['seating'] ?: 'No preference') ?>
                <?php if ($b['source'] !== 'website'): ?>
                    · <?= e(str_replace('_', ' ', $b['source'])) ?>
                <?php endif; ?>
                · <span class="tag tag-<?= e($b['status']) ?>"><?= e(booking_status_label($b['status'])) ?></span>
            </p>
            <?php if (!empty($b['notes'])): ?>
                <p class="bk-note"><?= e($b['notes']) ?></p>
            <?php endif; ?>
            <?php if (!empty($b['customer_notes'])): ?>
                <p class="bk-note bk-note-staff">★ <?= e($b['customer_notes']) ?></p>
            <?php endif; ?>
        </div>

        <div class="bk-do">
            <?php if (!empty($b['phone'])): ?>
                <a class="btn-s btn-call" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $b['phone'])) ?>">Call</a>
            <?php endif; ?>
            <a class="btn-s" href="booking-edit.php?id=<?= (int) $b['id'] ?>">Open</a>
        </div>
    </article>
    <?php
}
