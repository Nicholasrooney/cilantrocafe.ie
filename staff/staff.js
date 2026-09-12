// Erase button: make the destructive one deliberate.
(function () {
  var form = document.querySelector('form.erase');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    var field = form.querySelector('#confirm');
    if (!field || field.value.trim() !== 'ERASE') {
      e.preventDefault();
      field.focus();
      field.setAttribute('aria-invalid', 'true');
    }
  });
})();

// Keep the day view honest on a tablet left open overnight: if the page has
// been sitting on "today" past midnight, reload so staff aren't reading
// yesterday's bookings.
(function () {
  var meta = document.querySelector('.dayhead');
  if (!meta || location.search.indexOf('date=') !== -1) return;
  var loadedOn = new Date().toDateString();
  setInterval(function () {
    if (new Date().toDateString() !== loadedOn) location.reload();
  }, 60000);
})();

// Anything marked data-confirm asks first. Cancelling is reversible — the
// booking can be restored — so one plain confirm is the right weight: enough
// to stop a mis-tap, not so much that staff avoid using it.
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!window.confirm(btn.dataset.confirm)) e.preventDefault();
  });
})();
