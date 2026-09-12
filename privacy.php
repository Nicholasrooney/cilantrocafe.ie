<?php
$pageTitle       = 'Privacy notice | Cilantro Café';
$pageDescription = 'How Cilantro Café uses the details you give us when you book a table, how long we keep them, and how to ask for a copy or have them deleted.';
$activePage      = 'privacy';
require __DIR__ . '/includes/header.php';

$contact = $site['email'] ?: ($site['phone'] ?: 'us in the café');
$updated = 'September 2026';
?>

<section class="page-intro">
    <div class="container">
        <h1>Privacy notice</h1>
        <p>What we collect, why, and what you can ask us to do about it. Last updated <?= e($updated) ?>.</p>
    </div>
</section>

<div class="container prose">

    <h2>Who we are</h2>
    <p>
        <?= e($site['name']) ?><?= $site['address'] ? ', ' . e($site['address']) : '' ?><?= !empty($site['eircode']) ? ', ' . e($site['eircode']) : '' ?>.
        We are the data controller for the information described here.
        <?php if ($site['email']): ?>
            You can reach us at <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a>.
        <?php endif; ?>
    </p>

    <h2>When you book a table</h2>
    <p>We ask for your name, phone number and email address, plus the date, time,
       number of guests, any seating preference and anything you tell us in the
       notes box.</p>
    <p>We use it to hold your table, to contact you if something changes, and to
       keep a record of the booking. Our lawful basis is that we need it to
       provide the booking you asked for.</p>
    <p>Staff can add their own notes to your record — for example an allergy you
       mentioned, or that you prefer the booth. You can ask to see these.</p>

    <h2>Marketing emails</h2>
    <p>We only email you about offers and events if you ticked the box asking us
       to. That is separate from booking a table, and your booking is unaffected
       if you leave it unticked. You can tell us to stop at any time and we will.</p>

    <h2>How long we keep it</h2>
    <p>Booking records are kept for <?= (int) ($booking['retention_months'] ?? 24) ?> months after your
       most recent visit. After that your name and phone number are permanently
       removed from our system.</p>
    <p>The booking itself stays on as an anonymous record — just a date and a
       number of covers — because we use it to plan staffing. It can no longer
       be connected to you.</p>
    <p>If you asked for marketing emails, we keep your email address until you
       tell us to stop, because your consent is a separate reason for holding it.</p>

    <h2>Who else sees it</h2>
    <p>Your booking details are visible to café staff through our booking system.
       We do not sell your information and we do not share it for anyone else's
       marketing.</p>
    <p>Our website is hosted by Hostinger, who store the data on our behalf within
       the EU. If we have connected our booking system to Google Calendar, your
       name, party size and contact details are also written to a private calendar
       owned by the café so staff can see the day's bookings.</p>

    <h2>Cookies</h2>
    <p>This site sets one cookie of its own, which keeps your booking form secure
       while you fill it in. It is deleted when you close your browser and it does
       not track you.</p>
    <p>We do not use advertising or analytics cookies. If you choose to load the
       map on our home page, Google may set cookies at that point — which is why
       the map does not load until you ask it to.</p>

    <h2>Your rights</h2>
    <p>Under GDPR you can ask us to:</p>
    <ul>
        <li>give you a copy of everything we hold about you</li>
        <li>correct anything that is wrong</li>
        <li>delete your details</li>
        <li>stop using them for marketing</li>
        <li>restrict or object to how we use them</li>
    </ul>
    <p>Just ask <?= $site['email'] ? '' : '' ?><?php if ($site['email']): ?><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a><?php else: ?>a member of staff<?php endif; ?>
       and we will sort it out. There is no charge, and we will come back to you
       within one month.</p>

    <h2>Complaints</h2>
    <p>If you are unhappy with how we have handled your information, please tell
       us first so we can put it right. You also have the right to complain to the
       Data Protection Commission, the Irish supervisory authority, at
       <a href="https://www.dataprotection.ie" target="_blank" rel="noopener">dataprotection.ie</a>.</p>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
