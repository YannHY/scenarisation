const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadModules() {
  const context = vm.createContext({ window: {}, TextEncoder });
  // Use the order shipped to the browser, including the real competency sources.
  const designer = fs.readFileSync(path.join(__dirname, '../designer.php'), 'utf8');
  const scripts = [...designer.matchAll(/<script src="(js\/(?:competency-[^"?]+|editor\/[^"?]+)\.js)\?/g)];
  for (const [, filename] of scripts) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '..', filename), 'utf8'), context, { filename });
  }
  return context;
}

test('the deployed modules load without DOM access or editor initialization', () => {
  const context = loadModules();
  const modules = context.window.LearningDesignerModules;
  for (const factory of ['createCompetencies', 'createExports', 'createImports', 'createAnalysis', 'createFields']) {
    assert.equal(typeof modules[factory], 'function', factory);
  }
  assert.equal(modules.config.I18N.fr.save, 'Enregistrer');
  assert.equal(modules.config.I18N.en.save, 'Save');
});

test('competency labels follow language changes without rebuilding the catalogs', () => {
  const context = loadModules();
  vm.runInContext(`
    let language = 'fr';
    globalThis.competencies = window.LearningDesignerModules.createCompetencies({
      COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
      COMPETENCY_FRAMEWORK_CATALOG_SOURCE, COMPETENCY_GREENCOMP_DETAIL_SOURCE,
      COMPETENCY_DIGCOMP_DETAIL_SOURCE,
      normalizeToken: window.LearningDesignerModules.config.normalizeToken,
      currentLang: () => language
    });
  `, context);
  const catalog = context.competencies;
  const entry = catalog.SELECTABLE_TOOLS_DATA.find(tool => tool.labelFr !== tool.labelEn);
  assert.ok(entry);
  const french = catalog.formatCompetencyLabel(entry);
  vm.runInContext("language = 'en'", context);
  assert.notEqual(catalog.formatCompetencyLabel(entry), french);
  assert.ok(catalog.COMPETENCY_REFERENCE_MAP[context.window.LearningDesignerModules.config.normalizeToken(entry.id)]);
});

function documentWith(name, id, instructions) {
  return { meta: { name }, sessions: [{ id, title: name, activities: [{ instructions }] }] };
}

for (const format of ['Markdown', 'Html', 'Word', 'Excel']) {
  test(`${format} student export reads the replacement document and applies session selection`, () => {
    const modules = loadModules().window.LearningDesignerModules;
    let current = documentWith('Original title', 'old', 'Original instructions');
    const exporter = modules.createExports({ getState: () => current, escapeHtml: text => String(text) });
    const build = exporter[`build${format}Export${format === 'Markdown' ? '' : 'Document'}`];
    const decode = content => typeof content === 'string' ? content : new TextDecoder().decode(content);
    assert.match(decode(build('students')), /Original instructions/);
    current = documentWith('Replacement title', 'new', 'Replacement instructions');
    current.sessions.push({ id: 'excluded', title: 'Excluded title', activities: [{ instructions: 'Private instructions' }] });
    const selected = decode(build('students', ['new']));
    assert.match(selected, /Replacement instructions/);
    assert.doesNotMatch(selected, /Original instructions|Private instructions/);
    assert.doesNotMatch(decode(build('students', [])), /Replacement instructions|Private instructions/);
  });
}

