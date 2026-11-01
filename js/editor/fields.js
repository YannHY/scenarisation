// Champs extensibles, aperçu Markdown et raccourcis de mise en forme.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createFields = ({
  t, escapeHtml, announce, getLearningOutcomes, getBloomTaxonomy,
  createLearningOutcome, deleteLearningOutcome
}) => {
const MARKDOWN_ACTIONS = [
  { id: "bold", text: "B", titleKey: "mdBold", code: "KeyB", shift: false, key: "B" },
  { id: "italic", text: "I", titleKey: "mdItalic", code: "KeyI", shift: false, key: "I" },
  { id: "heading", text: "H", titleKey: "mdHeading", code: "KeyH", shift: true, key: "H" },
  { id: "list", text: "-", titleKey: "mdList", code: "KeyL", shift: true, key: "L" },
  { id: "ordered-list", text: "1.", titleKey: "mdOrderedList", code: "Digit7", shift: true, key: "7" },
  { id: "quote", text: ">", titleKey: "mdQuote", code: "KeyQ", shift: true, key: "Q" },
  { id: "link", iconClass: "fa-solid fa-link", titleKey: "mdLink", code: "KeyK", shift: false, key: "K" }
];

function usesMacKeyboardShortcuts() {
  const platform = navigator.userAgentData?.platform || navigator.platform || "";
  return /Mac|iPhone|iPad|iPod/i.test(platform);
}

function markdownShortcutLabel(action) {
  const modifier = usesMacKeyboardShortcuts() ? "⌘" : "Ctrl+";
  const shift = action.shift ? (usesMacKeyboardShortcuts() ? "⇧" : "Shift+") : "";
  return `${modifier}${shift}${action.key}`;
}

function markdownAriaKeyShortcuts(action) {
  const suffix = `${action.shift ? "Shift+" : ""}${action.key}`;
  return `Meta+${suffix} Control+${suffix}`;
}

function markdownActionForKeyboardEvent(event) {
  if (event.isComposing || event.altKey || !(event.metaKey || event.ctrlKey)) return null;
  return MARKDOWN_ACTIONS.find((action) =>
    event.code === action.code && event.shiftKey === action.shift
  ) || null;
}

function handleMarkdownListEnter(textarea, event) {
  if (
    event.key !== "Enter" ||
    event.isComposing ||
    event.shiftKey ||
    event.altKey ||
    event.metaKey ||
    event.ctrlKey ||
    textarea.selectionStart !== textarea.selectionEnd
  ) return false;

  const value = textarea.value;
  const caret = textarea.selectionStart ?? value.length;
  const lineStart = value.lastIndexOf("\n", Math.max(0, caret - 1)) + 1;
  const nextLineBreak = value.indexOf("\n", caret);
  const lineEnd = nextLineBreak === -1 ? value.length : nextLineBreak;
  const currentLine = value.slice(lineStart, lineEnd);
  const beforeCaret = value.slice(lineStart, caret);
  const unordered = beforeCaret.match(/^(\s*)([-*])\s+(.*)$/);
  const ordered = beforeCaret.match(/^(\s*)(\d+)\.\s+(.*)$/);
  if (!unordered && !ordered) return false;

  event.preventDefault();
  const indent = (unordered || ordered)[1];
  const fullUnordered = currentLine.match(/^(\s*)([-*])\s*(.*)$/);
  const fullOrdered = currentLine.match(/^(\s*)(\d+)\.\s*(.*)$/);
  const fullMatch = unordered ? fullUnordered : fullOrdered;

  if (fullMatch && fullMatch[3].trim() === "") {
    const nextValue = `${value.slice(0, lineStart)}${indent}${value.slice(lineEnd)}`;
    const nextCaret = lineStart + indent.length;
    updateTextareaValue(textarea, nextValue, nextCaret);
    return true;
  }

  const marker = unordered ? unordered[2] : `${Number(ordered[2]) + 1}.`;
  const insertion = `\n${indent}${marker} `;
  const nextValue = `${value.slice(0, caret)}${insertion}${value.slice(caret)}`;
  updateTextareaValue(textarea, nextValue, caret + insertion.length);
  return true;
}


function ensureMarkdownToolbars(root = document) {
  root.querySelectorAll(".expandable-field").forEach((wrapper) => {
    if (wrapper.querySelector(".markdown-toolbar")) return;
    const textarea = wrapper.querySelector("textarea");
    if (!textarea) return;
    const toolbar = document.createElement("div");
    toolbar.className = "markdown-toolbar";
    toolbar.setAttribute("role", "toolbar");
    toolbar.setAttribute("aria-label", t("markdownToolbarLabel"));
    MARKDOWN_ACTIONS.forEach((action) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "markdown-tool-btn";
      button.dataset.mdAction = action.id;
      if (action.iconClass) {
        const icon = document.createElement("i");
        icon.className = action.iconClass;
        icon.setAttribute("aria-hidden", "true");
        button.appendChild(icon);
      } else {
        button.textContent = action.text;
      }
      toolbar.appendChild(button);
    });
    if (textarea.classList.contains("activity-description")) {
      toolbar.appendChild(createLearningOutcomesControl());
    }
    wrapper.insertBefore(toolbar, textarea);
  });
}

