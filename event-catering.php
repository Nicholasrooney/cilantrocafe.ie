<?php
require __DIR__ . '/includes/catering-handler.php';

$pageTitle       = 'Event Catering Dublin | Mexican Catering in Blackrock | Cilantro Café';
$pageDescription = 'Mexican event catering from Cilantro Café in Blackrock, Dublin. Taco bars, torta platters, brunch spreads and more for 20 to 200 guests. Get a quote in seconds — pickup or delivery.';
$pageKeywords    = 'event catering Dublin, Mexican catering Dublin, catering Blackrock, taco bar catering, office lunch catering Dublin, party catering south Dublin, wedding catering Dublin';
$activePage      = 'catering';
$pageImage       = 'images/tacos-sharing.jpg';

require_once __DIR__ . '/includes/seo.php';

$cat_faq = [
    'How many people do you cater for?' =>
        'We cater for groups of ' . (int) $catering['min_guests'] . ' to ' . (int) $catering['max_guests'] . ' guests. For a smaller group, book a table at the café instead. For more than ' . (int) $catering['max_guests'] . ', put your numbers in the enquiry form and we will talk it through.',
    'Do you deliver, or do I collect?' =>
        'Either. You can pick your order up from the café at Newpark Centre, Blackrock, or we can deliver. Tell us where when you enquire and we will confirm delivery when we send your price.',
    'How is the price worked out?' =>
        'It depends on the dishes you choose, how many guests you have and any extras. Tell us about your event and we will send you a quote before anything is booked.',
    'Can you cater for allergies and dietary needs?' =>
        'Every dish on our menu is labelled with its allergens, and there are vegetarian options such as mushroom tacos, mushroom tortas and mushroom enchiladas. Tell us about any dietary needs in the enquiry form.',
    'How much notice do you need?' =>
        'Tell us your date when you enquire and we will let you know straight away whether we can do it.',
    'Can I see the full menu first?' =>
        'Yes — the full café menu, with allergens on every dish, is on our menu page.',
];

$cat_offers = [];
foreach ($cat_packages as $pkg) {
    if ($pkg['price'] === null || !show_prices()) {
        continue;
    }
    $cat_offers[] = [
        '@type'       => 'Offer',
        'name'        => $pkg['name'],
        'description' => $pkg['includes'],
        'priceSpecification' => [
            '@type'         => 'UnitPriceSpecification',
            'price'         => number_format($pkg['price'], 2, '.', ''),
            'priceCurrency' => 'EUR',
            'unitText'      => 'per person',
        ],
    ];
}

$pageSchema = [
    [
        '@type'       => 'Service',
        '@id'         => seo_url('event-catering.php') . '#catering',
        'name'        => 'Event catering',
        'serviceType' => 'Event catering',
        'description' => 'Mexican catering for 20 to 200 guests, from the Cilantro Café menu. Pickup from Blackrock or delivery.',
        'provider'    => ['@id' => seo_base_url() . '/#restaurant'],
        'areaServed'  => ['Blackrock', 'Dún Laoghaire', 'Dublin'],
    ] + ($cat_offers ? ['hasOfferCatalog' => [
        '@type'           => 'OfferCatalog',
        'name'            => 'Catering packages',
        'itemListElement' => $cat_offers,
    ]] : []),
    seo_faq($cat_faq),
    seo_breadcrumbs(['Event Catering' => 'event-catering.php']),
];

require __DIR__ . '/includes/header.php';

function cat_field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>'
        : '';
}
function cat_error_attrs(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}

$cardPackage = $cat_packages[$cat_card['package']] ?? reset($cat_packages);
$cardPrice   = $cardPackage['price'];
$cardTotal   = catering_estimate($cardPrice, (int) $cat_card['guests']);
?>

