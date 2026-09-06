// Calcul et affichage des répartitions et alertes pédagogiques.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules.createAnalysis = ({
  getState, normalizeAiasState, t, currentLang, normalizePedagogicalTime, getDayHours,
  TEACHING_VALUES, LOCATION_VALUES, LEARNING_TYPES, AIAS_LEVELS
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

  const learningDefs = LEARNING_TYPES.map((type) => ({
    key: type.id,
    label: type.label,
    color: type.color
  }));
  const learningData = buildSegments(learningDefs, metrics.byType);
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

  const deliveryDefs = [
    { key: "onsite", label: t("activityModeClassroom"), color: "#37658b" },
    { key: "location_based", label: t("activityModeLocation"), color: "#5b88a6" },
    { key: "online", label: t("activityModeOnline"), color: "#bcc7d7" },
    { key: "hybrid", label: t("activityModeBlended"), color: "#4e84c8" },
    { key: "other", label: t("activityModeOther"), color: "#94a3b8" }
  ];
  const deliveryData = buildSegments(deliveryDefs, metrics.byLocation);
  renderConic(analysisDeliveryPie, deliveryData);
  renderLegend(analysisDeliveryLegend, deliveryData);

  const teachingDefs = [
    { key: "directed", label: t("teaching_directed"), color: "#67513f" },
    { key: "guided", label: t("teaching_guided"), color: "#8b6f52" },
    { key: "supported", label: t("teaching_supported"), color: "#b09470" },
    { key: "independent", label: t("teaching_independent"), color: "#d2c2aa" },
    { key: "undefined", label: t("teaching_undefined"), color: "#d1d5db" }
  ];
  const teachingData = buildSegments(teachingDefs, metrics.byTeaching);
  renderConic(analysisTeacherPie, teachingData);
  renderLegend(analysisTeacherLegend, teachingData);

  const syncDefs = [
    { key: "sync", label: t("sync_sync"), color: "#ac7f8d" },
    { key: "async", label: t("sync_async"), color: "#cbbec2" }
  ];
  const syncData = buildSegments(syncDefs, metrics.bySync);
  renderConic(analysisSyncPie, syncData);
  renderLegend(analysisSyncLegend, syncData);

  const evalDefs = [
    { key: "formative", label: t("eval_formative"), color: "#ccd5aa" },
    { key: "summative", label: t("eval_summative"), color: "#b2cf69" }
  ];
  const evalData = buildSegments(evalDefs, metrics.byEvaluation);
  renderConic(analysisEvalPie, evalData);
  renderLegend(analysisEvalLegend, evalData);

  const groupDefs = [
    { key: "whole", label: t("group_whole"), color: "#4f7d5a" },
    { key: "subgroups", label: t("group_subgroups"), color: "#6ab084" },
    { key: "individual", label: t("group_individual"), color: "#a8c8b1" }
  ];
  const groupData = buildSegments(groupDefs, metrics.byGroup);
  renderSegmentedBar(analysisGroupBar, groupData);
  renderLegend(analysisGroupLegend, groupData);

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
  const aiasData = buildSegments(aiasDefs, metrics.byAias);
  renderSegmentedBar(analysisAiasBar, aiasData);
  renderLegend(analysisAiasLegend, aiasData);
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
return {
  renderAnalysisPanel
};
};
})();
