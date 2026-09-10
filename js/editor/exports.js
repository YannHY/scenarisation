// Génération des exports Markdown, HTML, Word et Excel.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createExports = ({
  escapeHtml, getState, totalSessionMinutes, splitMinutesToPedagogicalTime, getDayHours,
  labelForDeliveryMode, labelForSchoolSystem, labelForSchoolLevel, labelForType,
  labelForGroupMode, labelForTeachingMode, labelForSyncMode, labelForLocationMode,
  labelForEvaluationMode, aiasSummary, SELECTABLE_TOOLS_DATA, formatCompetencyLabel,
  defaultSessionTitle, slidersToString, formatCompetencyShortCode
}) => {
// Lire le document courant à chaque appel : il peut être remplacé par un import.
function escapeHtmlWithBreaks(value) {
  return escapeHtml(value).replaceAll("\n", "<br />");
}

function escapeXml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");
}

function formatPedagogicalTime(days, hours, minutes) {
  return `${Math.max(0, Number(days) || 0)} j ${Math.max(0, Number(hours) || 0)} h ${Math.max(
    0,
    Number(minutes) || 0
  )} min`;
}

function markdownQuoteBlock(text) {
  return String(text)
    .split("\n")
    .map((line) => `> ${line}`)
    .join("\n");
}

function normalizeExportScope(scope = "full") {
  return String(scope).toLowerCase() === "students" ? "students" : "full";
}

function exportSessionKey(session) {
  return String(session?.id || "");
}

function getExportSessionEntries(sessionIds = null) {
  const selectedIds = sessionIds === null
    ? null
    : sessionIds instanceof Set
      ? sessionIds
      : new Set(Array.from(sessionIds || [], String));
  return getState().sessions
    .map((session, sessionIndex) => ({ session, sessionIndex }))
    .filter(({ session }) => selectedIds === null || selectedIds.has(exportSessionKey(session)));
}

function totalExportDesignedMinutes(sessionIds = null) {
  return getExportSessionEntries(sessionIds).reduce(
    (sessionAcc, { session }) => sessionAcc + totalSessionMinutes(session),
    0
  );
}


function buildStudentInstructionsData(sessionIds = null) {
  return {
    exportType: "student_instructions",
    title: getState().meta.name || "Scénario pédagogique",
    sessions: getExportSessionEntries(sessionIds).map(({ session, sessionIndex }) => ({
      number: sessionIndex + 1,
      title: session.title || `Séance ${sessionIndex + 1}`,
      activities: session.activities.map((activity, activityIndex) => ({
        number: `${sessionIndex + 1}.${activityIndex + 1}`,
        instructions: activity.instructions || ""
      }))
    }))
  };
}

function buildStudentMarkdownExport(sessionIds = null) {
  const studentExport = buildStudentInstructionsData(sessionIds);
  const lines = [`# ${studentExport.title}`, "", "## Consignes pour les élèves", ""];
  studentExport.sessions.forEach((session) => {
    lines.push(`## ${session.number}. ${session.title}`);
    lines.push("");
    session.activities.forEach((activity) => {
      lines.push(`### Activité ${activity.number}`);
      lines.push(activity.instructions || "-");
      lines.push("");
    });
  });
  return lines.join("\n");
}

function buildMarkdownExport(scope = "full", sessionIds = null) {
  if (normalizeExportScope(scope) === "students") return buildStudentMarkdownExport(sessionIds);
  const designed = splitMinutesToPedagogicalTime(totalExportDesignedMinutes(sessionIds), getDayHours());
  const lines = [`# ${getState().meta.name || "Scénario pédagogique"}`, "", "## Paramètres", ""];
  lines.push(`- Mode: ${labelForDeliveryMode(getState().meta.modeDelivery)}`);
  lines.push(`- Système scolaire: ${labelForSchoolSystem(getState().meta.schoolSystem)}`);
  lines.push(`- Niveau: ${labelForSchoolLevel(getState().meta.schoolLevel)}`);
  lines.push(`- Taille du groupe: ${getState().meta.sizeClass || "-"}`);
  lines.push(`- Concepteur(s): ${getState().meta.designers || "-"}`);
  lines.push(`- Enseignant(s): ${getState().meta.trainers || "-"}`);
  lines.push(
    `- Durée prévue: ${formatPedagogicalTime(
      getState().meta.learningDays,
      getState().meta.learningHours,
      getState().meta.learningMinutes
    )}`
  );
  lines.push(
    `- Durée conçue: ${formatPedagogicalTime(designed.days, designed.hours, designed.minutes)}`
  );
  lines.push(`- 1 jour = ${getDayHours()} heures`);
  lines.push("");
  if (getState().meta.description) {
    lines.push("### Description");
    lines.push(getState().meta.description);
    lines.push("");
  }
  if (getState().meta.command) {
    lines.push("### Commande institutionnelle");
    lines.push(getState().meta.command);
    lines.push("");
  }
  if (getState().meta.personas) {
    lines.push("### Objectifs");
    lines.push(getState().meta.personas);
    lines.push("");
  }
  if (Array.isArray(getState().meta.sliders) && getState().meta.sliders.length) {
    lines.push("### Acquis d'apprentissage");
    getState().meta.sliders.forEach((o) => {
      const label = o.verb || o.categoryLabel || "";
      lines.push(`- ${label}${label && o.text ? " : " : ""}${o.text || ""}`);
    });
    lines.push("");
  }
  lines.push("## Séances");
  lines.push("");

  getExportSessionEntries(sessionIds).forEach(({ session, sessionIndex }) => {
    lines.push(`## ${sessionIndex + 1}. ${session.title}`);
    if (session.objectives) {
      lines.push("> Objectifs:");
      lines.push(markdownQuoteBlock(session.objectives));
    }
    if (session.intentions) {
      lines.push("> Choix pédagogiques:");
      lines.push(markdownQuoteBlock(session.intentions));
    }
    if (session.notes) {
      lines.push("> Notes:");
      lines.push(markdownQuoteBlock(session.notes));
    }
    lines.push("");
    session.activities.forEach((activity, activityIndex) => {
      lines.push(`### ${sessionIndex + 1}.${activityIndex + 1} ${labelForType(activity.type)}`);
      lines.push(`- Durée: ${activity.duration} min`);
      lines.push(`- Groupe: ${labelForGroupMode(activity.groupMode)}`);
      lines.push(`- Enseignement: ${labelForTeachingMode(activity.teachingMode)}`);
      lines.push(`- Rythme: ${labelForSyncMode(activity.syncMode)}`);
      lines.push(`- Modalité: ${labelForLocationMode(activity.locationMode)}`);
      lines.push(`- Évaluation: ${labelForEvaluationMode(activity.evaluationMode)}`);
      lines.push(`- AIAS: ${aiasSummary(activity.aias)}`);
      lines.push(`- Description: ${activity.description || "-"}`);
      lines.push(`- Consignes pour les élèves: ${activity.instructions || "-"}`);
      if (activity.tools && activity.tools.length) {
        const toolLabels = activity.tools
          .map(id => SELECTABLE_TOOLS_DATA.find(t => t.id === id))
          .filter(Boolean)
          .map(t => formatCompetencyLabel(t, "fr"))
          .join(", ");
        lines.push(`- Compétences: ${toolLabels}`);
      }
      lines.push("");
    });
  });
  return lines.join("\n");
}

