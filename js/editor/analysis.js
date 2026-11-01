// Calcul et affichage des répartitions et alertes pédagogiques.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createAnalysis = ({
  getState, normalizeAiasState, t, currentLang, normalizePedagogicalTime, getDayHours,
  TEACHING_VALUES, LOCATION_VALUES, LEARNING_TYPES, AIAS_LEVELS, downloadBlob, announce,
  createZipArchive
}) => {
// Lire le document courant à chaque appel : il peut être remplacé par un import.
function buildAnalysisMetrics() {
  const metrics = {
    activities: [],
    overall: 0,
    byType: {},
    byLocation: {},
    byTeaching: {},
    bySync: {},
    byEvaluation: {},
    byGroup: {},
    byAias: {}
  };
  let overall = 0;
  getState().sessions.forEach((session) => {
    session.activities.forEach((activity) => {
      const duration = Math.max(0, Number(activity.duration) || 0);
      const evaluationKey = activity.evaluationMode === "summative" || activity.evaluationMode === "certificative"
        ? "summative"
        : "formative";
      metrics.activities.push(activity);
      metrics.byType[activity.type] = (metrics.byType[activity.type] || 0) + duration;
      metrics.byLocation[activity.locationMode] = (metrics.byLocation[activity.locationMode] || 0) + duration;
      metrics.byTeaching[activity.teachingMode] = (metrics.byTeaching[activity.teachingMode] || 0) + duration;
      metrics.bySync[activity.syncMode] = (metrics.bySync[activity.syncMode] || 0) + duration;
      metrics.byEvaluation[evaluationKey] = (metrics.byEvaluation[evaluationKey] || 0) + duration;
      metrics.byGroup[activity.groupMode] = (metrics.byGroup[activity.groupMode] || 0) + duration;
      const normalizedAias = normalizeAiasState(activity.aias);
      const aiasKey = normalizedAias.status === "specified"
        ? `level_${normalizedAias.level}`
        : normalizedAias.status;
      metrics.byAias[aiasKey] = (metrics.byAias[aiasKey] || 0) + duration;
      overall += duration;
    });
  });
  metrics.overall = overall;
  return metrics;
}

function buildSegments(definitions, totals) {
  const sum = definitions.reduce((acc, def) => acc + Number(totals[def.key] || 0), 0);
  let cursor = 0;
  const segments = definitions.map((def) => {
    const value = Number(totals[def.key] || 0);
    const pct = sum > 0 ? (value / sum) * 100 : 0;
    const start = cursor;
    cursor += pct;
    return { ...def, value, pct, start, end: cursor };
  });
  return { sum, segments };
}

function buildAnalysisDatasets(metrics = buildAnalysisMetrics()) {
  const learningDefs = LEARNING_TYPES.map((type) => ({
    key: type.id,
    label: t(`lt_${type.id}`),
    color: type.color
  }));
  const deliveryDefs = [
    { key: "onsite", label: t("activityModeClassroom"), color: "#37658b" },
    { key: "location_based", label: t("activityModeLocation"), color: "#5b88a6" },
    { key: "online", label: t("activityModeOnline"), color: "#bcc7d7" },
    { key: "hybrid", label: t("activityModeBlended"), color: "#4e84c8" },
    { key: "other", label: t("activityModeOther"), color: "#94a3b8" }
  ];
  const teachingDefs = [
    { key: "directed", label: t("teaching_directed"), color: "#67513f" },
    { key: "guided", label: t("teaching_guided"), color: "#8b6f52" },
    { key: "supported", label: t("teaching_supported"), color: "#b09470" },
    { key: "independent", label: t("teaching_independent"), color: "#d2c2aa" },
    { key: "undefined", label: t("teaching_undefined"), color: "#d1d5db" }
  ];
  const syncDefs = [
    { key: "sync", label: t("sync_sync"), color: "#ac7f8d" },
    { key: "async", label: t("sync_async"), color: "#cbbec2" }
  ];
  const evalDefs = [
    { key: "formative", label: t("eval_formative"), color: "#ccd5aa" },
    { key: "summative", label: t("eval_summative"), color: "#b2cf69" }
  ];
  const groupDefs = [
    { key: "whole", label: t("group_whole"), color: "#4f7d5a" },
    { key: "subgroups", label: t("group_subgroups"), color: "#6ab084" },
    { key: "individual", label: t("group_individual"), color: "#a8c8b1" }
  ];
  const aiasColors = ["#64deff", "#c1ffd2", "#cfdeff", "#fffecb", "#ffc1ee"];
  const aiasDefs = [
    ...AIAS_LEVELS.map((definition) => ({
      key: `level_${definition.level}`,
      label: `${t("aiasLevelPrefix")} ${definition.level} · ${t(definition.labelKey)}`,
      color: aiasColors[definition.level - 1]
    })),
    { key: "undecided", label: t("aiasUndecided"), color: "#94a3b8" },
    { key: "not_applicable", label: t("aiasNotApplicable"), color: "#e2e8f0" }
  ];

  return [
    { key: "learning", title: t("groupTitleType"), data: buildSegments(learningDefs, metrics.byType) },
    { key: "delivery", title: t("groupTitleMode"), data: buildSegments(deliveryDefs, metrics.byLocation) },
    { key: "teaching", title: t("groupTitleTeaching"), data: buildSegments(teachingDefs, metrics.byTeaching) },
    { key: "pacing", title: t("groupTitlePacing"), data: buildSegments(syncDefs, metrics.bySync) },
    { key: "evaluation", title: t("groupTitleEvaluation"), data: buildSegments(evalDefs, metrics.byEvaluation) },
    { key: "group", title: t("groupTitleGroup"), data: buildSegments(groupDefs, metrics.byGroup) },
    { key: "aias", title: t("analysisAiasTitle"), data: buildSegments(aiasDefs, metrics.byAias) }
  ];
}

function chartSummary(data, emptyLabel = t("noData")) {
  if (!data || data.sum <= 0) return emptyLabel;
  return data.segments
    .filter((segment) => segment.pct > 0)
    .map((segment) => `${segment.label} ${Math.round(segment.pct)}%`)
    .join(", ");
}

function renderConic(el, data) {
  el.setAttribute("role", "img");
  if (data.sum <= 0) {
    el.classList.add("is-empty");
    el.style.background = "";
    el.style.removeProperty("--undefined-start");
    el.style.removeProperty("--undefined-end");
    el.setAttribute("aria-label", t("noData"));
    return;
  }
  el.classList.remove("is-empty");
  const parts = data.segments
    .filter((segment) => segment.pct > 0)
    .map((segment) => `${segment.color} ${segment.start}% ${segment.end}%`);
  el.style.background = `conic-gradient(${parts.join(", ")})`;
  const undefinedSegment = data.segments.find((segment) => segment.key === "undefined" && segment.pct > 0);
  el.style.setProperty("--undefined-start", `${undefinedSegment ? undefinedSegment.start : 0}%`);
  el.style.setProperty("--undefined-end", `${undefinedSegment ? undefinedSegment.end : 0}%`);
  el.setAttribute("aria-label", chartSummary(data));
}

/* "Non défini" is an unfilled ring, not a coloured dot: at 8px a hatch would
   be illegible, but an outline still reads as "nothing chosen yet". */
function legendDot(key, color) {
  if (key === "undefined") return `<span class="legend-dot legend-dot--undefined"></span>`;
  return `<span class="legend-dot" style="background:${color}"></span>`;
}

function renderLegend(container, data, showPct = true) {
  container.innerHTML = data.segments
    .filter((segment) => segment.pct > 0)
    .map((segment) => {
      const pct = showPct ? ` ${Math.round(segment.pct)}%` : "";
      return `<span class="legend-item">${legendDot(segment.key, segment.color)}${segment.label}${pct}</span>`;
    })
    .join("");
}

const LEARNING_PIE_CODES = {
  fr: {
    read: "Acq",
    collaborate: "Col",
    discuss: "Dis",
    investigate: "Inv",
    practice: "Pra",
    produce: "Pro"
  },
  en: {
    read: "Acq",
    collaborate: "Col",
    discuss: "Dis",
    investigate: "Inq",
    practice: "Pra",
    produce: "Pro"
  }
};

function learningPieCode(segmentKey) {
  return LEARNING_PIE_CODES[currentLang()]?.[segmentKey] || "–";
}

function hidePieTooltip(tooltipEl) {
  if (!tooltipEl) return;
  tooltipEl.classList.add("hidden");
  tooltipEl.setAttribute("aria-hidden", "true");
}

function showPieTooltip(wrapEl, pieEl, tooltipEl, segment) {
  if (!wrapEl || !pieEl || !tooltipEl || !segment) return;
  tooltipEl.textContent = "";
  tooltipEl.classList.remove("tooltip-below");
  const nameEl = document.createElement("span");
  nameEl.className = "pie-tooltip-name";
  nameEl.textContent = segment.label;
  const pctEl = document.createElement("span");
  pctEl.className = "pie-tooltip-pct";
  pctEl.textContent = `${Math.round(segment.pct)}%`;
  tooltipEl.append(nameEl, pctEl);
  tooltipEl.classList.remove("hidden");
  tooltipEl.setAttribute("aria-hidden", "false");

  const wrapRect = wrapEl.getBoundingClientRect();
  const pieRect = pieEl.getBoundingClientRect();
  const centerX = pieRect.left - wrapRect.left + pieRect.width / 2;
  const centerY = pieRect.top - wrapRect.top + pieRect.height / 2;
  const radius = Math.min(pieRect.width, pieRect.height) / 2;
  const middlePct = (segment.start + segment.end) / 2;
  const angle = ((middlePct / 100) * (Math.PI * 2)) - (Math.PI / 2);
  const anchorRadius = radius * 0.68;
  const anchorX = centerX + (Math.cos(angle) * anchorRadius);
  const anchorY = centerY + (Math.sin(angle) * anchorRadius);
  const tooltipWidth = tooltipEl.offsetWidth || 130;
  const tooltipHeight = tooltipEl.offsetHeight || 66;
  const left = Math.max(8, Math.min(anchorX - (tooltipWidth / 2), wrapRect.width - tooltipWidth - 8));
  const topAbove = anchorY - tooltipHeight - 14;
  const topBelow = anchorY + 14;
  const shouldPlaceBelow = topAbove < 8 && topBelow <= wrapRect.height - tooltipHeight - 8;
  const top = shouldPlaceBelow
    ? Math.max(8, Math.min(topBelow, wrapRect.height - tooltipHeight - 8))
    : Math.max(8, Math.min(topAbove, wrapRect.height - tooltipHeight - 8));
  const arrowLeft = Math.max(14, Math.min(tooltipWidth - 14, anchorX - left));
  tooltipEl.style.left = `${left}px`;
  tooltipEl.style.top = `${top}px`;
  tooltipEl.style.setProperty("--pie-tooltip-arrow", `${arrowLeft}px`);
  tooltipEl.classList.toggle("tooltip-below", shouldPlaceBelow);
}

function segmentForPointer(data, pieEl, event) {
  if (!data || data.sum <= 0 || !pieEl) return null;
  const rect = pieEl.getBoundingClientRect();
  const radius = Math.min(rect.width, rect.height) / 2;
  const dx = event.clientX - (rect.left + rect.width / 2);
  const dy = event.clientY - (rect.top + rect.height / 2);
  if ((dx * dx) + (dy * dy) > radius * radius) return null;
  let angle = (Math.atan2(dy, dx) * 180) / Math.PI + 90;
  if (angle < 0) angle += 360;
  const pct = (angle / 360) * 100;
  const segments = data.segments.filter((segment) => segment.pct > 0);
  return segments.find((segment, index) => {
    if (index === segments.length - 1) return pct >= segment.start && pct <= segment.end;
    return pct >= segment.start && pct < segment.end;
  }) || null;
}

function renderPieOuterLabels(wrapEl, pieEl, labelsEl, tooltipEl, data, codeForSegment) {
  if (!wrapEl || !pieEl || !labelsEl || !tooltipEl) return;
  hidePieTooltip(tooltipEl);
  labelsEl.innerHTML = "";
  // Cancel previous listeners via AbortController instead of property assignment.
  if (pieEl._pieAbort) pieEl._pieAbort.abort();
  const pieAbort = new AbortController();
  pieEl._pieAbort = pieAbort;
  const { signal } = pieAbort;

  if (!data || data.sum <= 0) return;

  const segments = data.segments.filter((segment) => segment.pct > 0);
  const labelsRect = labelsEl.getBoundingClientRect();
  const pieRect = pieEl.getBoundingClientRect();
  const pieWidth = pieRect.width || pieEl.clientWidth || pieEl.offsetWidth || 180;
  const pieHeight = pieRect.height || pieEl.clientHeight || pieEl.offsetHeight || pieWidth;
  const labelsWidth = labelsRect.width || labelsEl.clientWidth || (pieWidth + 48);
  const labelsHeight = labelsRect.height || labelsEl.clientHeight || (pieHeight + 48);
  const centerX = pieRect.width
    ? pieRect.left - labelsRect.left + pieRect.width / 2
    : labelsWidth / 2;
  const centerY = pieRect.height
    ? pieRect.top - labelsRect.top + pieRect.height / 2
    : labelsHeight / 2;
  const pieRadius = Math.min(pieWidth, pieHeight) / 2;
  const compactLabels = labelsWidth <= pieWidth + 110 || window.innerWidth <= 520;
  const labelInset = compactLabels ? 8 : 16;
  const labelGap = compactLabels ? 10 : 16;
  const labelRingGap = compactLabels ? 8 : 18;
  const positionedLabels = [];

  segments.forEach((segment) => {
    const label = document.createElement("span");
    label.className = "pie-outer-label";
    label.textContent = codeForSegment(segment);
    label.setAttribute("aria-hidden", "true");
    label.title = `${segment.label} ${Math.round(segment.pct)}%`;
    const angle = (((segment.start + segment.end) / 2) / 100) * (Math.PI * 2) - (Math.PI / 2);
    const ux = Math.cos(angle);
    const uy = Math.sin(angle);
    let align = "center";
    if (ux > 0.35) align = "right";
    else if (ux < -0.35) align = "left";
    else if (uy < 0) align = "top";
    else align = "bottom";
    labelsEl.appendChild(label);
    const labelWidth = label.offsetWidth || 24;
    const labelHeight = label.offsetHeight || 12;
    const halfWidth = labelWidth / 2;
    const halfHeight = labelHeight / 2;
    const radialDistance = pieRadius + labelRingGap;
    const x = centerX + ux * radialDistance;
    const y = centerY + uy * radialDistance;
    positionedLabels.push({
      label,
      segment,
      align,
      ux,
      uy,
      x,
      y,
      labelWidth,
      labelHeight,
      halfWidth,
      halfHeight
    });
  });

  ["left", "right"].forEach((align) => {
    const sideLabels = positionedLabels
      .filter((item) => item.align === align)
      .sort((a, b) => a.y - b.y);

    sideLabels.forEach((item, index) => {
      if (index === 0) return;
      const previous = sideLabels[index - 1];
      if (item.y - previous.y < labelGap) {
        item.y = previous.y + labelGap;
      }
    });
  });

  ["top", "bottom"].forEach((align) => {
    const sideLabels = positionedLabels
      .filter((item) => item.align === align)
      .sort((a, b) => a.x - b.x);

    sideLabels.forEach((item, index) => {
      if (index === 0) return;
      const previous = sideLabels[index - 1];
      const minGap = Math.max(labelGap, (previous.halfWidth + item.halfWidth) + 6);
      if (item.x - previous.x < minGap) {
        item.x = previous.x + minGap;
      }
    });
  });

  positionedLabels.forEach(({ label, segment, x, y, align, halfWidth, halfHeight }) => {
    const safeX = Math.max(labelInset + halfWidth, Math.min(x, labelsWidth - labelInset - halfWidth));
    const safeY = Math.max(labelInset + halfHeight, Math.min(y, labelsHeight - labelInset - halfHeight));
    label.style.left = `${safeX}px`;
    label.style.top = `${safeY}px`;
    if (align === "right") label.style.transform = "translate(0, -50%)";
    else if (align === "left") label.style.transform = "translate(-100%, -50%)";
    else if (align === "top") label.style.transform = "translate(-50%, -100%)";
    else label.style.transform = "translate(-50%, 0)";
    label.addEventListener("mouseenter", () => {
      showPieTooltip(wrapEl, pieEl, tooltipEl, segment);
    });
    label.addEventListener("mousemove", () => {
      showPieTooltip(wrapEl, pieEl, tooltipEl, segment);
    });
    label.addEventListener("mouseleave", () => {
      hidePieTooltip(tooltipEl);
    });
  });

  pieEl.addEventListener("mousemove", (event) => {
    const segment = segmentForPointer(data, pieEl, event);
    if (!segment) {
      hidePieTooltip(tooltipEl);
      return;
    }
    showPieTooltip(wrapEl, pieEl, tooltipEl, segment);
  }, { signal });
  pieEl.addEventListener("mouseleave", () => {
    hidePieTooltip(tooltipEl);
  }, { signal });
}

function renderSegmentedBar(element, data) {
  element.setAttribute("role", "img");
  if (data.sum <= 0) {
    element.classList.add("is-empty");
    element.style.background = "";
    element.setAttribute("aria-label", t("noData"));
    return;
  }
  element.classList.remove("is-empty");
  const parts = data.segments
    .filter((segment) => segment.pct > 0)
    .map((segment) => `${segment.color} ${segment.start}% ${segment.end}%`);
  element.style.background = `linear-gradient(90deg, ${parts.join(", ")})`;
  element.setAttribute("aria-label", chartSummary(data));
}

function totalDeclaredLearningMinutes() {
  const normalized = normalizePedagogicalTime(
    getState().meta.learningDays,
    getState().meta.learningHours,
    getState().meta.learningMinutes,
    getDayHours()
  );
  return ((normalized.days * getDayHours() + normalized.hours) * 60) + normalized.minutes;
}

function getAnalysisAlerts(metrics) {
  const activities = metrics.activities;
  const designedMinutes = metrics.overall;
  const learningMinutes = totalDeclaredLearningMinutes();

  const hasInvalidDuration = activities.some((activity) => {
    const duration = Number(activity.duration);
    return !Number.isFinite(duration) || duration <= 0;
  });
  const hasInvalidGroupMode = activities.some(
    (activity) => !["whole", "subgroups", "individual"].includes(activity.groupMode)
  );
  const hasInvalidTeachingMode = activities.some(
    (activity) => !TEACHING_VALUES.has(activity.teachingMode)
  );
  const hasInvalidSyncMode = activities.some(
    (activity) => !["sync", "async"].includes(activity.syncMode)
  );
  const hasInvalidLocationMode = activities.some(
    (activity) => !LOCATION_VALUES.has(activity.locationMode)
  );

  const alerts = [];
  if (hasInvalidDuration) alerts.push({ id: "AN-01", level: "warning", message: t("an01") });
  if (designedMinutes <= 0) alerts.push({ id: "AN-02", level: "warning", message: t("an02") });
  if (hasInvalidGroupMode) alerts.push({ id: "AN-03", level: "warning", message: t("an03") });
  if (hasInvalidTeachingMode) alerts.push({ id: "AN-04", level: "warning", message: t("an04") });
  if (hasInvalidSyncMode) alerts.push({ id: "AN-05", level: "warning", message: t("an05") });
  if (hasInvalidLocationMode) alerts.push({ id: "AN-06", level: "warning", message: t("an06") });
  if (learningMinutes > 0 && designedMinutes > learningMinutes) {
    alerts.push({ id: "AN-07", level: "info", message: t("an07") });
  }
  if (learningMinutes === 0 && designedMinutes > 0) {
    alerts.push({ id: "AN-08", level: "info", message: t("an08") });
  }
  const hasOnlyUndefined =
    activities.length > 0 &&
    activities.every((activity) => !activity.type || activity.type === "undefined");
  if (hasOnlyUndefined) {
    alerts.push({ id: "AN-09", level: "info", message: t("an09") });
  }
  return alerts;
}

function renderAnalysisAlerts(metrics) {
  const alerts = getAnalysisAlerts(metrics);
  analysisAlertTimers.forEach((timer) => window.clearTimeout(timer));
  analysisAlertTimers = [];
  analysisAlerts.innerHTML = "";
  analysisAlerts.classList.toggle("hidden", alerts.length === 0);
  if (!alerts.length) return;

  alerts.forEach((alert, index) => {
    const item = document.createElement("p");
    item.className = `analysis-alert ${alert.level}`;
    item.textContent = alert.message;
    item.dataset.alertId = alert.id;
    analysisAlerts.appendChild(item);

    const dismissTimer = window.setTimeout(() => {
      if (item.parentElement !== analysisAlerts) return;
      item.classList.add("is-dismissing");
      const removeTimer = window.setTimeout(() => {
        if (item.parentElement !== analysisAlerts) return;
        item.remove();
        analysisAlerts.classList.toggle("hidden", analysisAlerts.childElementCount === 0);
      }, 260);
      analysisAlertTimers.push(removeTimer);
    }, 6000 + (index * 700));
    analysisAlertTimers.push(dismissTimer);
  });
}

function renderAnalysisPanel() {
  const metrics = buildAnalysisMetrics();
  renderAnalysisAlerts(metrics);
  const [learning, delivery, teaching, pacing, evaluation, group, aias] = buildAnalysisDatasets(metrics);
  const learningData = learning.data;
  renderConic(analysisLearningPie, learningData);
  renderLegend(analysisLearningLegend, learningData, false);
  renderPieOuterLabels(
    analysisLearningPieWrap,
    analysisLearningPie,
    analysisLearningLabels,
    analysisLearningTooltip,
    learningData,
    (segment) => learningPieCode(segment.key)
  );

  const deliveryData = delivery.data;
  renderConic(analysisDeliveryPie, deliveryData);
  renderLegend(analysisDeliveryLegend, deliveryData);

  const teachingData = teaching.data;
  renderConic(analysisTeacherPie, teachingData);
  renderLegend(analysisTeacherLegend, teachingData);

  const syncData = pacing.data;
  renderConic(analysisSyncPie, syncData);
  renderLegend(analysisSyncLegend, syncData);

  const evalData = evaluation.data;
  renderConic(analysisEvalPie, evalData);
  renderLegend(analysisEvalLegend, evalData);

  const groupData = group.data;
  renderSegmentedBar(analysisGroupBar, groupData);
  renderLegend(analysisGroupLegend, groupData);

  const aiasData = aias.data;
  renderSegmentedBar(analysisAiasBar, aiasData);
  renderLegend(analysisAiasLegend, aiasData);
}

function analysisExportPayload() {
  const metrics = buildAnalysisMetrics();
  return {
    title: String(getState().meta?.name || "").trim() || t("analysisExportReportTitle"),
    total: metrics.overall,
    datasets: buildAnalysisDatasets(metrics),
    alerts: getAnalysisAlerts(metrics)
  };
}

function csvCell(value) {
  return `"${String(value ?? "").replace(/"/g, '""')}"`;
}

function buildAnalysisCsv(payload = analysisExportPayload()) {
  const rows = [[t("analysisExportReportTitle"), payload.title, "", ""]];
  rows.push([t("analysisExportTotal"), payload.total, t("analysisExportMinutes"), ""]);
  rows.push([]);
  rows.push([t("analysisExportCategory"), t("analysisExportDetail"), t("analysisExportValue"), t("analysisExportShare")]);
  payload.datasets.forEach((dataset) => {
    dataset.data.segments.forEach((segment) => {
      rows.push([dataset.title, segment.label, segment.value, segment.pct.toFixed(1)]);
    });
  });
  return `\uFEFF${rows.map((row) => row.map(csvCell).join(";")).join("\r\n")}`;
}

function buildAnalysisText(payload = analysisExportPayload()) {
  const lines = [
    t("analysisExportReportTitle").toUpperCase(),
    payload.title,
    `${t("analysisExportTotal")} : ${payload.total} ${t("analysisExportMinutes")}`,
    ""
  ];
  payload.datasets.forEach((dataset) => {
    lines.push(dataset.title);
    const visibleSegments = dataset.data.segments.filter((segment) => segment.value > 0);
    if (!visibleSegments.length) lines.push(`- ${t("noData")}`);
    visibleSegments.forEach((segment) => {
      lines.push(`- ${segment.label} : ${segment.value} min (${segment.pct.toFixed(1)} %)`);
    });
    lines.push("");
  });
  if (payload.alerts.length) {
    lines.push(currentLang() === "en" ? "Alerts" : "Alertes");
    payload.alerts.forEach((alert) => lines.push(`- ${alert.message}`));
  }
  return lines.join("\n").trimEnd();
}

function markdownCell(value) {
  return String(value ?? "").replace(/\\/g, "\\\\").replace(/\|/g, "\\|").replace(/\r?\n/g, " ");
}

function buildAnalysisMarkdown(payload = analysisExportPayload()) {
  const lines = [
    `# ${t("analysisExportReportTitle")}`,
    "",
    `## ${payload.title}`,
    "",
    `**${t("analysisExportTotal")} :** ${payload.total} ${t("analysisExportMinutes")}`,
    ""
  ];
  payload.datasets.forEach((dataset) => {
    lines.push(`### ${dataset.title}`, "");
    const visibleSegments = dataset.data.segments.filter((segment) => segment.value > 0);
    if (!visibleSegments.length) {
      lines.push(`_${t("noData")}_`, "");
      return;
    }
    lines.push(
      `| ${t("analysisExportDetail")} | ${t("analysisExportValue")} | ${t("analysisExportShare")} |`,
      "|---|---:|---:|"
    );
    visibleSegments.forEach((segment) => {
      lines.push(`| ${markdownCell(segment.label)} | ${segment.value} | ${segment.pct.toFixed(1)} % |`);
    });
    lines.push("");
  });
  if (payload.alerts.length) {
    lines.push(`### ${currentLang() === "en" ? "Alerts" : "Alertes"}`, "");
    payload.alerts.forEach((alert) => lines.push(`- ${alert.message}`));
    lines.push("");
  }
  return lines.join("\n").trimEnd();
}

function analysisExportStylesheetLink(stylesheet) {
  if (!stylesheet) return "";
  return `<link rel="stylesheet" href="data:text/css;charset=utf-8,${encodeURIComponent(stylesheet)}">`;
}

async function loadAnalysisExportStylesheet() {
  const response = await fetch("css/analysis-export.css", { cache: "no-cache" });
  if (!response.ok) throw new Error(`Analysis export stylesheet unavailable (${response.status})`);
  return response.text();
}

function buildAnalysisHtml(payload = analysisExportPayload(), stylesheet = "") {
  const sections = payload.datasets.map((dataset) => {
    const visibleSegments = dataset.data.segments.filter((segment) => segment.value > 0);
    const content = visibleSegments.length
      ? `${buildAnalysisDatasetSvg(dataset).replace('width="1200"', 'width="100%"')}<table border="1" bordercolor="#c8c8c8" cellpadding="8" cellspacing="0" width="100%" rules="all" frame="border"><thead><tr bgcolor="#e7e7e7"><th scope="col" align="left" width="60%">${escapeXml(t("analysisExportDetail"))}</th><th scope="col" align="left" width="20%">${escapeXml(t("analysisExportValue"))}</th><th scope="col" align="left" width="20%">${escapeXml(t("analysisExportShare"))}</th></tr></thead><tbody>${visibleSegments.map((segment) => `<tr><td>${escapeXml(segment.label)}</td><td>${escapeXml(segment.value)}</td><td>${escapeXml(segment.pct.toFixed(1))} %</td></tr>`).join("")}</tbody></table>`
      : `<p>${escapeXml(t("noData"))}</p>`;
    return `<section class="analysis-export-section"><h2>${escapeXml(dataset.title)}</h2>${content}</section>`;
  }).join("");
  const alerts = payload.alerts.length
    ? `<section class="analysis-export-section analysis-export-alerts"><h2>${currentLang() === "en" ? "Alerts" : "Alertes"}</h2><ul>${payload.alerts.map((alert) => `<li>${escapeXml(alert.message)}</li>`).join("")}</ul></section>`
    : "";
  return `<!doctype html>
<html lang="${currentLang() === "en" ? "en" : "fr"}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${escapeXml(t("analysisExportReportTitle"))} — ${escapeXml(payload.title)}</title>${analysisExportStylesheetLink(stylesheet)}</head>
<body class="analysis-export-page"><main class="analysis-export-document"><header class="analysis-export-header"><h1>${escapeXml(t("analysisExportReportTitle"))}</h1><p>${escapeXml(payload.title)}</p><p><strong>${escapeXml(t("analysisExportTotal"))} :</strong> ${escapeXml(payload.total)} ${escapeXml(t("analysisExportMinutes"))}</p></header>${sections}${alerts}</main></body>
</html>`;
}

function escapeXml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function wordParagraph(value, style = "BodyText", options = {}) {
  const runProperties = options.bold ? "<w:rPr><w:b/></w:rPr>" : "";
  return `<w:p><w:pPr><w:pStyle w:val="${style}"/></w:pPr><w:r>${runProperties}<w:t xml:space="preserve">${escapeXml(value)}</w:t></w:r></w:p>`;
}

function wordTableCell(value, width, options = {}) {
  const fill = options.header ? '<w:shd w:fill="E7E7E7"/>' : "";
  return `<w:tc><w:tcPr><w:tcW w:w="${width}" w:type="dxa"/>${fill}</w:tcPr>${wordParagraph(value, "TableText", { bold: options.header })}</w:tc>`;
}

function wordAnalysisTable(segments) {
  const rows = [
    `<w:tr>${wordTableCell(t("analysisExportDetail"), 5600, { header: true })}${wordTableCell(t("analysisExportValue"), 1800, { header: true })}${wordTableCell(t("analysisExportShare"), 1800, { header: true })}</w:tr>`
  ];
  segments.forEach((segment) => {
    rows.push(`<w:tr>${wordTableCell(segment.label, 5600)}${wordTableCell(String(segment.value), 1800)}${wordTableCell(`${segment.pct.toFixed(1)} %`, 1800)}</w:tr>`);
  });
  return `<w:tbl><w:tblPr><w:tblW w:w="9200" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B8B8B8"/><w:left w:val="single" w:sz="4" w:color="B8B8B8"/><w:bottom w:val="single" w:sz="4" w:color="B8B8B8"/><w:right w:val="single" w:sz="4" w:color="B8B8B8"/><w:insideH w:val="single" w:sz="4" w:color="D6D6D6"/><w:insideV w:val="single" w:sz="4" w:color="D6D6D6"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="5600"/><w:gridCol w:w="1800"/><w:gridCol w:w="1800"/></w:tblGrid>${rows.join("")}</w:tbl>`;
}

function wordAnalysisImage(relationshipId, name, widthEmu, heightEmu, documentPropertyId) {
  return `<w:p><w:pPr><w:keepNext/><w:spacing w:before="100" w:after="160"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0"><wp:extent cx="${widthEmu}" cy="${heightEmu}"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:docPr id="${documentPropertyId}" name="${escapeXml(name)}"/><wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="${documentPropertyId}" name="${escapeXml(name)}"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="${relationshipId}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="${widthEmu}" cy="${heightEmu}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>`;
}

function analysisUsesPie(dataset) {
  return dataset.key !== "group" && dataset.key !== "aias";
}

function piePoint(centerX, centerY, radius, percentage) {
  const angle = ((percentage / 100) * Math.PI * 2) - (Math.PI / 2);
  return {
    x: centerX + (Math.cos(angle) * radius),
    y: centerY + (Math.sin(angle) * radius)
  };
}

function svgPieSegments(segments, centerX, centerY, radius) {
  return segments.map((segment) => {
    if (segment.pct >= 99.999) {
      return `<circle cx="${centerX}" cy="${centerY}" r="${radius}" fill="${escapeXml(segment.color)}"/>`;
    }
    const start = piePoint(centerX, centerY, radius, segment.start);
    const end = piePoint(centerX, centerY, radius, segment.end);
    const largeArc = segment.pct > 50 ? 1 : 0;
    return `<path d="M ${centerX} ${centerY} L ${start.x.toFixed(2)} ${start.y.toFixed(2)} A ${radius} ${radius} 0 ${largeArc} 1 ${end.x.toFixed(2)} ${end.y.toFixed(2)} Z" fill="${escapeXml(segment.color)}" stroke="#ffffff" stroke-width="2"/>`;
  });
}

function buildAnalysisDatasetSvg(dataset) {
  const active = dataset.data.segments.filter((segment) => segment.value > 0);
  const width = 1200;
  if (analysisUsesPie(dataset)) {
    const height = 280;
    const parts = [
      `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img">`,
      `<rect width="${width}" height="${height}" fill="#ffffff"/>`,
      `<circle cx="190" cy="140" r="108" fill="#f3f3f3" stroke="#d4d4d4"/>`,
      ...svgPieSegments(active, 190, 140, 108)
    ];
    active.forEach((segment, index) => {
      const y = 72 + (index * 36);
      parts.push(`<rect x="360" y="${y - 13}" width="14" height="14" rx="2" fill="${escapeXml(segment.color)}" stroke="#999999" stroke-width="0.5"/>`);
      parts.push(`<text x="384" y="${y - 1}" fill="#333333" font-family="Arial, Helvetica, sans-serif" font-size="17">${escapeXml(visualLabel(segment.label, 48))}</text>`);
      parts.push(`<text x="1136" y="${y - 1}" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="17" text-anchor="end" font-weight="700">${escapeXml(`${segment.value} min · ${segment.pct.toFixed(1)} %`)}</text>`);
    });
    parts.push("</svg>");
    return parts.join("");
  }
  const rowCount = Math.max(1, Math.ceil(active.length / 2));
  const height = 68 + (rowCount * 34);
  const barWidth = 1072;
  const parts = [
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img">`,
    `<rect width="${width}" height="${height}" fill="#ffffff"/>`,
    `<rect x="64" y="10" width="${barWidth}" height="26" rx="4" fill="#f3f3f3" stroke="#d4d4d4"/>`
  ];
  active.forEach((segment) => {
    const segmentWidth = Math.max(0, (segment.pct / 100) * barWidth);
    if (segmentWidth <= 0) return;
    parts.push(`<rect x="${(64 + (segment.start / 100) * barWidth).toFixed(2)}" y="10" width="${segmentWidth.toFixed(2)}" height="26" fill="${escapeXml(segment.color)}"/>`);
  });
  active.forEach((segment, index) => {
    const column = index % 2;
    const row = Math.floor(index / 2);
    const x = 64 + (column * 536);
    const y = 68 + (row * 34);
    parts.push(`<rect x="${x}" y="${y - 13}" width="12" height="12" rx="2" fill="${escapeXml(segment.color)}" stroke="#999999" stroke-width="0.5"/>`);
    parts.push(`<text x="${x + 21}" y="${y - 2}" fill="#333333" font-family="Arial, Helvetica, sans-serif" font-size="14">${escapeXml(visualLabel(segment.label))}</text>`);
    parts.push(`<text x="${x + 510}" y="${y - 2}" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="14" text-anchor="end" font-weight="700">${escapeXml(`${segment.value} min · ${segment.pct.toFixed(1)} %`)}</text>`);
  });
  parts.push("</svg>");
  return parts.join("");
}

async function buildAnalysisWord(payload = analysisExportPayload()) {
  if (typeof createZipArchive !== "function") throw new Error("DOCX archive builder unavailable");
  const body = [
    wordParagraph(t("analysisExportReportTitle"), "Title"),
    wordParagraph(payload.title, "Subtitle"),
    wordParagraph(`${t("analysisExportTotal")} : ${payload.total} ${t("analysisExportMinutes")}`, "BodyText", { bold: true })
  ];
  const imageEntries = [];
  const imageRelationships = [];
  for (const [datasetIndex, dataset] of payload.datasets.entries()) {
    body.push(wordParagraph(dataset.title, "Heading1"));
    const visibleSegments = dataset.data.segments.filter((segment) => segment.value > 0);
    if (visibleSegments.length) {
      const chartCanvas = await canvasFromSvg(buildAnalysisDatasetSvg(dataset), 1.5);
      const chartBlob = await canvasBlob(chartCanvas, "image/png");
      const chartBytes = new Uint8Array(await chartBlob.arrayBuffer());
      const imageNumber = datasetIndex + 1;
      const relationshipId = `rId${imageNumber + 1}`;
      const imageName = `analysis-${dataset.key}.png`;
      const chartWidthEmu = 5943600;
      const chartHeightEmu = Math.round(chartWidthEmu * (chartCanvas.height / chartCanvas.width));
      body.push(wordAnalysisImage(relationshipId, imageName, chartWidthEmu, chartHeightEmu, imageNumber));
      body.push(wordAnalysisTable(visibleSegments));
      imageRelationships.push(`<Relationship Id="${relationshipId}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/${imageName}"/>`);
      imageEntries.push({ name: `word/media/${imageName}`, content: chartBytes });
    } else {
      body.push(wordParagraph(t("noData"), "BodyText"));
    }
  }
  if (payload.alerts.length) {
    body.push(wordParagraph(currentLang() === "en" ? "Alerts" : "Alertes", "Heading1"));
    payload.alerts.forEach((alert) => body.push(wordParagraph(`• ${alert.message}`, "BodyText")));
  }

  const documentXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>${body.join("")}<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>`;
  const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="21"/><w:color w:val="242424"/></w:rPr></w:rPrDefault></w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="180"/></w:pPr><w:rPr><w:b/><w:sz w:val="34"/><w:color w:val="111111"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="260"/></w:pPr><w:rPr><w:sz w:val="24"/><w:color w:val="555555"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="300" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="25"/><w:color w:val="111111"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="120"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0"/></w:pPr><w:rPr><w:sz w:val="19"/></w:rPr></w:style>
</w:styles>`;
  return createZipArchive([
    { name: "[Content_Types].xml", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>` },
    { name: "_rels/.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>` },
    { name: "word/_rels/document.xml.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>${imageRelationships.join("")}</Relationships>` },
    { name: "word/document.xml", content: documentXml },
    { name: "word/styles.xml", content: stylesXml },
    ...imageEntries
  ]);
}

function excelColumnName(index) {
  let value = Math.max(1, Number(index) || 1);
  let column = "";
  while (value > 0) {
    value -= 1;
    column = String.fromCharCode(65 + (value % 26)) + column;
    value = Math.floor(value / 26);
  }
  return column;
}

function excelCell(value, reference, style = 0) {
  const styleAttribute = style ? ` s="${style}"` : "";
  if (typeof value === "number" && Number.isFinite(value)) {
    return `<c r="${reference}"${styleAttribute} t="n"><v>${value}</v></c>`;
  }
  return `<c r="${reference}"${styleAttribute} t="inlineStr"><is><t xml:space="preserve">${escapeXml(value)}</t></is></c>`;
}

function excelChartSeriesXml(dataset, sheetName, firstRow, lastRow) {
  const segments = dataset.data.segments.filter((segment) => segment.value > 0);
  const quotedSheetName = `'${String(sheetName).replace(/'/g, "''")}'`;
  const categoryPoints = segments.map((segment, index) => `<c:pt idx="${index}"><c:v>${escapeXml(segment.label)}</c:v></c:pt>`).join("");
  const valuePoints = segments.map((segment, index) => `<c:pt idx="${index}"><c:v>${segment.value}</c:v></c:pt>`).join("");
  const colors = segments.map((segment, index) => `<c:dPt><c:idx val="${index}"/><c:spPr><a:solidFill><a:srgbClr val="${escapeXml(segment.color.replace(/^#/, "").toUpperCase())}"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr></c:dPt>`).join("");
  return `<c:ser><c:idx val="0"/><c:order val="0"/><c:tx><c:v>${escapeXml(t("analysisExportValue"))}</c:v></c:tx>${colors}<c:cat><c:strRef><c:f>${escapeXml(`${quotedSheetName}!$B$${firstRow}:$B$${lastRow}`)}</c:f><c:strCache><c:ptCount val="${segments.length}"/>${categoryPoints}</c:strCache></c:strRef></c:cat><c:val><c:numRef><c:f>${escapeXml(`${quotedSheetName}!$C$${firstRow}:$C$${lastRow}`)}</c:f><c:numCache><c:formatCode>0</c:formatCode><c:ptCount val="${segments.length}"/>${valuePoints}</c:numCache></c:numRef></c:val></c:ser>`;
}

function excelChartTitleXml(title) {
  return `<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="fr-FR" sz="1200" b="1"/><a:t>${escapeXml(title)}</a:t></a:r><a:endParaRPr lang="fr-FR"/></a:p></c:rich></c:tx><c:layout/><c:overlay val="0"/></c:title>`;
}

function buildAnalysisExcelChart(dataset, sheetName, firstRow, lastRow, chartIndex) {
  const series = excelChartSeriesXml(dataset, sheetName, firstRow, lastRow);
  const isPie = analysisUsesPie(dataset);
  const categoryAxisId = 100000 + (chartIndex * 2);
  const valueAxisId = categoryAxisId + 1;
  const plot = isPie
    ? `<c:pieChart><c:varyColors val="1"/>${series}<c:dLbls><c:showLegendKey val="0"/><c:showVal val="0"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="1"/><c:showLeaderLines val="1"/></c:dLbls><c:firstSliceAng val="270"/></c:pieChart>`
    : `<c:barChart><c:barDir val="bar"/><c:grouping val="clustered"/><c:varyColors val="1"/>${series}<c:dLbls><c:showLegendKey val="0"/><c:showVal val="1"/><c:showCatName val="0"/><c:showSerName val="0"/></c:dLbls><c:gapWidth val="55"/><c:axId val="${categoryAxisId}"/><c:axId val="${valueAxisId}"/></c:barChart><c:catAx><c:axId val="${categoryAxisId}"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:tickLblPos val="nextTo"/><c:crossAx val="${valueAxisId}"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/></c:catAx><c:valAx><c:axId val="${valueAxisId}"/><c:scaling><c:orientation val="minMax"/><c:min val="0"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:numFmt formatCode="0" sourceLinked="0"/><c:majorGridlines/><c:tickLblPos val="nextTo"/><c:crossAx val="${categoryAxisId}"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>`;
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><c:date1904 val="0"/><c:lang val="fr-FR"/><c:roundedCorners val="0"/><c:chart>${excelChartTitleXml(dataset.title)}<c:autoTitleDeleted val="0"/><c:plotArea><c:layout/>${plot}</c:plotArea><c:legend><c:legendPos val="r"/><c:layout/><c:overlay val="0"/></c:legend><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/><c:showDLblsOverMax val="0"/></c:chart><c:spPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln><a:solidFill><a:srgbClr val="D4D4D4"/></a:solidFill><a:prstDash val="solid"/></a:ln></c:spPr><c:printSettings><c:headerFooter/><c:pageMargins b="0.75" l="0.7" r="0.7" t="0.75" header="0.3" footer="0.3"/><c:pageSetup/></c:printSettings></c:chartSpace>`;
}

function buildAnalysisExcelDrawing(chartInfos) {
  const anchors = chartInfos.map((chart, index) => {
    const startRow = chart.startRow - 1;
    return `<xdr:twoCellAnchor><xdr:from><xdr:col>5</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>${startRow}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>13</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>${startRow + 14}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="${index + 2}" name="${escapeXml(chart.dataset.title)}"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId${index + 1}"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>`;
  }).join("");
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">${anchors}</xdr:wsDr>`;
}

