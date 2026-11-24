const { test } = require('node:test');
const assert = require('node:assert/strict');
const Merge = require('../js/scenario-merge.js');

// Mêmes cas que ScenarioMergeTests.swift : le site et les apps doivent fusionner de la même manière.
const clone = value => JSON.parse(JSON.stringify(value));
const activity = (id, extra = {}) => ({ id, type: 'acquisition', duration: 10, description: '', instructions: '', notes: '', tools: [], ...extra });
const base = {
  allNotesExpanded: false, intentionsCollapsed: false, topPanelCollapsed: true,
  meta: { name: 'Scénario', description: '', boardLayout: 'columns' },
  sessions: [
    { id: 'm1', title: 'Découvrir', objectives: '', notes: '', notesExpanded: false, activities: [activity('a1'), activity('a2')] },
    { id: 'm2', title: 'Pratiquer', objectives: '', notes: '', notesExpanded: false, activities: [activity('a3')] },
    { id: 'm3', title: 'Conclure', objectives: '', notes: '', notesExpanded: false, activities: [activity('a4')] }
  ]
};
const merge = (local, remote) => Merge.merge(base, local, remote);
const find = (scenario, id) => scenario.sessions.flatMap(m => m.activities).find(a => a.id === id);

test('different fields of the same activity are both kept', () => {
  const local = clone(base); local.sessions[0].activities[0].instructions = 'Consigne du site';
  const remote = clone(base); remote.sessions[0].activities[0].duration = 25;
  remote.meta.description = 'Description du collègue';
  const outcome = merge(local, remote);
  assert.deepEqual(outcome.conflicts, []);
  assert.equal(find(outcome.scenario, 'a1').instructions, 'Consigne du site');
  assert.equal(find(outcome.scenario, 'a1').duration, 25);
  assert.equal(outcome.scenario.meta.description, 'Description du collègue');
});

test('activities added on both sides are kept in place', () => {
  const local = clone(base); local.sessions[0].activities.push(activity('mine'));
  const remote = clone(base); remote.sessions[0].activities.unshift(activity('theirs'));
  assert.deepEqual(merge(local, remote).scenario.sessions[0].activities.map(a => a.id), ['theirs', 'a1', 'a2', 'mine']);
});

test('an activity moved here and edited there appears once with both changes', () => {
  const local = clone(base);
  const moved = local.sessions[0].activities.shift();
  local.sessions[2].activities.push(moved);
  const remote = clone(base); remote.sessions[0].activities[0].instructions = 'Consigne revue';
  const merged = merge(local, remote).scenario;
  assert.deepEqual(merged.sessions[0].activities.map(a => a.id), ['a2']);
  assert.equal(merged.sessions[2].activities.at(-1).id, 'a1');
  assert.equal(merged.sessions.flatMap(m => m.activities).filter(a => a.id === 'a1').length, 1);
  assert.equal(find(merged, 'a1').instructions, 'Consigne revue');
});

test('deletions keep items that the other side modified', () => {
  const local = clone(base); local.sessions[0].activities = [];
  const remote = clone(base); remote.sessions[0].activities[0].notes = 'Note ajoutée pendant ce temps';
  assert.equal(find(merge(local, remote).scenario, 'a1').notes, 'Note ajoutée pendant ce temps');
  assert.equal(find(merge(local, base).scenario, 'a1'), undefined);
  assert.equal(find(merge(base, local).scenario, 'a1'), undefined);
});

test('a deleted moment returns when the other side added an activity to it', () => {
  const local = clone(base); local.sessions.splice(1, 1);
  const remote = clone(base); remote.sessions[1].activities.push(activity('added'));
  const merged = merge(local, remote).scenario;
  assert.deepEqual(merged.sessions.find(m => m.id === 'm2').activities.map(a => a.id), ['added']);
  assert.deepEqual(merged.sessions.map(m => m.id), ['m1', 'm2', 'm3']);
});

test('remote reordering and local edits combine', () => {
  const local = clone(base); local.sessions[0].title = 'Découvrir autrement';
  const remote = clone(base); remote.sessions.reverse();
  const merged = merge(local, remote).scenario;
  assert.deepEqual(merged.sessions.map(m => m.id), ['m3', 'm2', 'm1']);
  assert.equal(merged.sessions.at(-1).title, 'Découvrir autrement');
});

