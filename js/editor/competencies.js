// Construction des référentiels et présentation des compétences.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createCompetencies = ({
  COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
  COMPETENCY_FRAMEWORK_CATALOG_SOURCE, COMPETENCY_GREENCOMP_DETAIL_SOURCE,
  COMPETENCY_DIGCOMP_DETAIL_SOURCE, normalizeToken, currentLang
}) => {
function normalizeCatalogSlug(value) {
  return String(value ?? "")
    .trim()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "") || "general";
}

// COMPETENCY_CATALOG_SOURCE est défini dans js/competency-catalog.js, chargé
// juste avant ce script par designer.php.

function toRomanNumeral(value) {
  const number = Number(value);
  if (!Number.isFinite(number) || number <= 0) return "";
  const numerals = [
    ["M", 1000],
    ["CM", 900],
    ["D", 500],
    ["CD", 400],
    ["C", 100],
    ["XC", 90],
    ["L", 50],
    ["XL", 40],
    ["X", 10],
    ["IX", 9],
    ["V", 5],
    ["IV", 4],
    ["I", 1]
  ];
  let remaining = Math.floor(number);
  let result = "";
  numerals.forEach(([symbol, amount]) => {
    while (remaining >= amount) {
      result += symbol;
      remaining -= amount;
    }
  });
  return result;
}

function mergeCompetencyCatalogTranslations(source, translations) {
  const byId = {};
  let levelId = "";
  String(translations ?? "").split("\n").forEach((rawLine) => {
    const line = String(rawLine ?? "").replace(/\r/g, "");
    if (!line.trim()) return;
    if (line.startsWith("# ")) {
      levelId = line.slice(2).trim();
      return;
    }
    const [number = "", labelEn = "", descEn = ""] = line.split("\t");
    if (levelId && number.trim()) byId[`${levelId}:${number.trim()}`] = [labelEn, descEn];
  });

  let sourceLevelId = "";
  return String(source ?? "").split("\n").map((rawLine) => {
    const line = String(rawLine ?? "").replace(/\r/g, "");
    if (line.startsWith("# ")) {
      sourceLevelId = (line.slice(2).split("\t")[0] || "").trim();
      return line;
    }
    if (!line.trim()) return line;
    const number = (line.split("\t")[2] || "").trim();
    const [labelEn = "", descEn = ""] = byId[`${sourceLevelId}:${number}`] || [];
    return `${line}\t${labelEn}\t${descEn}`;
  }).join("\n");
}