function buildStudentHtmlExportDocument(sessionIds = null) {
  const studentExport = buildStudentInstructionsData(sessionIds);
  const sections = studentExport.sessions
    .map((session) => {
      const activities = session.activities
        .map((activity) => `
          <li>
            <h3>Activité ${escapeHtml(activity.number)}</h3>
            <div class="instructions">${escapeHtmlWithBreaks(activity.instructions || "-")}</div>
          </li>`)
        .join("");
      return `
      <section>
        <h2>${session.number}. ${escapeHtml(session.title)}</h2>
        <ol>${activities}</ol>
      </section>`;
    })
    .join("");

  return `<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Consignes élèves — ${escapeHtml(studentExport.title)}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; line-height: 1.5; color: #1f2937; }
    h1, h2, h3 { margin-bottom: 8px; }
    section { margin-bottom: 24px; }
    ol { padding-left: 24px; }
    li { margin-bottom: 18px; }
    .instructions { white-space: normal; }
  </style>
</head>
<body>
  <h1>${escapeHtml(studentExport.title)}</h1>
  <h2>Consignes pour les élèves</h2>
  ${sections}
</body>
</html>`;
}

function buildHtmlExportDocument(scope = "full", sessionIds = null) {
  if (normalizeExportScope(scope) === "students") return buildStudentHtmlExportDocument(sessionIds);
  const designed = splitMinutesToPedagogicalTime(totalExportDesignedMinutes(sessionIds), getDayHours());
  const sections = getExportSessionEntries(sessionIds)
    .map(({ session, sessionIndex }) => {
      const activities = session.activities
        .map(
          (activity, activityIndex) => `
          <li>
            <h4>${sessionIndex + 1}.${activityIndex + 1} ${escapeHtml(labelForType(activity.type))}</h4>
            <p><strong>Durée:</strong> ${escapeHtml(activity.duration)} min</p>
            <p><strong>Groupe:</strong> ${escapeHtml(labelForGroupMode(activity.groupMode))}</p>
            <p><strong>Enseignement:</strong> ${escapeHtml(labelForTeachingMode(activity.teachingMode))}</p>
            <p><strong>Rythme:</strong> ${escapeHtml(labelForSyncMode(activity.syncMode))}</p>
            <p><strong>Modalité:</strong> ${escapeHtml(labelForLocationMode(activity.locationMode))}</p>
            <p><strong>Évaluation:</strong> ${escapeHtml(labelForEvaluationMode(activity.evaluationMode))}</p>
            <p><strong>AIAS:</strong> ${escapeHtml(aiasSummary(activity.aias))}</p>
            <p><strong>Description:</strong> ${escapeHtmlWithBreaks(activity.description || "")}</p>
            <p><strong>Consignes pour les élèves:</strong> ${escapeHtmlWithBreaks(activity.instructions || "")}</p>
            ${activity.tools && activity.tools.length ? `<p><strong>Compétences:</strong> ${escapeHtml(activity.tools.map(id => { const t = SELECTABLE_TOOLS_DATA.find(x => x.id === id); return t ? formatCompetencyLabel(t, "fr") : id; }).join(", "))}</p>` : ""}
          </li>
        `
        )
        .join("");
      return `
      <section>
        <h2>${sessionIndex + 1}. ${escapeHtml(session.title)}</h2>
        ${session.objectives ? `<p><strong>Objectifs:</strong><br />${escapeHtmlWithBreaks(session.objectives)}</p>` : ""}
        ${session.intentions ? `<p><strong>Choix pédagogiques:</strong><br />${escapeHtmlWithBreaks(session.intentions)}</p>` : ""}
        ${session.notes ? `<p><strong>Notes:</strong> ${escapeHtml(session.notes)}</p>` : ""}
        <ol>${activities}</ol>
      </section>
    `;
    })
    .join("");

  const metadata = `
  <section>
    <h2>Paramètres</h2>
    <p><strong>Mode:</strong> ${escapeHtml(labelForDeliveryMode(getState().meta.modeDelivery))}</p>
    <p><strong>Système scolaire:</strong> ${escapeHtml(labelForSchoolSystem(getState().meta.schoolSystem))}</p>
    <p><strong>Niveau:</strong> ${escapeHtml(labelForSchoolLevel(getState().meta.schoolLevel))}</p>
    <p><strong>Taille du groupe:</strong> ${escapeHtml(getState().meta.sizeClass || "-")}</p>
    <p><strong>Concepteur(s):</strong> ${escapeHtml(getState().meta.designers || "-")}</p>
    <p><strong>Enseignant(s):</strong> ${escapeHtml(getState().meta.trainers || "-")}</p>
    <p><strong>Durée prévue:</strong> ${escapeHtml(
      formatPedagogicalTime(
        getState().meta.learningDays,
        getState().meta.learningHours,
        getState().meta.learningMinutes
      )
    )}</p>
    <p><strong>Durée conçue:</strong> ${escapeHtml(
      formatPedagogicalTime(designed.days, designed.hours, designed.minutes)
    )}</p>
    <p><strong>1 jour =</strong> ${escapeHtml(getDayHours())} heures</p>
    ${
      getState().meta.description
        ? `<p><strong>Description:</strong><br />${escapeHtmlWithBreaks(getState().meta.description)}</p>`
        : ""
    }
    ${
      getState().meta.command
        ? `<p><strong>Commande institutionnelle:</strong><br />${escapeHtmlWithBreaks(
            getState().meta.command
          )}</p>`
        : ""
    }
    ${
      getState().meta.personas
        ? `<p><strong>Objectifs:</strong><br />${escapeHtmlWithBreaks(
            getState().meta.personas
          )}</p>`
        : ""
    }
    ${
      Array.isArray(getState().meta.sliders) && getState().meta.sliders.length
        ? `<p><strong>Acquis d'apprentissage :</strong></p><ul>${getState().meta.sliders.map((o) => {
            const label = o.verb || o.categoryLabel || "";
            return `<li>${label ? `<strong>${escapeHtml(label)}</strong> : ` : ""}${escapeHtmlWithBreaks(o.text || "")}</li>`;
          }).join("")}</ul>`
        : ""
    }
  </section>`;

  return `<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Export Scenarisation</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; line-height: 1.4; }
    h1, h2, h4 { margin-bottom: 8px; }
    section { margin-bottom: 24px; }
    p { margin: 3px 0; }
    ol { padding-left: 20px; }
    li { margin-bottom: 10px; }
  </style>
</head>
<body>
  <h1>${escapeHtml(getState().meta.name || "Scénario pédagogique")}</h1>
  ${metadata}
  ${sections}
</body>
</html>`;
}