test('PER preserves official objectives, components and merged-table learning entries', () => {
  const context = loadModules();
  vm.runInContext(`globalThis.perCatalog = window.LearningDesignerModules.createCompetencies({
    COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
    COMPETENCY_FRAMEWORK_CATALOG_SOURCE, COMPETENCY_GREENCOMP_DETAIL_SOURCE,
    COMPETENCY_DIGCOMP_DETAIL_SOURCE,
    normalizeToken: window.LearningDesignerModules.config.normalizeToken,
    currentLang: () => 'fr'
  });`, context);
  const catalog = context.perCatalog;
  const source = JSON.parse(fs.readFileSync(path.join(__dirname, '../data/references/per-numerique.json'), 'utf8'));
  const entries = catalog.SELECTABLE_TOOLS_DATA.filter(item => item.frameworkId === 'per-romand');
  const byCode = new Map(entries.map(item => [item.number, item]));
  assert.equal(entries.length, 227);
  assert.equal(byCode.size, entries.length);
  const framework = catalog.COMPETENCY_FRAMEWORKS.find(item => item.id === 'per-romand');
  for (const page of source.pages) {
    const code = page.title.split(' - ')[0];
    const group = framework.groups.find(item => item.id === code.toLowerCase().replace(' ', ''));
    assert.equal(group.labelFr, page.title);
    assert.equal(group.sourceUrl, `https://portail.ciip.ch/per/learning-objectives/${page.id}`);
    for (const component of page.components) {
      const [, n, label] = component.match(/^(\d+)(.*)$/);
      const item = byCode.get(`${code}.${n}`);
      assert.equal(item.labelFr, label);
      assert.equal(catalog.COMPETENCY_REFERENCE_MAP[context.window.LearningDesignerModules.config.normalizeToken(item.id)], item.id);
    }
    for (const item of entries.filter(item => item.groupId === group.id && /\.[PA]\d+$/.test(item.number))) {
      const anchor = item.number.match(/\.[PA](\d+)$/)[1];
      const cell = page.rows.flat().find(cell => cell.id === anchor);
      assert.equal(item.labelFr, cell.text);
      assert.equal(item.sourceUrl, `${group.sourceUrl}#${anchor}`);
    }
  }
  // A cell can contain an empty paragraph before its real content.
  assert.equal(byCode.get('EN 11.P18414').labelFr, 'Réalisation de créations médiatiques variées');
  // The fundamental expectation spans two programming rows on the CIIP page.
  assert.match(byCode.get('EN 32.P18973').descFr, /programmes optimisés/);
  assert.match(byCode.get('EN 32.P18979').descFr, /10e année \/ 11e année/);
  assert.doesNotMatch(byCode.get('EN 32.P18979').descFr, /9e année/);
  assert.equal(byCode.has('EN 31.P18332'), false, 'Cross-disciplinary links are not competencies');
});

test('CRCN includes official level indicators and retains existing competency IDs', () => {
  const context = loadModules();
  vm.runInContext(`globalThis.crcnCatalog = window.LearningDesignerModules.createCompetencies({
    COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
    COMPETENCY_FRAMEWORK_CATALOG_SOURCE, COMPETENCY_GREENCOMP_DETAIL_SOURCE,
    COMPETENCY_DIGCOMP_DETAIL_SOURCE,
    normalizeToken: window.LearningDesignerModules.config.normalizeToken,
    currentLang: () => 'fr'
  });`, context);
  const catalog = context.crcnCatalog;
  const items = catalog.SELECTABLE_TOOLS_DATA.filter(item => item.frameworkId === 'crcn');
  const byCode = new Map(items.map(item => [item.number, item]));
  assert.equal(items.length, 178);
  assert.equal(byCode.size, 178);
  assert.equal(items.filter(item => /^\d\.\d$/.test(item.number)).length, 16);
  for (const item of items) {
    assert.equal(catalog.COMPETENCY_REFERENCE_MAP[context.window.LearningDesignerModules.config.normalizeToken(item.id)], item.id);
    assert.match(item.sourceUrl, /eduscol\.education\.gouv\.fr\/media\/80451\/download\?attachment#page=\d+$/);
  }
  assert.equal(byCode.get('1.1.N1.1').labelFr, 'Lire et repérer des informations sur un support numérique');
  assert.equal(byCode.get('1.1.N5.2').labelFr, 'Utiliser un ou plusieurs logiciels spécialisés pour mettre en place une veille');
  assert.equal(byCode.get('1.2.N2.1').labelFr, "Sauvegarder des fichiers dans l'ordinateur utilisé, et dans un espace de stockage partagé et sécurisé, afin de pouvoir les réutiliser");
  assert.equal(byCode.has('4.1.N1.1'), false);
  assert.equal(byCode.get('4.1.N2.1').labelFr, 'Identifier les risques principaux qui menacent son environnement informatique');
  assert.equal(byCode.get('5.2.N5.3').labelFr, 'Connaître les grandes lignes des modèles économiques du numérique');
  for (const item of items) {
    assert.doesNotMatch(item.descFr, /Références au socle commun/);
    assert.doesNotMatch(item.descEn, /Références au socle commun/);
  }
  for (const item of items) {
    assert.doesNotMatch(item.descFr, /Thématiques et mots-clés associés/);
    assert.doesNotMatch(item.descEn, /Thématiques et mots-clés associés/);
  }
  assert.equal(byCode.get('5.2.N5.3').sourceUrl.endsWith('#page=18'), true);
});
