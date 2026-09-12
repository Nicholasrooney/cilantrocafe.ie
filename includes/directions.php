<?php
/*
 * Directions: getting people from wherever they are to the café door.
 *
 * The "Get directions" button always carries a Google Maps universal link,
 * which opens the Google Maps app on Android, the app or the web on iOS, and
 * the website on a desktop. main.js then upgrades it to an Apple Maps link on
 * Apple devices, so the button opens whichever app that person actually uses.
 * With JavaScript off everyone still gets a working Google link.
 *
 * The map itself is click-to-load. Embedding Google on page load would pull in
 * their cookies before the visitor has agreed to anything, which we do not want
 * on a site with no cookie banner.
 */

/**
 * Every destination URL for the café, built from one address.
 *
 * Uses $site['coords'] when set, because "Unit 7" in a shopping centre is
 * exactly the kind of address map apps drop a pin 200m away from.
 */
function directions_links(array $site): array
{
    $address = trim($site['address'] ?? '');
    $eircode = trim($site['eircode'] ?? '');

    $label = $eircode !== '' ? "$address, $eircode" : $address;
    $place = ($site['name'] ?? '') . ', ' . $label;

    // Coordinates are unambiguous; fall back to the written address.
    $target = trim($site['coords'] ?? '') !== '' ? trim($site['coords']) : $place;
    $t      = rawurlencode($target);

    return [
        'label'  => $label,
        'place'  => $place,
        'google' => "https://www.google.com/maps/dir/?api=1&destination=$t",
        'apple'  => "https://maps.apple.com/?daddr=$t&dirflg=d",
        'waze'   => "https://waze.com/ul?q=$t&navigate=yes",
        'embed'  => "https://www.google.com/maps?q=$t&output=embed",
    ];
}

/**
 * The address block plus the directions button. Used inside "Visit us".
 */
function render_directions_actions(array $site): void
{
    if (empty($site['address'])) {
        return;
    }

    $links = directions_links($site);
    ?>
    <p class="directions-address">
        <?= nl2br(e(str_replace(', ', ",\n", $site['address']))) ?>
        <?php if (!empty($site['eircode'])): ?>
            <br><span class="eircode"><?= e($site['eircode']) ?></span>
        <?php endif; ?>
    </p>

    <a class="btn btn-green btn-directions"
       href="<?= e($links['google']) ?>"
       data-apple="<?= e($links['apple']) ?>"
       target="_blank" rel="noopener">Get directions</a>

    <p class="directions-apps">
        Or open in
        <a href="<?= e($links['apple']) ?>" target="_blank" rel="noopener">Apple Maps</a>,
        <a href="<?= e($links['google']) ?>" target="_blank" rel="noopener">Google Maps</a>
        or <a href="<?= e($links['waze']) ?>" target="_blank" rel="noopener">Waze</a>.
    </p>
    <?php
}

/**
 * The full-width map strip. Nothing loads from Google until the visitor asks.
 */
function render_map_section(array $site): void
{
    if (empty($site['address'])) {
        return;
    }

    $links = directions_links($site);
    ?>
    <section class="section map-strip" id="map" aria-labelledby="map-title">
        <div class="container">
            <div class="section-head">
                <h2 id="map-title">On the map</h2>
                <a class="text-link" href="<?= e($links['google']) ?>" target="_blank" rel="noopener">Open in Maps</a>
            </div>

            <div class="map-frame">
                <button type="button" class="map-load"
                        data-embed="<?= e($links['embed']) ?>"
                        data-place="<?= e($links['place']) ?>">
                    <span class="map-load-title">Show the map</span>
                    <span class="map-load-note"><?= e($links['label']) ?></span>
                    <span class="map-load-small">Loads Google Maps when you tap</span>
                </button>
            </div>
        </div>
    </section>
    <?php
}