function parseCompetencyCatalog(source) {
  const data = [];
  const categories = {};
  const tabs = [];
  const badgeByLevel = { acquerir: "N1", approfondir: "N2", creer: "N3" };
  const legacyCodeByLevel = { acquerir: "A", approfondir: "P", creer: "C" };
  const sectionEnByFr = {
    "Utilisation de l'iPad": "Using the iPad",
    "Productivité et organisation": "Productivity and organisation",
    "Communication et collaboration": "Communication and collaboration",
    "Données et programmation": "Data and programming",
    "Créativité et expression": "Creativity and expression",
    "Général": "General"
  };
  const appEnByFr = {
    "Partager": "Sharing",
    "Écrire des emails": "Writing emails",
    "Excel & calcul": "Excel & calculations",
    "Programmation": "Programming"
  };
  let currentLevel = null;
  let currentLevelSections = null;

  String(source ?? "").split("\n").forEach((rawLine, index) => {
    const line = String(rawLine ?? "").replace(/\r/g, "");
    if (!line.trim()) return;

    if (line.startsWith("# ")) {
      const [id = "", labelFr = "", labelEn = ""] = line.slice(2).split("\t");
      currentLevel = { id, labelFr, labelEn };
      currentLevelSections = [];
      tabs.push(currentLevel);
      return;
    }

    if (!currentLevel) return;
    const [
      sectionRaw = "", appRaw = "", numberRaw = "", labelFrRaw = "", descFrRaw = "",
      labelEnRaw = "", descEnRaw = ""
    ] = line.split("\t");
    const section = sectionRaw.trim() || "Général";
    const sectionEn = sectionEnByFr[section] || section;
    const category = `${currentLevel.id}:${normalizeCatalogSlug(section)}`;
    let sectionIndex = currentLevelSections.indexOf(section);
    if (sectionIndex === -1) {
      currentLevelSections.push(section);
      sectionIndex = currentLevelSections.length - 1;
    }
    const sectionNumber = sectionIndex + 1;
    const sectionRoman = toRomanNumeral(sectionNumber);
    const competencyNumber = Number(numberRaw);
    const labelFr = labelFrRaw.trim();
    const descFr = descFrRaw.trim();
    const labelEn = labelEnRaw.trim() || labelFr;
    const descEn = descEnRaw.trim() || descFr;
    if (!Number.isFinite(competencyNumber) || !labelFr || !descFr) {
      console.warn("Invalid competency catalog row", {
        lineNumber: index + 1,
        level: currentLevel.id,
        row: line
      });
      return;
    }
    categories[category] ||= {
      fr: `${sectionRoman} - ${section}`,
      en: `${sectionRoman} - ${sectionEn}`,
      plainFr: section,
      plainEn: sectionEn,
      number: sectionNumber,
      roman: sectionRoman
    };
    const shortCodeFr = `${currentLevel.labelFr}-${sectionRoman}-${competencyNumber}`;
    const shortCodeEn = `${currentLevel.labelEn}-${sectionRoman}-${competencyNumber}`;
    const legacyShortCode = `${legacyCodeByLevel[currentLevel.id] || currentLevel.id.charAt(0).toUpperCase()}${competencyNumber}`;

    data.push({
      id: `competency:${currentLevel.id}:${numberRaw.trim()}`,
      frameworkId: "florimont",
      groupId: currentLevel.id,
      platform: currentLevel.id,
      category,
      sectionFr: section,
      sectionEn,
      appFr: appRaw.trim(),
      appEn: appEnByFr[appRaw.trim()] || appRaw.trim(),
      levelLabelFr: currentLevel.labelFr,
      levelLabelEn: currentLevel.labelEn,
      levelBadge: badgeByLevel[currentLevel.id] || currentLevel.id,
      number: competencyNumber,
      sectionNumber,
      sectionRoman,
      // `shortCode` reste l'alias français historique pour les imports existants.
      shortCode: shortCodeFr,
      shortCodeFr,
      shortCodeEn,
      legacyShortCode,
      labelFr,
      labelEn,
      descFr,
      descEn
    });
  });

  return { data, categories, tabs };
}

function parseAdditionalCompetencyFrameworkCatalog(source) {
  const data = [];
  const frameworks = [];
  let framework = null;
  let group = null;
  let subgroup = null;

  String(source ?? "").split("\n").forEach((rawLine) => {
    const line = String(rawLine ?? "").replace(/\r/g, "");
    if (!line.trim()) return;

    if (line.startsWith("# framework\t")) {
      const [, id = "", labelFr = "", labelEn = "", sourceUrl = ""] = line.split("\t");
      framework = { id, labelFr, labelEn, sourceUrl, groups: [] };
      frameworks.push(framework);
      group = null;
      subgroup = null;
      return;
    }

    if (line.startsWith("## group\t") && framework) {
      const [, id = "", labelFr = "", labelEn = "", sourceUrl = ""] = line.split("\t");
      group = { id, labelFr, labelEn, sourceUrl };
      framework.groups.push(group);
      subgroup = null;
      return;
    }

    if (line.startsWith("### subgroup\t") && framework && group) {
      const [, id = "", labelFr = "", labelEn = ""] = line.split("\t");
      subgroup = { id, labelFr, labelEn: labelEn || labelFr };
      return;
    }

    if (!framework || !group) return;
    const [code = "", labelFr = "", descFr = "", labelEn = "", descEn = "", sourceUrl = ""] = line.split("\t");
    if (!code.trim() || !labelFr.trim()) return;
    const sectionFr = subgroup?.labelFr || group.labelFr;
    const sectionEn = subgroup?.labelEn || group.labelEn;
    const categoryId = subgroup ? `${group.id}:${subgroup.id}` : group.id;
    data.push({
      id: `competency:${framework.id}:${code.trim()}`,
      frameworkId: framework.id,
      groupId: group.id,
      platform: framework.id,
      category: `${framework.id}:${categoryId}`,
      sectionFr,
      sectionEn,
      appFr: "",
      appEn: "",
      levelLabelFr: framework.labelFr,
      levelLabelEn: framework.labelEn,
      levelBadge: framework.labelFr,
      number: code.trim(),
      displayCode: code.trim(),
      sourceUrl: sourceUrl.trim() || group.sourceUrl || framework.sourceUrl,
      shortCode: `${framework.labelFr} ${code.trim()}`,
      shortCodeFr: `${framework.labelFr} ${code.trim()}`,
      shortCodeEn: `${framework.labelEn} ${code.trim()}`,
      legacyShortCode: "",
      labelFr: labelFr.trim(),
      labelEn: labelEn.trim() || labelFr.trim(),
      descFr: descFr.trim(),
      descEn: descEn.trim() || descFr.trim()
    });
  });

  return { data, frameworks };
}

