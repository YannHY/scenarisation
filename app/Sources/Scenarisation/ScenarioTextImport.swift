import Foundation

/// Reads the site's teacher/student Markdown and the native app's Markdown.
/// HTML and Word readers translate structural elements into these same records.
enum ScenarioTextReader {
    private enum Target {
        case meta(String), moment(String), activity(String), outcomes, none
    }

    static func markdown(_ source: String) throws -> Scenario {
        let lines = source.components(separatedBy: "\n")
        guard let title = lines.first(where: { $0.hasPrefix("# ") }) else {
            throw ScenarioError.invalid("Le fichier ne contient pas de titre de scénario reconnu.")
        }
        var result = Scenario.blank()
        result.meta["name"] = .string(ImportValues.clean(String(title.dropFirst(2))))
        var moment: Moment?; var activity: Activity?
        var target = Target.none; var buffer: [String] = []
        var hasStructure = false
        let students = lines.contains { ImportValues.token($0) == "## consignes pour les eleves" }
        let metaSections = ["description": "description", "commande institutionnelle": "command", "materiel enseignant": "teacherMaterials", "objectifs": "personas"]

        func flush() {
            let value = ImportValues.clean(buffer.joined(separator: "\n"))
            switch target {
            case .meta(let key): result.meta[key] = .string(value)
            case .moment(let key): moment?[key] = value
            case .activity(let key): activity?[key] = value
            case .outcomes: result.meta["sliders"] = ImportValues.outcomes(value)
            case .none: break
            }
            buffer = []; target = .none
        }
        func finishActivity() {
            flush()
            if let activity { moment?.activities.append(activity) }
            activity = nil
        }
        func finishMoment() {
            finishActivity()
            if let moment { result.moments.append(moment) }
            moment = nil
        }
        func begin(_ next: Target, _ value: String = "") {
            flush(); target = next
            if !value.isEmpty { buffer = [value] }
        }
        func metaField(_ label: String, value: String) {
            switch label {
            case "mode", "mode du scenario": result.meta["modeDelivery"] = .string(ImportValues.choice(value, key: "locationMode"))
            case "systeme scolaire": result.meta["schoolSystem"] = .string(ImportValues.school(value))
            case "niveau": result.meta["schoolLevel"] = .string(ImportValues.school(value, system: result.meta["schoolSystem"]?.string ?? ""))
            case "taille du groupe": result.meta["sizeClass"] = .string(value)
            case "concepteur(s)": result.meta["designers"] = .string(value)
            case "enseignant(s)": result.meta["trainers"] = .string(value)
            case "duree prevue": ImportValues.learningTime(value, into: &result)
            case "1 jour", "heures par jour":
                if let match = ImportValues.captures("([0-9]+(?:[.,][0-9]+)?)", value), let hours = Double(match[0].replacingOccurrences(of: ",", with: ".")), hours > 0, hours <= 24 {
                    result.meta["dayHours"] = .number(hours)
                }
            default: break
            }
        }
        let activityFields = ["duree", "groupe", "enseignement", "rythme", "modalite", "evaluation", "aias", "description",
                              "consignes", "consignes pour les eleves", "instructions", "notes", "competences"]
        var fence: String?
        for line in lines {
            let trimmed = line.trimmingCharacters(in: .whitespaces)
            if trimmed.hasPrefix("```") || trimmed.hasPrefix("~~~") {
                let marker = String(trimmed.prefix(3))
                if fence == marker { fence = nil } else if fence == nil { fence = marker }
                buffer.append(line); continue
            }
            if fence != nil { buffer.append(line); continue }
            if line == title { continue }
            if let heading = ImportValues.captures("^##\\s+(.+)$", line) {
                let normalized = ImportValues.token(heading[0])
                if ["parametres", "seances", "consignes pour les eleves"].contains(normalized) {
                    finishMoment(); hasStructure = true; continue
                }
                if moment == nil, let key = metaSections[normalized] { begin(.meta(key)); continue }
                if moment == nil, normalized == "acquis d'apprentissage" { begin(.outcomes); continue }
                finishMoment(); hasStructure = true
                let name = heading[0].replacingOccurrences(of: "^\\d+\\.\\s*", with: "", options: .regularExpression)
                moment = Moment.blank(title: ImportValues.clean(name))
                continue
            }
            if let heading = ImportValues.captures("^###\\s+(.+)$", line) {
                let normalized = ImportValues.token(heading[0])
                if moment == nil {
                    if let key = metaSections[normalized] { begin(.meta(key)); continue }
                    if normalized == "acquis d'apprentissage" { begin(.outcomes); continue }
                }
                let web = ImportValues.captures("^\\d+\\.\\d+\\s+(.+)$", heading[0])
                let native = ImportValues.captures("^Activit[eé]\\s+\\d+(?:\\.\\d+)?(?:\\s*[—–-]\\s*(.+))?$", heading[0])
                if moment != nil && (web != nil || native != nil) {
                    finishActivity(); activity = Activity.blank()
                    if let web { activity?["type"] = ImportValues.choice(web[0], key: "type") }
                    if let duration = native?.first, !duration.isEmpty { activity?.duration = try ImportValues.duration(duration) }
                    target = students ? .activity("instructions") : .activity("description")
                    continue
                }
                if moment != nil, ["objectifs", "intentions pedagogiques", "notes de l'enseignant"].contains(normalized) {
                    finishActivity()
                    target = .moment(normalized == "objectifs" ? "objectives" : normalized == "intentions pedagogiques" ? "intentions" : "notes")
                    continue
                }
            }
            if let quote = ImportValues.captures("^>\\s*(Objectifs|Choix p[eé]dagogiques|Notes)\\s*:\\s*(.*)$", line), moment != nil {
                let key = ImportValues.token(quote[0])
                begin(.moment(key == "objectifs" ? "objectives" : key == "notes" ? "notes" : "intentions"), quote[1])
                continue
            }
            if line.hasPrefix(">"), case .moment = target {
                buffer.append(line.replacingOccurrences(of: "^> ?", with: "", options: .regularExpression)); continue
            }
            if let bold = ImportValues.captures("^\\*\\*(Consignes pour les [eé]l[eè]ves|Notes)\\*\\*\\s*$", line), activity != nil {
                begin(.activity(ImportValues.token(bold[0]) == "notes" ? "notes" : "instructions")); continue
            }
            if activity == nil, moment == nil, let hours = ImportValues.captures("^-\\s*1 jour\\s*=\\s*(.+)$", line) {
                flush(); metaField("1 jour", value: hours[0]); continue
            }
            if let field = ImportValues.captures("^-\\s*([^:]+):\\s*(.*)$", line) {
                let label = ImportValues.token(field[0]); let value = ImportValues.clean(field[1])
                if activity != nil, activityFields.contains(label) {
                    flush()
                    switch label {
                    case "duree": activity?.duration = try ImportValues.duration(value)
                    case "groupe", "enseignement", "rythme", "modalite", "evaluation":
                        let key = ["groupe": "groupMode", "enseignement": "teachingMode", "rythme": "syncMode", "modalite": "locationMode", "evaluation": "evaluationMode"][label]!
                        activity?[key] = ImportValues.choice(value, key: key)
                    case "aias": if var item = activity { ImportValues.aias(value, activity: &item); activity = item }
                    case "competences": activity?.fields["tools"] = ImportValues.tools(value)
                    default:
                        let key = ["consignes", "consignes pour les eleves", "instructions"].contains(label) ? "instructions" : label
                        target = .activity(key); buffer = [value]
                    }
                    continue
                }
                if moment == nil {
                    if let key = metaSections[label] { begin(.meta(key), value); continue }
                    if label == "acquis d'apprentissage" { begin(.outcomes, value); continue }
                    if ["mode", "mode du scenario", "systeme scolaire", "niveau", "taille du groupe", "concepteur(s)", "enseignant(s)",
                        "duree prevue", "duree concue", "1 jour", "heures par jour"].contains(label) {
                        flush(); metaField(label, value: value); continue
                    }
                }
            }
            // Native Markdown presents activity modalities on one compact line.
            if activity != nil, case .activity("description") = target,
               buffer.allSatisfy({ ImportValues.clean($0).isEmpty }), line.contains(" · ") {
                flush()
                let parts = line.components(separatedBy: " · ")
                if parts.count >= 5 {
                    for (value, key) in zip(parts, ["type", "groupMode", "teachingMode", "locationMode", "evaluationMode"]) {
                        activity?[key] = ImportValues.choice(value, key: key)
                    }
                    if parts.count > 5, var item = activity { ImportValues.aias(parts[5], activity: &item); activity = item }
                    target = .activity("description"); continue
                }
            }
            // The native moment duration is a computed summary, not a field.
            if activity == nil, moment != nil, ImportValues.token(line).hasPrefix("duree :") { continue }
            buffer.append(line)
        }
        finishMoment()
        guard hasStructure else { throw ScenarioError.invalid("Aucune structure de scénario reconnue. Importez un fichier Markdown exporté par Scénarisation.") }
        return result
    }
}
