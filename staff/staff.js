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
