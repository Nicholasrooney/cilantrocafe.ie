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