function buildAnalysisExcel(payload = analysisExportPayload()) {
  if (typeof createZipArchive !== "function") throw new Error("XLSX archive builder unavailable");
  const sheetName = currentLang() === "en" ? "Analysis" : "Analyse";
  const sheetRows = [];
  const addRow = (rowNumber, values, style = 0) => {
    const cells = values.map((value, columnIndex) => excelCell(value, `${excelColumnName(columnIndex + 1)}${rowNumber}`, style)).join("");
    sheetRows.push(`<row r="${rowNumber}">${cells}</row>`);
  };
  addRow(1, [t("analysisExportReportTitle"), payload.title, "", ""], 1);
  addRow(2, [t("analysisExportTotal"), payload.total, t("analysisExportMinutes"), ""]);
  const chartInfos = [];
  payload.datasets.forEach((dataset, datasetIndex) => {
    const startRow = 5 + (datasetIndex * 16);
    const active = dataset.data.segments.filter((segment) => segment.value > 0);
    addRow(startRow, [dataset.title, "", "", ""], 3);
    addRow(startRow + 1, [t("analysisExportCategory"), t("analysisExportDetail"), t("analysisExportValue"), `${t("analysisExportShare")} (%)`], 2);
    if (!active.length) {
      addRow(startRow + 2, [dataset.title, t("noData"), "", ""], 4);
      return;
    }
    active.forEach((segment, segmentIndex) => {
      addRow(startRow + 2 + segmentIndex, [dataset.title, segment.label, segment.value, Number(segment.pct.toFixed(1))], 4);
    });
    chartInfos.push({ dataset, startRow, firstRow: startRow + 2, lastRow: startRow + 1 + active.length });
  });

  const sheetXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetViews><sheetView workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <cols><col min="1" max="1" width="26" customWidth="1"/><col min="2" max="2" width="38" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="15" customWidth="1"/><col min="5" max="5" width="3" customWidth="1"/><col min="6" max="14" width="12" customWidth="1"/></cols>
  <sheetData>${sheetRows.join("")}</sheetData>
  <drawing r:id="rId1"/>
</worksheet>`;
  const workbookXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="${escapeXml(sheetName)}" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="191029" calcMode="auto"/></workbook>`;
  const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>
  <fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7E7E7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD0D0D0"/></left><right style="thin"><color rgb="FFD0D0D0"/></right><top style="thin"><color rgb="FFD0D0D0"/></top><bottom style="thin"><color rgb="FFD0D0D0"/></bottom><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>`;

  const chartEntries = chartInfos.map((chart, index) => ({
    name: `xl/charts/chart${index + 1}.xml`,
    content: buildAnalysisExcelChart(chart.dataset, sheetName, chart.firstRow, chart.lastRow, index + 1)
  }));
  const chartOverrides = chartInfos.map((_, index) => `<Override PartName="/xl/charts/chart${index + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>`).join("");
  const drawingRelationships = chartInfos.map((_, index) => `<Relationship Id="rId${index + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart${index + 1}.xml"/>`).join("");

  return createZipArchive([
    { name: "[Content_Types].xml", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>${chartOverrides}</Types>` },
    { name: "_rels/.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>` },
    { name: "xl/_rels/workbook.xml.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>` },
    { name: "xl/workbook.xml", content: workbookXml },
    { name: "xl/worksheets/sheet1.xml", content: sheetXml },
    { name: "xl/worksheets/_rels/sheet1.xml.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>` },
    { name: "xl/styles.xml", content: stylesXml },
    { name: "xl/drawings/drawing1.xml", content: buildAnalysisExcelDrawing(chartInfos) },
    { name: "xl/drawings/_rels/drawing1.xml.rels", content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${drawingRelationships}</Relationships>` },
    ...chartEntries
  ]);
}

function visualLabel(value, maxLength = 55) {
  const text = String(value ?? "");
  return text.length > maxLength ? `${text.slice(0, maxLength - 1)}…` : text;
}

function buildAnalysisSvg(payload = analysisExportPayload()) {
  const width = 1200;
  const sectionHeights = payload.datasets.map((dataset) => {
    if (analysisUsesPie(dataset)) return 250;
    const count = Math.max(1, dataset.data.segments.filter((segment) => segment.value > 0).length);
    return 112 + (Math.ceil(count / 2) * 29);
  });
  const height = 174 + sectionHeights.reduce((sum, value) => sum + value, 0) + 54;
  const parts = [
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img" aria-labelledby="export-title export-desc">`,
    `<title id="export-title">${escapeXml(t("analysisExportReportTitle"))}</title>`,
    `<desc id="export-desc">${escapeXml(payload.title)}</desc>`,
    `<rect width="${width}" height="${height}" fill="#ffffff"/>`,
    `<text x="64" y="68" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700">${escapeXml(t("analysisExportReportTitle"))}</text>`,
    `<text x="64" y="105" fill="#4b5563" font-family="Arial, Helvetica, sans-serif" font-size="20">${escapeXml(payload.title)}</text>`,
    `<text x="1136" y="68" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="18" text-anchor="end" font-weight="700">${escapeXml(payload.total)} min</text>`,
    `<line x1="64" y1="134" x2="1136" y2="134" stroke="#d4d4d4" stroke-width="1"/>`
  ];
  let y = 174;
  payload.datasets.forEach((dataset, datasetIndex) => {
    const active = dataset.data.segments.filter((segment) => segment.value > 0);
    parts.push(`<text x="64" y="${y}" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="19" font-weight="700">${escapeXml(dataset.title)}</text>`);
    if (!active.length) {
      parts.push(`<text x="64" y="${y + 54}" fill="#6b7280" font-family="Arial, Helvetica, sans-serif" font-size="15">${escapeXml(t("noData"))}</text>`);
    } else if (analysisUsesPie(dataset)) {
      const centerX = 180;
      const centerY = y + 118;
      const radius = 82;
      parts.push(`<circle cx="${centerX}" cy="${centerY}" r="${radius}" fill="#f3f3f3" stroke="#d4d4d4"/>`);
      parts.push(...svgPieSegments(active, centerX, centerY, radius));
      active.forEach((segment, index) => {
        const itemY = y + 55 + (index * 31);
        parts.push(`<rect x="320" y="${itemY - 13}" width="12" height="12" rx="2" fill="${escapeXml(segment.color)}" stroke="#999999" stroke-width="0.5"/>`);
        parts.push(`<text x="341" y="${itemY - 2}" fill="#333333" font-family="Arial, Helvetica, sans-serif" font-size="14">${escapeXml(visualLabel(segment.label, 50))}</text>`);
        parts.push(`<text x="1136" y="${itemY - 2}" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="14" text-anchor="end" font-weight="700">${escapeXml(`${segment.value} min · ${segment.pct.toFixed(1)} %`)}</text>`);
      });
    } else {
      const barY = y + 24;
      const barWidth = 1072;
      parts.push(`<rect x="64" y="${barY}" width="${barWidth}" height="24" rx="4" fill="#f3f3f3" stroke="#d4d4d4"/>`);
      active.forEach((segment) => {
        const segmentWidth = Math.max(0, (segment.pct / 100) * barWidth);
        if (segmentWidth <= 0) return;
        parts.push(`<rect x="${(64 + (segment.start / 100) * barWidth).toFixed(2)}" y="${barY}" width="${segmentWidth.toFixed(2)}" height="24" fill="${escapeXml(segment.color)}"/>`);
      });
      const legendY = barY + 57;
      active.forEach((segment, index) => {
        const column = index % 2;
        const row = Math.floor(index / 2);
        const x = 64 + (column * 536);
        const itemY = legendY + (row * 29);
        parts.push(`<rect x="${x}" y="${itemY - 13}" width="12" height="12" rx="2" fill="${escapeXml(segment.color)}" stroke="#999999" stroke-width="0.5"/>`);
        parts.push(`<text x="${x + 21}" y="${itemY - 2}" fill="#333333" font-family="Arial, Helvetica, sans-serif" font-size="14">${escapeXml(visualLabel(segment.label))}</text>`);
        parts.push(`<text x="${x + 510}" y="${itemY - 2}" fill="#161616" font-family="Arial, Helvetica, sans-serif" font-size="14" text-anchor="end" font-weight="700">${escapeXml(`${segment.value} min · ${segment.pct.toFixed(1)} %`)}</text>`);
      });
    }
    y += sectionHeights[datasetIndex];
    if (datasetIndex < payload.datasets.length - 1) {
      parts.push(`<line x1="64" y1="${y - 28}" x2="1136" y2="${y - 28}" stroke="#e5e5e5" stroke-width="1"/>`);
    }
  });
  parts.push(`<text x="64" y="${height - 28}" fill="#777777" font-family="Arial, Helvetica, sans-serif" font-size="12">Scénarisation</text>`, "</svg>");
  return parts.join("");
}

function canvasFromSvg(svg, scale = 2) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(new Blob([svg], { type: "image/svg+xml;charset=utf-8" }));
    image.onload = () => {
      try {
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(image.width * scale);
        canvas.height = Math.round(image.height * scale);
        const context = canvas.getContext("2d");
        context.fillStyle = "#ffffff";
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        resolve(canvas);
      } catch (error) {
        URL.revokeObjectURL(url);
        reject(error);
      }
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("SVG rendering failed"));
    };
    image.src = url;
  });
}

