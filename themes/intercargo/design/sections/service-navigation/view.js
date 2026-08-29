(function () {
  'use strict';

  var ROOT_SELECTOR = '.intercargo-native-service-navigation';
  var LINK_SELECTOR = '.service-secnav-link[href^="#"]';
  var EDGE_PADDING = 18;
  var ACTIVE_LINE_RATIO = 0.22;
  var ACTIVE_LINE_MIN = 64;
  var ACTIVE_LINE_MAX = 160;
  var HORIZONTAL_IDLE_MS = 110;
  var SCROLL_SETTLE_TIMEOUT = 1400;

  function prefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function parsePixels(value) {
    var number = parseFloat(value || '0');
    return Number.isFinite(number) ? number : 0;
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function stickyTop(root) {
    return Math.max(0, parsePixels(window.getComputedStyle(root).top));
  }

  function stickyOffset(root) {
    return stickyTop(root) + root.getBoundingClientRect().height;
  }

  function horizontalStrip(root) {
    return root.querySelector('.service-secnav-links');
  }

  /*
   * Move the tab strip only as far as necessary. Centering every active tab is
   * visually noisy on long menus and makes the strip appear to chase the page.
   */
  function revealTab(root, link, behavior) {
    var strip = horizontalStrip(root);
    if (!strip || !link) return;

    var stripRect = strip.getBoundingClientRect();
    var linkRect = link.getBoundingClientRect();
    var leftEdge = stripRect.left + EDGE_PADDING;
    var rightEdge = stripRect.right - EDGE_PADDING;
    var desired = null;

    if (linkRect.left < leftEdge) {
      desired = strip.scrollLeft - (leftEdge - linkRect.left);
    } else if (linkRect.right > rightEdge) {
      desired = strip.scrollLeft + (linkRect.right - rightEdge);
    }

    if (desired === null) return;

    var max = Math.max(0, strip.scrollWidth - strip.clientWidth);
    desired = clamp(desired, 0, max);

    if (typeof strip.scrollTo === 'function') {
      strip.scrollTo({ left: desired, behavior: behavior || 'auto' });
    } else {
      strip.scrollLeft = desired;
    }
  }

  function documentOrder(a, b) {
    if (a.target === b.target) return 0;
    var relation = a.target.compareDocumentPosition(b.target);
    if (relation & Node.DOCUMENT_POSITION_FOLLOWING) return -1;
    if (relation & Node.DOCUMENT_POSITION_PRECEDING) return 1;
    return 0;
  }

  /* `behavior:auto` still inherits html{scroll-behavior:smooth}. This helper is
   * deliberately instant and is used only for final alignment/cancellation. */
  function instantWindowScroll(top) {
    var html = document.documentElement;
    var previousInline = html.style.scrollBehavior;
    html.style.scrollBehavior = 'auto';
    window.scrollTo(0, Math.max(0, top));
    // Keep the override through this paint so the browser cannot re-read the
    // global smooth rule before applying the correction.
    window.requestAnimationFrame(function () {
      html.style.scrollBehavior = previousInline;
    });
  }

  function init(root) {
    if (!root || root.dataset.serviceSecnavReady === '1') return;
    root.dataset.serviceSecnavReady = '1';

    var links = Array.prototype.slice.call(root.querySelectorAll(LINK_SELECTOR));
    var pairs = links.map(function (link) {
      var href = link.getAttribute('href') || '';
      if (!/^#[A-Za-z][A-Za-z0-9_-]*$/.test(href)) return null;
      var target = document.getElementById(href.slice(1));
      return target ? { link: link, target: target, href: href } : null;
    }).filter(Boolean);

    if (!pairs.length) return;

    var sectionPairs = pairs.slice().sort(documentOrder);
    var activeLink = null;
    var ticking = false;
    var horizontalTimer = 0;
    var reducedMotion = prefersReducedMotion();
    var programmaticPair = null;
    var programmaticToken = 0;
    var programmaticStartedAt = 0;

    root.classList.add('has-service-secnav-scrollspy');

    function setActive(link) {
      if (!link || activeLink === link) return;
      activeLink = link;
      links.forEach(function (candidate) {
        var active = candidate === link;
        candidate.classList.toggle('is-active', active);
        if (active) candidate.setAttribute('aria-current', 'location');
        else candidate.removeAttribute('aria-current');
      });
    }

    function scheduleActiveTabReveal(immediate) {
      if (!activeLink) return;
      if (horizontalTimer) window.clearTimeout(horizontalTimer);

      if (immediate) {
        revealTab(root, activeLink, 'auto');
        return;
      }

      // Wait until vertical scrolling settles. This prevents a long click-scroll
      // from launching a new horizontal smooth animation for every section it
      // passes on the way to the destination.
      horizontalTimer = window.setTimeout(function () {
        horizontalTimer = 0;
        revealTab(root, activeLink, reducedMotion ? 'auto' : 'smooth');
      }, HORIZONTAL_IDLE_MS);
    }

    function atDocumentBottom() {
      var doc = document.documentElement;
      return window.pageYOffset + window.innerHeight >= doc.scrollHeight - 2;
    }

    function activePairForViewport() {
      if (atDocumentBottom()) return sectionPairs[sectionPairs.length - 1];

      // Activate a section shortly after it enters the usable reading viewport,
      // not only after its heading has disappeared behind the sticky nav. This
      // matches what a person perceives as the section currently on screen.
      var contentTop = stickyOffset(root);
      var available = Math.max(1, window.innerHeight - contentTop);
      var lineGap = clamp(available * ACTIVE_LINE_RATIO, ACTIVE_LINE_MIN, ACTIVE_LINE_MAX);
      var line = contentTop + lineGap;
      var candidate = sectionPairs[0];

      for (var i = 0; i < sectionPairs.length; i += 1) {
        if (sectionPairs[i].target.getBoundingClientRect().top <= line) {
          candidate = sectionPairs[i];
        } else {
          break;
        }
      }
      return candidate;
    }

    function updateFromViewport() {
      if (programmaticPair) return;
      var pair = activePairForViewport();
      if (!pair) return;
      setActive(pair.link);
      scheduleActiveTabReveal(false);
    }

    function requestViewportUpdate() {
      if (ticking || programmaticPair) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        updateFromViewport();
      });
    }

    function exactTargetTop(target) {
      return Math.max(0, window.pageYOffset + target.getBoundingClientRect().top - stickyOffset(root));
    }

    function stopProgrammaticScroll(updateFromCurrentPosition) {
      if (!programmaticPair) return;
      programmaticToken += 1;
      programmaticPair = null;
      // Cancel the browser's compositor smooth-scroll at its current position.
      instantWindowScroll(window.pageYOffset);
      if (updateFromCurrentPosition) {
        window.requestAnimationFrame(function () {
          updateFromViewport();
          scheduleActiveTabReveal(true);
        });
      }
    }

    function finishProgrammaticScroll(token) {
      if (!programmaticPair || token !== programmaticToken) return;
      var pair = programmaticPair;
      var finalTop = exactTargetTop(pair.target);
      programmaticPair = null;

      // Native smooth scrolling can land a fraction of a pixel off after fonts,
      // sticky geometry or responsive content settle. Correct once, instantly.
      if (Math.abs(window.pageYOffset - finalTop) > 0.75) {
        instantWindowScroll(finalTop);
      }
      setActive(pair.link);
      scheduleActiveTabReveal(false);
    }

    function watchProgrammaticScroll(token) {
      var pair = programmaticPair;
      if (!pair || token !== programmaticToken) return;
      var delta = Math.abs(window.pageYOffset - exactTargetTop(pair.target));
      var elapsed = Date.now() - programmaticStartedAt;
      if (delta <= 0.75 || elapsed >= SCROLL_SETTLE_TIMEOUT) {
        finishProgrammaticScroll(token);
        return;
      }
      window.requestAnimationFrame(function () { watchProgrammaticScroll(token); });
    }

    function scrollToPair(pair, updateHistory) {
      if (!pair) return;

      // Cancel any older navigation before starting a new one.
      if (programmaticPair) stopProgrammaticScroll(false);

      programmaticToken += 1;
      var token = programmaticToken;
      programmaticPair = pair;
      programmaticStartedAt = Date.now();
      setActive(pair.link);
      scheduleActiveTabReveal(true);

      if (updateHistory && window.history && typeof window.history.pushState === 'function') {
        window.history.pushState(null, '', pair.href);
      }

      var top = exactTargetTop(pair.target);
      if (reducedMotion) {
        instantWindowScroll(top);
        window.requestAnimationFrame(function () { finishProgrammaticScroll(token); });
        return;
      }

      // Use the browser's native compositor animation. Scrollspy is locked to
      // the intended destination until it finishes, so intermediate sections do
      // not flash active while the page travels past them.
      window.scrollTo({ top: top, behavior: 'smooth' });
      watchProgrammaticScroll(token);
    }

    function pairForHash(hash) {
      return pairs.find(function (pair) { return pair.href === hash; }) || null;
    }

    pairs.forEach(function (pair) {
      pair.link.addEventListener('click', function (event) {
        event.preventDefault();
        scrollToPair(pair, true);
      });
    });

    window.addEventListener('scroll', requestViewportUpdate, { passive: true });
    window.addEventListener('resize', function () {
      stopProgrammaticScroll(false);
      requestViewportUpdate();
    }, { passive: true });

    // A person scrolling/touching while a click-scroll is in flight always wins.
    ['wheel', 'touchstart'].forEach(function (eventName) {
      window.addEventListener(eventName, function () {
        stopProgrammaticScroll(true);
      }, { passive: true });
    });
    window.addEventListener('keydown', function (event) {
      if (!programmaticPair) return;
      if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' ', 'Spacebar'].indexOf(event.key) !== -1) {
        stopProgrammaticScroll(true);
      }
    });

    // scrollend is a useful fast-path on modern browsers; the RAF watcher above
    // remains the cross-browser fallback.
    if ('onscrollend' in window) {
      window.addEventListener('scrollend', function () {
        if (programmaticPair) finishProgrammaticScroll(programmaticToken);
      }, { passive: true });
    }

    window.addEventListener('hashchange', function () {
      var pair = pairForHash(window.location.hash);
      if (pair) scrollToPair(pair, false);
    });

    // Initial state is deliberately animation-free. Correct a deep link after
    // layout settles so the section begins exactly below the sticky navigation.
    var initialPair = pairForHash(window.location.hash);
    if (initialPair) {
      setActive(initialPair.link);
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          instantWindowScroll(exactTargetTop(initialPair.target));
          scheduleActiveTabReveal(true);
        });
      });
    } else {
      var initialActive = activePairForViewport();
      if (initialActive) setActive(initialActive.link);
      scheduleActiveTabReveal(true);
    }
  }

  function boot() {
    document.querySelectorAll(ROOT_SELECTOR).forEach(init);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
