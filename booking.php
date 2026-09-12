<?php
require __DIR__ . '/includes/booking-handler.php';

$pageTitle       = 'Book a table | Cilantro Café';
$pageDescription = 'Book a table at Cilantro Café, Dublin. Choose a date, time and number of guests.';
$activePage      = 'booking';
require __DIR__ . '/includes/header.php';

function field_error(array $errors, string $field): string {
    return isset($errors[$field])
        ? '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>'
        : '';
}
function error_attrs(array $errors, string $field): string {
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}
?>

<div class="container booking-layout">
    <div class="booking-side">
        <h1>Book a table</h1>
        <p>Send us your details and we'll hold a table for you. We'll contact you if we can't fit you in at that time.</p>
        <p>More than <?= (int) $booking['max_guests'] ?> people?
            <?php if ($site['phone']): ?>
                Call us on <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $site['phone'])) ?>"><?= e($site['phone']) ?></a>.
            <?php elseif ($site['email']): ?>
                Email <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a>.
            <?php else: ?>
                Message us on <a href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a>.
            <?php endif; ?>
        </p>
        <img class="booking-photo" src="images/french-toast.jpg" alt="Maple syrup being poured over French toast with strawberries" loading="lazy">
    </div>

    <div class="booking-main">
        <?php if ($confirmed && isset($_GET['sent'])): ?>
            <div class="confirmation" role="status">
                <h2>Booking request sent</h2>
                <p>Thanks, <?= e($confirmed['name']) ?>. We've got your request for
                    <strong><?= (int) $confirmed['guests'] ?> <?= $confirmed['guests'] === 1 ? 'guest' : 'guests' ?></strong>
                    on <strong><?= e($confirmed['date']) ?></strong> at <strong><?= e($confirmed['time']) ?></strong>.</p>
                <p>We'll be in touch if anything needs to change.</p>
                <a class="btn btn-green" href="menu.php">See the menu</a>
            </div>
        <?php else: ?>

            <?php if (isset($errors['form'])): ?>
                <div class="form-alert" role="alert"><?= e($errors['form']) ?></div>
            <?php elseif ($errors): ?>
                <div class="form-alert" role="alert">Some details need fixing. Check the highlighted fields below.</div>
            <?php endif; ?>

            <form class="booking-form" method="post" action="booking.php" novalidate>
                <input type="hidden" name="token" value="<?= e($_SESSION['booking_token']) ?>">
                <div class="hp" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <fieldset>
                    <legend>Your table</legend>
                    <div class="field-row">
                        <div class="field">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" required
                                   min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($lastDay->format('Y-m-d')) ?>"
                                   value="<?= e($old['date']) ?>"<?= error_attrs($errors, 'date') ?>>
                            <?= field_error($errors, 'date') ?>
                        </div>
                        <div class="field">
                            <label for="time">Time</label>
                            <select id="time" name="time" required<?= error_attrs($errors, 'time') ?>>
                                <option value="">Choose a time</option>
                                <?php foreach ($booking['times'] as $t): ?>
                                    <option value="<?= e($t) ?>" <?= $old['time'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'time') ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="guests">Number of guests</label>
                            <select id="guests" name="guests" required<?= error_attrs($errors, 'guests') ?>>
                                <?php for ($i = 1; $i <= $booking['max_guests']; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string) $i === $old['guests'] ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <?= field_error($errors, 'guests') ?>
                        </div>
                        <div class="field">
                            <label for="seating">Seating</label>
                            <select id="seating" name="seating">
                                <?php foreach ($booking['seating'] as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $old['seating'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Your details</legend>
                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required autocomplete="name" maxlength="80"
                               value="<?= e($old['name']) ?>"<?= error_attrs($errors, 'name') ?>>
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" required autocomplete="tel" maxlength="20"
                                   value="<?= e($old['phone']) ?>"<?= error_attrs($errors, 'phone') ?>>
                            <?= field_error($errors, 'phone') ?>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required autocomplete="email" maxlength="120"
                                   value="<?= e($old['email']) ?>"<?= error_attrs($errors, 'email') ?>>
                            <?= field_error($errors, 'email') ?>
                        </div>
                    </div>
                    <div class="field">
                        <label for="notes">Anything we should know? <span class="optional">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="4" maxlength="500"
                                  placeholder="High chair, allergies, a birthday"<?= error_attrs($errors, 'notes') ?>><?= e($old['notes']) ?></textarea>
                        <?= field_error($errors, 'notes') ?>
                    </div>
                </fieldset>

                <p class="privacy-note">We only use your details to manage this booking.</p>
                <button class="btn btn-green btn-block" type="submit">Request booking</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