test('texts merge by line then by word', () => {
  const text = 'Lire le document.\nRépondre aux questions.\nComparer en binôme.';
  const start = clone(base); start.sessions[0].activities[0].instructions = text;
  const edited = value => { const s = clone(start); s.sessions[0].activities[0].instructions = value; return s; };
  const merged = (a, b) => {
    const outcome = Merge.merge(start, edited(a), edited(b));
    return [find(outcome.scenario, 'a1').instructions, outcome.conflicts];
  };
  assert.equal(merged('Lire attentivement le document.\nRépondre aux questions.\nComparer en binôme.',
    'Lire le document.\nRépondre aux questions.\nComparer en groupe.')[0],
    'Lire attentivement le document.\nRépondre aux questions.\nComparer en groupe.');
  assert.equal(merged('Lire attentivement le document.\nRépondre aux questions.\nComparer en binôme.',
    'Lire le document imprimé.\nRépondre aux questions.\nComparer en binôme.')[0],
    'Lire attentivement le document imprimé.\nRépondre aux questions.\nComparer en binôme.');
  assert.equal(merged(text + '\nPrésenter.', text + '\nRédiger une synthèse.')[0], text + '\nPrésenter.\nRédiger une synthèse.');
  const conflict = merged('Lire le roman.\nRépondre aux questions.\nComparer en binôme.',
    'Lire le poème.\nRépondre aux questions.\nComparer en binôme.');
  assert.equal(conflict[0], 'Lire le roman.\nRépondre aux questions.\nComparer en binôme.');
  assert.deepEqual(conflict[1], ['/activities/a1/instructions']);
});

test('view settings and browser metadata stay personal', () => {
  const local = clone(base); local.meta.boardLayout = 'grid'; local.intentionsCollapsed = true;
  local.meta.remoteRevision = 4; local.meta.uiLanguage = 'en';
  const remote = clone(base); remote.meta.boardLayout = 'list'; remote.meta.name = 'Titre du collègue';
  remote.sessions[0].notesExpanded = true; remote.meta.remoteRevision = 9;
  const merged = merge(local, remote).scenario;
  assert.equal(merged.meta.boardLayout, 'grid');
  assert.equal(merged.intentionsCollapsed, true);
  assert.equal(merged.meta.remoteRevision, 4);
  assert.equal(merged.meta.uiLanguage, 'en');
  assert.equal(merged.sessions[0].notesExpanded, false);
  assert.equal(merged.meta.name, 'Titre du collègue');
  assert.ok(Merge.sameContent(merged, remote));
});

test('competencies and unknown fields merge without loss', () => {
  const start = clone(base); start.sessions[0].activities[0].tools = ['A', 'B'];
  const local = clone(start); local.sessions[0].activities[0].tools = ['A', 'B', 'C']; local.futureField = { keep: true };
  const remote = clone(start); remote.sessions[0].activities[0].tools = ['B', 'D'];
  const merged = Merge.merge(start, local, remote).scenario;
  assert.deepEqual(find(merged, 'a1').tools, ['B', 'C', 'D']);
  assert.deepEqual(merged.futureField, { keep: true });
});

test('empty PHP arrays and empty objects are the same value', () => {
  const local = clone(base); local.meta.extra = {};
  const remote = clone(base); remote.meta.extra = []; remote.meta.name = 'Nouveau';
  const outcome = Merge.merge(local, local, remote);
  assert.deepEqual(outcome.conflicts, []);
  assert.equal(outcome.scenario.meta.name, 'Nouveau');
});

test('order merge inserts additions after their predecessor', () => {
  assert.deepEqual(Merge.order(['a', 'b', 'c'], ['a', 'x', 'b', 'c'], ['c', 'b', 'a'], new Set(['a', 'b', 'c', 'x'])), ['c', 'b', 'a', 'x']);
  assert.deepEqual(Merge.order(['a', 'b'], ['a', 'b', 'x'], ['y', 'a', 'b'], new Set(['a', 'b', 'x', 'y'])), ['y', 'a', 'b', 'x']);
});

test('changed focus names the edited item and field', () => {
  const edited = clone(base); edited.sessions[1].activities[0].instructions = 'Nouvelle consigne';
  assert.equal(Merge.changedFocus(base, edited), 'activity:a3:instructions');
  const titled = clone(base); titled.sessions[2].title = 'Bilan';
  assert.equal(Merge.changedFocus(base, titled), 'moment:m3:title');
  const named = clone(base); named.meta.name = 'Autre titre';
  assert.equal(Merge.changedFocus(base, named), 'scenario::name');
  const layout = clone(base); layout.meta.boardLayout = 'grid';
  assert.equal(Merge.changedFocus(base, layout), null);
});

test('the cursor stays in place when a colleague edits the field', () => {
  const text = 'Lire le document. Répondre aux questions.';
  assert.deepEqual(Merge.adjustSelection(25, 25, text, 'Lire attentivement le document. Répondre aux questions.'), { start: 39, end: 39 });
  assert.deepEqual(Merge.adjustSelection(25, 25, text, text + ' Comparer.'), { start: 25, end: 25 });
  assert.deepEqual(Merge.adjustSelection(5, 7, text, ''), { start: 0, end: 0 });
});
