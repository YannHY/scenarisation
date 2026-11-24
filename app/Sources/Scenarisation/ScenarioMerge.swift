import Foundation

/// Fusion à trois voies de deux versions d’un scénario, champ par champ.
///
/// `base` est la dernière version commune, `local` celle de cet appareil et `remote` celle du site
/// (ou d’un autre enseignant). Un champ modifié d’un seul côté est repris tel quel. Les moments et les
/// activités sont suivis par leur identifiant, y compris lorsqu’une activité change de moment : chaque
/// élément garde les modifications des deux côtés et l’ordre suit celui qui l’a changé. Un élément
/// supprimé d’un côté et modifié de l’autre est conservé, pour ne perdre aucune saisie.
///
/// Lorsqu’un même texte a changé des deux côtés, il est fusionné ligne par ligne, puis mot par mot ;
/// si les modifications se chevauchent encore, la version locale l’emporte et le champ est signalé.
enum ScenarioMerge {
    struct Outcome: Equatable {
        var scenario: Scenario
        /// Champs modifiés des deux côtés pour lesquels la version locale a été conservée.
        var conflicts: [String]
    }

    /// Réglages d’affichage propres à chaque personne : une fusion ne les impose jamais à une autre.
    static let viewStateKeys: Set<String> = ["allNotesExpanded", "intentionsCollapsed", "topPanelCollapsed"]
    static let viewStateMetaKeys: Set<String> = ["boardLayout"]
    static let viewStateMomentKeys: Set<String> = ["notesExpanded"]

    /// - Parameter keepLocalViewState: `false` pour annuler une action, où l’affichage suit aussi la fusion.
    static func merge(base: Scenario, local: Scenario, remote: Scenario, keepLocalViewState: Bool = true) -> Outcome {
        if local == remote || remote == base { return Outcome(scenario: local, conflicts: []) }
        if local == base && !keepLocalViewState { return Outcome(scenario: remote, conflicts: []) }
        var merger = Merger(keepLocalViewState: keepLocalViewState)
        var fields = merger.object(base.fields, local.fields, remote.fields, path: "", excluding: ["sessions"])
        fields["sessions"] = .array(merger.sessions(base: base, local: local, remote: remote))
        return Outcome(scenario: Scenario(fields: fields), conflicts: merger.conflicts)
    }

    /// Mêmes contenus, en ignorant les réglages d’affichage propres à chacun.
    static func sameContent(_ a: Scenario, _ b: Scenario) -> Bool {
        a == b || withoutViewState(a) == withoutViewState(b)
    }

    static func withoutViewState(_ scenario: Scenario) -> Scenario {
        var copy = scenario
        for key in viewStateKeys { copy.fields.removeValue(forKey: key) }
        if case .object(var meta)? = copy.fields["meta"] {
            for key in viewStateMetaKeys { meta.removeValue(forKey: key) }
            copy.fields["meta"] = .object(meta)
        }
        if case .array(let sessions)? = copy.fields["sessions"] {
            copy.fields["sessions"] = .array(sessions.map { session in
                guard case .object(var moment) = session else { return session }
                for key in viewStateMomentKeys { moment.removeValue(forKey: key) }
                return .object(moment)
            })
        }
        return copy
    }

    // MARK: Ordre des éléments

    /// Ordre fusionné des identifiants conservés (`keep`). Le côté qui a réordonné ses éléments impose
    /// son ordre ; les éléments ajoutés de l’autre côté s’insèrent après leur prédécesseur d’origine.
    static func order(base: [String], local: [String], remote: [String], keep: Set<String>) -> [String] {
        func relative(_ sequence: [String], to other: [String]) -> [String] {
            let members = Set(other)
            return sequence.filter(members.contains)
        }
        let localMoved = relative(local, to: base) != relative(base, to: local)
        let remoteMoved = relative(remote, to: base) != relative(base, to: remote)
        let (primary, secondary) = remoteMoved && !localMoved ? (remote, local) : (local, remote)
        var result: [String] = []
        var placed = Set<String>()
        for id in primary where keep.contains(id) && !placed.contains(id) {
            result.append(id); placed.insert(id)
        }
        for source in [secondary, base] {
            for (index, id) in source.enumerated() where keep.contains(id) && !placed.contains(id) {
                var position = 0
                var previous = index - 1
                while previous >= 0 {
                    if let at = result.firstIndex(of: source[previous]) { position = at + 1; break }
                    previous -= 1
                }
                result.insert(id, at: position); placed.insert(id)
            }
        }
        result += keep.subtracting(placed).sorted()
        return result
    }

