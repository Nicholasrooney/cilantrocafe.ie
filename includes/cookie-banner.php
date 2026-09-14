<?php
/*
 * Cookie notice.
 *
 * Two things here need consent: Google Analytics, and the Google Maps embed.
 * The booking form's session cookie is strictly necessary and needs none.
 *
 * "Accept all" loads analytics and keeps the map. "Essential only" loads no
 * analytics and swaps the map for a plain link. Analytics is opt-in — nothing
 * is sent to Google until somebody accepts. The footer's "Cookie settings"
 * link reopens this so a choice can be changed later.
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
                We use a cookie to keep the booking form working. With your OK we also use
                Google Analytics to see which pages people use, so we can improve the site.
                No ads. The map on our home page comes from Google, who may set their own cookies.
                <a href="privacy.php">Privacy notice</a>.
            </p>
            <div class="cookie-do">
                <button type="button" class="btn btn-outline-light" data-cookie="essential">Essential only</button>
                <button type="button" class="btn btn-light" data-cookie="all">Accept all</button>
            </div>
        </div>
    </div>
    <?php
}
