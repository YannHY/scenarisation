(() => {
  "use strict";

  function renderScenarioMath() {
    if (typeof window.renderMathInElement !== "function") return;

    document.querySelectorAll(".markdown-content").forEach((container) => {
      window.renderMathInElement(container, {
        delimiters: [
          { left: "$$", right: "$$", display: true },
          { left: "$", right: "$", display: false }
        ],
        throwOnError: false,
        strict: "ignore",
        trust: false
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", renderScenarioMath, { once: true });
  } else {
    renderScenarioMath();
  }
})();
