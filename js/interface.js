(() => {
"use strict";
// Assemblage des modules ; ce fichier conserve l’état et les interactions de l’éditeur.
const {
  LEARNING_TYPES, ACTIVITY_TYPE_OPTIONS, GROUP_MODE_OPTIONS, TEACHING_OPTIONS,
  UNDEFINED_TEACHING_OPTION, TEACHING_VALUES, SYNC_OPTIONS, LOCATION_OPTIONS,
  LOCATION_VALUES, SCHOOL_SYSTEM_OPTIONS, SCHOOL_LEVEL_OPTIONS, PARTITION_TYPE_OPTIONS,
  EVAL_OPTIONS, AIAS_VERSION, AIAS_LEVELS, AIAS_TRIGGER_ICON, DEFAULT_DAY_HOURS,
  BLOOM_TAXONOMY, I18N, normalizeToken
} = window.LearningDesignerModules.config;
const {
  COMPETENCY_FRAMEWORKS, SELECTABLE_TOOLS_DATA, SELECTABLE_TOOL_CATEGORY_LABELS,
  SELECTABLE_TOOL_IDS_SET, COMPETENCY_REFERENCE_MAP, applyCompetencyTheme,
  applyLanguageTypography, competencyTooltip, formatCompetencyLabel,
  formatCompetencyShortCode
} = window.LearningDesignerModules.createCompetencies({
  COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
  COMPETENCY_FRAMEWORK_CATALOG_SOURCE,
  COMPETENCY_GREENCOMP_DETAIL_SOURCE: typeof COMPETENCY_GREENCOMP_DETAIL_SOURCE === "undefined" ? "" : COMPETENCY_GREENCOMP_DETAIL_SOURCE,
  COMPETENCY_DIGCOMP_DETAIL_SOURCE: typeof COMPETENCY_DIGCOMP_DETAIL_SOURCE === "undefined" ? "" : COMPETENCY_DIGCOMP_DETAIL_SOURCE,
  normalizeToken, currentLang
});

let uid = 0;
const nextId = () => `id-${Date.now()}-${uid++}`;
/**
 * Returns a debounced version of fn that fires after `delay` ms of inactivity.
 * @param {Function} fn
 * @param {number} delay - milliseconds
 */
function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}
const DEFAULT_META = {
  name: "",
  uiLanguage: "fr",
  dayHours: DEFAULT_DAY_HOURS,
  learningDays: 0,
  learningHours: 0,
  learningMinutes: 0,
  modeDelivery: "",
  schoolSystem: "",
  schoolLevel: "",
  sizeClass: "",
  designers: "",
  trainers: "",
  description: "",
  command: "",
  personas: "",
  sliders: [],
  activeTab: "settings",
  boardLayout: "columns"
};

const defaultPartitionLineConfig = () => [
  { type: "locationMode", label: "En classe", value: "onsite", visible: true },
  { type: "locationMode", label: "Sur site", value: "location_based", visible: true },
  { type: "locationMode", label: "En ligne", value: "online", visible: true },
  { type: "locationMode", label: "Hybride", value: "hybrid", visible: true },
  { type: "locationMode", label: "Autre", value: "other", visible: true }
];

const defaultState = () => ({
  allNotesExpanded: false,
  intentionsCollapsed: false,
  topPanelCollapsed: true,
  meta: { ...DEFAULT_META, sliders: [] },
  sessions: [],
  partitionLineConfig: defaultPartitionLineConfig()
});

const board = document.getElementById("board");
const sessionTpl = document.getElementById("session-template");
const activityTpl = document.getElementById("activity-template");

const boardLayoutToggle = document.getElementById("board-layout-toggle");
const boardLayoutListBtn = document.getElementById("board-layout-list-btn");
const boardLayoutColumnsBtn = document.getElementById("board-layout-columns-btn");
const boardLayoutListText = document.getElementById("board-layout-list-text");
const boardLayoutColumnsText = document.getElementById("board-layout-columns-text");
const boardLayoutGridBtn  = document.getElementById("board-layout-grid-btn");
const boardLayoutGridText = document.getElementById("board-layout-grid-text");
const newDesignBtn = document.getElementById("new-design-btn");
const navNewDesignBtn = document.getElementById("nav-new-design-btn");
const importDesignBtn = document.getElementById("import-design-btn");
const exportDesignBtn = document.getElementById("export-design-btn");
const infoBtn = document.getElementById("info-btn");
const saveBtn = document.getElementById("save-btn");
const importFileInput = document.getElementById("import-file-input");
const importModalBackdrop = document.getElementById("import-modal-backdrop");
const importModalCancelBtn = document.getElementById("import-modal-cancel-btn");
const importFileBtn = document.getElementById("import-file-btn");
const importDropZone = document.getElementById("import-drop-zone");
const importDropTitle = document.getElementById("import-drop-title");
const importDropHint = document.getElementById("import-drop-hint");
const importModelsSearch = document.getElementById("import-models-search");
const importModelsFamily = document.getElementById("import-models-family");
const importModelsList = document.getElementById("import-models-list");
const importModelsStatus = document.getElementById("import-models-status");
const importModelsLink = document.getElementById("import-models-link");
const importModal = importModalBackdrop?.querySelector(".import-modal");
const importModelPreview = document.getElementById("import-model-preview");
const importModelPreviewEyebrow = document.getElementById("import-model-preview-eyebrow");
const importModelPreviewTitle = document.getElementById("import-model-preview-title");
const importModelPreviewSummary = document.getElementById("import-model-preview-summary");
const importModelPreviewChips = document.getElementById("import-model-preview-chips");
const importModelPreviewStatus = document.getElementById("import-model-preview-status");
const importModelPreviewContent = document.getElementById("import-model-preview-content");
const importModelPreviewBackBtn = document.getElementById("import-model-preview-back-btn");
const importModelPreviewUseBtn = document.getElementById("import-model-preview-use-btn");
const langSelect = document.getElementById("lang-select");
const languageButton = document.querySelector(".nav-language-toggle");
const srStatus = document.getElementById("sr-status");
const appTitle = document.getElementById("app-title");
const topPanel = document.getElementById("top-panel");
const topPanelBody = document.getElementById("top-panel-body");
const topPanelToggleBtn = document.getElementById("top-panel-toggle-btn");
const topTabSettings = document.getElementById("top-tab-settings");
const topTabAnalysis = document.getElementById("top-tab-analysis");
const topTabChronology = document.getElementById("top-tab-chronology");
const topTabSlider = document.querySelector(".top-tab-slider");
const timelineView = document.getElementById("timeline-view");
const analysisView = document.getElementById("analysis-view");
const chronologyView = document.getElementById("chronology-view");
const partitionConfigModalBackdrop = document.getElementById("partition-config-modal-backdrop");
let partitionConfigDraft = [];
const metaNameInput = document.getElementById("meta-name");
const metaLearningDaysInput = document.getElementById("meta-learning-days");
const metaLearningHoursInput = document.getElementById("meta-learning-hours");
const metaLearningMinutesInput = document.getElementById("meta-learning-minutes");
const metaDesignedDaysInput = document.getElementById("meta-designed-days");
const metaDesignedHoursInput = document.getElementById("meta-designed-hours");
const metaDesignedMinutesInput = document.getElementById("meta-designed-minutes");
const metaDeliverySelect = document.getElementById("meta-delivery");
const metaSchoolSystemSelect = document.getElementById("meta-school-system");
const metaLevelSelect = document.getElementById("meta-level");
const metaDayHoursInput = document.getElementById("meta-day-hours");
const metaSizeClassInput = document.getElementById("meta-size-class");
const metaDesignersInput = document.getElementById("meta-designers");
const metaTrainersInput = document.getElementById("meta-trainers");
const metaDescriptionInput = document.getElementById("meta-description");
const metaCommandInput = document.getElementById("meta-command");
const metaPersonasInput = document.getElementById("meta-personas");
const outcomesListEl = document.getElementById("outcomes-list");
const addOutcomeBtn = document.getElementById("add-outcome-btn");
const newDesignModalBackdrop = document.getElementById("new-design-modal-backdrop");
const newDesignModalMsg = document.getElementById("new-design-modal-msg");
const newDesignCancelBtn = document.getElementById("new-design-cancel-btn");
const newDesignConfirmBtn = document.getElementById("new-design-confirm-btn");
const bloomModalBackdrop = document.getElementById("bloom-modal-backdrop");
const bloomCategoryList = document.getElementById("bloom-category-list");
const bloomAddBtn = document.getElementById("bloom-add-btn");
const bloomCancelBtn = document.getElementById("bloom-cancel-btn");
const infoModalBackdrop = document.getElementById("info-modal-backdrop");
const infoModalCloseBtn = document.getElementById("info-modal-close-btn");
const exportScopeLabel = document.getElementById("export-scope-label");
const exportScopeFullInput = document.getElementById("export-scope-full-input");
const exportScopeStudentsInput = document.getElementById("export-scope-students-input");
const exportMomentsDetails = document.getElementById("export-moments-details");
const exportMomentsLabel = document.getElementById("export-moments-label");
const exportMomentsAllInput = document.getElementById("export-moments-all-input");
const exportMomentsAllLabel = document.getElementById("export-moments-all-label");
const exportMomentsList = document.getElementById("export-moments-list");
const exportMomentsSummary = document.getElementById("export-moments-summary");
const exportMomentsEmpty = document.getElementById("export-moments-empty");
const exportModalBackdrop = document.getElementById("export-modal-backdrop");
const exportFormatSelect = document.getElementById("export-format-select");
const exportFilenameInput = document.getElementById("export-filename-input");
const exportModalCancelBtn = document.getElementById("export-modal-cancel-btn");
const exportModalConfirmBtn = document.getElementById("export-modal-confirm-btn");
const exportPreviewDetails = document.getElementById("export-preview-details");
const exportPreviewLabel = document.getElementById("export-preview-label");
const exportResultText = document.getElementById("export-result-text");
const exportResultCopyBtn = document.getElementById("export-result-copy-btn");
const aiasModalBackdrop = document.getElementById("aias-modal-backdrop");
const aiasModalTitle = document.getElementById("aias-modal-title");
const aiasModalIntro = document.getElementById("aias-modal-intro");
const aiasModalStatusOptions = document.getElementById("aias-modal-status-options");
const aiasModalLevels = document.getElementById("aias-modal-levels");
const aiasModalAttributionPrefix = document.getElementById("aias-modal-attribution-prefix");
const aiasModalCloseBtn = document.getElementById("aias-modal-close-btn");

const LD_STORAGE_KEY_PREFIX = "ld_state_v2_";
const LD_LANGUAGE_STORAGE_KEY = "learningDesignerLang";
let activeStorageKey = null;
let storageScopeReady = false;
let activeAiasTrigger = null;
let activeAiasActivity = null;

function preferredInterfaceLanguage(fallback = "fr") {
  const normalizedFallback = fallback === "en" ? "en" : "fr";
  try {
    const savedLanguage = localStorage.getItem(LD_LANGUAGE_STORAGE_KEY);
    if (savedLanguage === "fr" || savedLanguage === "en") return savedLanguage;
  } catch (_) {}
  return normalizedFallback;
}

let state = defaultState();
let documentGeneration = 0;
state.meta.uiLanguage = preferredInterfaceLanguage(state.meta.uiLanguage);
let dragState = null;
let activeModalBackdrop = null;
let previousFocusedElement = null;
let exportPreviewObjectUrl = "";
let exportScope = "full";
let exportSessionIds = null;

const {
  normalizeExportScope, exportSessionKey, getExportSessionEntries,
  buildStudentInstructionsData, buildMarkdownExport, buildHtmlExportDocument,
  buildWordExportDocument, SPREADSHEET_COLUMNS, buildExcelExportDocument
} = window.LearningDesignerModules.createExports({
  escapeHtml, getState: () => state, totalSessionMinutes, splitMinutesToPedagogicalTime,
  getDayHours, labelForDeliveryMode, labelForSchoolSystem, labelForSchoolLevel,
  labelForType, labelForGroupMode, labelForTeachingMode, labelForSyncMode,
  labelForLocationMode, labelForEvaluationMode, aiasSummary, SELECTABLE_TOOLS_DATA,
  formatCompetencyLabel, defaultSessionTitle, slidersToString, formatCompetencyShortCode
});

const {
  CSV_SCHOOL_SYSTEM_LOOKUP, lookupSchoolLevel, lookupValue, isLegacyLdjDocument,
  buildStateFromLegacyLdj, buildStateFromCsv, buildStateFromMarkdown
} = window.LearningDesignerModules.createImports({
  normalizeToken, SPREADSHEET_COLUMNS, I18N, SCHOOL_SYSTEM_OPTIONS, SCHOOL_LEVEL_OPTIONS,
  DEFAULT_DAY_HOURS, normalizePedagogicalTime, createNewDesignState,
  toPlainTextareaValue, nextId, currentLang, defaultSessionTitle, normalizeActivity,
  hydrateState, parseAiasValue, defaultAiasState
});

const {
  renderAnalysisPanel
} = window.LearningDesignerModules.createAnalysis({
  getState: () => state, normalizeAiasState, t, currentLang, normalizePedagogicalTime,
  getDayHours, TEACHING_VALUES, LOCATION_VALUES, LEARNING_TYPES, AIAS_LEVELS
});

const {
  ensureMarkdownToolbars, ensureMarkdownPreviews, AUTO_RESIZE_SELECTOR,
  autoResizeTextarea, initAutoResizeTextareas, localizeExpandableFieldControls,
  setupExpandableFields, restoreAllFullscreenExpandableFields
} = window.LearningDesignerModules.createFields({
  t, escapeHtml, announce
});


function currentLang() {
  return state?.meta?.uiLanguage === "en" ? "en" : "fr";
}

function t(key) {
  const lang = currentLang();
  return I18N[lang][key] || I18N.fr[key] || key;
}

function localizedSchoolLabel(option) {
  return option?.labels?.[currentLang()] || option?.labels?.fr || "";
}

function schoolLevelsFor(system) {
  return SCHOOL_LEVEL_OPTIONS[system] || [];
}

function schoolLevelOption(level, system = "") {
  if (system) {
    return schoolLevelsFor(system).find((option) => option.value === level) || null;
  }
  for (const options of Object.values(SCHOOL_LEVEL_OPTIONS)) {
    const option = options.find((candidate) => candidate.value === level);
    if (option) return option;
  }
  return null;
}

function schoolSystemForLevel(level) {
  return Object.keys(SCHOOL_LEVEL_OPTIONS).find((system) => schoolLevelOption(level, system)) || "";
}

function renderSchoolLevelOptions() {
  if (!metaLevelSelect) return;
  const system = state.meta.schoolSystem;
  const levels = schoolLevelsFor(system);
  metaLevelSelect.replaceChildren();

  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.textContent = system ? t("metaLevelPlaceholder") : t("metaLevelChooseSystemFirst");
  metaLevelSelect.appendChild(placeholder);

  levels.forEach((level) => {
    const option = document.createElement("option");
    option.value = level.value;
    option.textContent = localizedSchoolLabel(level);
    metaLevelSelect.appendChild(option);
  });

  metaLevelSelect.disabled = !system;
  metaLevelSelect.value = schoolLevelOption(state.meta.schoolLevel, system)
    ? state.meta.schoolLevel
    : "";
}

function setButtonLabel(button, iconClass, text) {
  if (!button) return;
  button.innerHTML = `<span class="btn-label"><i class="${iconClass} btn-icon-inline" aria-hidden="true"></i>${escapeHtml(text)}</span>`;
}

function setSessionNotesButtonLabel(button, expanded) {
  if (!button) return;
  setButtonLabel(button, expanded ? "fa-solid fa-chevron-up" : "fa-solid fa-chevron-down", t("sessionNotes"));
}

function getBoardLayout() {
  const v = state?.meta?.boardLayout;
  if (v === "list") return "list";
  if (v === "grid") return "grid";
  return "columns";
}

function setBoardLayout(layout) {
  const allowed = ["list", "columns", "grid"];
  const nextLayout = allowed.includes(layout) ? layout : "columns";
  if (getBoardLayout() === nextLayout) return;
  state.meta.boardLayout = nextLayout;
  saveState();
  render();
}

function defaultSessionTitle(index1Based) {
  return `${t("sessionPrefix")} ${index1Based}`;
}

function announce(message) {
  if (!message || !srStatus) return;
  srStatus.textContent = "";
  window.setTimeout(() => {
    srStatus.textContent = message;
  }, 10);
}

let noticeTimeoutId = 0;
function ensureNoticeHost() {
  let host = document.getElementById("app-notice-host");
  if (host) return host;
  host = document.createElement("div");
  host.id = "app-notice-host";
  host.className = "app-notice-host";
  host.setAttribute("aria-hidden", "true");
  document.body.appendChild(host);
  return host;
}

function showNotice(message, kind = "info") {
  if (!message) return;
  announce(message);
  const host = ensureNoticeHost();
  const notice = document.createElement("div");
  notice.className = `app-notice app-notice-${kind}`;
  notice.textContent = message;
  host.replaceChildren(notice);
  window.clearTimeout(noticeTimeoutId);
  noticeTimeoutId = window.setTimeout(() => {
    if (host.firstChild === notice) {
      host.replaceChildren();
    }
  }, 4200);
}

function shortLabel(label) {
  return String(label || "")
    .split(/[ /-]+/)
    .filter(Boolean)[0] || String(label || "");
}

function migrateActivityNotesToSession(session) {
  if (!session || !Array.isArray(session.activities)) return;
  const migratedNotes = session.activities
    .map((activity, index) => {
      const note = toPlainTextareaValue(activity?.notes).trim();
      if (!note) return "";
      activity.notes = "";
      return `Activité ${index + 1}:\n${note}`;
    })
    .filter(Boolean);
  if (!migratedNotes.length) return;
  session.notes = [toPlainTextareaValue(session.notes).trim(), ...migratedNotes]
    .filter(Boolean)
    .join("\n\n");
}

function refreshLocalizedCatalogs() {
  LEARNING_TYPES.forEach((type) => {
    type.label = t(`lt_${type.id}`);
  });

  ACTIVITY_TYPE_OPTIONS.forEach((option) => {
    option.label = t(`lt_${option.value}`);
    option.short = shortLabel(option.label);
  });

  GROUP_MODE_OPTIONS.forEach((option) => {
    if (option.value === "whole") option.label = t("group_whole");
    if (option.value === "subgroups") option.label = t("group_subgroups");
    if (option.value === "individual") option.label = t("group_individual");
    option.short = shortLabel(option.label);
  });

  TEACHING_OPTIONS.forEach((option) => {
    option.label = t(`teaching_${option.value}`);
    option.description = t(`teaching_${option.value}_description`);
    option.short = shortLabel(option.label);
  });
  UNDEFINED_TEACHING_OPTION.label = t("teaching_undefined");
  UNDEFINED_TEACHING_OPTION.short = shortLabel(UNDEFINED_TEACHING_OPTION.label);

  SYNC_OPTIONS.forEach((option) => {
    if (option.value === "sync") option.label = t("sync_sync");
    if (option.value === "async") option.label = t("sync_async");
    option.short = shortLabel(option.label);
  });

  LOCATION_OPTIONS.forEach((option) => {
    if (option.value === "onsite") option.label = t("activityModeClassroom");
    if (option.value === "location_based") option.label = t("activityModeLocation");
    if (option.value === "online") option.label = t("activityModeOnline");
    if (option.value === "hybrid") option.label = t("activityModeBlended");
    if (option.value === "other") option.label = t("activityModeOther");
    option.short = shortLabel(option.label);
  });

  state.partitionLineConfig.forEach((line) => {
    if (line.type !== "locationMode") return;
    const option = LOCATION_OPTIONS.find((candidate) => candidate.value === line.value);
    if (option) line.label = option.label;
  });

  EVAL_OPTIONS.forEach((option) => {
    if (option.value === "none") option.label = t("eval_none");
    if (option.value === "diagnostic") option.label = t("eval_diagnostic");
    if (option.value === "formative") option.label = t("eval_formative");
    if (option.value === "summative") option.label = t("eval_summative");
    if (option.value === "certificative") option.label = t("eval_certificative");
    option.short = shortLabel(option.label);
  });
}

function applyLocalizedUI() {
  refreshLocalizedCatalogs();
  document.documentElement.lang = currentLang();
  try {
    localStorage.setItem(LD_LANGUAGE_STORAGE_KEY, currentLang());
  } catch (_) {}
  document.title = t("docTitle");
  if (langSelect) langSelect.value = currentLang();
  if (languageButton) {
    const isEnglish = currentLang() === "en";
    languageButton.querySelector(".nav-language-label").textContent = isEnglish ? "EN" : "FR";
    const actionLabel = isEnglish ? "Switch to French" : "Passer en anglais";
    languageButton.setAttribute("aria-label", actionLabel);
    languageButton.setAttribute("title", actionLabel);
  }
  document.getElementById("skip-link").textContent = t("skipLink");
  document.querySelector(".toolbar").setAttribute("aria-label", t("toolbarRegion"));
  appTitle.textContent = t("appTitle");
  topTabSettings.textContent = t("tabSettings");
  topTabAnalysis.textContent = t("tabAnalysis");
  topTabChronology.textContent = t("tabChronology");
  document.getElementById("chronology-title").textContent = t("chronologyTitle");
  const partCfgTitle = document.getElementById("partition-config-modal-title");
  if (partCfgTitle) partCfgTitle.textContent = t("partitionConfigTitle");
  const partCfgDesc = document.getElementById("partition-config-modal-desc");
  if (partCfgDesc) partCfgDesc.textContent = t("partitionConfigDesc");
  const partAddSection = document.getElementById("partition-add-section-label");
  if (partAddSection) partAddSection.textContent = t("partitionAddLineSection");
  const partCfgCancel = document.getElementById("partition-config-cancel-btn");
  if (partCfgCancel) partCfgCancel.textContent = t("cancel");
  const partCfgSave = document.getElementById("partition-config-save-btn");
  if (partCfgSave) partCfgSave.textContent = t("validate");
  const partAddBtn = document.getElementById("partition-add-line-btn");
  if (partAddBtn) partAddBtn.textContent = t("partitionAdd");
  document.getElementById("new-design-modal-title").textContent = t("newDesignModalTitle");
  if (navNewDesignBtn) {
    navNewDesignBtn.setAttribute("aria-label", t("newDesignModalTitle"));
    navNewDesignBtn.setAttribute("title", t("newDesignModalTitle"));
  }
  newDesignModalMsg.textContent = t("newDesignModalMsg");
  newDesignCancelBtn.textContent = t("cancel");
  newDesignConfirmBtn.textContent = t("newDesignModalConfirm");
  document.getElementById("analysis-title").textContent = t("analysisTitle");
  document.getElementById("analysis-learning-title").textContent = t("groupTitleType");
  document.getElementById("analysis-delivery-title").textContent = t("groupTitleMode");
  document.getElementById("analysis-teacher-title").textContent = t("groupTitleTeaching");
  document.getElementById("analysis-sync-title").textContent = t("groupTitlePacing");
  document.getElementById("analysis-eval-title").textContent = t("groupTitleEvaluation");
  document.getElementById("analysis-group-title").textContent = t("groupTitleGroup");
  document.getElementById("analysis-aias-title").textContent = t("analysisAiasTitle");
  document.getElementById("label-meta-name").textContent = t("metaNameLabel");
  document.getElementById("label-meta-learning").textContent = t("metaLearningLabel");
  document.getElementById("label-meta-designed").textContent = t("metaDesignedLabel");
  document.getElementById("label-meta-day-hours").textContent = t("metaDayLabel");
  document.getElementById("label-meta-description").textContent = t("metaDescriptionLabel");
  document.getElementById("label-meta-command").textContent = t("metaCommandLabel");
  document.getElementById("label-meta-delivery").textContent = t("metaDeliveryLabel");
  document.getElementById("label-meta-school-system").textContent = t("metaSchoolSystemLabel");
  document.getElementById("label-meta-level").textContent = t("metaLevelLabel");
  document.getElementById("opt-meta-school-system-france").textContent = t("schoolSystemFrance");
  document.getElementById("opt-meta-school-system-switzerland").textContent = t("schoolSystemSwitzerland");
  document.getElementById("opt-meta-school-system-us").textContent = t("schoolSystemUnitedStates");
  document.getElementById("opt-meta-school-system-belgium-french").textContent = t("schoolSystemBelgiumFrench");
  document.getElementById("opt-meta-school-system-belgium-flemish").textContent = t("schoolSystemBelgiumFlemish");
  document.getElementById("opt-meta-school-system-belgium-german").textContent = t("schoolSystemBelgiumGerman");
  document.getElementById("opt-meta-school-system-uk-england").textContent = t("schoolSystemUnitedKingdomEngland");
  document.getElementById("opt-meta-school-system-uk-wales").textContent = t("schoolSystemUnitedKingdomWales");
  document.getElementById("opt-meta-school-system-uk-scotland").textContent = t("schoolSystemUnitedKingdomScotland");
  document.getElementById("opt-meta-school-system-uk-northern-ireland").textContent = t("schoolSystemUnitedKingdomNorthernIreland");
  document.getElementById("opt-meta-school-system-european-schools").textContent = t("schoolSystemEuropeanSchools");
  document.getElementById("opt-meta-school-system-isced").textContent = t("schoolSystemIsced");
  document.getElementById("optgroup-meta-school-systems-national").label = t("schoolSystemsNationalGroup");
  document.getElementById("optgroup-meta-school-systems-transnational").label = t("schoolSystemsTransnationalGroup");
  document.getElementById("optgroup-meta-school-systems-international").label = t("schoolSystemsInternationalGroup");
  metaSchoolSystemSelect.value = state.meta.schoolSystem;
  renderSchoolLevelOptions();
  document.getElementById("label-meta-size-class").textContent = t("metaSizeLabel");
  document.getElementById("label-meta-designers").textContent = t("metaDesignersLabel");
  document.getElementById("label-meta-trainers").textContent = t("metaTrainersLabel");
  document.getElementById("label-meta-personas").textContent = t("metaPersonasLabel");
  document.getElementById("label-meta-outcomes").textContent = t("outcomesLabel");
  document.querySelectorAll("[data-tooltip-i18n]").forEach((element) => {
    element.dataset.tooltip = t(element.dataset.tooltipI18n);
  });
  document.getElementById("unit-learning-days").textContent = t("unitDays");
  document.getElementById("unit-learning-hours").textContent = t("unitHours");
  document.getElementById("unit-learning-minutes").textContent = t("unitMinutes");
  document.getElementById("unit-designed-days").textContent = t("unitDays");
  document.getElementById("unit-designed-hours").textContent = t("unitHours");
  document.getElementById("unit-designed-minutes").textContent = t("unitMinutes");
  document.getElementById("unit-day-hours").textContent = t("unitHours");
  document.getElementById("opt-meta-delivery-empty").textContent = "";
  document.getElementById("opt-meta-delivery-onsite").textContent = t("modeOnsite");
  document.getElementById("opt-meta-delivery-online").textContent = t("modeOnline");
  document.getElementById("opt-meta-delivery-hybrid").textContent = t("modeHybrid");
  const toggleLabel = state.topPanelCollapsed ? t("expandPanel") : t("collapsePanel");
  topPanelToggleBtn.setAttribute("aria-label", toggleLabel);
  topPanelToggleBtn.setAttribute("title", toggleLabel);
  updateResponsiveButtonLabels();
  boardLayoutToggle.setAttribute("aria-label", t("viewModeLabel"));
  boardLayoutListText.textContent = t("viewList");
  boardLayoutColumnsText.textContent = t("viewColumns");
  boardLayoutGridText.textContent = t("viewGrid");
  boardLayoutListBtn.title = t("viewList");
  boardLayoutColumnsBtn.title = t("viewColumns");
  boardLayoutGridBtn.title = t("viewGrid");
  boardLayoutListBtn.setAttribute("aria-label", t("viewList"));
  boardLayoutColumnsBtn.setAttribute("aria-label", t("viewColumns"));
  boardLayoutGridBtn.setAttribute("aria-label", t("viewGrid"));
  const activeLayout = getBoardLayout();
  boardLayoutListBtn.setAttribute("aria-pressed",    activeLayout === "list"    ? "true" : "false");
  boardLayoutColumnsBtn.setAttribute("aria-pressed", activeLayout === "columns" ? "true" : "false");
  boardLayoutGridBtn.setAttribute("aria-pressed",    activeLayout === "grid"    ? "true" : "false");
  setButtonLabel(newDesignBtn, "fa-regular fa-file", t("new"));
  setButtonLabel(importDesignBtn, "fa-solid fa-file-arrow-up", t("import"));
  setButtonLabel(exportDesignBtn, "fa-solid fa-file-export", t("export"));
  const saveLabel = Number(state.meta.remoteDesignId) > 0
    && Number(state.meta.remoteSaveConflict) === Number(state.meta.remoteDesignId)
    ? t("saveCopy") : t("save");
  setButtonLabel(saveBtn, "fa-regular fa-floppy-disk", saveLabel);
  [
    [newDesignBtn, t("new")],
    [importDesignBtn, t("import")],
    [saveBtn, saveLabel],
    [document.getElementById("publish-btn"), t("share")],
    [exportDesignBtn, t("export")]
  ].forEach(([button, label]) => {
    if (!button) return;
    button.setAttribute("aria-label", label);
    button.setAttribute("title", label);
  });
  infoBtn.setAttribute("aria-label", t("info"));
  infoBtn.setAttribute("title", t("info"));
  infoBtn.setAttribute("aria-haspopup", "dialog");
  const footerAboutBtn = document.getElementById("footer-about-btn");
  const footerHelpBtn = document.getElementById("footer-help-btn");
  const footerSharedDesignsBtn = document.getElementById("footer-shared-designs-btn");
  if (footerAboutBtn) footerAboutBtn.textContent = t("infoTitle");
  if (footerHelpBtn) footerHelpBtn.textContent = t("footerHelp");
  if (footerSharedDesignsBtn) footerSharedDesignsBtn.textContent = t("footerSharedDesigns");
  importDesignBtn.setAttribute("aria-haspopup", "dialog");
  const importModalTitle = document.getElementById("import-modal-title");
  if (importModalTitle) importModalTitle.textContent = t("importTitle");
  const importModalDesc = document.getElementById("import-modal-desc");
  if (importModalDesc) importModalDesc.textContent = t("importModalDesc");
  const importFileSectionTitle = document.getElementById("import-file-section-title");
  if (importFileSectionTitle) importFileSectionTitle.textContent = t("importFromFileTitle");
  const importModelsSectionTitle = document.getElementById("import-models-section-title");
  if (importModelsSectionTitle) importModelsSectionTitle.textContent = t("importModelsTitle");
  const importFileFormats = document.getElementById("import-file-formats");
  if (importFileFormats) importFileFormats.textContent = t("importFileFormats");
  if (importDropTitle) importDropTitle.textContent = t("importDropTitle");
  if (importDropHint) importDropHint.textContent = t("importDropHint");
  const importModelsSearchLabel = document.getElementById("import-models-search-label");
  if (importModelsSearchLabel) importModelsSearchLabel.textContent = t("importModelsSearchLabel");
  const importModelsFamilyLabel = document.getElementById("import-models-family-label");
  if (importModelsFamilyLabel) importModelsFamilyLabel.textContent = t("importModelsFamilyLabel");
  if (importModelsSearch) importModelsSearch.placeholder = t("importModelsSearchPlaceholder");
  if (importModelsLink) importModelsLink.textContent = t("importModelsLink");
  if (importModelPreviewEyebrow) importModelPreviewEyebrow.textContent = t("importModelPreviewEyebrow");
  if (importModelPreviewBackBtn) setButtonLabel(importModelPreviewBackBtn, "fa-solid fa-arrow-left", t("importModelPreviewBack"));
  if (importModelPreviewUseBtn) setButtonLabel(importModelPreviewUseBtn, "fa-solid fa-file-import", t("importModelUseButton"));
  if (importModalCancelBtn) importModalCancelBtn.textContent = t("close");
  if (importFileBtn) setButtonLabel(importFileBtn, "fa-solid fa-folder-open", t("importChooseFile"));
  if (importModalBackdrop && !importModalBackdrop.classList.contains("hidden")) {
    renderModelFamilyOptions();
    renderModelList();
  }
  exportDesignBtn.setAttribute("aria-haspopup", "dialog");
  board.setAttribute("aria-label", t("boardRegion"));
  metaCommandInput.placeholder = t("commandPlaceholder");
  metaPersonasInput.placeholder = t("personasPlaceholder");
  metaLearningDaysInput.setAttribute("aria-label", t("learningDaysLabel"));
  metaLearningHoursInput.setAttribute("aria-label", t("learningHoursLabel"));
  metaLearningMinutesInput.setAttribute("aria-label", t("learningMinutesLabel"));
  metaDesignedDaysInput.setAttribute("aria-label", t("designedDaysLabel"));
  metaDesignedHoursInput.setAttribute("aria-label", t("designedHoursLabel"));
  metaDesignedMinutesInput.setAttribute("aria-label", t("designedMinutesLabel"));
  const exportScopeFullTitle = exportScopeFullInput?.closest(".export-scope-option")?.querySelector(".export-scope-option-title");
  const exportScopeStudentsTitle = exportScopeStudentsInput?.closest(".export-scope-option")?.querySelector(".export-scope-option-title");
  const exportScopeFullDescription = document.getElementById("export-scope-full-description");
  const exportScopeStudentsDescription = document.getElementById("export-scope-students-description");
  if (exportScopeLabel) exportScopeLabel.textContent = t("exportScopeTitle");
  if (exportScopeFullTitle) exportScopeFullTitle.textContent = t("exportScopeFull");
  if (exportScopeStudentsTitle) exportScopeStudentsTitle.textContent = t("exportScopeStudents");
  if (exportScopeFullDescription) exportScopeFullDescription.textContent = t("exportScopeFullDescription");
  if (exportScopeStudentsDescription) exportScopeStudentsDescription.textContent = t("exportScopeStudentsDescription");
  if (exportMomentsLabel) exportMomentsLabel.textContent = t("exportMomentsTitle");
  if (exportMomentsAllLabel) exportMomentsAllLabel.textContent = t("exportMomentsAll");
  if (exportMomentsEmpty) exportMomentsEmpty.textContent = t("exportMomentsEmpty");
  renderExportMoments();
  exportModalBackdrop.querySelector("#export-modal-title").textContent = t("exportTitle");
  exportModalBackdrop.querySelector("label[for='export-format-select']").textContent = t("format");
  exportModalBackdrop.querySelector("label[for='export-filename-input']").textContent = t("exportFilename");
  const exportPreviewCopy = document.getElementById("export-result-modal-copy");
  if (exportPreviewCopy) exportPreviewCopy.textContent = t("exportPreviewCopy");
  if (exportPreviewLabel) exportPreviewLabel.textContent = t("exportPreviewTitle");
  if (exportResultCopyBtn) exportResultCopyBtn.textContent = t("copy");
  exportModalCancelBtn.textContent = t("close");
  exportModalConfirmBtn.textContent = t("download");
  if (aiasModalTitle) aiasModalTitle.textContent = t("aiasFieldLabel");
  if (aiasModalIntro) aiasModalIntro.textContent = t("aiasPanelIntro");
  if (aiasModalLevels) aiasModalLevels.setAttribute("aria-label", t("aiasLevelsAriaLabel"));
  if (aiasModalAttributionPrefix) aiasModalAttributionPrefix.textContent = t("aiasAttributionPrefix");
  if (aiasModalCloseBtn) aiasModalCloseBtn.textContent = t("close");
  document.getElementById("info-modal-title").textContent = t("infoTitle");
  document.getElementById("info-modal-p1").textContent = t("infoP1");
  document.getElementById("info-modal-p2").textContent = t("infoP2");
  document.getElementById("info-modal-p3").textContent = t("infoP3");
  document.getElementById("info-modal-p4").innerHTML = t("infoP4");
  document.getElementById("info-modal-p5").innerHTML = t("infoP5");
  infoModalCloseBtn.textContent = t("close");
  const langLabel = t("uiLanguage");
  document.querySelector("label[for='lang-select']").textContent = langLabel;
  if (langSelect) {
    langSelect.setAttribute("aria-label", langLabel);
    langSelect.dataset.tooltip = langLabel;
  }
  document.querySelector(".nav-language-switch")?.setAttribute("aria-label", langLabel);
  document.querySelectorAll(".duration-unit").forEach((unit) => {
    unit.textContent = "min";
  });
  document.querySelectorAll(".session-notes-input").forEach((input) => {
    input.placeholder = t("sessionNotesPlaceholder");
  });
  document.querySelectorAll(".session-objectives").forEach((input) => {
    input.placeholder = t("sessionObjectivesPlaceholder");
  });
  document.querySelectorAll(".session-intentions").forEach((input) => {
    input.placeholder = t("sessionIntentionsPlaceholder");
  });
  document.querySelectorAll(".activity-duration-sr-label").forEach((label) => {
    label.textContent = t("durationMinutesSr");
  });
  topPanel.querySelector(".top-tabs").setAttribute(
    "aria-label",
    currentLang() === "en" ? "Top panel views" : "Vues du panneau supérieur"
  );
  localizeExpandableFieldControls();
}

document.addEventListener("site-footer-ready", () => {
  const footerAboutBtn = document.getElementById("footer-about-btn");
  const footerHelpBtn = document.getElementById("footer-help-btn");
  const footerSharedDesignsBtn = document.getElementById("footer-shared-designs-btn");
  if (footerAboutBtn) footerAboutBtn.textContent = t("infoTitle");
  if (footerHelpBtn) footerHelpBtn.textContent = t("footerHelp");
  if (footerSharedDesignsBtn) footerSharedDesignsBtn.textContent = t("footerSharedDesigns");
});

function updateResponsiveButtonLabels() {
  // Les actions de la barre restent compactes sur mobile via CSS. La création
  // d'un moment est désormais proposée directement à la fin du design.
}

function hydrateState(parsed, fallback = defaultState()) {
  if (!parsed || !Array.isArray(parsed.sessions)) return fallback;
  const parsedMeta = parsed.meta || {};

  const hydrated = {
    allNotesExpanded: Boolean(parsed.allNotesExpanded),
    intentionsCollapsed: Boolean(parsed.intentionsCollapsed),
    topPanelCollapsed: Object.prototype.hasOwnProperty.call(parsed, "topPanelCollapsed")
      ? Boolean(parsed.topPanelCollapsed)
      : true,
    meta: {
      ...DEFAULT_META,
      ...parsedMeta,
      designers:
        typeof parsedMeta.designers === "string"
          ? parsedMeta.designers
          : typeof parsedMeta.author === "string"
            ? parsedMeta.author
            : "",
      trainers: typeof parsedMeta.trainers === "string" ? parsedMeta.trainers : "",
      sliders: Array.isArray(parsedMeta.sliders)
        ? parsedMeta.sliders
        : typeof parsedMeta.sliders === "string" && parsedMeta.sliders.trim()
          ? [{ id: nextId(), category: "", categoryLabel: "", verb: "", text: parsedMeta.sliders }]
          : []
    },
    partitionLineConfig: Array.isArray(parsed.partitionLineConfig)
      ? normalizePartitionLineConfig(parsed.partitionLineConfig)
      : defaultPartitionLineConfig(),
    sessions: parsed.sessions.map((session) => ({
      id: session?.id || nextId(),
      title: toPlainTextareaValue(session?.title).trim(),
      objectives: toPlainTextareaValue(session?.objectives),
      intentions: toPlainTextareaValue(session?.intentions),
      notes: toPlainTextareaValue(session?.notes),
      notesExpanded: Boolean(session?.notesExpanded),
      activities: Array.isArray(session?.activities)
        ? session.activities.map((activity) => {
            const normalized = {
              id: activity?.id || nextId(),
              type: activity?.type || "undefined",
              duration: Math.max(1, Number(activity?.duration) || 1),
              groupMode: activity?.groupMode,
              teachingMode: activity?.teachingMode,
              teacherPresence: activity?.teacherPresence,
              syncMode: activity?.syncMode,
              locationMode: activity?.locationMode,
              evaluationMode: activity?.evaluationMode,
              aias: normalizeAiasState(activity?.aias ?? activity?.aiasLevel),
              description: toPlainTextareaValue(activity?.description),
              instructions: toPlainTextareaValue(activity?.instructions),
              notes: toPlainTextareaValue(activity?.notes),
              tools: Array.isArray(activity?.tools) ? activity.tools : [],
              links: Array.isArray(activity?.links) ? activity.links : []
            };
            normalizeActivity(normalized);
            return normalized;
          })
        : []
    }))
  };

  hydrated.meta.dayHours = Math.max(1, Number(hydrated.meta.dayHours) || DEFAULT_DAY_HOURS);
  hydrated.sessions.forEach(migrateActivityNotesToSession);
  const normalizedLearning = normalizePedagogicalTime(
    hydrated.meta.learningDays,
    hydrated.meta.learningHours,
    hydrated.meta.learningMinutes,
    hydrated.meta.dayHours
  );
  hydrated.meta.learningDays = normalizedLearning.days;
  hydrated.meta.learningHours = normalizedLearning.hours;
  hydrated.meta.learningMinutes = normalizedLearning.minutes;
  hydrated.meta.sizeClass =
    String(hydrated.meta.sizeClass ?? "").trim() === ""
      ? ""
      : Math.max(1, Number(hydrated.meta.sizeClass) || 1);
  if (!["fr", "en"].includes(hydrated.meta.uiLanguage)) hydrated.meta.uiLanguage = "fr";

  if (hydrated.meta.modeDelivery === "classroom") hydrated.meta.modeDelivery = "onsite";
  if (hydrated.meta.modeDelivery === "blended") hydrated.meta.modeDelivery = "hybrid";
  if (!["", "onsite", "online", "hybrid"].includes(hydrated.meta.modeDelivery)) {
    hydrated.meta.modeDelivery = "";
  }
  const normalizedSchoolSystem = lookupValue(
    hydrated.meta.schoolSystem,
    CSV_SCHOOL_SYSTEM_LOOKUP,
    ""
  );
  const normalizedSchoolLevel = lookupSchoolLevel(
    hydrated.meta.schoolLevel,
    normalizedSchoolSystem
  );
  hydrated.meta.schoolLevel = normalizedSchoolLevel;
  hydrated.meta.schoolSystem = normalizedSchoolSystem || schoolSystemForLevel(normalizedSchoolLevel);
  if (hydrated.meta.activeTab === "timeline") hydrated.meta.activeTab = "settings";
  if (!["settings", "analysis", "chronology"].includes(hydrated.meta.activeTab)) {
    hydrated.meta.activeTab = "settings";
  }
  if (!["columns", "list", "grid"].includes(hydrated.meta.boardLayout)) {
    hydrated.meta.boardLayout = "columns";
  }

  return hydrated;
}

function createNewDesignState() {
  return {
    allNotesExpanded: false,
    intentionsCollapsed: false,
    topPanelCollapsed: true,
    meta: {
      ...DEFAULT_META,
      sliders: [],
      uiLanguage: preferredInterfaceLanguage(currentLang())
    },
    sessions: [],
    partitionLineConfig: defaultPartitionLineConfig()
  };
}

let localStateSaveTimer = 0;
let localStateSavePending = false;

function persistStateNow() {
  if (!localStateSavePending) return;
  window.clearTimeout(localStateSaveTimer);
  localStateSaveTimer = 0;
  localStateSavePending = false;
  if (!activeStorageKey) return;
  try {
    localStorage.setItem(activeStorageKey, JSON.stringify(state));
  } catch (_) {}
}

function initializeStorageScope(userId = null) {
  const numericUserId = Number(userId);
  const scope = Number.isInteger(numericUserId) && numericUserId > 0
    ? `user_${numericUserId}`
    : "guest";
  const nextStorageKey = `${LD_STORAGE_KEY_PREFIX}${scope}`;
  if (storageScopeReady && activeStorageKey === nextStorageKey) return;

  persistStateNow();
  activeStorageKey = nextStorageKey;
  storageScopeReady = true;

  let scopedState = createNewDesignState();
  try {
    const raw = localStorage.getItem(activeStorageKey);
    if (raw) scopedState = hydrateState(JSON.parse(raw), scopedState);
  } catch (_) {}
  scopedState.meta.uiLanguage = preferredInterfaceLanguage(scopedState.meta.uiLanguage);
  state = scopedState;
  documentGeneration++;
  render();
  void maybeApplyRequestedModel();
}

function saveState({ markDirty = true } = {}) {
  if (markDirty) state.meta.remoteDirty = true;
  localStateSavePending = true;
  window.clearTimeout(localStateSaveTimer);
  localStateSaveTimer = window.setTimeout(persistStateNow, 300);
  window.dispatchEvent(new CustomEvent("ld:statechange"));
}

window.addEventListener("beforeunload", persistStateNow);
window.addEventListener("pagehide", persistStateNow);
document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "hidden") persistStateNow();
});

