/*
 * Fusion à trois voies de deux versions d’un scénario, champ par champ.
 * Portage de ScenarioMerge.swift (app Scénarisation pour Mac et iPad) : le site et les apps
 * fusionnent de la même manière les modifications faites en même temps sur un scénario partagé.
 *
 * `base` est la dernière version commune, `local` celle de ce navigateur et `remote` celle du site
 * (ou d’un autre enseignant). Un champ modifié d’un seul côté est repris tel quel. Les moments et les
 * activités sont suivis par leur identifiant, y compris lorsqu’une activité change de moment. Un
 * élément supprimé d’un côté et modifié de l’autre est conservé, pour ne perdre aucune saisie.
 * Un même texte modifié des deux côtés est fusionné ligne par ligne, puis mot par mot ; si les
 * modifications se chevauchent encore, la version locale l’emporte et le champ est signalé.
 */
(function (root) {
  "use strict";

  /* Réglages d’affichage propres à chaque personne : une fusion ne les impose jamais à une autre.
     Les clés techniques du navigateur (remote*, provenance…) restent aussi locales. */
  const VIEW_STATE_KEYS = new Set(["allNotesExpanded", "intentionsCollapsed", "topPanelCollapsed"]);
  const VIEW_STATE_META_KEYS = new Set([
    "boardLayout", "activeTab", "uiLanguage", "designedMinutes", "provenance",
    "remoteDesignId", "remoteUpdatedAt", "remoteRevision", "remoteSaveConflict", "remoteDirty"
  ]);
  const VIEW_STATE_MOMENT_KEYS = new Set(["notesExpanded"]);

  const isObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);
  const isEmptyContainer = (value) => (Array.isArray(value) && value.length === 0)
    || (isObject(value) && Object.keys(value).length === 0);

  /* Égalité de valeurs JSON. Un objet vide et une liste vide sont équivalents : PHP renvoie `[]`
     pour un objet vide qu’il a décodé, et cet aller-retour ne doit pas passer pour une modification. */
  function equal(a, b) {
    if (a === b) return true;
    if (isEmptyContainer(a) && isEmptyContainer(b)) return true;
    if (Array.isArray(a)) {
      if (!Array.isArray(b) || a.length !== b.length) return false;
      return a.every((item, index) => equal(item, b[index]));
    }
    if (isObject(a)) {
      if (!isObject(b)) return false;
      const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
      for (const key of keys) {
        if (!equal(a[key], b[key])) return false;
      }
      return true;
    }
    return false;
  }

  const clone = (value) => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)));
  const sessionsOf = (scenario) => (Array.isArray(scenario?.sessions) ? scenario.sessions.filter(isObject) : []);
  const activitiesOf = (moment) => (Array.isArray(moment?.activities) ? moment.activities.filter(isObject) : []);
  const idOf = (value) => (isObject(value) && typeof value.id === "string" ? value.id : "");

  function withoutKeys(object, keys) {
    if (!isObject(object)) return object;
    const copy = { ...object };
    for (const key of keys) delete copy[key];
    return copy;
  }

  // ── Ordre des éléments ──────────────────────────────────────────────

  /* Ordre fusionné des identifiants conservés (`keep`). Le côté qui a réordonné ses éléments impose
     son ordre ; les éléments ajoutés de l’autre côté s’insèrent après leur prédécesseur d’origine. */
  function order(base, local, remote, keep) {
    const relative = (sequence, other) => {
      const members = new Set(other);
      return sequence.filter((id) => members.has(id));
    };
    const sameSequence = (a, b) => a.length === b.length && a.every((id, index) => id === b[index]);
    const localMoved = !sameSequence(relative(local, base), relative(base, local));
    const remoteMoved = !sameSequence(relative(remote, base), relative(base, remote));
    const [primary, secondary] = remoteMoved && !localMoved ? [remote, local] : [local, remote];
    const result = [];
    const placed = new Set();
    for (const id of primary) {
      if (keep.has(id) && !placed.has(id)) { result.push(id); placed.add(id); }
    }
    for (const source of [secondary, base]) {
      source.forEach((id, index) => {
        if (!keep.has(id) || placed.has(id)) return;
        let position = 0;
        for (let previous = index - 1; previous >= 0; previous--) {
          const at = result.indexOf(source[previous]);
          if (at >= 0) { position = at + 1; break; }
        }
        result.splice(position, 0, id);
        placed.add(id);
      });
    }
    result.push(...[...keep].filter((id) => !placed.has(id)).sort());
    return result;
  }

  // ── Fusion des textes ──────────────────────────────────────────────

  function lines(text) {
    const result = [];
    let start = 0;
    for (let index = 0; index < text.length; index++) {
      if (text[index] === "\n") { result.push(text.slice(start, index + 1)); start = index + 1; }
    }
    if (start < text.length) result.push(text.slice(start));
    return result;
  }

  function words(text) {
    return text.match(/\s+|\S+/g) || [];
  }

  /* Positions retirées de `base` et insérées dans `side`, selon une plus longue sous-suite commune.
     Le préfixe et le suffixe communs sont écartés d’abord : la plupart des saisies restent locales. */
  function difference(base, side) {
    let prefix = 0;
    while (prefix < base.length && prefix < side.length && base[prefix] === side[prefix]) prefix++;
    let suffix = 0;
    while (suffix < base.length - prefix && suffix < side.length - prefix
      && base[base.length - 1 - suffix] === side[side.length - 1 - suffix]) suffix++;
    const a = base.slice(prefix, base.length - suffix);
    const b = side.slice(prefix, side.length - suffix);
    const removed = new Set();
    const inserted = new Set();
    if (a.length * b.length > 4000000) return null;
    const width = b.length + 1;
    const table = new Uint32Array((a.length + 1) * width);
    for (let i = a.length - 1; i >= 0; i--) {
      for (let j = b.length - 1; j >= 0; j--) {
        table[i * width + j] = a[i] === b[j]
          ? table[(i + 1) * width + j + 1] + 1
          : Math.max(table[(i + 1) * width + j], table[i * width + j + 1]);
      }
    }
    let i = 0;
    let j = 0;
    while (i < a.length && j < b.length) {
      if (a[i] === b[j]) { i++; j++; }
      else if (table[(i + 1) * width + j] >= table[i * width + j + 1]) { removed.add(prefix + i); i++; }
      else { inserted.add(prefix + j); j++; }
    }
    for (; i < a.length; i++) removed.add(prefix + i);
    for (; j < b.length; j++) inserted.add(prefix + j);
    return { removed, inserted };
  }

  /* Passage `base[start..<end]` remplacé par `tokens` (insertion pure si start === end). */
  function hunks(base, side, local) {
    const diff = difference(base, side);
    if (!diff) return null;
    const { removed, inserted } = diff;
    const result = [];
    let b = 0;
    let s = 0;
    while (b < base.length || s < side.length) {
      if (b < base.length && s < side.length && !removed.has(b) && !inserted.has(s)) { b++; s++; continue; }
      const start = b;
      const tokens = [];
      for (;;) {
        if (b < base.length && removed.has(b)) b++;
        else if (s < side.length && inserted.has(s)) { tokens.push(side[s]); s++; }
        else break;
      }
      if (b === start && tokens.length === 0) break;
      result.push({ start, end: b, tokens, local });
    }
    return result;
  }

  const isInsertion = (hunk) => hunk.start === hunk.end;

  function overlaps(a, b) {
    if (isInsertion(a) && isInsertion(b)) return a.start === b.start;
    if (isInsertion(a)) return b.start < a.start && a.start < b.end;
    if (isInsertion(b)) return a.start < b.start && b.start < a.end;
    return a.start < b.end && b.start < a.end;
  }

  function applyHunks(group, base, start, end) {
    const result = [];
    let cursor = start;
    for (const hunk of group) {
      result.push(...base.slice(cursor, hunk.start), ...hunk.tokens);
      cursor = hunk.end;
    }
    result.push(...base.slice(cursor, end));
    return result;
  }

  function merge3(base, local, remote) {
    const mine = hunks(base, local, true);
    const theirs = hunks(base, remote, false);
    if (!mine || !theirs) return null;
    const all = [...mine, ...theirs].sort((x, y) => (x.start - y.start) || (x.end - y.end) || ((x.local ? 0 : 1) - (y.local ? 0 : 1)));
    const result = [];
    let cursor = 0;
    let index = 0;
    while (index < all.length) {
      const group = [all[index]];
      let end = all[index].end;
      index++;
      while (index < all.length && group.some((hunk) => hunk.local !== all[index].local && overlaps(hunk, all[index]))) {
        end = Math.max(end, all[index].end);
        group.push(all[index]);
        index++;
      }
      const start = group[0].start;
      result.push(...base.slice(cursor, start));
      const localGroup = group.filter((hunk) => hunk.local);
      const remoteGroup = group.filter((hunk) => !hunk.local);
      if (!localGroup.length || !remoteGroup.length) {
        result.push(...applyHunks(group, base, start, end));
      } else if (group.every(isInsertion)) {
        // Deux ajouts au même endroit : les deux sont gardés, l’ajout local en premier.
        const a = localGroup.flatMap((hunk) => hunk.tokens);
        const b = remoteGroup.flatMap((hunk) => hunk.tokens);
        result.push(...(a.join("") === b.join("") ? a : [...a, ...b]));
      } else {
        const a = applyHunks(localGroup, base, start, end);
        const b = applyHunks(remoteGroup, base, start, end);
        if (a.join("") !== b.join("")) return null;
        result.push(...a);
      }
      cursor = end;
    }
    result.push(...base.slice(cursor));
    return result;
  }

  /* Fusion d’un texte par lignes, puis par mots ; `null` si les deux côtés changent le même passage. */
  function mergeText(base, local, remote) {
    if (local === remote || remote === base) return local;
    if (local === base) return remote;
    for (const split of [lines, words]) {
      const merged = merge3(split(base), split(local), split(remote));
      if (merged) return merged.join("");
    }
    return null;
  }

  // ── Fusion des valeurs ─────────────────────────────────────────────

  function identified(values) {
    if (!Array.isArray(values)) return false;
    const seen = new Set();
    return values.every((value) => {
      const id = idOf(value);
      if (!id || seen.has(id)) return false;
      seen.add(id);
      return true;
    });
  }

  function strings(values) {
    return Array.isArray(values) && values.every((value) => typeof value === "string")
      && new Set(values).size === values.length;
  }

  function index(values) {
    const result = new Map();
    for (const value of values) {
      const id = idOf(value);
      if (!result.has(id)) result.set(id, value);
    }
    return result;
  }

  const ids = (values) => values.map(idOf);

  class Merger {
    constructor() {
      this.conflicts = [];
    }

    object(base, local, remote, path, viewState = null, excluding = new Set()) {
      const personal = viewState ?? (path === "" ? VIEW_STATE_KEYS : path === "/meta" ? VIEW_STATE_META_KEYS : new Set());
      const b = isObject(base) ? base : {};
      const l = isObject(local) ? local : {};
      const r = isObject(remote) ? remote : {};
      const result = {};
      const keys = new Set([...Object.keys(b), ...Object.keys(l), ...Object.keys(r)]);
      for (const key of keys) {
        if (excluding.has(key)) continue;
        if (personal.has(key)) {
          if (key in l) result[key] = clone(l[key]);
          continue;
        }
        const value = this.value(b[key], l[key], r[key], `${path}/${key}`);
        if (value !== undefined) result[key] = value;
      }
      return result;
    }

    value(base, local, remote, path) {
      if (equal(local, remote) || equal(remote, base)) return clone(local);
      // `meta` contient la disposition choisie : il est toujours parcouru pour la garder propre à chacun.
      if (path === "/meta" && isObject(local) && isObject(remote)) return this.object(base, local, remote, path);
      if (equal(local, base)) return clone(remote);
      if (isObject(local) && isObject(remote)) return this.object(base, local, remote, path);
      if (identified(local) && identified(remote)) {
        return this.identifiedArray(identified(base) ? base : [], local, remote, path);
      }
      if (strings(local) && strings(remote)) return this.stringSet(Array.isArray(base) ? base : [], local, remote);
      if (typeof local === "string" && typeof remote === "string" && typeof base === "string") {
        const merged = mergeText(base, local, remote);
        if (merged !== null) return merged;
      }
      this.conflicts.push(path);
      return clone(local);
    }

    identifiedArray(base, local, remote, path) {
      const b = index(base);
      const l = index(local);
      const r = index(remote);
      const merged = new Map();
      for (const id of new Set([...b.keys(), ...l.keys(), ...r.keys()])) {
        const item = this.item(b.get(id), l.get(id), r.get(id), `${path}/${id}`);
        if (item !== undefined) merged.set(id, item);
      }
      return order(ids(base), ids(local), ids(remote), new Set(merged.keys())).map((id) => merged.get(id));
    }

    /* Un élément supprimé d’un côté n’est retiré que s’il n’a pas été modifié de l’autre. */
    item(base, local, remote, path, viewState = new Set()) {
      const same = (a, b) => equal(withoutKeys(a, viewState), withoutKeys(b, viewState));
      if (local === undefined && remote === undefined) return undefined;
      if (remote === undefined) return base === undefined || !same(base, local) ? clone(local) : undefined;
      if (local === undefined) return base === undefined || !same(base, remote) ? clone(remote) : undefined;
      if (!isObject(local) || !isObject(remote)) return this.value(base, local, remote, path);
      return this.object(base, local, remote, path, viewState);
    }

    /* Listes de valeurs simples (compétences) : les ajouts et retraits des deux côtés s’appliquent. */
    stringSet(base, local, remote) {
      const b = new Set(base);
      const l = new Set(local);
      const r = new Set(remote);
      const removed = new Set([...b].filter((value) => !l.has(value) || !r.has(value)));
      const result = local.filter((value) => !removed.has(value));
      const present = new Set(result);
      for (const value of remote) {
        if (!removed.has(value) && !present.has(value)) { result.push(value); present.add(value); }
      }
      return result;
    }

    /* Moments, puis activités suivies d’un moment à l’autre : une activité déplacée d’un côté
       et modifiée de l’autre n’apparaît qu’une fois, dans son nouveau moment, avec ses modifications. */
    sessions(base, local, remote) {
      const moments = (scenario) => index(sessionsOf(scenario).map((moment) => withoutKeys(moment, ["activities"])));
      const placements = (scenario) => {
        const result = new Map();
        for (const moment of sessionsOf(scenario)) {
          for (const activity of activitiesOf(moment)) {
            const id = idOf(activity);
            if (!result.has(id)) result.set(id, { activity, moment: idOf(moment) });
          }
        }
        return result;
      };
      const activityOrder = (scenario, momentId) => {
        const moment = sessionsOf(scenario).find((candidate) => idOf(candidate) === momentId);
        return moment ? ids(activitiesOf(moment)) : [];
      };

      const bm = moments(base);
      const lm = moments(local);
      const rm = moments(remote);
      const merged = new Map();
      for (const id of new Set([...bm.keys(), ...lm.keys(), ...rm.keys()])) {
        const moment = this.item(bm.get(id), lm.get(id), rm.get(id), `/sessions/${id}`, VIEW_STATE_MOMENT_KEYS);
        if (moment !== undefined) merged.set(id, moment);
      }

      const ba = placements(base);
      const la = placements(local);
      const ra = placements(remote);
      const activities = new Map();
      for (const id of new Set([...ba.keys(), ...la.keys(), ...ra.keys()])) {
        const b = ba.get(id);
        const l = la.get(id);
        const r = ra.get(id);
        const changed = (side) => !b || !equal(b.activity, side.activity) || b.moment !== side.moment;
        if (!l && !r) continue;
        if (l && !r) { if (changed(l)) activities.set(id, l); continue; }
        if (!l && r) { if (changed(r)) activities.set(id, r); continue; }
        const content = this.item(b?.activity, l.activity, r.activity, `/activities/${id}`) ?? l.activity;
        // Déplacée des deux côtés vers des moments différents : le déplacement local l’emporte.
        const moment = l.moment === r.moment || l.moment !== b?.moment ? l.moment : r.moment;
        activities.set(id, { activity: content, moment });
      }
      // Un moment supprimé d’un côté revient si l’autre côté y a modifié ou ajouté une activité.
      for (const placement of activities.values()) {
        if (!merged.has(placement.moment)) {
          const restored = lm.get(placement.moment) ?? rm.get(placement.moment) ?? bm.get(placement.moment);
          if (restored) merged.set(placement.moment, clone(restored));
        }
      }
      for (const [id, placement] of activities) {
        if (!merged.has(placement.moment)) activities.delete(id);
      }

      const momentOrder = order(ids(sessionsOf(base)), ids(sessionsOf(local)), ids(sessionsOf(remote)), new Set(merged.keys()));
      return momentOrder.map((id) => {
        const fields = { ...merged.get(id) };
        const members = new Set([...activities].filter(([, placement]) => placement.moment === id).map(([activityId]) => activityId));
        const activityIds = order(activityOrder(base, id), activityOrder(local, id), activityOrder(remote, id), members);
        fields.activities = activityIds.map((activityId) => clone(activities.get(activityId).activity));
        return fields;
      });
    }
  }

  /* Résultat : `{ scenario, conflicts }`. `conflicts` liste les champs modifiés des deux côtés
     pour lesquels la version locale a été conservée. */
  function merge(base, local, remote) {
    if (equal(local, remote) || equal(remote, base)) return { scenario: clone(local), conflicts: [] };
    const merger = new Merger();
    const scenario = merger.object(base, local, remote, "", null, new Set(["sessions"]));
    scenario.sessions = merger.sessions(base, local, remote);
    return { scenario, conflicts: merger.conflicts };
  }

  /* Mêmes contenus, en ignorant les réglages d’affichage et les données techniques propres à chacun. */
  function withoutViewState(scenario) {
    if (!isObject(scenario)) return scenario;
    const copy = withoutKeys(scenario, VIEW_STATE_KEYS);
    if (isObject(copy.meta)) copy.meta = withoutKeys(copy.meta, VIEW_STATE_META_KEYS);
    copy.sessions = sessionsOf(scenario).map((moment) => withoutKeys(moment, VIEW_STATE_MOMENT_KEYS));
    return copy;
  }

  function sameContent(a, b) {
    return equal(withoutViewState(a), withoutViewState(b));
  }

  /* Élément et champ modifiés entre deux versions, pour indiquer aux autres où l’on travaille.
     Format : `activity:<id>:<champ>`, `moment:<id>:<champ>` ou `scenario::<champ>`. */
  function changedFocus(previous, next) {
    if (equal(previous, next)) return null;
    const changedKey = (before, after, ignoring = new Set()) => {
      const keys = [...new Set([...Object.keys(before || {}), ...Object.keys(after || {})])]
        .filter((key) => !ignoring.has(key)).sort();
      return keys.find((key) => !equal(before?.[key], after?.[key])) ?? null;
    };
    const previousActivities = new Map();
    for (const moment of sessionsOf(previous)) {
      for (const activity of activitiesOf(moment)) {
        if (!previousActivities.has(idOf(activity))) previousActivities.set(idOf(activity), activity);
      }
    }
    for (const moment of sessionsOf(next)) {
      for (const activity of activitiesOf(moment)) {
        const before = previousActivities.get(idOf(activity));
        if (!before) return `activity:${idOf(activity)}:`;
        if (!equal(before, activity)) return `activity:${idOf(activity)}:${changedKey(before, activity) ?? ""}`;
      }
    }
    const previousMoments = index(sessionsOf(previous));
    for (const moment of sessionsOf(next)) {
      const before = previousMoments.get(idOf(moment));
      if (!before) return `moment:${idOf(moment)}:`;
      const key = changedKey(before, moment, new Set([...VIEW_STATE_MOMENT_KEYS, "activities"]));
      if (key) return `moment:${idOf(moment)}:${key}`;
    }
    const key = changedKey(previous?.meta, next?.meta, VIEW_STATE_META_KEYS);
    return key ? `scenario::${key}` : null;
  }

  /* Garde le curseur au même endroit du texte lorsqu’un collègue modifie le champ en cours de saisie. */
  function adjustSelection(start, end, previous, next) {
    const shortest = Math.min(previous.length, next.length);
    let prefix = 0;
    while (prefix < shortest && previous[prefix] === next[prefix]) prefix++;
    let suffix = 0;
    while (suffix < shortest - prefix
      && previous[previous.length - 1 - suffix] === next[next.length - 1 - suffix]) suffix++;
    const oldEnd = previous.length - suffix;
    const newEnd = next.length - suffix;
    const map = (location) => {
      if (location <= prefix) return Math.min(location, next.length);
      if (location >= oldEnd) return Math.min(next.length, Math.max(0, location + next.length - previous.length));
      return newEnd; // dans le passage remplacé : après le nouveau texte
    };
    const mappedStart = map(start);
    return { start: mappedStart, end: Math.max(mappedStart, map(end)) };
  }

  const api = { merge, mergeText, order, equal, sameContent, withoutViewState, changedFocus, adjustSelection };
  root.LearningDesignerMerge = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