    // MARK: Emplacement d’une modification

    /// Élément et champ modifiés entre deux versions, pour indiquer aux autres participants où l’on travaille.
    /// Format : `activity:<id>:<champ>`, `moment:<id>:<champ>` ou `scenario::<champ>` (champ éventuellement vide).
    static func changedFocus(from old: Scenario, to new: Scenario) -> String? {
        guard old != new else { return nil }
        let previousActivities = Dictionary(old.moments.flatMap(\.activities).map { ($0.id, $0) }, uniquingKeysWith: { first, _ in first })
        for moment in new.moments {
            for activity in moment.activities {
                guard let before = previousActivities[activity.id] else { return "activity:\(activity.id):" }
                if before != activity { return "activity:\(activity.id):" + (changedKey(before.fields, activity.fields) ?? "") }
            }
        }
        let previousMoments = Dictionary(old.moments.map { ($0.id, $0) }, uniquingKeysWith: { first, _ in first })
        for moment in new.moments {
            guard let before = previousMoments[moment.id] else { return "moment:\(moment.id):" }
            if let key = changedKey(before.fields, moment.fields, ignoring: viewStateMomentKeys.union(["activities"])) {
                return "moment:\(moment.id):\(key)"
            }
        }
        if let key = changedKey(old.meta, new.meta, ignoring: viewStateMetaKeys) { return "scenario::\(key)" }
        return nil
    }

    private static func changedKey(_ old: [String: JSONValue], _ new: [String: JSONValue], ignoring: Set<String> = []) -> String? {
        Set(old.keys).union(new.keys).subtracting(ignoring).sorted().first { old[$0] != new[$0] }
    }
}

/// Parcours récursif : chaque désaccord sur un même champ est noté dans `conflicts`.
private struct Merger {
    let keepLocalViewState: Bool
    var conflicts: [String] = []

    mutating func object(_ base: [String: JSONValue], _ local: [String: JSONValue], _ remote: [String: JSONValue],
                         path: String, viewState: Set<String>? = nil, excluding: Set<String> = []) -> [String: JSONValue] {
        let personal = viewState ?? (path.isEmpty ? ScenarioMerge.viewStateKeys : path == "/meta" ? ScenarioMerge.viewStateMetaKeys : [])
        var result: [String: JSONValue] = [:]
        for key in Set(base.keys).union(local.keys).union(remote.keys) where !excluding.contains(key) {
            if keepLocalViewState && personal.contains(key) {
                if let value = local[key] { result[key] = value }
                continue
            }
            if let value = value(base[key], local[key], remote[key], path: path + "/" + key) { result[key] = value }
        }
        return result
    }

    mutating func value(_ base: JSONValue?, _ local: JSONValue?, _ remote: JSONValue?, path: String) -> JSONValue? {
        if local == remote || remote == base { return local }
        // `meta` contient la disposition choisie : il est toujours parcouru pour la garder propre à chacun.
        if keepLocalViewState, path == "/meta", case .object(let l)? = local, case .object(let r)? = remote {
            return .object(object(base?.object ?? [:], l, r, path: path))
        }
        if local == base { return remote }
        switch (local, remote) {
        case (.object(let l)?, .object(let r)?):
            return .object(object(base?.object ?? [:], l, r, path: path))
        case (.array(let l)?, .array(let r)?) where Self.identified(l) && Self.identified(r):
            let b = base.map(\.array).flatMap { Self.identified($0) ? $0 : nil } ?? []
            return .array(identifiedArray(b, l, r, path: path))
        case (.array(let l)?, .array(let r)?) where Self.strings(l) && Self.strings(r):
            return .array(stringSet(base?.array ?? [], l, r))
        case (.string(let l)?, .string(let r)?):
            if case .string(let b)? = base, let merged = TextMerge.merge(base: b, local: l, remote: r) { return .string(merged) }
            conflicts.append(path); return local
        default:
            conflicts.append(path); return local
        }
    }