// --- Outcomes (Acquis d'apprentissage) ---

let bloomModalMode = "add";
let bloomEditOutcomeId = null;
let bloomSelectedCategory = null;
let bloomSelectedVerb = null;

function renderOutcomes() {
  if (!outcomesListEl) return;
  const outcomes = Array.isArray(state.meta.sliders) ? state.meta.sliders : [];
  outcomesListEl.innerHTML = "";
  outcomes.forEach((outcome) => {
    const item = document.createElement("div");
    item.className = "outcome-item";
    item.dataset.id = outcome.id;

    const header = document.createElement("div");
    header.className = "outcome-item-header";

    const verbBtn = document.createElement("button");
    verbBtn.className = "outcome-verb-btn";
    verbBtn.type = "button";
    verbBtn.title = t("changeVerb");
    verbBtn.setAttribute("aria-label", t("changeVerb"));
    const verbLabel = outcome.verb || outcome.categoryLabel || "—";
    verbBtn.innerHTML = `<span class="outcome-verb-text">${escapeHtml(verbLabel)}</span><span class="outcome-verb-edit" aria-hidden="true">✎</span>`;
    verbBtn.addEventListener("click", () => openBloomModal("edit", outcome.id));

    const deleteBtn = document.createElement("button");
    deleteBtn.className = "icon-btn delete-btn outcome-delete-btn";
    deleteBtn.type = "button";
    deleteBtn.setAttribute("aria-label", t("deleteOutcome"));
    deleteBtn.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>`;
    deleteBtn.addEventListener("click", () => {
      state.meta.sliders = state.meta.sliders.filter((o) => o.id !== outcome.id);
      saveState();
      renderOutcomes();
    });

    header.appendChild(verbBtn);
    header.appendChild(deleteBtn);

    const textarea = document.createElement("textarea");
    textarea.className = "outcome-text panel-textarea";
    textarea.rows = 1;
    textarea.placeholder = t("outcomeTextPlaceholder");
    textarea.value = outcome.text || "";
    textarea.addEventListener("input", () => {
      const found = (Array.isArray(state.meta.sliders) ? state.meta.sliders : []).find((o) => o.id === outcome.id);
      if (found) found.text = textarea.value;
      saveState();
    });

    item.appendChild(header);
    item.appendChild(textarea);
    outcomesListEl.appendChild(item);
  });
}

function renderBloomModal() {
  if (!bloomCategoryList) return;
  bloomCategoryList.innerHTML = "";
  const taxonomy = BLOOM_TAXONOMY[currentLang()] || BLOOM_TAXONOMY.fr;

  taxonomy.forEach((cat) => {
    const details = document.createElement("details");
    details.className = "bloom-category";
    if (bloomSelectedCategory === cat.id) details.open = true;

    const summary = document.createElement("summary");
    summary.className = "bloom-category-summary";
    if (bloomSelectedCategory === cat.id && !bloomSelectedVerb) {
      summary.classList.add("selected");
    }
    summary.textContent = cat.label;
    summary.addEventListener("click", () => {
      bloomSelectedCategory = cat.id;
      bloomSelectedVerb = null;
      bloomCategoryList.querySelectorAll(".bloom-category-summary, .bloom-verb-item").forEach((el) => el.classList.remove("selected"));
      summary.classList.add("selected");
    });

    details.appendChild(summary);

    cat.verbs.forEach((verb) => {
      const verbItem = document.createElement("div");
      verbItem.className = "bloom-verb-item";
      if (bloomSelectedVerb === verb && bloomSelectedCategory === cat.id) {
        verbItem.classList.add("selected");
      }
      verbItem.textContent = verb;
      verbItem.addEventListener("click", () => {
        bloomSelectedCategory = cat.id;
        bloomSelectedVerb = verb;
        bloomCategoryList.querySelectorAll(".bloom-category-summary, .bloom-verb-item").forEach((el) => el.classList.remove("selected"));
        verbItem.classList.add("selected");
      });
      details.appendChild(verbItem);
    });

    bloomCategoryList.appendChild(details);
  });
}

function openBloomModal(mode, outcomeId = null) {
  bloomModalMode = mode;
  bloomEditOutcomeId = outcomeId;

  if (mode === "edit" && outcomeId) {
    const outcome = (Array.isArray(state.meta.sliders) ? state.meta.sliders : []).find((o) => o.id === outcomeId);
    bloomSelectedCategory = outcome?.category || null;
    bloomSelectedVerb = outcome?.verb || null;
  } else {
    bloomSelectedCategory = null;
    bloomSelectedVerb = null;
  }

  if (bloomAddBtn) bloomAddBtn.textContent = mode === "edit" ? t("bloomEdit") : t("bloomAdd");
  renderBloomModal();
  openModal(bloomModalBackdrop, "#bloom-cancel-btn");
}

function confirmBloom() {
  if (!bloomSelectedCategory) return;
  const taxonomy = BLOOM_TAXONOMY[currentLang()] || BLOOM_TAXONOMY.fr;
  const cat = taxonomy.find((c) => c.id === bloomSelectedCategory);
  const categoryLabel = cat?.label || "";

  if (bloomModalMode === "add") {
    if (!Array.isArray(state.meta.sliders)) state.meta.sliders = [];
    state.meta.sliders.push({
      id: nextId(),
      category: bloomSelectedCategory,
      categoryLabel,
      verb: bloomSelectedVerb || "",
      text: ""
    });
  } else if (bloomModalMode === "edit" && bloomEditOutcomeId) {
    const outcome = (Array.isArray(state.meta.sliders) ? state.meta.sliders : []).find((o) => o.id === bloomEditOutcomeId);
    if (outcome) {
      outcome.category = bloomSelectedCategory;
      outcome.categoryLabel = categoryLabel;
      outcome.verb = bloomSelectedVerb || "";
    }
  }

  saveState();
  closeModal(bloomModalBackdrop);
  renderOutcomes();
}

function setupFormAccessibility() {
  document.addEventListener(
    "invalid",
    (event) => {
      const field = event.target;
      if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return;
      }
      field.setAttribute("aria-invalid", "true");
      announce(field.validationMessage || "Valeur invalide");
    },
    true
  );

  document.addEventListener("input", (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
      return;
    }
    if (field.validity.valid) {
      field.removeAttribute("aria-invalid");
    }
    if (field instanceof HTMLTextAreaElement && field.matches(AUTO_RESIZE_SELECTOR)) {
      autoResizeTextarea(field);
    }
  });
}

function colorForType(typeId) {
  return LEARNING_TYPES.find((t) => t.id === typeId)?.color || "#999";
}

function getOption(options, value) {
  const option = options.find((opt) => opt.value === value);
  if (option) return option;
  if (options === TEACHING_OPTIONS) return UNDEFINED_TEACHING_OPTION;
  return options[0];
}

function setChoiceButton(button, options, value) {
  const option = getOption(options, value);
  const groupTitle = button.dataset.groupTitle || "";
  button.dataset.value = option.value;
  // Icon is static trusted SVG; label is set via textContent to prevent future injection.
  button.innerHTML = option.icon;
  const labelSpan = document.createElement("span");
  labelSpan.className = "choice-label";
  labelSpan.textContent = option.label;
  button.appendChild(labelSpan);
  const accessibleLabel = groupTitle ? `${groupTitle}: ${option.label}` : option.label;
  button.title = accessibleLabel;
  button.setAttribute("aria-label", accessibleLabel);
  button.setAttribute("aria-haspopup", "listbox");
  button.setAttribute("aria-expanded", "false");
}

let activeChoiceMenu = null;
let activeChoiceTrigger = null;
let activeChoiceItems = [];
let activeChoiceIndex = -1;

let activeToolPicker = null;
let activeToolPickerTrigger = null;
let activeToolPickerFramework = "florimont";
let activeToolPickerTab = "acquerir";

function getCompetencyFramework(frameworkId = activeToolPickerFramework) {
  return COMPETENCY_FRAMEWORKS.find((framework) => framework.id === frameworkId)
    || COMPETENCY_FRAMEWORKS[0];
}

function focusChoiceItem(index) {
  if (!activeChoiceItems.length) return;
  const safeIndex = Math.max(0, Math.min(index, activeChoiceItems.length - 1));
  activeChoiceItems[safeIndex].focus();
  activeChoiceIndex = safeIndex;
}

function closeChoiceMenu(restoreFocus = false) {
  if (!activeChoiceMenu) return;
  activeChoiceMenu.remove();
  if (activeChoiceTrigger) {
    activeChoiceTrigger.classList.remove("open");
    activeChoiceTrigger.setAttribute("aria-expanded", "false");
    activeChoiceTrigger.removeAttribute("aria-controls");
    if (restoreFocus) activeChoiceTrigger.focus();
  }
  activeChoiceMenu = null;
  activeChoiceTrigger = null;
  activeChoiceItems = [];
  activeChoiceIndex = -1;
}


function closeToolPicker(restoreFocus = false) {
  if (!activeToolPicker) return;
  if (activeToolPicker._backdrop) activeToolPicker._backdrop.remove();
  activeToolPicker.remove();
  if (activeToolPickerTrigger) {
    activeToolPickerTrigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) activeToolPickerTrigger.focus();
  }
  activeToolPicker = null;
  activeToolPickerTrigger = null;
}

function renderPickerBody(body, groupId, activity) {
  body.innerHTML = "";
  const lang = currentLang();
  const groupTools = SELECTABLE_TOOLS_DATA.filter(
    (tool) => tool.frameworkId === activeToolPickerFramework
      && tool.groupId === groupId
      && !tool.pickerHidden
  );
  const categories = [...new Set(groupTools.map((tool) => tool.category))];
  categories.forEach(categoryKey => {
    const tools = groupTools.filter(tool => tool.category === categoryKey);
    const fallbackCategory = tools[0]
      ? { fr: tools[0].sectionFr, en: tools[0].sectionEn }
      : null;
    const categoryTitle = (SELECTABLE_TOOL_CATEGORY_LABELS[categoryKey] || fallbackCategory)?.[lang];
    if (categoryTitle) {
      const sectionTitle = document.createElement("div");
      sectionTitle.className = "tool-picker-section-title";
      sectionTitle.setAttribute("aria-hidden", "true");
      sectionTitle.textContent = applyLanguageTypography(categoryTitle, lang);
      applyCompetencyTheme(
        sectionTitle,
        tools[0]?.platform || activeToolPickerFramework,
        tools[0]?.groupId || groupId
      );
      body.appendChild(sectionTitle);
    }
    tools.forEach(tool => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "tool-picker-item";
      item.dataset.level = tool.platform;
      item.dataset.tooltip = competencyTooltip(tool, lang);
      item.dataset.search = (tool.details || [])
        .map((detail) => lang === "en" ? detail.textEn : detail.textFr)
        .join(" ")
        .concat(" ", lang === "en" ? tool.parentLabelEn || "" : tool.parentLabelFr || "")
        .concat(" ", lang === "en" ? tool.parentDescEn || "" : tool.parentDescFr || "")
        .toLowerCase();
      applyCompetencyTheme(item, tool.platform, tool.groupId);
      const isSelected = activity.tools.includes(tool.id);
      if (isSelected) item.classList.add("selected");
      const checkBox = document.createElement("span");
      checkBox.className = "tool-picker-item-check";
      checkBox.setAttribute("aria-hidden", "true");
      checkBox.textContent = isSelected ? "✓" : "";
      const nameEl = document.createElement("span");
      nameEl.className = "tool-picker-item-name";
      nameEl.textContent = formatCompetencyLabel(tool, lang);
      const textWrapper = document.createElement("span");
      textWrapper.className = "tool-picker-item-text";
      textWrapper.appendChild(nameEl);
      const appLabel = lang === "en" ? tool.appEn : tool.appFr;
      const rawDesc = lang === "en" ? tool.descEn : tool.descFr;
      const desc = applyLanguageTypography(rawDesc, lang);
      const detailCount = Array.isArray(tool.details) ? tool.details.length : 0;
      const detailSummary = detailCount
        ? tool.platform === "digcomp"
          ? (lang === "en" ? `${detailCount} bilingual competence statements` : `${detailCount} énoncés de compétence bilingues`)
          : (lang === "en" ? `${detailCount} detailed indicators` : `${detailCount} repères détaillés`)
        : "";
      const helperText = [appLabel ? `${lang === "en" ? "App" : "App"}: ${appLabel}` : "", desc, detailSummary]
        .filter(Boolean)
        .join(" — ");
      if (helperText) {
        const descEl = document.createElement("span");
        descEl.className = "tool-picker-item-desc";
        descEl.textContent = `(${applyLanguageTypography(helperText, lang)})`;
        textWrapper.appendChild(descEl);
      }
      item.appendChild(checkBox);
      item.appendChild(textWrapper);
      item.addEventListener("click", () => {
        if (activity.tools.includes(tool.id)) {
          activity.tools = activity.tools.filter(id => id !== tool.id);
        } else {
          activity.tools = [...activity.tools, tool.id];
        }
        saveState();
        const nowSelected = activity.tools.includes(tool.id);
        item.classList.toggle("selected", nowSelected);
        checkBox.textContent = nowSelected ? "✓" : "";
        updateActivityToolsDisplay(activeToolPickerTrigger, activity);
      });
      body.appendChild(item);
    });
  });
}

function filterPickerItems(body, term) {
  body.querySelectorAll(".tool-picker-item").forEach(item => {
    const name = (item.querySelector(".tool-picker-item-name")?.textContent || "").toLowerCase();
    const desc = (item.querySelector(".tool-picker-item-desc")?.textContent || "").toLowerCase();
    const details = item.dataset.search || "";
    item.style.display = (!term || name.includes(term) || desc.includes(term) || details.includes(term)) ? "" : "none";
  });
  body.querySelectorAll(".tool-picker-section-title").forEach(title => {
    let next = title.nextElementSibling;
    let hasVisible = false;
    while (next && !next.classList.contains("tool-picker-section-title")) {
      if (next.style.display !== "none") hasVisible = true;
      next = next.nextElementSibling;
    }
    title.style.display = hasVisible ? "" : "none";
  });
}

function switchPickerTab(groupId, body, activity) {
  activeToolPickerTab = groupId;
  activeToolPicker.querySelectorAll(".tool-picker-tab").forEach(tab => {
    const isActive = tab.dataset.group === groupId;
    tab.classList.toggle("active", isActive);
    tab.setAttribute("aria-selected", String(isActive));
  });
  const searchInput = activeToolPicker.querySelector(".tool-picker-search-input");
  if (searchInput) searchInput.value = "";
  renderPickerBody(body, groupId, activity);
}

function renderPickerTabs(tabsRow, body, activity) {
  tabsRow.innerHTML = "";
  const framework = getCompetencyFramework();
  const groups = framework?.groups || [];
  if (!groups.some((group) => group.id === activeToolPickerTab)) {
    activeToolPickerTab = groups[0]?.id || "";
  }
  const lang = currentLang();
  groups.forEach(({ id, labelFr, labelEn }) => {
    const tab = document.createElement("button");
    const isActive = id === activeToolPickerTab;
    tab.type = "button";
    tab.className = "tool-picker-tab" + (isActive ? " active" : "");
    tab.dataset.group = id;
    applyCompetencyTheme(
      tab,
      activeToolPickerFramework === "florimont" ? id : activeToolPickerFramework,
      activeToolPickerFramework === "florimont" ? "" : id
    );
    tab.textContent = lang === "en" ? labelEn : labelFr;
    tab.setAttribute("role", "tab");
    tab.setAttribute("aria-selected", String(isActive));
    tab.addEventListener("click", () => switchPickerTab(id, body, activity));
    tabsRow.appendChild(tab);
  });
  renderPickerBody(body, activeToolPickerTab, activity);
}

function openToolPicker(trigger, activity) {
  if (activeToolPicker && activeToolPickerTrigger === trigger) {
    closeToolPicker(true);
    return;
  }
  closeToolPicker();
  closeChoiceMenu();
  if (!COMPETENCY_FRAMEWORKS.some((framework) => framework.id === activeToolPickerFramework)) {
    activeToolPickerFramework = COMPETENCY_FRAMEWORKS[0]?.id || "florimont";
  }

  const panel = document.createElement("div");
  panel.className = "tool-picker";
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-modal", "false");
  panel.setAttribute("aria-labelledby", "tool-picker-title");

  const header = document.createElement("div");
  header.className = "tool-picker-header";
  const titleEl = document.createElement("h2");
  titleEl.id = "tool-picker-title";
  titleEl.className = "modal-title";
  titleEl.textContent = t("toolPickerTitle");
  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "tool-picker-close";
  closeBtn.textContent = "✕";
  closeBtn.setAttribute("aria-label", t("toolPickerClose"));
  closeBtn.addEventListener("click", () => closeToolPicker(true));
  header.appendChild(titleEl);
  header.appendChild(closeBtn);
  panel.appendChild(header);

  const lang = currentLang();
  const frameworkRow = document.createElement("div");
  frameworkRow.className = "tool-picker-framework";
  const frameworkLabel = document.createElement("label");
  frameworkLabel.className = "tool-picker-framework-label";
  frameworkLabel.htmlFor = "tool-picker-framework-select";
  frameworkLabel.textContent = t("toolPickerFrameworkLabel");
  const frameworkSelect = document.createElement("select");
  frameworkSelect.id = "tool-picker-framework-select";
  frameworkSelect.className = "tool-picker-framework-select";
  COMPETENCY_FRAMEWORKS.forEach((framework) => {
    const option = document.createElement("option");
    option.value = framework.id;
    option.textContent = lang === "en" ? framework.labelEn : framework.labelFr;
    option.selected = framework.id === activeToolPickerFramework;
    frameworkSelect.appendChild(option);
  });
  const sourceLink = document.createElement("a");
  sourceLink.className = "tool-picker-source-link";
  sourceLink.target = "_blank";
  sourceLink.rel = "noopener noreferrer";
  sourceLink.textContent = t("toolPickerSource");
  const updateSourceLink = () => {
    const framework = getCompetencyFramework();
    let sourceUrl = framework?.sourceUrl || "";
    if (lang === "en" && framework?.id === "greencomp") {
      sourceUrl = sourceUrl
        .replace("/fr/publication-detail/", "/en/publication-detail/")
        .replace("/language-fr", "/language-en");
    }
    sourceLink.href = sourceUrl || "#";
    sourceLink.classList.toggle("hidden", !sourceUrl);
  };
  updateSourceLink();
  frameworkRow.appendChild(frameworkLabel);
  frameworkRow.appendChild(frameworkSelect);
  frameworkRow.appendChild(sourceLink);
  panel.appendChild(frameworkRow);

  const tabsRow = document.createElement("div");
  tabsRow.className = "tool-picker-tabs";
  tabsRow.setAttribute("role", "tablist");
  const body = document.createElement("div");
  body.className = "tool-picker-body";
  frameworkSelect.addEventListener("change", () => {
    activeToolPickerFramework = frameworkSelect.value;
    activeToolPickerTab = getCompetencyFramework()?.groups?.[0]?.id || "";
    const searchInput = panel.querySelector(".tool-picker-search-input");
    if (searchInput) searchInput.value = "";
    updateSourceLink();
    renderPickerTabs(tabsRow, body, activity);
  });
  renderPickerTabs(tabsRow, body, activity);
  panel.appendChild(tabsRow);

  const searchRow = document.createElement("div");
  searchRow.className = "tool-picker-search";
  const searchInput = document.createElement("input");
  searchInput.type = "search";
  searchInput.className = "tool-picker-search-input";
  searchInput.placeholder = lang === "en" ? "Search…" : "Rechercher…";
  searchInput.setAttribute("aria-label", lang === "en" ? "Search competencies" : "Rechercher des compétences");
  searchInput.addEventListener("input", () => {
    filterPickerItems(body, searchInput.value.trim().toLowerCase());
  });
  searchRow.appendChild(searchInput);
  panel.appendChild(searchRow);

  panel.appendChild(body);

  panel.addEventListener("keydown", (event) => {
    if (event.key === "Escape") { closeToolPicker(true); return; }
    if (
      (event.key === "ArrowDown" || event.key === "ArrowUp")
      && document.activeElement?.classList.contains("tool-picker-item")
    ) {
      event.preventDefault();
      const items = Array.from(body.querySelectorAll(".tool-picker-item"));
      const idx = items.indexOf(document.activeElement);
      if (idx === -1) { items[0]?.focus(); return; }
      const next = event.key === "ArrowDown"
        ? (idx + 1) % items.length
        : (idx - 1 + items.length) % items.length;
      items[next].focus();
    }
    if (event.key === "Tab") {
      const focusables = Array.from(
        panel.querySelectorAll("button:not([disabled]), select:not([disabled]), input:not([disabled]), a[href]")
      ).filter((element) => !element.classList.contains("hidden") && element.offsetParent !== null);
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
  });

  const backdrop = document.createElement("div");
  backdrop.className = "tool-picker-backdrop";
  backdrop.addEventListener("click", () => closeToolPicker(true));
  document.body.appendChild(backdrop);
  panel._backdrop = backdrop;
  document.body.appendChild(panel);
  panel.setAttribute("aria-modal", "true");
  activeToolPicker = panel;
  activeToolPickerTrigger = trigger;
  trigger.setAttribute("aria-expanded", "true");
  body.querySelector(".tool-picker-item")?.focus();
}

function updateActivityToolsDisplay(trigger, activity) {
  if (!trigger) return;
  const card = trigger.closest(".activity-card");
  if (!card) return;
  const count = activity.tools.length;
  trigger.dataset.count = count;
  trigger.classList.toggle("has-tools", count > 0);
  const label = t("selectTools");
  trigger.setAttribute("aria-label", label);
  trigger.title = label;
  const toolsRow = card.querySelector(".activity-tools");
  if (!toolsRow) return;
  toolsRow.classList.toggle("hidden", count === 0);
  toolsRow.setAttribute("aria-label", t("toolsAriaLabel"));
  toolsRow.innerHTML = "";
  const lang = currentLang();
  activity.tools.forEach(toolId => {
    const toolDef = SELECTABLE_TOOLS_DATA.find(td => td.id === toolId);
    if (!toolDef) return;
    const chip = document.createElement("span");
    chip.className = "tool-chip";
    chip.dataset.level = toolDef.platform;
    chip.dataset.tooltip = competencyTooltip(toolDef, lang);
    chip.setAttribute("role", "listitem");
    applyCompetencyTheme(chip, toolDef.platform, toolDef.groupId);
    const nameEl = document.createElement("span");
    nameEl.className = "tool-chip-name";
    const label = lang === "en" ? toolDef.labelEn : toolDef.labelFr;
    nameEl.textContent = formatCompetencyShortCode(toolDef, lang);
    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "tool-chip-remove";
    removeBtn.setAttribute("aria-label", t("removeToolAriaLabel")(label));
    removeBtn.textContent = "×";
    removeBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      activity.tools = activity.tools.filter(id => id !== toolId);
      saveState();
      updateActivityToolsDisplay(trigger, activity);
      if (activeToolPicker && activeToolPickerTrigger === trigger) {
        renderPickerBody(activeToolPicker.querySelector(".tool-picker-body"), activeToolPickerTab, activity);
      }
    });
    chip.appendChild(nameEl);
    chip.appendChild(removeBtn);
    toolsRow.appendChild(chip);
  });
}

function openChoiceMenu(trigger, options, currentValue, onSelect) {
  if (activeChoiceMenu && activeChoiceTrigger === trigger) {
    closeChoiceMenu(true);
    return;
  }
  closeChoiceMenu();
  const rect = trigger.getBoundingClientRect();
  const menu = document.createElement("div");
  const menuId = `choice-menu-${nextId()}`;
  menu.className = "choice-menu";
  menu.classList.toggle("choice-menu-with-descriptions", options.some((option) => option.description));
  menu.id = menuId;
  menu.setAttribute("role", "listbox");
  menu.setAttribute("aria-label", trigger.dataset.groupTitle || trigger.title || "Options");
  menu.style.left = `${rect.left}px`;
  menu.style.top = `${rect.bottom + 4}px`;
  const groupTitle = trigger.dataset.groupTitle || "";
  if (groupTitle) {
    const title = document.createElement("div");
    title.className = "choice-menu-title";
    title.textContent = groupTitle;
    menu.appendChild(title);
  }

  options.forEach((option) => {
    const item = document.createElement("button");
    item.type = "button";
    item.className = `choice-menu-item${option.value === currentValue ? " active" : ""}`;
    item.innerHTML = option.icon;
    const text = document.createElement("span");
    text.className = "choice-menu-item-text";
    const label = document.createElement("span");
    label.className = "choice-menu-item-label";
    label.textContent = option.label;
    text.appendChild(label);
    if (option.description) {
      const description = document.createElement("span");
      description.className = "choice-menu-item-description";
      description.textContent = option.description;
      text.appendChild(description);
    }
    item.appendChild(text);
    item.setAttribute("role", "option");
    item.setAttribute("aria-label", [option.label, option.description].filter(Boolean).join(". "));
    item.setAttribute("tabindex", "-1");
    item.setAttribute("aria-selected", option.value === currentValue ? "true" : "false");
    item.addEventListener("click", () => {
      onSelect(option.value);
      closeChoiceMenu();
    });
    menu.appendChild(item);
  });

  document.body.appendChild(menu);
  activeChoiceMenu = menu;
  activeChoiceTrigger = trigger;
  activeChoiceItems = Array.from(menu.querySelectorAll(".choice-menu-item"));
  activeChoiceIndex = Math.max(0, options.findIndex((option) => option.value === currentValue));
  trigger.classList.add("open");
  trigger.setAttribute("aria-expanded", "true");
  trigger.setAttribute("aria-controls", menuId);

  const spaceBelow = window.innerHeight - rect.bottom - 12;
  const spaceAbove = rect.top - 12;
  const availableHeight = Math.max(spaceBelow, spaceAbove, 160);
  menu.style.maxHeight = `${Math.min(420, availableHeight)}px`;

  const menuRect = menu.getBoundingClientRect();
  let left = rect.left;
  let top = rect.bottom + 4;
  if (left + menuRect.width > window.innerWidth - 8) {
    left = window.innerWidth - menuRect.width - 8;
  }
  if (left < 8) left = 8;
  if (top + menuRect.height > window.innerHeight - 8 && spaceAbove > spaceBelow) {
    top = rect.top - menuRect.height - 4;
  }
  if (top < 8) top = 8;
  menu.style.left = `${left}px`;
  menu.style.top = `${top}px`;

  menu.addEventListener("keydown", (event) => {
    if (!activeChoiceMenu) return;
    if (event.key === "Escape") {
      event.preventDefault();
      closeChoiceMenu(true);
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      focusChoiceItem(activeChoiceIndex + 1);
      return;
    }
    if (event.key === "ArrowUp") {
      event.preventDefault();
      focusChoiceItem(activeChoiceIndex - 1);
      return;
    }
    if (event.key === "Home") {
      event.preventDefault();
      focusChoiceItem(0);
      return;
    }
    if (event.key === "End") {
      event.preventDefault();
      focusChoiceItem(activeChoiceItems.length - 1);
      return;
    }
    if (event.key === "Tab") {
      closeChoiceMenu();
      return;
    }
    if (event.key === " " || event.key === "Enter") {
      const item = document.activeElement?.closest(".choice-menu-item");
      if (item) {
        event.preventDefault();
        item.click();
      }
    }
  });

  focusChoiceItem(activeChoiceIndex);
}

function normalizeActivity(activity) {
  activity.description = toPlainTextareaValue(activity.description);
  activity.instructions = toPlainTextareaValue(activity.instructions);
  activity.aias = normalizeAiasState(activity.aias ?? activity.aiasLevel);
  delete activity.aiasLevel;
  activity.description = migrateLegacyActivityLinks(activity.description, activity.links);
  delete activity.links;
  if (!Array.isArray(activity.tools)) activity.tools = [];
  activity.tools = activity.tools
    .map((reference) => {
      if (SELECTABLE_TOOL_IDS_SET.has(reference)) return reference;
      return COMPETENCY_REFERENCE_MAP[normalizeToken(reference)] || null;
    })
    .filter(Boolean)
    .filter((id, index, array) => array.indexOf(id) === index);
  const legacyGroupSize = Number(activity.groupSize || 0);
  if (!["whole", "subgroups", "individual"].includes(activity.groupMode)) {
    if (legacyGroupSize > 1 && legacyGroupSize < 15) {
      activity.groupMode = "subgroups";
    } else if (legacyGroupSize === 1) {
      activity.groupMode = "individual";
    } else {
      activity.groupMode = "whole";
    }
  }
  if (!TEACHING_VALUES.has(activity.teachingMode)) {
    activity.teachingMode = activity.teacherPresence === "absent" ? "independent" : "undefined";
  }
  delete activity.teacherPresence;
  if (activity.syncMode !== "sync" && activity.syncMode !== "async") {
    activity.syncMode = "sync";
  }
  if (activity.locationMode === "presentiel") activity.locationMode = "onsite";
  if (activity.locationMode === "distanciel") activity.locationMode = "online";
  if (activity.locationMode === "classroom") activity.locationMode = "onsite";
  if (activity.locationMode === "location-based") activity.locationMode = "location_based";
  if (activity.locationMode === "blended") activity.locationMode = "hybrid";
  if (!LOCATION_VALUES.has(activity.locationMode)) {
    activity.locationMode = "onsite";
  }
  if (
    !["none", "diagnostic", "formative", "summative", "certificative"].includes(
      activity.evaluationMode
    )
  ) {
    activity.evaluationMode = "none";
  }
}

function normalizePartitionLineConfig(lines) {
  const normalizedLines = lines.map((line) => {
    if (!line || typeof line !== "object") return line;
    if (line.type !== "teacherPresence") return { ...line };
    const value = line.value === "absent" ? "independent" : "undefined";
    return {
      ...line,
      type: "teachingMode",
      value,
      label: labelForTeachingMode(value)
    };
  });

  const legacyLocationLines = normalizedLines.filter((line) => line?.type === "locationMode");
  const legacyValues = new Set(legacyLocationLines.map((line) => line.value));
  const isLegacyDefault = normalizedLines.length === 3
    && legacyLocationLines.length === 3
    && ["onsite", "online", "hybrid"].every((value) => legacyValues.has(value));
  if (!isLegacyDefault) return normalizedLines;

  return defaultPartitionLineConfig().map((line) => ({
    ...line,
    visible: legacyLocationLines.find((legacyLine) => legacyLine.value === line.value)?.visible ?? true
  }));
}

function defaultAiasState() {
  return {
    version: AIAS_VERSION,
    status: "undecided",
    level: null
  };
}

function normalizeAiasState(value) {
  if (typeof value === "number" || typeof value === "string") {
    const legacyLevel = Number(value);
    if (Number.isInteger(legacyLevel) && legacyLevel >= 1 && legacyLevel <= 5) {
      return { version: AIAS_VERSION, status: "specified", level: legacyLevel };
    }
    return defaultAiasState();
  }

  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return defaultAiasState();
  }

  const level = Number(value.level);
  if (Number.isInteger(level) && level >= 1 && level <= 5) {
    return {
      version: String(value.version || AIAS_VERSION),
      status: "specified",
      level
    };
  }

  if (value.status === "not_applicable") {
    return {
      version: String(value.version || AIAS_VERSION),
      status: "not_applicable",
      level: null
    };
  }

  return defaultAiasState();
}

function createActivity() {
  return {
    id: nextId(),
    type: "undefined",
    duration: 10,
    groupMode: "whole",
    teachingMode: "undefined",
    syncMode: "sync",
    locationMode: "onsite",
    evaluationMode: "none",
    aias: defaultAiasState(),
    description: "",
    instructions: "",
    notes: "",
    tools: []
  };
}

function createSession() {
  return {
    id: nextId(),
    title: "",
    objectives: "",
    intentions: "",
    notes: "",
    notesExpanded: false,
    activities: []
  };
}

function focusCreatedSession(sessionId) {
  window.requestAnimationFrame(() => {
    const sessionElement = Array.from(
      board.querySelectorAll(".session-card, .grid-session-row")
    ).find((element) => element.dataset.sessionId === sessionId);
    if (!sessionElement) return;

    const titleInput = sessionElement.querySelector(".session-title, .grid-session-title-input");
    sessionElement.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "nearest" });
    titleInput?.focus({ preventScroll: true });
  });
}

function addSessionAndRender() {
  const session = createSession();
  state.sessions.push(session);
  saveState();
  render();
  focusCreatedSession(session.id);
  announce(t("momentAdded"));
}

function createAddSessionCard() {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "add-session-card";
  button.setAttribute("aria-label", t("createMoment"));
  button.title = t("createMoment");

  const icon = document.createElement("span");
  icon.className = "add-session-card-icon";
  icon.setAttribute("aria-hidden", "true");
  const plusIcon = document.createElement("i");
  plusIcon.className = "fa-solid fa-plus";
  icon.appendChild(plusIcon);

  const label = document.createElement("span");
  label.className = "add-session-card-label";
  label.textContent = t("createMoment");

  button.append(icon, label);
  button.addEventListener("click", addSessionAndRender);
  return button;
}

function aiasLevelDefinition(level) {
  return AIAS_LEVELS.find((item) => item.level === Number(level)) || null;
}

function aiasSummary(aias) {
  const normalized = normalizeAiasState(aias);
  if (normalized.status === "not_applicable") return `AIAS · ${t("aiasNotApplicable")}`;
  if (normalized.status !== "specified") return `AIAS · ${t("aiasUndecided")}`;
  const definition = aiasLevelDefinition(normalized.level);
  return definition
    ? `AIAS ${definition.level} · ${t(definition.labelKey)}`
    : t("aiasUndecided");
}

function applyAiasLevelClass(element, aias) {
  if (!element) return;
  element.classList.remove(
    "aias-level",
    ...AIAS_LEVELS.map((definition) => `aias-level-${definition.level}`)
  );
  const normalized = normalizeAiasState(aias);
  if (normalized.status === "specified") {
    element.classList.add("aias-level", `aias-level-${normalized.level}`);
  }
}

function parseAiasValue(value) {
  const raw = String(value || "").trim();
  if (!raw) return defaultAiasState();
  const normalized = normalizeToken(raw);
  if (["non pertinent", "not applicable", "n a", "na"].includes(normalized)) {
    return { version: AIAS_VERSION, status: "not_applicable", level: null };
  }
  const levelMatch = raw.match(/(?:AIAS|niveau|level)?\s*([1-5])\b/i);
  if (levelMatch) {
    return { version: AIAS_VERSION, status: "specified", level: Number(levelMatch[1]) };
  }
  return defaultAiasState();
}

function updateAiasTrigger(trigger, activity) {
  if (!trigger) return;
  activity.aias = normalizeAiasState(activity.aias);
  const summary = aiasSummary(activity.aias);
  applyAiasLevelClass(trigger, activity.aias);
  if (activity.aias.status === "specified") {
    const number = document.createElement("span");
    number.className = "aias-trigger-number";
    number.textContent = String(activity.aias.level);
    number.setAttribute("aria-hidden", "true");
    trigger.replaceChildren(number);
  } else {
    const icon = document.createElement("i");
    icon.className = `fa-solid ${AIAS_TRIGGER_ICON}`;
    icon.setAttribute("aria-hidden", "true");
    trigger.replaceChildren(icon);
  }
  trigger.title = summary;
  trigger.setAttribute("aria-label", `${t("aiasFieldLabel")}: ${summary}`);
}

function chooseAias(nextAias) {
  if (!activeAiasActivity) return;
  activeAiasActivity.aias = normalizeAiasState(nextAias);
  saveState();
  updateAiasTrigger(activeAiasTrigger, activeAiasActivity);
  announce(t("aiasUpdated"));
  closeAiasModal();
}

function renderAiasModal() {
  if (!activeAiasActivity || !aiasModalStatusOptions || !aiasModalLevels) return;
  activeAiasActivity.aias = normalizeAiasState(activeAiasActivity.aias);
  if (aiasModalTitle) aiasModalTitle.textContent = t("aiasFieldLabel");
  if (aiasModalIntro) aiasModalIntro.textContent = t("aiasPanelIntro");
  aiasModalLevels.setAttribute("aria-label", t("aiasLevelsAriaLabel"));
  aiasModalStatusOptions.replaceChildren();
  aiasModalLevels.replaceChildren();

  [
    { status: "undecided", labelKey: "aiasUndecided" },
    { status: "not_applicable", labelKey: "aiasNotApplicable" }
  ].forEach(({ status, labelKey }) => {
    const button = document.createElement("button");
    const selected = activeAiasActivity.aias.status === status;
    button.type = "button";
    button.className = "aias-status-btn";
    button.dataset.aiasStatus = status;
    button.textContent = t(labelKey);
    button.classList.toggle("selected", selected);
    button.setAttribute("aria-pressed", String(selected));
    button.addEventListener("click", () => chooseAias({
      version: AIAS_VERSION,
      status,
      level: null
    }));
    aiasModalStatusOptions.appendChild(button);
  });

  AIAS_LEVELS.forEach((definition) => {
    const selected = activeAiasActivity.aias.status === "specified"
      && activeAiasActivity.aias.level === definition.level;
    const button = document.createElement("button");
    button.type = "button";
    button.className = `aias-level-btn aias-level aias-level-${definition.level}`;
    button.dataset.aiasLevel = String(definition.level);
    button.setAttribute("role", "radio");
    button.setAttribute("aria-checked", String(selected));
    button.classList.toggle("selected", selected);

    const number = document.createElement("span");
    number.className = "aias-level-number";
    number.textContent = String(definition.level);
    const title = document.createElement("span");
    title.className = "aias-level-title";
    title.textContent = t(definition.labelKey);
    const description = document.createElement("span");
    description.className = "aias-level-description";
    description.textContent = t(definition.descriptionKey);

    button.append(number, title, description);
    button.addEventListener("click", () => chooseAias({
      version: AIAS_VERSION,
      status: "specified",
      level: definition.level
    }));
    aiasModalLevels.appendChild(button);
  });
}

function openAiasModal(trigger, activity) {
  activeAiasTrigger = trigger;
  activeAiasActivity = activity;
  renderAiasModal();
  trigger?.setAttribute("aria-expanded", "true");
  openModal(aiasModalBackdrop, ".selected, #aias-modal-close-btn");
}

function closeAiasModal() {
  activeAiasTrigger?.setAttribute("aria-expanded", "false");
  closeModal(aiasModalBackdrop);
  activeAiasTrigger = null;
  activeAiasActivity = null;
}

function normalizeActivityLinkEntry(link) {
  if (!link) return null;
  const title = toPlainTextareaValue(link.title || "").trim();
  const url = normalizeExternalUrl(link.url || "");
  if (!title || !url) return null;
  return {
    id: link.id || nextId(),
    title,
    url
  };
}

function escapeMarkdownLinkLabel(value) {
  return String(value || "")
    .replaceAll("\\", "\\\\")
    .replaceAll("[", "\\[")
    .replaceAll("]", "\\]");
}

function migrateLegacyActivityLinks(description, links) {
  const normalizedDescription = toPlainTextareaValue(description);
  if (!Array.isArray(links) || !links.length) return normalizedDescription;
  const markdownLinks = links
    .map(normalizeActivityLinkEntry)
    .filter(Boolean)
    .map((link) => `[${escapeMarkdownLinkLabel(link.title)}](${link.url})`)
    .filter((markdownLink) => !normalizedDescription.includes(markdownLink));
  if (!markdownLinks.length) return normalizedDescription;
  return [normalizedDescription.trim(), markdownLinks.join("\n")].filter(Boolean).join("\n\n");
}

function normalizeExternalUrl(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";
  const candidate = /^[a-z][a-z0-9+.-]*:/i.test(raw) ? raw : `https://${raw}`;
  try {
    const parsed = new URL(candidate);
    if (!["http:", "https:"].includes(parsed.protocol)) return "";
    return parsed.toString();
  } catch (_) {
    return "";
  }
}

function totalDesignedMinutes() {
  return state.sessions.reduce(
    (sessionAcc, session) =>
      sessionAcc + session.activities.reduce((activityAcc, activity) => activityAcc + Number(activity.duration || 0), 0),
    0
  );
}

function totalSessionMinutes(session) {
  return session.activities.reduce((acc, activity) => acc + Number(activity.duration || 0), 0);
}

function getDayHours() {
  return Math.max(1, Number(state.meta.dayHours) || DEFAULT_DAY_HOURS);
}

function normalizePedagogicalTime(days, hours, minutes, dayHours = DEFAULT_DAY_HOURS) {
  let d = Math.max(0, Number(days) || 0);
  let h = Math.max(0, Number(hours) || 0);
  let m = Math.max(0, Number(minutes) || 0);

  h += Math.floor(m / 60);
  m %= 60;
  d += Math.floor(h / dayHours);
  h %= dayHours;

  return { days: d, hours: h, minutes: m };
}

function splitMinutesToPedagogicalTime(totalMinutes, dayHours = DEFAULT_DAY_HOURS) {
  const safeMinutes = Math.max(0, Number(totalMinutes) || 0);
  const dayMinutes = dayHours * 60;
  const days = Math.floor(safeMinutes / dayMinutes);
  const remainder = safeMinutes % dayMinutes;
  const hours = Math.floor(remainder / 60);
  const minutes = remainder % 60;
  return { days, hours, minutes };
}

function setLearningTime(days, hours, minutes) {
  const normalized = normalizePedagogicalTime(days, hours, minutes, getDayHours());
  state.meta.learningDays = normalized.days;
  state.meta.learningHours = normalized.hours;
  state.meta.learningMinutes = normalized.minutes;
}

function renderPartitionView() {
  if (state.topPanelCollapsed || state.meta.activeTab !== "chronology") return;
  const container = document.getElementById('chronology-container');
  if (!container) return;
  container.innerHTML = '';

  // Create and render partition controls panel
  const controlsDiv = document.createElement('div');
  controlsDiv.className = 'partition-controls';
  controlsDiv.innerHTML = `
    <span class="partition-controls-label">${t("partitionLinesLabel")} :</span>
    <button class="partition-config-btn" id="partition-edit-btn">${t("partitionConfigure")}</button>
  `;
  container.appendChild(controlsDiv);

  // Wire up the config button
  document.getElementById('partition-edit-btn')?.addEventListener('click', openPartitionConfigModal);

  // Render partition for each session
  state.sessions.forEach((session, sessionIndex) => {
    const sessionDiv = document.createElement('div');
    sessionDiv.className = 'partition-session';

    // Session header with title and total duration
    const header = document.createElement('div');
    header.className = 'partition-session-header';

    const title = document.createElement('div');
    title.className = 'partition-session-title';
    title.textContent = session.title || `${t("partitionSession")} ${sessionIndex + 1}`;

    const total = document.createElement('div');
    total.className = 'partition-session-total';
    const totalDuration = session.activities.reduce((sum, a) => sum + (Number(a.duration) || 0), 0);
    total.textContent = `${t("partitionTotal")}: ${totalDuration} min`;

    header.appendChild(title);
    header.appendChild(total);
    sessionDiv.appendChild(header);

    // Calculate cumulative durations and positions for all activities
    const activityPositions = [];
    let cumulativeDuration = 0;
    session.activities.forEach((activity) => {
      const duration = Number(activity.duration) || 0;
      const startPercent = totalDuration > 0 ? (cumulativeDuration / totalDuration) * 100 : 0;
      const widthPercent = totalDuration > 0 ? (duration / totalDuration) * 100 : 0;
      activityPositions.push({ activity, startPercent, widthPercent });
      cumulativeDuration += duration;
    });

    // Render partition lines (one per visible modalite config)
    state.partitionLineConfig.filter(line => line.visible).forEach((lineConfig) => {
      const lineDiv = document.createElement('div');
      lineDiv.className = 'partition-line';

      // Line label (modalite name)
      const label = document.createElement('div');
      label.className = 'partition-line-label';
      label.textContent = lineConfig.label;
      lineDiv.appendChild(label);

      // Track container for blocks (relative positioning)
      const track = document.createElement('div');
      track.className = 'partition-line-track';

      // Add activity blocks that match this line's modalite
      activityPositions.forEach(({ activity, startPercent, widthPercent }) => {
        // Check if activity matches this line configuration
        const matchesLine = (() => {
          if (lineConfig.type === 'locationMode') return activity.locationMode === lineConfig.value;
          if (lineConfig.type === 'groupMode') return activity.groupMode === lineConfig.value;
          if (lineConfig.type === 'syncMode') return activity.syncMode === lineConfig.value;
          if (lineConfig.type === 'teachingMode') return activity.teachingMode === lineConfig.value;
          return false;
        })();

        if (matchesLine && widthPercent > 0) {
          const block = document.createElement('div');
          block.className = 'partition-block';
          block.style.left = startPercent + '%';
          block.style.width = widthPercent + '%';
          block.style.backgroundColor = colorForType(activity.type);

          // Type label (abbreviated)
          const typeLabel = document.createElement('div');
          typeLabel.className = 'partition-block-label';
          const abbreviation = labelForType(activity.type).substring(0, 3).toUpperCase();
          typeLabel.textContent = abbreviation;

          // Duration label
          const durationLabel = document.createElement('div');
          durationLabel.className = 'partition-block-duration';
          durationLabel.textContent = `${activity.duration}m`;

          block.appendChild(typeLabel);
          block.appendChild(durationLabel);

          // Optional: Add hover tooltip with activity details
          block.title = `${labelForType(activity.type)} - ${activity.duration}m`;

          track.appendChild(block);
        }
      });

      if (track.children.length > 0) track.classList.add('has-blocks');
      lineDiv.appendChild(track);
      sessionDiv.appendChild(lineDiv);
    });

    // Percentage scale (0%, 25%, 50%, 75%, 100%)
    const scale = document.createElement('div');
    scale.className = 'partition-scale';
    [0, 25, 50, 75, 100].forEach(pct => {
      const mark = document.createElement('div');
      mark.className = 'partition-scale-mark';
      mark.textContent = pct + '%';
      scale.appendChild(mark);
    });
    sessionDiv.appendChild(scale);

    container.appendChild(sessionDiv);
  });
}

function renderTopPanel() {
  topPanel.classList.toggle("collapsed", state.topPanelCollapsed);
  const toggleLabel = state.topPanelCollapsed ? t("expandPanel") : t("collapsePanel");
  topPanelToggleBtn.setAttribute("aria-label", toggleLabel);
  topPanelToggleBtn.setAttribute("title", toggleLabel);
  topPanelToggleBtn.setAttribute("aria-expanded", state.topPanelCollapsed ? "false" : "true");

  const panelExpanded = !state.topPanelCollapsed;
  const settingsActive = state.meta.activeTab === "settings";
  const analysisActive = state.meta.activeTab === "analysis";
  const chronologyActive = state.meta.activeTab === "chronology";

  topPanelBody.toggleAttribute("inert", !panelExpanded);
  topPanelBody.setAttribute("aria-hidden", panelExpanded ? "false" : "true");

  if (panelExpanded && settingsActive) {
    metaNameInput.value = state.meta.name;
    metaLearningDaysInput.value = state.meta.learningDays;
    metaLearningHoursInput.value = state.meta.learningHours;
    metaLearningMinutesInput.value = state.meta.learningMinutes;
    metaDeliverySelect.value = state.meta.modeDelivery;
    metaSchoolSystemSelect.value = state.meta.schoolSystem;
    renderSchoolLevelOptions();
    metaDayHoursInput.value = getDayHours();
    metaSizeClassInput.value = state.meta.sizeClass;
    metaDesignersInput.value = state.meta.designers;
    metaTrainersInput.value = state.meta.trainers;
    metaDescriptionInput.value = state.meta.description;
    metaCommandInput.value = state.meta.command;
    metaPersonasInput.value = state.meta.personas;
    ensureMarkdownPreviews(timelineView);
    renderOutcomes();
  }

  topTabSettings.classList.toggle("active", panelExpanded && state.meta.activeTab === "settings");
  topTabAnalysis.classList.toggle("active", panelExpanded && state.meta.activeTab === "analysis");
  topTabChronology.classList.toggle("active", panelExpanded && state.meta.activeTab === "chronology");
  topTabSettings.setAttribute("aria-selected", state.meta.activeTab === "settings" ? "true" : "false");
  topTabAnalysis.setAttribute("aria-selected", state.meta.activeTab === "analysis" ? "true" : "false");
  topTabChronology.setAttribute("aria-selected", state.meta.activeTab === "chronology" ? "true" : "false");
  topTabSettings.tabIndex = state.meta.activeTab === "settings" ? 0 : -1;
  topTabAnalysis.tabIndex = state.meta.activeTab === "analysis" ? 0 : -1;
  topTabChronology.tabIndex = state.meta.activeTab === "chronology" ? 0 : -1;
  timelineView.classList.toggle("hidden", !settingsActive);
  analysisView.classList.toggle("hidden", !analysisActive);
  chronologyView.classList.toggle("hidden", !chronologyActive);
  timelineView.setAttribute("aria-hidden", panelExpanded && settingsActive ? "false" : "true");
  analysisView.setAttribute("aria-hidden", panelExpanded && analysisActive ? "false" : "true");
  chronologyView.setAttribute("aria-hidden", panelExpanded && chronologyActive ? "false" : "true");

  if (panelExpanded && settingsActive) {
    const designed = splitMinutesToPedagogicalTime(totalDesignedMinutes(), getDayHours());
    metaDesignedDaysInput.value = designed.days;
    metaDesignedHoursInput.value = designed.hours;
    metaDesignedMinutesInput.value = designed.minutes;

  }

  if (panelExpanded && analysisActive) renderAnalysisPanel();
  updateTabSlider();
}

function updateTabSlider() {
  const panelExpanded = !state.topPanelCollapsed;
  const activeBtn =
    panelExpanded && state.meta.activeTab === "settings" ? topTabSettings :
    panelExpanded && state.meta.activeTab === "analysis"  ? topTabAnalysis :
    panelExpanded && state.meta.activeTab === "chronology" ? topTabChronology :
    null;

  if (!activeBtn || !topTabSlider) return;

  const container = topTabSlider.parentElement;
  const containerLeft = container.getBoundingClientRect().left;
  const btnRect = activeBtn.getBoundingClientRect();

  topTabSlider.style.left  = (btnRect.left - containerLeft) + "px";
  topTabSlider.style.width = btnRect.width + "px";
  topTabSlider.style.opacity = "1";
}

function labelForType(typeId) {
  return LEARNING_TYPES.find((type) => type.id === typeId)?.label || typeId;
}

function labelForGroupMode(groupMode) {
  if (groupMode === "whole") return t("group_whole");
  if (groupMode === "subgroups") return t("group_subgroups");
  return t("group_individual");
}

function labelForTeachingMode(mode) {
  return getOption(TEACHING_OPTIONS, mode)?.label || t("teaching_undefined");
}

function slidersToString(sliders) {
  if (!Array.isArray(sliders)) return typeof sliders === "string" ? sliders : "";
  return sliders.map((o) => {
    const label = o.verb || o.categoryLabel || "";
    return label ? `${label}: ${o.text || ""}` : (o.text || "");
  }).filter(Boolean).join("\n");
}

function labelForSyncMode(mode) {
  return mode === "async" ? t("sync_async") : t("sync_sync");
}

function labelForLocationMode(mode) {
  if (!mode) return "-";
  return LOCATION_OPTIONS.find((option) => option.value === mode)?.label || mode;
}

function labelForDeliveryMode(mode) {
  if (!mode) return "-";
  if (mode === "online") return t("modeOnline");
  if (mode === "hybrid") return t("modeHybrid");
  return t("modeOnsite");
}

function labelForSchoolSystem(system) {
  const option = SCHOOL_SYSTEM_OPTIONS.find((candidate) => candidate.value === system);
  return localizedSchoolLabel(option) || "-";
}

function labelForSchoolLevel(level, system = state.meta.schoolSystem) {
  return localizedSchoolLabel(schoolLevelOption(level, system)) || "-";
}

function labelForEvaluationMode(mode) {
  if (mode === "diagnostic") return t("eval_diagnostic");
  if (mode === "formative") return t("eval_formative");
  if (mode === "summative") return t("eval_summative");
  if (mode === "certificative") return t("eval_certificative");
  return t("eval_none");
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function syncExportMomentsSelection() {
  const total = state.sessions.length;
  const selected = getExportSessionEntries(exportSessionIds).length;
  if (exportMomentsAllInput) {
    exportMomentsAllInput.disabled = total === 0;
    exportMomentsAllInput.checked = total > 0 && selected === total;
    exportMomentsAllInput.indeterminate = selected > 0 && selected < total;
  }
  if (exportMomentsSummary) {
    const selectionLabel = t("exportMomentsSelection");
    exportMomentsSummary.textContent = total === 0
      ? ""
      : typeof selectionLabel === "function"
        ? selectionLabel(selected, total)
        : `${selected}/${total}`;
  }
}

function renderExportMoments() {
  if (!exportMomentsList) return;
  const allIds = new Set(state.sessions.map(exportSessionKey));
  const selectedIds = exportSessionIds instanceof Set ? exportSessionIds : allIds;
  exportMomentsList.innerHTML = "";

  state.sessions.forEach((session, sessionIndex) => {
    const sessionId = exportSessionKey(session);
    const option = document.createElement("label");
    option.className = "export-moment-option";

    const input = document.createElement("input");
    input.type = "checkbox";
    input.value = sessionId;
    input.checked = selectedIds.has(sessionId);
    input.dataset.exportSessionId = sessionId;

    const number = document.createElement("span");
    number.className = "export-moment-number";
    number.textContent = `${sessionIndex + 1}.`;

    const title = document.createElement("span");
    title.className = "export-moment-title";
    title.textContent = session.title || defaultSessionTitle(sessionIndex + 1);

    input.addEventListener("change", () => {
      if (!(exportSessionIds instanceof Set)) exportSessionIds = new Set(allIds);
      if (input.checked) exportSessionIds.add(sessionId);
      else exportSessionIds.delete(sessionId);
      syncExportMomentsSelection();
      updateExportPreview(exportFormatSelect?.value || "markdown", exportScope);
    });

    option.append(input, number, title);
    exportMomentsList.appendChild(option);
  });

  exportMomentsList.classList.toggle("hidden", state.sessions.length === 0);
  exportMomentsEmpty?.classList.toggle("hidden", state.sessions.length > 0);
  syncExportMomentsSelection();
}

async function downloadBlob(content, type, filename) {
  const blob = new Blob([content], { type });
  if (typeof navigator !== "undefined" && typeof navigator.msSaveOrOpenBlob === "function") {
    navigator.msSaveOrOpenBlob(blob, filename);
    return;
  }

  const userAgent = typeof navigator !== "undefined" ? String(navigator.userAgent || "") : "";
  const isTouchDevice =
    typeof navigator !== "undefined" &&
    (Number(navigator.maxTouchPoints || 0) > 0 || /Android|iPhone|iPad|iPod/i.test(userAgent));
  const isSafariLike =
    /Safari/i.test(userAgent) &&
    !/Chrome|Chromium|CriOS|Edg|OPR|Firefox|FxiOS|Android/i.test(userAgent);

  if (
    isTouchDevice &&
    typeof File === "function" &&
    typeof navigator !== "undefined" &&
    typeof navigator.canShare === "function" &&
    typeof navigator.share === "function"
  ) {
    try {
      const file = new File([blob], filename, { type });
      if (navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: filename });
        return;
      }
    } catch (error) {
      if (error?.name === "AbortError") return;
    }
  }

  if (isSafariLike && typeof FileReader !== "undefined") {
    const popup = window.open("", "_blank", "noopener");
    const reader = new FileReader();
    reader.onloadend = () => {
      const dataUrl = String(reader.result || "");
      if (!dataUrl) return;
      if (popup) {
        popup.location.replace(dataUrl);
      } else {
        window.location.href = dataUrl;
      }
    };
    reader.readAsDataURL(blob);
    return;
  }

  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.rel = "noopener";
  a.style.display = "none";
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function clearExportPreviewUrl() {
  if (!exportPreviewObjectUrl) return;
  URL.revokeObjectURL(exportPreviewObjectUrl);
  exportPreviewObjectUrl = "";
}

function isCopyableExportFormat(format = "json") {
  const chosen = String(format).toLowerCase();
  return chosen === "json" || chosen === "md" || chosen === "markdown" || chosen === "html";
}

function updateExportPreview(format = exportFormatSelect?.value || "json", scope = exportScope) {
  const normalizedScope = normalizeExportScope(scope);
  const { content, type } = getExportPayload(format, normalizedScope, exportSessionIds);
  const filename = getExportFilename(format, normalizedScope);
  const text = typeof content === "string" ? content : "";
  const isCopyable = isCopyableExportFormat(format);
  const exportPreviewCopy = document.getElementById("export-result-modal-copy");
  clearExportPreviewUrl();
  exportPreviewObjectUrl = URL.createObjectURL(new Blob([content], { type }));
  if (exportPreviewCopy) {
    exportPreviewCopy.textContent = isCopyable ? t("exportPreviewCopy") : t("exportDownloadOnly");
  }
  if (exportResultText) {
    exportResultText.value = isCopyable ? text : "";
  }
  if (exportPreviewDetails) {
    exportPreviewDetails.classList.toggle("hidden", !isCopyable);
  }
  if (exportResultCopyBtn) {
    exportResultCopyBtn.classList.toggle("hidden", !isCopyable);
  }
  return { content, type, filename };
}

function getFocusableElements(container) {
  return Array.from(
    container.querySelectorAll(
      "button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])"
    )
  ).filter((el) => !el.closest(".hidden"));
}

function openModal(backdrop, firstSelector = "button, select, input, textarea") {
  previousFocusedElement = document.activeElement;
  activeModalBackdrop = backdrop;
  backdrop.classList.remove("hidden");
  backdrop.setAttribute("aria-hidden", "false");
  const firstTarget = backdrop.querySelector(firstSelector) || getFocusableElements(backdrop)[0];
  if (firstTarget) firstTarget.focus();
}

function closeModal(backdrop) {
  backdrop.classList.add("hidden");
  backdrop.setAttribute("aria-hidden", "true");
  if (activeModalBackdrop === backdrop) {
    activeModalBackdrop = null;
  }
  if (previousFocusedElement && typeof previousFocusedElement.focus === "function") {
    previousFocusedElement.focus();
  }
}

function openExportModal() {
  clearExportPreviewUrl();
  exportScope = "full";
  exportSessionIds = new Set(state.sessions.map(exportSessionKey));
  if (exportMomentsDetails) exportMomentsDetails.open = false;
  if (exportPreviewDetails) exportPreviewDetails.open = false;
  if (exportScopeFullInput) exportScopeFullInput.checked = true;
  if (exportScopeStudentsInput) exportScopeStudentsInput.checked = false;
  renderExportMoments();
  exportModalBackdrop.querySelector("#export-modal-title").textContent = t("exportTitle");
  exportFormatSelect.value = "markdown";
  if (exportFilenameInput) {
    exportFilenameInput.value = getDefaultExportName(exportFormatSelect.value, exportScope);
  }
  updateExportPreview(exportFormatSelect.value, exportScope);
  openModal(exportModalBackdrop, "#export-scope-full-input");
}

function closeExportModal() {
  clearExportPreviewUrl();
  closeModal(exportModalBackdrop);
}

function openImportPicker(format = "") {
  const normalized = String(format || "").toLowerCase();
  importFileInput.dataset.format = normalized;
  importFileInput.accept =
    normalized === "csv" ? ".csv,text/csv,text/plain"
    : normalized === "xlsx" ? ".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    : normalized === "ldj" ? ".ldj,.json,application/json,text/json,text/plain"
    : normalized === "markdown" || normalized === "md" ? ".md,.markdown,text/markdown,text/plain"
    : ".json,.ldj,.csv,.xlsx,.md,.markdown,application/json,text/csv,text/markdown";
  importFileInput.value = "";
  importFileInput.click();
}

// ── Modèles de scénarios ─────────────────────────────────────────────────────

const MODEL_CATALOG_URL = "models.php?format=json&v=3";

let modelCatalog = null;
let modelCatalogPromise = null;
let modelCatalogFailed = false;
let modelFilterQuery = "";
let modelFilterFamily = "";
let activeModelPreviewId = "";
let activeModelPreviewTrigger = null;
const modelPayloadCache = new Map();

function modelLabel(entry, field) {
  const suffix = currentLang() === "en" ? "En" : "Fr";
  return String(entry?.[field + suffix] ?? entry?.[field + "Fr"] ?? "");
}

function formatModelDuration(minutes) {
  const total = Math.max(0, Number(minutes) || 0);
  if (total < 60) return `${total} min`;
  const hours = Math.floor(total / 60);
  const rest = total % 60;
  return rest === 0 ? `${hours} h` : `${hours} h ${String(rest).padStart(2, "0")}`;
}

function loadModelCatalog() {
  if (modelCatalog) return Promise.resolve(modelCatalog);
  if (modelCatalogPromise) return modelCatalogPromise;

  modelCatalogPromise = fetch(MODEL_CATALOG_URL, { headers: { Accept: "application/json" } })
    .then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then((payload) => {
      if (!payload || !Array.isArray(payload.models)) throw new Error("Invalid catalog");
      modelCatalog = {
        families: Array.isArray(payload.families) ? payload.families : [],
        models: payload.models
      };
      modelCatalogFailed = false;
      return modelCatalog;
    })
    .catch((error) => {
      modelCatalogPromise = null;
      modelCatalogFailed = true;
      throw error;
    });

  return modelCatalogPromise;
}

function matchesModelFilter(entry) {
  if (modelFilterFamily && entry.family !== modelFilterFamily) return false;
  if (!modelFilterQuery) return true;
  const haystack = [
    modelLabel(entry, "title"),
    modelLabel(entry, "summary"),
    modelLabel(entry, "familyLabel"),
    entry.keywords || ""
  ].join(" ").toLowerCase();
  return modelFilterQuery
    .split(/\s+/)
    .filter(Boolean)
    .every((token) => haystack.includes(token));
}

function renderModelFamilyOptions() {
  if (!importModelsFamily) return;
  const previous = modelFilterFamily;
  importModelsFamily.textContent = "";

  const allOption = document.createElement("option");
  allOption.value = "";
  allOption.textContent = t("importModelsFamilyAll");
  importModelsFamily.appendChild(allOption);

  (modelCatalog?.families || []).forEach((family) => {
    const option = document.createElement("option");
    option.value = family.id;
    option.textContent = currentLang() === "en" ? family.labelEn : family.labelFr;
    importModelsFamily.appendChild(option);
  });

  importModelsFamily.value = previous;
  if (importModelsFamily.value !== previous) {
    modelFilterFamily = "";
    importModelsFamily.value = "";
  }
}

function buildModelCard(entry) {
  const card = document.createElement("article");
  card.className = "import-model-card";
  card.dataset.modelId = entry.id;
  card.setAttribute("role", "listitem");

  const head = document.createElement("span");
  head.className = "import-model-head";
  const icon = document.createElement("i");
  icon.className = `${entry.icon || "fa-solid fa-shapes"} import-model-icon`;
  icon.setAttribute("aria-hidden", "true");
  const title = document.createElement("span");
  title.className = "import-model-title";
  title.textContent = modelLabel(entry, "title");
  head.append(icon, title);

  const chips = document.createElement("span");
  chips.className = "import-model-chips";
  [
    modelLabel(entry, "familyLabel"),
    formatModelDuration(entry.minutes),
    `${entry.momentCount} ${t("importModelsUnitMoments")}`,
    `${entry.activityCount} ${t("importModelsUnitActivities")}`
  ].forEach((label) => {
    const chip = document.createElement("span");
    chip.className = "import-model-chip";
    chip.textContent = label;
    chips.appendChild(chip);
  });

  const summary = document.createElement("span");
  summary.className = "import-model-summary";
  summary.textContent = modelLabel(entry, "summary");

  card.append(head, chips, summary);

  const types = document.createElement("span");
  types.className = "import-model-types";
  (entry.outline || []).forEach((moment) => {
    (moment.activities || []).forEach((activity) => {
      const dot = document.createElement("span");
      dot.className = `import-model-dot type-${activity.type}`;
      dot.title = currentLang() === "en" ? activity.typeLabelEn : activity.typeLabelFr;
      types.appendChild(dot);
    });
  });
  if (types.childElementCount) card.appendChild(types);

  const placeholders = currentLang() === "en"
    ? (entry.placeholdersEn || entry.placeholders || [])
    : (entry.placeholdersFr || entry.placeholders || []);
  if (Array.isArray(placeholders) && placeholders.length) {
    const todo = document.createElement("span");
    todo.className = "import-model-todo";
    todo.textContent = `${t("importModelsToComplete")} ${placeholders.join(" · ")}`;
    card.appendChild(todo);
  }

  const actions = document.createElement("span");
  actions.className = "import-model-actions";

  const previewButton = document.createElement("button");
  previewButton.type = "button";
  previewButton.className = "btn btn-light import-model-action";
  previewButton.dataset.modelAction = "preview";
  previewButton.innerHTML = `<span class="btn-label"><i class="fa-solid fa-eye btn-icon-inline" aria-hidden="true"></i>${escapeHtml(t("importModelPreviewButton"))}</span>`;

  const useButton = document.createElement("button");
  useButton.type = "button";
  useButton.className = "btn btn-light import-model-action";
  useButton.dataset.modelAction = "apply";
  useButton.innerHTML = `<span class="btn-label"><i class="fa-solid fa-file-import btn-icon-inline" aria-hidden="true"></i>${escapeHtml(t("importModelUseButton"))}</span>`;

  actions.append(previewButton, useButton);
  card.appendChild(actions);

  return card;
}

function renderModelList() {
  if (!importModelsList || !importModelsStatus) return;
  importModelsList.textContent = "";

  if (modelCatalogFailed) {
    importModelsStatus.textContent = t("importModelsError");
    importModelsStatus.classList.add("import-models-status-error");
    return;
  }
  if (!modelCatalog) {
    importModelsStatus.textContent = t("importModelsLoading");
    importModelsStatus.classList.remove("import-models-status-error");
    return;
  }

  importModelsStatus.classList.remove("import-models-status-error");
  const matches = modelCatalog.models.filter(matchesModelFilter);
  if (!matches.length) {
    importModelsStatus.textContent = t("importModelsNone");
    return;
  }
  importModelsStatus.textContent = matches.length === 1
    ? t("importModelsCountOne")
    : t("importModelsCount").replace("{count}", String(matches.length));

  matches.forEach((entry) => importModelsList.appendChild(buildModelCard(entry)));
}

async function loadModelPayload(modelId) {
  const id = String(modelId || "");
  if (!id) throw new Error("Missing model id");
  const language = currentLang() === "en" ? "en" : "fr";
  const cacheKey = `${language}:${id}`;
  if (modelPayloadCache.has(cacheKey)) return modelPayloadCache.get(cacheKey);

  const request = fetch(`${MODEL_CATALOG_URL}&model=${encodeURIComponent(id)}&lang=${language}`, {
    headers: { Accept: "application/json" }
  })
    .then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then((payload) => {
      if (!payload?.design || !payload?.model) throw new Error("Invalid model");
      return payload;
    })
    .catch((error) => {
      modelPayloadCache.delete(cacheKey);
      throw error;
    });

  modelPayloadCache.set(cacheKey, request);
  return request;
}

function setModelPreviewVisible(visible) {
  importModal?.classList.toggle("is-previewing-model", visible);
  importModelPreview?.classList.toggle("hidden", !visible);
  importModalBackdrop?.setAttribute(
    "aria-labelledby",
    visible ? "import-model-preview-title" : "import-modal-title"
  );
}

function addModelPreviewChip(label) {
  if (!importModelPreviewChips) return;
  const chip = document.createElement("span");
  chip.className = "import-model-chip";
  chip.textContent = label;
  importModelPreviewChips.appendChild(chip);
}

function addModelPreviewText(parent, label, value, className = "") {
  const text = String(value || "").trim();
  if (!text) return;
  const block = document.createElement("div");
  block.className = `import-model-preview-text ${className}`.trim();
  const heading = document.createElement("strong");
  heading.textContent = label;
  const content = document.createElement("p");
  content.textContent = text;
  block.append(heading, content);
  parent.appendChild(block);
}

function renderModelPreview(payload) {
  const entry = payload.model;
  const design = hydrateState(payload.design, null);
  if (!design) throw new Error("Invalid model");

  importModelPreviewTitle.textContent = modelLabel(entry, "title") || design.meta.name;
  importModelPreviewSummary.textContent = modelLabel(entry, "summary");
  importModelPreviewChips.textContent = "";
  addModelPreviewChip(modelLabel(entry, "familyLabel"));
  addModelPreviewChip(formatModelDuration(entry.minutes));
  addModelPreviewChip(`${entry.momentCount} ${t("importModelsUnitMoments")}`);
  addModelPreviewChip(`${entry.activityCount} ${t("importModelsUnitActivities")}`);
  importModelPreviewContent.textContent = "";

  design.sessions.forEach((session, sessionIndex) => {
    const moment = document.createElement("section");
    moment.className = "import-model-preview-moment";

    const momentHeader = document.createElement("header");
    momentHeader.className = "import-model-preview-moment-header";
    const number = document.createElement("span");
    number.className = "import-model-preview-moment-number";
    number.textContent = String(sessionIndex + 1);
    const title = document.createElement("h3");
    const outlineMoment = entry.outline?.[sessionIndex];
    const previewMomentTitle = currentLang() === "en"
      ? (outlineMoment?.titleEn || session.title)
      : (outlineMoment?.titleFr || outlineMoment?.title || session.title);
    title.textContent = String(previewMomentTitle || "")
      .replace(/^\s*\d+\s*[.)·:–—-]\s*/, "");
    momentHeader.append(number, title);
    moment.appendChild(momentHeader);
    addModelPreviewText(moment, t("importModelPreviewObjectives"), session.objectives, "import-model-preview-objectives");

    const activities = document.createElement("div");
    activities.className = "import-model-preview-activities";
    session.activities.forEach((activity, activityIndex) => {
      const activityCard = document.createElement("article");
      activityCard.className = `import-model-preview-activity type-${activity.type}`;

      const activityHeader = document.createElement("header");
      activityHeader.className = "import-model-preview-activity-header";
      const activityName = document.createElement("strong");
      activityName.textContent = t("importModelPreviewActivity").replace("{number}", String(activityIndex + 1));
      const activityMeta = document.createElement("span");
      activityMeta.className = "import-model-preview-activity-meta";
      const typeLabel = document.createElement("span");
      typeLabel.className = `import-model-preview-type type-${activity.type}`;
      typeLabel.textContent = t(`lt_${activity.type}`);
      const duration = document.createElement("span");
      duration.textContent = formatModelDuration(activity.duration);
      activityMeta.append(typeLabel, duration);
      activityHeader.append(activityName, activityMeta);
      activityCard.appendChild(activityHeader);
      addModelPreviewText(activityCard, t("importModelPreviewTeacher"), activity.description);
      addModelPreviewText(activityCard, t("importModelPreviewStudents"), activity.instructions, "import-model-preview-instructions");
      activities.appendChild(activityCard);
    });
    moment.appendChild(activities);
    importModelPreviewContent.appendChild(moment);
  });
}

async function openModelPreview(modelId, trigger) {
  activeModelPreviewId = String(modelId || "");
  activeModelPreviewTrigger = trigger || null;
  if (!activeModelPreviewId) return;
  const requestedId = activeModelPreviewId;

  setModelPreviewVisible(true);
  importModelPreviewTitle.textContent = "";
  importModelPreviewSummary.textContent = "";
  importModelPreviewChips.textContent = "";
  importModelPreviewContent.textContent = "";
  importModelPreviewStatus.textContent = t("importModelPreviewLoading");
  importModelPreviewStatus.classList.remove("import-model-preview-status-error");
  importModelPreviewBackBtn?.focus();

  try {
    const payload = await loadModelPayload(requestedId);
    if (activeModelPreviewId !== requestedId) return;
    renderModelPreview(payload);
    importModelPreviewStatus.textContent = "";
  } catch (_) {
    if (activeModelPreviewId !== requestedId) return;
    importModelPreviewStatus.textContent = t("importModelPreviewError");
    importModelPreviewStatus.classList.add("import-model-preview-status-error");
  }
}

function closeModelPreview({ restoreFocus = true } = {}) {
  setModelPreviewVisible(false);
  activeModelPreviewId = "";
  if (restoreFocus) activeModelPreviewTrigger?.focus();
  activeModelPreviewTrigger = null;
}

async function applyModel(modelId) {
  const id = String(modelId || "");
  if (!id) return false;
  try {
    const payload = await loadModelPayload(id);
    const hydrated = hydrateState(payload?.design, null);
    if (!hydrated) throw new Error("Invalid model");
    hydrated.meta.uiLanguage = preferredInterfaceLanguage(currentLang());
    delete hydrated.meta.remoteDesignId;
    delete hydrated.meta.remoteUpdatedAt;
    delete hydrated.meta.remoteRevision;
    state = hydrated;
    documentGeneration++;
    saveState();
    render();
    window.learningDesignerClearRemoteDesignUrl?.();
    const name = modelLabel(payload?.model || {}, "title") || hydrated.meta.name;
    const message = t("importModelApplied").replace("{name}", name);
    showNotice(message, "success");
    announce(message);
    return true;
  } catch (_) {
    showNotice(t("importModelFailed"), "error");
    return false;
  }
}

function openImportModal() {
  closeModelPreview({ restoreFocus: false });
  modelFilterQuery = "";
  modelFilterFamily = "";
  if (importModelsSearch) importModelsSearch.value = "";
  renderModelFamilyOptions();
  renderModelList();
  openModal(importModalBackdrop, "#import-file-btn");

  if (!modelCatalog) {
    loadModelCatalog()
      .then(() => {
        renderModelFamilyOptions();
        renderModelList();
      })
      .catch(() => {
        renderModelList();
      });
  }
}

function closeImportModal() {
  setImportDropActive(false);
  closeModelPreview({ restoreFocus: false });
  closeModal(importModalBackdrop);
}

async function maybeApplyRequestedModel() {
  const params = new URLSearchParams(window.location.search);
  const requested = String(params.get("model") || "").trim();
  if (!requested) return;
  if (params.get("remote_design_id")) return;
  const applied = await applyModel(requested);
  if (applied) {
    params.delete("model");
    const query = params.toString();
    const url = `${window.location.pathname}${query ? `?${query}` : ""}${window.location.hash}`;
    window.history.replaceState({}, "", url);
  }
}

function openInfoModal() {
  openModal(infoModalBackdrop, "#info-modal-close-btn");
}

window.learningDesignerOpenInfo = openInfoModal;

function closeInfoModal() {
  closeModal(infoModalBackdrop);
}


document.addEventListener("keydown", (event) => {
  const activeFullscreenField = document.querySelector(".expandable-field.fullscreen");
  if (activeFullscreenField && event.key === "Escape" && !activeModalBackdrop && !activeToolPicker && !activeChoiceMenu) {
    event.preventDefault();
    activeFullscreenField.querySelector(".expand-btn")?.click();
    return;
  }
  if (activeChoiceMenu && event.key === "Escape") {
    event.preventDefault();
    closeChoiceMenu(true);
    return;
  }
  if (activeToolPicker && event.key === "Escape") {
    event.preventDefault();
    closeToolPicker(true);
    return;
  }
  if (!activeModalBackdrop) return;
  if (event.key === "Escape") {
    event.preventDefault();
    if (activeModalBackdrop === exportModalBackdrop) closeExportModal();
    if (activeModalBackdrop === importModalBackdrop) {
      if (importModal?.classList.contains("is-previewing-model")) closeModelPreview();
      else closeImportModal();
    }
    if (activeModalBackdrop === infoModalBackdrop) closeInfoModal();
    if (activeModalBackdrop === aiasModalBackdrop) closeAiasModal();
    return;
  }
  if (event.key !== "Tab") return;
  const focusables = getFocusableElements(activeModalBackdrop);
  if (!focusables.length) return;
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
});

document.addEventListener("click", (event) => {
  if (activeToolPicker) {
    if (!activeToolPicker.contains(event.target) && !event.target.closest(".select-tools-btn")) {
      closeToolPicker();
    }
  }
  if (!activeChoiceMenu) return;
  if (activeChoiceMenu.contains(event.target)) return;
  if (event.target.closest(".choice-btn")) return;
  closeChoiceMenu();
});

const debouncedResizeLayoutRefresh = debounce(() => {
  updateResponsiveButtonLabels();
  renderTopPanel();
}, 120);

let topPanelRenderFrame = 0;
let partitionRenderFrame = 0;

function scheduleTopPanelRender() {
  if (topPanelRenderFrame) return;
  topPanelRenderFrame = window.requestAnimationFrame(() => {
    topPanelRenderFrame = 0;
    renderTopPanel();
  });
}

function schedulePartitionRender() {
  if (partitionRenderFrame) return;
  partitionRenderFrame = window.requestAnimationFrame(() => {
    partitionRenderFrame = 0;
    renderPartitionView();
  });
}

window.addEventListener("resize", closeChoiceMenu, { passive: true });
window.addEventListener("resize", debouncedResizeLayoutRefresh, { passive: true });
window.addEventListener("scroll", closeChoiceMenu, { capture: true, passive: true });

function stripGradientForSession(session) {
  if (!session.activities.length) return "linear-gradient(90deg, #cccccc, #bbbbbb)";
  const parts = [];
  const total = session.activities.length;
  for (let i = 0; i < total; i += 1) {
    const start = Math.round((i / total) * 100);
    const end = Math.round(((i + 1) / total) * 100);
    parts.push(`${colorForType(session.activities[i].type)} ${start}% ${end}%`);
  }
  return `linear-gradient(90deg, ${parts.join(", ")})`;
}

function isInteractiveTarget(target) {
  return Boolean(target.closest("textarea, input, select, button, option"));
}

function clearSessionDropIndicators() {
  board
    .querySelectorAll(".session-card.drop-before, .session-card.drop-after")
    .forEach((el) => el.classList.remove("drop-before", "drop-after"));
  delete board.dataset.dropSessionId;
  delete board.dataset.dropPosition;
}

function clearActivityDropIndicators() {
  board
    .querySelectorAll(".activity-card.drop-before, .activity-card.drop-after")
    .forEach((el) => el.classList.remove("drop-before", "drop-after"));
  board.querySelectorAll(".activities.drop-append").forEach((el) => el.classList.remove("drop-append"));
}

function clearDragIndicators() {
  clearSessionDropIndicators();
  clearActivityDropIndicators();
}

function cloneSerializableValue(value) {
  if (typeof window.structuredClone === "function") {
    return window.structuredClone(value);
  }
  return JSON.parse(JSON.stringify(value));
}

function copiedSessionTitle(title) {
  const sourceTitle = toPlainTextareaValue(title).trim();
  if (!sourceTitle) return "";

  const baseTitle = sourceTitle.replace(/\s+\((?:copie|copy)(?:\s+\d+)?\)$/i, "");
  const suffix = t("copySuffix");
  const usedTitles = new Set(
    state.sessions.map((session) => toPlainTextareaValue(session.title).trim().toLocaleLowerCase())
  );
  let copyNumber = 1;
  let candidate = `${baseTitle} (${suffix})`;
  while (usedTitles.has(candidate.toLocaleLowerCase())) {
    copyNumber += 1;
    candidate = `${baseTitle} (${suffix} ${copyNumber})`;
  }
  return candidate;
}

function cloneActivity(activity) {
  const copy = cloneSerializableValue(activity);
  copy.id = nextId();
  normalizeActivity(copy);
  return copy;
}

function cloneSession(session) {
  const copy = cloneSerializableValue(session);
  copy.id = nextId();
  copy.title = copiedSessionTitle(session.title);
  copy.notesExpanded = false;
  copy.activities = Array.isArray(session.activities)
    ? session.activities.map(cloneActivity)
    : [];
  return copy;
}

function duplicateActivity(sessionId, activityId) {
  const session = state.sessions.find((item) => item.id === sessionId);
  if (!session) return null;
  const activityIndex = session.activities.findIndex((activity) => activity.id === activityId);
  if (activityIndex < 0) return null;
  const copy = cloneActivity(session.activities[activityIndex]);
  session.activities.splice(activityIndex + 1, 0, copy);
  return copy;
}

function duplicateSession(sessionId) {
  const sessionIndex = state.sessions.findIndex((session) => session.id === sessionId);
  if (sessionIndex < 0) return null;
  const copy = cloneSession(state.sessions[sessionIndex]);
  state.sessions.splice(sessionIndex + 1, 0, copy);
  return copy;
}

function focusDuplicatedItem(kind, itemId) {
  window.requestAnimationFrame(() => {
    const candidates = kind === "session"
      ? board.querySelectorAll(".session-card, .grid-session-row")
      : board.querySelectorAll(".activity-card, .grid-activity-row");
    const target = Array.from(candidates).find((element) => (
      kind === "session"
        ? element.dataset.sessionId === itemId
        : element.dataset.activityId === itemId || element.dataset.actId === itemId
    ));
    if (!target) return;

    target.classList.add("is-duplicated");
    const gridButton = target.querySelector(
      kind === "session" ? ".grid-duplicate-session-btn" : ".grid-duplicate-activity-btn"
    );
    const focusTarget = gridButton || target;
    if (focusTarget === target && !target.hasAttribute("tabindex")) target.tabIndex = -1;
    focusTarget.focus({ preventScroll: true });
    const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches;
    target.scrollIntoView({
      behavior: reduceMotion ? "auto" : "smooth",
      block: "nearest",
      inline: "nearest"
    });
    window.setTimeout(() => target.classList.remove("is-duplicated"), 1400);
  });
}

function duplicateActivityAndRender(sessionId, activityId) {
  const copy = duplicateActivity(sessionId, activityId);
  if (!copy) return;
  saveState();
  render();
  focusDuplicatedItem("activity", copy.id);
  showNotice(t("activityDuplicated"), "success");
}

function duplicateSessionAndRender(sessionId) {
  const copy = duplicateSession(sessionId);
  if (!copy) return;
  saveState();
  render();
  focusDuplicatedItem("session", copy.id);
  showNotice(t("momentDuplicated"), "success");
}

function moveSession(sourceId, targetId, position) {
  if (sourceId === targetId) return;
  const sourceIndex = state.sessions.findIndex((session) => session.id === sourceId);
  if (sourceIndex < 0) return;
  const [movingSession] = state.sessions.splice(sourceIndex, 1);
  if (!targetId) {
    state.sessions.push(movingSession);
    return;
  }
  const targetIndex = state.sessions.findIndex((session) => session.id === targetId);
  if (targetIndex < 0) {
    state.sessions.push(movingSession);
    return;
  }
  const insertIndex = position === "before" ? targetIndex : targetIndex + 1;
  state.sessions.splice(insertIndex, 0, movingSession);
}

function moveActivity(sourceSessionId, activityId, targetSessionId, targetActivityId, position) {
  const sourceSession = state.sessions.find((session) => session.id === sourceSessionId);
  const targetSession = state.sessions.find((session) => session.id === targetSessionId);
  if (!sourceSession || !targetSession) return;
  if (sourceSessionId === targetSessionId && targetActivityId === activityId) return;

  const sourceIndex = sourceSession.activities.findIndex((activity) => activity.id === activityId);
  if (sourceIndex < 0) return;

  const [movingActivity] = sourceSession.activities.splice(sourceIndex, 1);
  if (!targetActivityId || position === "append") {
    targetSession.activities.push(movingActivity);
    return;
  }

  const targetIndex = targetSession.activities.findIndex((activity) => activity.id === targetActivityId);
  if (targetIndex < 0) {
    targetSession.activities.push(movingActivity);
    return;
  }

  const insertIndex = position === "before" ? targetIndex : targetIndex + 1;
  targetSession.activities.splice(insertIndex, 0, movingActivity);
}

function moveSessionByOffset(sessionId, offset) {
  const index = state.sessions.findIndex((session) => session.id === sessionId);
  if (index < 0) return false;
  const targetIndex = index + offset;
  if (targetIndex < 0 || targetIndex >= state.sessions.length) return false;
  const [session] = state.sessions.splice(index, 1);
  state.sessions.splice(targetIndex, 0, session);
  return true;
}

function moveActivityByOffset(sessionId, activityId, offset) {
  const session = state.sessions.find((item) => item.id === sessionId);
  if (!session) return false;
  const index = session.activities.findIndex((activity) => activity.id === activityId);
  if (index < 0) return false;
  const targetIndex = index + offset;
  if (targetIndex < 0 || targetIndex >= session.activities.length) return false;
  const [activity] = session.activities.splice(index, 1);
  session.activities.splice(targetIndex, 0, activity);
  return true;
}

board.addEventListener("dragover", (event) => {
  if (getBoardLayout() === "grid") return;
  if (!dragState || dragState.type !== "session") return;
  event.preventDefault();
  clearSessionDropIndicators();
  const targetCard = event.target.closest(".session-card");
  if (!targetCard) return;
  const rect = targetCard.getBoundingClientRect();
  const isListLayout = getBoardLayout() === "list";
  const position = isListLayout
    ? event.clientY < rect.top + rect.height / 2 ? "before" : "after"
    : event.clientX < rect.left + rect.width / 2 ? "before" : "after";
  targetCard.classList.add(position === "before" ? "drop-before" : "drop-after");
  board.dataset.dropSessionId = targetCard.dataset.sessionId;
  board.dataset.dropPosition = position;
});

board.addEventListener("drop", (event) => {
  if (getBoardLayout() === "grid") return;
  if (!dragState || dragState.type !== "session") return;
  event.preventDefault();
  const targetId = board.dataset.dropSessionId || null;
  const position = board.dataset.dropPosition || "after";
  moveSession(dragState.sessionId, targetId, position);
  saveState();
  render();
});

// ─── Grid (tableur) view ────────────────────────────────────────────────────

function buildGridSelect(options, currentValue, className) {
  const sel = document.createElement("select");
  sel.className = className;
  options.forEach(({ value, label }) => {
    const opt = document.createElement("option");
    opt.value = value;
    opt.textContent = label;
    if (value === currentValue) opt.selected = true;
    sel.appendChild(opt);
  });
  return sel;
}

function toPlainTextareaValue(value) {
  if (typeof value === "string") return value;
  if (value == null) return "";
  if (Array.isArray(value)) {
    return value.map((item) => toPlainTextareaValue(item).trim()).filter(Boolean).join("\n");
  }
  if (typeof value === "object") {
    if (typeof value.text === "string") return value.text;
    const commonTextKeys = ["details", "description", "content", "notes", "label", "title", "value", "name"];
    for (const key of commonTextKeys) {
      if (typeof value[key] === "string" && value[key].trim()) return value[key];
    }
    try {
      return JSON.stringify(value);
    } catch {
      return "";
    }
  }
  return String(value);
}

function buildGridSessionRow(session, sIdx) {
  const tr = document.createElement("tr");
  tr.className = "grid-session-row";
  tr.dataset.sessionId = session.id;

  const td = document.createElement("td");
  td.setAttribute("colspan", "12");

  const totalDur = session.activities.reduce((s, a) => s + (Number(a.duration) || 0), 0);

  const lbl = document.createElement("span");
  lbl.className = "grid-session-label";
  lbl.textContent = `${t("gridSessionPrefix")} ${sIdx + 1}`;

  const titleInput = document.createElement("input");
  titleInput.type = "text";
  titleInput.className = "grid-session-title-input";
  titleInput.value = session.title;
  titleInput.placeholder = t("sessionTitlePlaceholder");
  titleInput.addEventListener("input", (e) => {
    session.title = e.target.value;
    saveState();
    schedulePartitionRender();
  });

  const totalSpan = document.createElement("span");
  totalSpan.className = "grid-session-total";
  totalSpan.textContent = `— ${totalDur} min`;

  const duplicateButton = document.createElement("button");
  duplicateButton.type = "button";
  duplicateButton.className = "icon-btn duplicate-session-btn grid-duplicate-session-btn";
  duplicateButton.title = t("duplicateMoment");
  duplicateButton.setAttribute("aria-label", `${t("duplicateMoment")} ${sIdx + 1}`);
  duplicateButton.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
  duplicateButton.addEventListener("click", () => duplicateSessionAndRender(session.id));

  td.appendChild(lbl);
  td.appendChild(titleInput);
  td.appendChild(totalSpan);
  td.appendChild(duplicateButton);
  tr.appendChild(td);
  return tr;
}

function updateGridSessionTotal(session) {
  const row = Array.from(board.querySelectorAll(".grid-session-row"))
    .find((candidate) => candidate.dataset.sessionId === session.id);
  const total = row?.querySelector(".grid-session-total");
  if (total) total.textContent = `— ${totalSessionMinutes(session)} min`;
}

function buildGridActivityRow(session, act, aIdx) {
  normalizeActivity(act);
  const tr = document.createElement("tr");
  tr.className = "grid-activity-row";
  tr.dataset.actId = act.id;

  const mkTd = () => document.createElement("td");

  // Col 1 — #
  const numTd = mkTd();
  numTd.textContent = String(aIdx + 1);
  numTd.style.borderLeftColor = colorForType(act.type);
  tr.appendChild(numTd);

  // Col 2 — Type
  const typeTd = mkTd();
  const typeCell = document.createElement("div");
  typeCell.className = "grid-type-cell";
  const dot = document.createElement("span");
  dot.className = "grid-type-dot";
  dot.style.background = colorForType(act.type);
  const typeSel = buildGridSelect(
    LEARNING_TYPES.map(lt => ({ value: lt.id, label: lt.label })),
    act.type, "grid-type-select"
  );
  typeSel.addEventListener("change", (e) => {
    act.type = e.target.value;
    dot.style.background = colorForType(act.type);
    numTd.style.borderLeftColor = colorForType(act.type);
    saveState();
    renderTopPanel();
    renderPartitionView();
  });
  typeCell.appendChild(dot);
  typeCell.appendChild(typeSel);
  typeTd.appendChild(typeCell);
  tr.appendChild(typeTd);

  // Col 3 — Duration
  const durTd = mkTd();
  const durWrap = document.createElement("div");
  durWrap.className = "grid-dur-wrap";
  const durInput = document.createElement("input");
  durInput.type = "number";
  durInput.className = "grid-dur-input";
  durInput.min = "1";
  durInput.value = String(act.duration);
  durInput.addEventListener("input", (e) => {
    act.duration = Math.max(1, Number(e.target.value) || 1);
    saveState();
    updateGridSessionTotal(session);
    scheduleTopPanelRender();
    schedulePartitionRender();
  });
  const durUnit = document.createElement("span");
  durUnit.className = "grid-dur-unit";
  durUnit.textContent = "min";
  durWrap.appendChild(durInput);
  durWrap.appendChild(durUnit);
  durTd.appendChild(durWrap);
  tr.appendChild(durTd);

  // Col 4–8 — Select fields
  const selectCols = [
    { opts: LOCATION_OPTIONS,  val: act.locationMode,    key: "locationMode" },
    { opts: GROUP_MODE_OPTIONS, val: act.groupMode,       key: "groupMode" },
    { opts: SYNC_OPTIONS,       val: act.syncMode,        key: "syncMode" },
    {
      opts: [UNDEFINED_TEACHING_OPTION, ...TEACHING_OPTIONS],
      val: act.teachingMode,
      key: "teachingMode"
    },
    { opts: EVAL_OPTIONS,       val: act.evaluationMode,  key: "evaluationMode" },
  ];
  selectCols.forEach(({ opts, val, key }) => {
    const sTd = mkTd();
    const sel = buildGridSelect(
      opts.map(o => ({ value: o.value, label: o.label })),
      val, "grid-select"
    );
    sel.addEventListener("change", (e) => {
      act[key] = e.target.value;
      saveState();
      renderPartitionView();
    });
    sTd.appendChild(sel);
    tr.appendChild(sTd);
  });

  // Col 9 — AIAS
  const aiasTd = mkTd();
  const aiasOptions = [
    { value: "undecided", label: t("aiasUndecided") },
    { value: "not_applicable", label: t("aiasNotApplicable") },
    ...AIAS_LEVELS.map((definition) => ({
      value: `level_${definition.level}`,
      label: `AIAS ${definition.level} · ${t(definition.labelKey)}`
    }))
  ];
  const aiasValue = act.aias.status === "specified"
    ? `level_${act.aias.level}`
    : act.aias.status;
  const aiasSelect = buildGridSelect(aiasOptions, aiasValue, "grid-select grid-aias-select");
  applyAiasLevelClass(aiasSelect, act.aias);
  aiasSelect.addEventListener("change", (event) => {
    const selected = event.target.value;
    const levelMatch = selected.match(/^level_([1-5])$/);
    act.aias = levelMatch
      ? { version: AIAS_VERSION, status: "specified", level: Number(levelMatch[1]) }
      : { version: AIAS_VERSION, status: selected, level: null };
    applyAiasLevelClass(aiasSelect, act.aias);
    saveState();
  });
  aiasTd.appendChild(aiasSelect);
  tr.appendChild(aiasTd);

  // Col 10 — Description
  const descTd = mkTd();
  const descInput = document.createElement("textarea");
  descInput.className = "grid-desc-input";
  descInput.rows = 1;
  descInput.value = toPlainTextareaValue(act.description);
  descInput.placeholder = t("activityDescriptionPlaceholder") || "—";
  descInput.addEventListener("input", (e) => {
    act.description = e.target.value;
    saveState();
  });
  descTd.appendChild(descInput);
  tr.appendChild(descTd);

  // Col 11 — Instructions
  const instructionsTd = mkTd();
  const instructionsInput = document.createElement("textarea");
  instructionsInput.className = "grid-desc-input";
  instructionsInput.rows = 1;
  instructionsInput.value = toPlainTextareaValue(act.instructions);
  instructionsInput.placeholder = t("activityInstructionsPlaceholder") || "—";
  instructionsInput.addEventListener("input", (event) => {
    act.instructions = event.target.value;
    saveState();
  });
  instructionsTd.appendChild(instructionsInput);
  tr.appendChild(instructionsTd);

  // Col 12 — Actions ↑ ↓ dupliquer ✕
  const actTd = mkTd();
  const btns = document.createElement("div");
  btns.className = "grid-action-btns";

  const mkBtn = (label, title, handler, extraClass) => {
    const b = document.createElement("button");
    b.type = "button";
    b.className = "grid-action-btn" + (extraClass ? " " + extraClass : "");
    b.textContent = label;
    b.title = title;
    b.setAttribute("aria-label", title);
    b.addEventListener("click", handler);
    return b;
  };

  btns.appendChild(mkBtn("↑", t("partitionMoveUp"), () => {
    if (aIdx === 0) return;
    [session.activities[aIdx - 1], session.activities[aIdx]] =
      [session.activities[aIdx], session.activities[aIdx - 1]];
    saveState(); renderGridView(); renderTopPanel(); renderPartitionView();
  }));
  btns.appendChild(mkBtn("↓", t("partitionMoveDown"), () => {
    if (aIdx >= session.activities.length - 1) return;
    [session.activities[aIdx], session.activities[aIdx + 1]] =
      [session.activities[aIdx + 1], session.activities[aIdx]];
    saveState(); renderGridView(); renderTopPanel(); renderPartitionView();
  }));
  const sessionIndex = state.sessions.indexOf(session);
  const duplicateActivityLabel = [
    `${t("duplicateActivity")} ${aIdx + 1}`,
    sessionIndex >= 0 ? `${t("gridSessionPrefix")} ${sessionIndex + 1}` : ""
  ].filter(Boolean).join(" · ");
  const duplicateButton = mkBtn("", duplicateActivityLabel, () => {
    duplicateActivityAndRender(session.id, act.id);
  }, "grid-duplicate-activity-btn");
  duplicateButton.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
  btns.appendChild(duplicateButton);
  btns.appendChild(mkBtn("✕", t("deleteActivity") || "Supprimer", () => {
    session.activities.splice(aIdx, 1);
    saveState(); renderGridView(); renderTopPanel(); renderPartitionView();
  }, "del"));

  actTd.appendChild(btns);
  tr.appendChild(actTd);
  return tr;
}

function renderGridView() {
  board.innerHTML = "";

  const wrapper = document.createElement("div");
  wrapper.className = "grid-view-wrapper";

  const table = document.createElement("table");
  table.className = "grid-table";

  // Sticky header
  const thead = document.createElement("thead");
  const hRow = document.createElement("tr");
  [
    { cls: "grid-col-num",     label: "#" },
    { cls: "grid-col-type",    label: t("gridColType") },
    { cls: "grid-col-dur",     label: t("gridColDuration") },
    { cls: "grid-col-loc",     label: t("gridColLocation") },
    { cls: "grid-col-group",   label: t("gridColGroup") },
    { cls: "grid-col-sync",    label: t("gridColSync") },
    { cls: "grid-col-teaching", label: t("gridColTeaching") },
    { cls: "grid-col-eval",    label: t("gridColEval") },
    { cls: "grid-col-aias",    label: t("gridColAias") },
    { cls: "grid-col-desc",    label: t("gridColDesc") },
    { cls: "grid-col-instructions", label: t("gridColInstructions") },
    { cls: "grid-col-actions", label: "" },
  ].forEach(({ cls, label }) => {
    const th = document.createElement("th");
    th.className = cls;
    th.textContent = label;
    hRow.appendChild(th);
  });
  thead.appendChild(hRow);
  table.appendChild(thead);

  const tbody = document.createElement("tbody");

  state.sessions.forEach((session, sIdx) => {
    tbody.appendChild(buildGridSessionRow(session, sIdx));
    session.activities.forEach((act, aIdx) => {
      tbody.appendChild(buildGridActivityRow(session, act, aIdx));
    });

    // Add-activity row
    const addActRow = document.createElement("tr");
    addActRow.className = "grid-add-activity-row";
    const addActTd = document.createElement("td");
    addActTd.setAttribute("colspan", "12");
    const addActBtn = document.createElement("button");
    addActBtn.className = "grid-add-activity-btn";
    addActBtn.type = "button";
    addActBtn.textContent = t("gridAddActivity");
    addActBtn.addEventListener("click", () => {
      session.activities.push(createActivity());
      saveState(); renderGridView(); renderTopPanel(); renderPartitionView();
    });
    addActTd.appendChild(addActBtn);
    addActRow.appendChild(addActTd);
    tbody.appendChild(addActRow);
  });

  // Add-session row
  const addSessRow = document.createElement("tr");
  addSessRow.className = "grid-add-session-row";
  const addSessTd = document.createElement("td");
  addSessTd.setAttribute("colspan", "12");
  const addSessBtn = document.createElement("button");
  addSessBtn.className = "grid-add-session-btn";
  addSessBtn.type = "button";
  addSessBtn.textContent = t("gridAddSession");
  addSessBtn.setAttribute("aria-label", t("createMoment"));
  addSessBtn.addEventListener("click", addSessionAndRender);
  addSessTd.appendChild(addSessBtn);
  addSessRow.appendChild(addSessTd);
  tbody.appendChild(addSessRow);

  table.appendChild(tbody);
  wrapper.appendChild(table);
  board.appendChild(wrapper);
}

// ─── End grid view ──────────────────────────────────────────────────────────

function render() {
  restoreAllFullscreenExpandableFields();
  closeChoiceMenu();
  closeToolPicker();
  if (activeAiasActivity || !aiasModalBackdrop.classList.contains("hidden")) {
    closeAiasModal();
  }
  applyLocalizedUI();
  renderTopPanel();
  renderPartitionView();
  const boardLayout = getBoardLayout();
  board.classList.toggle("layout-list",    boardLayout === "list");
  board.classList.toggle("layout-columns", boardLayout === "columns");
  board.classList.toggle("layout-grid",    boardLayout === "grid");
  board.classList.toggle("intentions-collapsed", Boolean(state.intentionsCollapsed));
  const toggleIntentionsBtn = document.getElementById("toggle-intentions-btn");
  if (toggleIntentionsBtn) {
    const intentionsVisible = !state.intentionsCollapsed;
    setButtonLabel(
      toggleIntentionsBtn,
      intentionsVisible ? "fa-solid fa-eye" : "fa-solid fa-eye-slash",
      intentionsVisible ? t("hideIntentions") : t("showIntentions")
    );
    const intentionsLabel = intentionsVisible ? t("hideIntentions") : t("showIntentions");
    toggleIntentionsBtn.setAttribute("aria-label", intentionsLabel);
    toggleIntentionsBtn.setAttribute("title", intentionsLabel);
  }

  if (boardLayout === "grid") {
    renderGridView();
    return;
  }

  const isListLayout = boardLayout === "list";
  const sessionMoveHint = isListLayout ? t("sessionMoveHintList") : t("sessionMoveHintColumns");
  board.innerHTML = "";
  state.sessions.forEach((session, sessionIndex) => {
    const frag = sessionTpl.content.cloneNode(true);
    const card = frag.querySelector(".session-card");
    const strip = frag.querySelector(".session-strip");
    const title = frag.querySelector(".session-title");
    const objectives = frag.querySelector(".session-objectives");
    const intentions = frag.querySelector(".session-intentions");
    const activitiesWrap = frag.querySelector(".activities");
    const totalDuration = frag.querySelector(".total-duration");
    const sessionNotes = frag.querySelector(".session-notes");
    const sessionNotesInput = frag.querySelector(".session-notes-input");
    const duplicateSessionBtn = frag.querySelector(".duplicate-session-btn");
    const deleteSessionBtn = frag.querySelector(".delete-session-btn");

    card.dataset.sessionId = session.id;
    card.draggable = true;
    card.tabIndex = 0;
    card.setAttribute("role", "group");
    card.setAttribute(
      "aria-label",
      `${sessionIndex + 1}. ${session.title || defaultSessionTitle(sessionIndex + 1)}. ${sessionMoveHint}`
    );
    strip.style.background = stripGradientForSession(session);
    title.value = session.title;
    title.placeholder = t("sessionTitlePlaceholder");
    title.setAttribute("aria-label", `${t("sessionTitleLabel")} ${sessionIndex + 1}`);
    title.addEventListener("input", (e) => {
      session.title = e.target.value;
      saveState();
      schedulePartitionRender();
    });
    objectives.value = session.objectives || "";
    objectives.setAttribute("aria-label", `${t("sessionObjectivesLabel")} ${sessionIndex + 1}`);
    objectives.placeholder = t("sessionObjectivesPlaceholder");
    objectives.addEventListener("input", (e) => {
      session.objectives = e.target.value;
      saveState();
    });
    intentions.value = session.intentions || "";
    intentions.setAttribute("aria-label", `${t("sessionIntentionsLabel")} ${sessionIndex + 1}`);
    intentions.placeholder = t("sessionIntentionsPlaceholder");
    intentions.addEventListener("input", (e) => {
      session.intentions = e.target.value;
      saveState();
    });
    duplicateSessionBtn.title = t("duplicateMoment");
    duplicateSessionBtn.setAttribute("aria-label", `${t("duplicateMoment")} ${sessionIndex + 1}`);
    deleteSessionBtn.title = t("deleteSession");
    deleteSessionBtn.setAttribute("aria-label", deleteSessionBtn.title);
    card.addEventListener("dragstart", (event) => {
      if (isInteractiveTarget(event.target)) {
        event.preventDefault();
        return;
      }
      dragState = { type: "session", sessionId: session.id };
      card.classList.add("dragging");
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", session.id);
    });
    card.addEventListener("dragend", () => {
      card.classList.remove("dragging");
      dragState = null;
      clearDragIndicators();
    });
    card.addEventListener("keydown", (event) => {
      if (
        isInteractiveTarget(event.target) ||
        !event.altKey ||
        event.shiftKey ||
        event.metaKey ||
        event.ctrlKey
      ) return;
      const wantsVerticalMove = isListLayout && (event.key === "ArrowUp" || event.key === "ArrowDown");
      const wantsHorizontalMove = !isListLayout && (event.key === "ArrowLeft" || event.key === "ArrowRight");
      if (wantsVerticalMove || wantsHorizontalMove) {
        event.preventDefault();
        const moveDelta = event.key === "ArrowLeft" || event.key === "ArrowUp" ? -1 : 1;
        const moved = moveSessionByOffset(session.id, moveDelta);
        if (moved) {
          saveState();
          render();
          announce(t("moved"));
        }
      }
    });

    totalDuration.textContent = String(totalSessionMinutes(session));
    activitiesWrap.dataset.sessionId = session.id;
    activitiesWrap.setAttribute("role", "group");
    activitiesWrap.setAttribute("aria-label", `${t("sessionActivitiesLabel")} ${sessionIndex + 1}`);
    activitiesWrap.addEventListener("dragover", (event) => {
      if (!dragState || dragState.type !== "activity") return;
      event.preventDefault();
      event.stopPropagation();
      clearActivityDropIndicators();

      const targetCard = event.target.closest(".activity-card");
      if (targetCard && targetCard.closest(".activities") === activitiesWrap) {
        const rect = targetCard.getBoundingClientRect();
        const position = event.clientY < rect.top + rect.height / 2 ? "before" : "after";
        targetCard.classList.add(position === "before" ? "drop-before" : "drop-after");
        activitiesWrap.dataset.dropActivityId = targetCard.dataset.activityId;
        activitiesWrap.dataset.dropPosition = position;
        return;
      }

      activitiesWrap.classList.add("drop-append");
      activitiesWrap.dataset.dropActivityId = "";
      activitiesWrap.dataset.dropPosition = "append";
    });
    activitiesWrap.addEventListener("drop", (event) => {
      if (!dragState || dragState.type !== "activity") return;
      event.preventDefault();
      event.stopPropagation();
      const targetSessionId = session.id;
      const targetActivityId = activitiesWrap.dataset.dropActivityId || null;
      const position = activitiesWrap.dataset.dropPosition || "append";
      moveActivity(
        dragState.sessionId,
        dragState.activityId,
        targetSessionId,
        targetActivityId,
        position
      );
      saveState();
      render();
    });

    session.activities.forEach((activity, activityIndex) => {
      normalizeActivity(activity);
      const activityFrag = activityTpl.content.cloneNode(true);
      const activityCard = activityFrag.querySelector(".activity-card");
      const typeBtn = activityFrag.querySelector(".activity-type-btn");
      const durationInput = activityFrag.querySelector(".activity-duration");
      const groupModeBtn = activityFrag.querySelector(".activity-group-mode-btn");
      const teachingModeBtn = activityFrag.querySelector(".activity-teaching-mode-btn");
      const syncModeBtn = activityFrag.querySelector(".activity-sync-mode-btn");
      const locationModeBtn = activityFrag.querySelector(".activity-location-mode-btn");
      const evaluationModeBtn = activityFrag.querySelector(".activity-evaluation-mode-btn");
      const activityAiasBtn = activityFrag.querySelector(".activity-aias-btn");
      const typeLabel = activityFrag.querySelector(".activity-type-label");
      const durationLabel = activityFrag.querySelector(".activity-duration-label");
      const groupLabel = activityFrag.querySelector(".activity-group-label");
      const syncLabel = activityFrag.querySelector(".activity-sync-label");
      const locationLabel = activityFrag.querySelector(".activity-location-label");
      const evaluationLabel = activityFrag.querySelector(".activity-evaluation-label");
      const description = activityFrag.querySelector(".activity-description");
      const instructions = activityFrag.querySelector(".activity-instructions");
      const descriptionLabel = activityFrag.querySelector(".activity-description-label");
      const instructionsLabel = activityFrag.querySelector(".activity-instructions-label");
      const duplicateActivityBtn = activityFrag.querySelector(".duplicate-activity-btn");
      const deleteActivityBtn = activityFrag.querySelector(".delete-activity-btn");
      const selectToolsBtn = activityFrag.querySelector(".select-tools-btn");

      activityCard.style.borderLeftColor = colorForType(activity.type);
      activityCard.style.setProperty('--card-type-color', colorForType(activity.type));
      activityCard.dataset.activityId = activity.id;
      activityCard.dataset.sessionId = session.id;
      activityCard.draggable = true;
      activityCard.tabIndex = 0;
      activityCard.setAttribute("role", "group");
      activityCard.setAttribute("aria-label", `${t("activityLabel")} ${activityIndex + 1}. ${t("activityMoveHint")}`);
      typeBtn.dataset.groupTitle = t("groupTitleType");
      groupModeBtn.dataset.groupTitle = t("groupTitleGroup");
      teachingModeBtn.dataset.groupTitle = t("groupTitleTeaching");
      syncModeBtn.dataset.groupTitle = t("groupTitlePacing");
      locationModeBtn.dataset.groupTitle = t("groupTitleMode");
      evaluationModeBtn.dataset.groupTitle = t("groupTitleEvaluation");
      setChoiceButton(typeBtn, ACTIVITY_TYPE_OPTIONS, activity.type);
      setChoiceButton(groupModeBtn, GROUP_MODE_OPTIONS, activity.groupMode);
      setChoiceButton(teachingModeBtn, TEACHING_OPTIONS, activity.teachingMode);
      setChoiceButton(syncModeBtn, SYNC_OPTIONS, activity.syncMode);
      setChoiceButton(locationModeBtn, LOCATION_OPTIONS, activity.locationMode);
      setChoiceButton(evaluationModeBtn, EVAL_OPTIONS, activity.evaluationMode);
      updateAiasTrigger(activityAiasBtn, activity);
      if (typeLabel) typeLabel.textContent = t("groupTitleType");
      if (durationLabel) durationLabel.textContent = currentLang() === "en" ? "Duration" : "Durée";
      if (groupLabel) groupLabel.textContent = t("groupTitleGroup");
      if (syncLabel) syncLabel.textContent = t("groupTitlePacing");
      if (locationLabel) locationLabel.textContent = t("groupTitleMode");
      if (evaluationLabel) evaluationLabel.textContent = t("groupTitleEvaluation");
      durationInput.value = activity.duration;
      durationInput.setAttribute("inputmode", "numeric");
      durationInput.setAttribute("aria-label", `${t("activityDurationLabel")} ${activityIndex + 1}`);
      description.value = activity.description;
      description.placeholder = t("activityDescriptionPlaceholder");
      description.setAttribute("aria-label", `${t("activityDescriptionLabel")} ${activityIndex + 1}`);
      instructions.value = activity.instructions;
      instructions.placeholder = t("activityInstructionsPlaceholder");
      instructions.setAttribute("aria-label", `${t("activityInstructionsLabel")} ${activityIndex + 1}`);
      if (descriptionLabel) descriptionLabel.textContent = t("activityDescriptionLabel");
      if (instructionsLabel) instructionsLabel.textContent = t("activityInstructionsLabel");
      duplicateActivityBtn.title = t("duplicateActivity");
      duplicateActivityBtn.setAttribute(
        "aria-label",
        `${t("duplicateActivity")} ${activityIndex + 1}`
      );
      deleteActivityBtn.title = t("deleteActivity");
      deleteActivityBtn.setAttribute("aria-label", deleteActivityBtn.title);
      activityAiasBtn.addEventListener("click", () => openAiasModal(activityAiasBtn, activity));
      updateActivityToolsDisplay(selectToolsBtn, activity);
      activityCard.addEventListener("dragstart", (event) => {
        if (isInteractiveTarget(event.target)) {
          event.preventDefault();
          return;
        }
        event.stopPropagation();
        dragState = { type: "activity", sessionId: session.id, activityId: activity.id };
        activityCard.classList.add("dragging");
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", activity.id);
      });
      activityCard.addEventListener("dragend", (event) => {
        event.stopPropagation();
        activityCard.classList.remove("dragging");
        dragState = null;
        clearDragIndicators();
      });
      activityCard.addEventListener("keydown", (event) => {
        if (
          isInteractiveTarget(event.target) ||
          !event.altKey ||
          event.shiftKey ||
          event.metaKey ||
          event.ctrlKey
        ) return;
        if (event.key === "ArrowUp" || event.key === "ArrowDown") {
          event.preventDefault();
          const moved = moveActivityByOffset(session.id, activity.id, event.key === "ArrowUp" ? -1 : 1);
          if (moved) {
            saveState();
            render();
            announce(t("moved"));
          }
        }
      });

      const bindChoiceControl = (button, options, getValue, applyValue) => {
        const openMenu = () => {
          openChoiceMenu(button, options, getValue(), (nextValue) => {
            applyValue(nextValue);
          });
        };
        button.addEventListener("click", openMenu);
        button.addEventListener("keydown", (event) => {
          if (event.key === "Enter" || event.key === " " || event.key === "ArrowDown") {
            event.preventDefault();
            openMenu();
          }
          if (event.key === "Escape") {
            closeChoiceMenu(true);
          }
        });
      };

      bindChoiceControl(typeBtn, ACTIVITY_TYPE_OPTIONS, () => activity.type, (nextValue) => {
          activity.type = nextValue;
          saveState();
          render();
      });

      durationInput.addEventListener("input", (e) => {
        activity.duration = Math.max(1, Number(e.target.value) || 1);
        saveState();
        totalDuration.textContent = String(totalSessionMinutes(session));
        scheduleTopPanelRender();
        schedulePartitionRender();
      });

      bindChoiceControl(groupModeBtn, GROUP_MODE_OPTIONS, () => activity.groupMode, (nextValue) => {
          activity.groupMode = nextValue;
          saveState();
          renderTopPanel();
          setChoiceButton(groupModeBtn, GROUP_MODE_OPTIONS, activity.groupMode);
          renderPartitionView();
      });

      bindChoiceControl(teachingModeBtn, TEACHING_OPTIONS, () => activity.teachingMode, (nextValue) => {
          activity.teachingMode = nextValue;
          saveState();
          renderTopPanel();
          setChoiceButton(teachingModeBtn, TEACHING_OPTIONS, activity.teachingMode);
          renderPartitionView();
      });

      bindChoiceControl(syncModeBtn, SYNC_OPTIONS, () => activity.syncMode, (nextValue) => {
          activity.syncMode = nextValue;
          saveState();
          renderTopPanel();
          setChoiceButton(syncModeBtn, SYNC_OPTIONS, activity.syncMode);
          renderPartitionView();
      });

      bindChoiceControl(
        locationModeBtn,
        LOCATION_OPTIONS,
        () => activity.locationMode,
        (nextValue) => {
          activity.locationMode = nextValue;
          saveState();
          renderTopPanel();
          setChoiceButton(locationModeBtn, LOCATION_OPTIONS, activity.locationMode);
          renderPartitionView();
        }
      );

      bindChoiceControl(
        evaluationModeBtn,
        EVAL_OPTIONS,
        () => activity.evaluationMode,
        (nextValue) => {
          activity.evaluationMode = nextValue;
          saveState();
          renderTopPanel();
          setChoiceButton(evaluationModeBtn, EVAL_OPTIONS, activity.evaluationMode);
        }
      );

      description.addEventListener("input", (e) => {
        activity.description = e.target.value;
        saveState();
      });

      instructions.addEventListener("input", (event) => {
        activity.instructions = event.target.value;
        saveState();
      });

      duplicateActivityBtn.addEventListener("click", () => {
        duplicateActivityAndRender(session.id, activity.id);
      });

      deleteActivityBtn.addEventListener("click", () => {
        session.activities = session.activities.filter((a) => a.id !== activity.id);
        saveState();
        render();
        announce(t("activityDeleted"));
      });

      selectToolsBtn.addEventListener("click", () => openToolPicker(selectToolsBtn, activity));
      selectToolsBtn.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " " || e.key === "ArrowDown") {
          e.preventDefault();
          openToolPicker(selectToolsBtn, activity);
        }
        if (e.key === "Escape") closeToolPicker(true);
      });
      activitiesWrap.appendChild(activityFrag);
    });

    const addActivityBtn = frag.querySelector(".add-activity-btn");
    const toggleSessionNotesBtn = frag.querySelector(".toggle-session-notes-btn");
    setButtonLabel(addActivityBtn, "fa-solid fa-plus", t("addLearningType").replace(/^\+\s*/, ""));
    setSessionNotesButtonLabel(toggleSessionNotesBtn, session.notesExpanded);
    toggleSessionNotesBtn.setAttribute("aria-expanded", String(Boolean(session.notesExpanded)));
    addActivityBtn.addEventListener("click", () => {
      session.activities.push(createActivity());
      saveState();
      render();
      announce(t("activityAdded"));
    });

    duplicateSessionBtn.addEventListener("click", () => {
      duplicateSessionAndRender(session.id);
    });

    deleteSessionBtn.addEventListener("click", () => {
      state.sessions = state.sessions.filter((s) => s.id !== session.id);
      saveState();
      render();
      announce(t("sessionDeleted"));
    });

    toggleSessionNotesBtn.addEventListener("click", () => {
      session.notesExpanded = !session.notesExpanded;
      saveState();
      const isVisible = session.notesExpanded;
      sessionNotes.classList.toggle("hidden", !isVisible);
      setSessionNotesButtonLabel(toggleSessionNotesBtn, isVisible);
      toggleSessionNotesBtn.setAttribute("aria-expanded", String(Boolean(isVisible)));
      if (isVisible) {
        autoResizeTextarea(sessionNotesInput);
        requestAnimationFrame(() => {
          sessionNotesInput.focus();
          sessionNotesInput.scrollIntoView({ block: "nearest", behavior: "smooth" });
        });
      }
    });

    sessionNotesInput.value = session.notes || "";
    sessionNotesInput.setAttribute("aria-label", `${t("sessionNotesLabel")} ${sessionIndex + 1}`);
    sessionNotesInput.placeholder = t("sessionNotesPlaceholder");
    sessionNotesInput.addEventListener("input", (e) => {
      session.notes = e.target.value;
      saveState();
    });
    sessionNotes.classList.toggle("hidden", !session.notesExpanded);

    board.appendChild(frag);
  });

  board.appendChild(createAddSessionCard());

  ensureMarkdownToolbars(board);
  ensureMarkdownPreviews(board);
  localizeExpandableFieldControls(board);
  initAutoResizeTextareas();
}

