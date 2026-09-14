<?php
/*
 * Google Analytics 4, loaded only with consent.
 *
 * This is Google's own gtag.js snippet with one change: the script is not
 * fetched until the visitor has chosen "Accept all" in the cookie notice.
 * Analytics cookies need prior consent under Irish and EU law, so pasting the
 * snippet straight into <head> would set them on every visitor the moment the
 * page loads. Until someone accepts, the page makes no request to Google at all.
 *
 * Once loaded, js/main.js reports what people do — menu, directions, bookings,
 * catering, phone and Instagram taps — through window.cilantroAnalytics.track().
 * Nothing personal is ever sent: no names, phone numbers or email addresses,
 * which Google's terms forbid anyway.
 *
 * Only the public pages include this. The staff area and setup page never load
 * analytics, so staff activity doesn't skew the numbers and booking screens full
 * of customer details are never reported anywhere.
 */

function render_analytics_head(): void
{
    global $analytics;

    $id = (string) ($analytics['ga4_id'] ?? '');
    if (!preg_match('/^G-[A-Z0-9]{4,}$/', $id)) {
        return;
    }
    ?>
    <script>
    (function () {
      var ID = <?= json_encode($id) ?>;
      var loaded = false;

      window.dataLayer = window.dataLayer || [];
      function gtag() { window.dataLayer.push(arguments); }
      window.gtag = gtag;

      function choice() {
        try { return localStorage.getItem('cilantro-cookies'); } catch (e) { return null; }
      }

      function load() {
        if (loaded) return;
        loaded = true;
        window['ga-disable-' + ID] = false;

        // Analytics only. This site runs no ads, so advertising storage stays off.
        gtag('consent', 'default', {
          analytics_storage: 'granted',
          ad_storage: 'denied',
          ad_user_data: 'denied',
          ad_personalization: 'denied'
        });

        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + ID;
        document.head.appendChild(s);

        gtag('js', new Date());
        gtag('config', ID);
      }

      // Withdrawing consent has to be as easy as giving it: stop sending, and
      // clear the cookies Google already set.
      function unload() {
        window['ga-disable-' + ID] = true;
        loaded = false;
        var host = location.hostname;
        document.cookie.split(';').forEach(function (c) {
          var name = c.split('=')[0].trim();
          if (name.indexOf('_ga') !== 0) return;
          [host, '.' + host, host.replace(/^www\./, '.')].forEach(function (d) {
            document.cookie = name + '=; Max-Age=0; path=/; domain=' + d;
          });
          document.cookie = name + '=; Max-Age=0; path=/';
        });
      }

      window.cilantroAnalytics = {
        enable: load,
        disable: unload,
        enabled: function () { return loaded; },
        track: function (name, params) {
          if (!loaded) return;
          gtag('event', name, params || {});
        }
      };

      if (choice() === 'all') load();
    })();
    </script>
    <?php
}
