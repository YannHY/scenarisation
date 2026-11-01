(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('.new-design-choice').forEach((card, index) => {
    if (reduceMotion || typeof card.animate !== 'function') return;
    const animation = card.animate([
      { opacity: 0, transform: 'translateY(24px)' },
      { opacity: 1, transform: 'translateY(0)' }
    ], {
      duration: 850,
      delay: index * 320,
      easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
      fill: 'backwards'
    });
    card.addEventListener('focus', () => animation.finish(), { once: true });
  });
})();

(() => {
  const dialog = document.getElementById('new-design-import');
  const title = document.getElementById('new-design-import-title');
  const fileSection = document.getElementById('new-design-file-section');
  const filters = document.getElementById('new-design-import-filters');
  const search = document.getElementById('new-design-import-search');
  const family = document.getElementById('new-design-import-family');
  const modelDuration = document.getElementById('new-design-import-duration');
  const modelMoments = document.getElementById('new-design-import-moments');
  const modelMode = document.getElementById('new-design-import-mode');
  const modelType = document.getElementById('new-design-import-type');
  const modelReset = document.getElementById('new-design-import-reset');
  const sharedFilters = document.getElementById('new-design-shared-filters');
  const sharedSystem = document.getElementById('new-design-shared-system');
  const sharedStage = document.getElementById('new-design-shared-stage');
  const sharedLevel = document.getElementById('new-design-shared-level');
  const sharedCompetency = document.getElementById('new-design-shared-competency');
  const sharedReset = document.getElementById('new-design-shared-reset');
  const status = document.getElementById('new-design-import-status');
  const content = document.getElementById('new-design-import-content');
  const back = document.getElementById('new-design-import-back');
  const close = document.getElementById('new-design-import-close');
  let catalog;
  let mode;
  let trigger;
  let requestId = 0;
  let sharedUrl = 'share.php';
  let sharedCards = [];
  const en = () => document.documentElement.lang === 'en';
  const text = (fr, english) => en() ? english : fr;
  const label = (entry, key) => entry[key + (en() ? 'En' : 'Fr')] || entry[key + 'Fr'] || '';
  const sequence = (prefix, first, last) => Array.from({ length: last - first + 1 }, (_, index) => `${prefix}${first + index}`);
  const schoolStages = {
    france: [
      ['maternelle', 'Maternelle', 'Pre-primary school', ['petite_section', 'moyenne_section', 'grande_section']],
      ['elementaire', 'École élémentaire', 'Primary school', ['cp', 'ce1', 'ce2', 'cm1', 'cm2']],
      ['college', 'Collège', 'Lower secondary school', ['sixieme', 'cinquieme', 'quatrieme', 'troisieme']],
      ['lycee', 'Lycée', 'Upper secondary school', ['seconde', 'premiere', 'terminale']]
    ],
    switzerland: [
      ['cycle_1', 'Cycle 1', 'Cycle 1', sequence('ch_', 1, 4).map(value => `${value}p`)],
      ['cycle_2', 'Cycle 2', 'Cycle 2', sequence('ch_', 5, 8).map(value => `${value}p`)],
      ['cycle_3', 'Cycle 3 — secondaire I', 'Cycle 3 — lower secondary', ['ch_9s', 'ch_10s', 'ch_11s']],
      ['secondaire_2', 'Secondaire II', 'Upper secondary', ['ch_sec2_1', 'ch_sec2_2', 'ch_sec2_3']]
    ],
    united_states: [
      ['elementary', 'École élémentaire', 'Elementary school', ['us_k', ...sequence('us_grade_', 1, 5)]],
      ['middle', 'Collège', 'Middle school', sequence('us_grade_', 6, 8)],
      ['high', 'Lycée', 'High school', sequence('us_grade_', 9, 12)]
    ],
    belgium_french: [
      ['maternelle', 'Enseignement maternel', 'Pre-primary education', sequence('be_fr_m', 1, 3)],
      ['primaire', 'Enseignement primaire', 'Primary education', sequence('be_fr_p', 1, 6)],
      ['secondaire', 'Enseignement secondaire', 'Secondary education', sequence('be_fr_s', 1, 7)]
    ],
    belgium_flemish: [
      ['maternelle', 'Enseignement maternel', 'Pre-primary education', sequence('be_nl_k', 1, 3)],
      ['primaire', 'Enseignement primaire', 'Primary education', sequence('be_nl_l', 1, 6)],
      ['secondaire', 'Enseignement secondaire', 'Secondary education', sequence('be_nl_s', 1, 7)]
    ],
    belgium_german: [
      ['maternelle', 'Enseignement maternel', 'Pre-primary education', sequence('be_de_k', 1, 3)],
      ['primaire', 'Enseignement primaire', 'Primary education', sequence('be_de_p', 1, 6)],
      ['secondaire', 'Enseignement secondaire', 'Secondary education', sequence('be_de_s', 1, 7)]
    ],
    uk_england: [
      ['early_years', 'Petite enfance', 'Early years', ['uk_england_nursery', 'uk_england_reception']],
      ['primary', 'Enseignement primaire', 'Primary education', sequence('uk_england_year_', 1, 6)],
      ['secondary', 'Enseignement secondaire', 'Secondary education', sequence('uk_england_year_', 7, 11)],
      ['sixth_form', 'Sixth form', 'Sixth form', sequence('uk_england_year_', 12, 13)]
    ],
    uk_wales: [
      ['early_years', 'Petite enfance', 'Early years', ['uk_wales_nursery', 'uk_wales_reception']],
      ['primary', 'Enseignement primaire', 'Primary education', sequence('uk_wales_year_', 1, 6)],
      ['secondary', 'Enseignement secondaire', 'Secondary education', sequence('uk_wales_year_', 7, 11)],
      ['sixth_form', 'Sixth form', 'Sixth form', sequence('uk_wales_year_', 12, 13)]
    ],
    uk_scotland: [
      ['early_years', 'Petite enfance', 'Early years', ['uk_scotland_early_learning']],
      ['primary', 'Enseignement primaire', 'Primary education', sequence('uk_scotland_p', 1, 7)],
      ['secondary', 'Enseignement secondaire', 'Secondary education', sequence('uk_scotland_s', 1, 6)]
    ],
    uk_northern_ireland: [
      ['early_years', 'Petite enfance', 'Early years', ['uk_northern_ireland_preschool']],
      ['primary', 'Enseignement primaire', 'Primary education', sequence('uk_northern_ireland_p', 1, 7)],
      ['secondary', 'Enseignement secondaire', 'Secondary education', sequence('uk_northern_ireland_year_', 8, 14)]
    ],
    european_schools: [
      ['nursery', 'Cycle maternel', 'Nursery cycle', sequence('eu_school_n', 1, 2)],
      ['primary', 'Cycle primaire', 'Primary cycle', sequence('eu_school_p', 1, 5)],
      ['observation', 'Cycle d’observation', 'Observation cycle', sequence('eu_school_s', 1, 3)],
      ['pre_orientation', 'Cycle de pré-orientation', 'Pre-orientation cycle', sequence('eu_school_s', 4, 5)],
      ['baccalaureate', 'Cycle du Baccalauréat européen', 'European Baccalaureate cycle', sequence('eu_school_s', 6, 7)]
    ],
    ib: [
      ['pyp', 'Programme primaire (PYP)', 'Primary Years Programme (PYP)', ['ib_pyp']],
      ['myp', 'Programme d’éducation intermédiaire (MYP)', 'Middle Years Programme (MYP)', sequence('ib_myp_', 1, 5)],
      ['diploma', 'Programme du diplôme (DP)', 'Diploma Programme (DP)', sequence('ib_dp_', 1, 2)],
      ['career', 'Programme à orientation professionnelle (CP)', 'Career-related Programme (CP)', sequence('ib_cp_', 1, 2)]
    ],
    isced_2011: [
      ['early', 'Éducation de la petite enfance', 'Early childhood education', ['isced_0']],
      ['primary', 'Enseignement primaire', 'Primary education', ['isced_1']],
      ['lower_secondary', 'Premier cycle du secondaire', 'Lower secondary education', ['isced_2']],
      ['upper_secondary', 'Deuxième cycle du secondaire et post-secondaire', 'Upper secondary and post-secondary education', ['isced_3', 'isced_4']],
      ['tertiary', 'Enseignement supérieur', 'Tertiary education', sequence('isced_', 5, 8)]
    ]
  };
  const config = () => window.LearningDesignerModules?.config || {};
  const stageEntries = system => (schoolStages[system] || []).map(([id, fr, english, levels]) => ({ id, label: text(fr, english), levels }));
  const stageForLevel = (system, level) => stageEntries(system).find(stage => stage.levels.includes(level));
  const splitFilterValue = value => {
    const separator = value.indexOf(':');
    return separator < 0 ? ['', value] : [value.slice(0, separator), value.slice(separator + 1)];
  };
  const node = (tag, className, value) => {
    const el = document.createElement(tag);
    el.className = className;
    if (value) el.textContent = value;
    return el;
  };
  const icon = (className) => {
    const el = node('i', className);
    el.setAttribute('aria-hidden', 'true');
    return el;
  };
  const duration = (minutes) => {
    const total = Math.max(0, Number(minutes) || 0);
    if (total < 60) return `${total} min`;
    return `${Math.floor(total / 60)} h${total % 60 ? ` ${String(total % 60).padStart(2, '0')}` : ''}`;
  };
  const modelChips = (entry) => {
    const chips = node('div', 'import-model-chips');
    [label(entry, 'familyLabel'), duration(entry.minutes), text(`${entry.momentCount} moments`, `${entry.momentCount} moments`), text(`${entry.activityCount} activités`, `${entry.activityCount} activities`)]
      .forEach(value => chips.append(node('span', 'import-model-chip', value)));
    return chips;
  };
  const action = (caption, href, iconClass = '') => {
    const el = node(href ? 'a' : 'button', 'btn btn-light import-model-action');
    const copy = node('span', 'btn-label');
    if (iconClass) copy.append(icon(`${iconClass} btn-icon-inline`));
    copy.append(document.createTextNode(caption));
    el.append(copy);
    if (href) el.href = href;
    else el.type = 'button';
    return el;
  };
  function listView() {
    fileSection.classList.toggle('hidden', mode !== 'shared');
    content.classList.remove('is-preview');
    back.classList.add('hidden');
    filters.classList.toggle('hidden', mode !== 'models');
    sharedFilters.classList.toggle('hidden', mode !== 'shared');
    filters.classList.remove('has-load-error');
    sharedFilters.classList.remove('has-load-error');
    title.textContent = mode === 'models' ? text('Importer un modèle', 'Import a template') : text('Importer un scénario partagé', 'Import a shared scenario');
  }
  function renderModels() {
    listView();
    content.replaceChildren();
    const tokens = search.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    const entries = catalog.models.filter(entry => {
      const types = (entry.outline || []).flatMap(moment => (moment.activities || []).map(activity => activity.type));
      const durationMatches = !modelDuration.value
        || (modelDuration.value === 'short' && entry.minutes <= 60)
        || (modelDuration.value === 'medium' && entry.minutes > 60 && entry.minutes <= 120)
        || (modelDuration.value === 'long' && entry.minutes > 120);
      return (!family.value || entry.family === family.value)
        && durationMatches
        && (!modelMoments.value || entry.momentCount === Number(modelMoments.value))
        && (!modelMode.value || entry.mode === modelMode.value)
        && (!modelType.value || types.includes(modelType.value))
        && tokens.every(token => [label(entry, 'title'), label(entry, 'summary'), label(entry, 'familyLabel'), entry.keywords].join(' ').toLocaleLowerCase().includes(token));
    });
    status.textContent = entries.length ? text(`${entries.length} modèles`, `${entries.length} templates`) : text('Aucun modèle ne correspond à votre recherche.', 'No templates match your search.');
    entries.forEach(entry => {
      const card = node('article', 'import-model-card');
      const head = node('div', 'import-model-head');
      head.append(icon(`${entry.icon || 'fa-solid fa-shapes'} import-model-icon`), node('span', 'import-model-title', label(entry, 'title')));
      card.append(head, modelChips(entry), node('span', 'import-model-summary', label(entry, 'summary')));
      const types = node('div', 'import-model-types');
      (entry.outline || []).forEach(moment => (moment.activities || []).forEach(activity => {
        const dot = node('span', `import-model-dot type-${activity.type}`);
        dot.title = label(activity, 'typeLabel');
        dot.setAttribute('aria-label', dot.title);
        types.append(dot);
      }));
      if (types.childElementCount) card.append(types);
      const actions = node('div', 'import-model-actions');
      const preview = action(text('Visualiser', 'Preview'), null, 'fa-solid fa-eye');
      preview.addEventListener('click', () => previewModel(entry));
      actions.append(preview, action(text('Importer', 'Import'), `designer.php?model=${encodeURIComponent(entry.id)}`, 'fa-solid fa-file-import'));
      card.append(actions);
      content.append(card);
    });
  }
  async function loadModels() {
    const id = ++requestId;
    listView();
    content.replaceChildren();
    status.textContent = text('Chargement des modèles…', 'Loading templates…');
    try {
      if (!catalog) {
        const response = await fetch('models.php?format=json&v=3');
        if (!response.ok) throw new Error('catalog');
        const result = await response.json();
        if (!Array.isArray(result.models)) throw new Error('catalog');
        catalog = result;
      }
      if (id !== requestId || !dialog.open) return;
      family.replaceChildren(new Option(text('Toutes les familles', 'All families'), ''));
      (catalog.families || []).forEach(item => family.add(new Option(label(item, 'label'), item.id)));
      modelDuration.replaceChildren(
        new Option(text('Toutes les durées', 'All durations'), ''),
        new Option(text('60 min ou moins', '60 min or less'), 'short'),
        new Option(text('De 61 à 120 min', '61 to 120 min'), 'medium'),
        new Option(text('Plus de 120 min', 'More than 120 min'), 'long')
      );
      fillSharedSelect(modelMoments, text('Tous les nombres de moments', 'Any number of moments'), uniqueSharedOptions(
        catalog.models.map(entry => ({ value: String(entry.momentCount), label: text(`${entry.momentCount} moments`, `${entry.momentCount} moments`) }))
      ));
      const modeLabels = {
        onsite: text('Présentiel', 'Onsite'),
        hybrid: text('Hybride', 'Blended'),
        online: text('Distanciel', 'Online')
      };
      fillSharedSelect(modelMode, text('Tous les modes', 'All delivery modes'), uniqueSharedOptions(
        catalog.models.map(entry => ({ value: entry.mode, label: modeLabels[entry.mode] || entry.mode }))
      ));
      fillSharedSelect(modelType, text('Tous les types d’apprentissage', 'All learning types'), uniqueSharedOptions(
        catalog.models.flatMap(entry => (entry.outline || []).flatMap(moment => (moment.activities || []).map(activity => ({
          value: activity.type,
          label: label(activity, 'typeLabel') || activity.type
        }))))
      ));
      modelDuration.setAttribute('aria-label', text('Durée', 'Duration'));
      modelMoments.setAttribute('aria-label', text('Nombre de moments', 'Number of moments'));
      modelMode.setAttribute('aria-label', text('Mode de diffusion', 'Delivery mode'));
      modelType.setAttribute('aria-label', text('Type d’apprentissage', 'Learning type'));
      modelReset.textContent = text('Réinitialiser', 'Reset');
      renderModels();
    } catch (_) { if (id === requestId) showError(loadModels); }
  }
  function showError(retry) {
    if (mode === 'shared') sharedFilters.classList.add('has-load-error');
    else filters.classList.add('has-load-error');
    status.textContent = text('Chargement impossible. Veuillez réessayer.', 'Unable to load. Please try again.');
    const button = action(text('Réessayer', 'Try again'));
    button.addEventListener('click', retry);
    content.replaceChildren(button);
  }
  function previewView(caption) {
    fileSection.classList.add('hidden');
    title.textContent = caption;
    filters.classList.add('hidden');
    sharedFilters.classList.add('hidden');
    back.classList.remove('hidden');
    content.classList.add('is-preview');
    content.replaceChildren();
    status.textContent = text('Chargement de l’aperçu…', 'Loading preview…');
    back.focus();
  }
  function paragraph(parent, heading, value) {
    if (!value) return;
    const block = node('div', 'import-model-preview-text');
    block.append(node('strong', '', heading), node('p', '', value));
    parent.append(block);
  }
  async function previewModel(entry) {
    const id = ++requestId;
    previewView(label(entry, 'title'));
    try {
      const response = await fetch(`models.php?format=json&model=${encodeURIComponent(entry.id)}&lang=${en() ? 'en' : 'fr'}`);
      if (!response.ok) throw new Error('preview');
      const payload = await response.json();
      if (id !== requestId || !dialog.open) return;
      if (!Array.isArray(payload.design?.sessions)) throw new Error('preview');
      status.textContent = label(entry, 'summary');
      content.append(modelChips(entry));
      payload.design.sessions.forEach((session, index) => {
        const moment = node('section', 'import-model-preview-moment');
        const header = node('header', 'import-model-preview-moment-header');
        header.append(node('span', 'import-model-preview-moment-number', String(index + 1)), node('h3', '', String(session.title || '').replace(/^\s*\d+\s*[.)·:–—-]\s*/, '')));
        moment.append(header);
        paragraph(moment, text('Objectifs', 'Objectives'), session.objectives);
        const activities = node('div', 'import-model-preview-activities');
        (session.activities || []).forEach((activity, activityIndex) => {
          const card = node('article', `import-model-preview-activity type-${activity.type}`);
          const activityHeader = node('header', 'import-model-preview-activity-header');
          const meta = node('span', 'import-model-preview-activity-meta');
          const outline = entry.outline?.[index]?.activities?.[activityIndex];
          meta.append(node('span', `import-model-preview-type type-${activity.type}`, label(outline || {}, 'typeLabel') || activity.type), node('span', '', duration(activity.duration)));
          activityHeader.append(node('strong', '', text(`Activité ${activityIndex + 1}`, `Activity ${activityIndex + 1}`)), meta);
          card.append(activityHeader);
          paragraph(card, text('Enseignant', 'Teacher'), activity.description);
          paragraph(card, text('Consignes aux élèves', 'Student instructions'), activity.instructions);
          activities.append(card);
        });
        moment.append(activities);
        content.append(moment);
      });
      content.append(action(text('Importer ce modèle', 'Import this template'), `designer.php?model=${encodeURIComponent(entry.id)}`, 'fa-solid fa-file-import'));
    } catch (_) { if (id === requestId) showError(() => previewModel(entry)); }
  }
  // Reuse the server-rendered gallery, including its authenticated import forms.
  // Only content is copied; scripts, inline styles and event handlers are excluded.
  function clean(element) {
    element.querySelectorAll('script, style, iframe, object, embed').forEach(el => el.remove());
    [element, ...element.querySelectorAll('*')].forEach(el => {
      [...el.attributes].forEach(attr => {
        if (attr.name === 'style' || attr.name.startsWith('on')) el.removeAttribute(attr.name);
      });
      if (el.dataset.siteI18nEn && !el.dataset.siteI18nAttr) el.textContent = en() ? el.dataset.siteI18nEn : el.dataset.siteI18nFr;
    });
    return element;
  }
  function parsedData(value, fallback) {
    try { return JSON.parse(value || ''); } catch (_) { return fallback; }
  }
  function fillSharedSelect(select, caption, entries, sort = true) {
    const selected = select.value;
    select.replaceChildren(new Option(caption, ''));
    const options = sort ? [...entries].sort((a, b) => a.label.localeCompare(b.label, en() ? 'en' : 'fr')) : entries;
    options.forEach(entry => select.add(new Option(entry.label, entry.value)));
    if ([...select.options].some(option => option.value === selected)) select.value = selected;
  }
  function uniqueSharedOptions(entries) {
    const unique = new Map();
    entries.forEach(entry => {
      if (entry.value && entry.label && !unique.has(entry.value)) unique.set(entry.value, entry);
    });
    return [...unique.values()];
  }
  function updateSharedSchoolOptions() {
    const systems = config().SCHOOL_SYSTEM_OPTIONS || [];
    const levelsBySystem = config().SCHOOL_LEVEL_OPTIONS || {};
    const systemLabels = new Map(systems.map(item => [item.value, item.labels[en() ? 'en' : 'fr'] || item.labels.fr]));
    const selectedSystem = sharedSystem.value;
    const systemsForStages = selectedSystem ? systems.filter(item => item.value === selectedSystem) : systems;
    const stages = systemsForStages.flatMap(system => stageEntries(system.value).map(stage => ({
      value: `${system.value}:${stage.id}`,
      label: selectedSystem ? stage.label : `${systemLabels.get(system.value)} — ${stage.label}`
    })));
    fillSharedSelect(sharedStage, text('Tous les cycles', 'All stages'), stages, false);
    const [stageSystem, stageId] = splitFilterValue(sharedStage.value);
    const systemsForLevels = selectedSystem
      ? systems.filter(item => item.value === selectedSystem)
      : stageSystem
        ? systems.filter(item => item.value === stageSystem)
        : systems;
    const levels = systemsForLevels.flatMap(system => (levelsBySystem[system.value] || [])
      .filter(level => !stageId || stageForLevel(system.value, level.value)?.id === stageId)
      .map(level => ({
        value: `${system.value}:${level.value}`,
        label: systemsForLevels.length === 1
          ? (level.labels[en() ? 'en' : 'fr'] || level.labels.fr)
          : `${systemLabels.get(system.value)} — ${level.labels[en() ? 'en' : 'fr'] || level.labels.fr}`
      })));
    fillSharedSelect(sharedLevel, text('Tous les niveaux', 'All levels'), levels, false);
  }
  async function prepareSharedFilters() {
    const systems = (config().SCHOOL_SYSTEM_OPTIONS || []).map(item => ({
      value: item.value,
      label: item.labels[en() ? 'en' : 'fr'] || item.labels.fr
    }));
    fillSharedSelect(sharedSystem, text('Tous les systèmes', 'All systems'), systems, false);
    updateSharedSchoolOptions();
    let competencies = [];
    try {
      const response = await fetch('data/cli-competencies.json');
      if (!response.ok) throw new Error('competencies');
      const competencyCatalog = await response.json();
      competencies = (competencyCatalog.competencies || []).map(item => ({
        value: item.id,
        label: item[en() ? 'labelEn' : 'labelFr'] || item.labelFr || item.id
      }));
    } catch (_) {
      sharedCards.forEach(card => parsedData(card.dataset.competencies, []).forEach(item => competencies.push({
        value: item.id,
        label: item[en() ? 'en' : 'fr'] || item.fr || item.id
      })));
    }
    fillSharedSelect(sharedCompetency, text('Toutes les compétences', 'All competencies'), uniqueSharedOptions(competencies));
    sharedSystem.setAttribute('aria-label', text('Système scolaire', 'School system'));
    sharedStage.setAttribute('aria-label', text('Cycle scolaire', 'School stage'));
    sharedLevel.setAttribute('aria-label', text('Niveau', 'Level'));
    sharedCompetency.setAttribute('aria-label', text('Compétence', 'Competency'));
    sharedReset.textContent = text('Réinitialiser', 'Reset');
  }
  function applySharedFilters() {
    const [stageSystem, stageId] = splitFilterValue(sharedStage.value);
    const [levelSystem, levelId] = splitFilterValue(sharedLevel.value);
    let visible = 0;
    sharedCards.forEach(card => {
      const competencyIds = parsedData(card.dataset.competencies, []).map(item => item.id);
      const cardStage = stageForLevel(card.dataset.schoolSystem, card.dataset.schoolLevel);
      const matches = (!sharedSystem.value || card.dataset.schoolSystem === sharedSystem.value)
        && (!sharedStage.value || (card.dataset.schoolSystem === stageSystem && cardStage?.id === stageId))
        && (!sharedLevel.value || (card.dataset.schoolSystem === levelSystem && card.dataset.schoolLevel === levelId))
        && (!sharedCompetency.value || competencyIds.includes(sharedCompetency.value));
      card.hidden = !matches;
      if (matches) visible += 1;
    });
    status.textContent = visible
      ? text(`${visible} scénario${visible > 1 ? 's' : ''}`, `${visible} scenario${visible > 1 ? 's' : ''}`)
      : text('Aucun scénario ne correspond à ces filtres.', 'No scenarios match these filters.');
  }
  async function loadShared(url = sharedUrl) {
    sharedUrl = url;
    const id = ++requestId;
    listView();
    content.replaceChildren();
    status.textContent = text('Chargement des scénarios…', 'Loading scenarios…');
    try {
      const requestUrl = new URL(url, window.location.href);
      requestUrl.searchParams.set('embed', '1');
      const response = await fetch(requestUrl);
      if (!response.ok) throw new Error('gallery');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (id !== requestId || !dialog.open) return;
      const elements = html.querySelectorAll('.shared-card, .shared-empty, .shared-pagination');
      if (!elements.length) throw new Error('gallery');
      status.textContent = '';
      elements.forEach(el => content.append(clean(el)));
      sharedCards = [...content.querySelectorAll('.shared-card')];
      await prepareSharedFilters();
      if (id !== requestId || !dialog.open) return;
      applySharedFilters();
      content.querySelectorAll('a[href^="view.php?"]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        previewShared(link.href);
      }));
      content.querySelectorAll('.shared-pagination a').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        void loadShared(link.href);
      }));
    } catch (_) { if (id === requestId) showError(() => loadShared(url)); }
  }
  async function previewShared(url) {
    const id = ++requestId;
    previewView(text('Aperçu du scénario', 'Scenario preview'));
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error('preview');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (id !== requestId || !dialog.open) return;
      const main = html.querySelector('main');
      if (!main) throw new Error('preview');
      status.textContent = '';
      main.className = 'shared-preview';
      main.querySelectorAll('.sessions-toolbar').forEach(el => el.remove());
      content.append(clean(main));
    } catch (_) { if (id === requestId) showError(() => previewShared(url)); }
  }
  document.querySelectorAll('[data-import-dialog]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    trigger = link;
    mode = link.getAttribute('href') === 'models.php' ? 'models' : 'shared';
    search.value = '';
    search.placeholder = text('Rechercher un modèle…', 'Search templates…');
    search.setAttribute('aria-label', text('Rechercher un modèle', 'Search templates'));
    family.setAttribute('aria-label', text('Famille de modèles', 'Template family'));
    [family, modelDuration, modelMoments, modelMode, modelType].forEach(select => { select.value = ''; });
    [sharedSystem, sharedStage, sharedLevel, sharedCompetency].forEach(select => { select.value = ''; });
    const closeLabel = text('Fermer', 'Close');
    close.setAttribute('aria-label', closeLabel);
    back.textContent = text('Retour', 'Back');
    dialog.showModal();
    if (mode === 'models') void loadModels();
    else void loadShared('share.php');
    close.focus();
  }));
  const fileInput = document.getElementById('new-design-file-input');
  const fileButton = document.getElementById('new-design-file-button');
  const fileStatus = document.getElementById('new-design-file-status');
  let transferring = false;
  async function importLocalFile(files) {
    if (transferring || !files.length) return;
    if (files.length !== 1 || files[0].size > 5 * 1024 * 1024 || !/\.(ldj|json|csv|xlsx|md|markdown)$/i.test(files[0].name)) {
      fileStatus.textContent = text('Choisissez un seul fichier LDJ, JSON, CSV, Excel ou Markdown de 5 Mo maximum.', 'Choose one LDJ, JSON, CSV, Excel or Markdown file, up to 5 MB.');
      return;
    }
    transferring = true;
    fileButton.disabled = true;
    fileStatus.textContent = text('Ouverture du fichier…', 'Opening file…');
    try {
      const id = await window.learningDesignerFileTransfer.store(files[0]);
      window.location.href = `designer.php?import_file=${encodeURIComponent(id)}`;
    } catch (_) {
      fileStatus.textContent = text('Impossible d’ouvrir ce fichier. Veuillez réessayer.', 'Unable to open this file. Please try again.');
      transferring = false;
      fileButton.disabled = false;
    }
  }
  fileButton.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => { void importLocalFile(fileInput.files); fileInput.value = ''; });
  const dropMessage = document.getElementById('new-design-drop-message');
  const isFileDrag = event => Array.from(event.dataTransfer?.types || []).includes('Files');
  function setDropActive(active) {
    if (dialog.classList.contains('is-dragover') === active) return;
    dialog.classList.toggle('is-dragover', active);
    dialog.setAttribute('aria-labelledby', active ? 'new-design-drop-message' : 'new-design-import-title');
    dropMessage.textContent = active
      ? text('Relâchez pour importer le fichier', 'Release to import the file')
      : '';
  }
  function resetDrop() { setDropActive(false); }
  const canDrop = event => mode === 'shared' && isFileDrag(event) && !transferring;
  dialog.addEventListener('dragenter', event => {
    if (!canDrop(event)) return;
    event.preventDefault();
    setDropActive(true);
  });
  dialog.addEventListener('dragover', event => {
    if (!canDrop(event)) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
    setDropActive(true);
  });
  dialog.addEventListener('dragleave', event => {
    // Hiding the original contents can emit child leave events. Only reset
    // when the pointer actually leaves the whole dialog.
    const rect = dialog.getBoundingClientRect();
    if (event.clientX <= rect.left || event.clientX >= rect.right || event.clientY <= rect.top || event.clientY >= rect.bottom) resetDrop();
  });
  dialog.addEventListener('drop', event => {
    if (mode !== 'shared' || !isFileDrag(event)) return;
    event.preventDefault();
    resetDrop();
    void importLocalFile(event.dataTransfer.files);
  });
  document.addEventListener('dragend', resetDrop);
  dialog.addEventListener('close', resetDrop);
  search.addEventListener('input' , () => { if (catalog) renderModels(); });
  [family, modelDuration, modelMoments, modelMode, modelType].forEach(select => select.addEventListener('change', () => { if (catalog) renderModels(); }));
  modelReset.addEventListener('click', () => {
    search.value = '';
    [family, modelDuration, modelMoments, modelMode, modelType].forEach(select => { select.value = ''; });
    if (catalog) renderModels();
  });
  sharedSystem.addEventListener('change', () => {
    sharedStage.value = '';
    sharedLevel.value = '';
    updateSharedSchoolOptions();
    applySharedFilters();
  });
  sharedStage.addEventListener('change', () => {
    sharedLevel.value = '';
    updateSharedSchoolOptions();
    applySharedFilters();
  });
  [sharedLevel, sharedCompetency].forEach(select => select.addEventListener('change', applySharedFilters));
  sharedReset.addEventListener('click', () => {
    [sharedSystem, sharedStage, sharedLevel, sharedCompetency].forEach(select => { select.value = ''; });
    updateSharedSchoolOptions();
    applySharedFilters();
  });
  back.addEventListener('click', () => {
    ++requestId;
    if (mode === 'models') renderModels();
    else void loadShared();
    close.focus();
  });
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
  });
  dialog.addEventListener('close', () => { ++requestId; trigger?.focus(); });
})();
