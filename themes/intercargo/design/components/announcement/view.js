(function () {
  'use strict';

  var bar = document.querySelector('.announce');
  var clocks = Array.prototype.slice.call(document.querySelectorAll('.announce-clock[data-tz]'));
  if (!bar || !clocks.length || !window.Intl || !window.Intl.DateTimeFormat) return;

  function publishHeight() {
    document.documentElement.style.setProperty('--intercargo-announcement-height', bar.offsetHeight + 'px');
  }

  var formatters = {};

  function formatter(timeZone) {
    if (!formatters[timeZone]) {
      try {
        formatters[timeZone] = new Intl.DateTimeFormat('en-AU', {
          timeZone: timeZone,
          hour: 'numeric',
          minute: '2-digit',
          hour12: true
        });
      } catch (error) {
        formatters[timeZone] = null;
      }
    }
    return formatters[timeZone];
  }

  function normalize(value) {
    return String(value || '')
      .replace(/[\u00a0\u202f]/g, ' ')
      .replace(/\bAM\b/g, 'am')
      .replace(/\bPM\b/g, 'pm');
  }

  function update() {
    var now = new Date();
    clocks.forEach(function (node) {
      var tz = node.getAttribute('data-tz') || '';
      var fmt = formatter(tz);
      if (!fmt) return;
      node.textContent = normalize(fmt.format(now));
      node.setAttribute('datetime', now.toISOString());
    });
  }

  publishHeight();
  update();
  window.setInterval(update, 30000);
  window.addEventListener('resize', publishHeight, { passive: true });
})();