    // MARK: Listes d’éléments identifiés

    mutating func identifiedArray(_ base: [JSONValue], _ local: [JSONValue], _ remote: [JSONValue], path: String) -> [JSONValue] {
        let b = Self.index(base), l = Self.index(local), r = Self.index(remote)
        var merged: [String: JSONValue] = [:]
        for id in Set(b.keys).union(l.keys).union(r.keys) {
            if let item = item(b[id], l[id], r[id], path: path + "/" + id) { merged[id] = item }
        }
        let order = ScenarioMerge.order(base: Self.ids(base), local: Self.ids(local), remote: Self.ids(remote), keep: Set(merged.keys))
        return order.compactMap { merged[$0] }
    }

    /// Un élément supprimé d’un côté n’est retiré que s’il n’a pas été modifié de l’autre.
    mutating func item(_ base: JSONValue?, _ local: JSONValue?, _ remote: JSONValue?, path: String,
                       viewState: Set<String> = []) -> JSONValue? {
        switch (local, remote) {
        case (nil, nil): return nil
        case (let local?, nil): return base.map { !Self.same($0, local, ignoring: viewState) } ?? true ? local : nil
        case (nil, let remote?): return base.map { !Self.same($0, remote, ignoring: viewState) } ?? true ? remote : nil
        case (let local?, let remote?):
            guard case .object(let l) = local, case .object(let r) = remote else { return value(base, local, remote, path: path) }
            return .object(object(base?.object ?? [:], l, r, path: path, viewState: viewState))
        }
    }

    /// Moments, puis activités suivies d’un moment à l’autre : une activité déplacée d’un côté
    /// et modifiée de l’autre n’apparaît qu’une fois, dans son nouveau moment, avec ses modifications.
    mutating func sessions(base: Scenario, local: Scenario, remote: Scenario) -> [JSONValue] {
        func moments(_ scenario: Scenario) -> [String: JSONValue] {
            Self.index(scenario.moments.map { moment in
                var fields = moment.fields
                fields.removeValue(forKey: "activities")
                return .object(fields)
            })
        }
        struct Placement { let activity: JSONValue; let moment: String }
        func placements(_ scenario: Scenario) -> [String: Placement] {
            var result: [String: Placement] = [:]
            for moment in scenario.moments {
                for activity in moment.activities where result[activity.id] == nil {
                    result[activity.id] = Placement(activity: .object(activity.fields), moment: moment.id)
                }
            }
            return result
        }
        func activityOrder(_ scenario: Scenario, in momentID: String) -> [String] {
            scenario.moments.first { $0.id == momentID }?.activities.map(\.id) ?? []
        }

        let bm = moments(base), lm = moments(local), rm = moments(remote)
        var merged: [String: JSONValue] = [:]
        for id in Set(bm.keys).union(lm.keys).union(rm.keys) {
            if let moment = item(bm[id], lm[id], rm[id], path: "/sessions/" + id, viewState: ScenarioMerge.viewStateMomentKeys) {
                merged[id] = moment
            }
        }

        let ba = placements(base), la = placements(local), ra = placements(remote)
        var activities: [String: Placement] = [:]
        for id in Set(ba.keys).union(la.keys).union(ra.keys) {
            let b = ba[id], l = la[id], r = ra[id]
            func changed(_ side: Placement) -> Bool { b.map { $0.activity != side.activity || $0.moment != side.moment } ?? true }
            switch (l, r) {
            case (nil, nil): continue
            case (let l?, nil): if changed(l) { activities[id] = l }
            case (nil, let r?): if changed(r) { activities[id] = r }
            case (let l?, let r?):
                let content = item(b?.activity, l.activity, r.activity, path: "/activities/" + id) ?? l.activity
                // Déplacée des deux côtés vers des moments différents : le déplacement local l’emporte.
                let moment = l.moment == r.moment || l.moment != b?.moment ? l.moment : r.moment
                activities[id] = Placement(activity: content, moment: moment)
            }
        }
        // Un moment supprimé d’un côté revient si l’autre côté y a modifié ou ajouté une activité.
        for moment in Set(activities.values.map(\.moment)) where merged[moment] == nil {
            if let restored = lm[moment] ?? rm[moment] ?? bm[moment] { merged[moment] = restored }
        }
        for (id, placement) in activities where merged[placement.moment] == nil { activities.removeValue(forKey: id) }

        func momentIDs(_ scenario: Scenario) -> [String] { scenario.moments.map(\.id) }
        let order = ScenarioMerge.order(base: momentIDs(base), local: momentIDs(local), remote: momentIDs(remote),
                                        keep: Set(merged.keys))
        return order.compactMap { id in
            guard var fields = merged[id]?.object else { return nil }
            let members = Set(activities.filter { $0.value.moment == id }.map(\.key))
            let ids = ScenarioMerge.order(base: activityOrder(base, in: id), local: activityOrder(local, in: id),
                                          remote: activityOrder(remote, in: id), keep: members)
            fields["activities"] = .array(ids.compactMap { activities[$0]?.activity })
            return .object(fields)
        }
    }

