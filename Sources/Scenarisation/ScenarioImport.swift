import Foundation
import UniformTypeIdentifiers

/// All file entry points use the same reader; the editable document remains JSON.
enum ScenarioImporter {
    static let extensions = ["json", "ldj", "md", "markdown", "csv", "xlsx", "html", "htm", "docx"]
    static var contentTypes: [UTType] {
        extensions.compactMap { UTType(filenameExtension: $0) }
    }
    static let limit = 20_000_000

    static func read(_ url: URL) throws -> Scenario {
        let size = try url.resourceValues(forKeys: [.fileSizeKey]).fileSize ?? 0
        guard size <= limit else { throw ScenarioError.invalid("Le document dépasse 20 Mo.") }
        return try decode(Data(contentsOf: url), filename: url.lastPathComponent)
    }

    static func decode(_ data: Data, filename: String) throws -> Scenario {
        guard data.count <= limit else { throw ScenarioError.invalid("Le document dépasse 20 Mo.") }
        let ext = (filename as NSString).pathExtension.lowercased()
        let scenario: Scenario
        switch ext {
        case "json", "ldj": scenario = try json(data)
        case "md", "markdown": scenario = try ScenarioTextReader.markdown(text(data))
        case "csv": scenario = try ScenarioTableReader.scenario(ScenarioTableReader.csv(text(data)))
        case "xlsx": scenario = try ScenarioTableReader.scenario(ScenarioOfficeReader.spreadsheet(data))
        case "html", "htm": scenario = try ScenarioTextReader.markdown(ScenarioOfficeReader.html(text(data)))
        case "docx": scenario = try ScenarioTextReader.markdown(ScenarioOfficeReader.word(data))
        default: throw ScenarioError.invalid("Format non pris en charge. Choisissez un fichier JSON, LDJ, Markdown, CSV, Excel (.xlsx), HTML ou Word (.docx).")
        }
        // The common validator also repairs missing or duplicated identifiers.
        return try Scenario.decode(scenario.encoded())
    }

    static func text(_ data: Data) throws -> String {
        let value: String?
        if data.starts(with: [0xff, 0xfe]) || data.starts(with: [0xfe, 0xff]) {
            value = String(data: data, encoding: .utf16)
        } else {
            value = String(data: data, encoding: .utf8)
        }
        guard let value else { throw ScenarioError.invalid("Le fichier texte doit être encodé en UTF-8 ou UTF-16.") }
        return (value.hasPrefix("\u{feff}") ? String(value.dropFirst()) : value)
            .replacingOccurrences(of: "\r\n", with: "\n").replacingOccurrences(of: "\r", with: "\n")
    }

    private static func json(_ data: Data) throws -> Scenario {
        let root = try JSONDecoder().decode(JSONValue.self, from: data).object
        if root["sessions"] != nil, root["meta"] != nil { return try Scenario.decode(data) }
        if root["exportType"]?.string == "student_instructions", case .array(let sessions) = root["sessions"] {
            var result = Scenario.blank()
            result.meta["name"] = root["title"] ?? .string("Sans titre")
            result.moments = try sessions.map { item in
                guard case .object(let fields) = item, case .array(let activities) = fields["activities"] else {
                    throw ScenarioError.invalid("Le fichier de consignes contient une séance invalide.")
                }
                var moment = Moment.blank(title: fields["title"]?.string ?? "Sans titre")
                moment.activities = try activities.map { item in
                    guard case .object(let fields) = item else { throw ScenarioError.invalid("Activité invalide.") }
                    var activity = Activity.blank()
                    activity["instructions"] = fields["instructions"]?.string ?? ""
                    return activity
                }
                return moment
            }
            return result
        }
        guard case .array(let sessions) = root["activities"] else {
            throw ScenarioError.invalid("Ce fichier ne contient pas de scénario JSON ou LDJ reconnu.")
        }
        var result = Scenario.blank()
        func string(_ fields: [String: JSONValue], _ key: String) -> String {
            if case .number(let n) = fields[key] { return String(n) }
            if case .bool(let b) = fields[key] { return b ? "true" : "false" }
            return fields[key]?.string ?? ""
        }
        for (source, target) in [("name", "name"), ("description", "description"), ("topic", "command"),
                                  ("aims", "personas"), ("author", "designers"), ("groupSize", "sizeClass")] {
            result.meta[target] = .string(string(root, source))
        }
        result.meta["modeDelivery"] = .string(ImportValues.choice(string(root, "modeOfDelivery"), key: "locationMode"))
        ImportValues.learningTime(string(root, "learningTime"), into: &result)
        result.meta["sliders"] = .array((root["outcomes"]?.array ?? []).map { value in
            .object(ImportValues.outcome(string(value.object, "details"), verb: string(value.object, "verb")))
        })
        result.moments = try sessions.enumerated().map { index, value in
            guard case .object(let fields) = value else { throw ScenarioError.invalid("Séance LDJ invalide.") }
            let title = string(fields, "title")
            var moment = Moment.blank(title: title.isEmpty ? "Séance \(index + 1)" : title)
            moment["intentions"] = string(fields, "teachingMethod")
            moment["notes"] = ([string(fields, "notes")] + (fields["resources"]?.array ?? []).map(\.string))
                .filter { !$0.isEmpty }.joined(separator: "\n\n")
            if let slas = fields["slas"] {
                guard case .array = slas else { throw ScenarioError.invalid("La séance LDJ doit contenir une liste d’activités.") }
            }
            moment.activities = try (fields["slas"]?.array ?? []).map { value in
                guard case .object(let item) = value else { throw ScenarioError.invalid("Activité LDJ invalide.") }
                var activity = Activity.blank()
                activity["type"] = ImportValues.choice(string(item, "type"), key: "type")
                activity.duration = try ImportValues.duration(string(item, "duration"))
                let sessionSize = Double(string(fields, "groupSize")) ?? Double(string(root, "groupSize")) ?? 0
                let size = Double(string(item, "groupSize")) ?? 0
                activity["groupMode"] = string(item, "groupSizeSameAsSession") == "true" ?
                    (sessionSize > 1 ? "whole" : "individual") : (size <= 1 ? "individual" : size < max(2, sessionSize) ? "subgroups" : "whole")
                activity["teachingMode"] = string(item, "tutorAvailable") == "true" ? "undefined" : "independent"
                activity["syncMode"] = string(item, "syncActivity") == "true" ? "sync" : "async"
                activity["locationMode"] = string(item, "onlineActivity") == "true" ? "online" : "onsite"
                activity["evaluationMode"] = ImportValues.choice(string(item, "assessmentType"), key: "evaluationMode")
                activity["description"] = string(item, "description")
                activity["instructions"] = string(item, item["instructions"] == nil ? "studentInstructions" : "instructions")
                activity["notes"] = (item["resources"]?.array ?? []).map(\.string).joined(separator: "\n")
                return activity
            }
            return moment
        }
        return result
    }
}