function canvasBlob(canvas, type, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error("Canvas export failed")), type, quality);
  });
}

async function buildPdfFromCanvas(canvas) {
  const jpeg = await canvasBlob(canvas, "image/jpeg", 0.92);
  const jpegBytes = new Uint8Array(await jpeg.arrayBuffer());
  const encoder = new TextEncoder();
  const chunks = [];
  const offsets = [0];
  let length = 0;
  const append = (value) => {
    const bytes = typeof value === "string" ? encoder.encode(value) : value;
    chunks.push(bytes);
    length += bytes.length;
  };
  const object = (number, body, binary = null, tail = "") => {
    offsets[number] = length;
    append(`${number} 0 obj\n${body}`);
    if (binary) append(binary);
    if (tail) append(tail);
    append("\nendobj\n");
  };
  append("%PDF-1.4\n");
  object(1, "<< /Type /Catalog /Pages 2 0 R >>");
  object(2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
  const pageWidth = 595;
  const pageHeight = 842;
  const margin = 28;
  const ratio = Math.min((pageWidth - margin * 2) / canvas.width, (pageHeight - margin * 2) / canvas.height);
  const drawWidth = canvas.width * ratio;
  const drawHeight = canvas.height * ratio;
  const x = (pageWidth - drawWidth) / 2;
  const y = (pageHeight - drawHeight) / 2;
  object(3, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageWidth} ${pageHeight}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>`);
  object(4, `<< /Type /XObject /Subtype /Image /Width ${canvas.width} /Height ${canvas.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpegBytes.length} >>\nstream\n`, jpegBytes, "\nendstream");
  const drawing = `q ${drawWidth.toFixed(3)} 0 0 ${drawHeight.toFixed(3)} ${x.toFixed(3)} ${y.toFixed(3)} cm /Im0 Do Q`;
  object(5, `<< /Length ${drawing.length} >>\nstream\n${drawing}\nendstream`);
  const xrefOffset = length;
  append("xref\n0 6\n0000000000 65535 f \n");
  for (let index = 1; index <= 5; index += 1) append(`${String(offsets[index]).padStart(10, "0")} 00000 n \n`);
  append(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`);
  return new Blob(chunks, { type: "application/pdf" });
}