function createLearningOutcomesControl() {
  const control = document.createElement("div");
  control.className = "learning-outcomes-control";

  const button = document.createElement("button");
  button.type = "button";
  button.className = "markdown-tool-btn learning-outcomes-trigger";
  button.setAttribute("aria-haspopup", "dialog");
  button.setAttribute("aria-expanded", "false");
  button.innerHTML = '<i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>';

  const menu = document.createElement("div");
  menu.className = "learning-outcomes-menu hidden";
  menu.setAttribute("role", "dialog");
  menu.setAttribute("aria-label", t("learningOutcomesMenuLabel"));
  control.append(button, menu);
  localizeLearningOutcomesControl(control);
  return control;
}

function localizeLearningOutcomesControl(control) {
  const button = control.querySelector(".learning-outcomes-trigger");
  const label = t("insertLearningOutcome");
  if (button) {
    button.setAttribute("aria-label", label);
    button.setAttribute("title", label);
  }
}

function renderLearningOutcomesMenu(control) {
  const menu = control.querySelector(".learning-outcomes-menu");
  if (!menu) return;
  menu.replaceChildren();

  const heading = document.createElement("div");
  heading.className = "learning-outcomes-menu-heading";
  const title = document.createElement("strong");
  title.textContent = t("learningOutcomesMenuLabel");
  const hint = document.createElement("span");
  hint.textContent = t("learningOutcomesMenuHint");
  heading.append(title, hint);
  menu.appendChild(heading);

  const createButton = document.createElement("button");
  createButton.type = "button";
  createButton.className = "learning-outcome-create-trigger";
  createButton.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i><span></span>';
  createButton.querySelector("span").textContent = t("newLearningOutcome");
  menu.appendChild(createButton);

  const form = createLearningOutcomeForm();
  menu.appendChild(form);

  const outcomes = typeof getLearningOutcomes === "function" ? getLearningOutcomes() : [];
  if (!Array.isArray(outcomes) || outcomes.length === 0) {
    const empty = document.createElement("p");
    empty.className = "learning-outcomes-empty";
    empty.textContent = t("noLearningOutcomes");
    menu.appendChild(empty);
  } else {
    const list = document.createElement("div");
    list.className = "learning-outcomes-options";
    outcomes.forEach((outcome) => {
      const row = document.createElement("div");
      row.className = "learning-outcome-option-row";
      const item = document.createElement("button");
      item.type = "button";
      item.className = "learning-outcome-option";
      item.dataset.outcomeId = outcome.id || "";
      item.dataset.bloom = outcome.category || "";

      const tag = document.createElement("span");
      tag.className = "learning-outcome-option-tag";
      tag.textContent = outcome.verb || outcome.categoryLabel || "—";
      const text = document.createElement("span");
      text.className = "learning-outcome-option-text";
      text.textContent = outcome.text || tag.textContent;
      item.append(tag, text);

      const deleteButton = document.createElement("button");
      deleteButton.type = "button";
      deleteButton.className = "learning-outcome-option-delete";
      deleteButton.dataset.outcomeId = outcome.id || "";
      deleteButton.setAttribute("aria-label", `${t("deleteOutcome")} : ${outcome.text || tag.textContent}`);
      deleteButton.setAttribute("title", t("deleteOutcome"));
      deleteButton.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
      row.append(item, deleteButton);
      list.appendChild(row);
    });
    menu.appendChild(list);
  }
}

