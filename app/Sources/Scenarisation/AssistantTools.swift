import Foundation

enum AssistantTools {
    static func run(_ name: String, arguments: [String: JSONValue], scenario: inout Scenario,
                    reference: Scenario) throws -> JSONValue {
        switch name {
        case "catalog": return try catalog(arguments)
        case "analyze": return analysis(scenario)
        case "validate":
            _ = try validated(scenario, reference: reference)
            return .object(["valid": .bool(true), "scope": .string("Structure et identifiants ; la qualité pédagogique reste à apprécier.")])
        case "add_outcome":
            let category = arguments["category"]?.string ?? ""
            let verb = arguments["verb"]?.string ?? ""
            let text = (arguments["text"]?.string ?? "").trimmingCharacters(in: .whitespacesAndNewlines)
            guard let bloom = try BloomCategory.bundled.get().first(where: { $0.id == category }),
                  bloom.verbs.contains(verb), !text.isEmpty else {
                throw ScenarioError.invalid("Choisissez une catégorie et un verbe du catalogue Bloom et formulez un acquis observable.")
            }
            let id = UUID().uuidString
            var candidate = scenario
            var outcomes = candidate.meta["sliders"]?.array ?? []
            outcomes.append(.object(["id": .string(id), "category": .string(category), "categoryLabel": .string(bloom.label),
                "verb": .string(verb), "text": .string(text)]))
            candidate.meta["sliders"] = .array(outcomes)
            // Outcomes may be prepared before the first moment/activity exists.
            // The final proposal still goes through the complete validator.
            scenario = candidate
            return .object(["outcome_id": .string(id)])
        case "add_moment":
            guard case .object(let fields) = arguments["moment"],
                  !(fields["title"]?.string ?? "").trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
                  case .array(let entries) = fields["activities"], !entries.isEmpty else {
                throw ScenarioError.invalid("Le nouveau moment doit avoir un titre et des activités complètes.")
            }
            var moment = Moment.blank()
            moment.fields.merge(fields) { _, new in new }
            moment["id"] = UUID().uuidString
            moment.activities = try entries.map { value in
                guard case .object(let fields) = value else { throw ScenarioError.invalid("Activité invalide.") }
                var activity = Activity.blank()
                activity.fields.merge(fields) { _, new in new }
                activity["id"] = UUID().uuidString
                guard activity["type"] != "undefined", activity["teachingMode"] != "undefined",
                      activity.aiasSelection != "undecided",
                      !activity["description"].trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
                      !activity["instructions"].trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
                    throw ScenarioError.invalid("Définissez le type, l’enseignement, AIAS, la description et les consignes des nouvelles activités.")
                }
                return activity
            }
            var candidate = scenario
            var moments = candidate.moments
            let index = try integer(arguments["index"], fallback: moments.count, maximum: moments.count)
            moments.insert(moment, at: index); candidate.moments = moments
            scenario = try validated(candidate, reference: reference)
            return .object(["moment_id": .string(moment.id), "minutes": .number(moment.duration),
                "activity_ids": .array(moment.activities.map { .string($0.id) })])
        case "update_activity":
            let id = arguments["activity_id"]?.string ?? ""
            let allowed: Set<String> = ["description", "instructions", "notes", "duration", "type", "groupMode",
                "teachingMode", "syncMode", "locationMode", "evaluationMode", "aias", "tools"]
            guard !id.isEmpty, case .object(let changes) = arguments["changes"], !changes.isEmpty,
                  Set(changes.keys).isSubset(of: allowed) else {
                throw ScenarioError.invalid("Précisez l’activité et uniquement les champs modifiables ; son identifiant doit être conservé.")
            }
            var candidate = scenario
            var moments = candidate.moments
            guard let momentIndex = moments.firstIndex(where: { $0.activities.contains { $0.id == id } }),
                  let activityIndex = moments[momentIndex].activities.firstIndex(where: { $0.id == id }) else {
                throw ScenarioError.invalid("Cette activité n’existe pas dans le scénario préparé.")
            }
            var activities = moments[momentIndex].activities
            activities[activityIndex].fields.merge(changes) { _, new in new }
            moments[momentIndex].activities = activities; candidate.moments = moments
            scenario = try validated(candidate, reference: reference)
            return .object(["activity_id": .string(id), "updated": .bool(true)])
        default:
            throw ScenarioError.invalid("Cet outil n’est pas disponible dans Scénarisation.")
        }
    }

    static func validated(_ scenario: Scenario, reference: Scenario) throws -> Scenario {
        let result = try Scenario.decode(scenario.encoded(), strict: true)
        let existing = Set(reference.moments.flatMap(\.activities).flatMap { $0.fields["tools"]?.array ?? [] }.map(\.string))
        let catalog = try CompetencyCatalog.bundled.get()
        let known = Set(catalog.competencies.map(\.id))
        for activity in result.moments.flatMap(\.activities) {
            for value in activity.fields["tools"]?.array ?? [] {
                guard case .string(let id) = value, existing.contains(id) || known.contains(id) else {
                    throw ScenarioError.invalid("Une compétence ajoutée est inconnue. Consultez le catalogue et utilisez son identifiant exact.")
                }
            }
        }
        let bloom = try BloomCategory.bundled.get()
        let previous = reference.meta["sliders"]?.array ?? []
        if let value = result.meta["sliders"], value != reference.meta["sliders"] {
            guard case .array = value else {
                throw ScenarioError.invalid("Les acquis d’apprentissage doivent être une liste.")
            }
        }
        for value in result.meta["sliders"]?.array ?? [] where !previous.contains(value) {
            let outcome = value.object
            guard let category = bloom.first(where: { $0.id == outcome["category"]?.string }),
                  category.verbs.contains(outcome["verb"]?.string ?? ""),
                  !(outcome["text"]?.string ?? "").trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
                throw ScenarioError.invalid("Un acquis ajouté ou modifié doit utiliser une catégorie et un verbe de Bloom connus et un texte observable.")
            }
        }
        let system = result.meta["schoolSystem"]?.string ?? ""
        let level = result.meta["schoolLevel"]?.string ?? ""
        if system != (reference.meta["schoolSystem"]?.string ?? "") || level != (reference.meta["schoolLevel"]?.string ?? "") {
            let schools = try SchoolCatalog.bundled.get()
            guard (system.isEmpty && level.isEmpty) ||
                    (schools.systems.contains { $0.value == system } && (level.isEmpty ||
                        (schools.levels[system] ?? []).contains { $0.value == level })) else {
                throw ScenarioError.invalid("Le système scolaire et le niveau doivent correspondre aux identifiants du catalogue.")
            }
        }
        return result
    }

    static func analysis(_ scenario: Scenario) -> JSONValue {
        let activities = scenario.moments.flatMap(\.activities)
        func distribution(_ key: String) -> JSONValue {
            .object(Dictionary(grouping: activities, by: { $0[key] }).mapValues { .number($0.reduce(0) { $0 + $1.duration }) })
        }
        let planned = (scenario.meta["learningDays"]?.number ?? 0) * (scenario.meta["dayHours"]?.number ?? 7) * 60
            + (scenario.meta["learningHours"]?.number ?? 0) * 60 + (scenario.meta["learningMinutes"]?.number ?? 0)
        return .object(["minutes": .number(scenario.duration), "planned_minutes": .number(planned),
            "activities": .number(Double(scenario.activityCount)), "moments": .number(Double(scenario.moments.count)),
            "minutes_by_type": distribution("type"), "minutes_by_evaluation": distribution("evaluationMode"),
            "minutes_by_group": distribution("groupMode"),
            "moments_without_objectives": .array(scenario.moments.filter { $0["objectives"].isEmpty }.map { .string($0.id) })])
    }

    private static func catalog(_ arguments: [String: JSONValue]) throws -> JSONValue {
        let topic = arguments["topic"]?.string ?? ""
        let search = (arguments["search"]?.string ?? "").trimmingCharacters(in: .whitespacesAndNewlines)
        let items: [JSONValue]
        switch topic {
        case "frameworks":
            items = try CompetencyCatalog.bundled.get().frameworks.map {
                .object(["id": .string($0.id), "label": .string($0.labelFr),
                         "groups": .array($0.groups.map { .object(["id": .string($0.id), "label": .string($0.labelFr)]) })])
            }
        case "competencies":
            let catalog = try CompetencyCatalog.bundled.get()
            let framework = arguments["framework"]?.string ?? ""
            guard catalog.frameworks.contains(where: { $0.id == framework }) else {
                throw ScenarioError.invalid("Choisissez un référentiel du catalogue frameworks avant de chercher ses compétences.")
            }
            let group = arguments["group"]?.string ?? ""
            guard group.isEmpty || catalog.frameworks.first(where: { $0.id == framework })?.groups.contains(where: { $0.id == group }) == true else {
                throw ScenarioError.invalid("Choisissez un domaine appartenant au référentiel sélectionné.")
            }
            items = catalog.matching(frameworkID: framework, groupID: group, search: search).map {
                .object(["id": .string($0.id), "code": .string($0.code), "label": .string($0.labelFr),
                    "description": .string($0.descriptionFr), "groupId": .string($0.groupId),
                    "context": .string(catalog.context(for: $0))])
            }
        case "school_systems":
            items = try SchoolCatalog.bundled.get().systems.map { .object(["id": .string($0.value), "label": .string($0.choice.label)]) }
        case "school_levels":
            let catalog = try SchoolCatalog.bundled.get()
            let system = arguments["system"]?.string ?? ""
            guard catalog.systems.contains(where: { $0.value == system }) else {
                throw ScenarioError.invalid("Choisissez un système scolaire du catalogue school_systems.")
            }
            items = (catalog.levels[system] ?? []).map { .object(["id": .string($0.value), "label": .string($0.choice.label)]) }
        case "bloom":
            items = try BloomCategory.bundled.get().map {
                .object(["id": .string($0.id), "label": .string($0.label), "verbs": .array($0.verbs.map { .string($0) })])
            }
        default: throw ScenarioError.invalid("Catalogue inconnu. Utilisez frameworks, competencies, school_systems, school_levels ou bloom.")
        }
        let filtered = topic == "competencies" ? items : items.filter { search.isEmpty || searchable($0).localizedStandardContains(search) }
        let offset = try integer(arguments["offset"], fallback: 0, maximum: filtered.count)
        let end = min(offset + 40, filtered.count)
        return .object(["items": .array(Array(filtered[offset..<end])), "total": .number(Double(filtered.count)),
            "nextOffset": end < filtered.count ? .number(Double(end)) : .null])
    }

    private static func searchable(_ value: JSONValue) -> String {
        switch value {
        case .object(let fields): fields.values.map(searchable).joined(separator: " ")
        case .array(let values): values.map(searchable).joined(separator: " ")
        case .string(let text): text
        default: ""
        }
    }

    private static func integer(_ value: JSONValue?, fallback: Int, maximum: Int) throws -> Int {
        guard let value else { return fallback }
        guard case .number(let number) = value, number.isFinite, number >= 0, number <= Double(maximum), number.rounded() == number else {
            throw ScenarioError.invalid("La position demandée est invalide.")
        }
        return Int(number)
    }
}
