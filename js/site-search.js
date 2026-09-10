(function () {
  "use strict";

  var openButton = document.getElementById("site-search-open");
  if (!openButton) return;

  var scriptUrl = new URL(document.currentScript.src, window.location.href);
  var appRootUrl = new URL("../", scriptUrl);
  var pagefindPromises = { fr: null, en: null };
  var previousFocus = null;
  var searchTimer = 0;
  var searchSequence = 0;
  var selectedCategory = "all";
  var nextOffset = 0;
  var categoryFiles = {
    help: ["help.php"], models: ["models.php"], scenarios: ["view.php", "share.php"],
    competencies: ["competencies.php"], prompts: ["prompts.php"],
    references: ["bloom.php", "cadre-conversationnel.php", "learning-design.php", "competencies.php"],
    other: ["index.php", "about.php", "licence-reutilisation.php", "mentions-legales.php", "politique-confidentialite.php"]
  };

  var translations = {
    fr: {
      button: "Rechercher sur le site",
      title: "Rechercher",
      close: "Fermer la recherche",
      placeholder: "Aide, modèles, scénarios partagés…",
      filtersLabel: "Filtrer par catégorie",
      filters: { all: "Tout", help: "Aide", models: "Modèles", scenarios: "Scénarios partagés", competencies: "Compétences", prompts: "Prompts", references: "Références", other: "Autres pages" },
      more: "Afficher plus de résultats",
      partial: "Une partie de la recherche est indisponible ; les résultats disponibles sont affichés.",
      invitation: "Saisissez au moins deux caractères pour lancer la recherche.",
      loading: "Chargement de la recherche…",
      searching: "Recherche en cours…",
      unavailable: "La recherche est indisponible pour le moment. L’index Pagefind doit être généré et publié avec le site.",
      empty: "Aucun résultat pour « {query} ».",
      oneResult: "1 résultat pour « {query} »",
      manyResults: "{count} résultats pour « {query} »",
      untitled: "Page sans titre",
      categories: {
        "view.php": "Scénarios partagés",
        "share.php": "Scénarios partagés",
        "index.php": "Accueil",
        "about.php": "À propos",
        "bloom.php": "Taxonomie de Bloom",
        "cadre-conversationnel.php": "Cadre conversationnel",
        "competencies.php": "Compétences",
        "help.php": "Aide",
        "learning-design.php": "Learning design",
        "licence-reutilisation.php": "Licence",
        "mentions-legales.php": "Informations légales",
        "models.php": "Modèles",
        "politique-confidentialite.php": "Confidentialité",
        "prompts.php": "Prompts"
      }
    },
    en: {
      button: "Search the site",
      title: "Search",
      close: "Close search",
      placeholder: "Help, templates, shared scenarios…",
      filtersLabel: "Filter by category",
      filters: { all: "All", help: "Help", models: "Templates", scenarios: "Shared scenarios", competencies: "Competencies", prompts: "Prompts", references: "References", other: "Other pages" },
      more: "Show more results",
      partial: "Some search sources are unavailable; available results are shown.",
      invitation: "Enter at least two characters to start searching.",
      loading: "Loading search…",
      searching: "Searching…",
      unavailable: "Search is temporarily unavailable. The Pagefind index must be generated and published with the site.",
      empty: "No results for “{query}”.",
      oneResult: "1 result for “{query}”",
      manyResults: "{count} results for “{query}”",
      untitled: "Untitled page",
      categories: {
        "view.php": "Shared scenarios",
        "share.php": "Shared scenarios",
        "index.php": "Home",
        "about.php": "About",
        "bloom.php": "Bloom’s Taxonomy",
        "cadre-conversationnel.php": "Conversational Framework",
        "competencies.php": "Competencies",
        "help.php": "Help",
        "learning-design.php": "Learning design",
        "licence-reutilisation.php": "License",
        "mentions-legales.php": "Legal information",
        "models.php": "Templates",
        "politique-confidentialite.php": "Privacy",
        "prompts.php": "Prompts"
      }
    }
  };

  function currentLanguage() {
    return document.documentElement.lang === "en" ? "en" : "fr";
  }

  function copy() {
    return translations[currentLanguage()];
  }

  function interpolate(template, values) {
    return template.replace(/\{(\w+)\}/g, function (_, key) {
      return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : "";
    });
  }

  var overlay = document.createElement("div");
  overlay.className = "site-search-overlay";
  overlay.hidden = true;
  overlay.innerHTML = [
    '<section class="site-search-dialog" role="dialog" aria-modal="true" aria-labelledby="site-search-title">',
    '  <header class="site-search-header">',
    '    <h2 id="site-search-title"></h2>',
    '    <button class="site-search-close" type="button"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>',
    "  </header>",
    '  <div class="site-search-field">',
    '    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>',
    '    <label class="sr-only" for="site-search-input"></label>',
    '    <input id="site-search-input" type="search" maxlength="200" autocomplete="off" spellcheck="false">',
    '    <kbd>Esc</kbd>',
    "  </div>",
    '  <div class="site-search-filters" role="group"></div>',
    '  <p class="site-search-status" role="status" aria-live="polite"></p>',
    '  <ol class="site-search-results"></ol>',
    '  <button class="site-search-more" type="button" hidden></button>',
    "</section>"
  ].join("");
  document.body.appendChild(overlay);

  var dialog = overlay.querySelector(".site-search-dialog");
  var closeButton = overlay.querySelector(".site-search-close");
  var input = overlay.querySelector("#site-search-input");
  var inputLabel = overlay.querySelector('label[for="site-search-input"]');
  var title = overlay.querySelector("#site-search-title");
  var status = overlay.querySelector(".site-search-status");
  var results = overlay.querySelector(".site-search-results");

  var filters = overlay.querySelector(".site-search-filters");
  var moreButton = overlay.querySelector(".site-search-more");
  Object.keys(translations.fr.filters).forEach(function (key) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "site-search-filter";
    button.dataset.category = key;
    button.addEventListener("click", function () {
      selectedCategory = key;
      applyLanguage();
      queueSearch();
    });
    filters.appendChild(button);
  });
  moreButton.addEventListener("click", function () {
    runSearch(input.value.trim(), nextOffset);
  });

  function applyLanguage() {
    var strings = copy();
    openButton.setAttribute("aria-label", strings.button);
    openButton.setAttribute("title", strings.button);
    title.textContent = strings.title;
    filters.setAttribute("aria-label", strings.filtersLabel);
    filters.querySelectorAll("button").forEach(function (button) {
      button.textContent = strings.filters[button.dataset.category];
      button.setAttribute("aria-pressed", String(button.dataset.category === selectedCategory));
    });
    moreButton.textContent = strings.more;
    closeButton.setAttribute("aria-label", strings.close);
    closeButton.setAttribute("title", strings.close);
    inputLabel.textContent = strings.button;
    input.setAttribute("placeholder", strings.placeholder);
    if (!input.value.trim() && !overlay.hidden) {
      setStatus(strings.invitation);
    }
  }

  function setStatus(message, kind) {
    status.textContent = message;
    status.dataset.state = kind || "idle";
  }

  function clearResults() {
    results.replaceChildren();
    moreButton.hidden = true;
  }

  function resultUrl(url) {
    try {
      var parsed = new URL(url, window.location.origin);
      if (parsed.origin !== window.location.origin) return "#";
      var appRootPath = appRootUrl.pathname.endsWith("/") ? appRootUrl.pathname : appRootUrl.pathname + "/";
      if (parsed.pathname === appRootPath.slice(0, -1) || parsed.pathname.startsWith(appRootPath)) {
        return parsed.href;
      }
      var relativePath = parsed.pathname.replace(/^\/+/, "");
      var resolved = new URL(relativePath + parsed.search + parsed.hash, appRootUrl);
      return resolved.href;
    } catch (_) {
      return "#";
    }
  }

  function categoryFor(url) {
    var filename = "";
    try {
      var pathname = new URL(url, window.location.origin).pathname;
      filename = pathname.split("/").filter(Boolean).pop() || "index.php";
    } catch (_) {
      filename = "";
    }
    return copy().categories[filename] || "Scenarisation";
  }

  function normalizedWords(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLocaleLowerCase(currentLanguage())
      .split(/[^\p{L}\p{N}]+/u)
      .filter(function (word) { return word.length >= 2; });
  }

  function highlightSearchTerms(scrollToMatch) {
    var root = document.querySelector("main");
    if (!root) return;
    root.querySelectorAll("mark.site-search-highlight").forEach(function (mark) {
      var parent = mark.parentNode;
      mark.replaceWith(document.createTextNode(mark.textContent));
      parent.normalize();
    });
    var query = new URL(window.location.href).searchParams.get("highlight") || "";
    var words = new Set(normalizedWords(query.slice(0, 300)));
    if (!words.size) return;

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var nodes = [];
    while (walker.nextNode()) {
      if (!walker.currentNode.parentElement.closest(
        'script, style, textarea, input, select, button, mark, [contenteditable], [hidden], [aria-hidden="true"], [data-pagefind-ignore]'
      )) nodes.push(walker.currentNode);
    }
    var firstMatch = null;
    nodes.forEach(function (node) {
      var text = node.nodeValue;
      var pattern = /[\p{L}\p{N}][\p{L}\p{N}\p{M}]*/gu;
      var match;
      var offset = 0;
      var fragment = document.createDocumentFragment();
      while ((match = pattern.exec(text))) {
        if (!words.has(normalizedWords(match[0])[0])) continue;
        fragment.appendChild(document.createTextNode(text.slice(offset, match.index)));
        var mark = document.createElement("mark");
        mark.className = "site-search-highlight";
        mark.textContent = match[0];
        fragment.appendChild(mark);
        if (!firstMatch) firstMatch = mark;
        offset = match.index + match[0].length;
      }
      if (offset) {
        fragment.appendChild(document.createTextNode(text.slice(offset)));
        node.replaceWith(fragment);
      }
    });
    if (scrollToMatch) {
      var anchor = null;
      try { anchor = document.getElementById(decodeURIComponent(window.location.hash.slice(1))); } catch (_) {}
      var target = anchor || firstMatch;
      if (target) {
        for (var parent = target.parentElement; parent; parent = parent.parentElement) {
          if (parent.tagName === "DETAILS") parent.open = true;
        }
        target.scrollIntoView({ block: "center" });
      }
    }
  }

  function bestSubResult(data, query) {
    var subResults = Array.isArray(data.sub_results) ? data.sub_results : [];
    if (!subResults.length) return null;

    var queryWords = normalizedWords(query);
    var best = null;
    var bestScore = 0;
    subResults.forEach(function (candidate) {
      var titleWords = normalizedWords(candidate.title);
      var score = queryWords.reduce(function (total, word) {
        return total + (titleWords.includes(word) ? 1 : 0);
      }, 0);
      if (score > bestScore) {
        best = candidate;
        bestScore = score;
      }
    });
    return best || subResults[0];
  }

  function createResultItem(data, query) {
    var section = bestSubResult(data, query);
    var sectionHasAnchor = section && (section.anchor || String(section.url || "").includes("#"));
    var destination = section || data;
    var item = document.createElement("li");
    item.className = "site-search-result";

    var link = document.createElement("a");
    link.className = "site-search-result-link";
    link.href = resultUrl(destination.url || data.url || "");
    if (link.getAttribute("href") !== "#") {
      var highlightedUrl = new URL(link.href);
      highlightedUrl.searchParams.set("highlight", query);
      link.href = highlightedUrl.href;
    }

    var category = document.createElement("span");
    category.className = "site-search-result-category";
    category.textContent = data.referenceLabel || categoryFor(data.url || "");

    var heading = document.createElement("span");
    heading.className = "site-search-result-title";
    heading.textContent = (sectionHasAnchor && section.title) || (data.meta && data.meta.title) || copy().untitled;

    var excerpt = document.createElement("span");
    excerpt.className = "site-search-result-excerpt";
    excerpt.innerHTML = destination.excerpt || data.excerpt || "";

    link.append(category, heading, excerpt);
    item.appendChild(link);
    return item;
  }

  async function loadPagefind(language) {
    var selectedLanguage = language === "en" ? "en" : "fr";
    if (!pagefindPromises[selectedLanguage]) {
      var pagefindUrl = new URL("pagefind/" + selectedLanguage + "/pagefind.js", appRootUrl);
      pagefindPromises[selectedLanguage] = import(pagefindUrl.href).then(async function (pagefind) {
        await pagefind.options({
          excerptLength: 24
        });
        await pagefind.init();
        return pagefind;
      }).catch(function (error) {
        pagefindPromises[selectedLanguage] = null;
        throw error;
      });
    }
    return pagefindPromises[selectedLanguage];
  }

  function matchesCategory(data, category) {
    if (category === "all") return true;
    var filename = new URL(data.url, appRootUrl).pathname.split("/").pop() || "index.php";
    return (categoryFiles[category] || []).includes(filename);
  }

  function filterCategoryResult(data, category) {
    if (matchesCategory(data, category)) return data;
    var filename = new URL(data.url, appRootUrl).pathname.split("/").pop();
    if (category !== "references" || filename !== "help.php") return null;
    var isAias = function (section) {
      return /\bAIAS\b|AI Assessment Scale/i.test(
        String(section.title || "") + " " + String(section.excerpt || "").replace(/<[^>]*>/g, "")
      );
    };
    var sections = (data.sub_results || []).filter(isAias);
    if (!sections.length && !isAias(data)) return null;
    return Object.assign({}, data, { sub_results: sections, referenceLabel: "AIAS" });
  }

  async function searchPages(query, language, category) {
    var pagefind = await loadPagefind(language);
    var search = await pagefind.search(query);
    var loaded = await Promise.all(search.results.map(function (result) { return result.data(); }));
    return loaded.map(function (data) { return filterCategoryResult(data, category); }).filter(Boolean);
  }

  async function searchScenarios(query, offset) {
    var url = new URL("search_public_designs.php", appRootUrl);
    url.searchParams.set("q", query);
    url.searchParams.set("offset", offset);
    var response = await fetch(url, { cache: "no-store" });
    if (!response.ok) throw new Error("Public search unavailable");
    return response.json();
  }

  async function runSearch(query, offset) {
    offset = offset || 0;
    var sequence = ++searchSequence;
    var language = currentLanguage();
    var category = selectedCategory;
    if (!offset) clearResults();
    moreButton.disabled = true;
    setStatus(copy().searching, "loading");

    var sources = await Promise.allSettled([
      category === "scenarios" ? Promise.resolve([]) : searchPages(query, language, category),
      category === "all" || category === "scenarios" ? searchScenarios(query, offset) : Promise.resolve({ results: [], count: 0 })
    ]);
    if (sequence !== searchSequence) return;
    moreButton.disabled = false;
    var pages = sources[0].status === "fulfilled" ? sources[0].value : [];
    var scenarios = sources[1].status === "fulfilled" ? sources[1].value : { results: [], count: 0 };
    var failed = sources.some(function (source) { return source.status === "rejected"; });
    var count = pages.length + scenarios.count;
    var strings = copy();
    var message = count ? interpolate(count === 1 ? strings.oneResult : strings.manyResults, {
      count: new Intl.NumberFormat(language).format(count), query: query
    }) : interpolate(strings.empty, { query: query });
    if (failed) message = count ? message + " — " + strings.partial : strings.partial;
    setStatus(message, failed ? "error" : count ? "results" : "empty");
    scenarios.results.concat(pages.slice(offset, offset + 12)).forEach(function (data) {
      results.appendChild(createResultItem(data, query));
    });
    nextOffset = offset + 12;
    moreButton.hidden = nextOffset >= Math.max(pages.length, scenarios.count);
  }

  function queueSearch() {
    window.clearTimeout(searchTimer);
    searchSequence += 1;
    moreButton.hidden = true;
    var query = input.value.trim().slice(0, 200);
    if (query.length < 2) {
      searchSequence += 1;
      clearResults();
      setStatus(copy().invitation);
      return;
    }

    clearResults();
    setStatus(copy().searching, "loading");
    if (selectedCategory !== "scenarios") {
      loadPagefind(currentLanguage()).then(function (pagefind) {
        pagefind.preload(query);
      }).catch(function () {
        // runSearch reports any unavailable source after the debounce.
      });
    }
    searchTimer = window.setTimeout(function () {
      runSearch(query);
    }, 180);
  }

  function openSearch() {
    if (!overlay.hidden) return;
    previousFocus = document.activeElement;
    var navigationActions = document.getElementById("site-nav-actions");
    var navigationToggle = document.getElementById("nav-hamburger");
    if (navigationActions) navigationActions.classList.remove("nav-open");
    if (navigationToggle) navigationToggle.setAttribute("aria-expanded", "false");
    overlay.hidden = false;
    document.body.classList.add("site-search-is-open");
    applyLanguage();
    if (input.value.trim().length >= 2) {
      queueSearch();
    } else {
      setStatus(copy().invitation);
    }
    window.requestAnimationFrame(function () {
      input.focus();
      input.select();
    });
    loadPagefind(currentLanguage()).catch(function () {
      // A missing static index must not prevent searching public scenarios.
    });
  }

  function closeSearch() {
    if (overlay.hidden) return;
    searchSequence += 1;
    window.clearTimeout(searchTimer);
    overlay.hidden = true;
    document.body.classList.remove("site-search-is-open");
    if (previousFocus && typeof previousFocus.focus === "function") {
      previousFocus.focus();
    }
  }

  function trapFocus(event) {
    if (event.key !== "Tab") return;
    var focusable = Array.from(dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]:not([href="#"])'));
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  openButton.addEventListener("click", openSearch);
  closeButton.addEventListener("click", closeSearch);
  input.addEventListener("input", queueSearch);
  overlay.addEventListener("click", function (event) {
    if (event.target === overlay) closeSearch();
  });
  overlay.addEventListener("keydown", trapFocus);
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !overlay.hidden) {
      event.preventDefault();
      closeSearch();
      return;
    }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      openSearch();
    }
  });

  var languageSelect = document.getElementById("lang-select");
  var activeLanguage = currentLanguage();
  function handleLanguageChange() {
    var nextLanguage = currentLanguage();
    applyLanguage();
    if (nextLanguage === activeLanguage) return;
    activeLanguage = nextLanguage;
    window.setTimeout(function () { highlightSearchTerms(false); }, 0);
    searchSequence += 1;
    window.clearTimeout(searchTimer);
    clearResults();
    if (!overlay.hidden && input.value.trim().length >= 2) {
      queueSearch();
    }
  }
  if (languageSelect) {
    languageSelect.addEventListener("change", function () {
      window.setTimeout(handleLanguageChange, 0);
    });
  }
  new MutationObserver(handleLanguageChange).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["lang"]
  });
  applyLanguage();
  if (document.readyState === "complete") {
    highlightSearchTerms(true);
  } else {
    window.addEventListener("load", function () { highlightSearchTerms(true); }, { once: true });
  }
  window.addEventListener("hashchange", function () { highlightSearchTerms(true); });
})();