function createLearningOutcomeForm() {
  const form = document.createElement("div");
  form.className = "learning-outcome-create-form hidden";

  const intro = document.createElement("p");
  intro.textContent = t("newLearningOutcomeHint");

  const taxonomy = typeof getBloomTaxonomy === "function" ? getBloomTaxonomy() : [];
  const level = document.createElement("select");
  level.className = "learning-outcome-level-select";
  level.setAttribute("aria-label", t("bloomLevel"));
  level.appendChild(new Option(t("bloomLevelOptional"), ""));
  (Array.isArray(taxonomy) ? taxonomy : []).forEach((category) => {
    level.appendChild(new Option(category.label, category.id));
  });

  const verb = document.createElement("select");
  verb.className = "learning-outcome-verb-select";
  verb.setAttribute("aria-label", t("bloomVerb"));
  verb.disabled = true;
  verb.appendChild(new Option(t("bloomVerbOptional"), ""));

  const text = document.createElement("input");
  text.type = "text";
  text.className = "learning-outcome-new-text";
  text.setAttribute("aria-label", t("learningOutcomeText"));
  text.placeholder = t("learningOutcomeTextPlaceholder");

  const actions = document.createElement("div");
  actions.className = "learning-outcome-create-actions";
  const cancel = document.createElement("button");
  cancel.type = "button";
  cancel.className = "learning-outcome-create-cancel";
  cancel.textContent = t("cancel");
  const confirm = document.createElement("button");
  confirm.type = "button";
  confirm.className = "learning-outcome-create-confirm";
  confirm.textContent = t("createAndInsertOutcome");
  confirm.disabled = true;
  actions.append(cancel, confirm);
  form.append(intro, level, verb, text, actions);
  return form;
}

function updateLearningOutcomeVerbSelect(form) {
  const level = form.querySelector(".learning-outcome-level-select");
  const verb = form.querySelector(".learning-outcome-verb-select");
  const taxonomy = typeof getBloomTaxonomy === "function" ? getBloomTaxonomy() : [];
  const category = (Array.isArray(taxonomy) ? taxonomy : []).find((item) => item.id === level?.value);
  verb.replaceChildren(new Option(t("bloomVerbOptional"), ""));
  (category?.verbs || []).forEach((label) => verb.appendChild(new Option(label, label)));
  verb.disabled = !category;
}

