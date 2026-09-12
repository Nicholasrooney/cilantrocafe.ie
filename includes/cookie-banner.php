<?php
/*
 * Cookie notice.
 *
 * This site sets one cookie of its own — the session cookie that keeps the
 * booking form secure — and that is "strictly necessary", so it needs no
 * consent. The only thing here that genuinely needs asking about is the Google
 * Maps embed, which is why the map does not load until someone asks for it.
 *
 * So this banner is not decoration: choosing "Accept all" is what lets the map
 * load on its own next time. Choosing "Essential only" keeps Google off the
 * page entirely.
 *
 * The choice is kept in localStorage rather than a cookie, so declining
 * cookies does not itself set one.
 */
function render_cookie_banner(): void
{
    ?>
    <div class="cookie" id="cookie-banner" hidden>
        <div class="cookie-inner">
            <p class="cookie-text">
                We use one cookie to keep the booking form working. Nothing tracks you
                and there are no ads. Our map is loaded from Google only when you ask
                for it — allow that here and it will load on its own next time.
                <a href="privacy.php">Privacy notice</a>.
            </p>
            <div class="cookie-do">
                <button type="button" class="btn btn-outline-light" data-cookie="essential">Essential only</button>
                <button type="button" class="btn btn-light" data-cookie="all">Allow the map</button>
            </div>
        </div>
    </div>
    <?php
}
