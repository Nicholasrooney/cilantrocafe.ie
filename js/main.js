// Mobile navigation
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (!toggle || !nav) return;

  toggle.addEventListener('click', function () {
    var open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    toggle.setAttribute('aria-label', open ? 'Open navigation' : 'Close navigation');
    nav.classList.toggle('is-open', !open);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
      toggle.click();
      toggle.focus();
    }
  });
})();

// Gallery lightbox
(function () {
  var items = Array.prototype.slice.call(document.querySelectorAll('.gallery-item'));
  var box = document.querySelector('.lightbox');
  if (!items.length || !box || typeof box.showModal !== 'function') return;

  var img = box.querySelector('img');
  var caption = box.querySelector('figcaption');
  var current = 0;

  function show(index) {
    current = (index + items.length) % items.length;
    var item = items[current];
    img.src = item.dataset.src;
    img.alt = item.dataset.caption;
    caption.textContent = item.dataset.caption;
  }

  items.forEach(function (item, i) {
    item.addEventListener('click', function () {
      show(i);
      box.showModal();
    });
  });

  box.querySelector('.lb-prev').addEventListener('click', function () { show(current - 1); });
  box.querySelector('.lb-next').addEventListener('click', function () { show(current + 1); });
  box.querySelector('.lb-close').addEventListener('click', function () { box.close(); });

  box.addEventListener('click', function (e) {
    if (e.target === box) box.close();
  });

  box.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });

  box.addEventListener('close', function () {
    items[current].focus();
  });
})();

// Directions: open the maps app this device actually uses
(function () {
  var btn = document.querySelector('.btn-directions');
  if (!btn || !btn.dataset.apple) return;

  var ua = navigator.userAgent || '';
  var isApple = /iPad|iPhone|iPod|Macintosh/.test(ua) ||
                // iPadOS 13+ reports itself as a Mac, so check for touch too
                (/Mac/.test(ua) && navigator.maxTouchPoints > 1);

  // Everyone starts on the Google link, which works everywhere. Apple devices
  // get upgraded to Apple Maps so the button opens their default app.
  if (isApple) btn.href = btn.dataset.apple;
})();

// Cookie choice. Stored in localStorage rather than a cookie, so saying no to
// cookies doesn't ironically set one. Returns 'all', 'essential' or null.
function cookieChoice(set) {
  try {
    if (set) { localStorage.setItem('cilantro-cookies', set); return set; }
    return localStorage.getItem('cilantro-cookies');
  } catch (e) {
    // Private browsing, or storage blocked. Treat as "not decided" and never
    // assume consent.
    return null;
  }
}

// Map. It loads with the page now rather than waiting for a tap, so people see
// where we are without doing anything. Google may set cookies when it loads, so
// anyone who has actively chosen "Essential only" gets it taken back out and
// replaced with a link — that keeps the cookie choice meaningful rather than
// decorative.
(function () {
  var frame = document.querySelector('.map-frame');
  if (!frame) return;

  function removeMap() {
    var iframe = frame.querySelector('.map-embed');
    if (!iframe) return;
    iframe.remove();

    var link = document.createElement('a');
    link.className = 'map-declined';
    link.href = frame.dataset.maps || '#';
    link.target = '_blank';
    link.rel = 'noopener';
    link.innerHTML =
      '<span class="map-declined-title">Map hidden</span>' +
      '<span class="map-declined-note">You chose essential cookies only. ' +
      'Tap to open the map in Google Maps instead.</span>';
    frame.appendChild(link);
  }

  function restoreMap() {
    if (frame.querySelector('.map-embed')) return;
    var declined = frame.querySelector('.map-declined');
    if (declined) declined.remove();

    var iframe = document.createElement('iframe');
    iframe.className = 'map-embed';
    iframe.src = frame.dataset.embed;
    iframe.title = 'Map showing ' + frame.dataset.place;
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'no-referrer-when-downgrade';
    iframe.allowFullscreen = true;
    frame.appendChild(iframe);
  }

  if (cookieChoice() === 'essential') removeMap();

  document.addEventListener('cilantro:allow-map', restoreMap);
  document.addEventListener('cilantro:deny-map', removeMap);
})();