function createCrc32Table() {
  const table = new Uint32Array(256);
  for (let i = 0; i < 256; i += 1) {
    let c = i;
    for (let k = 0; k < 8; k += 1) {
      c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
    }
    table[i] = c >>> 0;
  }
  return table;
}

const CRC32_TABLE = createCrc32Table();

function crc32(bytes) {
  let crc = 0xffffffff;
  bytes.forEach((byte) => {
    crc = CRC32_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  });
  return (crc ^ 0xffffffff) >>> 0;
}

function writeUint16(target, offset, value) {
  target[offset] = value & 0xff;
  target[offset + 1] = (value >>> 8) & 0xff;
}

function writeUint32(target, offset, value) {
  target[offset] = value & 0xff;
  target[offset + 1] = (value >>> 8) & 0xff;
  target[offset + 2] = (value >>> 16) & 0xff;
  target[offset + 3] = (value >>> 24) & 0xff;
}

function concatUint8Arrays(parts) {
  const totalLength = parts.reduce((sum, part) => sum + part.length, 0);
  const output = new Uint8Array(totalLength);
  let offset = 0;
  parts.forEach((part) => {
    output.set(part, offset);
    offset += part.length;
  });
  return output;
}

function createZipArchive(files) {
  const encoder = new TextEncoder();
  const localParts = [];
  const centralParts = [];
  let offset = 0;

  files.forEach((file) => {
    const nameBytes = encoder.encode(file.name);
    const dataBytes = typeof file.content === "string" ? encoder.encode(file.content) : file.content;
    const checksum = crc32(dataBytes);

    const localHeader = new Uint8Array(30 + nameBytes.length);
    writeUint32(localHeader, 0, 0x04034b50);
    writeUint16(localHeader, 4, 20);
    writeUint16(localHeader, 6, 0);
    writeUint16(localHeader, 8, 0);
    writeUint16(localHeader, 10, 0);
    writeUint16(localHeader, 12, 0);
    writeUint32(localHeader, 14, checksum);
    writeUint32(localHeader, 18, dataBytes.length);
    writeUint32(localHeader, 22, dataBytes.length);
    writeUint16(localHeader, 26, nameBytes.length);
    writeUint16(localHeader, 28, 0);
    localHeader.set(nameBytes, 30);
    localParts.push(localHeader, dataBytes);

    const centralHeader = new Uint8Array(46 + nameBytes.length);
    writeUint32(centralHeader, 0, 0x02014b50);
    writeUint16(centralHeader, 4, 20);
    writeUint16(centralHeader, 6, 20);
    writeUint16(centralHeader, 8, 0);
    writeUint16(centralHeader, 10, 0);
    writeUint16(centralHeader, 12, 0);
    writeUint16(centralHeader, 14, 0);
    writeUint32(centralHeader, 16, checksum);
    writeUint32(centralHeader, 20, dataBytes.length);
    writeUint32(centralHeader, 24, dataBytes.length);
    writeUint16(centralHeader, 28, nameBytes.length);
    writeUint16(centralHeader, 30, 0);
    writeUint16(centralHeader, 32, 0);
    writeUint16(centralHeader, 34, 0);
    writeUint16(centralHeader, 36, 0);
    writeUint32(centralHeader, 38, 0);
    writeUint32(centralHeader, 42, offset);
    centralHeader.set(nameBytes, 46);
    centralParts.push(centralHeader);

    offset += localHeader.length + dataBytes.length;
  });

  const centralDirectory = concatUint8Arrays(centralParts);
  const endRecord = new Uint8Array(22);
  writeUint32(endRecord, 0, 0x06054b50);
  writeUint16(endRecord, 8, files.length);
  writeUint16(endRecord, 10, files.length);
  writeUint32(endRecord, 12, centralDirectory.length);
  writeUint32(endRecord, 16, offset);
  writeUint16(endRecord, 20, 0);

  return concatUint8Arrays([...localParts, centralDirectory, endRecord]);
}

