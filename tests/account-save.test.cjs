const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../js/account-ui.js'), 'utf8');
const clone = value => JSON.parse(JSON.stringify(value));
const initialState = () => ({ meta: { name: 'Local draft', remoteDesignId: 1, remoteUpdatedAt: 'v1', remoteRevision: 1, remoteDirty: false }, sessions: [] });
const success = (id = 1, updatedAt = 'v2') => ({ success: true, design: { id, updatedAt, revision: Number(updatedAt.replace(/\D/g, "")) || 1 } });

// Isolate the account controller with a fake clock, storage and transport.
// Requests, state changes and saved snapshots remain observable to the tests.
function harness(initial = initialState(), { merge = false } = {}) {
  let state = clone(initial), generation = 0, timerId = 0, stored;
  const timers = new Map(), requests = [], notices = [], confirmations = [];
  const button = {
    dataset: {}, hidden: false, textContent: '',
    querySelector: () => null,
    setAttribute: () => {},
    removeAttribute(name) { if (name === 'data-save-status') delete this.dataset.saveStatus; }
  };
  let onStateChange = () => {};
  const app = {
    getState: () => clone(state),
    getDocumentGeneration: () => generation,
    getCurrentLang: () => 'fr',
    updateMeta(patch, { markDirty = true } = {}) { Object.assign(state.meta, patch); if (markDirty) state.meta.remoteDirty = true; stored = clone(state); onStateChange(); },
    loadDocument(doc, meta) { state = clone(doc); Object.assign(state.meta, meta); generation++; onStateChange(); },
    applyMergedDocument(doc) { state = clone(doc); onStateChange(); return true; },
    showNotice: message => notices.push(message), announce: () => {}
  };
  const context = {
    URL, URLSearchParams,
    localStorage: (() => { const items = new Map(); return { getItem: key => items.get(key) ?? null, setItem: (key, value) => items.set(key, String(value)) }; })(),
    CustomEvent: class { constructor(type, init = {}) { this.type = type; this.detail = init.detail; } },
    setTimeout(fn, ms) { timers.set(++timerId, { fn, ms }); return timerId; },
    clearTimeout(id) { timers.delete(id); },
    document: { getElementById: id => id === 'save-btn' ? button : null, addEventListener: () => {} },
    window: {
      learningDesignerApp: app,
      LearningDesignerMerge: merge ? require('../js/scenario-merge.js') : undefined,
      location: { href: 'http://localhost/designer.php', search: '' },
      history: { replaceState: () => {} },
      dispatchEvent: () => true,
      confirm: message => { confirmations.push(message); return context.confirmResult; }
    },
    confirmResult: false,
    confirmDialog: async options => { confirmations.push(options); return context.confirmResult; },
    transport: async () => success(),
    async fetch(url, options) {
      const body = options.body ? JSON.parse(options.body) : null;
      requests.push({ url, body });
      const result = await context.transport(url, body);
      return { ok: result.success, status: result.status || 200, json: async () => result };
    }
  };
  vm.createContext(context);
  vm.runInContext(source.replace(/\}\)\(\);\s*$/, `
    authState.user = { id: 1 };
    confirmAccountAction = (...args) => globalThis.confirmDialog(...args);
    globalThis.controller = { autoSaveRemote, saveRemoteDesign, scheduleAutoSave, syncRemoteDesignFromServer, persistRemoteDesign };
  })();`), context);
  onStateChange = context.controller.scheduleAutoSave;
  return {
    ...context.controller, context, requests, notices, confirmations, button, app,
    get state() { return clone(state); },
    get stored() { return stored; },
    get autoTimers() { return [...timers.values()].filter(timer => timer.ms === 45000); },
    edit(name) { state.meta.name = name; state.meta.remoteDirty = true; stored = clone(state); onStateChange(); },
    replace(doc) { state = clone(doc); generation++; onStateChange(); }
  };
}

function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return { promise, resolve };
}

test('successful save does not schedule or send another unchanged save', async () => {
  const h = harness();
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 1);
  assert.equal(h.autoTimers.length, 0);
  h.app.updateMeta({ remoteUpdatedAt: 'v3', remoteRevision: 3 }, { markDirty: false });
  await h.autoSaveRemote();
  await h.saveRemoteDesign();
  assert.equal(h.requests.length, 1);
  assert.equal(h.autoTimers.length, 0);
  h.edit('Updated content');
  assert.equal(h.autoTimers.length, 1);
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 2);
  assert.equal(h.requests[1].body.expected_revision, 3);
});