// Cookie banner. Shown until somebody chooses, and reopened by the footer's
// "Cookie settings" link so a choice can be changed or withdrawn later.
(function () {
  var banner = document.getElementById('cookie-banner');
  if (!banner) return;

  if (!cookieChoice()) banner.hidden = false;

  banner.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cookie]');
    if (!btn) return;

    var accepted = btn.dataset.cookie === 'all';
    cookieChoice(btn.dataset.cookie);
    banner.hidden = true;

    if (window.cilantroAnalytics) {
      if (accepted) {
        window.cilantroAnalytics.enable();
        window.cilantroAnalytics.track('cookie_consent', { choice: 'accept_all' });
      } else {
        window.cilantroAnalytics.disable();
      }
    }

    document.dispatchEvent(new CustomEvent(accepted ? 'cilantro:allow-map' : 'cilantro:deny-map'));
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-cookie-settings]')) return;
    banner.hidden = false;
    var first = banner.querySelector('[data-cookie="all"]');
    if (first) first.focus();
  });
})();

// Booking form: refresh the time slots when the date or party size changes, so
// a full sitting is greyed out before somebody fills in the whole form and gets
// turned away at the end.
(function () {
  var form = document.querySelector('.booking-form');
  if (!form) return;

  var date   = form.querySelector('#date');
  var time   = form.querySelector('#time');
  var guests = form.querySelector('#guests');
  if (!date || !time) return;

  var inFlight = null;

  function refresh() {
    if (!date.value) return;

    var url = 'slots.php?date=' + encodeURIComponent(date.value) +
              '&guests=' + encodeURIComponent(guests ? guests.value : 1);

    if (inFlight) inFlight.abort();
    inFlight = new AbortController();

    fetch(url, { signal: inFlight.signal })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;

        var note = document.getElementById('slot-note');
        var chosen = time.value;

        // Rebuild the list: opening hours differ by day and the cafe is shut
        // on Mondays, so the times themselves change, not just their state.
        while (time.options.length > 1) time.remove(1);

        if (data.closed) {
          time.value = '';
          if (note) {
            note.textContent = data.message || 'We are closed that day.';
            note.hidden = false;
          }
          return;
        }

        if (note) note.hidden = true;

        Object.keys(data.slots).forEach(function (t) {
          var free = data.slots[t];
          var opt = document.createElement('option');
          opt.value = t;
          opt.textContent = t + (free ? '' : ' — fully booked');
          opt.disabled = !free;
          if (t === chosen) opt.selected = true;
          time.appendChild(opt);
        });

        // If their chosen time is gone or now full, clear it rather than
        // submitting something that will be rejected.
        if (chosen && (!time.value || (time.selectedOptions[0] && time.selectedOptions[0].disabled))) {
          time.value = '';
        }
      })
      .catch(function () { /* offline or aborted: leave the form as it is */ });
  }

  date.addEventListener('change', refresh);
  if (guests) guests.addEventListener('change', refresh);
})();