function bindTopPanelEvents() {
  const tabButtons = [topTabSettings, topTabAnalysis];
  tabButtons.forEach((tab, index) => {
    tab.addEventListener("keydown", (event) => {
      if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
      event.preventDefault();
      let nextIndex = index;
      if (event.key === "ArrowLeft") nextIndex = (index - 1 + tabButtons.length) % tabButtons.length;
      if (event.key === "ArrowRight") nextIndex = (index + 1) % tabButtons.length;
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = tabButtons.length - 1;
      tabButtons[nextIndex].focus();
      tabButtons[nextIndex].click();
    });
  });

  topPanelToggleBtn.addEventListener("click", () => {
    state.topPanelCollapsed = !state.topPanelCollapsed;
    saveState();
    renderTopPanel();
  });

  topTabSettings.addEventListener("click", () => {
    if (!state.topPanelCollapsed && state.meta.activeTab === "settings") {
      state.topPanelCollapsed = true;
    } else {
      state.meta.activeTab = "settings";
      state.topPanelCollapsed = false;
    }
    saveState();
    renderTopPanel();
  });

  topTabAnalysis.addEventListener("click", () => {
    if (!state.topPanelCollapsed && state.meta.activeTab === "analysis") {
      state.topPanelCollapsed = true;
    } else {
      state.meta.activeTab = "analysis";
      state.topPanelCollapsed = false;
    }
    saveState();
    renderTopPanel();
  });

  topTabChronology.addEventListener("click", () => {
    if (!state.topPanelCollapsed && state.meta.activeTab === "chronology") {
      state.topPanelCollapsed = true;
    } else {
      state.meta.activeTab = "chronology";
      state.topPanelCollapsed = false;
    }
    saveState();
    renderTopPanel();
    renderPartitionView();
  });

  metaNameInput.addEventListener("input", (event) => {
    state.meta.name = event.target.value;
    saveState();
  });
  const debouncedRenderTopPanel = debounce(renderTopPanel, 300);
  metaLearningDaysInput.addEventListener("input", (event) => {
    setLearningTime(event.target.value, state.meta.learningHours, state.meta.learningMinutes);
    saveState();
    debouncedRenderTopPanel();
  });
  metaLearningHoursInput.addEventListener("input", (event) => {
    setLearningTime(state.meta.learningDays, event.target.value, state.meta.learningMinutes);
    saveState();
    debouncedRenderTopPanel();
  });
  metaLearningMinutesInput.addEventListener("input", (event) => {
    setLearningTime(state.meta.learningDays, state.meta.learningHours, event.target.value);
    saveState();
    debouncedRenderTopPanel();
  });
  metaDeliverySelect.addEventListener("change", (event) => {
    state.meta.modeDelivery = event.target.value;
    saveState();
  });
  metaSchoolSystemSelect.addEventListener("change", (event) => {
    state.meta.schoolSystem = event.target.value;
    state.meta.schoolLevel = "";
    renderSchoolLevelOptions();
    saveState();
  });
  metaLevelSelect.addEventListener("change", (event) => {
    state.meta.schoolLevel = event.target.value;
    saveState();
  });
  metaDayHoursInput.addEventListener("input", (event) => {
    state.meta.dayHours = Math.max(1, Number(event.target.value) || DEFAULT_DAY_HOURS);
    setLearningTime(state.meta.learningDays, state.meta.learningHours, state.meta.learningMinutes);
    saveState();
    renderTopPanel();
  });
  metaSizeClassInput.addEventListener("input", (event) => {
    const rawValue = String(event.target.value ?? "").trim();
    state.meta.sizeClass = rawValue === "" ? "" : Math.max(1, Number(rawValue) || 1);
    saveState();
  });
  metaDesignersInput.addEventListener("input", (event) => {
    state.meta.designers = event.target.value;
    saveState();
  });
  metaTrainersInput.addEventListener("input", (event) => {
    state.meta.trainers = event.target.value;
    saveState();
  });
  metaDescriptionInput.addEventListener("input", (event) => {
    state.meta.description = event.target.value;
    saveState();
  });
  metaCommandInput.addEventListener("input", (event) => {
    state.meta.command = event.target.value;
    saveState();
  });
  metaPersonasInput.addEventListener("input", (event) => {
    state.meta.personas = event.target.value;
    saveState();
  });
  addOutcomeBtn.addEventListener("click", () => openBloomModal("add"));
  bloomCancelBtn.addEventListener("click", () => closeModal(bloomModalBackdrop));
  bloomAddBtn.addEventListener("click", confirmBloom);
  bloomModalBackdrop.addEventListener("click", (e) => {
    if (e.target === bloomModalBackdrop) closeModal(bloomModalBackdrop);
  });
  aiasModalCloseBtn.addEventListener("click", closeAiasModal);
  aiasModalBackdrop.addEventListener("click", (event) => {
    if (event.target === aiasModalBackdrop) closeAiasModal();
  });
  langSelect.addEventListener("change", (event) => {
    state.meta.uiLanguage = event.target.value === "en" ? "en" : "fr";
    document.documentElement.lang = state.meta.uiLanguage;
    try {
      localStorage.setItem(LD_LANGUAGE_STORAGE_KEY, state.meta.uiLanguage);
    } catch (_) {}
    saveState();
    render();
  });
  if (languageButton) {
    languageButton.addEventListener("click", () => {
      const nextLang = langSelect.value === "en" ? "fr" : "en";
      const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches;
      const applyLanguage = () => {
        langSelect.value = nextLang;
        langSelect.dispatchEvent(new Event("change", { bubbles: true }));
      };
      if (reduceMotion) {
        applyLanguage();
        return;
      }
      languageButton.classList.add("is-leaving");
      window.setTimeout(() => {
        applyLanguage();
        languageButton.classList.remove("is-leaving");
        languageButton.classList.add("is-entering");
        window.setTimeout(() => languageButton.classList.remove("is-entering"), 180);
      }, 90);
    });
  }
}