function renderInlineMarkdown(value) {
  const lines = String(value || "").split("\n");
  const html = [];
  let listType = "";
  let paragraph = [];

  const inline = (text) => escapeHtml(text)
    .replace(/\*\*([^*\n]+)\*\*/g, "<strong>$1</strong>")
    .replace(/(^|[^*])\*([^*\n]+)\*/g, "$1<em>$2</em>")
    .replace(
      /\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:)[^\s)<]+)\)/gi,
      '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
    );
  const closeParagraph = () => {
    if (!paragraph.length) return;
    html.push(`<p>${paragraph.map(inline).join("<br />")}</p>`);
    paragraph = [];
  };
  const closeList = () => {
    if (!listType) return;
    html.push(`</${listType}>`);
    listType = "";
  };
  const openList = (type) => {
    closeParagraph();
    if (listType === type) return;
    closeList();
    html.push(`<${type}>`);
    listType = type;
  };

  lines.forEach((line) => {
    const trimmed = line.trim();
    if (!trimmed) {
      closeParagraph();
      closeList();
      return;
    }

    const heading = trimmed.match(/^##\s+(.+)$/);
    if (heading) {
      closeParagraph();
      closeList();
      html.push(`<h2>${inline(heading[1])}</h2>`);
      return;
    }

    const unordered = trimmed.match(/^[-*]\s+(.+)$/);
    if (unordered) {
      openList("ul");
      html.push(`<li>${inline(unordered[1])}</li>`);
      return;
    }

    const ordered = trimmed.match(/^\d+\.\s+(.+)$/);
    if (ordered) {
      openList("ol");
      html.push(`<li>${inline(ordered[1])}</li>`);
      return;
    }

    const quote = trimmed.match(/^>\s?(.+)$/);
    if (quote) {
      closeParagraph();
      closeList();
      html.push(`<blockquote>${inline(quote[1])}</blockquote>`);
      return;
    }

    closeList();
    paragraph.push(line);
  });

  closeParagraph();
  closeList();
  return html.join("");
}

function refreshMarkdownPreview(wrapper) {
  const textarea = wrapper?.querySelector("textarea");
  const preview = wrapper?.querySelector(".markdown-preview");
  if (!textarea || !preview) return;
  const value = textarea.value || "";
  preview.innerHTML = renderInlineMarkdown(value);
  wrapper.classList.toggle("preview-active", value.trim() !== "");
}

function ensureMarkdownPreviews(root = document) {
  root.querySelectorAll(".expandable-field").forEach((wrapper) => {
    const textarea = wrapper.querySelector("textarea");
    if (!textarea) return;
    let preview = wrapper.querySelector(".markdown-preview");
    if (!preview) {
      preview = document.createElement("div");
      preview.className = "markdown-preview";
      preview.setAttribute("aria-hidden", "true");
      textarea.insertAdjacentElement("afterend", preview);
    }
    refreshMarkdownPreview(wrapper);
  });
}

const AUTO_RESIZE_SELECTOR = ".session-title, .session-objectives, .session-intentions, .activity-description, .activity-instructions, .session-notes-input, .panel-textarea, .outcome-text";

/* scrollHeight excludes the border, but these textareas are border-box, so
   assigning it directly left the last line clipped by the border width. */
function autoResizeTextarea(el) {
  el.style.height = "auto";
  const styles = getComputedStyle(el);
  const border =
    parseFloat(styles.borderTopWidth) + parseFloat(styles.borderBottomWidth);
  el.style.height = el.scrollHeight + border + "px";
}

function initAutoResizeTextareas(root = document) {
  root.querySelectorAll(AUTO_RESIZE_SELECTOR).forEach(autoResizeTextarea);
}


function localizeExpandableFieldControls(root = document) {
  root.querySelectorAll(".markdown-toolbar").forEach((toolbar) => {
    toolbar.setAttribute("aria-label", t("markdownToolbarLabel"));
  });
  root.querySelectorAll(".markdown-tool-btn").forEach((button) => {
    const action = MARKDOWN_ACTIONS.find((item) => item.id === button.dataset.mdAction);
    if (!action) return;
    const label = t(action.titleKey);
    button.setAttribute("aria-label", label);
    button.setAttribute("title", `${label} (${markdownShortcutLabel(action)})`);
    button.setAttribute("aria-keyshortcuts", markdownAriaKeyShortcuts(action));
  });
  root.querySelectorAll(".expand-btn").forEach((button) => {
    const wrapper = button.closest(".expandable-field");
    const expanded = wrapper?.classList.contains("fullscreen");
    button.setAttribute("aria-label", expanded ? t("closeFullscreen") : t("fullscreen"));
    button.setAttribute("title", expanded ? t("closeFullscreen") : t("fullscreen"));
  });
  root.querySelectorAll(".learning-outcomes-control").forEach(localizeLearningOutcomesControl);
}


