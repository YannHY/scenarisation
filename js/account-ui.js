(function () {
  "use strict";

  const app = () => window.learningDesignerApp;
  const authState = {
    user: null,
    loading: false
  };
  let requestedRemoteDesignHandled = false;
  let startupRemoteDesignSynced = false;
  let autoSaveTimer = null;
  let lastSaved = null;
  let saveInFlight = null;
  let remoteLoadInFlight = null;
  let autoSaveHideTimer = null;

  function tr(fr, en) {
    return app()?.getCurrentLang?.() === "en" ? en : fr;
  }

  function $(id) {
    return document.getElementById(id);
  }

  function currentDesignId() {
    const state = app()?.getState?.() ?? {};
    return Number(state?.meta?.remoteDesignId || 0);
  }

  function hasMeaningfulDesignContent(state) {
    if (Array.isArray(state?.sessions) && state.sessions.length > 0) return true;

    const meta = state?.meta ?? {};
    const textFields = [
      "name",
      "description",
      "command",
      "designers",
      "trainers",
      "personas",
      "modeDelivery",
      "schoolLevel",
      "sizeClass"
    ];
    if (textFields.some((field) => String(meta[field] ?? "").trim() !== "")) return true;
    if (Array.isArray(meta.sliders) && meta.sliders.length > 0) return true;

    return ["learningDays", "learningHours", "learningMinutes"]
      .some((field) => Number(meta[field] || 0) > 0);
  }

  function setRemoteDesignUrl(designId) {
    if (!Number.isFinite(designId) || designId <= 0) return;
    const url = new URL(window.location.href);
    url.searchParams.set("remote_design_id", String(designId));
    window.history.replaceState({}, "", url.toString());
  }

  function clearRemoteDesignUrl() {
    const url = new URL(window.location.href);
    url.searchParams.delete("remote_design_id");
    window.history.replaceState({}, "", url.toString());
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(options.body ? { "Content-Type": "application/json" } : {})
      },
      ...options
    });

    let data = null;
    try {
      data = await response.json();
    } catch (_) {
      data = null;
    }

    if (!response.ok || !data?.success) {
      const error = new Error(data?.error || tr("Erreur serveur.", "Server error."));
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  async function refreshAuth() {
    authState.loading = true;
    try {
      const data = await fetchJson("auth_me.php");
      authState.user = data.user;
    } catch (_) {
      authState.user = null;
    } finally {
      authState.loading = false;
      app()?.initializeStorageScope?.(authState.user?.id ?? null);
      renderAccountArea();
      syncSaveUi();
      syncPublishUi();
      await maybeLoadRequestedDesign();
      await maybeSyncCurrentRemoteDesign();
    }
  }

  function setSaveButtonText(label) {
    const button = $("save-btn");
    if (!button) return;
    const labelNode = button.querySelector(".btn-label");
    if (labelNode) {
      labelNode.innerHTML = `<i class="fa-regular fa-floppy-disk btn-icon-inline" aria-hidden="true"></i>${escapeHtml(label)}`;
    } else {
      button.textContent = label;
    }
    button.setAttribute("aria-label", label);
    button.title = label;
  }

  function resetSaveButtonState() {
    const button = $("save-btn");
    if (!button) return;
    clearTimeout(autoSaveHideTimer);
    button.removeAttribute("data-save-status");
    button.removeAttribute("aria-busy");
    setSaveButtonText(hasSaveConflict()
      ? tr("Enregistrer une copie", "Save a copy")
      : tr("Enregistrer", "Save"));
  }

  function syncSaveUi() {
    const button = $("save-btn");
    if (!button) return;

    if (authState.user) {
      button.hidden = false;
      if (!button.dataset.saveStatus) {
        resetSaveButtonState();
      }
      return;
    }

    resetSaveButtonState();
    button.hidden = true;
  }

  function openSavedDesignsOrLogin() {
    if (!authState.user) {
      window.location.href = "login.php";
      return;
    }
    window.location.href = "my-designs.php";
  }

  function saveToAccountOrLogin(event) {
    if (event) {
      event.preventDefault?.();
      event.stopImmediatePropagation?.();
    }
    if (!authState.user) {
      window.location.href = "login.php";
      return;
    }
    saveRemoteDesign();
  }

  function setAutoSaveStatus(kind, text) {
    const button = $("save-btn");
    if (!button || button.hidden) return;
    clearTimeout(autoSaveHideTimer);
    button.dataset.saveStatus = kind;
    if (kind === "saving") {
      button.setAttribute("aria-busy", "true");
    } else {
      button.removeAttribute("aria-busy");
    }
    setSaveButtonText(text);
    if (kind === "saving") return;
    autoSaveHideTimer = setTimeout(() => {
      resetSaveButtonState();
    }, kind === "error" ? 4000 : 2500);
  }

  let activeConfirmation = null;

  function confirmAccountAction({ title, message, confirmLabel, cancelLabel }) {
    // A second click must not create another dialog or repeat the action.
    if (activeConfirmation) return Promise.resolve(false);
    return new Promise((resolve) => {
      const previousFocus = document.activeElement;
      const dialog = document.createElement("dialog");
      dialog.className = "modal account-confirm-dialog";
      dialog.setAttribute("aria-labelledby", "account-confirm-title");
      dialog.setAttribute("aria-describedby", "account-confirm-message");
      dialog.innerHTML = `
        <h2 id="account-confirm-title" class="modal-title"></h2>
        <p id="account-confirm-message" class="account-confirm-message"></p>
        <div class="modal-actions">
          <button class="btn btn-light" type="button" data-confirm-cancel autofocus></button>
          <button class="btn btn-primary" type="button" data-confirm-accept></button>
        </div>`;
      dialog.querySelector("h2").textContent = title;
      dialog.querySelector("p").textContent = message;
      const cancel = dialog.querySelector("[data-confirm-cancel]");
      const accept = dialog.querySelector("[data-confirm-accept]");
      cancel.textContent = cancelLabel;
      accept.textContent = confirmLabel;
      activeConfirmation = dialog;
      cancel.addEventListener("click", () => dialog.close("cancel"));
      accept.addEventListener("click", () => dialog.close("confirm"));
      dialog.addEventListener("cancel", (event) => {
        event.preventDefault();
        dialog.close("cancel");
      });
      dialog.addEventListener("keydown", (event) => {
        // Keep the editor's shortcuts and other modal handlers out of this dialog.
        event.stopPropagation();
        if (event.key === "Escape") {
          event.preventDefault();
          dialog.close("cancel");
        } else if (event.key === "Tab") {
          if (event.shiftKey && document.activeElement === cancel) {
            event.preventDefault();
            accept.focus();
          } else if (!event.shiftKey && document.activeElement === accept) {
            event.preventDefault();
            cancel.focus();
          }
        }
      });
      dialog.addEventListener("close", () => {
        const confirmed = dialog.returnValue === "confirm";
        dialog.remove();
        activeConfirmation = null;
        if (previousFocus?.isConnected) previousFocus.focus();
        resolve(confirmed);
      }, { once: true });
      document.body.appendChild(dialog);
      dialog.showModal();
      cancel.focus();
    });
  }

  function hasSaveConflict(state = app()?.getState?.()) {
    const id = Number(state?.meta?.remoteDesignId || 0);
    return id > 0 && Number(state?.meta?.remoteSaveConflict) === id;
  }

  function contentSignature(state) {
    const meta = { ...state.meta };
    delete meta.remoteDesignId;
    delete meta.remoteUpdatedAt;
    delete meta.remoteRevision;
    delete meta.remoteSaveConflict;
    delete meta.remoteDirty;
    return JSON.stringify({ ...state, meta });
  }

  function isSaved(state) {
    return lastSaved?.generation === app()?.getDocumentGeneration?.()
      && lastSaved?.id === Number(state.meta?.remoteDesignId || 0)
      && lastSaved?.signature === contentSignature(state);
  }

  function showSaveConflict() {
    resetSaveButtonState();
    const message = tr(
      "Conflit de sauvegarde : la version distante a changé. La sauvegarde automatique est suspendue et votre brouillon local est conservé. Cliquez sur « Enregistrer une copie » pour le sauvegarder séparément.",
      "Save conflict: the remote version has changed. Auto-save is paused and your local draft is preserved. Click “Save a copy” to save it separately."
    );
    app()?.showNotice?.(message, "error");
    app()?.announce?.(message);
  }

  function scheduleAutoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = null;
    if (!authState.user || saveInFlight || remoteLoadInFlight) return;
    const state = app()?.getState?.();
    if (!state || hasSaveConflict(state) || isSaved(state)) return;
    if (Number(state.meta?.remoteDesignId || 0) <= 0 && !hasMeaningfulDesignContent(state)) return;
    autoSaveTimer = setTimeout(() => {
      autoSaveTimer = null;
      void autoSaveRemote();
    }, 45000);
  }

  // All save entry points share one request at a time and acknowledge only
  // the snapshot actually sent, so edits made during the request stay dirty.
  async function persistRemoteDesign(automatic = false) {
    while (remoteLoadInFlight || saveInFlight) await (remoteLoadInFlight || saveInFlight);
    if (!authState.user) return null;
    const state = app()?.getState?.();
    if (!state) return null;
    let designId = Number(state.meta?.remoteDesignId || 0);
    let expectedRevision = Number(state.meta?.remoteRevision) || null;
    if (hasSaveConflict(state)) {
      if (automatic) return null;
      const decisionGeneration = app()?.getDocumentGeneration?.();
      if (!await confirmAccountAction({
        title: tr("Enregistrer une copie", "Save a copy"),
        message: tr(
          "Votre brouillon sera enregistré comme un nouveau scénario. La version distante sera conservée.",
          "Your draft will be saved as a new scenario. The remote version will be preserved."
        ),
        cancelLabel: tr("Revenir au brouillon", "Back to draft"),
        confirmLabel: tr("Enregistrer une copie", "Save a copy")
      })) return null;
      if (decisionGeneration !== app()?.getDocumentGeneration?.()
        || contentSignature(state) !== contentSignature(app().getState())) return null;
      designId = 0;
      expectedRevision = null;
    } else if (isSaved(state)) {
      return lastSaved.data;
    }
    if (automatic && designId <= 0 && !hasMeaningfulDesignContent(state)) return null;

    clearTimeout(autoSaveTimer);
    autoSaveTimer = null;
    const generation = app()?.getDocumentGeneration?.();
    const sourceId = Number(state.meta?.remoteDesignId || 0);
    const userId = authState.user.id;
    const signature = contentSignature(state);
    const stillCurrent = () => generation === app()?.getDocumentGeneration?.()
      && userId === authState.user?.id && sourceId === currentDesignId();
    const document = JSON.parse(signature);
    const title = String(state.meta?.name || "").trim() || tr("Production sans titre", "Untitled scenario");
    setAutoSaveStatus("saving", tr("Sauvegarde…", "Saving…"));
    saveInFlight = (async () => {
      try {
        const data = await fetchJson("save_design.php", {
          method: "POST",
          body: JSON.stringify({ design_id: designId, expected_revision: expectedRevision, title, document })
        });
        if (!stillCurrent()) return null;
        lastSaved = { generation, id: data.design.id, signature, data };
        app()?.updateMeta?.({
          remoteDesignId: data.design.id,
          remoteUpdatedAt: data.design.updatedAt,
          remoteRevision: data.design.revision,
          remoteSaveConflict: null,
          remoteDirty: contentSignature(app().getState()) !== signature
        }, { markDirty: false });
        setRemoteDesignUrl(data.design.id);
        setAutoSaveStatus("success", tr("Enregistré ✓", "Saved ✓"));
        return data;
      } catch (error) {
        if (!stillCurrent()) return null;
        if (error?.status === 409) {
          // Keep the revision that belongs to the local snapshot. Adopting
          // the server revision here would allow the next save to overwrite it.
          app()?.updateMeta?.({ remoteSaveConflict: sourceId }, { markDirty: false });
          showSaveConflict();
        } else {
          setAutoSaveStatus("error", tr("Échec", "Failed"));
          app()?.showNotice?.(error.message || tr("Sauvegarde distante impossible.", "Remote save failed."), "error");
        }
        return null;
      }
    })();
    let result;
    try {
      result = await saveInFlight;
    } finally {
      saveInFlight = null;
      if (result || !stillCurrent()) scheduleAutoSave();
    }
    return result;
  }

  async function autoSaveRemote() {
    return persistRemoteDesign(true);
  }

  async function saveRemoteDesign() {
    const data = await persistRemoteDesign();
    if (!data) return null;
    const message = tr(
      "Production sauvegardée sur votre compte. Ouvrez Scénarios pour la retrouver.",
      "Scenario saved to your account. Open Scenarios to find it again."
    );
    app()?.showNotice?.(message, "success");
    app()?.announce?.(message);
    return data;
  }

  function ensureSiteNavUi() {
    if (document.querySelector(".account-toolbar-cluster")) return;
    const navActions = $("site-nav-actions");
    if (!navActions) return;

    const cluster = document.createElement("div");
    cluster.className = "account-toolbar-cluster";
    cluster.id = "account-toolbar-cluster";
    cluster.innerHTML = `
      <a id="saved-designs-btn" class="nav-account-btn nav-account-icon-btn nav-saves-btn" href="my-designs.php" title="${tr("Scénarios", "Scenarios")}" aria-label="${tr("Scénarios", "Scenarios")}">
        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
      </a>
      <span id="account-pill" class="account-pill" style="display:none"></span>
      <div class="account-menu-wrap">
        <button id="account-menu-btn" class="nav-account-btn nav-account-icon-btn" type="button" title="${tr("Connexion", "Sign in")}" aria-label="${tr("Connexion", "Sign in")}">
          <i class="fa-regular fa-user" aria-hidden="true"></i>
        </button>
        <div id="account-menu" class="account-menu hidden" role="menu" aria-hidden="true"></div>
      </div>
    `;

    navActions.append(cluster);

    $("account-menu-btn")?.addEventListener("click", () => {
      if (!authState.user) {
        window.location.href = "login.php";
        return;
      }
      toggleAccountMenu();
    });

    document.addEventListener("click", (event) => {
      const wrap = document.querySelector(".account-menu-wrap");
      if (wrap && !wrap.contains(event.target)) {
        closeAccountMenu();
      }
    });
  }

  function renderAccountArea() {
    ensureSiteNavUi();

    const pill = $("account-pill");
    const button = $("account-menu-btn");
    const menu = $("account-menu");
    if (!pill || !button || !menu) return;

    if (!authState.user) {
      pill.innerHTML = `${tr("Compte", "Account")} <strong>${tr("non connecte", "not signed in")}</strong>`;
      button.innerHTML = `<i class="fa-regular fa-user" aria-hidden="true"></i>`;
      button.title = tr("Connexion", "Sign in");
      button.setAttribute("aria-label", tr("Connexion", "Sign in"));
      menu.innerHTML = "";
      syncSaveUi();
      return;
    }

    pill.innerHTML = `${tr("Compte", "Account")} <strong>${escapeHtml(authState.user.username || authState.user.email)}</strong>`;
    button.innerHTML = `<i class="fa-solid fa-user-check" aria-hidden="true"></i>`;
    button.title = tr("Compte", "Account");
    button.setAttribute("aria-label", tr("Compte", "Account"));
    menu.innerHTML = `
      <a class="account-menu-link" role="menuitem" href="profile.php">${tr("Profil", "Profile")}</a>
      ${String(authState.user.role) === "admin" ? `<a class="account-menu-link" role="menuitem" href="admin.php">${tr("Administration", "Admin")}</a>` : ""}
      <a class="account-menu-link" role="menuitem" href="logout.php">${tr("Deconnexion", "Sign out")}</a>
    `;
    syncSaveUi();
  }

  function toggleAccountMenu() {
    const menu = $("account-menu");
    if (!menu) return;
    const hidden = menu.classList.toggle("hidden");
    menu.setAttribute("aria-hidden", hidden ? "true" : "false");
  }

  function closeAccountMenu() {
    const menu = $("account-menu");
    if (!menu) return;
    menu.classList.add("hidden");
    menu.setAttribute("aria-hidden", "true");
  }

  async function maybeLoadRequestedDesign() {
    if (requestedRemoteDesignHandled) return;
    const params = new URLSearchParams(window.location.search);
    const designId = Number(params.get("remote_design_id") || 0);
    if (!Number.isFinite(designId) || designId <= 0) return;
    requestedRemoteDesignHandled = true;

    if (!authState.user) {
      const message = tr(
        "Connectez-vous pour ouvrir cette production sauvegardée.",
        "Sign in to open this saved scenario."
      );
      app()?.showNotice?.(message, "warning");
      app()?.announce?.(message);
      return;
    }

    await syncRemoteDesignFromServer(designId, true);
  }

  function hasLocalDraft(state) {
    // Old drafts have no marker: preserve them rather than assume they synced.
    return state?.meta?.remoteDirty !== false
      && (Number(state?.meta?.remoteDesignId || 0) > 0 || hasMeaningfulDesignContent(state));
  }

  async function syncRemoteDesignFromServer(designId, showLoadedMessage = false) {
    if (!Number.isFinite(designId) || designId <= 0) return false;
    while (remoteLoadInFlight || saveInFlight) await (remoteLoadInFlight || saveInFlight);
    const generation = app()?.getDocumentGeneration?.();
    const userId = authState.user?.id;
    clearTimeout(autoSaveTimer);
    autoSaveTimer = null;
    let canResumeAutoSave = false;
    remoteLoadInFlight = (async () => {
      try {
        const data = await fetchJson(`get_design.php?design_id=${encodeURIComponent(String(designId))}`);
        // Re-read after the request: typing during a slow load also creates a draft.
        if (generation !== app()?.getDocumentGeneration?.() || userId !== authState.user?.id) return false;
        const local = app()?.getState?.();
        const localId = Number(local?.meta?.remoteDesignId || 0);
        if (hasLocalDraft(local) || hasSaveConflict(local)) {
          const sameDesign = localId === designId;
          const sameRevision = Number.isSafeInteger(local.meta.remoteRevision)
            && local.meta.remoteRevision > 0 && local.meta.remoteRevision === data.design.revision;
          if (sameDesign && sameRevision && !hasSaveConflict(local)) {
            app()?.updateMeta?.({ remoteDirty: true }, { markDirty: false });
            setRemoteDesignUrl(designId);
            const message = tr(
              "Votre brouillon local a été restauré. La sauvegarde automatique va reprendre.",
              "Your local draft has been restored. Auto-save will resume."
            );
            app()?.showNotice?.(message, "success");
            app()?.announce?.(message);
            canResumeAutoSave = true;
            return true;
          }
          const loadRemote = await confirmAccountAction({
            title: tr("Brouillon non sauvegardé", "Unsaved draft"),
            message: sameDesign ? tr(
              "La version de votre brouillon ne correspond pas à la version distante. Charger la version distante remplacera vos modifications locales. Vous pouvez conserver votre brouillon pour l’enregistrer comme une copie.",
              "Your draft’s version does not match the remote version. Loading the remote version will replace your local changes. You can keep your draft and save it as a copy."
            ) : tr(
              "Vous avez des modifications qui ne sont pas encore sauvegardées sur votre compte. Ouvrir l’autre scénario remplacera ce brouillon. Conservez-le pour sauvegarder votre travail d’abord.",
              "You have changes that have not yet been saved to your account. Opening the other scenario will replace this draft. Keep it to save your work first."
            ),
            cancelLabel: tr("Conserver mon brouillon", "Keep my draft"),
            confirmLabel: sameDesign
              ? tr("Charger la version distante", "Load remote version")
              : tr("Ouvrir l’autre scénario", "Open other scenario")
          });
          if (generation !== app()?.getDocumentGeneration?.()
            || contentSignature(local) !== contentSignature(app().getState())) return false;
          if (!loadRemote) {
            if (localId > 0) setRemoteDesignUrl(localId);
            else clearRemoteDesignUrl();
            if (sameDesign) {
              app()?.updateMeta?.({ remoteSaveConflict: localId, remoteDirty: true }, { markDirty: false });
              showSaveConflict();
            } else {
              canResumeAutoSave = true;
            }
            return false;
          }
        }
        app()?.loadDocument?.(data.design.document, {
          remoteDesignId: data.design.id,
          remoteUpdatedAt: data.design.updatedAt,
          remoteRevision: data.design.revision,
          remoteSaveConflict: null,
          remoteDirty: false
        });
        lastSaved = {
          generation: app()?.getDocumentGeneration?.(),
          id: data.design.id,
          signature: contentSignature(app().getState()),
          data
        };
        canResumeAutoSave = true;
        setRemoteDesignUrl(data.design.id);
        if (showLoadedMessage) {
          const message = tr("Production chargée.", "Scenario loaded.");
          app()?.showNotice?.(message, "success");
          app()?.announce?.(message);
        }
        return true;
      } catch (error) {
        if (generation === app()?.getDocumentGeneration?.()) {
          app()?.showNotice?.(tr(
            "La version distante n’a pas pu être chargée. Votre travail local est conservé.",
            "The remote version could not be loaded. Your local work is preserved."
          ), "warning");
        }
        return false;
      }
    })();
    try {
      return await remoteLoadInFlight;
    } finally {
      remoteLoadInFlight = null;
      if (canResumeAutoSave || generation !== app()?.getDocumentGeneration?.()) scheduleAutoSave();
    }
  }

  async function maybeSyncCurrentRemoteDesign() {
    if (startupRemoteDesignSynced) return;
    if (!authState.user) return;
    const params = new URLSearchParams(window.location.search);
    const requestedDesignId = Number(params.get("remote_design_id") || 0);
    if (Number.isFinite(requestedDesignId) && requestedDesignId > 0) return;
    const designId = currentDesignId();
    if (!Number.isFinite(designId) || designId <= 0) return;
    startupRemoteDesignSynced = true;
    await syncRemoteDesignFromServer(designId, false);
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function bindSaveButton() {
    $("save-btn")?.addEventListener("click", saveToAccountOrLogin, true);
  }

  // ── Publish / Share ──────────────────────────────────────────

  function syncPublishUi() {
    const btn = $("publish-btn");
    if (!btn) return;
    btn.hidden = !authState.user;
  }

  const CC_LICENSES = [
    { code: "cc-by", label: "CC BY 4.0", fr: "Attribution", en: "Attribution" },
    { code: "cc-by-sa", label: "CC BY-SA 4.0", fr: "Attribution, partage dans les mêmes conditions", en: "Attribution, ShareAlike" },
    { code: "cc-by-nd", label: "CC BY-ND 4.0", fr: "Attribution, pas de modification", en: "Attribution, NoDerivatives" },
    { code: "cc-by-nc", label: "CC BY-NC 4.0", fr: "Attribution, pas d’utilisation commerciale", en: "Attribution, NonCommercial" },
    { code: "cc-by-nc-sa", label: "CC BY-NC-SA 4.0", fr: "Attribution, pas d’utilisation commerciale, partage dans les mêmes conditions", en: "Attribution, NonCommercial, ShareAlike" },
    { code: "cc-by-nc-nd", label: "CC BY-NC-ND 4.0", fr: "Attribution, pas d’utilisation commerciale, pas de modification", en: "Attribution, NonCommercial, NoDerivatives" }
  ];

  function openPublishModal(shareUrl, alreadyPublished, isListed = false, listingUrl = "share.php", licenseCode = null) {
    closePublishModal();

    const backdrop = document.createElement("div");
    backdrop.id = "publish-modal-backdrop";
    backdrop.className = "modal-backdrop";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");
    backdrop.setAttribute("aria-label", tr("Partager la production", "Share scenario"));

    const urlHtml = shareUrl
      ? `<div class="publish-url-row">
           <input id="publish-url-input" class="publish-url-input" type="text" readonly value="${escapeHtml(shareUrl)}" aria-label="${tr("Lien de partage", "Share link")}">
           <button id="publish-copy-btn" class="btn btn-light" type="button">${tr("Copier", "Copy")}</button>
         </div>`
      : "";

    const revokeHtml = alreadyPublished
      ? `<button id="publish-revoke-btn" class="btn" type="button" style="color:#b91c1c">${tr("Révoquer le lien", "Revoke link")}</button>`
      : "";

    const selectedLicense = CC_LICENSES.some((license) => license.code === licenseCode) ? licenseCode : "";
    const licenseOptions = [
      `<option value="" disabled ${selectedLicense === "" ? "selected" : ""}>${tr("Choisir une licence…", "Choose a license…")}</option>`,
      ...CC_LICENSES.map((license) => {
        const description = document.documentElement.lang === "en" ? license.en : license.fr;
        return `<option value="${license.code}" ${license.code === selectedLicense ? "selected" : ""}>${license.label} — ${escapeHtml(description)}</option>`;
      })
    ].join("");
    const licenseHtml = `
      <div id="publish-license-panel" class="publish-license-panel" ${isListed ? "" : "hidden"}>
        <div class="publish-license-choice">
          <label for="publish-license-select">${tr("Licence Creative Commons", "Creative Commons license")}</label>
          <select id="publish-license-select" ${isListed ? "required" : ""}>${licenseOptions}</select>
          <p class="publish-license-help">
            ${tr("Vous ne connaissez pas ces licences ?", "Not familiar with these licenses?")}
            <a href="https://creativecommons.org/chooser/" target="_blank" rel="noopener noreferrer">${tr("Utiliser l’outil officiel pour choisir", "Use the official license chooser")}</a>
            · <a href="https://creativecommons.org/cc-licenses/" target="_blank" rel="noopener noreferrer">${tr("Comparer les licences", "Compare licenses")}</a>
          </p>
          <p class="publish-license-warning">${tr("Une licence CC déjà accordée ne peut pas être révoquée pour les copies déjà reçues.", "A CC license already granted cannot be revoked for copies already received.")}</p>
        </div>
      </div>`;

    const listingHtml = `
      <section id="publish-catalog-option" class="publish-option ${isListed ? "is-active" : ""}" aria-labelledby="publish-catalog-title">
        <label class="publish-listing-choice">
          <input id="publish-listing-checkbox" type="checkbox" ${isListed ? "checked" : ""}
            aria-controls="publish-license-panel" aria-expanded="${isListed ? "true" : "false"}">
          <span>
            <strong id="publish-catalog-title">${tr("Publier aussi dans le catalogue", "Also publish in the catalog")}</strong>
            <small>${tr("Votre scénario devient visible par tous sur la page de partage et peut être importé par d’autres enseignants.", "Your scenario becomes visible to everyone on the shared scenarios page and can be imported by other teachers.")}</small>
          </span>
        </label>
        ${licenseHtml}
        ${shareUrl && isListed
          ? `<p class="publish-catalog-link"><a href="${escapeHtml(listingUrl)}">${tr("Voir le scénario dans le catalogue", "View the scenario in the catalog")}</a></p>`
          : ""}
      </section>`;

    backdrop.innerHTML = `
      <div class="modal-card publish-modal-card">
        <h2 class="modal-title">${tr("Partager la production", "Share scenario")}</h2>
        <p class="publish-modal-intro">${tr("Choisissez comment vous souhaitez diffuser votre scénario.", "Choose how you want to share your scenario.")}</p>
        <section class="publish-option publish-link-option" aria-labelledby="publish-link-title">
          <div class="publish-option-heading">
            <span class="publish-option-icon" aria-hidden="true"><i class="fa-solid fa-link"></i></span>
            <div>
              <h3 id="publish-link-title">${tr("Partager avec un lien", "Share with a link")}</h3>
              <p>${tr("Seules les personnes qui possèdent le lien peuvent consulter le scénario, en lecture seule. Aucune licence n’est nécessaire.", "Only people who have the link can view the scenario, read-only. No license is required.")}</p>
            </div>
          </div>
          ${shareUrl
            ? urlHtml
            : `<p class="publish-link-status">${tr("Un lien unique et non répertorié sera créé.", "A unique, unlisted link will be created.")}</p>`
          }
        </section>
        ${listingHtml}
        <div class="modal-actions" style="margin-top:20px">
          <button id="publish-confirm-btn" class="btn btn-primary" type="button">${shareUrl ? tr("Enregistrer", "Save") : tr("Créer le lien", "Create link")}</button>
          ${revokeHtml}
          <button id="publish-close-btn" class="btn btn-light" type="button">${tr("Fermer", "Close")}</button>
        </div>
      </div>`;

    document.body.appendChild(backdrop);

    $("publish-close-btn")?.addEventListener("click", closePublishModal);
    backdrop.addEventListener("click", (e) => { if (e.target === backdrop) closePublishModal(); });

    $("publish-copy-btn")?.addEventListener("click", () => {
      const input = $("publish-url-input");
      if (!input) return;
      navigator.clipboard?.writeText(input.value).then(() => {
        const btn = $("publish-copy-btn");
        if (btn) { btn.textContent = tr("Copié !", "Copied!"); setTimeout(() => { btn.textContent = tr("Copier", "Copy"); }, 2000); }
      }).catch(() => { input.select(); document.execCommand("copy"); });
    });

    const syncListingUi = () => {
      const checkbox = $("publish-listing-checkbox");
      const panel = $("publish-license-panel");
      const select = $("publish-license-select");
      const option = $("publish-catalog-option");
      const isCatalogListed = Boolean(checkbox?.checked);
      if (panel) panel.hidden = !isCatalogListed;
      if (select) {
        select.required = isCatalogListed;
        if (!isCatalogListed) select.setCustomValidity("");
      }
      checkbox?.setAttribute("aria-expanded", String(isCatalogListed));
      option?.classList.toggle("is-active", isCatalogListed);
    };
    $("publish-listing-checkbox")?.addEventListener("change", syncListingUi);
    syncListingUi();

    $("publish-confirm-btn")?.addEventListener("click", async () => {
      const isCatalogListed = Boolean($("publish-listing-checkbox")?.checked);
      const licenseSelect = $("publish-license-select");
      if (isCatalogListed && !licenseSelect?.value) {
        licenseSelect?.setCustomValidity(tr("Choisissez une licence Creative Commons.", "Choose a Creative Commons license."));
        licenseSelect?.reportValidity();
        licenseSelect?.focus();
        return;
      }
      licenseSelect?.setCustomValidity("");
      const confirmBtn = $("publish-confirm-btn");
      if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = shareUrl ? tr("Enregistrement…", "Saving…") : tr("Création…", "Creating…");
      }
      await doPublish();
    });
    $("publish-license-select")?.addEventListener("change", (event) => {
      event.currentTarget.setCustomValidity("");
    });

    $("publish-revoke-btn")?.addEventListener("click", async () => {
      if (!window.confirm(tr("Révoquer ce lien ? Les personnes qui l'ont reçu ne pourront plus y accéder.", "Revoke this link? Anyone who received it will lose access."))) return;
      const revokeBtn = $("publish-revoke-btn");
      if (revokeBtn) { revokeBtn.disabled = true; revokeBtn.textContent = tr("Révocation…", "Revoking…"); }
      await doUnpublish();
    });
  }

  function closePublishModal() {
    $("publish-modal-backdrop")?.remove();
  }

  async function doPublish() {
    const saved = await persistRemoteDesign();
    if (!saved) {
      closePublishModal();
      return;
    }

    try {
      const isCatalogListed = Boolean($("publish-listing-checkbox")?.checked);
      const data = await fetchJson("publish_design.php", {
        method: "POST",
        body: JSON.stringify({
          action: "publish",
          design_id: saved.design.id,
          is_listed: isCatalogListed,
          license_code: isCatalogListed ? ($("publish-license-select")?.value || "") : ""
        })
      });
      closePublishModal();
      openPublishModal(data.share_url, true, Boolean(data.is_listed), data.listing_url || "share.php", data.license_code);
    } catch (err) {
      app()?.showNotice?.(err.message || tr("Publication impossible.", "Publish failed."), "error");
      closePublishModal();
    }
  }

  async function doUnpublish() {
    const designId = currentDesignId();
    if (designId <= 0) { closePublishModal(); return; }
    try {
      await fetchJson("publish_design.php", {
        method: "POST",
        body: JSON.stringify({ action: "unpublish", design_id: designId })
      });
      closePublishModal();
      app()?.showNotice?.(tr("Lien révoqué.", "Link revoked."), "success");
    } catch (err) {
      app()?.showNotice?.(err.message || tr("Révocation impossible.", "Revoke failed."), "error");
      closePublishModal();
    }
  }

  function openPublishFlow(event) {
    if (event) { event.preventDefault?.(); event.stopImmediatePropagation?.(); }
    if (!authState.user) { window.location.href = "login.php"; return; }
    const designId = currentDesignId();
    if (designId <= 0) {
      openPublishModal(null, false, false);
      return;
    }
    fetchJson("publish_design.php", {
      method: "POST",
      body: JSON.stringify({ action: "status", design_id: designId })
    }).then((data) => {
      openPublishModal(data.share_url || null, Boolean(data.is_published), Boolean(data.is_listed), data.listing_url || "share.php", data.license_code);
    }).catch(() => {
      openPublishModal(null, false, false);
    });
  }

  function bindPublishButton() {
    $("publish-btn")?.addEventListener("click", openPublishFlow, true);
  }

  window.learningDesignerOpenSaves = openSavedDesignsOrLogin;
  window.learningDesignerSaveToAccount = saveToAccountOrLogin;
  window.learningDesignerClearRemoteDesignUrl = clearRemoteDesignUrl;

  document.addEventListener("DOMContentLoaded", () => {
    ensureSiteNavUi();
    bindSaveButton();
    bindPublishButton();
    syncSaveUi();
    syncPublishUi();
    refreshAuth();
    window.addEventListener("ld:statechange", scheduleAutoSave);
  });
})();