let wordListNumberingInstances = [];
let wordNextListNumberId = 1;

function resetWordNumbering() {
  wordListNumberingInstances = [];
  wordNextListNumberId = 1;
}

function registerWordList(ordered = false) {
  const numId = wordNextListNumberId;
  wordNextListNumberId += 1;
  wordListNumberingInstances.push({ numId, abstractNumId: ordered ? 1 : 0 });
  return numId;
}

function wordRun(value, options = {}) {
  const properties = [];
  if (options.bold) properties.push("<w:b/>");
  if (options.italic) properties.push("<w:i/>");
  if (options.hyperlink) {
    properties.push('<w:color w:val="0563C1"/>');
    properties.push('<w:u w:val="single"/>');
  }
  const runProperties = properties.length ? `<w:rPr>${properties.join("")}</w:rPr>` : "";
  return `<w:r>${runProperties}<w:t xml:space="preserve">${escapeXml(value)}</w:t></w:r>`;
}

function wordPlainTextRuns(value, options = {}) {
  return String(value ?? "")
    .split("\n")
    .map((line, index) => `${index ? "<w:r><w:br/></w:r>" : ""}${line ? wordRun(line, options) : ""}`)
    .join("");
}

function wordHyperlink(label, url, options = {}) {
  const safeUrl = String(url || "").replaceAll('"', "%22");
  const instruction = escapeXml(`HYPERLINK "${safeUrl}"`);
  return `<w:fldSimple w:instr="${instruction}">${wordPlainTextRuns(label, { ...options, hyperlink: true })}</w:fldSimple>`;
}

function wordInlineRuns(value, options = {}, depth = 0) {
  const source = String(value ?? "");
  if (!source || depth > 4) return wordPlainTextRuns(source, options);

  const tokenPattern = /\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:)[^\s)<]+)\)|\*\*([^*\n]+)\*\*|\*([^*\n]+)\*/gi;
  const runs = [];
  let cursor = 0;
  let match;

  while ((match = tokenPattern.exec(source))) {
    if (match.index > cursor) runs.push(wordPlainTextRuns(source.slice(cursor, match.index), options));
    if (match[1] !== undefined) {
      runs.push(wordHyperlink(match[1], match[2], options));
    } else if (match[3] !== undefined) {
      runs.push(wordInlineRuns(match[3], { ...options, bold: true }, depth + 1));
    } else {
      runs.push(wordInlineRuns(match[4], { ...options, italic: true }, depth + 1));
    }
    cursor = tokenPattern.lastIndex;
  }

  if (!runs.length) return wordPlainTextRuns(source, options);
  if (cursor < source.length) runs.push(wordPlainTextRuns(source.slice(cursor), options));
  return runs.join("");
}

function wordParagraph(text, style = "", options = {}) {
  const properties = [];
  if (style) properties.push(`<w:pStyle w:val="${style}"/>`);
  if (options.spacingBefore || options.spacingAfter) {
    properties.push(
      `<w:spacing${options.spacingBefore ? ` w:before="${options.spacingBefore}"` : ""}${options.spacingAfter ? ` w:after="${options.spacingAfter}"` : ""}/>`
    );
  }
  if (options.numId) {
    properties.push(`<w:numPr><w:ilvl w:val="${Math.max(0, Math.min(8, Number(options.numLevel) || 0))}"/><w:numId w:val="${options.numId}"/></w:numPr>`);
  }
  if (options.indentLeft || options.indentRight) {
    properties.push(`<w:ind${options.indentLeft ? ` w:left="${options.indentLeft}"` : ""}${options.indentRight ? ` w:right="${options.indentRight}"` : ""}/>`);
  }
  if (options.align) properties.push(`<w:jc w:val="${options.align}"/>`);
  if (options.keepNext) properties.push("<w:keepNext/>");
  if (options.shading) properties.push(`<w:shd w:fill="${options.shading}"/>`);
  const paragraphProperties = properties.length ? `<w:pPr>${properties.join("")}</w:pPr>` : "";
  return `<w:p>${paragraphProperties}${wordInlineRuns(text, { bold: Boolean(options.bold), italic: Boolean(options.italic) })}</w:p>`;
}

