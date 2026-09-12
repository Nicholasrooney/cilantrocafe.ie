<?php
$pageTitle       = 'Gallery | Cilantro Café, Blackrock Dublin';
$pageDescription = 'Photos of the food and the room at Cilantro Café in Blackrock, Dublin — tacos, brunch plates, conchas and the shopfront.';
$pageKeywords    = 'Cilantro Café photos, Mexican food Dublin photos, Blackrock café';
$activePage      = 'gallery';

require_once __DIR__ . '/includes/seo.php';
$pageSchema = [seo_breadcrumbs(['Gallery' => 'gallery.php'])];

require __DIR__ . '/includes/header.php';

// To add a photo: upload it to /images and add a line here.
$photos = [
    ['tacos-sharing.jpg',            'Chorizo and steak tacos with salsa'],
    ['french-toast.jpg',             'French toast with maple syrup, berries and bacon'],
    ['storefront-day.jpg',           'The shopfront'],
    ['tostadas.jpg',                 'Tostadas with salsa verde, avocado and pickled onion'],
    ['steak-tacos.jpg',              'Steak tacos'],
    ['concha-and-matcha.jpg',        'Pink concha and a matcha latte'],
    ['burrito-salsa.jpg',            'Burrito with salsa'],
    ['torta-and-fries.jpg',          'Torta with fries'],
    ['chorizo-tacos.jpg',            'Chorizo tacos'],
    ['french-toast-and-flautas.jpg', 'French toast and flautas'],
    ['interior.jpg',                 'Inside the café'],
    ['tostadas-and-torta.jpg',       'Tostadas and a torta'],
    ['chorizo-tacos-closeup.jpg',    'Chorizo tacos with guacamole'],
    ['burrito.jpg',                  'Burrito'],
    ['storefront.jpg',               'The shopfront in the evening'],
];
?>

<section class="page-intro">
    <div class="container">
        <h1>Gallery</h1>
        <p>More on <a class="text-link" href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a>.</p>
    </div>
</section>

<div class="container">
    <ul class="gallery">
        <?php foreach ($photos as $i => [$file, $caption]): ?>
            <li>
                <button type="button" class="gallery-item" data-index="<?= $i ?>"
                        data-src="images/<?= e($file) ?>" data-caption="<?= e($caption) ?>"
                        aria-label="Open photo: <?= e($caption) ?>">
                    <img src="images/<?= e($file) ?>" alt="<?= e($caption) ?>" loading="lazy">
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<dialog class="lightbox" aria-label="Photo viewer">
    <figure>
        <img src="" alt="">
        <figcaption></figcaption>
    </figure>
    <button type="button" class="lb-btn lb-prev" aria-label="Previous photo">&#8249;</button>
    <button type="button" class="lb-btn lb-next" aria-label="Next photo">&#8250;</button>
    <button type="button" class="lb-btn lb-close" aria-label="Close">&times;</button>
</dialog>

<?php require __DIR__ . '/includes/footer.php'; ?>