document.getElementById("toggle-intentions-btn")?.addEventListener("click", () => {
  state.intentionsCollapsed = !state.intentionsCollapsed;
  saveState();
  render();
});

boardLayoutListBtn.addEventListener("click", () => {
  setBoardLayout("list");
});

boardLayoutColumnsBtn.addEventListener("click", () => {
  setBoardLayout("columns");
});

boardLayoutGridBtn.addEventListener("click", () => {
  setBoardLayout("grid");
});

(function () {
  const layoutBtns = [boardLayoutListBtn, boardLayoutColumnsBtn, boardLayoutGridBtn];
  const layouts    = ["list", "columns", "grid"];
  layoutBtns.forEach((btn, i) => {
    btn.addEventListener("keydown", (event) => {
      if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
      event.preventDefault();
      let idx;
      if (event.key === "Home")           idx = 0;
      else if (event.key === "End")       idx = layoutBtns.length - 1;
      else if (event.key === "ArrowLeft") idx = (i - 1 + layoutBtns.length) % layoutBtns.length;
      else                                idx = (i + 1) % layoutBtns.length;
      layoutBtns[idx].focus();
      setBoardLayout(layouts[idx]);
    });
  });
})();

function openNewDesignModal() {
  openModal(newDesignModalBackdrop, "#new-design-cancel-btn");
}

