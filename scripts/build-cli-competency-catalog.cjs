#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const context = vm.createContext({ window: {}, TextEncoder });
const designer = fs.readFileSync(path.join(root, 'designer.php'), 'utf8');
const scripts = [...designer.matchAll(/<script src="(js\/(?:competency-[^"?]+|editor\/[^"?]+)\.js)\?/g)];

for (const [, filename] of scripts) {
  vm.runInContext(fs.readFileSync(path.join(root, filename), 'utf8'), context, { filename });
}

vm.runInContext(`
  globalThis.cliCompetencyCatalog = window.LearningDesignerModules.createCompetencies({
    COMPETENCY_CATALOG_SOURCE, COMPETENCY_CATALOG_EN_SOURCE,
    COMPETENCY_FRAMEWORK_CATALOG_SOURCE, COMPETENCY_GREENCOMP_DETAIL_SOURCE,
    COMPETENCY_DIGCOMP_DETAIL_SOURCE,
    normalizeToken: window.LearningDesignerModules.config.normalizeToken,
    currentLang: () => 'fr'
  });
`, context);

const catalog = context.cliCompetencyCatalog;
const unique = values => [...new Set(values.map(value => String(value || '').trim()).filter(Boolean))];
const output = {
  version: 1,
  frameworks: catalog.COMPETENCY_FRAMEWORKS.map(framework => ({
    id: framework.id,
    labelFr: framework.labelFr,
    labelEn: framework.labelEn,
    sourceUrl: framework.sourceUrl,
    groups: framework.groups.map(group => ({
      id: group.id,
      labelFr: group.labelFr,
      labelEn: group.labelEn,
      sourceUrl: group.sourceUrl || ''
    }))
  })),
  competencies: catalog.SELECTABLE_TOOLS_DATA
    .filter(item => !item.pickerHidden)
    .map(item => ({
      id: item.id,
      frameworkId: item.frameworkId,
      groupId: item.groupId,
      code: String(item.displayCode || item.number),
      labelFr: item.labelFr,
      labelEn: item.labelEn,
      descriptionFr: item.descFr || '',
      descriptionEn: item.descEn || '',
      sourceUrl: item.sourceUrl || '',
      aliases: unique([
        item.shortCode,
        item.shortCodeFr,
        item.shortCodeEn,
        item.legacyShortCode
      ])
    }))
};

const destination = path.join(root, 'data', 'cli-competencies.json');
fs.writeFileSync(destination, `${JSON.stringify(output)}\n`);
console.log(`Wrote ${destination} (${output.frameworks.length} frameworks, ${output.competencies.length} competencies)`);
