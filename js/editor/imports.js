// Lecture et migration des scénarios CSV, Markdown et LDJ.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createImports = ({
  normalizeToken, SPREADSHEET_COLUMNS, I18N, SCHOOL_SYSTEM_OPTIONS, SCHOOL_LEVEL_OPTIONS,
  DEFAULT_DAY_HOURS, normalizePedagogicalTime, createNewDesignState,
  toPlainTextareaValue, nextId, currentLang, defaultSessionTitle, normalizeActivity,
  hydrateState, parseAiasValue, defaultAiasState
}) => {
function parseCsvRows(text) {
  const source = String(text ?? "").replace(/^\uFEFF/, "");
  const rows = [];
  let row = [];
  let cell = "";
  let inQuotes = false;

  for (let index = 0; index < source.length; index += 1) {
    const char = source[index];
    if (inQuotes) {
      if (char === '"') {
        if (source[index + 1] === '"') {
          cell += '"';
          index += 1;
        } else {
          inQuotes = false;
        }
      } else {
        cell += char;
      }
      continue;
    }
    if (char === '"') {
      inQuotes = true;
      continue;
    }
    if (char === ",") {
      row.push(cell);
      cell = "";
      continue;
    }
    if (char === "\n") {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = "";
      continue;
    }
    if (char === "\r") continue;
    cell += char;
  }

  if (cell.length > 0 || row.length > 0) {
    row.push(cell);
    rows.push(row);
  }
  return rows;
}

function buildLookup(entries) {
  const map = {};
  entries.forEach(([code, ...labels]) => {
    [code, ...labels].forEach((label) => {
      const token = normalizeToken(label);
      if (token) map[token] = code;
    });
  });
  return map;
}

function buildSpreadsheetHeaderIndex(headerRow) {
  const headerIndex = {};
  headerRow.forEach((header, index) => {
    const token = normalizeToken(header);
    if (!token) return;
    headerIndex[token] = index;
  });

  SPREADSHEET_COLUMNS.forEach(({ key, label }) => {
    [key, label].forEach((header) => {
      const token = normalizeToken(header);
      if (token && headerIndex[token] != null && headerIndex[key] == null) {
        headerIndex[key] = headerIndex[token];
      }
    });
  });

  if (headerIndex.teaching_mode == null) {
    ["trainer_presence", "Présence de l'enseignant", "Teacher presence"].some((legacyHeader) => {
      const legacyIndex = headerIndex[normalizeToken(legacyHeader)];
      if (legacyIndex == null) return false;
      headerIndex.teaching_mode = legacyIndex;
      return true;
    });
  }

  return headerIndex;
}

const CSV_TYPE_LOOKUP = buildLookup([
  ["undefined", I18N.fr.lt_undefined, I18N.en.lt_undefined],
  ["read", I18N.fr.lt_read, I18N.en.lt_read],
  ["investigate", I18N.fr.lt_investigate, I18N.en.lt_investigate],
  ["practice", I18N.fr.lt_practice, I18N.en.lt_practice],
  ["produce", I18N.fr.lt_produce, I18N.en.lt_produce],
  ["discuss", I18N.fr.lt_discuss, I18N.en.lt_discuss],
  ["collaborate", I18N.fr.lt_collaborate, I18N.en.lt_collaborate]
]);

const CSV_GROUP_LOOKUP = buildLookup([
  ["whole", I18N.fr.group_whole, I18N.en.group_whole, "whole class"],
  ["subgroups", I18N.fr.group_subgroups, I18N.en.group_subgroups, "subgroups"],
  ["individual", I18N.fr.group_individual, I18N.en.group_individual]
]);

const CSV_TEACHING_LOOKUP = buildLookup([
  ["directed", I18N.fr.teaching_directed, I18N.en.teaching_directed, "teacher directed"],
  ["guided", I18N.fr.teaching_guided, I18N.en.teaching_guided, "teacher guided"],
  ["supported", I18N.fr.teaching_supported, I18N.en.teaching_supported, "teacher supported"],
  ["independent", I18N.fr.teaching_independent, I18N.en.teaching_independent, "independent work", "absent", "enseignant absent", "teacher absent"],
  ["undefined", I18N.fr.teaching_undefined, I18N.en.teaching_undefined, "present", "enseignant present", "teacher present"]
]);

const CSV_SYNC_LOOKUP = buildLookup([
  ["sync", I18N.fr.sync_sync, I18N.en.sync_sync, "synchronous"],
  ["async", I18N.fr.sync_async, I18N.en.sync_async, "asynchronous"]
]);

const CSV_LOCATION_LOOKUP = buildLookup([
  ["onsite", I18N.fr.activityModeClassroom, I18N.en.activityModeClassroom, "presentiel", "face to face", "classroom"],
  ["location_based", I18N.fr.activityModeLocation, I18N.en.activityModeLocation, "location based"],
  ["online", I18N.fr.activityModeOnline, I18N.en.activityModeOnline, "online", "distanciel", "distance"],
  ["hybrid", I18N.fr.activityModeBlended, I18N.en.activityModeBlended, "hybrid"],
  ["other", I18N.fr.activityModeOther, I18N.en.activityModeOther]
]);

const SCHOOL_SYSTEM_ALIASES = {
  france: ["système français", "French system"],
  switzerland: ["système suisse", "Swiss system"],
  united_states: ["K-12", "US K-12", "American system"],
  belgium_french: ["Belgique francophone", "Communauté française de Belgique", "Fédération Wallonie-Bruxelles", "French Community of Belgium"],
  belgium_flemish: ["Belgique néerlandophone", "Communauté flamande", "Vlaamse Gemeenschap", "Flemish Community"],
  belgium_german: ["Belgique germanophone", "Communauté germanophone", "Deutschsprachige Gemeinschaft", "German-speaking Community"],
  uk_england: ["Angleterre", "England", "English school system"],
  uk_wales: ["Pays de Galles", "Wales", "Welsh school system"],
  uk_scotland: ["Écosse", "Ecosse", "Scotland", "Scottish school system"],
  uk_northern_ireland: ["Irlande du Nord", "Northern Ireland", "Northern Irish school system"],
  european_schools: ["Écoles européennes", "Ecoles europeennes", "European Schools", "Schola Europaea"],
  ib: ["IB", "Baccalauréat international (IB)", "Baccalauréat international", "Bac international", "International Baccalaureate"],
  isced_2011: ["ISCED", "ISCED 2011", "CITE", "CITE 2011"]
};

const CSV_SCHOOL_SYSTEM_LOOKUP = buildLookup(
  SCHOOL_SYSTEM_OPTIONS.map((option) => [
    option.value,
    option.labels.fr,
    option.labels.en,
    ...(SCHOOL_SYSTEM_ALIASES[option.value] || [])
  ])
);

const CSV_LEVEL_LOOKUPS = Object.fromEntries(
  Object.entries(SCHOOL_LEVEL_OPTIONS).map(([system, options]) => [
    system,
    buildLookup(options.map((option) => [
      option.value,
      option.labels.fr,
      option.labels.en,
      ...(option.aliases || [])
    ]))
  ])
);

const CSV_LEVEL_LOOKUP = buildLookup(
  Object.values(SCHOOL_LEVEL_OPTIONS).flat().map((option) => [
    option.value,
    option.labels.fr,
    option.labels.en,
    ...(option.aliases || [])
  ])
);

function lookupSchoolLevel(raw, system = "") {
  const lookup = CSV_LEVEL_LOOKUPS[system] || CSV_LEVEL_LOOKUP;
  return lookupValue(raw, lookup, "");
}

const CSV_EVAL_LOOKUP = buildLookup([
  ["none", I18N.fr.eval_none, I18N.en.eval_none, "aucune evaluation", "none"],
  ["diagnostic", I18N.fr.eval_diagnostic, I18N.en.eval_diagnostic],
  ["formative", I18N.fr.eval_formative, I18N.en.eval_formative],
  ["summative", I18N.fr.eval_summative, I18N.en.eval_summative],
  ["certificative", I18N.fr.eval_certificative, I18N.en.eval_certificative, "certifying"]
]);

function lookupValue(raw, lookup, fallback) {
  return lookup[normalizeToken(raw)] || fallback;
}

function parseCsvInteger(value, fallback = 0) {
  const parsed = Number.parseInt(String(value ?? "").trim(), 10);
  if (!Number.isFinite(parsed)) return fallback;
  return parsed;
}

function parseCsvPedagogicalTime(value, dayHours = DEFAULT_DAY_HOURS) {
  const values = String(value ?? "")
    .match(/\d+/g)
    ?.map((part) => Number.parseInt(part, 10))
    .filter((num) => Number.isFinite(num)) || [];
  const days = values[0] ?? 0;
  const hours = values[1] ?? 0;
  const minutes = values[2] ?? 0;
  return normalizePedagogicalTime(days, hours, minutes, dayHours);
}

function parseLegacyLearningType(value) {
  return lookupValue(value, CSV_TYPE_LOOKUP, "read");
}

function parseLegacyEvaluationType(value) {
  return lookupValue(value, CSV_EVAL_LOOKUP, "none");
}

function parseLegacyGroupMode(groupSize, groupSizeSameAsSession, sessionGroupSize) {
  if (groupSizeSameAsSession) {
    return Number(sessionGroupSize) > 1 ? "whole" : "individual";
  }
  const size = Math.max(0, parseCsvInteger(groupSize, 0));
  if (size <= 1) return "individual";
  if (size < Math.max(2, Number(sessionGroupSize) || 15)) return "subgroups";
  return "whole";
}

function isLegacyLdjDocument(parsed) {
  return Boolean(
    parsed &&
    typeof parsed === "object" &&
    Array.isArray(parsed.activities) &&
    !Array.isArray(parsed.sessions)
  );
}

function buildStateFromLegacyLdj(parsed) {
  if (!isLegacyLdjDocument(parsed)) return null;

  const imported = createNewDesignState();
  const topic = toPlainTextareaValue(parsed.topic).trim();
  const description = toPlainTextareaValue(parsed.description).trim();
  const aims = toPlainTextareaValue(parsed.aims).trim();
  const outcomes = Array.isArray(parsed.outcomes)
    ? parsed.outcomes
        .map((item) => {
          const text = toPlainTextareaValue(item?.details).trim();
          const verb = toPlainTextareaValue(item?.verb).trim();
          return { id: nextId(), category: "", categoryLabel: "", verb, text };
        })
        .filter((o) => o.verb || o.text)
    : [];

  imported.meta.uiLanguage = currentLang();
  imported.meta.name = toPlainTextareaValue(parsed.name).trim();
  imported.meta.modeDelivery = lookupValue(parsed.modeOfDelivery, CSV_LOCATION_LOOKUP, "onsite");
  imported.meta.sizeClass = toPlainTextareaValue(parsed.groupSize).trim();
  imported.meta.designers = toPlainTextareaValue(parsed.author).trim();
  imported.meta.description = description;
  imported.meta.command = topic;
  imported.meta.personas = aims;
  imported.meta.sliders = outcomes;

  const learningTime = parseCsvPedagogicalTime(parsed.learningTime, imported.meta.dayHours);
  imported.meta.learningDays = learningTime.days;
  imported.meta.learningHours = learningTime.hours;
  imported.meta.learningMinutes = learningTime.minutes;

  imported.sessions = parsed.activities.map((legacySession, sessionIndex) => {
    const sessionGroupSize = parseCsvInteger(
      legacySession?.groupSize ?? parsed.groupSize,
      parseCsvInteger(parsed.groupSize, 0)
    );
    const session = {
      id: nextId(),
      title: toPlainTextareaValue(legacySession?.title).trim() || defaultSessionTitle(sessionIndex + 1),
      objectives: "",
      intentions: toPlainTextareaValue(legacySession?.teachingMethod).trim(),
      notes: toPlainTextareaValue(legacySession?.notes).trim(),
      notesExpanded: false,
      activities: []
    };

    const resources = Array.isArray(legacySession?.resources)
      ? legacySession.resources.map((value) => toPlainTextareaValue(value).trim()).filter(Boolean)
      : [];
    if (resources.length) {
      session.notes = [session.notes, resources.join("\n")].filter(Boolean).join("\n\n");
    }

    session.activities = Array.isArray(legacySession?.slas)
      ? legacySession.slas.map((legacyActivity) => {
          const activity = {
            id: nextId(),
            type: parseLegacyLearningType(legacyActivity?.type),
            duration: Math.max(1, parseCsvInteger(legacyActivity?.duration, 1)),
            groupMode: parseLegacyGroupMode(
              legacyActivity?.groupSize,
              String(legacyActivity?.groupSizeSameAsSession) === "true",
              sessionGroupSize
            ),
            teachingMode: String(legacyActivity?.tutorAvailable) === "true" ? "undefined" : "independent",
            syncMode: String(legacyActivity?.syncActivity) === "true" ? "sync" : "async",
            locationMode: String(legacyActivity?.onlineActivity) === "true" ? "online" : "onsite",
            evaluationMode: parseLegacyEvaluationType(legacyActivity?.assessmentType),
            description: toPlainTextareaValue(legacyActivity?.description).trim(),
            instructions: toPlainTextareaValue(
              legacyActivity?.instructions ?? legacyActivity?.studentInstructions
            ).trim(),
            notes: "",
            tools: []
          };

          const activityResources = Array.isArray(legacyActivity?.resources)
            ? legacyActivity.resources.map((value) => toPlainTextareaValue(value).trim()).filter(Boolean)
            : [];
          if (activityResources.length) {
            activity.notes = activityResources.join("\n");
          }
          normalizeActivity(activity);
          return activity;
        })
      : [];

    return session;
  });

  return hydrateState(imported, null);
}

function buildStateFromCsv(csvText) {
  const rows = parseCsvRows(csvText);
  if (rows.length < 2) return null;

  const headerIndex = buildSpreadsheetHeaderIndex(rows[0]);
  if (headerIndex.session_index == null || headerIndex.session_title == null) return null;

  const dataRows = rows.slice(1).filter((row) => row.some((cell) => String(cell || "").trim() !== ""));
  if (!dataRows.length) return null;

  const imported = createNewDesignState();
  imported.meta.uiLanguage = currentLang();
  const sessions = [];
  const sessionsByIndex = new Map();
  let metaLoaded = false;

  dataRows.forEach((row, rowIndex) => {
    const read = (name) => {
      const index = headerIndex[name];
      if (index == null) return "";
      return String(row[index] ?? "");
    };

    if (!metaLoaded) {
      const dayHours = Math.max(1, parseCsvInteger(read("design_day_hours"), DEFAULT_DAY_HOURS));
      imported.meta.name = read("design_title");
      imported.meta.modeDelivery = lookupValue(read("design_mode"), CSV_LOCATION_LOOKUP, "onsite");
      imported.meta.schoolSystem = lookupValue(
        read("design_school_system"),
        CSV_SCHOOL_SYSTEM_LOOKUP,
        ""
      );
      imported.meta.schoolLevel = lookupSchoolLevel(
        read("design_level"),
        imported.meta.schoolSystem
      );
      imported.meta.sizeClass = read("design_group_size").trim();
      imported.meta.designers = read("design_designers");
      imported.meta.trainers = read("design_trainers");
      imported.meta.dayHours = dayHours;
      imported.meta.description = read("design_description");
      imported.meta.command = read("design_institutional_brief");
      imported.meta.personas = read("design_personas");
      imported.meta.sliders = read("design_sliders");

      const learningTime = parseCsvPedagogicalTime(read("design_learning_time"), dayHours);
      imported.meta.learningDays = learningTime.days;
      imported.meta.learningHours = learningTime.hours;
      imported.meta.learningMinutes = learningTime.minutes;
      metaLoaded = true;
    }

    const sessionOrder = parseCsvInteger(read("session_index"), rowIndex + 1);
    const sessionTitle = read("session_title").trim() || defaultSessionTitle(sessionOrder || sessions.length + 1);
    const sessionObjectives = read("session_objectives");
    const sessionIntentions = read("session_intentions");
    const sessionNotes = read("session_notes");
    const sessionKey = `${sessionOrder}`;
    let session = sessionsByIndex.get(sessionKey);
    if (!session) {
      session = {
        id: nextId(),
        title: sessionTitle,
        objectives: sessionObjectives,
        intentions: sessionIntentions,
        notes: sessionNotes,
        notesExpanded: false,
        activities: []
      };
      sessionsByIndex.set(sessionKey, session);
      sessions.push(session);
    } else {
      if (!session.title && sessionTitle) session.title = sessionTitle;
      if (!session.objectives && sessionObjectives) session.objectives = sessionObjectives;
      if (!session.intentions && sessionIntentions) session.intentions = sessionIntentions;
      if (!session.notes && sessionNotes) session.notes = sessionNotes;
    }

    const hasActivityData = [
      read("activity_index"),
      read("learning_type"),
      read("duration_minutes"),
      read("group_size"),
      read("teaching_mode") || read("trainer_presence"),
      read("pacing"),
      read("delivery_mode"),
      read("assessment"),
      read("aias"),
      read("activity_description"),
      read("activity_instructions"),
      read("activity_notes")
    ].some((value) => value.trim() !== "");
    if (!hasActivityData) return;

    const duration = Math.max(1, parseCsvInteger(read("duration_minutes"), 1));
    const activity = {
      id: nextId(),
      type: lookupValue(read("learning_type"), CSV_TYPE_LOOKUP, "read"),
      duration,
      groupMode: lookupValue(read("group_size"), CSV_GROUP_LOOKUP, "whole"),
      teachingMode: lookupValue(read("teaching_mode") || read("trainer_presence"), CSV_TEACHING_LOOKUP, "undefined"),
      syncMode: lookupValue(read("pacing"), CSV_SYNC_LOOKUP, "sync"),
      locationMode: lookupValue(read("delivery_mode"), CSV_LOCATION_LOOKUP, "onsite"),
      evaluationMode: lookupValue(read("assessment"), CSV_EVAL_LOOKUP, "none"),
      aias: parseAiasValue(read("aias")),
      description: read("activity_description"),
      instructions: read("activity_instructions"),
      notes: read("activity_notes"),
      tools: (read("activity_competencies") || read("activity_tools")).split(";").map(s => s.trim()).filter(Boolean),
      _csvOrder: parseCsvInteger(read("activity_index"), session.activities.length + 1)
    };
    normalizeActivity(activity);
    session.activities.push(activity);
  });

  sessions.forEach((session) => {
    session.activities.sort((a, b) => a._csvOrder - b._csvOrder);
    session.activities.forEach((activity) => {
      delete activity._csvOrder;
    });
  });

  imported.sessions = sessions;
  return hydrateState(imported, null);
}

function cleanMarkdownExportValue(value) {
  const trimmed = String(value ?? "").trim();
  return trimmed === "-" ? "" : trimmed;
}

function parseMarkdownFieldLine(line) {
  const match = String(line || "").match(/^-\s*([^:]+):\s*(.*)$/);
  if (!match) return null;
  return {
    key: normalizeToken(match[1]),
    value: cleanMarkdownExportValue(match[2])
  };
}

function readMarkdownBlock(lines, startIndex) {
  const block = [];
  let index = startIndex;
  while (index < lines.length && !/^#{2,3}\s+/.test(lines[index])) {
    block.push(lines[index]);
    index += 1;
  }
  return {
    value: cleanMarkdownExportValue(block.join("\n").trim()),
    nextIndex: index
  };
}

function parseMarkdownLinks(value) {
  const links = [];
  const pattern = /([^(),]+?)\s*\((https?:\/\/[^)]+)\)/g;
  let match;
  while ((match = pattern.exec(String(value || ""))) !== null) {
    const title = match[1].trim();
    const url = match[2].trim();
    if (title && url) links.push({ id: nextId(), title, url });
  }
  return links;
}