newDesignBtn.addEventListener("click", openNewDesignModal);
navNewDesignBtn?.addEventListener("click", openNewDesignModal);
newDesignCancelBtn.addEventListener("click", () => closeModal(newDesignModalBackdrop));
newDesignConfirmBtn.addEventListener("click", () => {
  closeModal(newDesignModalBackdrop);
  state = createNewDesignState();
  documentGeneration++;
  window.learningDesignerClearRemoteDesignUrl?.();
  saveState();
  render();
  announce(t("moved"));
});
newDesignModalBackdrop.addEventListener("click", (e) => {
  if (e.target === newDesignModalBackdrop) closeModal(newDesignModalBackdrop);
});

function getExportPayload(format = "json", scope = exportScope, sessionIds = exportSessionIds) {
  const chosen = String(format).toLowerCase();
  const normalizedScope = normalizeExportScope(scope);
  const filenamePrefix = normalizedScope === "students" ? "consignes-eleves" : "design";
  if (chosen === "excel" || chosen === "xls" || chosen === "xlsx") {
    return {
      content: buildExcelExportDocument(normalizedScope, sessionIds),
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      filename: `${filenamePrefix}-learning-designer-fr.xlsx`
    };
  }
  if (chosen === "md" || chosen === "markdown") {
    return {
      content: buildMarkdownExport(normalizedScope, sessionIds),
      type: "text/markdown;charset=utf-8",
      filename: `${filenamePrefix}-learning-designer-fr.md`
    };
  }
  if (chosen === "html") {
    return {
      content: buildHtmlExportDocument(normalizedScope, sessionIds),
      type: "text/html;charset=utf-8",
      filename: `${filenamePrefix}-learning-designer-fr.html`
    };
  }
  if (chosen === "word" || chosen === "doc" || chosen === "docx") {
    return {
      content: buildWordExportDocument(normalizedScope, sessionIds),
      type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      filename: `${filenamePrefix}-learning-designer-fr.docx`
    };
  }
  return {
    content: JSON.stringify(
      normalizedScope === "students"
        ? buildStudentInstructionsData(sessionIds)
        : {
            ...state,
            sessions: getExportSessionEntries(sessionIds).map(({ session }) => session)
          },
      null,
      2
    ),
    type: "application/json;charset=utf-8",
    filename: `${filenamePrefix}-learning-designer-fr.json`
  };
}