function getStoredSelection(wrapper, textarea) {
  const fallback = textarea.value.length;
  const start = Number.parseInt(wrapper.dataset.selectionStart || "", 10);
  const end = Number.parseInt(wrapper.dataset.selectionEnd || "", 10);
  return {
    start: Number.isInteger(start) ? start : (textarea.selectionStart ?? fallback),
    end: Number.isInteger(end) ? end : (textarea.selectionEnd ?? fallback)
  };
}

function rememberSelection(textarea) {
  const selectionHost = textarea?.closest(".expandable-field") || textarea;
  if (!selectionHost) return;
  selectionHost.dataset.selectionStart = String(textarea.selectionStart ?? textarea.value.length);
  selectionHost.dataset.selectionEnd = String(textarea.selectionEnd ?? textarea.value.length);
}

function updateTextareaValue(textarea, value, selectionStart, selectionEnd = selectionStart) {
  textarea.value = value;
  textarea.focus();
  textarea.setSelectionRange(selectionStart, selectionEnd);
  rememberSelection(textarea);
  textarea.dispatchEvent(new Event("input", { bubbles: true }));
}

function replaceSelection(textarea, wrapper, replacement, selectionStart, selectionEnd) {
  const { start, end } = getStoredSelection(wrapper, textarea);
  const nextValue = `${textarea.value.slice(0, start)}${replacement}${textarea.value.slice(end)}`;
  updateTextareaValue(textarea, nextValue, selectionStart, selectionEnd);
}

function wrapSelection(textarea, wrapper, before, after, placeholder) {
  const { start, end } = getStoredSelection(wrapper, textarea);
  const selected = textarea.value.slice(start, end);
  const content = selected || placeholder;
  const replacement = `${before}${content}${after}`;
  replaceSelection(
    textarea,
    wrapper,
    replacement,
    start + before.length,
    start + before.length + content.length
  );
}

function prefixSelectionLines(textarea, wrapper, prefix, placeholder) {
  const { start, end } = getStoredSelection(wrapper, textarea);
  const selected = textarea.value.slice(start, end);
  const content = selected || placeholder;
  const replacement = String(content)
    .split("\n")
    .map((line) => `${prefix}${line}`)
    .join("\n");
  replaceSelection(textarea, wrapper, replacement, start, start + replacement.length);
}

function applyMarkdownAction(textarea, actionId) {
  const wrapper = textarea.closest(".expandable-field") || textarea;
  const placeholders = {
    bold: t("mdPlaceholderBold"),
    italic: t("mdPlaceholderItalic"),
    heading: t("mdPlaceholderHeading"),
    list: t("mdPlaceholderList"),
    orderedList: t("mdPlaceholderOrderedList"),
    quote: t("mdPlaceholderQuote"),
    linkText: t("mdPlaceholderLinkText"),
    linkUrl: t("mdPlaceholderLinkUrl")
  };
  if (actionId === "bold") {
    wrapSelection(textarea, wrapper, "**", "**", placeholders.bold);
    return;
  }
  if (actionId === "italic") {
    wrapSelection(textarea, wrapper, "*", "*", placeholders.italic);
    return;
  }
  if (actionId === "heading") {
    prefixSelectionLines(textarea, wrapper, "## ", placeholders.heading);
    return;
  }
  if (actionId === "list") {
    prefixSelectionLines(textarea, wrapper, "- ", placeholders.list);
    return;
  }
  if (actionId === "ordered-list") {
    const { start, end } = getStoredSelection(wrapper, textarea);
    const selected = textarea.value.slice(start, end);
    const content = selected || placeholders.orderedList;
    const replacement = String(content)
      .split("\n")
      .map((line, i) => `${i + 1}. ${line}`)
      .join("\n");
    replaceSelection(textarea, wrapper, replacement, start, start + replacement.length);
    return;
  }
  if (actionId === "quote") {
    prefixSelectionLines(textarea, wrapper, "> ", placeholders.quote);
    return;
  }
  if (actionId === "link") {
    const { start, end } = getStoredSelection(wrapper, textarea);
    const label = textarea.value.slice(start, end) || placeholders.linkText;
    const replacement = `[${label}](${placeholders.linkUrl})`;
    const urlStart = start + label.length + 3;
    replaceSelection(
      textarea,
      wrapper,
      replacement,
      urlStart,
      urlStart + placeholders.linkUrl.length
    );
  }
}