function wordMarkdownBlocks(value, options = {}) {
  const defaultStyle = options.style || "BodyText";
  const tableMode = Boolean(options.tableMode);
  const lines = String(value ?? "").replace(/\r\n?/g, "\n").split("\n");
  const paragraphs = [];
  let paragraphLines = [];
  let activeList = null;

  const closeList = () => {
    activeList = null;
  };
  const flushParagraph = () => {
    if (!paragraphLines.length) return;
    paragraphs.push(wordParagraph(paragraphLines.join("\n"), defaultStyle));
    paragraphLines = [];
  };

  lines.forEach((line) => {
    const trimmed = line.trim();
    if (!trimmed) {
      flushParagraph();
      closeList();
      return;
    }

    const heading = trimmed.match(/^(#{1,6})\s+(.+)$/);
    if (heading) {
      flushParagraph();
      closeList();
      const headingStyle = tableMode
        ? "TableHeading"
        : heading[1].length === 1
          ? "Heading1"
          : heading[1].length === 2
            ? "Heading2"
            : "Heading3";
      paragraphs.push(wordParagraph(heading[2], headingStyle));
      return;
    }

    const unordered = line.match(/^(\s*)[-*+]\s+(.+)$/);
    const ordered = line.match(/^(\s*)\d+[.)]\s+(.+)$/);
    if (unordered || ordered) {
      flushParagraph();
      const isOrdered = Boolean(ordered);
      if (!activeList || activeList.ordered !== isOrdered) {
        activeList = { ordered: isOrdered, numId: registerWordList(isOrdered) };
      }
      const match = ordered || unordered;
      const indentation = match[1].replaceAll("\t", "  ").length;
      paragraphs.push(wordParagraph(match[2], tableMode ? "TableText" : "ListParagraph", {
        numId: activeList.numId,
        numLevel: Math.floor(indentation / 2)
      }));
      return;
    }

    const quote = trimmed.match(/^>\s?(.*)$/);
    if (quote) {
      flushParagraph();
      closeList();
      paragraphs.push(wordParagraph(quote[1], tableMode ? "TableQuote" : "Quote"));
      return;
    }

    closeList();
    paragraphLines.push(line);
  });

  flushParagraph();
  return paragraphs.length ? paragraphs.join("") : wordParagraph("", defaultStyle);
}

function wordSpacer(size = 160) {
  return `<w:p><w:pPr><w:spacing w:after="${size}"/></w:pPr></w:p>`;
}

function wordTableCell(content, options = {}) {
  const width = options.width || 4500;
  const shading = options.shading ? `<w:shd w:fill="${options.shading}"/>` : "";
  const text = Array.isArray(content) ? content.join("\n") : content;
  const cellContent = options.markdown
    ? wordMarkdownBlocks(text, { style: options.style || "TableText", tableMode: true })
    : wordParagraph(text, options.style || "TableText", { bold: Boolean(options.bold) });
  return `<w:tc><w:tcPr><w:tcW w:w="${width}" w:type="dxa"/>${shading}</w:tcPr>${cellContent}</w:tc>`;
}

function wordTable(rows, widths = []) {
  const tableRows = rows
    .map((row) => {
      const cells = row
        .map((cell, cellIndex) =>
          wordTableCell(cell.text ?? cell, {
            width: widths[cellIndex] || 4500,
            shading: cell.shading,
            bold: cell.bold,
            style: cell.style,
            markdown: cell.markdown
          })
        )
        .join("");
      return `<w:tr>${cells}</w:tr>`;
    })
    .join("");
  return `<w:tbl>
    <w:tblPr>
      <w:tblW w:w="0" w:type="auto"/>
      <w:tblBorders>
        <w:top w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
        <w:left w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
        <w:bottom w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
        <w:right w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
        <w:insideH w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
        <w:insideV w:val="single" w:sz="6" w:space="0" w:color="D8DEE9"/>
      </w:tblBorders>
      <w:tblCellMar>
        <w:top w:w="90" w:type="dxa"/>
        <w:left w:w="120" w:type="dxa"/>
        <w:bottom w:w="90" w:type="dxa"/>
        <w:right w:w="120" w:type="dxa"/>
      </w:tblCellMar>
    </w:tblPr>
    ${tableRows}
  </w:tbl>`;
}

function wordFieldTable(rows) {
  return wordTable(
    rows.map(([label, value]) => [
      { text: label, bold: true, shading: "EEF2F7" },
      { text: value || "-", markdown: true }
    ]),
    [3000, 6300]
  );
}

function buildStudentWordBody(sessionIds = null) {
  const body = [];
  const studentExport = buildStudentInstructionsData(sessionIds);
  body.push(wordParagraph(studentExport.title, "Title"));
  body.push(wordParagraph("Consignes pour les élèves", "Heading1"));
  studentExport.sessions.forEach((session) => {
    body.push(wordParagraph(`${session.number}. ${session.title}`, "Heading2"));
    session.activities.forEach((activity) => {
      body.push(wordParagraph(`Activité ${activity.number}`, "Heading3"));
      body.push(wordMarkdownBlocks(activity.instructions || "-", { style: "BodyText" }));
      body.push(wordSpacer(90));
    });
  });
  return body;
}

function buildFullWordBody(sessionIds = null) {
  const designed = splitMinutesToPedagogicalTime(totalExportDesignedMinutes(sessionIds), getDayHours());
  const body = [];
  body.push(wordParagraph(getState().meta.name || "Scénario pédagogique", "Title"));
  body.push(wordParagraph("Paramètres", "Heading1"));
  body.push(wordFieldTable([
    ["Mode", labelForDeliveryMode(getState().meta.modeDelivery)],
    ["Système scolaire", labelForSchoolSystem(getState().meta.schoolSystem)],
    ["Niveau", labelForSchoolLevel(getState().meta.schoolLevel)],
    ["Taille du groupe", getState().meta.sizeClass || "-"],
    ["Concepteur(s)", getState().meta.designers || "-"],
    ["Enseignant(s)", getState().meta.trainers || "-"],
    [
      "Durée prévue",
      formatPedagogicalTime(
        getState().meta.learningDays,
        getState().meta.learningHours,
        getState().meta.learningMinutes
      )
    ],
    ["Durée conçue", formatPedagogicalTime(designed.days, designed.hours, designed.minutes)],
    ["1 jour", `${getDayHours()} heures`]
  ]));
  body.push(wordSpacer(120));
  if (getState().meta.description) {
    body.push(wordParagraph("Description", "Heading2"));
    body.push(wordMarkdownBlocks(getState().meta.description, { style: "BodyText" }));
    body.push(wordSpacer(80));
  }
  if (getState().meta.command) {
    body.push(wordParagraph("Commande institutionnelle", "Heading2"));
    body.push(wordMarkdownBlocks(getState().meta.command, { style: "BodyText" }));
    body.push(wordSpacer(80));
  }
  if (getState().meta.personas) {
    body.push(wordParagraph("Objectifs", "Heading2"));
    body.push(wordMarkdownBlocks(getState().meta.personas, { style: "BodyText" }));
    body.push(wordSpacer(80));
  }
  if (Array.isArray(getState().meta.sliders) && getState().meta.sliders.length) {
    body.push(wordParagraph("Acquis d'apprentissage", "Heading2"));
    const outcomeListId = registerWordList(false);
    getState().meta.sliders.forEach((outcome) => {
      const label = outcome.verb || outcome.categoryLabel || "";
      body.push(wordParagraph(`${label}${label && outcome.text ? " : " : ""}${outcome.text || ""}`, "ListParagraph", { numId: outcomeListId }));
    });
    body.push(wordSpacer(100));
  }
  body.push(wordParagraph("Séances", "Heading1"));

  getExportSessionEntries(sessionIds).forEach(({ session, sessionIndex }) => {
    body.push(wordParagraph(`${sessionIndex + 1}. ${session.title || defaultSessionTitle(sessionIndex + 1)}`, "Heading2"));
    const sessionRows = [];
    if (session.objectives) sessionRows.push(["Objectifs", session.objectives]);
    if (session.intentions) sessionRows.push(["Choix pédagogiques", session.intentions]);
    if (session.notes) sessionRows.push(["Notes", session.notes]);
    if (sessionRows.length) {
      body.push(wordFieldTable(sessionRows));
      body.push(wordSpacer(80));
    }
    session.activities.forEach((activity, activityIndex) => {
      let toolLabels = "";
      if (activity.tools && activity.tools.length) {
        toolLabels = activity.tools
          .map((id) => SELECTABLE_TOOLS_DATA.find((tool) => tool.id === id))
          .filter(Boolean)
          .map((tool) => formatCompetencyLabel(tool, "fr"))
          .join(", ");
      }
      body.push(wordParagraph(`${sessionIndex + 1}.${activityIndex + 1} ${labelForType(activity.type)}`, "Heading3"));
      body.push(wordFieldTable([
        ["Durée", `${activity.duration} min`],
        ["Groupe", labelForGroupMode(activity.groupMode)],
        ["Enseignement", labelForTeachingMode(activity.teachingMode)],
        ["Rythme", labelForSyncMode(activity.syncMode)],
        ["Modalité", labelForLocationMode(activity.locationMode)],
        ["Évaluation", labelForEvaluationMode(activity.evaluationMode)],
        ["AIAS", aiasSummary(activity.aias)],
        ["Description", activity.description || "-"],
        ["Consignes pour les élèves", activity.instructions || "-"],
        ["Compétences", toolLabels || "-"]
      ]));
      body.push(wordSpacer(activityIndex === session.activities.length - 1 ? 150 : 70));
    });
  });
  return body;
}

function buildWordExportDocument(scope = "full", sessionIds = null) {
  resetWordNumbering();
  const body = normalizeExportScope(scope) === "students"
    ? buildStudentWordBody(sessionIds)
    : buildFullWordBody(sessionIds);

  const documentXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    ${body.join("\n    ")}
    <w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>
  </w:body>
</w:document>`;

  const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="21"/><w:color w:val="1F2937"/></w:rPr></w:rPrDefault>
    <w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:qFormat/><w:pPr><w:spacing w:after="340"/></w:pPr><w:rPr><w:b/><w:color w:val="123B6D"/><w:sz w:val="36"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:uiPriority w:val="9"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="420" w:after="180"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="145BB4"/><w:sz w:val="30"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:uiPriority w:val="9"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="300" w:after="140"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:color w:val="1F4D7A"/><w:sz w:val="25"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:uiPriority w:val="9"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="220" w:after="100"/><w:outlineLvl w:val="2"/></w:pPr><w:rPr><w:b/><w:color w:val="243B53"/><w:sz w:val="22"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="140"/></w:pPr><w:rPr><w:sz w:val="21"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0"/></w:pPr><w:rPr><w:sz w:val="19"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableHeading"><w:name w:val="Table Heading"/><w:basedOn w:val="TableText"/><w:next w:val="TableText"/><w:pPr><w:keepNext/><w:spacing w:before="100" w:after="50"/></w:pPr><w:rPr><w:b/><w:color w:val="1F4D7A"/><w:sz w:val="20"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="BodyText"/><w:pPr><w:spacing w:after="120"/><w:ind w:left="540" w:right="240"/><w:shd w:fill="F3F6FA"/></w:pPr><w:rPr><w:i/><w:color w:val="475569"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableQuote"><w:name w:val="Table Quote"/><w:basedOn w:val="TableText"/><w:pPr><w:spacing w:after="40"/><w:ind w:left="300" w:right="120"/><w:shd w:fill="F3F6FA"/></w:pPr><w:rPr><w:i/><w:color w:val="475569"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="90"/><w:ind w:left="720"/></w:pPr><w:rPr><w:sz w:val="21"/></w:rPr></w:style>
</w:styles>`;

  const numberingLevelXml = (level, ordered) => {
    const left = 720 + (level * 360);
    const bullets = ["•", "◦", "▪"];
    const levelText = ordered
      ? `${Array.from({ length: level + 1 }, (_, index) => `%${index + 1}`).join(".")}.`
      : bullets[level % bullets.length];
    return `<w:lvl w:ilvl="${level}">
      <w:start w:val="1"/>
      <w:numFmt w:val="${ordered ? "decimal" : "bullet"}"/>
      <w:lvlText w:val="${levelText}"/>
      <w:lvlJc w:val="left"/>
      <w:pPr><w:tabs><w:tab w:val="num" w:pos="${left}"/></w:tabs><w:ind w:left="${left}" w:hanging="360"/></w:pPr>
      ${ordered ? "" : '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/></w:rPr>'}
    </w:lvl>`;
  };
  const numberingXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>${Array.from({ length: 9 }, (_, level) => numberingLevelXml(level, false)).join("")}</w:abstractNum>
  <w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="multilevel"/>${Array.from({ length: 9 }, (_, level) => numberingLevelXml(level, true)).join("")}</w:abstractNum>
  ${wordListNumberingInstances.map(({ numId, abstractNumId }) => {
    const restartOverrides = abstractNumId === 1
      ? Array.from({ length: 9 }, (_, level) => `<w:lvlOverride w:ilvl="${level}"><w:startOverride w:val="1"/></w:lvlOverride>`).join("")
      : "";
    return `<w:num w:numId="${numId}"><w:abstractNumId w:val="${abstractNumId}"/>${restartOverrides}</w:num>`;
  }).join("")}
</w:numbering>`;

  return createZipArchive([
    {
      name: "[Content_Types].xml",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>`
    },
    {
      name: "_rels/.rels",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>`
    },
    {
      name: "word/_rels/document.xml.rels",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
</Relationships>`
    },
    { name: "word/document.xml", content: documentXml },
    { name: "word/styles.xml", content: stylesXml },
    { name: "word/numbering.xml", content: numberingXml }
  ]);
}

