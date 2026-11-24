document.addEventListener('DOMContentLoaded', function () {
  var root = document.documentElement;
  var systemReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

  function motionReduced() {
    return (systemReduce && systemReduce.matches) || root.getAttribute('data-motion') === 'reduced';
  }

  /* ── Apparitions au défilement ─────────────────────────────── */
  var sections = Array.prototype.slice.call(document.querySelectorAll('.home-reveal'));

  function revealAll() {
    sections.forEach(function (section) {
      section.classList.add('is-visible');
    });
  }

  if (sections.length) {
    if (motionReduced() || !('IntersectionObserver' in window)) {
      revealAll();
    } else {
      root.classList.add('reveal-ready');

      var pendingSections = sections.slice();
      var observer;

      var reveal = function (section) {
        if (section.classList.contains('is-visible')) return;
        section.classList.add('is-visible');
        startCounters(section);
        observer.unobserve(section);
        pendingSections = pendingSections.filter(function (item) {
          return item !== section;
        });
        if (!pendingSections.length) window.removeEventListener('scroll', catchUp);
      };

      // Un saut de défilement (ancre, touche Fin) peut franchir une section sans
      // qu’elle croise jamais la fenêtre : tout ce qui est déjà dépassé est révélé.
      var catchUp = function () {
        pendingSections.slice().forEach(function (section) {
          if (section.getBoundingClientRect().bottom <= 0) reveal(section);
        });
      };

      observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) reveal(entry.target);
        });
      }, {
        threshold: 0.12,
        rootMargin: '0px 0px -10% 0px'
      });

      window.requestAnimationFrame(function () {
        sections.forEach(function (section) {
          observer.observe(section);
        });
      });
      window.addEventListener('scroll', catchUp, { passive: true });
    }
  }

  /* ── Pourcentages animés de la synthèse ────────────────────── */
  function startCounters(container) {
    var counters = container.querySelectorAll('[data-home-count]');
    if (!counters.length) return;

    Array.prototype.forEach.call(counters, function (counter, index) {
      var target = parseInt(counter.getAttribute('data-home-count'), 10);
      var suffix = counter.textContent.replace(/^\s*\d+/, '');
      var delay = 520 + index * 120;
      var duration = 1100;
      var start = null;

      if (isNaN(target)) return;
      counter.textContent = '0' + suffix;

      function step(now) {
        if (start === null) start = now;
        var progress = Math.min(1, Math.max(0, (now - start - delay) / duration));
        var eased = 1 - Math.pow(1 - progress, 3);
        counter.textContent = Math.round(target * eased) + suffix;
        if (progress < 1) window.requestAnimationFrame(step);
      }

      window.requestAnimationFrame(step);
    });
  }

  /* ── Profondeur de la roue : inclinaison au pointeur et défilement ── */
  var hero = document.querySelector('.home-hero');
  var tilt = document.querySelector('.home-wheel-tilt');
  if (!hero || !tilt || motionReduced()) return;

  var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var maxTilt = 7;
  var pending = false;
  var pointer = null;

  function render() {
    pending = false;
    if (motionReduced()) {
      tilt.style.removeProperty('--tilt-x');
      tilt.style.removeProperty('--tilt-y');
      tilt.style.removeProperty('--wheel-shift');
      return;
    }

    var shift = Math.min(window.scrollY || 0, 700) * 0.07;
    tilt.style.setProperty('--wheel-shift', shift.toFixed(1) + 'px');

    if (pointer) {
      var rect = tilt.getBoundingClientRect();
      var dx = (pointer.x - (rect.left + rect.width / 2)) / (rect.width / 2);
      var dy = (pointer.y - (rect.top + rect.height / 2)) / (rect.height / 2);
      dx = Math.max(-1, Math.min(1, dx));
      dy = Math.max(-1, Math.min(1, dy));
      tilt.style.setProperty('--tilt-x', (-dy * maxTilt).toFixed(2) + 'deg');
      tilt.style.setProperty('--tilt-y', (dx * maxTilt).toFixed(2) + 'deg');
    } else {
      tilt.style.setProperty('--tilt-x', '0deg');
      tilt.style.setProperty('--tilt-y', '0deg');
    }
  }

  function schedule() {
    if (pending) return;
    pending = true;
    window.requestAnimationFrame(render);
  }

  if (finePointer) {
    hero.addEventListener('pointermove', function (event) {
      pointer = { x: event.clientX, y: event.clientY };
      schedule();
    });
    hero.addEventListener('pointerleave', function () {
      pointer = null;
      schedule();
    });
  }

  window.addEventListener('scroll', schedule, { passive: true });
});