function buildStateFromMarkdown(markdownText) {
  const lines = String(markdownText ?? "").replace(/^\uFEFF/, "").replace(/\r\n?/g, "\n").split("\n");
  const firstTitle = lines.find((line) => /^#\s+/.test(line));
  if (
    !firstTitle ||
    !lines.some((line) => /^##\s+Paramètres\s*$/i.test(line)) ||
    !lines.some((line) => /^##\s+Séances\s*$/i.test(line))
  ) {
    return null;
  }

  const imported = createNewDesignState();
  imported.meta.uiLanguage = currentLang();
  imported.meta.name = cleanMarkdownExportValue(firstTitle.replace(/^#\s+/, ""));

  let index = 0;
  let inSettings = false;
  let inSessions = false;
  let currentSession = null;
  let currentActivity = null;

  const pushCurrentActivity = () => {
    if (currentSession && currentActivity) {
      normalizeActivity(currentActivity);
      currentSession.activities.push(currentActivity);
    }
    currentActivity = null;
  };

  const pushCurrentSession = () => {
    pushCurrentActivity();
    if (currentSession) imported.sessions.push(currentSession);
    currentSession = null;
  };

  while (index < lines.length) {
    const line = lines[index];

    if (/^##\s+Paramètres\s*$/i.test(line)) {
      pushCurrentSession();
      inSettings = true;
      inSessions = false;
      index += 1;
      continue;
    }

    if (/^##\s+Séances\s*$/i.test(line)) {
      inSettings = false;
      inSessions = true;
      index += 1;
      continue;
    }

    if (inSettings) {
      const dayHoursMatch = line.match(/^-\s*1 jour\s*=\s*(\d+)/i);
      if (dayHoursMatch) {
        imported.meta.dayHours = Math.max(1, parseCsvInteger(dayHoursMatch[1], DEFAULT_DAY_HOURS));
        index += 1;
        continue;
      }

      const field = parseMarkdownFieldLine(line);
      if (field) {
        if (field.key === "mode") imported.meta.modeDelivery = lookupValue(field.value, CSV_LOCATION_LOOKUP, "");
        if (["systeme scolaire", "school system"].includes(field.key)) {
          imported.meta.schoolSystem = lookupValue(field.value, CSV_SCHOOL_SYSTEM_LOOKUP, "");
        }
        if (["niveau", "level"].includes(field.key)) {
          imported.meta.schoolLevel = lookupSchoolLevel(field.value, imported.meta.schoolSystem);
        }
        if (field.key === "taille du groupe") imported.meta.sizeClass = field.value;
        if (field.key === "concepteur(s)") imported.meta.designers = field.value;
        if (field.key === "enseignant(s)") imported.meta.trainers = field.value;
        if (["duree prevue", "temps d'apprentissage"].includes(field.key)) {
          const learningTime = parseCsvPedagogicalTime(field.value, imported.meta.dayHours);
          imported.meta.learningDays = learningTime.days;
          imported.meta.learningHours = learningTime.hours;
          imported.meta.learningMinutes = learningTime.minutes;
        }
        index += 1;
        continue;
      }

      const settingSection = line.match(/^###\s+(.+)$/);
      if (settingSection) {
        const title = normalizeToken(settingSection[1]);
        if (title === "description" || title === "commande institutionnelle" || title === "objectifs") {
          const block = readMarkdownBlock(lines, index + 1);
          if (title === "description") imported.meta.description = block.value;
          if (title === "commande institutionnelle") imported.meta.command = block.value;
          if (title === "objectifs") imported.meta.personas = block.value;
          index = block.nextIndex;
          continue;
        }
        if (title === "acquis d'apprentissage") {
          const outcomes = [];
          index += 1;
          while (index < lines.length && !/^#{2,3}\s+/.test(lines[index])) {
            const outcome = String(lines[index] || "").match(/^-\s*(.*)$/);
            if (outcome) {
              const text = outcome[1].trim();
              const parts = text.split(/\s+:\s+/);
              outcomes.push({
                id: nextId(),
                category: "",
                categoryLabel: "",
                verb: parts.length > 1 ? parts.shift().trim() : "",
                text: parts.join(" : ").trim() || text
              });
            }
            index += 1;
          }
          imported.meta.sliders = outcomes.filter((outcome) => outcome.verb || outcome.text);
          continue;
        }
      }
    }

    if (inSessions) {
      const sessionHeading = line.match(/^##\s+\d+\.\s*(.*)$/);
      if (sessionHeading) {
        pushCurrentSession();
        currentSession = {
          id: nextId(),
          title: cleanMarkdownExportValue(sessionHeading[1]) || defaultSessionTitle(imported.sessions.length + 1),
          objectives: "",
          intentions: "",
          notes: "",
          notesExpanded: false,
          activities: []
        };
        index += 1;
        continue;
      }

      const activityHeading = line.match(/^###\s+\d+\.\d+\s*(.*)$/);
      if (activityHeading && currentSession) {
        pushCurrentActivity();
        currentActivity = {
          id: nextId(),
          type: lookupValue(activityHeading[1], CSV_TYPE_LOOKUP, "undefined"),
          duration: 1,
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
        index += 1;
        continue;
      }

      if (currentSession && /^>\s*/.test(line)) {
        const label = normalizeToken(line.replace(/^>\s*/, "").replace(/:$/, ""));
        if (["objectifs", "choix pedagogiques", "notes"].includes(label)) {
          const quoteLines = [];
          index += 1;
          while (index < lines.length && /^>\s*/.test(lines[index])) {
            const nextQuoteLabel = normalizeToken(lines[index].replace(/^>\s*/, "").replace(/:$/, ""));
            if (["objectifs", "choix pedagogiques", "notes"].includes(nextQuoteLabel)) break;
            quoteLines.push(lines[index].replace(/^>\s?/, ""));
            index += 1;
          }
          const value = cleanMarkdownExportValue(quoteLines.join("\n"));
          if (label === "objectifs") currentSession.objectives = value;
          if (label === "choix pedagogiques") currentSession.intentions = value;
          if (label === "notes") currentSession.notes = value;
          continue;
        }
      }

      if (currentActivity) {
        const field = parseMarkdownFieldLine(line);
        if (field) {
          if (field.key === "duree") currentActivity.duration = Math.max(1, parseCsvInteger(field.value, 1));
          if (field.key === "groupe") currentActivity.groupMode = lookupValue(field.value, CSV_GROUP_LOOKUP, "whole");
          if (["enseignement", "teaching", "enseignant", "teacher"].includes(field.key)) {
            currentActivity.teachingMode = lookupValue(field.value, CSV_TEACHING_LOOKUP, "undefined");
          }
          if (field.key === "rythme") currentActivity.syncMode = lookupValue(field.value, CSV_SYNC_LOOKUP, "sync");
          if (["modalite", "mode de formation", "mode of delivery"].includes(field.key)) {
            currentActivity.locationMode = lookupValue(field.value, CSV_LOCATION_LOOKUP, "onsite");
          }
          if (field.key === "evaluation") currentActivity.evaluationMode = lookupValue(field.value, CSV_EVAL_LOOKUP, "none");
          if (field.key === "aias") currentActivity.aias = parseAiasValue(field.value);
          if (field.key === "description") {
            const descriptionLines = [field.value];
            index += 1;
            while (
              index < lines.length &&
              !/^- [^:]+:/.test(lines[index]) &&
              !/^#{2,3}\s+/.test(lines[index])
            ) {
              descriptionLines.push(lines[index]);
              index += 1;
            }
            currentActivity.description = cleanMarkdownExportValue(descriptionLines.join("\n"));
            continue;
          }
          if (["consignes", "consignes pour les eleves", "instructions", "instructions for students"].includes(field.key)) {
            const instructionLines = [field.value];
            index += 1;
            while (
              index < lines.length &&
              !/^- [^:]+:/.test(lines[index]) &&
              !/^#{2,3}\s+/.test(lines[index])
            ) {
              instructionLines.push(lines[index]);
              index += 1;
            }
            currentActivity.instructions = cleanMarkdownExportValue(instructionLines.join("\n"));
            continue;
          }
          if (field.key === "liens") currentActivity.links = parseMarkdownLinks(field.value);
          if (field.key === "competences") {
            currentActivity.tools = field.value.split(",").map((item) => item.trim()).filter(Boolean);
          }
          index += 1;
          continue;
        }
      }
    }

    index += 1;
  }

  pushCurrentSession();
  if (!imported.sessions.length) return null;
  return hydrateState(imported, null);
}

return {
  CSV_SCHOOL_SYSTEM_LOOKUP, lookupSchoolLevel, lookupValue, isLegacyLdjDocument,
  buildStateFromLegacyLdj, buildStateFromCsv, buildStateFromMarkdown
};
};
})();