// Analytics: what people actually do on the site.
//
// Every event has its own name so it shows up on its own line in Google
// Analytics with no setup: click_menu, click_directions, click_book_table and
// so on. A "location" says where on the page it was tapped (nav, hero, footer,
// or the section it sits in), so you can tell a nav tap from a hero-button tap.
//
// Links are recognised by where they go, not by hand-tagging each one, so a
// new "Book a table" button anywhere on the site is counted automatically.
// Nothing personal is ever sent.
(function () {
  function track(name, params) {
    if (window.cilantroAnalytics) window.cilantroAnalytics.track(name, params);
  }

  function whereOnPage(el) {
    if (el.closest('.fascia, .site-nav')) return 'nav';
    if (el.closest('.site-footer')) return 'footer';
    if (el.closest('.hero')) return 'hero';
    if (el.closest('.cookie')) return 'cookie_banner';
    var section = el.closest('section[id], section[class]');
    if (section) return section.id || section.className.split(' ').pop();
    return 'page';
  }

  // Which event a link counts as, from its destination.
  function eventFor(a) {
    var explicit = a.getAttribute('data-track');
    if (explicit) return { name: explicit, label: a.getAttribute('data-track-label') || '' };

    var href = a.getAttribute('href') || '';
    if (/^tel:/.test(href))                return { name: 'click_phone' };
    if (/^mailto:/.test(href))             return { name: 'click_email' };
    if (/instagram\.com/.test(href))       return { name: 'click_instagram' };
    if (/maps\.apple\.com/.test(href))     return { name: 'click_directions', app: 'apple_maps' };
    if (/waze\.com/.test(href))            return { name: 'click_directions', app: 'waze' };
    if (/google\.[a-z.]+\/maps/.test(href)) return { name: 'click_directions', app: 'google_maps' };
    if (/event-catering/.test(href))       return { name: 'click_catering' };
    if (/booking\.php/.test(href))         return { name: 'click_book_table' };
    if (/menu\.php/.test(href))            return { name: 'click_menu' };
    if (/gallery\.php/.test(href))         return { name: 'click_gallery' };
    if (/privacy\.php/.test(href))         return { name: 'click_privacy' };
    if (/^#/.test(href) && a.closest('.menu-jump')) return { name: 'menu_section', label: href.slice(1) };
    return null;
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    var hit = eventFor(a);
    if (!hit) return;

    var params = { location: whereOnPage(a) };
    if (hit.app) params.app = hit.app;
    if (hit.label) params.label = hit.label;
    if (a.classList.contains('btn-directions')) params.label = 'get_directions_button';
    track(hit.name, params);
  }, true);

  // Gallery: which photos people open.
  document.addEventListener('click', function (e) {
    var item = e.target.closest('.gallery-item');
    if (item) track('gallery_open', { label: item.dataset.caption || '' });
  }, true);

  // Booking funnel: started, hit a problem, or went through.
  var form = document.querySelector('.booking-form');
  if (form) {
    var started = false;
    form.addEventListener('focusin', function () {
      if (started) return;
      started = true;
      track('booking_form_start', { location: 'booking_page' });
    });

    // The server re-renders the form with errors; report which fields (never
    // what was typed) so the fields that trip people up are visible.
    var errors = Array.prototype.map.call(
      document.querySelectorAll('.field-error[id]'),
      function (el) { return el.id.replace(/-error$/, ''); }
    );
    if (errors.length) track('booking_form_error', { fields: errors.join(',') });
  }

  var done = document.querySelector('[data-track-view="booking_submitted"]');
  if (done) {
    track('booking_submitted', {
      guests: parseInt(done.dataset.guests, 10) || 0,
      time: done.dataset.time || '',
      day_of_week: done.dataset.day || ''
    });
  }
})();

