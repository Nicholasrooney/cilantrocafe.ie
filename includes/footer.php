</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-col">
            <p class="footer-name"><?= e($site['name']) ?></p>
            <?php if ($site['address']): ?>
                <p><?= e($site['address']) ?></p>
            <?php endif; ?>
            <?php if ($site['maps_url']): ?>
                <p><a href="<?= e($site['maps_url']) ?>" target="_blank" rel="noopener">Get directions</a></p>
            <?php endif; ?>
        </div>

        <div class="footer-col">
            <?php if ($site['phone']): ?>
                <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $site['phone'])) ?>"><?= e($site['phone']) ?></a></p>
            <?php endif; ?>
            <?php if ($site['email']): ?>
                <p><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></p>
            <?php endif; ?>
            <p><a href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a></p>
        </div>

        <?php if (!empty($site['hours'])): ?>
            <div class="footer-col">
                <p class="footer-heading">Opening hours</p>
                <dl class="hours">
                    <?php foreach ($site['hours'] as $days => $time): ?>
                        <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>
    </div>
    <p class="footer-legal">
        &copy; <?= date('Y') ?> <?= e($site['name']) ?>
        · <a href="privacy.php">Privacy</a>
    </p>
</footer>

<?php
require_once __DIR__ . '/cookie-banner.php';
render_cookie_banner();
?>

<script src="js/main.js" defer></script>
</body>
</html>