function insertLearningOutcome(textarea, outcome) {
  const wrapper = textarea.closest(".expandable-field") || textarea;
  const { start, end } = getStoredSelection(wrapper, textarea);
  const tag = String(outcome?.verb || outcome?.categoryLabel || "").trim();
  const body = String(outcome?.text || "").trim();
  const content = tag && body ? `**${tag}** — ${body}` : (body || tag);
  if (!content) return;
  const before = textarea.value.slice(0, start);
  const after = textarea.value.slice(end);
  const leadingBreak = before && !before.endsWith("\n") ? "\n" : "";
  const trailingBreak = after && !after.startsWith("\n") ? "\n" : "";
  const replacement = `${leadingBreak}- ${content}${trailingBreak}`;
  replaceSelection(textarea, wrapper, replacement, start + replacement.length - trailingBreak.length);
  announce(t("learningOutcomeInserted"));
}

function closeLearningOutcomesMenus(except = null) {
  document.querySelectorAll(".learning-outcomes-control").forEach((control) => {
    if (control === except) return;
    control.querySelector(".learning-outcomes-menu")?.classList.add("hidden");
    control.querySelector(".learning-outcomes-trigger")?.setAttribute("aria-expanded", "false");
  });
}

function setupExpandableFields() {
  ensureMarkdownToolbars();
  ensureMarkdownPreviews();
  document.addEventListener("click", (event) => {
    const btn = event.target.closest(".expand-btn");
    if (!btn) return;
    const wrapper = btn.closest(".expandable-field");
    if (!wrapper) return;
    const textarea = wrapper.querySelector("textarea");
    const isFullscreen = !wrapper.classList.contains("fullscreen");
    toggleExpandableFieldFullscreen(wrapper, isFullscreen);
    if (!isFullscreen) {
      wrapper.classList.remove("is-editing");
      refreshMarkdownPreview(wrapper);
    }
    if (textarea) autoResizeTextarea(textarea);
    btn.textContent = isFullscreen ? "✕" : "⤢";
    const label = isFullscreen ? t("closeFullscreen") : t("fullscreen");
    btn.setAttribute("aria-label", label);
    btn.setAttribute("title", label);
    localizeExpandableFieldControls(wrapper);
    announce(label);
  });

  document.addEventListener("mousedown", (event) => {
    if (event.target.closest(".markdown-tool-btn, .learning-outcome-option, .learning-outcome-option-delete")) {
      event.preventDefault();
    }
  });

  document.addEventListener("click", (event) => {
    const button = event.target.closest(".markdown-tool-btn");
    if (!button) return;
    const wrapper = button.closest(".expandable-field");
    const textarea = wrapper?.querySelector("textarea");
    if (!textarea) return;
    applyMarkdownAction(textarea, button.dataset.mdAction || "");
  });

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest(".learning-outcomes-trigger");
    if (trigger) {
      const control = trigger.closest(".learning-outcomes-control");
      const menu = control?.querySelector(".learning-outcomes-menu");
      if (!control || !menu) return;
      const shouldOpen = menu.classList.contains("hidden");
      closeLearningOutcomesMenus(control);
      if (shouldOpen) renderLearningOutcomesMenu(control);
      menu.classList.toggle("hidden", !shouldOpen);
      trigger.setAttribute("aria-expanded", String(shouldOpen));
      return;
    }

    const option = event.target.closest(".learning-outcome-option");
    if (option) {
      const control = option.closest(".learning-outcomes-control");
      const textarea = control?.closest(".expandable-field")?.querySelector("textarea");
      const outcomes = typeof getLearningOutcomes === "function" ? getLearningOutcomes() : [];
      const outcome = Array.isArray(outcomes)
        ? outcomes.find((item) => String(item.id || "") === option.dataset.outcomeId)
        : null;
      if (textarea && outcome) insertLearningOutcome(textarea, outcome);
      closeLearningOutcomesMenus();
      return;
    }

    const deleteButton = event.target.closest(".learning-outcome-option-delete");
    if (deleteButton) {
      const control = deleteButton.closest(".learning-outcomes-control");
      if (typeof deleteLearningOutcome === "function") {
        deleteLearningOutcome(deleteButton.dataset.outcomeId || "");
      }
      if (control) renderLearningOutcomesMenu(control);
      announce(t("learningOutcomeDeleted"));
      return;
    }

    const createTrigger = event.target.closest(".learning-outcome-create-trigger");
    if (createTrigger) {
      const form = createTrigger.closest(".learning-outcomes-menu")?.querySelector(".learning-outcome-create-form");
      if (!form) return;
      form.classList.remove("hidden");
      createTrigger.classList.add("hidden");
      form.querySelector(".learning-outcome-new-text")?.focus();
      return;
    }

    const createCancel = event.target.closest(".learning-outcome-create-cancel");
    if (createCancel) {
      const menu = createCancel.closest(".learning-outcomes-menu");
      menu?.querySelector(".learning-outcome-create-form")?.classList.add("hidden");
      const createTriggerButton = menu?.querySelector(".learning-outcome-create-trigger");
      createTriggerButton?.classList.remove("hidden");
      createTriggerButton?.focus();
      return;
    }

    const createConfirm = event.target.closest(".learning-outcome-create-confirm");
    if (createConfirm) {
      const form = createConfirm.closest(".learning-outcome-create-form");
      const control = createConfirm.closest(".learning-outcomes-control");
      const textarea = control?.closest(".expandable-field")?.querySelector(".activity-description");
      const level = form?.querySelector(".learning-outcome-level-select");
      const verb = form?.querySelector(".learning-outcome-verb-select");
      const text = form?.querySelector(".learning-outcome-new-text")?.value.trim() || "";
      const taxonomy = typeof getBloomTaxonomy === "function" ? getBloomTaxonomy() : [];
      const category = (Array.isArray(taxonomy) ? taxonomy : []).find((item) => item.id === level?.value);
      if (!textarea || !text) return;
      const draft = {
        category: category?.id || "",
        categoryLabel: category?.label || "",
        verb: verb?.value || "",
        text
      };
      const outcome = typeof createLearningOutcome === "function"
        ? createLearningOutcome(draft)
        : draft;
      insertLearningOutcome(textarea, outcome);
      closeLearningOutcomesMenus();
      return;
    }

    if (!event.target.closest(".learning-outcomes-menu")) closeLearningOutcomesMenus();
  });

  document.addEventListener("change", (event) => {
    const level = event.target.closest(".learning-outcome-level-select");
    if (!level) return;
    const form = level.closest(".learning-outcome-create-form");
    if (form) updateLearningOutcomeVerbSelect(form);
  });

  document.addEventListener("input", (event) => {
    const input = event.target.closest(".learning-outcome-new-text");
    if (!input) return;
    const confirm = input.closest(".learning-outcome-create-form")?.querySelector(".learning-outcome-create-confirm");
    if (confirm) confirm.disabled = !input.value.trim();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const openControl = event.target.closest(".learning-outcomes-control") ||
      document.querySelector(".learning-outcomes-trigger[aria-expanded='true']")?.closest(".learning-outcomes-control");
    if (!openControl) return;
    closeLearningOutcomesMenus();
    openControl.querySelector(".learning-outcomes-trigger")?.focus();
  });

  document.addEventListener("keydown", (event) => {
    const textarea = event.target.closest(".expandable-field textarea, .grid-desc-input");
    if (!textarea) return;
    if (handleMarkdownListEnter(textarea, event)) return;
    const action = markdownActionForKeyboardEvent(event);
    if (!action) return;
    event.preventDefault();
    rememberSelection(textarea);
    applyMarkdownAction(textarea, action.id);
  });

  ["focusin", "input", "keyup", "mouseup"].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
      const textarea = event.target.closest(".expandable-field textarea");
      if (!textarea) return;
      rememberSelection(textarea);
      if (eventName === "focusin") {
        textarea.closest(".expandable-field")?.classList.add("is-editing");
      }
      if (eventName === "input") {
        refreshMarkdownPreview(textarea.closest(".expandable-field"));
      }
    });
  });

  document.addEventListener("focusout", (event) => {
    const textarea = event.target.closest(".expandable-field textarea");
    if (!textarea) return;
    const wrapper = textarea.closest(".expandable-field");
    window.setTimeout(() => {
      if (wrapper?.querySelector("textarea") !== document.activeElement) {
        wrapper?.classList.remove("is-editing");
        refreshMarkdownPreview(wrapper);
      }
    }, 0);
  });

  document.addEventListener("click", (event) => {
    const preview = event.target.closest(".markdown-preview");
    if (!preview) return;
    const textarea = preview.closest(".expandable-field")?.querySelector("textarea");
    if (textarea) textarea.focus();
  });
}