function buildStudentSpreadsheetRows(sessionIds = null) {
  const rows = [STUDENT_SPREADSHEET_COLUMNS.map((column) => column.label)];
  const studentExport = buildStudentInstructionsData(sessionIds);
  studentExport.sessions.forEach((session) => {
    if (!session.activities.length) {
      rows.push([studentExport.title, session.number, session.title, "", ""]);
      return;
    }
    session.activities.forEach((activity) => {
      rows.push([
        studentExport.title,
        session.number,
        session.title,
        activity.number,
        activity.instructions
      ]);
    });
  });
  return rows;
}

function buildSpreadsheetRows(scope = "full", sessionIds = null) {
  if (normalizeExportScope(scope) === "students") return buildStudentSpreadsheetRows(sessionIds);
  const designed = splitMinutesToPedagogicalTime(totalExportDesignedMinutes(sessionIds), getDayHours());
  const metaLearningTime = formatPedagogicalTime(
    getState().meta.learningDays,
    getState().meta.learningHours,
    getState().meta.learningMinutes
  );
  const metaDesignedTime = formatPedagogicalTime(designed.days, designed.hours, designed.minutes);
  const rows = [];
  rows.push(SPREADSHEET_COLUMNS.map((column) => column.label));

  getExportSessionEntries(sessionIds).forEach(({ session, sessionIndex }) => {
    if (!session.activities.length) {
      rows.push(
        [
          sessionIndex + 1,
          session.title || "",
          session.objectives || "",
          session.intentions || "",
          session.notes || "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          "",
          getState().meta.name || "",
          labelForDeliveryMode(getState().meta.modeDelivery),
          labelForSchoolSystem(getState().meta.schoolSystem),
          labelForSchoolLevel(getState().meta.schoolLevel),
          getState().meta.sizeClass || "",
          getState().meta.designers || "",
          getState().meta.trainers || "",
          metaLearningTime,
          metaDesignedTime,
          getDayHours(),
          getState().meta.description || "",
          getState().meta.command || "",
          getState().meta.personas || "",
          slidersToString(getState().meta.sliders)
        ]
      );
      return;
    }

    session.activities.forEach((activity, activityIndex) => {
      rows.push(
        [
          sessionIndex + 1,
          session.title || "",
          session.objectives || "",
          session.intentions || "",
          session.notes || "",
          activityIndex + 1,
          labelForType(activity.type),
          activity.duration,
          labelForGroupMode(activity.groupMode),
          labelForTeachingMode(activity.teachingMode),
          labelForSyncMode(activity.syncMode),
          labelForLocationMode(activity.locationMode),
          labelForEvaluationMode(activity.evaluationMode),
          aiasSummary(activity.aias),
          activity.description || "",
          activity.instructions || "",
          activity.notes || "",
          (activity.tools || [])
            .map((id) => {
              const tool = SELECTABLE_TOOLS_DATA.find((candidate) => candidate.id === id);
              return tool ? formatCompetencyShortCode(tool) : id;
            })
            .join(";"),
          getState().meta.name || "",
          labelForDeliveryMode(getState().meta.modeDelivery),
          labelForSchoolSystem(getState().meta.schoolSystem),
          labelForSchoolLevel(getState().meta.schoolLevel),
          getState().meta.sizeClass || "",
          getState().meta.designers || "",
          getState().meta.trainers || "",
          metaLearningTime,
          metaDesignedTime,
          getDayHours(),
          getState().meta.description || "",
          getState().meta.command || "",
          getState().meta.personas || "",
          slidersToString(getState().meta.sliders)
        ]
      );
    });
  });

  return rows;
}

