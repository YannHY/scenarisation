// Navigation et interactions de l’aide embarquée, sans dépendance réseau.
(() => {
  const appearance = matchMedia('(prefers-color-scheme: dark)');
  function syncAppearance() {
    document.documentElement.dataset.theme = appearance.matches ? 'dark' : 'light';
  }
  syncAppearance();
  appearance.addEventListener('change', syncAppearance);

  function prepare() {
    document.querySelectorAll('.site-guide > .help-section').forEach((section, index) => {
      if (section.dataset.prepared) return;
      section.dataset.prepared = 'true';
      const number = String(index + 1).padStart(2, '0');
      const heading = section.querySelector(':scope > h2');
      const title = heading.textContent;
      const prefix = document.createElement('span');
      prefix.className = 'help-heading-index';
      prefix.setAttribute('aria-hidden', 'true');
      prefix.textContent = number + '.';
      heading.prepend(prefix);
      section.querySelectorAll(':scope > h3').forEach((subheading, subindex) => {
        const subprefix = document.createElement('span');
        subprefix.className = 'help-subheading-index';
        subprefix.setAttribute('aria-hidden', 'true');
        subprefix.textContent = number + '.' + (subindex + 1) + ' ';
        subheading.prepend(subprefix);
      });
      const intro = heading.nextElementSibling;
      const details = document.createElement('details');
      details.className = 'help-chapter';
      details.open = true;
      const summary = document.createElement('summary');
      summary.setAttribute('aria-label', 'Afficher ou réduire le chapitre : ' + title);
      summary.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
      const content = document.createElement('div');
      content.className = 'help-section-body';
      let node = intro?.tagName === 'P' ? intro.nextSibling : heading.nextSibling;
      while (node) {
        const next = node.nextSibling;
        content.append(node);
        node = next;
      }
      details.append(summary, content);
      section.append(details);
    });
  }
  prepare();
  document.addEventListener('rich-content-updated', prepare);
  document.addEventListener('click', async event => {
    const link = event.target.closest('a[href^="#"]');
    if (link) {
      event.preventDefault();
      try { reveal(decodeURIComponent(link.getAttribute('href').slice(1))); } catch (_) {}
      return;
    }
    const button = event.target.closest('.help-copy-btn');
    if (!button) return;
    const source = button.parentElement.querySelector('.help-code, .help-prompt');
    if (!source) return;
    const value = ('value' in source ? source.value : source.textContent).trim();
    try {
      await navigator.clipboard.writeText(value);
    } catch (_) {
      const field = document.createElement('textarea');
      field.value = value;
      field.style.cssText = 'position:fixed;top:-10000px';
      document.body.append(field);
      field.select();
      document.execCommand('copy');
      field.remove();
    }
    const original = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
    button.title = 'Copié';
    setTimeout(() => { button.innerHTML = original; button.title = 'Copier'; }, 1300);
  });
})();
