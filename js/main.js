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

// Cookie banner
(function () {
  var banner = document.getElementById('cookie-banner');
  if (!banner) return;

  if (cookieChoice()) return;   // already answered
  banner.hidden = false;

  banner.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cookie]');
    if (!btn) return;

    cookieChoice(btn.dataset.cookie);
    banner.hidden = true;

    document.dispatchEvent(new CustomEvent(
      btn.dataset.cookie === 'all' ? 'cilantro:allow-map' : 'cilantro:deny-map'
    ));
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