function toggleExpandableFieldFullscreen(wrapper, shouldOpen) {
  if (!wrapper) return;
  if (shouldOpen) {
    if (!wrapper.dataset.fullscreenPlaceholderId) {
      const placeholder = wrapper.cloneNode(true);
      placeholder.classList.add("expandable-field-placeholder");
      placeholder.setAttribute("aria-hidden", "true");
      placeholder.querySelectorAll("textarea").forEach((textarea, index) => {
        const sourceTextarea = wrapper.querySelectorAll("textarea")[index];
        textarea.value = sourceTextarea?.value || "";
        textarea.readOnly = true;
        textarea.tabIndex = -1;
      });
      placeholder.querySelectorAll("button").forEach((button) => {
        button.tabIndex = -1;
        button.disabled = true;
      });
      const placeholderId = `expandable-placeholder-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      placeholder.id = placeholderId;
      wrapper.dataset.fullscreenPlaceholderId = placeholderId;
      wrapper.parentNode?.insertBefore(placeholder, wrapper.nextSibling);
    }
    document.body.appendChild(wrapper);
    wrapper.classList.add("fullscreen");
    document.body.classList.add("fullscreen-editor-open");
    return;
  }

  const placeholder = document.getElementById(wrapper.dataset.fullscreenPlaceholderId || "");
  if (placeholder?.parentNode) {
    placeholder.parentNode.insertBefore(wrapper, placeholder);
    placeholder.remove();
  }
  delete wrapper.dataset.fullscreenPlaceholderId;
  wrapper.classList.remove("fullscreen");
  if (!document.querySelector(".expandable-field.fullscreen")) {
    document.body.classList.remove("fullscreen-editor-open");
  }
}

function restoreAllFullscreenExpandableFields() {
  document.querySelectorAll(".expandable-field.fullscreen").forEach((wrapper) => {
    toggleExpandableFieldFullscreen(wrapper, false);
    const btn = wrapper.querySelector(".expand-btn");
    const textarea = wrapper.querySelector("textarea");
    if (btn) {
      btn.textContent = "⤢";
      btn.setAttribute("aria-label", t("fullscreen"));
      btn.setAttribute("title", t("fullscreen"));
    }
    if (textarea) autoResizeTextarea(textarea);
    localizeExpandableFieldControls(wrapper);
  });
}
return {
  ensureMarkdownToolbars, ensureMarkdownPreviews, AUTO_RESIZE_SELECTOR,
  autoResizeTextarea, initAutoResizeTextareas, localizeExpandableFieldControls,
  setupExpandableFields, restoreAllFullscreenExpandableFields
};
};
})();
