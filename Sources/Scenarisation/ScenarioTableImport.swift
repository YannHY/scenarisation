import Foundation

enum ScenarioTableReader {
    static let keys = ["session_index", "session_title", "session_objectives", "session_intentions", "session_notes",
        "activity_index", "learning_type", "duration_minutes", "group_size", "teaching_mode", "pacing", "delivery_mode", "assessment", "aias",
        "activity_description", "activity_instructions", "activity_notes", "activity_competencies", "design_title", "design_mode", "design_school_system",
        "design_level", "design_group_size", "design_designers", "design_trainers", "design_learning_time", "design_designed_time", "design_day_hours",
        "design_description", "design_institutional_brief", "design_teacher_materials", "design_personas", "design_sliders"]

    static func csv(_ text: String) throws -> [[String]] {
        // Detect separators only outside quoted cells on the first logical row.
        var counts: [Character: Int] = [",": 0, ";": 0, "\t": 0]
        var quoted = false
        for char in text {
            if char == "\"" { quoted.toggle() }
            if !quoted && (char == "\n" || char == "\r") { break }
            if !quoted && counts[char] != nil { counts[char, default: 0] += 1 }
        }
        let separator: Character = [Character(","), ";", "\t"].max { counts[$0, default: 0] < counts[$1, default: 0] } ?? ","
        var rows: [[String]] = []; var row: [String] = []; var cell = ""
        var inQuotes = false; var closedQuote = false
        var iterator = text.makeIterator(); var current = iterator.next()
        while let char = current {
            current = iterator.next()
            if inQuotes {
                if char == "\"" {
                    if current == "\"" { cell.append("\""); current = iterator.next() }
                    else { inQuotes = false; closedQuote = true }
                } else { cell.append(char) }
            } else if char == "\"" {
                guard cell.isEmpty && !closedQuote else { throw ScenarioError.invalid("Guillemets invalides dans le CSV, ligne \(rows.count + 1).") }
                inQuotes = true
            } else if char == separator {
                row.append(cell); cell = ""; closedQuote = false
            } else if char == "\n" || char == "\r" {
                if char == "\r" && current == "\n" { current = iterator.next() }
                row.append(cell); rows.append(row); row = []; cell = ""; closedQuote = false
            } else {
                guard !closedQuote || char == " " else { throw ScenarioError.invalid("Caractère inattendu après les guillemets du CSV.") }
                if !closedQuote { cell.append(char) }
            }
        }
        guard !inQuotes else { throw ScenarioError.invalid("Le CSV contient un champ entre guillemets non terminé.") }
        if !cell.isEmpty || !row.isEmpty || closedQuote { row.append(cell); rows.append(row) }
        return rows
    }