function analysisFilename(extension) {
  const base = String(getState().meta?.name || t("analysisExportReportTitle"))
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9_-]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .toLowerCase() || "analyse";
  return `${base}-analyse.${extension}`;
}

async function exportAnalysis(format) {
  const payload = analysisExportPayload();
  const svg = buildAnalysisSvg(payload);
  if (format === "txt") return downloadBlob(buildAnalysisText(payload), "text/plain;charset=utf-8", analysisFilename("txt"));
  if (format === "md") return downloadBlob(buildAnalysisMarkdown(payload), "text/markdown;charset=utf-8", analysisFilename("md"));
  if (format === "html") {
    const stylesheet = await loadAnalysisExportStylesheet();
    return downloadBlob(buildAnalysisHtml(payload, stylesheet), "text/html;charset=utf-8", analysisFilename("html"));
  }
  if (format === "csv") return downloadBlob(buildAnalysisCsv(payload), "text/csv;charset=utf-8", analysisFilename("csv"));
  if (format === "xlsx") return downloadBlob(buildAnalysisExcel(payload), "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", analysisFilename("xlsx"));
  if (format === "docx") {
    const wordDocument = await buildAnalysisWord(payload);
    return downloadBlob(wordDocument, "application/vnd.openxmlformats-officedocument.wordprocessingml.document", analysisFilename("docx"));
  }
  if (format === "svg") return downloadBlob(svg, "image/svg+xml;charset=utf-8", analysisFilename("svg"));
  const canvas = await canvasFromSvg(svg, format === "pdf" ? 1.5 : 2);
  if (format === "pdf") {
    const pdf = await buildPdfFromCanvas(canvas);
    return downloadBlob(pdf, "application/pdf", analysisFilename("pdf"));
  }
  const mime = format === "jpg" ? "image/jpeg" : "image/png";
  const blob = await canvasBlob(canvas, mime, format === "jpg" ? 0.9 : undefined);
  return downloadBlob(blob, mime, analysisFilename(format));
}

