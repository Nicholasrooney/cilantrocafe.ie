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

// Map: never load Google until either the visitor taps it, or they have said
// the map is welcome.
(function () {
  var loader = document.querySelector('.map-load');
  if (!loader) return;

  function loadMap() {
    if (!loader.parentNode) return;
    var frame = document.createElement('iframe');
    frame.src = loader.dataset.embed;
    frame.title = 'Map showing ' + loader.dataset.place;
    frame.loading = 'lazy';
    frame.referrerPolicy = 'no-referrer-when-downgrade';
    frame.allowFullscreen = true;
    frame.className = 'map-embed';
    loader.parentNode.replaceChild(frame, loader);
  }

  loader.addEventListener('click', loadMap);
  document.addEventListener('cilantro:allow-map', loadMap);

  if (cookieChoice() === 'all') loadMap();
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

    if (btn.dataset.cookie === 'all') {
      document.dispatchEvent(new CustomEvent('cilantro:allow-map'));
    }
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
        if (!data || !data.slots) return;

        var chosen = time.value;
        Array.prototype.forEach.call(time.options, function (opt) {
          if (!opt.value) return;
          var free = data.slots[opt.value];
          opt.disabled = free === false;
          opt.textContent = opt.value + (free === false ? ' — fully booked' : '');
        });

        // If their chosen time just became unavailable, clear it rather than
        // submitting something that will be rejected.
        if (chosen && time.selectedOptions[0] && time.selectedOptions[0].disabled) {
          time.value = '';
        }
      })
      .catch(function () { /* offline or aborted: leave the form as it is */ });
  }

  date.addEventListener('change', refresh);
  if (guests) guests.addEventListener('change', refresh);
})();