const SPREADSHEET_COLUMNS = [
  { key: "session_index", label: "N° de séance", width: 12 },
  { key: "session_title", label: "Titre de la séance", width: 20 },
  { key: "session_objectives", label: "Objectifs de la séance", width: 34 },
  { key: "session_intentions", label: "Choix pédagogiques de la séance", width: 34 },
  { key: "session_notes", label: "Notes de la séance", width: 24 },
  { key: "activity_index", label: "N° d'activité", width: 12 },
  { key: "learning_type", label: "Type d'apprentissage", width: 18 },
  { key: "duration_minutes", label: "Durée (minutes)", width: 16 },
  { key: "group_size", label: "Organisation du groupe", width: 18 },
  { key: "teaching_mode", label: "Enseignement", width: 24 },
  { key: "pacing", label: "Rythme", width: 14 },
  { key: "delivery_mode", label: "Modalité", width: 22 },
  { key: "assessment", label: "Évaluation", width: 18 },
  { key: "aias", label: "AIAS", width: 28 },
  { key: "activity_description", label: "Description de l'activité", width: 34 },
  { key: "activity_instructions", label: "Consignes pour les élèves", width: 34 },
  { key: "activity_notes", label: "Notes de l'activité", width: 24 },
  { key: "activity_competencies", label: "Compétences", width: 22 },
  { key: "design_title", label: "Titre du scénario", width: 22 },
  { key: "design_mode", label: "Mode du scénario", width: 16 },
  { key: "design_school_system", label: "Système scolaire", width: 22 },
  { key: "design_level", label: "Niveau", width: 30 },
  { key: "design_group_size", label: "Taille du groupe", width: 16 },
  { key: "design_designers", label: "Concepteur(s)", width: 18 },
  { key: "design_trainers", label: "Enseignant(s)", width: 18 },
  { key: "design_learning_time", label: "Durée prévue", width: 22 },
  { key: "design_designed_time", label: "Durée conçue", width: 16 },
  { key: "design_day_hours", label: "Heures par jour", width: 14 },
  { key: "design_description", label: "Description du scénario", width: 30 },
  { key: "design_institutional_brief", label: "Commande institutionnelle", width: 30 },
  { key: "design_personas", label: "Personas", width: 26 },
  { key: "design_sliders", label: "Objectifs / curseurs", width: 26 }
];

