<?php
$pageTitle       = 'Cilantro Café | Mexican café in Dublin';
$pageDescription = 'Tacos, flautas, birria, brunch plates and fresh conchas. Eat in, sit outside, or book a table at Cilantro Café, Dublin.';
$activePage      = 'home';
require __DIR__ . '/includes/header.php';

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
                        <span class="dish-price">€<?= e($dish['price']) ?></span>
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

<section class="section visit">
    <div class="container visit-grid">
        <div>
            <h2>Visit us</h2>
            <?php if ($site['address']): ?>
                <p class="visit-address"><?= e($site['address']) ?></p>
            <?php endif; ?>
            <?php if ($site['maps_url']): ?>
                <p><a class="text-link" href="<?= e($site['maps_url']) ?>" target="_blank" rel="noopener">Get directions</a></p>
            <?php endif; ?>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
