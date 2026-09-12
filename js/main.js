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

// Map: don't load Google until the visitor asks for it
(function () {
  var loader = document.querySelector('.map-load');
  if (!loader) return;

  loader.addEventListener('click', function () {
    var frame = document.createElement('iframe');
    frame.src = loader.dataset.embed;
    frame.title = 'Map showing ' + loader.dataset.place;
    frame.loading = 'lazy';
    frame.referrerPolicy = 'no-referrer-when-downgrade';
    frame.allowFullscreen = true;
    frame.className = 'map-embed';
    loader.parentNode.replaceChild(frame, loader);
  });
})();
