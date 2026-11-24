import Foundation

// Keep unexposed web fields intact when a document travels between Mac and web.
enum JSONValue: Codable, Equatable, Sendable {
    case object([String: JSONValue]), array([JSONValue]), string(String)
    case number(Double), bool(Bool), null

    init(from decoder: Decoder) throws {
        let c = try decoder.singleValueContainer()
        if c.decodeNil() { self = .null }
        else if let v = try? c.decode(Bool.self) { self = .bool(v) }
        else if let v = try? c.decode(String.self) { self = .string(v) }
        else if let v = try? c.decode(Double.self) { self = .number(v) }
        else if let v = try? c.decode([String: JSONValue].self) { self = .object(v) }
        else { self = .array(try c.decode([JSONValue].self)) }
    }

    func encode(to encoder: Encoder) throws {
        var c = encoder.singleValueContainer()
        switch self {
        case .object(let v): try c.encode(v)
        case .array(let v): try c.encode(v)
        case .string(let v): try c.encode(v)
        case .number(let v): try c.encode(v)
        case .bool(let v): try c.encode(v)
        case .null: try c.encodeNil()
        }
    }

    var object: [String: JSONValue] { if case .object(let v) = self { v } else { [:] } }
    var array: [JSONValue] { if case .array(let v) = self { v } else { [] } }
    var string: String { if case .string(let v) = self { v } else { "" } }
    var number: Double { if case .number(let v) = self { v } else { 0 } }
}

protocol JSONRecord {
    var fields: [String: JSONValue] { get set }
}

extension JSONRecord {
    subscript(_ key: String) -> String {
        get { fields[key]?.string ?? "" }
        set { fields[key] = .string(newValue) }
    }
}

struct Choice: Identifiable, Sendable {
    let id: String
    let label: String
    init(_ id: String, _ label: String) { self.id = id; self.label = label }
}

enum Pedagogy {
    static let types = [Choice("undefined", "À définir"), Choice("read", "Lire / Regarder / Écouter"),
        Choice("investigate", "Investiguer"), Choice("practice", "Pratiquer"),
        Choice("produce", "Produire"), Choice("discuss", "Discuter"), Choice("collaborate", "Collaborer")]
    static let groups = [Choice("whole", "Groupe entier"), Choice("subgroups", "Sous-groupes"), Choice("individual", "Individuel")]
    static let teaching = [Choice("undefined", "À définir"), Choice("directed", "Dirigé"), Choice("guided", "Guidé"),
        Choice("supported", "Accompagné"), Choice("independent", "En autonomie")]
    static let rhythms = [Choice("sync", "Synchrone"), Choice("async", "Asynchrone")]
    static let locations = [Choice("onsite", "En classe"), Choice("location_based", "Sur site"),
        Choice("online", "En ligne"), Choice("hybrid", "Hybride"), Choice("other", "Autre")]
    static let evaluations = [Choice("none", "Aucune"), Choice("diagnostic", "Diagnostique"),
        Choice("formative", "Formative"), Choice("summative", "Sommative"), Choice("certificative", "Certificative")]
    static func label(_ value: String, in choices: [Choice]) -> String {
        choices.first { $0.id == value }?.label ?? value
    }
}

struct Activity: JSONRecord, Identifiable, Equatable, Sendable {
    var fields: [String: JSONValue]
    var id: String { self["id"] }
    var duration: Double {
        get { fields["duration"]?.number ?? 10 }
        set { fields["duration"] = .number(newValue) }
    }
    var aiasSelection: String {
        get {
            let value = fields["aias"]?.object ?? [:]
            if value["status"]?.string == "specified", let level = value["level"]?.number,
               [1, 2, 3, 4, 5].contains(level) { return String(Int(level)) }
            return value["status"]?.string == "not_applicable" ? "not_applicable" : "undecided"
        }
        set {
            var value = fields["aias"]?.object ?? [:]
            value["version"] = value["version"] ?? .string("2.1")
            value["status"] = .string(Int(newValue) == nil ? newValue : "specified")
            value["level"] = Int(newValue).map { .number(Double($0)) } ?? .null
            fields["aias"] = .object(value)
        }
    }
    static func blank() -> Activity {
        Activity(fields: ["id": .string(UUID().uuidString), "type": .string("undefined"), "duration": .number(10),
            "groupMode": .string("whole"), "teachingMode": .string("undefined"), "syncMode": .string("sync"),
            "locationMode": .string("onsite"), "evaluationMode": .string("none"), "description": .string(""),
            "instructions": .string(""), "notes": .string(""), "tools": .array([]),
            "aias": .object(["version": .string("2.1"), "status": .string("undecided"), "level": .null])])
    }
}

struct Moment: JSONRecord, Identifiable, Equatable, Sendable {
    var fields: [String: JSONValue]
    var id: String { self["id"] }
    var activities: [Activity] {
        get { (fields["activities"]?.array ?? []).map { Activity(fields: $0.object) } }
        set { fields["activities"] = .array(newValue.map { .object($0.fields) }) }
    }
    var duration: Double { activities.reduce(0) { $0 + $1.duration } }
    static func blank(title: String = "Nouveau moment") -> Moment {
        Moment(fields: ["id": .string(UUID().uuidString), "title": .string(title), "objectives": .string(""),
            "intentions": .string(""), "notes": .string(""), "notesExpanded": .bool(false), "activities": .array([])])
    }
}