    /// Listes de valeurs simples (compétences) : les ajouts et retraits des deux côtés s’appliquent.
    private func stringSet(_ base: [JSONValue], _ local: [JSONValue], _ remote: [JSONValue]) -> [JSONValue] {
        let b = Set(base.map(\.string)), r = Set(remote.map(\.string)), l = Set(local.map(\.string))
        let removed = b.subtracting(l).union(b.subtracting(r))
        var result = local.filter { !removed.contains($0.string) }
        var present = Set(result.map(\.string))
        for value in remote where !removed.contains(value.string) && !present.contains(value.string) {
            result.append(value); present.insert(value.string)
        }
        return result
    }

    private static func identified(_ values: [JSONValue]) -> Bool {
        var seen = Set<String>()
        for value in values {
            guard case .object(let object) = value, case .string(let id)? = object["id"], !id.isEmpty,
                  seen.insert(id).inserted else { return false }
        }
        return true
    }
    private static func strings(_ values: [JSONValue]) -> Bool {
        values.allSatisfy { if case .string = $0 { true } else { false } } && Set(values.map(\.string)).count == values.count
    }
    private static func index(_ values: [JSONValue]) -> [String: JSONValue] {
        Dictionary(values.map { ($0.object["id"]?.string ?? "", $0) }, uniquingKeysWith: { first, _ in first })
    }
    private static func ids(_ values: [JSONValue]) -> [String] { values.map { $0.object["id"]?.string ?? "" } }
    private static func same(_ a: JSONValue, _ b: JSONValue, ignoring keys: Set<String>) -> Bool {
        guard !keys.isEmpty, case .object(var x) = a, case .object(var y) = b else { return a == b }
        for key in keys { x.removeValue(forKey: key); y.removeValue(forKey: key) }
        return x == y
    }
}

/// Fusion à trois voies d’un texte : par lignes, puis par mots lorsque deux lignes voisines ont changé.
enum TextMerge {
    /// - Returns: `nil` si les deux modifications portent sur le même passage.
    static func merge(base: String, local: String, remote: String) -> String? {
        if local == remote || remote == base { return local }
        if local == base { return remote }
        for split in [lines, words] {
            if let merged = merge3(split(base), split(local), split(remote)) { return merged.joined() }
        }
        return nil
    }

