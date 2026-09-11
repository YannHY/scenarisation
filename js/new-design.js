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
  const status = document.getElementById('new-design-import-status');
  const content = document.getElementById('new-design-import-content');
  const back = document.getElementById('new-design-import-back');
  const close = document.getElementById('new-design-import-close');
  let catalog;
  let mode;
  let trigger;
  let requestId = 0;
  let sharedUrl = 'share.php';
  const en = () => document.documentElement.lang === 'en';
  const text = (fr, english) => en() ? english : fr;
  const label = (entry, key) => entry[key + (en() ? 'En' : 'Fr')] || entry[key + 'Fr'] || '';
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
    title.textContent = mode === 'models' ? text('Importer un modèle', 'Import a template') : text('Importer un scénario partagé', 'Import a shared scenario');
  }
  function renderModels() {
    listView();
    content.replaceChildren();
    const tokens = search.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    const entries = catalog.models.filter(entry => (!family.value || entry.family === family.value) && tokens.every(token => [label(entry, 'title'), label(entry, 'summary'), label(entry, 'familyLabel'), entry.keywords].join(' ').toLocaleLowerCase().includes(token)));
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
      renderModels();
    } catch (_) { if (id === requestId) showError(loadModels); }
  }
  function showError(retry) {
    status.textContent = text('Chargement impossible. Veuillez réessayer.', 'Unable to load. Please try again.');
    const button = action(text('Réessayer', 'Try again'));
    button.addEventListener('click', retry);
    content.replaceChildren(button);
  }
  function previewView(caption) {
    fileSection.classList.add('hidden');
    title.textContent = caption;
    filters.classList.add('hidden');
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
  async function loadShared(url = sharedUrl) {
    sharedUrl = url;
    const id = ++requestId;
    listView();
    content.replaceChildren();
    status.textContent = text('Chargement des scénarios…', 'Loading scenarios…');
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error('gallery');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (id !== requestId || !dialog.open) return;
      const elements = html.querySelectorAll('.shared-card, .shared-empty, .shared-pagination');
      if (!elements.length) throw new Error('gallery');
      status.textContent = '';
      elements.forEach(el => content.append(clean(el)));
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
    close.textContent = text('Fermer', 'Close');
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
  family.addEventListener('change', () => { if (catalog) renderModels(); });
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