function attachFrameworkDetails(data, frameworkId, source) {
  const byId = new Map(data.map((item) => [item.id, item]));
  String(source ?? "").split("\n").forEach((rawLine) => {
    const line = String(rawLine ?? "").replace(/\r/g, "");
    if (!line.trim()) return;
    const [code = "", kind = "", orderRaw = "", textFr = "", textEn = ""] = line.split("\t");
    const item = byId.get(`competency:${frameworkId}:${code.trim()}`);
    const text = textFr.trim();
    if (!item || !text) return;
    if (kind === "description") {
      item.descFr = text;
      item.descEn = textEn.trim() || text;
      return;
    }
    if (!["knowledge", "skills", "attitudes", "basic", "intermediate", "advanced", "highly_advanced"].includes(kind)) return;
    if (!Array.isArray(item.details)) item.details = [];
    item.details.push({
      kind,
      order: Number.parseInt(orderRaw, 10) || item.details.length + 1,
      textFr: text,
      textEn: textEn.trim() || text
    });
  });
}

const florimontCatalog = parseCompetencyCatalog(mergeCompetencyCatalogTranslations(
  COMPETENCY_CATALOG_SOURCE,
  COMPETENCY_CATALOG_EN_SOURCE
));
const additionalFrameworkCatalog = parseAdditionalCompetencyFrameworkCatalog(
  COMPETENCY_FRAMEWORK_CATALOG_SOURCE
);
attachFrameworkDetails(
  additionalFrameworkCatalog.data,
  "greencomp",
  typeof COMPETENCY_GREENCOMP_DETAIL_SOURCE === "undefined"
    ? ""
    : COMPETENCY_GREENCOMP_DETAIL_SOURCE
);
attachFrameworkDetails(
  additionalFrameworkCatalog.data,
  "digcomp",
  typeof COMPETENCY_DIGCOMP_DETAIL_SOURCE === "undefined"
    ? ""
    : COMPETENCY_DIGCOMP_DETAIL_SOURCE
);

const DIGCOMP_LEVEL_LABELS = {
  basic: { fr: "Niveau élémentaire", en: "Basic level" },
  intermediate: { fr: "Niveau intermédiaire", en: "Intermediate level" },
  advanced: { fr: "Niveau avancé", en: "Advanced level" },
  highly_advanced: { fr: "Niveau hautement avancé", en: "Highly advanced level" }
};

const GREENCOMP_TYPE_LABELS = {
  knowledge: { code: "K", fr: "Connaissance", en: "Knowledge" },
  skills: { code: "S", fr: "Aptitude", en: "Skill" },
  attitudes: { code: "A", fr: "Attitude", en: "Attitude" }
};

function expandGreenCompIndicators(items) {
  return items.flatMap((parent) => {
    if (parent.frameworkId !== "greencomp" || !Array.isArray(parent.details) || !parent.details.length) {
      return [parent];
    }

    const hiddenParent = { ...parent, pickerHidden: true };
    const indicators = parent.details.map((detail) => {
      const type = GREENCOMP_TYPE_LABELS[detail.kind];
      if (!type) return null;
      const order = Number.parseInt(detail.order, 10) || 1;
      const indicatorCode = `GC${parent.number}.${type.code}${String(order).padStart(2, "0")}`;
      const textFr = String(detail.textFr || "").trim();
      const textEn = String(detail.textEn || textFr).trim();
      if (!textFr) return null;
      return {
        ...parent,
        id: `competency:greencomp:${indicatorCode}`,
        category: `greencomp:${parent.number}`,
        sectionFr: `${parent.number}. ${parent.labelFr}`,
        sectionEn: `${parent.number}. ${parent.labelEn}`,
        number: indicatorCode,
        displayCode: indicatorCode,
        shortCode: indicatorCode,
        shortCodeFr: indicatorCode,
        shortCodeEn: indicatorCode,
        legacyShortCode: indicatorCode,
        labelFr: textFr,
        labelEn: textEn,
        descFr: type.fr,
        descEn: type.en,
        parentLabelFr: `${parent.number}. ${parent.labelFr}`,
        parentLabelEn: `${parent.number}. ${parent.labelEn}`,
        parentDescFr: parent.descFr,
        parentDescEn: parent.descEn,
        indicatorKind: detail.kind,
        indicatorOrder: order,
        details: [],
        isCompetencyIndicator: true
      };
    }).filter(Boolean);
    return [hiddenParent, ...indicators];
  });
}