    static func lines(_ text: String) -> [Substring] {
        var result: [Substring] = []
        var start = text.startIndex
        for index in text.indices where text[index].isNewline {
            result.append(text[start...index]); start = text.index(after: index)
        }
        if start < text.endIndex { result.append(text[start...]) }
        return result
    }

    static func words(_ text: String) -> [Substring] {
        var result: [Substring] = []
        var start = text.startIndex
        var space: Bool?
        for index in text.indices {
            let current = text[index].isWhitespace
            if let space, space != current { result.append(text[start..<index]); start = index }
            space = current
        }
        if start < text.endIndex { result.append(text[start...]) }
        return result
    }

    /// Passage `base[start..<end]` remplacé par `tokens` (insertion pure si `start == end`).
    private struct Hunk {
        let start: Int, end: Int
        let tokens: [Substring]
        let local: Bool
        var insertion: Bool { start == end }
        func overlaps(_ other: Hunk) -> Bool {
            switch (insertion, other.insertion) {
            case (true, true): start == other.start
            case (true, false): other.start < start && start < other.end
            case (false, true): start < other.start && other.start < end
            case (false, false): start < other.end && other.start < end
            }
        }
    }

    private static func hunks(_ base: [Substring], _ side: [Substring], local: Bool) -> [Hunk] {
        var removed = Set<Int>(), inserted = Set<Int>()
        for change in side.difference(from: base) {
            switch change {
            case .remove(let offset, _, _): removed.insert(offset)
            case .insert(let offset, _, _): inserted.insert(offset)
            }
        }
        var result: [Hunk] = []
        var b = 0, s = 0
        while b < base.count || s < side.count {
            if b < base.count, s < side.count, !removed.contains(b), !inserted.contains(s) { b += 1; s += 1; continue }
            let start = b
            var tokens: [Substring] = []
            while true {
                if b < base.count, removed.contains(b) { b += 1 }
                else if s < side.count, inserted.contains(s) { tokens.append(side[s]); s += 1 }
                else { break }
            }
            guard b > start || !tokens.isEmpty else { break }
            result.append(Hunk(start: start, end: b, tokens: tokens, local: local))
        }
        return result
    }

    private static func merge3(_ base: [Substring], _ local: [Substring], _ remote: [Substring]) -> [Substring]? {
        let all = (hunks(base, local, local: true) + hunks(base, remote, local: false))
            .sorted { ($0.start, $0.end, $0.local ? 0 : 1) < ($1.start, $1.end, $1.local ? 0 : 1) }
        var result: [Substring] = []
        var cursor = 0
        var index = 0
        while index < all.count {
            var group = [all[index]]
            var end = all[index].end
            index += 1
            while index < all.count, group.contains(where: { $0.local != all[index].local && $0.overlaps(all[index]) }) {
                end = max(end, all[index].end); group.append(all[index]); index += 1
            }
            let start = group[0].start
            result += base[cursor..<start]
            let mine = group.filter(\.local), theirs = group.filter { !$0.local }
            if mine.isEmpty || theirs.isEmpty {
                result += apply(group, base: base, from: start, to: end)
            } else if group.allSatisfy(\.insertion) {
                // Deux ajouts au même endroit : les deux sont gardés, l’ajout local en premier.
                let a = mine.flatMap(\.tokens), b = theirs.flatMap(\.tokens)
                result += a == b ? a : a + b
            } else {
                let a = apply(mine, base: base, from: start, to: end), b = apply(theirs, base: base, from: start, to: end)
                guard a == b else { return nil }
                result += a
            }
            cursor = end
        }
        result += base[cursor...]
        return result
    }

    private static func apply(_ hunks: [Hunk], base: [Substring], from start: Int, to end: Int) -> [Substring] {
        var result: [Substring] = []
        var cursor = start
        for hunk in hunks {
            result += base[cursor..<hunk.start]; result += hunk.tokens; cursor = hunk.end
        }
        result += base[cursor..<end]
        return result
    }
}
