/* Préférences d’affichage du site : mode (clair, chaud, sombre), taille du texte, espacement et
   animations. Chargé de façon synchrone dans <head> pour appliquer les réglages avant le premier
   rendu ; les effets eux-mêmes sont décrits dans css/display-preferences.css. */
(function () {
  'use strict';

  var root = document.documentElement;

  var SETTINGS = {
    mode: { key: 'learningDesignerTheme', values: ['light', 'warm', 'dark'], fallback: 'light' },
    textSize: { key: 'learningDesignerTextSize', values: ['small', 'default', 'large', 'larger', 'largest'], fallback: 'default' },
    spacing: { key: 'learningDesignerSpacing', values: ['default', 'relaxed', 'wide'], fallback: 'default' },
    motion: { key: 'learningDesignerMotion', values: ['default', 'reduced'], fallback: 'default' }
  };
  var TEXT_SIZE_PERCENT = { small: 90, default: 100, large: 110, larger: 120, largest: 130 };
  var NAMES = Object.keys(SETTINGS);

  function read(name) {
    var setting = SETTINGS[name];
    var value = '';
    try {
      value = localStorage.getItem(setting.key) || '';
    } catch (error) {
      value = '';
    }
    return setting.values.indexOf(value) === -1 ? setting.fallback : value;
  }

  function write(name, value) {
    var setting = SETTINGS[name];
    try {
      if (value === setting.fallback) localStorage.removeItem(setting.key);
      else localStorage.setItem(setting.key, value);
    } catch (error) {
    }
  }

  function setAttribute(name, value) {
    if (value) root.setAttribute(name, value);
    else root.removeAttribute(name);
  }

  var state = {};

  function apply() {
    setAttribute('data-theme', state.mode === 'dark' ? 'dark' : '');
    setAttribute('data-tone', state.mode === 'warm' ? 'warm' : '');
    setAttribute('data-text-size', state.textSize === 'default' ? '' : state.textSize);
    setAttribute('data-text-spacing', state.spacing === 'default' ? '' : state.spacing);
    setAttribute('data-motion', state.motion === 'reduced' ? 'reduced' : '');
  }

  function load() {
    NAMES.forEach(function (name) {
      state[name] = read(name);
    });
    apply();
  }

  load();

  var syncControls = function () {};

  function set(name, value) {
    var setting = SETTINGS[name];
    if (!setting || setting.values.indexOf(value) === -1 || state[name] === value) return;
    state[name] = value;
    write(name, value);
    apply();
    syncControls();
  }

  function reset() {
    NAMES.forEach(function (name) {
      state[name] = SETTINGS[name].fallback;
      write(name, state[name]);
    });
    apply();
    syncControls();
  }

  window.learningDesignerDisplay = {
    get: function (name) { return state[name]; },
    set: set,
    reset: reset
  };

  // Un réglage modifié dans un autre onglet s’applique aussi à celui-ci.
  window.addEventListener('storage', function (event) {
    var known = NAMES.some(function (name) { return SETTINGS[name].key === event.key; });
    if (event.key !== null && !known) return;
    load();
    syncControls();
  });

  document.addEventListener('DOMContentLoaded', function () {
    var modeOptions = Array.prototype.slice.call(document.querySelectorAll('[data-display-mode]'));
    var modeToggle = document.querySelector('.mode-toggle');
    var modeFace = document.querySelector('.mode-toggle-face');
    var modes = modeOptions.map(function (option) { return option.getAttribute('data-display-mode'); });
    var systemReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var spacingOptions = document.querySelectorAll('[data-display-spacing]');
    var textSizeValue = document.querySelector('[data-display-text-size-value]');
    var textSteps = document.querySelectorAll('[data-display-text-step]');
    var motionSwitch = document.querySelector('[data-display-motion]');
    var resetButton = document.querySelector('[data-display-reset]');
    var sizes = SETTINGS.textSize.values;

    function pressed(options, attribute, value) {
      options.forEach(function (option) {
        option.setAttribute('aria-pressed', option.getAttribute(attribute) === value ? 'true' : 'false');
      });
    }

    function percentLabel(value) {
      return root.lang === 'en' ? value + '%' : value + ' %';
    }

    syncControls = function () {
      var index = sizes.indexOf(state.textSize);
      var label = percentLabel(TEXT_SIZE_PERCENT[state.textSize]);
      modeOptions.forEach(function (option) {
        var checked = option.getAttribute('data-display-mode') === state.mode;
        option.setAttribute('aria-checked', checked ? 'true' : 'false');
        option.tabIndex = checked ? 0 : -1;
      });
      pressed(spacingOptions, 'data-display-spacing', state.spacing);
      if (textSizeValue) textSizeValue.textContent = label;
      textSteps.forEach(function (button) {
        var next = index + Number(button.getAttribute('data-display-text-step'));
        button.disabled = next < 0 || next >= sizes.length;
      });
      if (motionSwitch) motionSwitch.setAttribute('aria-checked', state.motion === 'reduced' ? 'true' : 'false');
      if (resetButton) {
        resetButton.hidden = NAMES.every(function (name) {
          return state[name] === SETTINGS[name].fallback;
        });
      }
    };

    // La page change de thème en fondu, sur la même durée que le trajet du bouton : sans cela,
    // les couleurs basculent d’un coup alors que le sélecteur n’a pas encore fini sa course.
    var MODE_DURATION = 640;
    var switchToken = 0;

    // Le bouton s’étire dans le sens du trajet, puis reprend sa forme ronde.
    function squash(distance) {
      if (!modeFace || !modeFace.animate || !motionAllowed()) return;
      var stretch = 1 + 0.16 * distance;
      modeFace.animate([
        { transform: 'scale(1, 1)' },
        { transform: 'scale(' + stretch + ', ' + (2 - stretch) + ')', offset: 0.45 },
        { transform: 'scale(0.96, 1.04)', offset: 0.8 },
        { transform: 'scale(1, 1)' }
      ], { duration: MODE_DURATION, easing: 'ease-in-out' });
    }

    function motionAllowed() {
      return !systemReducedMotion.matches && state.motion !== 'reduced';
    }

    function chooseMode(mode) {
      var from = modes.indexOf(state.mode);
      var to = modes.indexOf(mode);
      if (to < 0 || to === from) return;
      var distance = Math.abs(to - from);
      if (!document.startViewTransition || !motionAllowed()) {
        set('mode', mode);
        squash(distance);
        return;
      }
      var token = ++switchToken;
      root.classList.add('mode-switching');
      var transition = document.startViewTransition(function () {
        set('mode', mode);
        squash(distance);
      });
      transition.finished.finally(function () {
        if (token === switchToken) root.classList.remove('mode-switching');
      });
    }

    modeOptions.forEach(function (option) {
      option.addEventListener('click', function () {
        chooseMode(option.getAttribute('data-display-mode'));
      });
    });

    // Les flèches passent d’un mode à l’autre, comme dans tout groupe de boutons radio.
    if (modeToggle) {
      modeToggle.addEventListener('keydown', function (event) {
        var index = modes.indexOf(state.mode);
        var next = { ArrowRight: index + 1, ArrowDown: index + 1, ArrowLeft: index - 1, ArrowUp: index - 1, Home: 0, End: modes.length - 1 }[event.key];
        if (next === undefined) return;
        event.preventDefault();
        next = (next + modes.length) % modes.length;
        chooseMode(modes[next]);
        modeOptions[next].focus();
      });
    }
    spacingOptions.forEach(function (option) {
      option.addEventListener('click', function () {
        set('spacing', option.getAttribute('data-display-spacing'));
      });
    });
    textSteps.forEach(function (button) {
      button.addEventListener('click', function () {
        var next = sizes[sizes.indexOf(state.textSize) + Number(button.getAttribute('data-display-text-step'))];
        if (next) set('textSize', next);
      });
    });
    if (motionSwitch) {
      motionSwitch.addEventListener('click', function () {
        set('motion', state.motion === 'reduced' ? 'default' : 'reduced');
      });
    }
    if (resetButton) {
      resetButton.addEventListener('click', function () {
        var returnFocus = document.activeElement === resetButton;
        reset();
        // Le bouton disparaît une fois les réglages rétablis : le focus revient au premier mode.
        if (returnFocus && modeOptions[0]) modeOptions[0].focus();
      });
    }

    new MutationObserver(syncControls).observe(root, { attributes: true, attributeFilter: ['lang'] });
    syncControls();
  });
})();