function expandDigCompStatements(items) {
  return items.flatMap((parent) => {
    if (parent.frameworkId !== "digcomp" || !Array.isArray(parent.details) || !parent.details.length) {
      return [parent];
    }

    const hiddenParent = { ...parent, pickerHidden: true };
    const statements = parent.details.map((detail) => {
      const textFr = String(detail.textFr || "").trim();
      const textEn = String(detail.textEn || textFr).trim();
      const frMatch = textFr.match(/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u);
      const enMatch = textEn.match(/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u);
      if (!frMatch || !enMatch || frMatch[1] !== enMatch[1]) return null;
      const statementCode = frMatch[1];
      const level = DIGCOMP_LEVEL_LABELS[detail.kind] || { fr: detail.kind, en: detail.kind };
      return {
        ...parent,
        id: `competency:digcomp:${statementCode}`,
        category: `digcomp:${parent.number}`,
        sectionFr: `${parent.number}. ${parent.labelFr}`,
        sectionEn: `${parent.number}. ${parent.labelEn}`,
        number: statementCode,
        displayCode: statementCode,
        shortCode: statementCode,
        shortCodeFr: statementCode,
        shortCodeEn: statementCode,
        legacyShortCode: statementCode,
        labelFr: frMatch[2],
        labelEn: enMatch[2],
        descFr: level.fr,
        descEn: level.en,
        parentLabelFr: `${parent.number}. ${parent.labelFr}`,
        parentLabelEn: `${parent.number}. ${parent.labelEn}`,
        parentDescFr: parent.descFr,
        parentDescEn: parent.descEn,
        statementKind: detail.kind,
        statementOrder: detail.order,
        details: [],
        isCompetencyStatement: true
      };
    }).filter(Boolean);
    return [hiddenParent, ...statements];
  });
}

const additionalSelectableTools = expandDigCompStatements(
  expandGreenCompIndicators(additionalFrameworkCatalog.data)
);
const COMPETENCY_FRAMEWORKS = [
  {
    id: "florimont",
    labelFr: "Florimont",
    labelEn: "Florimont",
    sourceUrl: "competencies.php#framework-florimont",
    groups: florimontCatalog.tabs.map((tab) => ({
      id: tab.id,
      labelFr: tab.labelFr,
      labelEn: tab.labelEn
    }))
  },
  ...additionalFrameworkCatalog.frameworks
];
const SELECTABLE_TOOLS_DATA = [
  ...florimontCatalog.data,
  ...additionalSelectableTools
];
const SELECTABLE_TOOL_CATEGORY_LABELS = florimontCatalog.categories;

const SELECTABLE_TOOL_IDS_SET = new Set(SELECTABLE_TOOLS_DATA.map(tool => tool.id));
const COMPETENCY_REFERENCE_MAP = SELECTABLE_TOOLS_DATA.reduce((map, tool) => {
  [
    tool.id,
    tool.shortCode,
    tool.shortCodeEn,
    tool.legacyShortCode,
    tool.labelFr,
    tool.labelEn
  ].forEach((value) => {
    const token = normalizeToken(value);
    if (token) map[token] = tool.id;
  });
  return map;
}, {});

const COMPETENCY_LEVEL_STYLES = {
  acquerir: {
    bg: "#e0f2fe",
    border: "#7dd3fc",
    text: "#075985",
    active: "#bae6fd"
  },
  approfondir: {
    bg: "#ede9fe",
    border: "#c4b5fd",
    text: "#5b21b6",
    active: "#ddd6fe"
  },
  creer: {
    bg: "#dcfce7",
    border: "#86efac",
    text: "#166534",
    active: "#bbf7d0"
  },
  socle: {
    bg: "#fff7ed",
    border: "#fdba74",
    text: "#9a3412",
    active: "#ffedd5"
  },
  greencomp: {
    bg: "#ecfdf5",
    border: "#6ee7b7",
    text: "#047857",
    active: "#d1fae5"
  },
  digcomp: {
    bg: "#eff6ff",
    border: "#93c5fd",
    text: "#1d4ed8",
    active: "#dbeafe"
  },
  crcn: {
    bg: "#fdf2f8",
    border: "#f9a8d4",
    text: "#be185d",
    active: "#fce7f3"
  },
  pix: {
    bg: "#ecfeff",
    border: "#67e8f9",
    text: "#155e75",
    active: "#cffafe"
  },
  "pix-ia": {
    bg: "#fff7ed",
    border: "#fdba74",
    text: "#9a3412",
    active: "#ffedd5"
  }
};

