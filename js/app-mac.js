/* App Mac : animations de la page de présentation.
   Chargé dans le <head> pour poser la classe mac-motion avant le premier affichage :
   sans JavaScript ou avec « Réduire les animations », la page reste entièrement statique et visible. */
(function () {
  'use strict';

  var root = document.documentElement;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || !('IntersectionObserver' in window)) return;

  root.classList.add('mac-motion');

  // Éléments qui apparaissent seuls, et groupes dont les enfants apparaissent l’un après l’autre.
  var REVEAL = '.mac-section-title, .mac-builtin-title, .mac-builtin-note, .mac-showcase-text, .mac-visual, .mac-privacy, .mac-final';
  var STAGGER = '.mac-stats, .mac-grid, .mac-steps, .mac-privacy-points, .mac-legend, .mac-people, .mac-providers, '
    + '.mac-strip, .mac-timeline-bar, .mac-board, .mac-inspector, .mac-sidebar';

  function isEnglish() {
    return root.lang === 'en';
  }

  function localized(el) {
    return (isEnglish() ? el.dataset.siteI18nEn : el.dataset.siteI18nFr) || el.textContent;
  }

  function wait(ms) {
    return new Promise(function (resolve) { window.setTimeout(resolve, ms); });
  }

  // Une boucle qui ne tourne que lorsque son élément est à l’écran et l’onglet visible.
  function whileVisible(el, loop) {
    var visible = false;
    var running = false;
    function canRun() {
      return visible && !document.hidden;
    }
    function start() {
      if (running || !canRun()) return;
      running = true;
      loop(canRun).then(function () {
        running = false;
        start();
      });
    }
    new IntersectionObserver(function (entries) {
      visible = entries[0].isIntersecting;
      start();
    }, { threshold: 0.35 }).observe(el);
    document.addEventListener('visibilitychange', start);
  }

  function typeInto(el, text, speed, canRun) {
    var i = 0;
    el.classList.add('is-typing');
    return new Promise(function (resolve) {
      (function step() {
        if (!canRun() || i > text.length) {
          el.textContent = text;
          resolve();
          return;
        }
        el.textContent = text.slice(0, i);
        i += 1;
        window.setTimeout(step, speed + Math.random() * speed * 0.8);
      })();
    }).then(function () {
      el.classList.remove('is-typing');
    });
  }

  function formatNumber(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function countUp(el) {
    var target = parseInt(el.textContent.replace(/\D/g, ''), 10);
    if (!target) return;
    var duration = 1400;
    var start = null;
    el.textContent = '0';
    function frame(now) {
      if (start === null) start = now;
      var progress = Math.min((now - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = formatNumber(Math.round(target * eased));
      if (progress < 1) window.requestAnimationFrame(frame);
    }
    window.requestAnimationFrame(frame);
  }

  // La fenêtre est inclinée sous le titre et se redresse à mesure qu’elle monte dans l’écran.
  function tiltWindow(win) {
    var pending = false;
    function update() {
      pending = false;
      var rect = win.getBoundingClientRect();
      var vh = window.innerHeight || 1;
      var progress = (rect.top - vh * 0.12) / (vh * 0.62);
      win.style.setProperty('--mac-tilt', Math.max(0, Math.min(1, progress)).toFixed(3));
    }
    function request() {
      if (pending) return;
      pending = true;
      window.requestAnimationFrame(update);
    }
    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    update();
  }

  // Le champ de l’assistant tape des demandes, les « envoie », puis passe à la suivante.
  function composerDemo(composer) {
    var input = composer.querySelector('span');
    if (!input) return;
    var placeholder = input.textContent;
    var prompts = [
      'Ajoute une évaluation formative à la séance 4',
      'Propose une variante pour les élèves en difficulté',
      'Raccourcis la séance 2 à 45 minutes'
    ];
    var index = 0;
    whileVisible(composer, function (canRun) {
      var prompt = prompts[index % prompts.length];
      index += 1;
      return wait(900)
        .then(function () {
          composer.classList.add('has-text');
          return typeInto(input, prompt, 38, canRun);
        })
        .then(function () { return wait(1300); })
        .then(function () {
          composer.classList.add('is-sent');
          return wait(450);
        })
        .then(function () {
          composer.classList.remove('is-sent', 'has-text');
          input.textContent = placeholder;
          return wait(2200);
        });
    });
  }

  // Une collègue réécrit la description de l’activité partagée.
  function remoteTypingDemo(field) {
    whileVisible(field, function (canRun) {
      return typeInto(field, localized(field), 26, canRun).then(function () { return wait(6500); });
    });
  }

  // L’aide IA essaie tour à tour reformuler, raccourcir et préciser, puis revient au texte d’origine.
  function fieldHelpDemo(help) {
    var text = help.querySelector('.mac-field-help-text');
    var pills = Array.prototype.slice.call(help.querySelectorAll('.mac-pill[data-mac-result-fr]'));
    if (!text || !pills.length) return;
    var step = 0;
    whileVisible(help, function () {
      var pill = pills[step % (pills.length + 1)];
      step += 1;
      pills.forEach(function (p) { p.classList.toggle('is-active', p === pill); });
      text.classList.add('is-thinking');
      return wait(pill ? 1100 : 600).then(function () {
        text.textContent = pill
          ? (isEnglish() ? pill.dataset.macResultEn : pill.dataset.macResultFr)
          : localized(text);
        text.classList.remove('is-thinking');
        return wait(pill ? 2800 : 2400);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll(STAGGER), function (group) {
      Array.prototype.forEach.call(group.children, function (child, i) {
        child.style.setProperty('--mac-i', i);
      });
    });

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        el.classList.add('is-visible');
        observer.unobserve(el);
        if (el.classList.contains('mac-stats')) {
          Array.prototype.forEach.call(el.querySelectorAll('dd'), countUp);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    Array.prototype.forEach.call(
      document.querySelectorAll(REVEAL + ', .mac-stats, .mac-grid, .mac-steps, .mac-privacy-points, .mac-window'),
      function (el) { observer.observe(el); }
    );

    var win = document.querySelector('.mac-window');
    if (win) tiltWindow(win);

    var composer = document.querySelector('.mac-composer');
    if (composer) composerDemo(composer);

    var remote = document.querySelector('.mac-act.is-remote .mac-field');
    if (remote) remoteTypingDemo(remote);

    var help = document.querySelector('.mac-field-help');
    if (help) fieldHelpDemo(help);
  });
})();