    static func scenario(_ rows: [[String]]) throws -> Scenario {
        guard let header = rows.first else { throw ScenarioError.invalid("Le tableau est vide.") }
        var aliases: [String: String] = [:]
        for (key, label) in zip(keys, ScenarioExporter.headers) {
            aliases[ImportValues.token(key)] = key; aliases[ImportValues.token(label)] = key
        }
        for (label, key) in [("trainer_presence", "teaching_mode"), ("Présence de l’enseignant", "teaching_mode"),
                             ("Teacher presence", "teaching_mode"), ("activity_tools", "activity_competencies")] {
            aliases[ImportValues.token(label)] = key
        }
        var index: [String: Int] = [:]
        for (column, label) in header.enumerated() {
            guard let key = aliases[ImportValues.token(label)] else { continue }
            guard index[key] == nil else { throw ScenarioError.invalid("La colonne « \(label) » apparaît plusieurs fois.") }
            index[key] = column
        }
        guard index["session_index"] != nil, index["session_title"] != nil else {
            throw ScenarioError.invalid("Le tableau doit contenir les colonnes « N° de séance » et « Titre de la séance ».")
        }
        let dataRows = rows.dropFirst().filter { $0.contains { !ImportValues.clean($0).isEmpty } }
        guard let first = dataRows.first else { throw ScenarioError.invalid("Le tableau ne contient aucune séance.") }
        func read(_ row: [String], _ key: String) -> String {
            guard let column = index[key], column < row.count else { return "" }
            return row[column]
        }
        var result = Scenario.blank()
        for (column, field) in [("design_title", "name"), ("design_group_size", "sizeClass"), ("design_designers", "designers"),
            ("design_trainers", "trainers"), ("design_description", "description"), ("design_institutional_brief", "command"),
            ("design_teacher_materials", "teacherMaterials"), ("design_personas", "personas")] {
            if index[column] != nil { result.meta[field] = .string(read(first, column)) }
        }
        result.meta["modeDelivery"] = .string(ImportValues.choice(read(first, "design_mode"), key: "locationMode"))
        let system = ImportValues.school(read(first, "design_school_system"))
        result.meta["schoolSystem"] = .string(system)
        result.meta["schoolLevel"] = .string(ImportValues.school(read(first, "design_level"), system: system))
        if let hours = Double(read(first, "design_day_hours").replacingOccurrences(of: ",", with: ".")), hours > 0, hours <= 24 {
            result.meta["dayHours"] = .number(hours)
        }
        ImportValues.learningTime(read(first, "design_learning_time"), into: &result)
        result.meta["sliders"] = ImportValues.outcomes(read(first, "design_sliders"))
        var moments: [Moment] = []; var positions: [String: Int] = [:]
        var activityOrders: [String: Double] = [:]; var sessionOrders: [String: Double] = [:]
        for (rowNumber, row) in dataRows.enumerated() {
            guard row.count <= header.count else { throw ScenarioError.invalid("La ligne \(rowNumber + 2) contient plus de cellules que l’en-tête.") }
            let sessionKey = ImportValues.clean(read(row, "session_index"))
            guard !sessionKey.isEmpty else { throw ScenarioError.invalid("Le numéro de séance manque à la ligne \(rowNumber + 2).") }
            let position: Int
            if let found = positions[sessionKey] { position = found }
            else {
                position = moments.count; positions[sessionKey] = position
                let title = ImportValues.clean(read(row, "session_title"))
                var moment = Moment.blank(title: title.isEmpty ? "Séance \(sessionKey)" : title)
                for (column, field) in [("session_objectives", "objectives"), ("session_intentions", "intentions"), ("session_notes", "notes")] {
                    moment[field] = read(row, column)
                }
                sessionOrders[moment.id] = Double(sessionKey) ?? Double(position + 1)
                moments.append(moment)
            }
            let activityKeys = keys[5..<18]
            guard activityKeys.contains(where: { !ImportValues.clean(read(row, $0)).isEmpty }) else { continue }
            var activity = Activity.blank()
            activity.duration = try ImportValues.duration(read(row, "duration_minutes"))
            for (column, field) in [("learning_type", "type"), ("group_size", "groupMode"), ("teaching_mode", "teachingMode"),
                                    ("pacing", "syncMode"), ("delivery_mode", "locationMode"), ("assessment", "evaluationMode")] {
                activity[field] = ImportValues.choice(read(row, column), key: field)
            }
            for (column, field) in [("activity_description", "description"), ("activity_instructions", "instructions"), ("activity_notes", "notes")] {
                activity[field] = read(row, column)
            }
            ImportValues.aias(read(row, "aias"), activity: &activity)
            activity.fields["tools"] = ImportValues.tools(read(row, "activity_competencies"))
            let order = read(row, "activity_index").split(separator: ".").last.map(String.init) ?? ""
            activityOrders[activity.id] = Double(order) ?? Double(moments[position].activities.count + 1)
            moments[position].activities.append(activity)
        }
        for i in moments.indices {
            moments[i].activities.sort { activityOrders[$0.id, default: 0] < activityOrders[$1.id, default: 0] }
        }
        moments.sort { sessionOrders[$0.id, default: 0] < sessionOrders[$1.id, default: 0] }
        result.moments = moments
        return result
    }
}