function getFilenameExtension(filename = "") {
  const match = String(filename).match(/(\.[^.]+)$/);
  return match ? match[1] : "";
}

function getDefaultExportName(format = "json", scope = exportScope) {
  const normalizedScope = normalizeExportScope(scope);
  const payload = getExportPayload(format, normalizedScope);
  const extension = getFilenameExtension(payload.filename);
  const title = String(state?.meta?.name || "").trim();
  if (title) return normalizedScope === "students" ? `${title} - consignes élèves` : title;
  return String(payload.filename || "").slice(0, -extension.length) || "export";
}

function sanitizeExportFilename(rawName, defaultFilename) {
  const extension = getFilenameExtension(defaultFilename) || ".txt";
  const fallbackBase = String(defaultFilename || `export${extension}`).slice(0, -extension.length) || "export";
  const cleaned = String(rawName || fallbackBase)
    .replace(/[\\/:*?"<>|]+/g, "-")
    .replace(/\s+/g, " ")
    .replace(/^\.+|\.+$/g, "")
    .trim();
  const baseName = (cleaned || fallbackBase).replace(/\.(json|md|markdown|html|doc|docx|xls|xlsx)$/i, "") || fallbackBase;
  if (baseName.toLowerCase().endsWith(extension.toLowerCase())) {
    return baseName;
  }
  return `${baseName}${extension}`;
}

function getExportFilename(format = "json", scope = exportScope) {
  const defaultFilename = getExportPayload(format, scope).filename;
  return sanitizeExportFilename(exportFilenameInput?.value, defaultFilename);
}

window.learningDesignerGetExportPayload = getExportPayload;

async function exportDesign(format = "json", scope = exportScope) {
  const { content, type, filename } = updateExportPreview(format, scope);
  try {
    await downloadBlob(content, type, filename);
  } catch (error) {
    console.error("Export failed", error);
    showNotice(currentLang() === "en" ? "Download blocked by browser. Use the export window." : "Téléchargement bloqué par le navigateur. Utilisez la fenêtre d’export.", "warning");
  }
}

window.learningDesignerOpenExport = () => {
  openExportModal();
};

window.learningDesignerRunExport = async () => {
  await exportDesign(exportFormatSelect?.value || "json", exportScope);
};

exportDesignBtn.addEventListener("click", () => {
  openExportModal();
});

[exportScopeFullInput, exportScopeStudentsInput].forEach((input) => {
  input?.addEventListener("change", () => {
    if (!input.checked) return;
    exportScope = normalizeExportScope(input.value);
    if (exportFilenameInput) {
      exportFilenameInput.value = getDefaultExportName(exportFormatSelect?.value || "markdown", exportScope);
    }
    updateExportPreview(exportFormatSelect?.value || "markdown", exportScope);
  });
});

exportMomentsAllInput?.addEventListener("change", () => {
  exportSessionIds = exportMomentsAllInput.checked
    ? new Set(state.sessions.map(exportSessionKey))
    : new Set();
  exportMomentsList?.querySelectorAll("input[type='checkbox']").forEach((input) => {
    input.checked = exportSessionIds.has(input.dataset.exportSessionId || "");
  });
  syncExportMomentsSelection();
  updateExportPreview(exportFormatSelect?.value || "markdown", exportScope);
});

infoBtn.addEventListener("click", () => {
  openInfoModal();
});

infoModalCloseBtn.addEventListener("click", () => {
  closeInfoModal();
});

infoModalBackdrop.addEventListener("click", (event) => {
  if (event.target === infoModalBackdrop) {
    closeInfoModal();
  }
});

exportModalCancelBtn.addEventListener("click", () => {
  closeExportModal();
});

exportModalConfirmBtn.addEventListener("click", async () => {
  await window.learningDesignerRunExport();
});

exportFormatSelect?.addEventListener("change", () => {
  if (exportFilenameInput) {
    exportFilenameInput.value = getDefaultExportName(exportFormatSelect.value, exportScope);
  }
  updateExportPreview(exportFormatSelect.value, exportScope);
});

exportFilenameInput?.addEventListener("input", () => {
  updateExportPreview(exportFormatSelect?.value || "json", exportScope);
});

exportModalBackdrop.addEventListener("click", (event) => {
  if (event.target === exportModalBackdrop) {
    closeExportModal();
  }
});

exportResultCopyBtn?.addEventListener("click", async () => {
  if (!isCopyableExportFormat(exportFormatSelect?.value || "json")) return;
  try {
    await navigator.clipboard.writeText(exportResultText.value);
    showNotice(currentLang() === "en" ? "Export copied." : "Export copié.", "success");
  } catch {
    exportResultText.focus();
    exportResultText.select();
  }
});

importDesignBtn.addEventListener("click", () => {
  openImportModal();
});

importModalCancelBtn?.addEventListener("click", () => {
  closeImportModal();
});

importFileBtn?.addEventListener("click", () => {
  openImportPicker();
});

let importDropDepth = 0;

function isFileDrag(event) {
  const dataTransfer = event.dataTransfer;
  return Boolean(dataTransfer?.files?.length)
    || Array.from(dataTransfer?.types || []).includes("Files");
}

function setImportDropActive(active) {
  if (!importDropZone) return;
  importDropZone.classList.toggle("is-dragover", active);
  if (importDropTitle) {
    importDropTitle.textContent = t(active ? "importDropActive" : "importDropTitle");
  }
  if (!active) importDropDepth = 0;
}

importDropZone?.addEventListener("dragenter", (event) => {
  if (!isFileDrag(event)) return;
  event.preventDefault();
  importDropDepth += 1;
  setImportDropActive(true);
});

importDropZone?.addEventListener("dragover", (event) => {
  if (!isFileDrag(event)) return;
  event.preventDefault();
  event.dataTransfer.dropEffect = "copy";
});

importDropZone?.addEventListener("dragleave", (event) => {
  if (!isFileDrag(event)) return;
  event.preventDefault();
  importDropDepth = Math.max(0, importDropDepth - 1);
  if (importDropDepth === 0) setImportDropActive(false);
});

importDropZone?.addEventListener("drop", async (event) => {
  if (!isFileDrag(event)) return;
  event.preventDefault();
  setImportDropActive(false);
  const file = event.dataTransfer?.files?.[0];
  if (file) await importScenarioFile(file);
});

importModalBackdrop?.addEventListener("dragover", (event) => {
  if (isFileDrag(event)) event.preventDefault();
});

importModalBackdrop?.addEventListener("drop", (event) => {
  if (!isFileDrag(event)) return;
  event.preventDefault();
  setImportDropActive(false);
});

importModalBackdrop?.addEventListener("click", (event) => {
  if (event.target === importModalBackdrop) closeImportModal();
});

importModelsSearch?.addEventListener("input", () => {
  modelFilterQuery = importModelsSearch.value.trim().toLowerCase();
  renderModelList();
});

importModelsFamily?.addEventListener("change", () => {
  modelFilterFamily = importModelsFamily.value;
  renderModelList();
});

importModelsList?.addEventListener("click", async (event) => {
  const card = event.target.closest(".import-model-card");
  const action = event.target.closest("[data-model-action]");
  if (!card || !action) return;
  if (action.dataset.modelAction === "preview") {
    openModelPreview(card.dataset.modelId, action);
    return;
  }
  const applied = await applyModel(card.dataset.modelId);
  if (applied) closeImportModal();
});

importModelPreviewBackBtn?.addEventListener("click", () => {
  closeModelPreview();
});

importModelPreviewUseBtn?.addEventListener("click", async () => {
  const applied = await applyModel(activeModelPreviewId);
  if (applied) closeImportModal();
});

let xlsxLibraryPromise = null;
function loadXlsxLibrary() {
  if (window.XLSX) return Promise.resolve(window.XLSX);
  if (xlsxLibraryPromise) return xlsxLibraryPromise;

  xlsxLibraryPromise = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js";
    script.async = true;
    script.onload = () => {
      if (window.XLSX) {
        resolve(window.XLSX);
        return;
      }
      xlsxLibraryPromise = null;
      reject(new Error("XLSX unavailable"));
    };
    script.onerror = () => {
      xlsxLibraryPromise = null;
      script.remove();
      reject(new Error("XLSX loading failed"));
    };
    document.head.appendChild(script);
  });

  return xlsxLibraryPromise;
}

