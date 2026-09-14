<?php
/*
 * Sitemap, generated rather than hand-written so it cannot drift out of date
 * when a page is added. Served at /sitemap.xml via the rewrite in .htaccess.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=utf-8');

// path => [change frequency, priority]
$pages = [
    'index.php'   => ['weekly',  '1.0'],
    'menu.php'    => ['weekly',  '0.9'],
    'booking.php' => ['monthly', '0.9'],
    'event-catering.php' => ['monthly', '0.9'],
    'gallery.php' => ['monthly', '0.7'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pages as $path => [$freq, $priority]) {
    $file = __DIR__ . '/' . $path;
    $mod  = is_file($file) ? date('Y-m-d', filemtime($file)) : date('Y-m-d');

    echo "  <url>\n";
    echo '    <loc>' . e(seo_url($path)) . "</loc>\n";
    echo '    <lastmod>' . $mod . "</lastmod>\n";
    echo '    <changefreq>' . $freq . "</changefreq>\n";
    echo '    <priority>' . $priority . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
