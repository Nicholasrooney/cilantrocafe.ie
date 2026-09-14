<?php
$pageTitle       = 'Cilantro Café | Mexican Café & Brunch in Blackrock, Dublin';
$pageDescription = 'Mexican café in Blackrock, Dublin. Tacos, birria, flautas, tortas, all-day brunch and fresh conchas, with coffee and cocktails. Eat in, sit outside, or book a table.';
$pageKeywords    = 'Mexican restaurant Blackrock, Mexican café Dublin, tacos Dublin, brunch Blackrock, birria Dublin, breakfast Blackrock, Newpark Centre café';
$activePage      = 'home';

// Answers here are all true of the café — everything comes from the menu, the
// address or the booking form. A wrong answer in a rich result is worse than
// no rich result at all.
require_once __DIR__ . '/includes/seo.php';
$pageSchema = [
    seo_faq([
        'Where is Cilantro Café?' =>
            'Cilantro Café is at Unit 7, Newpark Centre, Newtownpark Avenue, Blackrock, Co. Dublin, A94 W956.',
        'Do you take bookings?' =>
            'Yes. You can book a table through the website, and we will be in touch if we cannot fit you in at that time. For groups larger than ten, get in touch directly.',
        'Do you serve breakfast and brunch?' =>
            'Yes. Breakfast is served until 12:00, and the Morning Munchie and chilaquiles are available all day. The menu includes pancakes, French toast, huevos rancheros, a breakfast burrito, avocado toast and a full Irish.',
        "Is there a children's menu?" =>
            'Yes, there is a kids menu for under 13s with chicken tenders, sausages, a quesadilla and pancakes, each served with fries and a juice.',
        'Do you cater for allergies?' =>
            'Allergen codes are listed next to every dish on our menu, covering gluten, dairy, eggs, fish, nuts, sesame, soy and sulphites. Tell a member of staff about any dietary needs when you order.',
        'Can you sit outside?' =>
            'Yes, there are tables out front when the weather allows, as well as booth and table seating inside. You can state a seating preference when you book.',
    ]),
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/directions.php';

$featured = [
    ['img' => 'steak-tacos.jpg',           'name' => 'Steak taco trio',   'price' => '16.50', 'alt' => 'Three steak tacos with guacamole and pico de gallo'],
    ['img' => 'chorizo-tacos-closeup.jpg', 'name' => 'Chorizo taco trio', 'price' => '15.50', 'alt' => 'Chorizo tacos with guacamole, pico de gallo and a lime wedge'],
    ['img' => 'concha-and-matcha.jpg',     'name' => 'Conchas',           'price' => '4.90',  'alt' => 'A pink concha on a plate beside a matcha latte'],
];
?>

<section class="hero">
    <div class="hero-text">
        <h1>Mexican café in Dublin</h1>
        <p class="hero-lede">Tacos, flautas, birria, brunch plates and fresh conchas. Eat in, sit outside, or book a table.</p>
        <div class="hero-actions">
            <a class="btn btn-light" href="menu.php">See the menu</a>
            <a class="btn btn-outline-light" href="booking.php">Book a table</a>
        </div>
    </div>
    <div class="hero-photo">
        <img src="images/storefront.jpg" alt="The green Cilantro Café shopfront with wooden tables outside" width="1220" height="1442" fetchpriority="high">
    </div>
</section>

<section class="section featured">
    <div class="container">
        <div class="section-head">
            <h2>From the menu</h2>
            <a class="text-link" href="menu.php">Full menu</a>
        </div>
        <ul class="dish-row">
            <?php foreach ($featured as $dish): ?>
                <li class="dish">
                    <a href="menu.php">
                        <img src="images/<?= e($dish['img']) ?>" alt="<?= e($dish['alt']) ?>" loading="lazy">
                        <span class="dish-name"><?= e($dish['name']) ?></span>
                        <?php if (show_prices()): ?>
                            <span class="dish-price">€<?= e($dish['price']) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section inside">
    <div class="container inside-grid">
        <img class="inside-photo" src="images/interior.jpg" alt="Inside the café: white tables, wooden chairs and a green booth" width="1600" height="949" loading="lazy">
        <div class="inside-text">
            <h2>Come in and sit down</h2>
            <p>Booth seating and wooden tables inside, and a few tables out front when the weather allows. Bring the kids, there is a menu for under 13s.</p>
            <a class="btn btn-green" href="booking.php">Book a table</a>
        </div>
    </div>
</section>

<section class="section catering-teaser" aria-labelledby="catering-teaser-title">
    <div class="container catering-teaser-inner">
        <div>
            <h2 id="catering-teaser-title">Catering for <?= (int) $catering['min_guests'] ?> to <?= (int) $catering['max_guests'] ?></h2>
            <p>Taco bars, torta platters and brunch spreads for offices, parties and weddings. Food straight from our café menu, collected or delivered.</p>
        </div>
        <a class="btn btn-light" href="event-catering.php">Get a catering quote</a>
    </div>
</section>

<section class="section visit">
    <div class="container visit-grid">
        <div>
            <h2>Visit us</h2>
            <?php render_directions_actions($site); ?>
            <?php if (!empty($site['hours'])): ?>
                <dl class="hours hours-large">
                    <?php foreach ($site['hours'] as $days => $time): ?>
                        <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <p>See what's new on <a class="text-link" href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a>.</p>
        </div>
        <a class="visit-photo" href="gallery.php">
            <img src="images/tacos-sharing.jpg" alt="Two people sharing plates of tacos with salsa" loading="lazy">
            <span>View the gallery</span>
        </a>
    </div>
</section>

<?php render_map_section($site); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