async function importScenarioFile(file, forcedFormat = "") {
  const MAX_IMPORT_SIZE = 5 * 1024 * 1024; // 5 MB
  if (file.size > MAX_IMPORT_SIZE) {
    alert(t("importInvalid"));
    return false;
  }
  const normalizedForcedFormat = String(forcedFormat || "").toLowerCase();
  const filename = String(file.name || "").toLowerCase();
  const selectedFormat =
    normalizedForcedFormat === "xlsx" || filename.endsWith(".xlsx") ? "xlsx"
    : normalizedForcedFormat === "csv" || filename.endsWith(".csv") ? "csv"
    : normalizedForcedFormat === "markdown" || normalizedForcedFormat === "md" || filename.endsWith(".md") || filename.endsWith(".markdown") ? "markdown"
    : normalizedForcedFormat === "ldj" || filename.endsWith(".ldj") ? "ldj"
    : "json";
  try {
    let hydrated = null;
    if (selectedFormat === "xlsx") {
      const xlsx = await loadXlsxLibrary();
      const buffer = await file.arrayBuffer();
      const workbook = xlsx.read(buffer, { type: "array" });
      const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
      const csvText = xlsx.utils.sheet_to_csv(firstSheet);
      hydrated = buildStateFromCsv(csvText);
    } else if (selectedFormat === "csv") {
      const text = await file.text();
      hydrated = buildStateFromCsv(text);
    } else if (selectedFormat === "markdown") {
      const text = await file.text();
      hydrated = buildStateFromMarkdown(text);
    } else {
      const text = await file.text();
      const parsed = JSON.parse(text);
      hydrated = isLegacyLdjDocument(parsed)
        ? buildStateFromLegacyLdj(parsed)
        : hydrateState(parsed, null);
    }
    if (!hydrated) {
      throw new Error("Format invalide");
    }
    hydrated.meta.uiLanguage = preferredInterfaceLanguage(currentLang());
    state = hydrated;
    documentGeneration++;
    saveState();
    render();
    if (activeModalBackdrop === importModalBackdrop) closeImportModal();
    announce(t("import"));
    return true;
  } catch {
    alert(t("importInvalid"));
    return false;
  }
}