struct Scenario: Equatable, Sendable, Codable {
    var fields: [String: JSONValue]
    init(fields: [String: JSONValue]) { self.fields = fields }
    init(from decoder: Decoder) throws {
        let value = try JSONValue(from: decoder)
        let data = try JSONEncoder().encode(value)
        self = try Self.decode(data)
    }
    func encode(to encoder: Encoder) throws { try JSONValue.object(fields).encode(to: encoder) }
    var meta: [String: JSONValue] {
        get { fields["meta"]?.object ?? [:] }
        set { fields["meta"] = .object(newValue) }
    }
    var moments: [Moment] {
        get { (fields["sessions"]?.array ?? []).map { Moment(fields: $0.object) } }
        set { fields["sessions"] = .array(newValue.map { .object($0.fields) }) }
    }
    var name: String { meta["name"]?.string ?? "" }
    var duration: Double { moments.reduce(0) { $0 + $1.duration } }
    var activityCount: Int { moments.reduce(0) { $0 + $1.activities.count } }

    static func blank() -> Scenario {
        Scenario(fields: ["meta": .object(["name": .string("Sans titre"), "uiLanguage": .string("fr"),
            "dayHours": .number(7), "sliders": .array([])]), "sessions": .array([]),
            "allNotesExpanded": .bool(false), "intentionsCollapsed": .bool(false), "topPanelCollapsed": .bool(true)])
    }

    func encoded() throws -> Data {
        let encoder = JSONEncoder()
        encoder.outputFormatting = [.prettyPrinted, .sortedKeys, .withoutEscapingSlashes]
        return try encoder.encode(JSONValue.object(fields))
    }

    static func decode(_ data: Data, strict: Bool = false) throws -> Scenario {
        guard data.count <= 20_000_000 else { throw ScenarioError.invalid("Le document dépasse 20 Mo.") }
        let value = try JSONDecoder().decode(JSONValue.self, from: data)
        guard case .object(let root) = value, case .object = root["meta"], case .array(let sessions) = root["sessions"] else {
            throw ScenarioError.invalid("Un scénario doit contenir un objet meta et une liste sessions.")
        }
        var seen = Set<String>()
        var result = Scenario(fields: root)
        var moments: [Moment] = []
        func identified(_ object: [String: JSONValue]) throws -> [String: JSONValue] {
            var object = object
            let id = object["id"]?.string ?? ""
            if id.isEmpty || seen.contains(id) {
                if strict { throw ScenarioError.invalid("Chaque moment et activité doit avoir un identifiant unique.") }
                object["id"] = .string(UUID().uuidString)
            }
            seen.insert(object["id"]!.string)
            return object
        }
        for session in sessions {
            guard case .object(let object) = session, case .array(let activities) = object["activities"] else {
                throw ScenarioError.invalid("Chaque moment doit contenir une liste activities.")
            }
            var moment = Moment(fields: try identified(object))
            var parsed: [Activity] = []
            for item in activities {
                guard case .object(let object) = item else { throw ScenarioError.invalid("Activité invalide.") }
                let activity = Activity(fields: try identified(object))
                guard case .number(let duration) = object["duration"], duration.isFinite, duration > 0, duration <= 100_000 else {
                    throw ScenarioError.invalid("La durée d’une activité doit être comprise entre 0 et 100 000 minutes (0 exclu).")
                }
                if strict {
                    for (key, choices) in [("type", Pedagogy.types), ("groupMode", Pedagogy.groups),
                        ("teachingMode", Pedagogy.teaching), ("syncMode", Pedagogy.rhythms),
                        ("locationMode", Pedagogy.locations), ("evaluationMode", Pedagogy.evaluations)] {
                        guard choices.contains(where: { $0.id == activity[key] }) else {
                            throw ScenarioError.invalid("Valeur pédagogique invalide pour \(key).")
                        }
                    }
                    guard case .string = object["description"], case .string = object["instructions"],
                          case .array = object["tools"] else { throw ScenarioError.invalid("Description, consignes ou compétences manquantes.") }
                    let aias = object["aias"]?.object ?? [:]
                    let status = aias["status"]?.string ?? ""
                    guard ["undecided", "not_applicable", "specified"].contains(status),
                          status != "specified" || [1, 2, 3, 4, 5].contains(aias["level"]?.number ?? 0) else {
                        throw ScenarioError.invalid("Niveau AIAS invalide.")
                    }
                }
                parsed.append(activity)
            }
            moment.activities = parsed
            moments.append(moment)
        }
        result.moments = moments
        if strict && (result.name.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || moments.isEmpty || result.activityCount == 0) {
            throw ScenarioError.invalid("La proposition doit avoir un titre, un moment et une activité.")
        }
        return result
    }

    static func template() -> Scenario {
        var scenario = blank()
        scenario.meta["name"] = .string("Découvrir, pratiquer, réinvestir")
        scenario.meta["description"] = .string("Une structure à adapter à votre discipline et à vos élèves.")
        scenario.moments = [("Découvrir", "read", "Observer un exemple et formuler des hypothèses."),
            ("Pratiquer", "practice", "S’entraîner avec un retour formatif."),
            ("Réinvestir", "produce", "Produire et expliquer ses choix.")].map { title, type, instructions in
                var moment = Moment.blank(title: title)
                var activity = Activity.blank()
                activity["type"] = type
                activity["description"] = title
                activity["instructions"] = instructions
                activity["teachingMode"] = "guided"
                moment.activities = [activity]
                return moment
            }
        return scenario
    }
}

enum ScenarioError: LocalizedError {
    case invalid(String)
    var errorDescription: String? { switch self { case .invalid(let message): message } }
}
