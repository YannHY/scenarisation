const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const root = path.join(__dirname, '..');
const cli = path.join(root, 'bin', 'scenarisation');
const catalogPath = path.join(root, 'data', 'cli-competencies.json');

function run(args, options = {}) {
  return spawnSync('python3', [cli, ...args], {
    cwd: root,
    encoding: 'utf8',
    env: { ...process.env, SCENARISATION_NO_BANNER: '1' },
    ...options
  });
}

function expectSuccess(result) {
  assert.equal(result.status, 0, result.stderr || result.stdout);
  return result.stdout;
}

test('CLI catalog exposes every editor competency framework with labels', () => {
  const catalog = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
  assert.deepEqual(
    catalog.frameworks.map(framework => framework.id),
    ['florimont', 'socle', 'greencomp', 'digcomp', 'per-romand', 'crcn', 'pix', 'pix-ia']
  );
  assert.equal(catalog.competencies.length, 1198);

  const frameworks = expectSuccess(run(['list', 'competency-frameworks']));
  for (const framework of catalog.frameworks) {
    assert.match(frameworks, new RegExp(`^${framework.id}\\s`, 'm'));
  }

  const crcn = expectSuccess(run(['list', 'competencies', '--framework', 'crcn', '--search', 'sources', '--details']));
  assert.match(crcn, /crcn:1\.1\.N2\.2\s+Questionner la fiabilité et la pertinence des sources/);

  const pixAi = JSON.parse(expectSuccess(run([
    'list', 'competencies', '--framework', 'pix-ia', '--search', 'générative', '--format', 'json'
  ])));
  assert.ok(pixAi.some(item => item.id === 'competency:pix-ia:2.3'));
});

test('CLI resolves competencies inside declared frameworks and preserves legacy aliases', () => {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'scenarisation-cli-'));
  const design = path.join(directory, 'design.json');
  try {
    expectSuccess(run([
      'init', design, '--title', 'Test', '--duration', '45',
      '--competency-framework', 'crcn', '--competency-framework', 'pix-ia'
    ]));
    expectSuccess(run(['add-moment', design, '--title', 'Rechercher']));
    expectSuccess(run([
      'add-activity', design, '--type', 'investigate', '--duration', '45',
      '--group', 'subgroups', '--teaching', 'guided', '--pacing', 'sync',
      '--mode', 'onsite', '--evaluation', 'formative', '--aias', '3',
      '--competencies', '1.1.N2.2,pix-ia:2.3'
    ]));
    expectSuccess(run(['validate', design, '--strict-pedagogy']));

    const document = JSON.parse(fs.readFileSync(design, 'utf8'));
    assert.deepEqual(document.meta.competencyFrameworks, ['crcn', 'pix-ia']);
    assert.deepEqual(document.sessions[0].activities[0].tools, [
      'competency:crcn:1.1.N2.2',
      'competency:pix-ia:2.3'
    ]);

    const legacyDesign = path.join(directory, 'legacy.json');
    expectSuccess(run(['init', legacyDesign, '--title', 'Legacy', '--duration', '10']));
    expectSuccess(run(['add-moment', legacyDesign, '--title', 'Moment']));
    expectSuccess(run([
      'add-activity', legacyDesign, '--type', 'read', '--duration', '10',
      '--group', 'whole', '--teaching', 'directed', '--pacing', 'sync',
      '--mode', 'onsite', '--evaluation', 'none', '--aias', 'not-applicable',
      '--competencies', 'A1'
    ]));
    assert.deepEqual(
      JSON.parse(fs.readFileSync(legacyDesign, 'utf8')).sessions[0].activities[0].tools,
      ['competency:acquerir:1']
    );
  } finally {
    fs.rmSync(directory, { recursive: true, force: true });
  }
});

test('CLI rejects unknown and undeclared competency identifiers', () => {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'scenarisation-cli-invalid-'));
  const design = path.join(directory, 'design.json');
  try {
    expectSuccess(run(['init', design, '--title', 'Test', '--duration', '10', '--competency-framework', 'crcn']));
    expectSuccess(run(['add-moment', design, '--title', 'Moment']));
    const unknown = run([
      'add-activity', design, '--type', 'read', '--duration', '10', '--group', 'whole',
      '--teaching', 'directed', '--pacing', 'sync', '--mode', 'onsite',
      '--evaluation', 'none', '--aias', 'not-applicable', '--competencies', '9.9'
    ]);
    assert.notEqual(unknown.status, 0);
    assert.match(unknown.stderr, /unknown digital competency: 9\.9/);

    const document = JSON.parse(fs.readFileSync(design, 'utf8'));
    document.sessions[0].activities.push({
      type: 'read', duration: 10, groupMode: 'whole', teachingMode: 'directed',
      syncMode: 'sync', locationMode: 'onsite', evaluationMode: 'none',
      aias: { version: '2.1', status: 'not_applicable', level: null },
      tools: ['competency:pix:1.1']
    });
    fs.writeFileSync(design, `${JSON.stringify(document)}\n`);
    const invalid = run(['validate', design]);
    assert.notEqual(invalid.status, 0);
    assert.match(invalid.stdout, /uses undeclared framework pix/);
  } finally {
    fs.rmSync(directory, { recursive: true, force: true });
  }
});