const STUDENT_SPREADSHEET_COLUMNS = [
  { key: "design_title", label: "Titre du scénario", width: 28 },
  { key: "session_index", label: "N° de séance", width: 12 },
  { key: "session_title", label: "Titre de la séance", width: 24 },
  { key: "activity_index", label: "N° d’activité", width: 12 },
  { key: "activity_instructions", label: "Consignes pour les élèves", width: 60 }
];

function buildExcelExportDocument(scope = "full", sessionIds = null) {
  const normalizedScope = normalizeExportScope(scope);
  const columns = normalizedScope === "students" ? STUDENT_SPREADSHEET_COLUMNS : SPREADSHEET_COLUMNS;
  const rows = buildSpreadsheetRows(normalizedScope, sessionIds);
  const columnsXml = columns
    .map((column, index) => {
      const columnIndex = index + 1;
      return `<col min="${columnIndex}" max="${columnIndex}" width="${column.width}" customWidth="1"/>`;
    })
    .join("");
  const sheetRows = rows
    .map((row, rowIndex) => {
      const cells = row
        .map((cell, cellIndex) => {
          const reference = `${excelColumnName(cellIndex + 1)}${rowIndex + 1}`;
          return `<c r="${reference}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(cell)}</t></is></c>`;
        })
        .join("");
      return `<row r="${rowIndex + 1}">${cells}</row>`;
    })
    .join("");

  const sheetXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews><sheetView workbookViewId="0"/></sheetViews>
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>${columnsXml}</cols>
  <sheetData>${sheetRows}</sheetData>
</worksheet>`;

  const workbookXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="${normalizedScope === "students" ? "Consignes élèves" : "Scénario"}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>`;

  return createZipArchive([
    {
      name: "[Content_Types].xml",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>`
    },
    {
      name: "_rels/.rels",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`
    },
    {
      name: "xl/_rels/workbook.xml.rels",
      content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>`
    },
    { name: "xl/workbook.xml", content: workbookXml },
    { name: "xl/worksheets/sheet1.xml", content: sheetXml }
  ]);
}

function excelColumnName(index) {
  let column = "";
  let value = Math.max(1, Number(index) || 1);
  while (value > 0) {
    value -= 1;
    column = String.fromCharCode(65 + (value % 26)) + column;
    value = Math.floor(value / 26);
  }
  return column;
}

return {
  normalizeExportScope, exportSessionKey, getExportSessionEntries,
  buildStudentInstructionsData, buildMarkdownExport, buildHtmlExportDocument,
  buildWordExportDocument, SPREADSHEET_COLUMNS, buildExcelExportDocument
};
};
})();