enum ImportValues {
    static func token(_ value: String) -> String {
        value.folding(options: [.diacriticInsensitive, .caseInsensitive], locale: Locale(identifier: "en_US_POSIX"))
            .replacingOccurrences(of: "’", with: "'").replacingOccurrences(of: "\u{a0}", with: " ")
            .split(whereSeparator: { $0.isWhitespace }).joined(separator: " ")
    }
    static func clean(_ value: String) -> String {
        let value = value.trimmingCharacters(in: .whitespacesAndNewlines)
        return value == "-" ? "" : value
    }
    static func captures(_ pattern: String, _ value: String) -> [String]? {
        guard let regex = try? NSRegularExpression(pattern: pattern, options: [.caseInsensitive]),
              let match = regex.firstMatch(in: value, range: NSRange(value.startIndex..., in: value)) else { return nil }
        return (1..<match.numberOfRanges).map { index in
            Range(match.range(at: index), in: value).map { String(value[$0]) } ?? ""
        }
    }
    static func duration(_ value: String) throws -> Double {
        if clean(value).isEmpty { return 10 }
        guard let match = captures("^\\s*([0-9]+(?:[.,][0-9]+)?)\\s*(?:min(?:utes?)?)?\\s*$", value),
              let number = Double(match[0].replacingOccurrences(of: ",", with: ".")), number > 0, number <= 100_000 else {
            throw ScenarioError.invalid("Durée d’activité invalide : « \(value) ». Indiquez un nombre positif de minutes.")
        }
        return number
    }
    static func choice(_ value: String, key: String) -> String {
        let choices: [Choice]
        switch key {
        case "type": choices = Pedagogy.types
        case "groupMode": choices = Pedagogy.groups
        case "teachingMode": choices = Pedagogy.teaching
        case "syncMode": choices = Pedagogy.rhythms
        case "locationMode": choices = Pedagogy.locations
        default: choices = Pedagogy.evaluations
        }
        let normalized = token(value)
        if let match = choices.first(where: { token($0.id) == normalized || token($0.label) == normalized }) { return match.id }
        let aliases: [String: [String]] = [
            "read": ["acquisition", "read / watch / listen", "read, watch, listen", "lire, regarder, ecouter"],
            "investigate": ["investigation", "investigate"], "practice": ["practice"], "produce": ["production", "produce"],
            "discuss": ["discussion", "discuss"], "collaborate": ["collaboration", "collaborate"],
            "whole": ["whole class", "whole group", "classe entiere"], "subgroups": ["small groups", "sous-groupes", "sub-groups"],
            "individual": ["individual"], "directed": ["teacher directed", "teacher-directed", "directed", "dirige par l'enseignant", "enseignement dirige"],
            "guided": ["teacher guided", "teacher-guided", "guided", "guide par l'enseignant", "enseignement guide"],
            "supported": ["teacher supported", "teacher-supported", "supported", "accompagne par l'enseignant", "enseignement accompagne"],
            "independent": ["independent", "independent work", "autonome", "teacher absent", "absent", "enseignement en autonomie"],
            "undefined": ["a definir", "undefined", "to be defined", "present", "teacher present"],
            "sync": ["synchronous"], "async": ["asynchronous"],
            "onsite": ["presentiel", "face to face", "classroom", "in class", "classroom-based"],
            "location_based": ["location based", "place-based", "on site", "sur le terrain"],
            "online": ["distanciel", "distance"], "hybrid": ["blended", "blended learning"], "other": ["other"],
            "none": ["no assessment", "aucune evaluation", "none"], "diagnostic": ["diagnostic"],
            "formative": ["formative"], "summative": ["summative"], "certificative": ["certifying", "certificative"]]
        return choices.first { (aliases[$0.id] ?? []).contains(normalized) }?.id ?? choices[0].id
    }
    static func school(_ value: String, system: String? = nil) -> String {
        guard let catalog = try? SchoolCatalog.bundled.get() else { return value }
        let options = system.map { catalog.levels[$0] ?? [] } ?? catalog.systems
        return options.first { token($0.value) == token(value) || $0.labels.values.contains { token($0) == token(value) } }?.value ?? clean(value)
    }
    static func learningTime(_ value: String, into scenario: inout Scenario) {
        let normalized = token(value)
        for (key, pattern) in [("learningDays", "([0-9]+(?:[.,][0-9]+)?)\\s*(?:j(?:ours?)?|days?)\\b"),
                               ("learningHours", "([0-9]+(?:[.,][0-9]+)?)\\s*(?:h(?:eures?)?|hours?)\\b"),
                               ("learningMinutes", "([0-9]+(?:[.,][0-9]+)?)\\s*(?:min(?:utes?)?)\\b")] {
            let number = captures(pattern, normalized).flatMap { Double($0[0].replacingOccurrences(of: ",", with: ".")) } ?? 0
            scenario.meta[key] = .number(number)
        }
        if let minutes = Double(normalized) { scenario.meta["learningMinutes"] = .number(minutes) }
    }
    static func outcome(_ text: String, verb: String = "") -> [String: JSONValue] {
        ["id": .string(UUID().uuidString), "category": .string(""), "categoryLabel": .string(""),
         "verb": .string(verb), "text": .string(text)]
    }
    static func outcomes(_ value: String) -> JSONValue {
        .array(value.components(separatedBy: CharacterSet(charactersIn: "|;\n")).compactMap { line in
            let line = clean(line.replacingOccurrences(of: "^[-•]\\s*", with: "", options: .regularExpression))
            guard !line.isEmpty else { return nil }
            let colon = line.firstIndex(of: ":")
            return .object(outcome(colon.map { clean(String(line[line.index(after: $0)...])) } ?? line,
                                   verb: colon.map { clean(String(line[..<$0])) } ?? ""))
        })
    }
    static func aias(_ value: String, activity: inout Activity) {
        let normalized = token(value)
        if let number = captures("(?:^|\\b)([1-5])(?:\\b|$)", normalized)?.first { activity.aiasSelection = number }
        else { activity.aiasSelection = ["not_applicable", "non applicable", "not applicable", "non pertinent", "n/a"].contains(where: { normalized.contains($0) }) ? "not_applicable" : "undecided" }
    }
    private static let competencyAliases: [(label: String, id: String)] = {
        guard let catalog = try? CompetencyCatalog.bundled.get() else { return [] }
        var index: [String: Set<String>] = [:]
        for item in catalog.competencies {
            let labels = [item.labelFr, item.labelEn ?? item.labelFr]
            let framework = catalog.frameworks.first { $0.id == item.frameworkId }
            var variants = [item.id, item.code] + (item.aliases ?? []) + labels
            variants += labels.flatMap { ["\(item.code). \($0)", "\(item.code) · \($0)"] }
            for name in [framework?.labelFr, framework?.labelEn].compactMap({ $0 }) {
                variants += ["\(name) \(item.code)", "\(name) · \(item.code)"]
            }
            for variant in variants { index[token(variant), default: []].insert(item.id) }
        }
        return index.compactMap { label, ids in ids.count == 1 ? (label, ids.first!) : nil }
            .sorted { $0.label.count > $1.label.count }
    }()

    static func tools(_ value: String) -> JSONValue {
        var values: [JSONValue] = []
        for part in value.components(separatedBy: CharacterSet(charactersIn: ";|")) {
            let raw = clean(part)
            if raw.isEmpty { continue }
            var remaining = token(raw); var found: [JSONValue] = []
            // Longest known labels disambiguate commas inside competency wording.
            while !remaining.isEmpty {
                guard let match = competencyAliases.first(where: { alias in
                    remaining == alias.label || remaining.hasPrefix(alias.label + ",")
                }) else { break }
                found.append(.string(match.id))
                remaining = clean(String(remaining.dropFirst(match.label.count)).trimmingCharacters(in: CharacterSet(charactersIn: ", ")))
            }
            values += remaining.isEmpty ? found : [.string(raw)]
        }
        return .array(values)
    }
}