importFileInput.addEventListener("change", async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  const forcedFormat = String(importFileInput.dataset.format || "").toLowerCase();
  try {
    await importScenarioFile(file, forcedFormat);
  } finally {
    importFileInput.value = "";
    importFileInput.accept = ".json,.ldj,.csv,.xlsx,.md,.markdown,application/json,text/csv,text/markdown";
    delete importFileInput.dataset.format;
  }
});

// ── Partition config modal ───────────────────────────────────────────────────

function openPartitionConfigModal() {
  partitionConfigDraft = state.partitionLineConfig.map(line => ({ ...line }));
  renderPartitionConfigList();
  renderPartitionAddTypeSelect();
  openModal(partitionConfigModalBackdrop, "#partition-config-cancel-btn");
}

function closePartitionConfigModal() {
  closeModal(partitionConfigModalBackdrop);
}

function renderPartitionConfigList() {
  const list = document.getElementById("partition-config-list");
  if (!list) return;
  list.innerHTML = "";

  partitionConfigDraft.forEach((line, index) => {
    const row = document.createElement("div");
    row.className = "partition-config-row";

    const upBtn = document.createElement("button");
    upBtn.type = "button";
    upBtn.textContent = "▲";
    upBtn.title = t("partitionMoveUp");
    upBtn.disabled = index === 0;
    upBtn.addEventListener("click", () => {
      [partitionConfigDraft[index - 1], partitionConfigDraft[index]] =
        [partitionConfigDraft[index], partitionConfigDraft[index - 1]];
      renderPartitionConfigList();
    });

    const downBtn = document.createElement("button");
    downBtn.type = "button";
    downBtn.textContent = "▼";
    downBtn.title = t("partitionMoveDown");
    downBtn.disabled = index === partitionConfigDraft.length - 1;
    downBtn.addEventListener("click", () => {
      [partitionConfigDraft[index], partitionConfigDraft[index + 1]] =
        [partitionConfigDraft[index + 1], partitionConfigDraft[index]];
      renderPartitionConfigList();
    });

    const label = document.createElement("span");
    label.className = "partition-config-row-label";
    label.textContent = line.label;

    const typeBadge = document.createElement("span");
    typeBadge.className = "partition-config-row-type";
    const typeOption = PARTITION_TYPE_OPTIONS.find(t => t.type === line.type);
    typeBadge.textContent = typeOption ? t(typeOption.labelKey) : line.type;

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.checked = line.visible;
    checkbox.title = t("partitionShowHide");
    checkbox.setAttribute("aria-label", `${t("partitionShowPrefix")} ${line.label}`);
    checkbox.addEventListener("change", () => {
      partitionConfigDraft[index].visible = checkbox.checked;
    });

    const delBtn = document.createElement("button");
    delBtn.type = "button";
    delBtn.textContent = "✕";
    delBtn.className = "del";
    delBtn.title = t("partitionDeleteLine");
    delBtn.addEventListener("click", () => {
      partitionConfigDraft.splice(index, 1);
      renderPartitionConfigList();
    });

    const btnGroup = document.createElement("div");
    btnGroup.className = "partition-config-row-btns";
    btnGroup.append(upBtn, downBtn, checkbox, delBtn);

    row.append(label, typeBadge, btnGroup);
    list.appendChild(row);
  });
}

function renderPartitionAddTypeSelect() {
  const typeSelect = document.getElementById("partition-add-type");
  const valueSelect = document.getElementById("partition-add-value");
  if (!typeSelect || !valueSelect) return;

  typeSelect.innerHTML = PARTITION_TYPE_OPTIONS
    .map(opt => `<option value="${opt.type}">${t(opt.labelKey)}</option>`)
    .join("");

  updatePartitionAddValueSelect();

  // Remove previous listener to avoid stacking
  typeSelect.replaceWith(typeSelect.cloneNode(true));
  const freshTypeSelect = document.getElementById("partition-add-type");
  freshTypeSelect.addEventListener("change", updatePartitionAddValueSelect);
}

function updatePartitionAddValueSelect() {
  const typeSelect = document.getElementById("partition-add-type");
  const valueSelect = document.getElementById("partition-add-value");
  if (!typeSelect || !valueSelect) return;
  const selectedType = PARTITION_TYPE_OPTIONS.find(t => t.type === typeSelect.value);
  valueSelect.innerHTML = (selectedType ? selectedType.options : [])
    .map(opt => `<option value="${opt.value}">${opt.label}</option>`)
    .join("");
}

function addPartitionLine() {
  const typeSelect = document.getElementById("partition-add-type");
  const valueSelect = document.getElementById("partition-add-value");
  if (!typeSelect || !valueSelect) return;
  const selectedType = PARTITION_TYPE_OPTIONS.find(t => t.type === typeSelect.value);
  const selectedOption = (selectedType ? selectedType.options : []).find(opt => opt.value === valueSelect.value);
  if (!selectedOption) return;
  const exists = partitionConfigDraft.some(l => l.type === typeSelect.value && l.value === valueSelect.value);
  if (exists) return;
  partitionConfigDraft.push({
    type: typeSelect.value,
    label: selectedOption.label,
    value: selectedOption.value,
    visible: true
  });
  renderPartitionConfigList();
}

function savePartitionConfig() {
  state.partitionLineConfig = partitionConfigDraft.map(line => ({ ...line }));
  saveState();
  renderPartitionView();
  closePartitionConfigModal();
}

document.getElementById("partition-config-cancel-btn")?.addEventListener("click", closePartitionConfigModal);
document.getElementById("partition-config-save-btn")?.addEventListener("click", savePartitionConfig);
document.getElementById("partition-add-line-btn")?.addEventListener("click", addPartitionLine);
partitionConfigModalBackdrop?.addEventListener("click", (e) => {
  if (e.target === partitionConfigModalBackdrop) closePartitionConfigModal();
});

// ─────────────────────────────────────────────────────────────────────────────

setupExpandableFields();
setupFormAccessibility();
bindTopPanelEvents();
window.learningDesignerApp = {
  getDocumentGeneration() {
    return documentGeneration;
  },
  getState() {
    const snapshot = JSON.parse(JSON.stringify(state));
    snapshot.meta = snapshot.meta && typeof snapshot.meta === "object" ? snapshot.meta : {};
    snapshot.meta.designedMinutes = totalDesignedMinutes();
    return snapshot;
  },
  getCurrentLang() {
    return currentLang();
  },
  t,
  announce,
  showNotice,
  initializeStorageScope,
  saveLocal() {
    saveState();
  },
  updateMeta(patch, options) {
    if (!patch || typeof patch !== "object") return;
    Object.assign(state.meta, patch);
    saveState(options);
  },
  clearRemoteMeta() {
    delete state.meta.remoteDesignId;
    delete state.meta.remoteUpdatedAt;
    delete state.meta.remoteRevision;
    saveState();
  },
  loadDocument(documentState, remoteMeta = {}) {
    const interfaceLanguage = preferredInterfaceLanguage(currentLang());
    state = hydrateState(documentState, defaultState());
    documentGeneration++;
    Object.assign(state.meta, remoteMeta);
    state.meta.uiLanguage = interfaceLanguage;
    saveState({ markDirty: false });
    render();
  }
};
// ── Tooltip personnalisé ─────────────────────────────────────
(function initTooltip() {
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

  function formatTipText(text) {
    return applyLanguageTypography(text, currentLang());
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
    activeTarget = target;
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
    activeTarget = null;
    tip.classList.remove("tip-visible", "tip-above", "tip-below");
    tip.setAttribute("aria-hidden", "true");
  }

  document.addEventListener("mouseover", (e) => {
    const target = nearestTip(e.target);
    if (!target || target === activeTarget) return;
    clearTimeout(timer);
    timer = setTimeout(() => show(target), 480);
  });

  document.addEventListener("mouseout", (e) => {
    if (!nearestTip(e.target)) return;
    clearTimeout(timer);
    hide();
  });

  document.addEventListener("click", hide, true);
  document.addEventListener("keydown", hide, true);
  document.addEventListener("scroll", () => {
    if (activeTarget) place(activeTarget);
  }, { passive: true, capture: true });
})();

// ── Effet ripple au clic ──────────────────────────────────────
(function initRipple() {
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(
      ".btn:not(.btn-primary), .icon-btn, .layout-toggle-btn"
    );
    if (!btn) return;

    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2.2;
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;

    const ripple = document.createElement("span");
    ripple.className = "btn-ripple";
    ripple.style.cssText = `width:${size}px;height:${size}px;left:${x}px;top:${y}px`;
    btn.appendChild(ripple);
    ripple.addEventListener("animationend", () => ripple.remove(), { once: true });
  });
})();

render();
})();