test('edits made during a request remain pending until a second save', async () => {
  const h = harness(), pending = deferred();
  h.context.transport = () => pending.promise;
  const saving = h.autoSaveRemote();
  h.edit('Written during save');
  pending.resolve(success());
  await saving;
  assert.equal(h.requests[0].body.document.meta.name, 'Local draft');
  assert.equal(h.state.meta.name, 'Written during save');
  assert.equal(h.autoTimers.length, 1);
  h.context.transport = async () => success(1, 'v3');
  await h.autoSaveRemote();
  assert.equal(h.requests[1].body.document.meta.name, 'Written during save');
  assert.equal(h.autoTimers.length, 0);
});

test('a conflict preserves the revision and draft and blocks further auto-saves', async () => {
  const h = harness();
  h.context.transport = async () => ({ success: false, status: 409, design: { updatedAt: 'server-v2' } });
  await h.autoSaveRemote();
  assert.equal(h.state.meta.remoteUpdatedAt, 'v1');
  assert.equal(h.stored.meta.remoteSaveConflict, 1);
  assert.equal(h.stored.meta.name, 'Local draft');
  assert.equal(h.button.textContent, 'Enregistrer une copie');
  h.edit('Continued local work');
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 1);
  assert.equal(h.autoTimers.length, 0);
  await h.saveRemoteDesign(); // Cancel saving a copy.
  assert.equal(h.requests.length, 1);
  assert.equal(h.confirmations.length, 1);
});

test('manual conflict then confirmed copy creates a new design without overwriting the original', async () => {
  const h = harness();
  h.context.transport = async () => ({ success: false, status: 409, design: { updatedAt: 'server-v2' } });
  await h.saveRemoteDesign();
  h.context.confirmResult = true;
  h.context.transport = async () => success(2, 'copy-v1');
  await h.saveRemoteDesign();
  assert.equal(h.requests[1].body.design_id, 0);
  assert.equal(h.requests[1].body.expected_revision, null);
  assert.equal(h.requests[1].body.document.meta.remoteSaveConflict, undefined);
  assert.equal(h.state.meta.remoteDesignId, 2);
  assert.equal(h.state.meta.remoteSaveConflict, null);
  assert.equal(h.autoTimers.length, 0);
});

test('failed copy leaves the conflict and original identity intact', async () => {
  const state = initialState(); state.meta.remoteSaveConflict = 1;
  const h = harness(state);
  h.context.confirmResult = true;
  h.context.transport = async () => { throw new Error('Offline'); };
  await h.saveRemoteDesign();
  assert.equal(h.state.meta.remoteDesignId, 1);
  assert.equal(h.state.meta.remoteUpdatedAt, 'v1');
  assert.equal(h.state.meta.remoteSaveConflict, 1);
  assert.equal(h.autoTimers.length, 0);
});

test('reopening a conflicted draft preserves it when remote replacement is declined', async () => {
  const state = initialState(); state.meta.remoteSaveConflict = 1;
  const h = harness(state);
  h.context.transport = async () => ({success:true,design:{id:1,updatedAt:"v2",document:initialState()}});
  assert.equal(await h.syncRemoteDesignFromServer(1), false);
  assert.equal(h.requests.length, 1);
  assert.deepEqual(h.state, {...state,meta:{...state.meta,remoteDirty:true}});
  assert.equal(h.button.textContent, 'Enregistrer une copie');
});

test('loading a remote document establishes a clean baseline', async () => {
  const h = harness();
  h.context.transport = async () => ({ ...success(), design: { ...success().design, document: initialState() } });
  await h.syncRemoteDesignFromServer(1);
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 1);
  assert.equal(h.autoTimers.length, 0);
});

test('concurrent manual and automatic saves create a new design only once', async () => {
  const state = initialState(); delete state.meta.remoteDesignId; delete state.meta.remoteUpdatedAt;
  const h = harness(state), pending = deferred();
  h.context.transport = () => pending.promise;
  const first = h.autoSaveRemote(), second = h.saveRemoteDesign();
  assert.equal(h.requests.length, 1);
  pending.resolve(success(4));
  await Promise.all([first, second]);
  assert.equal(h.requests.length, 1);
  assert.equal(h.state.meta.remoteDesignId, 4);
});