// Catering price card: the live price, straight from the menu-linked figures
// the server printed onto each package option. Works as a plain form without
// this; the script just keeps the price in step as people choose.
(function () {
  var card = document.querySelector('[data-price-card]');
  if (!card) return;

  var range   = card.querySelector('.pc-range');
  var select  = card.querySelector('.pc-select');
  var guestEl = card.querySelector('[data-pc-guests]');
  var priceEl = card.querySelector('[data-pc-price]');
  var totalEl = card.querySelector('[data-pc-total]');
  var inclEl  = card.querySelector('[data-pc-includes]');
  var nameEl  = card.querySelector('[data-pc-name]');
  if (!range || !select) return;

  function euro(n, cents) {
    return '€' + n.toLocaleString('en-IE', {
      minimumFractionDigits: cents ? 2 : 0,
      maximumFractionDigits: cents ? 2 : 0
    });
  }

  function update() {
    var guests = parseInt(range.value, 10) || 0;
    var opt    = select.options[select.selectedIndex];
    var price  = parseFloat(opt && opt.dataset.price);

    if (guestEl) guestEl.textContent = guests;
    range.setAttribute('aria-valuetext', guests + ' guests');
    if (inclEl && opt) inclEl.textContent = opt.dataset.includes || '';
    if (nameEl && opt) nameEl.textContent = opt.textContent.trim();

    // Prices switched off in config: the card names the package instead.
    if (!priceEl) return;

    if (isNaN(price)) {
      priceEl.textContent = 'Price on request';
      totalEl.textContent = '';
      return;
    }

    priceEl.innerHTML = euro(price, true) + ' <small>per person</small>';
    // Same rounding as the server: nearest €5, because it is an estimate.
    totalEl.textContent = 'around ' + euro(Math.round((price * guests) / 5) * 5, false) + ' for ' + guests + ' guests';
  }

  card.addEventListener('change', function (e) {
    if (e.target.name === 'occasion' && e.target.dataset.package) {
      select.value = e.target.dataset.package;
    }
    update();
  });
  range.addEventListener('input', update);

  // "Price this package" buttons further down the page.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-price-package]');
    if (!btn) return;
    select.value = btn.dataset.pricePackage;
    update();
  });

  update();
})();

// Catering full form: only ask for a delivery address when it's needed.
(function () {
  var form = document.querySelector('.catering-form');
  if (!form) return;
  var box = form.querySelector('[data-delivery-address]');
  if (!box) return;

  form.addEventListener('change', function (e) {
    if (e.target.name !== 'fulfilment') return;
    box.hidden = e.target.value !== 'delivery';
    if (!box.hidden) {
      var ta = box.querySelector('textarea');
      if (ta) ta.focus();
    }
  });
})();

// Catering analytics: which occasions and packages people price, how many
// guests they slide to, and whether they go on to ask. No contact details.
(function () {
  function track(name, params) {
    if (window.cilantroAnalytics) window.cilantroAnalytics.track(name, params);
  }

  var card = document.querySelector('[data-price-card]');
  if (card) {
    card.addEventListener('change', function (e) {
      var t = e.target;
      if (t.name === 'occasion') track('catering_occasion_select', { label: t.value });
      if (t.name === 'package')  track('catering_package_select',  { label: t.value });
      // 'change' fires once when the slider is let go, not on every step.
      if (t.name === 'guests')   track('catering_guests_set', { guests: parseInt(t.value, 10) || 0 });
      // Only that a location was given — never what it says, which could be an address.
      if (t.name === 'location' && t.value.trim()) track('catering_location_entered', {});
    });

    var contact = card.querySelector('#pc-contact');
    if (contact) {
      contact.addEventListener('focus', function once() {
        track('catering_price_card_start', {});
        contact.removeEventListener('focus', once);
      });
    }
  }

  var full = document.querySelector('.catering-form');
  if (full) {
    var started = false;
    full.addEventListener('focusin', function () {
      if (started) return;
      started = true;
      track('catering_form_start', {});
    });
  }

  if (card || full) {
    var errors = Array.prototype.map.call(
      document.querySelectorAll('.field-error[id]'),
      function (el) { return el.id.replace(/-error$/, ''); }
    );
    if (errors.length) track('catering_form_error', { fields: errors.join(',') });
  }

  var done = document.querySelector('[data-track-view="catering_enquiry_submitted"]');
  if (done) {
    track('catering_enquiry_submitted', {
      form: done.dataset.source || '',
      guests: parseInt(done.dataset.guests, 10) || 0,
      package: done.dataset.package || '',
      occasion: done.dataset.occasion || ''
    });
  }
})();
