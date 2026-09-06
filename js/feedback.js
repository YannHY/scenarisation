(() => {
  if (window.__learningDesignerFeedbackLoaded) return;
  window.__learningDesignerFeedbackLoaded = true;

  const copy = {
    fr: {
      trigger: "Donner votre avis",
      title: "Votre avis",
      prompt: "Comment trouvez-vous Scenarisation ?",
      positive: "Satisfait",
      neutral: "Mitigé",
      negative: "Insatisfait",
      comment: "Un commentaire ?",
      optional: "Facultatif",
      placeholder: "Dites-nous ce qui vous a aidé ou ce qui pourrait être amélioré…",
      send: "Envoyer",
      close: "Fermer",
      loading: "Préparation du formulaire…",
      sending: "Envoi…",
      thanks: "Merci pour votre retour !",
      error: "L’envoi a échoué. Veuillez réessayer.",
      privacy: "Confidentialité"
    },
    en: {
      trigger: "Give feedback",
      title: "Your feedback",
      prompt: "How do you feel about Scenarisation?",
      positive: "Satisfied",
      neutral: "Mixed",
      negative: "Dissatisfied",
      comment: "Any comments?",
      optional: "Optional",
      placeholder: "Tell us what helped or what could be improved…",
      send: "Send",
      close: "Close",
      loading: "Preparing the form…",
      sending: "Sending…",
      thanks: "Thanks for your feedback!",
      error: "Your feedback could not be sent. Please try again.",
      privacy: "Privacy"
    }
  };

  const root = document.createElement("div");
  root.className = "feedback-widget";
  root.innerHTML = `
    <button class="feedback-trigger" type="button" aria-expanded="false" aria-controls="feedback-panel">
      <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
      <span class="sr-only" data-feedback-copy="trigger"></span>
    </button>
    <section id="feedback-panel" class="feedback-panel" role="dialog" aria-modal="false" aria-labelledby="feedback-title" hidden>
      <div class="feedback-panel-head">
        <div>
          <p class="feedback-kicker" data-feedback-copy="title"></p>
          <h2 id="feedback-title" data-feedback-copy="prompt"></h2>
        </div>
        <button class="feedback-close" type="button" data-feedback-title="close">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          <span class="sr-only" data-feedback-copy="close"></span>
        </button>
      </div>
      <form class="feedback-form">
        <fieldset class="feedback-rating-group">
          <legend class="sr-only" data-feedback-copy="prompt"></legend>
          <div class="feedback-rating-options">
            <label class="feedback-rating feedback-rating-positive" data-feedback-title="positive">
              <input type="radio" name="rating" value="positive" required>
              <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
              <span class="sr-only" data-feedback-copy="positive"></span>
            </label>
            <label class="feedback-rating feedback-rating-neutral" data-feedback-title="neutral">
              <input type="radio" name="rating" value="neutral" required>
              <i class="fa-regular fa-face-meh" aria-hidden="true"></i>
              <span class="sr-only" data-feedback-copy="neutral"></span>
            </label>
            <label class="feedback-rating feedback-rating-negative" data-feedback-title="negative">
              <input type="radio" name="rating" value="negative" required>
              <i class="fa-regular fa-face-frown" aria-hidden="true"></i>
              <span class="sr-only" data-feedback-copy="negative"></span>
            </label>
          </div>
        </fieldset>
        <label class="feedback-comment-label" for="feedback-comment">
          <span data-feedback-copy="comment"></span>
          <small data-feedback-copy="optional"></small>
        </label>
        <textarea id="feedback-comment" name="comment" rows="3" maxlength="2000" data-feedback-placeholder="placeholder"></textarea>
        <div class="feedback-honeypot" aria-hidden="true">
          <label for="feedback-website">Website</label>
          <input id="feedback-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <p class="feedback-status" role="status" aria-live="polite"></p>
        <div class="feedback-actions">
          <a href="politique-confidentialite.php" data-feedback-copy="privacy"></a>
          <button class="feedback-submit" type="submit" disabled data-feedback-copy="send"></button>
        </div>
      </form>
      <div class="feedback-success" role="status" hidden>
        <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
        <p data-feedback-copy="thanks"></p>
      </div>
    </section>`;

  document.body.appendChild(root);

  const trigger = root.querySelector(".feedback-trigger");
  const panel = root.querySelector(".feedback-panel");
  const closeButton = root.querySelector(".feedback-close");
  const form = root.querySelector(".feedback-form");
  const submitButton = root.querySelector(".feedback-submit");
  const status = root.querySelector(".feedback-status");
  const success = root.querySelector(".feedback-success");
  const comment = root.querySelector("textarea");
  let token = "";
  let tokenPromise = null;

  function language() {
    return document.documentElement.lang.toLowerCase().startsWith("en") ? "en" : "fr";
  }

  function translate() {
    const strings = copy[language()];
    root.querySelectorAll("[data-feedback-copy]").forEach((element) => {
      element.textContent = strings[element.dataset.feedbackCopy] || "";
    });
    root.querySelectorAll("[data-feedback-title]").forEach((element) => {
      const value = strings[element.dataset.feedbackTitle] || "";
      element.setAttribute("title", value);
      element.setAttribute("aria-label", value);
    });
    root.querySelectorAll("[data-feedback-placeholder]").forEach((element) => {
      element.setAttribute("placeholder", strings[element.dataset.feedbackPlaceholder] || "");
    });
    trigger.setAttribute("title", strings.trigger);
    trigger.setAttribute("aria-label", strings.trigger);
  }

  async function prepareToken() {
    if (token) return token;
    if (tokenPromise) return tokenPromise;
    submitButton.disabled = true;
    status.textContent = copy[language()].loading;
    tokenPromise = fetch("feedback.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success || !data.token) throw new Error("feedback-token");
        token = data.token;
        status.textContent = "";
        submitButton.disabled = false;
        return token;
      })
      .catch((error) => {
        status.textContent = copy[language()].error;
        throw error;
      })
      .finally(() => {
        tokenPromise = null;
      });
    return tokenPromise;
  }

  function setOpen(open) {
    panel.hidden = !open;
    trigger.setAttribute("aria-expanded", open ? "true" : "false");
    root.classList.toggle("is-open", open);
    if (open) {
      prepareToken().catch(() => {});
      closeButton.focus();
    } else {
      trigger.focus();
    }
  }

  trigger.addEventListener("click", () => setOpen(panel.hidden));
  closeButton.addEventListener("click", () => setOpen(false));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) setOpen(false);
  });
  document.addEventListener("click", (event) => {
    if (!panel.hidden && !root.contains(event.target)) setOpen(false);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const selected = form.querySelector('input[name="rating"]:checked');
    if (!selected) return;

    submitButton.disabled = true;
    status.textContent = copy[language()].sending;
    try {
      const preparedToken = await prepareToken();
      const response = await fetch("feedback.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          token: preparedToken,
          rating: selected.value,
          comment: comment.value,
          website: form.elements.website.value,
          page: `${window.location.pathname}${window.location.search}`,
          locale: language()
        })
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        throw new Error(data.error || copy[language()].error);
      }
      token = "";
      form.hidden = true;
      success.hidden = false;
    } catch (error) {
      status.textContent = error.message || copy[language()].error;
      submitButton.disabled = false;
    }
  });

  const languageSelect = document.getElementById("lang-select");
  if (languageSelect) languageSelect.addEventListener("change", () => window.setTimeout(translate, 0));
  new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.attributeName === "lang")) translate();
  }).observe(document.documentElement, { attributes: true, attributeFilter: ["lang"] });
  translate();
})();
