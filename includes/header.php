<?php
require_once __DIR__ . '/config.php';

$pageTitle       = $pageTitle       ?? $site['name'];
$pageDescription = $pageDescription ?? 'Mexican café in Dublin serving tacos, flautas, birria, brunch and fresh conchas.';
$activePage      = $activePage      ?? '';

$nav = [
    'home'    => ['Home', 'index.php'],
    'menu'    => ['Menu', 'menu.php'],
    'gallery' => ['Gallery', 'gallery.php'],
    'booking' => ['Book a table', 'booking.php'],
];

$hasLogo = file_exists(__DIR__ . '/../images/logo.png');
?>
<!DOCTYPE html>
<html lang="en-IE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="https://<?= e($site['domain']) ?>/images/storefront.jpg">
    <meta name="theme-color" content="#0d5c46">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Young+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-<?= e($activePage) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="fascia">
    <div class="fascia-inner">
        <a class="wordmark" href="index.php" aria-label="<?= e($site['name']) ?> home">
            <?php if ($hasLogo): ?>
                <img src="images/logo.png" alt="<?= e($site['name']) ?>">
            <?php else: ?>
                <span class="wordmark-main">Cilantro</span>
                <span class="wordmark-sub">Café</span>
            <?php endif; ?>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open navigation">
            <span class="nav-toggle-bars" aria-hidden="true"></span>
        </button>

        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($nav as $key => [$label, $href]): ?>
                    <li>
                        <a href="<?= e($href) ?>"
                           class="<?= $key === 'booking' ? 'nav-book' : '' ?>"
                           <?= $activePage === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="main">