<section class="catering-hero" id="price" aria-labelledby="catering-title">
    <div class="container catering-hero-grid">

        <div class="catering-copy">
            <span class="catering-eyebrow">Event Catering · Blackrock &amp; Dublin</span>
            <h1 id="catering-title">Tacos, tortas and brunch, catered for your event.</h1>
            <p class="catering-lede">From office lunches to weddings, we cater for
                <?= (int) $catering['min_guests'] ?> to <?= (int) $catering['max_guests'] ?> guests with food straight
                from our café menu. Collect it from Blackrock or have it delivered.</p>
            <ul class="catering-proof">
                <li><strong><?= (int) $catering['min_guests'] ?>–<?= (int) $catering['max_guests'] ?></strong> guests</li>
                <li><strong>Pickup</strong> or delivery</li>
                <li><strong>Straight</strong> from our café menu</li>
            </ul>
            <a class="catering-textlink" href="#quote">Rather tell us everything? Use the full enquiry form &darr;</a>
        </div>

        <form class="price-card" method="post" action="event-catering.php#price" novalidate data-price-card>
            <input type="hidden" name="form" value="price_card">
            <input type="hidden" name="token" value="<?= e($_SESSION['catering_token']) ?>">
            <div class="hp" aria-hidden="true">
                <label for="pc-website">Leave this empty</label>
                <input type="text" id="pc-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="pc-head">
                <h2 class="pc-title">Get a catering quote</h2>
                <p class="pc-sub">Pick your occasion, then slide for guest numbers</p>
            </div>

            <fieldset class="pc-block">
                <legend class="pc-step"><span class="pc-step-num">1</span> What's the occasion?</legend>
                <div class="pc-pills">
                    <?php foreach ($catering['occasions'] as $key => $occasion): ?>
                        <label class="pc-pill">
                            <input type="radio" name="occasion" value="<?= e($key) ?>"
                                   data-package="<?= e($occasion['package']) ?>"
                                   <?= $cat_card['occasion'] === $key ? 'checked' : '' ?>>
                            <span><?= e($occasion['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="pc-block">
                <label class="pc-step" for="pc-guests"><span class="pc-step-num">2</span> How many guests?</label>
                <div class="pc-guests-row">
                    <input class="pc-range" type="range" id="pc-guests" name="guests"
                           min="<?= (int) $catering['min_guests'] ?>" max="<?= (int) $catering['max_guests'] ?>" step="5"
                           value="<?= (int) $cat_card['guests'] ?>">
                    <output class="pc-guests-out" for="pc-guests"><strong data-pc-guests><?= (int) $cat_card['guests'] ?></strong> guests</output>
                </div>
                <p class="pc-hint">Under <?= (int) $catering['min_guests'] ?>? <a href="booking.php">Book a table</a>
                    · More than <?= (int) $catering['max_guests'] ?>? <a href="#quote">Tell us about it</a></p>
            </div>

            <div class="pc-block">
                <label class="pc-step" for="pc-location"><span class="pc-step-num">3</span> Where's the event?</label>
                <input class="pc-input pc-location" type="text" id="pc-location" name="location" maxlength="200"
                       value="<?= e($cat_card['location']) ?>" placeholder="Town, area or venue — e.g. Blackrock"
                       autocomplete="address-level2"<?= isset($cat_errors['location']) ? ' aria-invalid="true" aria-describedby="location-error"' : '' ?>>
                <?php if (isset($cat_errors['location'])): ?>
                    <p class="pc-error field-error" id="location-error"><?= e($cat_errors['location']) ?></p>
                <?php endif; ?>
            </div>

            <div class="pc-block">
                <label class="pc-step" for="pc-package"><span class="pc-step-num">4</span> Your package</label>
                <select class="pc-select" id="pc-package" name="package">
                    <?php foreach ($cat_packages as $key => $pkg): ?>
                        <option value="<?= e($key) ?>"
                                <?php if (show_prices() && $pkg['price'] !== null): ?>data-price="<?= e(number_format($pkg['price'], 2, '.', '')) ?>"<?php endif; ?>
                                data-includes="<?= e($pkg['includes']) ?>"
                                <?= $cat_card['package'] === $key ? 'selected' : '' ?>><?= e($pkg['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pc-result" aria-live="polite">
                <?php if (show_prices()): ?>
                    <span class="pc-result-label">Starting price</span>
                    <span class="pc-price" data-pc-price>
                        <?php if ($cardPrice === null): ?>
                            Price on request
                        <?php else: ?>
                            <?= e(catering_money($cardPrice)) ?> <small>per person</small>
                        <?php endif; ?>
                    </span>
                    <span class="pc-total" data-pc-total>
                        <?php if ($cardTotal !== null): ?>
                            around <?= e(catering_money((float) $cardTotal, false)) ?> for <?= (int) $cat_card['guests'] ?> guests
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="pc-result-label">Your package</span>
                    <span class="pc-price" data-pc-name><?= e($cardPackage['name']) ?></span>
                <?php endif; ?>
                <span class="pc-includes" data-pc-includes><?= e($cardPackage['includes']) ?></span>
            </div>

            <div class="pc-capture">
                <?php if ($cat_sent && ($cat_sent['source'] ?? '') === 'price_card'): ?>
                    <div class="pc-success" role="status"
                         data-track-view="catering_enquiry_submitted"
                         data-source="price_card"
                         data-guests="<?= (int) $cat_sent['guests'] ?>"
                         data-package="<?= e($cat_sent['package']) ?>"
                         data-occasion="<?= e($cat_sent['occasion']) ?>">
                        <span class="pc-success-icon" aria-hidden="true">&#10003;</span>
                        <h3>Got it — thanks!</h3>
                        <p>We'll come back to you with a quote for your event.</p>
                    </div>
                <?php else: ?>
                    <?php if (isset($cat_errors['form_price_card'])): ?>
                        <p class="pc-error" role="alert"><?= e($cat_errors['form_price_card']) ?></p>
                    <?php endif; ?>
                    <label class="pc-prompt" for="pc-contact">Want a quote? Leave a phone number or email.</label>
                    <div class="pc-form-row">
                        <input class="pc-input" type="text" id="pc-contact" name="contact"
                               value="<?= e($cat_card['contact']) ?>" placeholder="Phone number or email"
                               autocomplete="off" inputmode="email"
                               <?= isset($cat_errors['contact']) ? 'aria-invalid="true" aria-describedby="contact-error"' : '' ?>>
                        <button class="btn btn-green pc-submit" type="submit">Get my quote</button>
                    </div>
                    <?php if (isset($cat_errors['contact'])): ?>
                        <p class="pc-error field-error" id="contact-error"><?= e($cat_errors['contact']) ?></p>
                    <?php endif; ?>
                    <p class="pc-reassure">No obligation · <a href="privacy.php">privacy</a></p>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<section class="section catering-steps-section" aria-labelledby="how-title">
    <div class="container">
        <h2 id="how-title">How catering works</h2>
        <ol class="catering-steps">
            <li>
                <span class="catering-step-num">1</span>
                <h3>Tell us about your event</h3>
                <p>Use the quote box above, or fill in the full form below with your date, numbers and what you fancy.</p>
            </li>
            <li>
                <span class="catering-step-num">2</span>
                <h3>We send you a quote</h3>
                <p>We come back to you with a quote for your event, using the same dishes as our café menu.</p>
            </li>
            <li>
                <span class="catering-step-num">3</span>
                <h3>Collect it, or we deliver</h3>
                <p>Pick your order up from the café in Blackrock, or we bring it to you.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section catering-packages-section" aria-labelledby="packages-title">
    <div class="container">
        <div class="section-head">
            <h2 id="packages-title">Catering packages</h2>
            <a class="text-link" href="menu.php">See the full menu</a>
        </div>
        <ul class="package-grid">
            <?php foreach ($cat_packages as $key => $pkg): ?>
                <li class="package-card">
                    <h3><?= e($pkg['name']) ?></h3>
                    <p class="package-includes"><?= e($pkg['includes']) ?></p>
                    <?php if (show_prices()): ?>
                        <p class="package-price">
                            <?php if ($pkg['price'] === null): ?>
                                Price on request
                            <?php else: ?>
                                From <strong><?= e(catering_money($pkg['price'])) ?></strong> per person
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <a class="btn btn-green" href="#price" data-price-package="<?= e($key) ?>"
                       data-track="catering_choose_package" data-track-label="<?= e($key) ?>">Choose this package</a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (show_prices()): ?>
            <p class="package-note">Starting prices per person, taken from our café menu. Drinks, sides and extras are added to your exact price.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($catering['videos'])): ?>
<section class="section catering-videos-section" aria-labelledby="videos-title">
    <div class="container">
        <h2 id="videos-title">See us cater</h2>
        <ul class="catering-videos">
            <?php foreach ($catering['videos'] as $video): ?>
                <li class="catering-video">
                    <?php if (!empty($video['youtube']) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $video['youtube'])): ?>
                        <iframe src="https://www.youtube-nocookie.com/embed/<?= e($video['youtube']) ?>"
                                title="<?= e($video['title'] ?? 'Catering video') ?>" loading="lazy"
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <?php elseif (!empty($video['file'])): ?>
                        <video controls preload="none" playsinline
                               <?= !empty($video['poster']) ? 'poster="' . e($video['poster']) . '"' : '' ?>>
                            <source src="<?= e($video['file']) ?>" type="video/mp4">
                        </video>
                    <?php endif; ?>
                    <?php if (!empty($video['title'])): ?>
                        <p class="catering-video-title"><?= e($video['title']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="section catering-enquiry-section" id="quote" aria-labelledby="quote-title">
    <div class="container catering-enquiry">
        <div class="catering-enquiry-side">
            <h2 id="quote-title">Tell us about your event</h2>
            <p>The more you tell us, the more exact your quote. We use your details only to answer your enquiry.</p>
            <ul class="catering-next">
                <li>We read every enquiry ourselves</li>
                <li>You get a quote for your event</li>
                <li>Nothing is booked until you say yes</li>
            </ul>
            <img class="catering-side-photo" src="images/tostadas-and-torta.jpg" alt="Tostadas and a torta on the table at Cilantro Café" loading="lazy">
        </div>

        <div class="catering-enquiry-main">
            <?php if ($cat_sent && ($cat_sent['source'] ?? '') === 'full_form'): ?>
                <div class="confirmation" role="status"
                     data-track-view="catering_enquiry_submitted"
                     data-source="full_form"
                     data-guests="<?= (int) $cat_sent['guests'] ?>"
                     data-package="<?= e($cat_sent['package']) ?>"
                     data-occasion="<?= e($cat_sent['occasion']) ?>">
                    <h2>Enquiry sent</h2>
                    <p>Thanks — we have your catering enquiry for
                        <strong><?= (int) $cat_sent['guests'] ?> guests</strong>
                        (<?= e(catering_package_name($cat_sent['package'])) ?>).</p>
                    <p>We'll come back to you with a quote. If you gave us your email, a copy is on its way.</p>
                    <a class="btn btn-green" href="menu.php">See the menu</a>
                </div>
            <?php else: ?>

                <?php if (isset($cat_errors['form_full_form'])): ?>
                    <div class="form-alert" role="alert"><?= e($cat_errors['form_full_form']) ?></div>
                <?php elseif (array_diff_key($cat_errors, ['contact' => 1, 'form_price_card' => 1])): ?>
                    <div class="form-alert" role="alert">Some details need fixing. Check the highlighted fields below.</div>
                <?php endif; ?>

                <form class="booking-form catering-form" method="post" action="event-catering.php#quote" novalidate>
                    <input type="hidden" name="form" value="full_form">
                    <input type="hidden" name="token" value="<?= e($_SESSION['catering_token']) ?>">
                    <div class="hp" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset>
                        <legend>Your event</legend>
                        <div class="field-row">
                            <div class="field">
                                <label for="occasion">Occasion</label>
                                <select id="occasion" name="occasion" required<?= cat_error_attrs($cat_errors, 'occasion') ?>>
                                    <option value="">Choose one</option>
                                    <?php foreach ($catering['occasions'] as $key => $occasion): ?>
                                        <option value="<?= e($key) ?>" <?= $cat_old['occasion'] === $key ? 'selected' : '' ?>><?= e($occasion['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= cat_field_error($cat_errors, 'occasion') ?>
                            </div>
                            <div class="field">
                                <label for="event_date">Date <span class="optional">(if you know it)</span></label>
                                <input type="date" id="event_date" name="event_date"
                                       min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($lastDay->format('Y-m-d')) ?>"
                                       value="<?= e($cat_old['event_date']) ?>"<?= cat_error_attrs($cat_errors, 'event_date') ?>>
                                <?= cat_field_error($cat_errors, 'event_date') ?>
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="guests">Number of guests</label>
                                <input type="number" id="guests" name="guests" inputmode="numeric" required
                                       min="<?= (int) $catering['min_guests'] ?>" max="<?= (int) $catering['max_guests'] ?>"
                                       placeholder="<?= (int) $catering['min_guests'] ?>–<?= (int) $catering['max_guests'] ?>"
                                       value="<?= e($cat_old['guests']) ?>"<?= cat_error_attrs($cat_errors, 'guests') ?>>
                                <?= cat_field_error($cat_errors, 'guests') ?>
                            </div>
                            <div class="field">
                                <label for="package">Package</label>
                                <select id="package" name="package" required<?= cat_error_attrs($cat_errors, 'package') ?>>
                                    <option value="">Choose one</option>
                                    <?php foreach ($cat_packages as $key => $pkg): ?>
                                        <option value="<?= e($key) ?>" <?= $cat_old['package'] === $key ? 'selected' : '' ?>>
                                            <?= e($pkg['name']) ?><?= (show_prices() && $pkg['price'] !== null) ? ' — from ' . e(catering_money($pkg['price'])) . ' pp' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= cat_field_error($cat_errors, 'package') ?>
                            </div>
                        </div>

                        <div class="field">
                            <span class="field-label" id="fulfilment-label">Pickup or delivery?</span>
                            <div class="choice-row" role="radiogroup" aria-labelledby="fulfilment-label"<?= isset($cat_errors['fulfilment']) ? ' aria-describedby="fulfilment-error"' : '' ?>>
                                <label class="choice">
                                    <input type="radio" name="fulfilment" value="pickup" <?= $cat_old['fulfilment'] === 'pickup' ? 'checked' : '' ?>>
                                    <span>I'll collect from the café</span>
                                </label>
                                <label class="choice">
                                    <input type="radio" name="fulfilment" value="delivery" <?= $cat_old['fulfilment'] === 'delivery' ? 'checked' : '' ?>>
                                    <span>Please deliver</span>
                                </label>
                            </div>
                            <?= cat_field_error($cat_errors, 'fulfilment') ?>
                        </div>

                        <div class="field" data-delivery-address <?= $cat_old['fulfilment'] === 'delivery' || isset($cat_errors['delivery_address']) ? '' : 'hidden' ?>>
                            <label for="delivery_address">Where should we deliver?</label>
                            <textarea id="delivery_address" name="delivery_address" rows="2" maxlength="300"
                                      placeholder="Venue or address, and Eircode if you have it"<?= cat_error_attrs($cat_errors, 'delivery_address') ?>><?= e($cat_old['delivery_address']) ?></textarea>
                            <?= cat_field_error($cat_errors, 'delivery_address') ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Your details</legend>
                        <div class="field">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required autocomplete="name" maxlength="80"
                                   value="<?= e($cat_old['name']) ?>"<?= cat_error_attrs($cat_errors, 'name') ?>>
                            <?= cat_field_error($cat_errors, 'name') ?>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" required autocomplete="tel" maxlength="20"
                                       value="<?= e($cat_old['phone']) ?>"<?= cat_error_attrs($cat_errors, 'phone') ?>>
                                <?= cat_field_error($cat_errors, 'phone') ?>
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required autocomplete="email" maxlength="120"
                                       value="<?= e($cat_old['email']) ?>"<?= cat_error_attrs($cat_errors, 'email') ?>>
                                <?= cat_field_error($cat_errors, 'email') ?>
                            </div>
                        </div>
                        <div class="field">
                            <label for="notes">Anything else? <span class="optional">(optional)</span></label>
                            <textarea id="notes" name="notes" rows="4" maxlength="1000"
                                      placeholder="Dishes you'd like, dietary needs, timings, venue details"<?= cat_error_attrs($cat_errors, 'notes') ?>><?= e($cat_old['notes']) ?></textarea>
                            <?= cat_field_error($cat_errors, 'notes') ?>
                        </div>
                    </fieldset>

                    <div class="field consent">
                        <label class="consent-label" for="marketing_consent">
                            <input type="checkbox" id="marketing_consent" name="marketing_consent" value="1" <?= $cat_consent ? 'checked' : '' ?>>
                            <span>Email me now and then about offers and events at Cilantro Café. Optional — it makes no difference to your enquiry, and you can stop any time.</span>
                        </label>
                    </div>

                    <p class="privacy-note">We use your details to answer your enquiry. See our <a href="privacy.php">privacy notice</a>.</p>
                    <button class="btn btn-green btn-block" type="submit">Send my enquiry</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section catering-faq-section" aria-labelledby="faq-title">
    <div class="container catering-faq">
        <h2 id="faq-title">Catering questions</h2>
        <?php foreach ($cat_faq as $q => $a): ?>
            <details class="faq-item">
                <summary><?= e($q) ?></summary>
                <p><?= e($a) ?><?php if (str_contains($q, 'menu first')): ?> <a class="text-link" href="menu.php">See the menu</a><?php endif; ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section class="section cta-band">
    <div class="container cta-inner">
        <h2>Feeding a crowd?</h2>
        <a class="btn btn-light" href="#price">Get a quote</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
