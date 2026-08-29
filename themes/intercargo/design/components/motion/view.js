(function () {
  'use strict';

  if (!window.gsap || !window.ScrollTrigger) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var gsap = window.gsap;
  var ScrollTrigger = window.ScrollTrigger;
  gsap.registerPlugin(ScrollTrigger);

  function reveal(targets, trigger, options) {
    if (!targets || !targets.length) return;
    gsap.from(targets, Object.assign({
      autoAlpha: 0,
      y: 22,
      duration: 0.72,
      ease: 'power3.out',
      stagger: 0.065,
      clearProps: 'opacity,visibility,transform',
      scrollTrigger: {
        trigger: trigger || targets[0],
        start: 'top 88%',
        once: true
      }
    }, options || {}));
  }

  document.querySelectorAll('.intercargo-native').forEach(function (section) {
    var title = section.querySelector('.section-title, .hero-title');
    if (title) reveal([title], title, { y: 18, duration: 0.82 });

    var cards = section.querySelectorAll('.service-card, .reason-card');
    if (cards.length) reveal(Array.prototype.slice.call(cards), cards[0], { y: 28, stagger: 0.075 });

    var rows = section.querySelectorAll('.location-row, .enquiry-step, .guide-list li, .wp-block-accordion-item');
    if (rows.length) reveal(Array.prototype.slice.call(rows), rows[0], { y: 14, duration: 0.58, stagger: 0.045 });

    var custom = section.querySelectorAll('.ic-motion-reveal');
    if (custom.length) reveal(Array.prototype.slice.call(custom), custom[0]);
  });

  document.querySelectorAll('.intercargo-native-faq .wp-block-accordion-item').forEach(function (item) {
    var panel = item.querySelector('.wp-block-accordion-panel');
    if (!panel) return;
    var observer = new MutationObserver(function () {
      if (!item.classList.contains('is-open')) return;
      var content = panel.children.length ? Array.prototype.slice.call(panel.children) : [panel];
      gsap.fromTo(content, { autoAlpha: 0, y: -6 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.34,
        ease: 'power2.out',
        clearProps: 'opacity,visibility,transform'
      });
    });
    observer.observe(item, { attributes: true, attributeFilter: ['class'] });
  });

  window.addEventListener('load', function () { ScrollTrigger.refresh(); }, { once: true });
})();