const COMPETENCY_DOMAIN_STYLES = [
  {
    bg: "#fff7ed",
    border: "#fdba74",
    text: "#9a3412",
    active: "#ffedd5"
  },
  {
    bg: "#ecfeff",
    border: "#67e8f9",
    text: "#155e75",
    active: "#cffafe"
  },
  {
    bg: "#f5f3ff",
    border: "#c4b5fd",
    text: "#6d28d9",
    active: "#ede9fe"
  },
  {
    bg: "#ecfdf5",
    border: "#6ee7b7",
    text: "#047857",
    active: "#d1fae5"
  },
  {
    bg: "#fdf2f8",
    border: "#f9a8d4",
    text: "#be185d",
    active: "#fce7f3"
  }
];

const COMPETENCY_DOMAIN_INDEX = {
  "domaine-1": 0,
  valeurs: 0,
  information: 0,
  fondements: 0,
  "domaine-2": 1,
  complexite: 1,
  communication: 1,
  usages: 1,
  "domaine-3": 2,
  avenirs: 2,
  creation: 2,
  enjeux: 2,
  "domaine-4": 3,
  action: 3,
  protection: 3,
  "domaine-5": 4,
  problemes: 4,
  environnement: 4
};

function getCompetencyStyle(level, groupId = "") {
  const primaryGroupId = String(groupId).split(":")[0];
  const domainIndex = COMPETENCY_DOMAIN_INDEX[primaryGroupId];
  if (level !== "florimont" && Number.isInteger(domainIndex)) {
    return COMPETENCY_DOMAIN_STYLES[domainIndex];
  }
  return COMPETENCY_LEVEL_STYLES[level] || COMPETENCY_LEVEL_STYLES.acquerir;
}

function applyCompetencyTheme(element, level, groupId = "") {
  if (!element) return;
  element.classList.toggle("competency-per-romand", level === "per-romand");
  const theme = getCompetencyStyle(level, groupId);
  element.style.setProperty("--competency-bg", theme.bg);
  element.style.setProperty("--competency-border", theme.border);
  element.style.setProperty("--competency-text", theme.text);
  element.style.setProperty("--competency-active", theme.active);
}

function applyLanguageTypography(value, lang = currentLang()) {
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

function competencyTooltip(toolDef, lang) {
  if (!toolDef) return "";
  const label = formatCompetencyLabel(toolDef, lang);
  const rawDetails = lang === "en" ? toolDef.descEn : toolDef.descFr;
  const details = applyLanguageTypography(rawDetails, lang);
  return [label, details].filter(Boolean).join(" — ");
}

function formatCompetencyLabel(toolDef, lang = currentLang()) {
  if (!toolDef) return "";
  const label = lang === "en" ? toolDef.labelEn : toolDef.labelFr;
  const formattedLabel = applyLanguageTypography(label, lang);
  if (toolDef.isCompetencyStatement || toolDef.isCompetencyIndicator) {
    return `${toolDef.displayCode || toolDef.number} · ${formattedLabel}`;
  }
  return `${toolDef.displayCode || toolDef.number}. ${formattedLabel}`;
}

function formatCompetencyShortCode(toolDef, lang = currentLang()) {
  if (!toolDef) return "";
  if (toolDef.frameworkId === "per-romand") {
    return `PER romand · ${toolDef.displayCode || toolDef.number}`;
  }
  return lang === "en" ? toolDef.shortCodeEn : toolDef.shortCodeFr;
}

return {
  COMPETENCY_FRAMEWORKS, SELECTABLE_TOOLS_DATA, SELECTABLE_TOOL_CATEGORY_LABELS,
  SELECTABLE_TOOL_IDS_SET, COMPETENCY_REFERENCE_MAP, applyCompetencyTheme,
  applyLanguageTypography, competencyTooltip, formatCompetencyLabel,
  formatCompetencyShortCode
};
};
})();