test('a late response cannot attach saved metadata to a different document', async () => {
  const h = harness(), pending = deferred();
  h.context.transport = () => pending.promise;
  const saving = h.autoSaveRemote();
  h.replace({ meta: { name: 'New document' }, sessions: [] });
  pending.resolve(success());
  await saving;
  assert.equal(h.state.meta.name, 'New document');
  assert.equal(h.state.meta.remoteDesignId, undefined);
  assert.equal(h.autoTimers.length, 1);
});

test('an empty new document is not automatically saved', async () => {
  const h = harness({ meta: { name: '' }, sessions: [] });
  h.scheduleAutoSave();
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 0);
  assert.equal(h.autoTimers.length, 0);
});

const remoteDocument = (version = 'v1', name = 'Server document', id = 1) => ({
  success: true, design: {id, updatedAt:version, revision:Number(version.replace(/\D/g, "")) || 1, document:{meta:{name},sessions:[]}}
});

test('unsynced draft survives a restart and resumes saving against an unchanged server', async () => {
  const first = harness();
  first.edit('Unsynced last words');
  const reopened = harness(first.stored);
  reopened.context.transport = async () => remoteDocument();
  await reopened.syncRemoteDesignFromServer(1);
  assert.equal(reopened.state.meta.name, 'Unsynced last words');
  assert.equal(reopened.state.meta.remoteDirty, true);
  assert.equal(reopened.confirmations.length, 0);
  assert.equal(reopened.autoTimers.length, 1);
  reopened.context.transport = async () => success();
  await reopened.autoSaveRemote();
  assert.equal(reopened.requests[1].body.document.meta.name, 'Unsynced last words');
  assert.equal(reopened.requests[1].body.document.meta.remoteDirty, undefined);
  assert.equal(reopened.stored.meta.remoteDirty, false);
});

test('a saved draft allows a newer remote version to load without prompting', async () => {
  const first = harness();
  first.edit('Saved document');
  await first.saveRemoteDesign();
  const reopened = harness(first.stored);
  reopened.context.transport = async () => remoteDocument('v3');
  await reopened.syncRemoteDesignFromServer(1);
  assert.equal(reopened.state.meta.name, 'Server document');
  assert.equal(reopened.state.meta.remoteDirty, false);
  assert.equal(reopened.confirmations.length, 0);
  assert.equal(reopened.autoTimers.length, 0);
});

test('both versions changed: declining remote replacement retains a blocked local draft', async () => {
  const h = harness(); h.edit('My unsynced draft');
  h.context.transport = async () => remoteDocument('v2');
  assert.equal(await h.syncRemoteDesignFromServer(1), false);
  assert.equal(h.confirmations.length, 1);
  assert.equal(h.state.meta.name, 'My unsynced draft');
  assert.equal(h.state.meta.remoteUpdatedAt, 'v1');
  assert.equal(h.state.meta.remoteSaveConflict, 1);
  assert.equal(h.autoTimers.length, 0);
});

test('both versions changed: explicit approval loads the remote version', async () => {
  const h = harness(); h.edit('My unsynced draft');
  h.context.confirmResult = true;
  h.context.transport = async () => remoteDocument('v2');
  assert.equal(await h.syncRemoteDesignFromServer(1), true);
  assert.equal(h.confirmations.length, 1);
  assert.equal(h.state.meta.name, 'Server document');
  assert.equal(h.state.meta.remoteUpdatedAt, 'v2');
  assert.equal(h.state.meta.remoteDirty, false);
  assert.equal(h.autoTimers.length, 0);
});

test('typing while the remote request is pending is preserved', async () => {
  const h = harness(), pending = deferred();
  h.context.transport = () => pending.promise;
  const loading = h.syncRemoteDesignFromServer(1);
  h.edit('Typed during load');
  assert.equal(h.autoTimers.length, 0);
  pending.resolve(remoteDocument());
  await loading;
  assert.equal(h.state.meta.name, 'Typed during load');
  assert.equal(h.autoTimers.length, 1);
});