function setupAnalysisExport() {
  if (!analysisExportMenu || typeof downloadBlob !== "function") return;
  analysisExportMenu.querySelectorAll("[data-analysis-export]").forEach((button) => {
    button.addEventListener("click", async () => {
      const format = button.dataset.analysisExport;
      analysisExportStatus.textContent = t("analysisExportPreparing");
      button.disabled = true;
      try {
        await exportAnalysis(format);
        analysisExportStatus.textContent = t("analysisExportReady");
        announce?.(t("analysisExportReady"));
        window.setTimeout(() => {
          analysisExportMenu.open = false;
          analysisExportStatus.textContent = "";
        }, 900);
      } catch (error) {
        console.error("Analysis export failed", error);
        analysisExportStatus.textContent = t("analysisExportError");
        announce?.(t("analysisExportError"));
      } finally {
        button.disabled = false;
      }
    });
  });
  document.addEventListener("click", (event) => {
    if (analysisExportMenu.open && !analysisExportMenu.contains(event.target)) analysisExportMenu.open = false;
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && analysisExportMenu.open) {
      analysisExportMenu.open = false;
      analysisExportButton?.focus();
    }
  });
}


const analysisAlerts = document.getElementById("analysis-alerts");
let analysisAlertTimers = [];
const analysisLearningPieWrap = document.getElementById("analysis-learning-pie-wrap");
const analysisLearningPie = document.getElementById("analysis-learning-pie");
const analysisLearningLabels = document.getElementById("analysis-learning-labels");
const analysisLearningTooltip = document.getElementById("analysis-learning-tooltip");
const analysisLearningLegend = document.getElementById("analysis-learning-legend");
const analysisDeliveryPie = document.getElementById("analysis-delivery-pie");
const analysisDeliveryLegend = document.getElementById("analysis-delivery-legend");
const analysisTeacherPie = document.getElementById("analysis-teacher-pie");
const analysisTeacherLegend = document.getElementById("analysis-teacher-legend");
const analysisSyncPie = document.getElementById("analysis-sync-pie");
const analysisSyncLegend = document.getElementById("analysis-sync-legend");
const analysisEvalPie = document.getElementById("analysis-eval-pie");
const analysisEvalLegend = document.getElementById("analysis-eval-legend");
const analysisGroupBar = document.getElementById("analysis-group-bar");
const analysisGroupLegend = document.getElementById("analysis-group-legend");
const analysisAiasBar = document.getElementById("analysis-aias-bar");
const analysisAiasLegend = document.getElementById("analysis-aias-legend");
const analysisExportMenu = document.getElementById("analysis-export-menu");
const analysisExportButton = document.getElementById("analysis-export-button");
const analysisExportStatus = document.getElementById("analysis-export-status");
setupAnalysisExport();
return {
  renderAnalysisPanel,
  buildAnalysisCsv,
  buildAnalysisText,
  buildAnalysisMarkdown,
  buildAnalysisHtml,
  buildAnalysisExcel,
  buildAnalysisWord,
  buildAnalysisSvg
};
};
})();
