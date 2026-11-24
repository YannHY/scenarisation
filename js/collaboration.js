/*
 * Collaboration sur un scénario partagé, depuis le site.
 *
 * Mêmes principes que l’app Scénarisation pour Mac et iPad : chaque onglet se signale au site
 * toutes les deux secondes lorsque le scénario est partagé, avec l’élément sur lequel il travaille.
 * La réponse donne les collègues présents et la révision du site ; une révision plus récente déclenche
 * aussitôt la récupération et la fusion des modifications (js/account-ui.js, js/scenario-merge.js).
 * L’hébergement mutualisé ne permet pas de connexion permanente : ces échanges courts en tiennent lieu.
 */
(function () {
  "use strict";

  const app = () => window.learningDesignerApp;
  const account = () => window.learningDesignerCollaboration;
  const PALETTE_SIZE = 8;
  const SHARED_DELAY = 2000;
  const SOLO_DELAY = 30000;
  const RECENT_FOCUS_MS = 20000;
  const clientId = (() => {
    if (typeof crypto?.randomUUID === "function") return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, "0")).join("");
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  })();

  /* Champs éditables, nommés comme dans le format JSON partagé avec l’app. */
  const FIELD_CLASSES = {
    "activity-description": "description",
    "activity-instructions": "instructions",
    "activity-duration": "duration",
    "session-title": "title",
    "session-objectives": "objectives",
    "session-intentions": "intentions",
    "session-notes-input": "notes"
  };
  const META_FIELDS = {
    "meta-name": "name",
    "meta-description": "description",
    "meta-command": "command",
    "meta-personas": "personas",
    "meta-teacher-materials": "teacherMaterials",
    "meta-designers": "designers",
    "meta-trainers": "trainers",
    "meta-size-class": "sizeClass",
    "meta-delivery": "modeDelivery",
    "meta-school-system": "schoolSystem",
    "meta-level": "schoolLevel"
  };
  const FIELD_NAMES = {
    fr: {
      description: "la description", instructions: "les consignes", notes: "les notes", objectives: "les objectifs",
      intentions: "les intentions", title: "le titre", duration: "la durée", type: "le type", tools: "les compétences",
      name: "le titre", command: "la commande institutionnelle", personas: "les objectifs de la formation",
      teacherMaterials: "le matériel enseignant"
    },
    en: {
      description: "the description", instructions: "the instructions", notes: "the notes", objectives: "the objectives",
      intentions: "the intentions", title: "the title", duration: "the duration", type: "the type", tools: "the competencies",
      name: "the title", command: "the institutional brief", personas: "the course objectives",
      teacherMaterials: "the teacher materials"
    }
  };

  let participants = [];
  let shared = false;
  let timer = null;
  let failures = 0;
  let lostDesignId = 0;
  let trackedDesignId = 0;
  let editingField = null;
  let recentFocus = null;
  let previousSnapshot = null;
  let snapshotTimer = null;
  let sharing = null;
  let dialog = null;
  let latestRevision = 0;

  const english = () => app()?.getCurrentLang?.() === "en";
  const tr = (fr, en) => (english() ? en : fr);
  const context = () => account()?.context?.() || { user: null, designId: 0, role: "owner", shared: false, revision: 0 };
  const colorClass = (userId) => `presence-color-${Math.abs(Number(userId) || 0) % PALETTE_SIZE}`;
  const initial = (name) => (String(name || "").trim().charAt(0) || "?").toUpperCase();

  function uniquePeople(list) {
    const seen = new Set();
    return list.filter((participant) => !seen.has(participant.userId) && seen.add(participant.userId));
  }

  function joinNames(list) {
    const names = uniquePeople(list).map((participant) => participant.username);
    if (names.length <= 1) return names[0] || "";
    return `${names.slice(0, -1).join(", ")} ${tr("et", "and")} ${names.at(-1)}`;
  }

  /* « Modifie les consignes d’une activité », pour les infobulles et les lecteurs d’écran. */
  function activityDescription(participant) {
    if (!participant.focus) return tr("Consulte le scénario", "Viewing the scenario");
    const [kind, , field] = participant.focus.split(":");
    const name = FIELD_NAMES[english() ? "en" : "fr"][field];
    if (kind === "activity") {
      return name ? tr(`Modifie ${name} d’une activité`, `Editing ${name} of an activity`) : tr("Modifie une activité", "Editing an activity");
    }
    if (kind === "moment") {
      return name ? tr(`Modifie ${name} d’un moment`, `Editing ${name} of a moment`) : tr("Modifie un moment", "Editing a moment");
    }
    return name ? tr(`Modifie ${name}`, `Editing ${name}`) : tr("Modifie les paramètres du scénario", "Editing the scenario settings");
  }

  // ── Emplacement de travail signalé aux collègues ────────────────────

  function focusOf(element) {
    if (!(element instanceof HTMLElement)) return null;
    if (element.id && META_FIELDS[element.id]) return `scenario::${META_FIELDS[element.id]}`;
    const fieldClass = Object.keys(FIELD_CLASSES).find((name) => element.classList.contains(name));
    if (!fieldClass) return null;
    const wrapper = element.closest(".expandable-field");
    const anchor = wrapper?.classList.contains("fullscreen")
      ? document.getElementById(wrapper.dataset.fullscreenPlaceholderId || "") || element
      : element;
    if (fieldClass.startsWith("activity-")) {
      const activity = anchor.closest("[data-activity-id], [data-act-id]");
      const id = activity?.dataset.activityId || activity?.dataset.actId;
      return id ? `activity:${id}:${FIELD_CLASSES[fieldClass]}` : null;
    }
    const moment = anchor.closest("[data-session-id]");
    return moment ? `moment:${moment.dataset.sessionId}:${FIELD_CLASSES[fieldClass]}` : null;
  }

  /* Le champ en cours de saisie, sinon la dernière modification pendant vingt secondes. */
  function currentFocus() {
    if (editingField) return editingField;
    if (recentFocus && Date.now() - recentFocus.at < RECENT_FOCUS_MS) return recentFocus.focus;
    return null;
  }

  function noteStateChange() {
    if (!shared && !participants.length) {
      previousSnapshot = null;
      return;
    }
    clearTimeout(snapshotTimer);
    snapshotTimer = setTimeout(() => {
      const snapshot = app()?.getState?.();
      const Merge = window.LearningDesignerMerge;
      if (!snapshot || !Merge) return;
      const focus = previousSnapshot ? Merge.changedFocus(previousSnapshot, snapshot) : null;
      previousSnapshot = snapshot;
      if (!focus) return;
      const moved = recentFocus?.focus !== focus;
      recentFocus = { focus, at: Date.now() };
      if (moved && !editingField) wake();
    }, 400);
  }

  // ── Signal de présence ─────────────────────────────────────────────

  async function request(body, options = {}) {
    const response = await fetch("design_sharing.php", {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
      ...options
    });
    let data = null;
    try {
      data = await response.json();
    } catch (_) {}
    if (!response.ok || !data?.success) {
      const error = new Error(data?.error || tr("Le site n’a pas pu répondre.", "The site could not respond."));
      error.status = response.status;
      error.code = data?.code;
      throw error;
    }
    return data;
  }

  function schedule(delay) {
    clearTimeout(timer);
    timer = setTimeout(tick, delay);
  }

  function wake() {
    if (shared || participants.length) schedule(150);
  }

  async function tick() {
    timer = null;
    const current = context();
    if (!current.user || !(current.designId > 0) || current.designId === lostDesignId) {
      setParticipants([]);
      return;
    }
    if (document.visibilityState === "hidden") {
      schedule(SOLO_DELAY);
      return;
    }
    try {
      const data = await request({
        action: "presence", design_id: current.designId, client_id: clientId, focus: currentFocus()
      });
      if (context().designId !== current.designId) {
        schedule(200);
        return;
      }
      failures = 0;
      const others = (data.presence?.participants || []).filter((participant) => participant.clientId !== clientId);
      shared = Boolean(data.presence?.shared) || others.length > 0;
      account()?.setShared?.(Boolean(data.presence?.shared));
      account()?.setActive?.(others.length > 0);
      setParticipants(others);
      latestRevision = Number(data.presence?.revision) || 0;
      if (latestRevision > Number(context().revision)) void account()?.pull?.(latestRevision);
      schedule(shared ? SHARED_DELAY : SOLO_DELAY);
    } catch (error) {
      if (error.status === 404 && context().designId === current.designId) {
        // Scénario retiré du partage ou supprimé : la présence s’arrête et le brouillon reste affiché.
        lostDesignId = current.designId;
        setParticipants([]);
        if (current.role !== "owner") account()?.accessLost?.(current.designId);
        return;
      }
      if (error.status === 401) {
        setParticipants([]);
        return;
      }
      failures += 1;
      setParticipants([]);
      schedule(Math.min(60000, 5000 * failures));
    }
  }

  /* Les autres onglets voient aussitôt celui-ci partir, sans attendre l’expiration de sa présence. */
  function leave() {
    const current = context();
    if (!shared || !(current.designId > 0)) return;
    try {
      void fetch("design_sharing.php", {
        method: "POST",
        credentials: "same-origin",
        keepalive: true,
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({ action: "presence", design_id: current.designId, client_id: clientId, focus: null, leave: true })
      }).catch(() => {});
    } catch (_) {}
  }

  function restart() {
    const current = context();
    if (current.designId !== trackedDesignId) {
      trackedDesignId = current.designId;
      participants = [];
      latestRevision = 0;
      shared = current.shared;
      failures = 0;
      previousSnapshot = null;
      recentFocus = null;
      if (current.designId !== lostDesignId) lostDesignId = 0;
    } else {
      shared = shared || current.shared;
    }
    renderToolbarButton();
    renderReadOnly();
    if (current.user && current.designId > 0) schedule(300);
    else {
      clearTimeout(timer);
      setParticipants([]);
    }
  }

  // ── Affichage de la présence ───────────────────────────────────────

  function setParticipants(list) {
    const before = JSON.stringify(participants);
    participants = list;
    if (before === JSON.stringify(list)) return;
    renderToolbarButton();
    renderPresence();
    if (dialog?.open) renderMembers();
  }

  function avatar(participant, size = "small") {
    const element = document.createElement("span");
    element.className = `collab-avatar collab-avatar-${size} ${colorClass(participant.userId ?? participant.id)}`;
    element.textContent = initial(participant.username);
    element.setAttribute("aria-hidden", "true");
    return element;
  }

  function renderToolbarButton() {
    const button = document.getElementById("collab-btn");
    if (!button) return;
    const current = context();
    button.hidden = !current.user;
    if (button.hidden) return;
    const people = uniquePeople(participants);
    const label = button.querySelector(".btn-label");
    label.replaceChildren();
    if (people.length) {
      const stack = document.createElement("span");
      stack.className = "collab-avatar-stack";
      people.slice(0, 3).forEach((participant) => stack.append(avatar(participant)));
      if (people.length > 3) {
        const more = document.createElement("span");
        more.className = "collab-avatar-more";
        more.textContent = `+${people.length - 3}`;
        stack.append(more);
      }
      label.append(stack);
      const names = joinNames(people);
      button.setAttribute("aria-label", tr(`Collaborer. Présents\u00A0: ${names}`, `Collaborate. Present: ${names}`));
      button.title = people.map((participant) => `${participant.username} · ${activityDescription(participant).toLowerCase()}`).join("\n");
    } else {
      const icon = document.createElement("i");
      icon.className = `${shared ? "fa-solid fa-user-group" : "fa-solid fa-user-plus"} btn-icon-inline`;
      icon.setAttribute("aria-hidden", "true");
      label.append(icon, document.createTextNode(tr("Collaborer", "Collaborate")));
      button.setAttribute("aria-label", tr("Collaborer", "Collaborate"));
      button.title = shared
        ? tr("Scénario partagé · gérer les enseignants invités", "Shared scenario · manage invited teachers")
        : tr("Inviter des collègues à travailler sur ce scénario", "Invite colleagues to work on this scenario");
    }
  }

  function elementsFor(focus) {
    const [kind, id] = String(focus || "").split(":");
    if (kind === "activity" && id) {
      return [...document.querySelectorAll("[data-activity-id], [data-act-id]")]
        .filter((element) => (element.dataset.activityId || element.dataset.actId) === id);
    }
    if (kind === "moment" && id) {
      return [...document.querySelectorAll(".session-card[data-session-id], tr[data-session-id]")]
        .filter((element) => element.dataset.sessionId === id);
    }
    if (kind === "scenario") {
      const field = String(focus).split(":")[2];
      const metaId = Object.keys(META_FIELDS).find((key) => META_FIELDS[key] === field);
      const element = metaId ? document.getElementById(metaId) : null;
      return element ? [element] : [];
    }
    return [];
  }

  /* Contour et prénom des collègues sur la carte où ils travaillent ; message dans le champ partagé. */
  function renderPresence() {
    document.querySelectorAll(".presence-badge, .presence-field-note").forEach((element) => element.remove());
    document.querySelectorAll(".has-presence").forEach((element) => {
      element.classList.remove("has-presence");
      [...element.classList].filter((name) => name.startsWith("presence-color-")).forEach((name) => element.classList.remove(name));
    });
    const groups = new Map();
    for (const participant of participants) {
      if (!participant.focus) continue;
      const item = participant.focus.split(":").slice(0, 2).join(":");
      if (!groups.has(item)) groups.set(item, []);
      groups.get(item).push(participant);
    }
    for (const [, list] of groups) {
      const people = uniquePeople(list);
      for (const element of elementsFor(list[0].focus)) {
        element.classList.add("has-presence", colorClass(people[0].userId));
        if (element.matches("input, textarea, select, tr")) continue;
        const badge = document.createElement("span");
        badge.className = "presence-badge";
        badge.setAttribute("role", "note");
        badge.setAttribute("aria-label", tr(`En cours de modification par ${joinNames(people)}`, `Being edited by ${joinNames(people)}`));
        people.forEach((participant) => {
          const name = document.createElement("span");
          name.className = `presence-name ${colorClass(participant.userId)}`;
          name.textContent = participant.username;
          name.title = activityDescription(participant);
          badge.append(name);
        });
        element.append(badge);
      }
    }
    renderFieldNote();
  }

  function renderFieldNote() {
    document.querySelectorAll(".presence-field-note").forEach((element) => element.remove());
    if (!editingField) return;
    const others = participants.filter((participant) => participant.focus === editingField);
    if (!others.length) return;
    const field = document.activeElement;
    if (!(field instanceof HTMLElement) || focusOf(field) !== editingField) return;
    const people = uniquePeople(others);
    const note = document.createElement("p");
    note.className = `presence-field-note ${colorClass(people[0].userId)}`;
    note.setAttribute("role", "status");
    const verb = people.length > 1 ? tr("modifient", "are also editing") : tr("modifie", "is also editing");
    note.textContent = tr(
      `${joinNames(people)} ${verb} aussi ce texte. Les modifications de chacun sont fusionnées ; sur une même phrase, la dernière enregistrée l’emporte.`,
      `${joinNames(people)} ${verb} this text. Everyone’s changes are merged; on the same sentence, the last one saved wins.`
    );
    const wrapper = field.closest(".expandable-field");
    if (wrapper) wrapper.append(note);
    else field.insertAdjacentElement("afterend", note);
  }

  // ── Consultation seule ─────────────────────────────────────────────

  const READ_ONLY_ALLOWED = ".expand-btn, .toggle-session-notes-btn, .top-tab, .top-panel-toggle";

  function renderReadOnly() {
    const readOnly = context().role === "viewer" && context().designId > 0;
    document.body.classList.toggle("is-read-only-scenario", readOnly);
    // Un enseignant invité ne publie pas le scénario : seul son propriétaire le peut.
    document.body.classList.toggle("is-invited-scenario", context().role !== "owner" && context().designId > 0);
    let banner = document.getElementById("read-only-banner");
    if (!readOnly) {
      banner?.remove();
      return;
    }
    if (!banner) {
      banner = document.createElement("div");
      banner.id = "read-only-banner";
      banner.className = "read-only-banner";
      banner.setAttribute("role", "status");
      const icon = document.createElement("i");
      icon.className = "fa-regular fa-eye";
      icon.setAttribute("aria-hidden", "true");
      const text = document.createElement("p");
      banner.append(icon, text);
      document.getElementById("main-content")?.prepend(banner);
    }
    const owner = context().owner;
    const strong = document.createElement("strong");
    strong.textContent = tr("Consultation seule.", "View only.");
    banner.querySelector("p").replaceChildren(strong, document.createTextNode(" " + (owner
      ? tr(`${owner} vous a partagé ce scénario en lecture : vous pouvez le parcourir et l’exporter, mais pas le modifier.`,
        `${owner} shared this scenario with you for viewing: you can browse and export it, but not edit it.`)
      : tr("Ce scénario vous est partagé en lecture : vous pouvez le parcourir et l’exporter, mais pas le modifier.",
        "This scenario is shared with you for viewing: you can browse and export it, but not edit it."))));
    lockEditing();
  }

  function lockEditing() {
    if (!document.body.classList.contains("is-read-only-scenario")) return;
    for (const scope of [document.getElementById("board"), document.getElementById("timeline-view")]) {
      if (!scope) continue;
      scope.querySelectorAll("textarea, input").forEach((field) => {
        if (field.type === "checkbox" || field.type === "radio") field.disabled = true;
        else field.readOnly = true;
      });
      scope.querySelectorAll("select").forEach((select) => { select.disabled = true; });
      scope.querySelectorAll("button").forEach((button) => {
        if (!button.matches(READ_ONLY_ALLOWED)) button.disabled = true;
      });
      scope.querySelectorAll("[draggable='true']").forEach((element) => { element.draggable = false; });
    }
  }

  // ── Fenêtre de partage ─────────────────────────────────────────────

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function ensureDialog() {
    if (dialog) return dialog;
    dialog = element("dialog", "modal collab-dialog");
    dialog.setAttribute("aria-labelledby", "collab-dialog-title");
    dialog.innerHTML = `
      <header class="collab-dialog-header">
        <div>
          <h2 id="collab-dialog-title" class="modal-title"></h2>
          <p class="collab-dialog-subtitle"></p>
        </div>
        <button class="icon-btn collab-dialog-close" type="button"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </header>
      <section class="collab-section collab-members-section" aria-labelledby="collab-members-title">
        <h3 id="collab-members-title" class="collab-section-title"></h3>
        <ul class="collab-members"></ul>
      </section>
      <section class="collab-section collab-invite-section" aria-labelledby="collab-invite-title">
        <h3 id="collab-invite-title" class="collab-section-title"></h3>
        <form class="collab-invite-form" novalidate>
          <label class="sr-only" for="collab-invite-email"></label>
          <input id="collab-invite-email" class="collab-invite-email" type="email" autocomplete="off" spellcheck="false" maxlength="190" />
          <label class="sr-only" for="collab-invite-role"></label>
          <select id="collab-invite-role" class="collab-role-select">
            <option value="editor"></option>
            <option value="viewer"></option>
          </select>
          <button class="btn btn-primary collab-invite-btn" type="submit"></button>
        </form>
        <p class="collab-hint collab-invite-hint"></p>
      </section>
      <section class="collab-section collab-leave-section">
        <p class="collab-hint collab-leave-copy"></p>
        <button class="btn btn-light collab-leave-btn" type="button"></button>
      </section>
      <section class="collab-section collab-save-first">
        <p class="collab-hint collab-save-first-copy"></p>
        <button class="btn btn-primary collab-save-first-btn" type="button"></button>
      </section>
      <p class="collab-message" role="alert" hidden></p>
      <p class="collab-footnote"></p>`;
    document.body.append(dialog);
    dialog.querySelector(".collab-dialog-close").addEventListener("click", () => dialog.close());
    dialog.addEventListener("keydown", (event) => event.stopPropagation());
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener("close", () => {
      document.getElementById("collab-btn")?.focus();
      // Les modifications reçues pendant l’ouverture de la fenêtre sont appliquées maintenant.
      if (latestRevision > Number(context().revision)) void account()?.pull?.(latestRevision);
    });
    dialog.querySelector(".collab-invite-form").addEventListener("submit", (event) => {
      event.preventDefault();
      void invite();
    });
    dialog.querySelector("#collab-invite-role").addEventListener("change", renderInviteHint);
    dialog.querySelector(".collab-invite-email").addEventListener("input", () => showMessage(""));
    dialog.querySelector(".collab-leave-btn").addEventListener("click", () => void leaveSharing());
    dialog.querySelector(".collab-save-first-btn").addEventListener("click", async () => {
      const saved = await account()?.save?.();
      if (saved) await openDialog();
    });
    return dialog;
  }

  function localizeDialog() {
    const scenario = app()?.getState?.();
    dialog.querySelector("#collab-dialog-title").textContent = tr("Collaborer", "Collaborate");
    dialog.querySelector(".collab-dialog-subtitle").textContent = String(scenario?.meta?.name || "").trim() || tr("Scénario sans titre", "Untitled scenario");
    const close = dialog.querySelector(".collab-dialog-close");
    close.setAttribute("aria-label", tr("Fermer", "Close"));
    close.title = tr("Fermer", "Close");
    dialog.querySelector("#collab-members-title").textContent = tr("Enseignants", "Teachers");
    dialog.querySelector("#collab-invite-title").textContent = tr("Inviter un enseignant", "Invite a teacher");
    dialog.querySelector("label[for='collab-invite-email']").textContent = tr("Adresse e-mail de son compte", "Email address of their account");
    dialog.querySelector(".collab-invite-email").placeholder = tr("Adresse e-mail de son compte", "Email address of their account");
    dialog.querySelector("label[for='collab-invite-role']").textContent = tr("Droits", "Rights");
    dialog.querySelector("option[value='editor']").textContent = tr("Peut modifier", "Can edit");
    dialog.querySelector("option[value='viewer']").textContent = tr("Peut consulter", "Can view");
    dialog.querySelector(".collab-invite-btn").textContent = tr("Inviter", "Invite");
    dialog.querySelector(".collab-leave-btn").textContent = tr("Quitter le partage", "Leave");
    dialog.querySelector(".collab-save-first-copy").textContent = tr(
      "Ce scénario doit d’abord être enregistré dans votre compte. Vous pourrez ensuite inviter des collègues.",
      "This scenario must first be saved to your account. You can then invite colleagues."
    );
    dialog.querySelector(".collab-save-first-btn").textContent = tr("Enregistrer", "Save");
    dialog.querySelector(".collab-footnote").textContent = tr(
      "Les enseignants invités doivent avoir un compte vérifié sur Scénarisation. Ils retrouvent le scénario dans Mes scénarios, sur le site ou dans l’app pour Mac et iPad. Les modifications de chacun apparaissent chez les autres en quelques secondes et sont fusionnées champ par champ.",
      "Invited teachers need a verified Scénarisation account. They find the scenario in My scenarios, on the site or in the Mac and iPad app. Everyone’s changes appear for the others within seconds and are merged field by field."
    );
    renderInviteHint();
  }

  function renderInviteHint() {
    const viewer = dialog.querySelector("#collab-invite-role").value === "viewer";
    dialog.querySelector(".collab-invite-hint").textContent = viewer
      ? tr("L’enseignant pourra parcourir et exporter le scénario, sans le modifier. Vous seul pouvez inviter, changer les droits, retirer ou supprimer.",
        "The teacher can browse and export the scenario without editing it. Only you can invite, change rights, remove or delete.")
      : tr("L’enseignant pourra modifier le scénario avec vous. Vous seul pouvez inviter, changer les droits, retirer ou supprimer.",
        "The teacher can edit the scenario with you. Only you can invite, change rights, remove or delete.");
  }

  function showMessage(text) {
    const message = dialog?.querySelector(".collab-message");
    if (!message) return;
    message.hidden = !text;
    message.textContent = text;
  }

  function setBusy(busy) {
    dialog?.querySelectorAll("button, input, select").forEach((control) => {
      if (!control.classList.contains("collab-dialog-close")) control.disabled = busy;
    });
    dialog?.classList.toggle("is-busy", busy);
  }

  function describeError(error) {
    if (error?.status === 404) {
      return tr("Ce scénario n’est pas disponible pour le partage.", "This scenario is not available for sharing.");
    }
    return error?.message || tr("Le partage n’a pas pu être mis à jour.", "Sharing could not be updated.");
  }

  function renderMembers() {
    if (!dialog) return;
    const list = dialog.querySelector(".collab-members");
    list.replaceChildren();
    const me = context().user?.id;
    const present = new Set(participants.map((participant) => participant.userId));
    for (const member of sharing?.members || []) {
      const item = element("li", "collab-member");
      item.append(avatar(member, "large"));
      const identity = element("div", "collab-member-identity");
      const nameLine = element("p", "collab-member-name");
      nameLine.append(document.createTextNode(member.username + (member.id === me ? tr(" (vous)", " (you)") : "")));
      if (present.has(member.id) && member.id !== me) {
        const presence = element("span", "collab-member-present", tr("présent", "present"));
        nameLine.append(presence);
      }
      identity.append(nameLine);
      if (member.email) identity.append(element("p", "collab-member-email", member.email));
      item.append(identity);
      const rights = element("div", "collab-member-rights");
      if (member.role === "owner") {
        rights.append(element("span", "collab-member-role", tr("Propriétaire", "Owner")));
      } else if (sharing.canManage) {
        const select = element("select", "collab-role-select");
        select.setAttribute("aria-label", tr(`Droits de ${member.username}`, `Rights of ${member.username}`));
        [["editor", tr("Peut modifier", "Can edit")], ["viewer", tr("Peut consulter", "Can view")]].forEach(([value, label]) => {
          const option = element("option", "", label);
          option.value = value;
          option.selected = member.role === value;
          select.append(option);
        });
        select.addEventListener("change", () => void changeRole(member, select.value));
        const remove = element("button", "icon-btn collab-member-remove");
        remove.type = "button";
        remove.setAttribute("aria-label", tr(`Retirer ${member.username} du partage`, `Remove ${member.username}`));
        remove.title = tr("Retirer du partage", "Remove");
        remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        remove.addEventListener("click", () => void removeMember(member));
        rights.append(select, remove);
      } else {
        rights.append(element("span", "collab-member-role", member.role === "viewer" ? tr("Peut consulter", "Can view") : tr("Peut modifier", "Can edit")));
      }
      item.append(rights);
      list.append(item);
    }
    if ((sharing?.members || []).length === 1) {
      const empty = element("li", "collab-members-empty", tr("Personne d’autre n’a encore accès à ce scénario.", "Nobody else has access to this scenario yet."));
      list.append(empty);
    }
  }

  function renderSections() {
    const saved = context().designId > 0;
    const canManage = Boolean(sharing?.canManage);
    dialog.querySelector(".collab-members-section").hidden = !saved || !sharing;
    dialog.querySelector(".collab-invite-section").hidden = !saved || !canManage;
    dialog.querySelector(".collab-leave-section").hidden = !saved || !sharing || canManage;
    dialog.querySelector(".collab-save-first").hidden = saved;
    if (sharing && !canManage) {
      const me = sharing.members.find((member) => member.id === context().user?.id);
      dialog.querySelector(".collab-leave-copy").textContent = me?.role === "viewer"
        ? tr(`${sharing.members[0]?.username || "Son propriétaire"} vous a partagé ce scénario en consultation.`, `${sharing.members[0]?.username || "Its owner"} shared this scenario with you for viewing.`)
        : tr(`${sharing.members[0]?.username || "Son propriétaire"} vous a partagé ce scénario.`, `${sharing.members[0]?.username || "Its owner"} shared this scenario with you.`);
    }
    renderMembers();
  }

  async function openDialog() {
    if (!context().user) {
      window.location.href = "login.php";
      return;
    }
    ensureDialog();
    localizeDialog();
    showMessage("");
    sharing = null;
    renderSections();
    if (!dialog.open) dialog.showModal();
    const designId = context().designId;
    if (!(designId > 0)) return;
    setBusy(true);
    try {
      const data = await request({ action: "members", design_id: designId });
      sharing = data.sharing;
    } catch (error) {
      showMessage(describeError(error));
    } finally {
      setBusy(false);
    }
    renderSections();
    if (sharing?.canManage) dialog.querySelector(".collab-invite-email").focus();
  }

  function updateSharing(next) {
    sharing = next;
    const isShared = Boolean(next && next.members.length > 1);
    shared = isShared || participants.length > 0;
    account()?.setShared?.(isShared);
    renderToolbarButton();
    renderSections();
    if (shared) schedule(SHARED_DELAY);
  }

  async function invite() {
    const input = dialog.querySelector(".collab-invite-email");
    const email = input.value.trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      showMessage(tr("Saisissez l’adresse e-mail du compte de votre collègue.", "Enter the email address of your colleague’s account."));
      input.focus();
      return;
    }
    showMessage("");
    setBusy(true);
    try {
      const data = await request({
        action: "share", design_id: context().designId, email, role: dialog.querySelector("#collab-invite-role").value
      });
      input.value = "";
      updateSharing(data.sharing);
      app()?.announce?.(tr("Invitation envoyée.", "Invitation sent."));
    } catch (error) {
      showMessage(error.code === "unknown_user"
        ? tr("Aucun compte vérifié de Scénarisation n’utilise cette adresse. Votre collègue doit d’abord créer son compte et confirmer son adresse.",
          "No verified Scénarisation account uses this address. Your colleague must first create an account and confirm the address.")
        : describeError(error));
    } finally {
      setBusy(false);
      input.focus();
    }
  }

  async function changeRole(member, role) {
    showMessage("");
    setBusy(true);
    try {
      const data = await request({ action: "role", design_id: context().designId, user_id: member.id, role });
      updateSharing(data.sharing);
    } catch (error) {
      showMessage(describeError(error));
      renderMembers();
    } finally {
      setBusy(false);
    }
  }

  async function confirmAction(title, message, confirmLabel) {
    // La fenêtre de confirmation du site s’ouvre au-dessus de celle-ci.
    const confirm = window.learningDesignerConfirm;
    if (typeof confirm === "function") {
      return confirm({ title, message, confirmLabel, cancelLabel: tr("Annuler", "Cancel") });
    }
    return window.confirm(message);
  }

  async function removeMember(member) {
    const confirmed = await confirmAction(
      tr("Retirer cet enseignant du partage ?", "Remove this teacher?"),
      tr(`${member.username} ne pourra plus ouvrir ce scénario.`, `${member.username} will no longer be able to open this scenario.`),
      tr("Retirer", "Remove")
    );
    if (!confirmed) return;
    showMessage("");
    setBusy(true);
    try {
      const data = await request({ action: "unshare", design_id: context().designId, user_id: member.id });
      updateSharing(data.sharing);
    } catch (error) {
      showMessage(describeError(error));
    } finally {
      setBusy(false);
    }
  }

  async function leaveSharing() {
    const confirmed = await confirmAction(
      tr("Quitter ce scénario partagé ?", "Leave this shared scenario?"),
      tr("Il disparaîtra de vos scénarios. Son propriétaire le conserve et pourra vous inviter de nouveau.",
        "It will disappear from your scenarios. Its owner keeps it and can invite you again."),
      tr("Quitter", "Leave")
    );
    if (!confirmed) return;
    setBusy(true);
    try {
      await request({ action: "unshare", design_id: context().designId, user_id: context().user.id });
      lostDesignId = context().designId;
      dialog.close();
      window.location.href = "my-designs.php";
    } catch (error) {
      showMessage(describeError(error));
      setBusy(false);
    }
  }

  // ── Branchements ───────────────────────────────────────────────────

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("collab-btn")?.addEventListener("click", () => void openDialog());
    renderToolbarButton();
  });

  window.addEventListener("ld:collaboration", restart);
  window.addEventListener("ld:render", () => {
    renderPresence();
    lockEditing();
    renderToolbarButton();
  });
  window.addEventListener("ld:statechange", () => {
    if (context().designId !== trackedDesignId) restart();
    noteStateChange();
  });
  window.addEventListener("ld:collaboration-lost", (event) => {
    lostDesignId = event.detail?.designId || lostDesignId;
    setParticipants([]);
  });
  document.addEventListener("focusin", (event) => {
    const focus = focusOf(event.target);
    if (focus === editingField) return;
    editingField = focus;
    renderFieldNote();
    if (focus) wake();
  });
  document.addEventListener("focusout", () => {
    setTimeout(() => {
      const focus = focusOf(document.activeElement);
      if (focus === editingField) return;
      // La saisie qui vient de se terminer reste signalée quelques secondes.
      if (editingField) recentFocus = { focus: editingField, at: Date.now() };
      editingField = focus;
      renderFieldNote();
    }, 0);
  });
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible" && context().designId > 0) schedule(100);
  });
  window.addEventListener("pagehide", leave);
})();
