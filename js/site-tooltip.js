// ── Tooltip personnalisé ─────────────────────────────────────
(function initTooltip() {
  if (document.getElementById("app-tooltip")) return;
  const tip = document.createElement("div");
  tip.id = "app-tooltip";
  tip.setAttribute("role", "tooltip");
  tip.setAttribute("aria-hidden", "true");
  document.body.appendChild(tip);

  let timer = null;
  let activeTarget = null;

  // Déplace title → data-tooltip pour éviter le doublon natif
  function hoistTitles(root) {
    (root.querySelectorAll ? root.querySelectorAll("[title]:not(abbr)") : [])
      .forEach((el) => {
        if (!el.dataset.tooltip) el.dataset.tooltip = el.getAttribute("title");
        el.removeAttribute("title");
      });
  }
  hoistTitles(document);

  // Surveille les nouveaux éléments (cartes d'activité, boutons de choix…)
  new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      m.addedNodes.forEach((node) => {
        if (node.nodeType === 1) {
          if (node.getAttribute && node.getAttribute("title") && node.tagName !== "ABBR") {
            if (!node.dataset.tooltip) node.dataset.tooltip = node.getAttribute("title");
            node.removeAttribute("title");
          }
          hoistTitles(node);
        }
      });
      // Gère aussi les attributions dynamiques de title (setChoiceButton)
      if (
        m.type === "attributes" &&
        m.attributeName === "title" &&
        m.target.tagName !== "ABBR" &&
        m.target.getAttribute("title")
      ) {
        m.target.dataset.tooltip = m.target.getAttribute("title");
        m.target.removeAttribute("title");
      }
    });
  }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ["title"] });

  function nearestTip(el) {
    let node = el;
    while (node && node !== document.body) {
      if (node.dataset && node.dataset.tooltip) return node;
      node = node.parentElement;
    }
    return null;
  }

function applyLanguageTypography(value, lang = document.documentElement.lang) {
  const text = String(value ?? "").replace(/,\s*(…|\.{3})/g, "$1");
  return text.replace(/[ \u00a0\u202f]*([:;]|[!?]+)(?!\/\/)/g, (match, punctuation, offset, source) => {
    const previous = source.charAt(offset - 1);
    const next = source.charAt(offset + match.length);
    if (punctuation === ":" && /\d/.test(previous) && /\d/.test(next)) {
      return punctuation;
    }
    if (lang === "en") {
      return punctuation;
    }
    return `${punctuation === ":" ? "\u00a0" : "\u202f"}${punctuation}`;
  });
}

  function formatTipText(text) {
    return applyLanguageTypography(text, document.documentElement.lang);
  }

  function place(target) {
    const rect = target.getBoundingClientRect();
    const tw = tip.offsetWidth;
    const th = tip.offsetHeight;
    const gap = 9;
    const vw = window.innerWidth;

    tip.classList.remove("tip-above", "tip-below");

    let top;
    if (rect.top - th - gap > 6) {
      top = rect.top - th - gap;
      tip.classList.add("tip-above");
    } else {
      top = rect.bottom + gap;
      tip.classList.add("tip-below");
    }

    let left = rect.left + rect.width / 2 - tw / 2;
    left = Math.max(6, Math.min(vw - tw - 6, left));

    // Décale la flèche si le tooltip est déporté
    const arrowPos = Math.max(14, Math.min(tw - 14, rect.left + rect.width / 2 - left));
    tip.style.setProperty("--tip-arrow", arrowPos + "px");
    tip.style.top = Math.round(top) + "px";
    tip.style.left = Math.round(left) + "px";
  }

  function show(target) {
    if (!target.isConnected) return;
    activeTarget = target;
    const describedBy = new Set((target.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean));
    describedBy.add(tip.id);
    target.setAttribute("aria-describedby", [...describedBy].join(" "));
    tip.textContent = formatTipText(target.dataset.tooltip);
    tip.setAttribute("aria-hidden", "false");
    // Positionne hors-écran le temps de mesurer
    tip.style.left = "-9999px";
    tip.style.top = "-9999px";
    tip.classList.add("tip-visible");
    requestAnimationFrame(() => { if (activeTarget === target) place(target); });
  }

  function hide() {
    clearTimeout(timer);
    if (activeTarget) {
      const ids = (activeTarget.getAttribute("aria-describedby") || "").split(/\s+/).filter(id => id && id !== tip.id);
      if (ids.length) activeTarget.setAttribute("aria-describedby", ids.join(" "));
      else activeTarget.removeAttribute("aria-describedby");
    }
    activeTarget = null;
    tip.classList.remove("tip-visible", "tip-above", "tip-below");
    tip.setAttribute("aria-hidden", "true");
  }

  document.addEventListener("mouseover", (e) => {
    const target = nearestTip(e.target);
    if (!target || target === activeTarget) return;
    hide();
    timer = setTimeout(() => show(target), 480);
  });

  document.addEventListener("mouseout", (e) => {
    const target = nearestTip(e.target);
    if (!target || target.contains(e.relatedTarget)) return;
    clearTimeout(timer);
    hide();
  });

  document.addEventListener("focusin", (e) => {
    const target = nearestTip(e.target);
    hide();
    if (target) show(target);
  });
  document.addEventListener("focusout", hide);
  window.addEventListener("resize", hide);
  document.addEventListener("click", hide, true);
  document.addEventListener("keydown", hide, true);
  document.addEventListener("scroll", () => {
    if (activeTarget) place(activeTarget);
  }, { passive: true, capture: true });
})();