test('typing during save remains dirty on disk and survives the next startup', async () => {
  const first = harness(), pending = deferred();
  first.context.transport = () => pending.promise;
  const saving = first.saveRemoteDesign();
  first.edit('Not in the saved snapshot');
  pending.resolve(success());
  await saving;
  assert.equal(first.stored.meta.remoteDirty, true);
  const reopened = harness(first.stored);
  reopened.context.transport = async () => remoteDocument('v2');
  await reopened.syncRemoteDesignFromServer(1);
  assert.equal(reopened.state.meta.name, 'Not in the saved snapshot');
});

test('legacy drafts without a dirty marker are preserved conservatively', async () => {
  const state = initialState(); delete state.meta.remoteDirty;
  const h = harness(state);
  h.context.transport = async () => remoteDocument();
  await h.syncRemoteDesignFromServer(1);
  assert.equal(h.state.meta.name, 'Local draft');
  assert.equal(h.state.meta.remoteDirty, true);
});

test('offline startup does not replace or acknowledge the local draft', async () => {
  const h = harness(); h.edit('Offline draft');
  h.context.transport = async () => {throw new Error('Offline');};
  assert.equal(await h.syncRemoteDesignFromServer(1), false);
  assert.equal(h.state.meta.name, 'Offline draft');
  assert.equal(h.state.meta.remoteDirty, true);
  assert.equal(h.autoTimers.length, 0);
});

test('opening a different design requires approval before discarding unsaved work', async () => {
  const h = harness(); h.edit('Unsaved first design');
  h.context.transport = async () => remoteDocument('v1','Other design',2);
  assert.equal(await h.syncRemoteDesignFromServer(2), false);
  assert.equal(h.state.meta.name, 'Unsaved first design');
  assert.equal(h.state.meta.remoteDesignId, 1);
  assert.equal(h.confirmations.length, 1);
});

test('late remote loading cannot replace a newly selected document', async () => {
  const h = harness(), pending = deferred();
  h.context.transport = () => pending.promise;
  const loading = h.syncRemoteDesignFromServer(1);
  h.replace({meta:{name:'New selection',remoteDirty:true},sessions:[]});
  pending.resolve(remoteDocument());
  assert.equal(await loading, false);
  assert.equal(h.state.meta.name, 'New selection');
});

test('manual save waits for startup synchronization to check remote revisions', async () => {
  const h = harness(), pending = deferred(); h.edit('Unsynced');
  h.context.transport = () => pending.promise;
  const loading = h.syncRemoteDesignFromServer(1);
  const saving = h.saveRemoteDesign();
  assert.equal(h.requests.length, 1);
  pending.resolve(remoteDocument('v2'));
  await Promise.all([loading,saving]);
  assert.equal(h.requests.length, 1);
  assert.equal(h.state.meta.remoteSaveConflict, 1);
});

// Verify the real editor persistence function, not just the controller mock.
test('the editor persists dirty state and a clean acknowledgement before closing', () => {
  const editor = fs.readFileSync(path.join(__dirname, '../js/interface.js'), 'utf8');
  const persist = editor.slice(editor.indexOf('function persistStateNow()'), editor.indexOf('function initializeStorageScope('));
  const save = editor.slice(editor.indexOf('function saveState('), editor.indexOf('window.addEventListener("beforeunload"'));
  let stored;
  const ctx = {state:initialState(),localStateSavePending:false,localStateSaveTimer:0,activeStorageKey:'test',
    localStorage:{setItem:(key,value)=>{stored=JSON.parse(value);}},
    scheduleHistoryCheckpoint: () => {},
    CustomEvent: class {},window:{clearTimeout:()=>{},setTimeout:()=>1,dispatchEvent:()=>{}}};
  vm.createContext(ctx); vm.runInContext(persist + save,ctx);
  ctx.state.meta.name = 'Last words';
  vm.runInContext('saveState(); persistStateNow();',ctx);
  assert.equal(stored.meta.remoteDirty,true);
  ctx.state.meta.remoteDirty = false;
  vm.runInContext('saveState({markDirty:false}); persistStateNow();',ctx);
  assert.equal(stored.meta.remoteDirty,false);
});

test('deleting all content of an existing design is still an unsynced edit', async () => {
  const state = initialState(); state.meta.name = ''; state.meta.remoteDirty = true;
  const h = harness(state);
  h.context.transport = async () => remoteDocument();
  await h.syncRemoteDesignFromServer(1);
  assert.equal(h.state.meta.name, '');
  assert.deepEqual(h.state.sessions, []);
  assert.equal(h.autoTimers.length, 1);
});

test('a pending styled dialog cannot overwrite a document selected in the meantime', async () => {
  const h = harness(), decision = deferred(), opened = deferred();
  h.edit('Unsynced local draft');
  h.context.transport = async () => remoteDocument('v2');
  h.context.confirmDialog = () => { opened.resolve(); return decision.promise; };
  const loading = h.syncRemoteDesignFromServer(1);
  await opened.promise;
  h.replace({meta:{name:'New document',remoteDirty:true},sessions:[]});
  decision.resolve(true);
  assert.equal(await loading, false);
  assert.equal(h.state.meta.name, 'New document');
});

test('a pending copy dialog does not save a stale snapshot after a document change', async () => {
  const state = initialState(); state.meta.remoteSaveConflict = 1;
  const h = harness(state), decision = deferred(), opened = deferred();
  h.context.confirmDialog = () => { opened.resolve(); return decision.promise; };
  const saving = h.saveRemoteDesign();
  await opened.promise;
  h.replace({meta:{name:'New document',remoteDirty:true},sessions:[]});
  decision.resolve(true);
  assert.equal(await saving, null);
  assert.equal(h.requests.length, 0);
});

test('legacy drafts without a revision cannot silently overwrite the server', async () => {
  const state = initialState(); delete state.meta.remoteRevision; state.meta.remoteDirty = true;
  const h = harness(state);
  h.context.transport = async () => remoteDocument('v1');
  await h.syncRemoteDesignFromServer(1);
  assert.equal(h.state.meta.name, 'Local draft');
  assert.equal(h.state.meta.remoteSaveConflict, 1);
  assert.equal(h.autoTimers.length, 0);
});

test('different revisions conflict even when timestamps are identical', async () => {
  const state = initialState(); state.meta.remoteDirty = true;
  const h = harness(state);
  h.context.transport = async () => {
    const remote = remoteDocument('v1'); remote.design.revision = 2; return remote;
  };
  await h.syncRemoteDesignFromServer(1);
  assert.equal(h.state.meta.remoteSaveConflict, 1);
  assert.equal(h.state.meta.remoteRevision, 1);
});

// Collaboration : un enregistrement refusé parce qu’un collègue a enregistré entre-temps
// récupère sa version, fusionne les deux et renvoie le résultat, sans créer de copie.
test('a conflict on a shared scenario merges the colleague version and saves again', async () => {
  const h = harness(initialState(), { merge: true });
  const base = { meta: { name: 'Local draft' }, sessions: [] };
  h.context.localStorage.setItem('ld_remote_base_v1_1_1', JSON.stringify({ revision: 1, document: base }));
  h.edit('Titre local');
  const remote = { meta: { name: 'Local draft', description: 'Description du collègue' }, sessions: [] };
  const replies = [
    { success: false, status: 409, design: { id: 1, revision: 2 } },
    { success: true, design: { id: 1, title: 'Local draft', updatedAt: 'v2', revision: 2, role: 'editor', owner: 'Alice', shared: true, document: remote } },
    { success: true, design: { id: 1, updatedAt: 'v3', revision: 3 } }
  ];
  h.context.transport = async () => replies.shift();
  await h.autoSaveRemote();
  assert.deepEqual(h.requests.map(request => request.url.split('?')[0]), ['save_design.php', 'get_design.php', 'save_design.php']);
  assert.equal(h.requests[2].body.expected_revision, 2);
  assert.equal(h.requests[2].body.document.meta.name, 'Titre local');
  assert.equal(h.requests[2].body.document.meta.description, 'Description du collègue');
  assert.equal(h.state.meta.remoteRevision, 3);
  assert.equal(h.state.meta.remoteSaveConflict, null);
  assert.equal(h.confirmations.length, 0);
});

test('without a known common version a conflict still keeps the draft and offers a copy', async () => {
  const h = harness(initialState(), { merge: true });
  h.edit('Titre local');
  h.context.transport = async () => ({ success: false, status: 409, design: { id: 1, revision: 2 } });
  await h.autoSaveRemote();
  assert.equal(h.requests.length, 1);
  assert.equal(h.state.meta.remoteSaveConflict, 1);
  assert.equal(h.state.meta.name, 'Titre local');
});
