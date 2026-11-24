import Foundation

// MARK: - Contenu commun

/// Contenu d’un export mis en page (HTML, Word) : libellés résolus, couleurs et sections dans l’ordre du document.
/// Les intitulés reprennent ceux que relisent les importeurs HTML et Word : un document retouché
/// (dont la copie exacte embarquée n’est plus valable) se réimporte encore avec sa structure.
struct ExportContent {
    struct Fact { let label: String; let value: String }
    struct Section { let title: String; let markdown: String }
    struct Share { let type: String; let label: String; let minutes: Double }
    struct Step {
        let number: String
        let type: String
        let typeLabel: String
        let minutes: Double
        /// Fiche de l’activité ; les intitulés sont ceux des champs reconnus à l’import.
        let facts: [Fact]
        let competencies: [String]
        let description: String
        let instructions: String
        let notes: String
    }
    struct Part {
        let number: Int
        let title: String
        let minutes: Double
        let objectives: String
        let intentions: String
        let notes: String
        let steps: [Step]
    }

    let title: String
    let students: Bool
    let facts: [Fact]
    let sections: [Section]
    let outcomes: [String]
    let parts: [Part]
    let shares: [Share]
    var minutes: Double { parts.reduce(0) { $0 + $1.minutes } }
    var activityCount: Int { parts.reduce(0) { $0 + $1.steps.count } }
    var instructionsLabel: String { students ? "Consignes" : "Consignes pour les élèves" }

    static let aias = ["undecided": "À définir", "not_applicable": "Non pertinent", "1": "1 · Sans IA", "2": "2 · Planification",
                       "3": "3 · Collaboration", "4": "4 · IA intégrale", "5": "5 · Exploration"]

    init(_ scenario: Scenario, students: Bool) {
        self.students = students
        title = scenario.name.isEmpty ? "Scénario sans titre" : scenario.name
        let meta = scenario.meta
        func text(_ key: String) -> String { ImportValues.clean(meta[key]?.string ?? "") }

        var facts: [Fact] = []
        if students {
            facts = [Fact(label: "Durée totale", value: Self.clock(scenario.duration)),
                     Fact(label: "Séances", value: String(scenario.moments.count))]
        } else {
            let modes = ["onsite": "Présentiel", "online": "Distanciel", "hybrid": "Hybride"]
            if let mode = modes[text("modeDelivery")] { facts.append(Fact(label: "Mode", value: mode)) }
            if case .success(let catalog) = SchoolCatalog.bundled {
                let system = text("schoolSystem"), level = text("schoolLevel")
                if !system.isEmpty {
                    facts.append(Fact(label: "Système scolaire", value: catalog.systems.first { $0.value == system }?.choice.label ?? system))
                }
                if !level.isEmpty {
                    facts.append(Fact(label: "Niveau", value: (catalog.levels[system] ?? []).first { $0.value == level }?.choice.label ?? level))
                }
            }
            for (key, label) in [("sizeClass", "Taille du groupe"), ("designers", "Concepteur(s)"), ("trainers", "Enseignant(s)")] where !text(key).isEmpty {
                facts.append(Fact(label: label, value: text(key)))
            }
            let days = meta["learningDays"]?.number ?? 0, hours = meta["learningHours"]?.number ?? 0, minutes = meta["learningMinutes"]?.number ?? 0
            let planned = [(days, "j"), (hours, "h"), (minutes, "min")].filter { $0.0 > 0 }.map { Self.number($0.0) + " " + $0.1 }
            if !planned.isEmpty { facts.append(Fact(label: "Durée prévue", value: planned.joined(separator: " "))) }
            facts.append(Fact(label: "Durée conçue", value: Self.clock(scenario.duration)))
            if days > 0 { facts.append(Fact(label: "Heures par jour", value: Self.number(meta["dayHours"]?.number ?? 7) + " h")) }
        }
        self.facts = facts

        sections = students ? [] : [("description", "Description"), ("command", "Commande institutionnelle"),
            ("teacherMaterials", "Matériel enseignant"), ("personas", "Objectifs")].compactMap { key, title in
                text(key).isEmpty ? nil : Section(title: title, markdown: text(key))
            }
        outcomes = students ? [] : (meta["sliders"]?.array ?? []).compactMap { item in
            let verb = ImportValues.clean(item.object["verb"]?.string ?? ""), body = ImportValues.clean(item.object["text"]?.string ?? "")
            if body.isEmpty { return verb.isEmpty ? nil : verb }
            return verb.isEmpty ? body : "**\(verb)** : \(body)"
        }

        let catalog = try? CompetencyCatalog.bundled.get()
        parts = scenario.moments.enumerated().map { index, moment in
            Part(number: index + 1, title: moment["title"].isEmpty ? "Sans titre" : moment["title"], minutes: moment.duration,
                 objectives: students ? "" : ImportValues.clean(moment["objectives"]),
                 intentions: students ? "" : ImportValues.clean(moment["intentions"]),
                 notes: students ? "" : ImportValues.clean(moment["notes"]),
                 steps: moment.activities.enumerated().map { position, activity in
                var facts = [Fact(label: "Durée", value: Self.number(activity.duration) + " min")]
                if !students {
                    for (key, label, choices) in [("groupMode", "Groupe", Pedagogy.groups), ("teachingMode", "Enseignement", Pedagogy.teaching),
                                                  ("syncMode", "Rythme", Pedagogy.rhythms), ("locationMode", "Modalité", Pedagogy.locations),
                                                  ("evaluationMode", "Évaluation", Pedagogy.evaluations)] where !activity[key].isEmpty {
                        facts.append(Fact(label: label, value: Pedagogy.label(activity[key], in: choices)))
                    }
                    facts.append(Fact(label: "AIAS", value: Self.aias[activity.aiasSelection] ?? activity.aiasSelection))
                }
                let competencies = students ? [] : (activity.fields["tools"]?.array ?? []).map(\.string).map { id in
                    catalog?.competencies.first { $0.id == id }.map { "\($0.code) · \($0.labelFr)" } ?? id
                }
                return Step(number: "\(index + 1).\(position + 1)", type: activity["type"],
                            typeLabel: Pedagogy.label(activity["type"], in: Pedagogy.types), minutes: activity.duration,
                            facts: facts, competencies: competencies,
                            description: students ? "" : ImportValues.clean(activity["description"]),
                            instructions: ImportValues.clean(activity["instructions"]),
                            notes: students ? "" : ImportValues.clean(activity["notes"]))
            })
        }
        let activities = scenario.moments.flatMap(\.activities)
        shares = Pedagogy.types.compactMap { choice in
            let minutes = activities.filter { ($0["type"].isEmpty ? "undefined" : $0["type"]) == choice.id }.reduce(0) { $0 + $1.duration }
            return minutes > 0 ? Share(type: choice.id, label: choice.label, minutes: minutes) : nil
        }
    }

    /// Nombre à la française, sans décimales inutiles (12 ; 12,5).
    static func number(_ value: Double) -> String {
        if value == value.rounded() { return String(Int(value)) }
        var text = String(format: "%.2f", value)
        while text.hasSuffix("0") { text.removeLast() }
        return text.replacingOccurrences(of: ".", with: ",")
    }
    /// Durée lisible : 45 min, 2 h, 1 h 05.
    static func clock(_ minutes: Double) -> String {
        if minutes < 60 { return number(minutes) + " min" }
        let hours = Int(minutes / 60), rest = minutes - Double(hours) * 60
        if rest < 0.01 { return "\(hours) h" }
        return "\(hours) h " + (rest < 10 ? "0" : "") + number(rest)
    }
    static func plural(_ count: Int, _ word: String) -> String { "\(count) \(word)\(count > 1 ? "s" : "")" }
    func partSummary(_ part: Part) -> String {
        "Durée : \(Self.clock(part.minutes)) · " + (part.steps.isEmpty ? "aucune activité" : Self.plural(part.steps.count, "activité"))
    }
    var summary: [String] {
        [Self.clock(minutes), Self.plural(parts.count, "séance"), Self.plural(activityCount, "activité")]
    }

    // Couleurs (hexadécimal RVB).
    static func color(_ type: String) -> UInt32 { StudioStyle.hex(type.isEmpty ? "undefined" : type) }
    /// Mélange avec le blanc (`amount` = part de blanc) ou, si négatif, avec le noir.
    static func mix(_ color: UInt32, _ amount: Double) -> UInt32 {
        let target: Double = amount >= 0 ? 255 : 0, weight = abs(amount)
        func channel(_ shift: UInt32) -> UInt32 {
            let value = Double((color >> shift) & 255)
            return UInt32((value + (target - value) * weight).rounded()) << shift
        }
        return channel(16) | channel(8) | channel(0)
    }
    static func hex(_ color: UInt32) -> String { String(format: "%06X", color) }
}

// MARK: - Copie exacte embarquée

/// Les exports HTML et Word embarquent le scénario JSON, avec l’empreinte du contenu visible :
/// tant que le document n’a pas été modifié, la réimportation est exacte (Markdown, champs, identifiants).
/// Après une retouche, l’empreinte ne correspond plus et l’import relit le contenu affiché.
enum ExportPayload {
    static let format = "scenarisation-export"
    static let htmlMarker = "<script type=\"application/json\" id=\"scenarisation-data\">"
    static let wordPath = "scenarisation/scenario.json"

    static func encode(_ scenario: Scenario, checksum: UInt32) throws -> String {
        let json = String(decoding: try scenario.encoded(), as: UTF8.self)
        return "{\"format\":\"\(format)\",\"version\":1,\"checksum\":\(checksum),\"scenario\":\(json)}"
    }
    static func decode(_ text: String, checksum: UInt32) -> Scenario? {
        guard let root = try? JSONDecoder().decode(JSONValue.self, from: Data(text.utf8)),
              root.object["format"]?.string == format, root.object["checksum"]?.number == Double(checksum),
              let scenario = root.object["scenario"], let data = try? JSONEncoder().encode(scenario) else { return nil }
        return try? Scenario.decode(data)
    }
    /// Partie du document HTML couverte par l’empreinte.
    static func htmlBody(_ source: String) -> Substring? {
        guard let start = source.range(of: "<body"), let end = source.range(of: "</body>", options: .backwards),
              start.lowerBound < end.lowerBound else { return nil }
        return source[start.lowerBound..<end.upperBound]
    }
    static func html(_ source: String) -> Scenario? {
        guard let open = source.range(of: htmlMarker), let close = source.range(of: "</script>", range: open.upperBound..<source.endIndex),
              let body = htmlBody(source) else { return nil }
        return decode(String(source[open.upperBound..<close.lowerBound]), checksum: StoredZip.crc32(Data(body.utf8)))
    }
    static func word(_ data: Data) -> Scenario? {
        guard let files = try? ImportZip.files(data), let payload = files[wordPath], let document = files["word/document.xml"] else { return nil }
        return decode(String(decoding: payload, as: UTF8.self), checksum: StoredZip.crc32(document))
    }
}

// MARK: - HTML

enum ExportHTML {
    private static func e(_ text: String) -> String { RichText.escape(text) }

    /// Markdown d’un champ. Les titres internes deviennent h5/h6, sous la structure du scénario (h1 à h4).
    static func markdown(_ text: String) -> String {
        RichText.blocks(text).map { block -> String in
            switch block {
            case .paragraph(let lines): return "<p>" + lines.map(RichText.inline).joined(separator: "<br>") + "</p>"
            case .heading(let level, let text):
                let tag = level <= 2 ? "h5" : "h6"
                return "<\(tag)>\(RichText.inline(text))</\(tag)>"
            case .list(let ordered, let items):
                let tag = ordered ? "ol" : "ul"
                return "<\(tag)>" + items.map { "<li>\(RichText.inline($0))</li>" }.joined() + "</\(tag)>"
            case .quote(let text): return "<blockquote><p>\(RichText.inline(text))</p></blockquote>"
            case .math(let formula, closed: true): return "<p class=\"math-block\">$$\n\(e(formula))\n$$</p>"
            case .math(let formula, closed: false): return "<p>$$<br>" + e(formula).replacingOccurrences(of: "\n", with: "<br>") + "</p>"
            }
        }.joined(separator: "\n")
    }

    /// Intitulé de champ ; les deux-points, masqués à l’écran, guident l’import d’un document retouché.
    private static func label(_ text: String) -> String {
        "<p class=\"label\"><strong>\(e(text))<span class=\"vh\"> :</span></strong></p>"
    }
    private static func facts(_ facts: [ExportContent.Fact], className: String) -> String {
        guard !facts.isEmpty else { return "" }
        // Liste d’intitulés et de valeurs ; les deux-points masqués guident l’import d’un document retouché.
        return "<ul class=\"\(className)\">" + facts.map {
            "<li\($0.label == "Compétences" ? " class=\"wide\"" : "")><strong>\(e($0.label))<span class=\"vh\"> :</span></strong> <span>\(e($0.value))</span></li>"
        }.joined() + "</ul>"
    }
    private static func percent(_ part: Double, _ total: Double) -> String {
        total > 0 ? "\(Int((part / total * 100).rounded())) %" : ""
    }
    private static func typeStyle(_ type: String) -> String {
        let color = ExportContent.color(type)
        return "--c:#\(ExportContent.hex(color));--tint:#\(ExportContent.hex(ExportContent.mix(color, 0.84)));--deep:#\(ExportContent.hex(ExportContent.mix(color, -0.55)))"
    }

    static func body(_ content: ExportContent, date: Date) -> String {
        var html: [String] = []
        // En-tête : titre, chiffres clés, fiche, répartition des types d’apprentissage.
        html.append("<header class=\"cover\"><div class=\"eyebrow\">\(content.students ? "Consignes pour les élèves" : "Scénario pédagogique")</div>")
        html.append("<h1>\(e(content.title))</h1>")
        html.append("<div class=\"summary\">" + content.summary.map { "<span>\(e($0))</span>" }.joined() + "</div>")
        if !content.students { html.append(facts(content.facts, className: "facts")) }
        let steps = content.parts.flatMap(\.steps)
        if content.minutes > 0 {
            html.append("<div class=\"timeline\" role=\"img\" aria-label=\"Déroulé des activités, proportionnel à leur durée\">" + steps.map { step in
                "<span style=\"flex:\(step.minutes) 1 0;\(typeStyle(step.type))\" title=\"\(e(step.number + " · " + step.typeLabel + " · " + ExportContent.clock(step.minutes)))\"></span>"
            }.joined() + "</div>")
            html.append("<div class=\"legend\">" + content.shares.map { share in
                "<span style=\"\(typeStyle(share.type))\"><i></i>\(e(share.label)) <b>\(ExportContent.clock(share.minutes))</b> · \(percent(share.minutes, content.minutes))</span>"
            }.joined() + "</div>")
        }
        if !content.parts.isEmpty {
            html.append("<table class=\"overview\"><thead><tr><th>Séance</th><th>Activités</th><th class=\"time\">Durée</th></tr></thead><tbody>" + content.parts.map { part in
                "<tr><td><span class=\"num\">\(part.number).</span> \(e(part.title))</td><td><span class=\"dots\">" +
                    part.steps.map { "<i style=\"\(typeStyle($0.type))\" title=\"\(e($0.typeLabel))\"></i>" }.joined() +
                    "</span></td><td class=\"time\">\(ExportContent.clock(part.minutes))</td></tr>"
            }.joined() + "</tbody></table>")
        }
        html.append("</header>")

        for section in content.sections {
            html.append("<section class=\"intro\"><h2>\(e(section.title))</h2><div class=\"md\">\(markdown(section.markdown))</div></section>")
        }
        if !content.outcomes.isEmpty {
            html.append("<section class=\"intro\"><h2>Acquis d’apprentissage</h2><ul class=\"outcomes\">" +
                content.outcomes.map { "<li>\(RichText.inline($0))</li>" }.joined() + "</ul></section>")
        }

        for part in content.parts {
            html.append("<section class=\"moment\"><h2><span class=\"num\">\(part.number).</span> \(e(part.title))</h2>")
            html.append("<p class=\"moment-meta\">\(e(content.partSummary(part)))</p>")
            let panels = [("Objectifs", part.objectives), ("Intentions pédagogiques", part.intentions)].filter { !$0.1.isEmpty }
            if !panels.isEmpty {
                html.append("<div class=\"panels\">" + panels.map { "<div class=\"panel\"><h3>\(e($0.0))</h3><div class=\"md\">\(markdown($0.1))</div></div>" }.joined() + "</div>")
            }
            for step in part.steps {
                html.append("<article class=\"activity\" style=\"\(typeStyle(step.type))\">")
                html.append("<h3><span class=\"badge\">\(step.number)</span> <span class=\"kind\">\(e(step.typeLabel))</span></h3>")
                var fiche = step.facts
                if !step.competencies.isEmpty { fiche.append(.init(label: "Compétences", value: step.competencies.joined(separator: " ; "))) }
                html.append(facts(fiche, className: content.students ? "fiche compact" : "fiche"))
                if !step.description.isEmpty { html.append(label("Description") + "<div class=\"md\">\(markdown(step.description))</div>") }
                if !step.instructions.isEmpty {
                    html.append("<div class=\"consignes\">" + label(content.instructionsLabel) + "<div class=\"md\">\(markdown(step.instructions))</div></div>")
                }
                if !step.notes.isEmpty { html.append(label("Notes") + "<div class=\"md muted\">\(markdown(step.notes))</div>") }
                html.append("</article>")
            }
            if !part.notes.isEmpty {
                html.append("<div class=\"panel teacher-notes\"><h3>Notes de l’enseignant</h3><div class=\"md\">\(markdown(part.notes))</div></div>")
            }
            html.append("</section>")
        }
        html.append("<footer><span>Exporté depuis Scénarisation le \(date.formatted(.dateTime.day().month(.wide).year().locale(Locale(identifier: "fr_FR"))))</span></footer>")
        return html.joined(separator: "\n")
    }

    static let style = """
    :root{color-scheme:light;--ink:#1f2933;--muted:#667085;--line:#e4e7ec;--soft:#f5f6f8;--accent:#2f6fd6}
    *{box-sizing:border-box}
    html{-webkit-text-size-adjust:100%}
    body{margin:0;background:#eceef2;color:var(--ink);font:15.5px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;-webkit-font-smoothing:antialiased}
    main{max-width:920px;margin:40px auto;background:#fff;border-radius:20px;box-shadow:0 1px 2px rgba(16,24,40,.05),0 18px 48px rgba(16,24,40,.08);padding:56px 64px 40px}
    .eyebrow{font-size:12px;font-weight:650;letter-spacing:.09em;text-transform:uppercase;color:var(--muted)}
    h1{font-size:36px;line-height:1.12;letter-spacing:-.015em;margin:.3em 0 .35em}
    .summary{display:flex;flex-wrap:wrap;gap:6px 10px;margin-bottom:24px}
    .summary span{background:var(--soft);border-radius:999px;padding:3px 12px;font-size:14px;font-weight:600}
    ul.facts,ul.fiche{list-style:none;margin:0}
    .facts strong,.fiche strong{display:block;font-size:11px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:var(--muted)}
    .facts li>span,.fiche li>span{display:block;margin-top:2px;font-weight:500}
    ul.facts{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px 26px;padding:18px 22px;background:var(--soft);border-radius:14px;margin-bottom:26px}
    .timeline{display:flex;gap:2px;height:16px;border-radius:8px;overflow:hidden;margin-bottom:12px}
    .timeline span{background:var(--c);min-width:4px}
    .legend{display:flex;flex-wrap:wrap;gap:6px 20px;font-size:13.5px;color:var(--muted);margin-bottom:26px}
    .legend i{display:inline-block;width:11px;height:11px;border-radius:3px;background:var(--c);margin-right:7px;vertical-align:-1px}
    .legend b{color:var(--ink);font-weight:600}
    table.overview{width:100%;border-collapse:collapse;font-size:14.5px}
    .overview th{text-align:left;font-size:11px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);padding:0 10px 8px 0;border-bottom:1px solid var(--line)}
    .overview td{padding:9px 10px 9px 0;border-bottom:1px solid var(--line);vertical-align:middle}
    .overview .time{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;padding-right:0}
    .dots{display:inline-flex;flex-wrap:wrap;gap:4px}.dots i{width:12px;height:12px;border-radius:4px;background:var(--c)}
    .num{color:var(--muted);font-variant-numeric:tabular-nums}
    section{margin-top:46px}
    h2{font-size:25px;line-height:1.25;letter-spacing:-.01em;margin:0 0 4px}
    section.intro h2{font-size:19px;padding-bottom:8px;border-bottom:1px solid var(--line);margin-bottom:12px}
    .outcomes{padding-left:1.3em;margin:0}.outcomes li{margin:.2em 0}
    section.moment{padding-top:26px;border-top:3px solid var(--ink)}
    .moment-meta{color:var(--muted);margin:0 0 18px}
    .panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:18px}
    .panel{background:var(--soft);border-radius:14px;padding:14px 18px}
    .panel h3{font-size:11.5px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin:0 0 6px}
    .teacher-notes{margin-top:18px;background:#fff8e6}
    article.activity{border:1px solid var(--line);border-left:6px solid var(--c);border-radius:14px;padding:18px 22px 16px;margin:16px 0}
    article.activity h3{display:flex;align-items:center;gap:10px;font-size:18px;line-height:1.3;margin:0 0 14px}
    .badge{display:inline-block;min-width:44px;text-align:center;padding:2px 10px;border-radius:999px;background:var(--tint);color:var(--deep);font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
    ul.fiche{display:grid;grid-template-columns:repeat(auto-fill,minmax(122px,1fr));gap:10px 18px;padding:12px 16px;background:var(--soft);border-radius:12px;margin-bottom:6px}
    ul.fiche .wide{grid-column:1/-1}
    article.activity{position:relative}
    ul.compact{position:absolute;top:18px;right:22px;display:block;padding:2px 12px;border-radius:999px;font-size:13.5px;font-variant-numeric:tabular-nums}
    ul.compact strong{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}ul.compact li>span{margin:0;font-weight:600}
    .label{margin:16px 0 4px;font-size:11.5px;letter-spacing:.07em;text-transform:uppercase;color:var(--muted)}
    .label strong{font-weight:650}
    .consignes{background:var(--tint);border-radius:12px;padding:2px 18px 12px;margin-top:14px}
    .consignes .label{color:var(--deep);margin-top:12px}
    .md p{margin:0 0 .6em}.md>:last-child{margin-bottom:0}
    .md ul,.md ol{margin:0 0 .6em;padding-left:1.4em}.md li{margin:.15em 0}
    .md blockquote{margin:0 0 .6em;padding:.15em 1em;border-left:3px solid rgba(16,24,40,.18);color:#475467}
    .md blockquote p{margin:0}.md blockquote+blockquote{margin-top:-.6em}
    .md h5,.md h6{font-size:15.5px;margin:.9em 0 .3em}.md h6{font-size:14.5px;color:#475467}
    .md code{font:.88em ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:rgba(16,24,40,.06);padding:.1em .35em;border-radius:5px}
    .md.muted{color:#475467}
    .math-block{overflow-x:auto}
    a{color:var(--accent);text-decoration-thickness:1px;text-underline-offset:2px}
    .vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    footer{margin-top:52px;padding-top:14px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
    @media (max-width:720px){main{margin:0;border-radius:0;padding:28px 18px}h1{font-size:28px}article.activity{padding:16px}}
    @media print{
      @page{margin:15mm 14mm}
      *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
      body{background:#fff;font-size:10.5pt}
      main{max-width:none;margin:0;padding:0;box-shadow:none;border-radius:0}
      h2,h3,.label,.panel h3{break-after:avoid}
      article.activity,.panel,tr{break-inside:avoid}
      section.moment{break-before:page;border-top:none;padding-top:0}
    }
    """

    static func document(_ scenario: Scenario, students: Bool, math: Bool, date: Date = Date()) throws -> String {
        let content = ExportContent(scenario, students: students)
        let body = "<body><main>\n\(body(content, date: date))\n</main>\(math ? "<script>renderMath(document.body)</script>" : "")</body>"
        // `<` est échappé pour que le JSON ne puisse pas refermer la balise script.
        let payload = try ExportPayload.encode(scenario, checksum: StoredZip.crc32(Data(body.utf8)))
            .replacingOccurrences(of: "<", with: "\\u003c")
        return """
        <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="generator" content="Scénarisation">
        <title>\(e(content.title))</title>
        \(math ? KaTeX.head : "")
        <style>\(style)</style>
        \(ExportPayload.htmlMarker)\(payload)</script>
        </head>\(body)</html>
        """
    }
}

// MARK: - Word

/// Document Word (OOXML) mis en page : styles nommés (titres navigables, table des matières possible),
/// listes Word réelles, liens actifs, couleurs des types d’apprentissage et pied de page numéroté.
final class ExportWord {
    private let content: ExportContent
    private var links: [String] = []
    private var orderedLists = 0
    /// Largeur utile d’une page A4 avec des marges de 2 cm, en vingtièmes de point.
    private static let width = 9638

    private init(_ content: ExportContent) { self.content = content }

    private static func x(_ text: String) -> String { ScenarioExporter.xml(text) }

    // Paragraphes et segments.
    struct RunStyle {
        var bold = false, italic = false, caps = false
        var color: String?, size: Int?, font: String?, style: String?
        var xml: String {
            var props = ""
            if let style { props += "<w:rStyle w:val=\"\(style)\"/>" }
            if let font { props += "<w:rFonts w:ascii=\"\(font)\" w:hAnsi=\"\(font)\" w:cs=\"\(font)\"/>" }
            if bold { props += "<w:b/>" }
            if italic { props += "<w:i/>" }
            if caps { props += "<w:caps/>" }
            if let color { props += "<w:color w:val=\"\(color)\"/>" }
            if let size { props += "<w:sz w:val=\"\(size)\"/><w:szCs w:val=\"\(size)\"/>" }
            return props.isEmpty ? "" : "<w:rPr>\(props)</w:rPr>"
        }
    }
    private static func run(_ text: String, _ style: RunStyle = RunStyle()) -> String {
        "<w:r>\(style.xml)<w:t xml:space=\"preserve\">\(x(text))</w:t></w:r>"
    }
    /// Propriétés de paragraphe, dans l’ordre imposé par le schéma OOXML.
    private static func paragraph(_ runs: String, style: String? = nil, keepNext: Bool = false, numbering: Int? = nil,
                                  border: String? = nil, fill: String? = nil, spacing: String? = nil, indent: String? = nil,
                                  align: String? = nil) -> String {
        var props = ""
        if let style { props += "<w:pStyle w:val=\"\(style)\"/>" }
        if keepNext { props += "<w:keepNext/>" }
        if let numbering { props += "<w:numPr><w:ilvl w:val=\"0\"/><w:numId w:val=\"\(numbering)\"/></w:numPr>" }
        if let border { props += "<w:pBdr>\(border)</w:pBdr>" }
        if let fill { props += "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"\(fill)\"/>" }
        if let spacing { props += "<w:spacing \(spacing)/>" }
        if let indent { props += "<w:ind \(indent)/>" }
        if let align { props += "<w:jc w:val=\"\(align)\"/>" }
        return "<w:p>" + (props.isEmpty ? "" : "<w:pPr>\(props)</w:pPr>") + runs + "</w:p>"
    }
    private static func spacer(_ points: Int = 4) -> String {
        paragraph("", spacing: "w:before=\"0\" w:after=\"0\" w:line=\"\(points * 20)\" w:lineRule=\"exact\"")
    }

    /// Ligne Markdown → segments Word (gras, italique, code, liens, formules).
    private func inline(_ text: String, _ base: RunStyle = RunStyle()) -> String {
        RichText.spans(text).map { span in
            var style = base
            if span.bold { style.bold = true }
            if span.italic || span.math { style.italic = true }
            if span.code { style.font = "Menlo"; style.color = "B42318" }
            if span.math { style.font = "Cambria Math" }
            if let link = span.link {
                links.append(link)
                style.style = "Hyperlink"
                return "<w:hyperlink r:id=\"rIdLink\(links.count)\" w:history=\"1\">\(Self.run(span.text, style))</w:hyperlink>"
            }
            return Self.run(span.text, style)
        }.joined()
    }

    /// Champ Markdown → paragraphes Word.
    private func markdown(_ text: String, _ base: RunStyle = RunStyle()) -> String {
        RichText.blocks(text).map { block -> String in
            switch block {
            case .paragraph(let lines):
                return Self.paragraph(lines.map { inline($0, base) }.joined(separator: "<w:r><w:br/></w:r>"))
            case .heading(_, let text):
                return Self.paragraph(inline(text, base), style: "Heading4")
            case .list(let ordered, let items):
                var id = 1
                if ordered { orderedLists += 1; id = 1 + orderedLists }
                return items.map { Self.paragraph(inline($0, base), style: "ListParagraph", numbering: id) }.joined()
            case .quote(let text):
                return Self.paragraph(inline(text, base), style: "Quote")
            case .math(let formula, _):
                var style = base; style.font = "Cambria Math"; style.italic = true
                return Self.paragraph(formula.components(separatedBy: "\n").map { Self.run($0, style) }.joined(separator: "<w:r><w:br/></w:r>"), align: "center")
            }
        }.joined()
    }

    // Tableaux.
    private struct Cell {
        var xml: String
        var width: Int
        var fill: String?
        var span = 1
        var borders: String?
    }
    private static func table(_ rows: [[Cell]], columns: [Int], borders: String = "", margins: (Int, Int) = (80, 120),
                              cantSplit: Bool = true, height: Int? = nil) -> String {
        let grid = columns.map { "<w:gridCol w:w=\"\($0)\"/>" }.joined()
        let body = rows.map { cells in
            "<w:tr><w:trPr>\(cantSplit ? "<w:cantSplit/>" : "")\(height.map { "<w:trHeight w:val=\"\($0)\" w:hRule=\"exact\"/>" } ?? "")</w:trPr>" + cells.map { cell in
                "<w:tc><w:tcPr><w:tcW w:w=\"\(cell.width)\" w:type=\"dxa\"/>" + (cell.span > 1 ? "<w:gridSpan w:val=\"\(cell.span)\"/>" : "") +
                    (cell.borders.map { "<w:tcBorders>\($0)</w:tcBorders>" } ?? "") +
                    (cell.fill.map { "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"\($0)\"/>" } ?? "") +
                    "<w:vAlign w:val=\"top\"/></w:tcPr>\(cell.xml)</w:tc>"
            }.joined() + "</w:tr>"
        }.joined()
        return "<w:tbl><w:tblPr><w:tblW w:w=\"\(columns.reduce(0, +))\" w:type=\"dxa\"/><w:tblBorders>\(borders)</w:tblBorders><w:tblLayout w:type=\"fixed\"/>" +
            "<w:tblCellMar><w:top w:w=\"\(margins.0)\" w:type=\"dxa\"/><w:left w:w=\"\(margins.1)\" w:type=\"dxa\"/><w:bottom w:w=\"\(margins.0)\" w:type=\"dxa\"/><w:right w:w=\"\(margins.1)\" w:type=\"dxa\"/></w:tblCellMar>" +
            "<w:tblLook w:val=\"0000\"/></w:tblPr><w:tblGrid>\(grid)</w:tblGrid>\(body)</w:tbl>"
    }
    private static func line(_ side: String, _ color: String, size: Int = 4) -> String {
        "<w:\(side) w:val=\"single\" w:sz=\"\(size)\" w:space=\"0\" w:color=\"\(color)\"/>"
    }
    private static let none = ["top", "left", "bottom", "right", "insideH", "insideV"].map { "<w:\($0) w:val=\"nil\"/>" }.joined()

    /// Fiche en grille : intitulés en petites capitales au-dessus des valeurs, quatre colonnes par ligne.
    private static func fiche(_ facts: [ExportContent.Fact], fill: String) -> String {
        let columns = 4, width = Self.width / columns
        let caption = RunStyle(bold: true, caps: true, color: "667085", size: 15)
        var rows: [[Cell]] = []
        // Les compétences occupent toute une ligne, sous les modalités.
        let regular = facts.filter { $0.label != "Compétences" }
        let groups = stride(from: 0, to: regular.count, by: columns).map { Array(regular[$0..<min($0 + columns, regular.count)]) }
            + facts.filter { $0.label == "Compétences" }.map { [$0] }
        for slice in groups {
            let wide = slice[0].label == "Compétences"
            func cells(_ text: (ExportContent.Fact) -> String) -> [Cell] {
                if wide { return [Cell(xml: text(slice[0]), width: width * columns, fill: fill, span: columns)] }
                return (0..<columns).map { index in
                    Cell(xml: index < slice.count ? text(slice[index]) : paragraph(""), width: width, fill: fill)
                }
            }
            rows.append(cells { paragraph(run($0.label, caption), spacing: "w:before=\"0\" w:after=\"0\"") })
            rows.append(cells { fact in
                fact.label == "Compétences"
                    ? fact.value.components(separatedBy: " ; ").map { paragraph(run($0, RunStyle(size: 19)), spacing: "w:before=\"0\" w:after=\"20\"") }.joined()
                    : paragraph(run(fact.value, RunStyle(bold: true, size: 20)), spacing: "w:before=\"0\" w:after=\"40\"")
            })
        }
        return table(rows, columns: Array(repeating: width, count: columns), borders: none, margins: (50, 140))
    }

    private func build(date: Date) -> String {
        var xml: [String] = []
        let c = content
        xml.append(Self.paragraph(Self.run(c.students ? "Consignes pour les élèves" : "Scénario pédagogique"), style: "Eyebrow"))
        xml.append(Self.paragraph(Self.run(c.title), style: "Title"))
        xml.append(Self.paragraph(Self.run(c.summary.joined(separator: "  ·  ")), style: "Subtitle"))

        if !c.students && !c.facts.isEmpty {
            // Fiche d’identité : paires intitulé / valeur sur deux colonnes.
            let half = Self.width / 2, labelWidth = 1900
            var rows: [[Cell]] = []
            for start in stride(from: 0, to: c.facts.count, by: 2) {
                rows.append((start..<min(start + 2, c.facts.count)).flatMap { index -> [Cell] in
                    let fact = c.facts[index]
                    return [Cell(xml: Self.paragraph(Self.run(fact.label, RunStyle(color: "667085", size: 18)), spacing: "w:before=\"0\" w:after=\"0\""), width: labelWidth, fill: "F5F6F8"),
                            Cell(xml: Self.paragraph(Self.run(fact.value, RunStyle(bold: true, size: 20)), spacing: "w:before=\"0\" w:after=\"0\""), width: half - labelWidth, fill: "F5F6F8")]
                } + (start + 1 < c.facts.count ? [] : [Cell(xml: Self.paragraph(""), width: labelWidth, fill: "F5F6F8"), Cell(xml: Self.paragraph(""), width: half - labelWidth, fill: "F5F6F8")]))
            }
            xml.append(Self.table(rows, columns: [labelWidth, half - labelWidth, labelWidth, half - labelWidth],
                                  borders: Self.line("insideH", "FFFFFF", size: 12), margins: (70, 120)))
            xml.append(Self.spacer(10))
        }

        let steps = c.parts.flatMap(\.steps)
        if c.minutes > 0 && !steps.isEmpty && steps.count <= 80 {
            // Frise : une cellule colorée par activité, de largeur proportionnelle à sa durée.
            let minimum = 70, available = Self.width - minimum * steps.count
            var widths = steps.map { minimum + Int(Double(available) * $0.minutes / c.minutes) }
            widths[widths.count - 1] += Self.width - widths.reduce(0, +)
            let cells = zip(steps, widths).map { step, width in
                Cell(xml: Self.paragraph("", spacing: "w:before=\"0\" w:after=\"0\" w:line=\"20\" w:lineRule=\"exact\""), width: width,
                     fill: ExportContent.hex(ExportContent.color(step.type)),
                     borders: Self.line("right", "FFFFFF", size: 12))
            }
            xml.append(Self.paragraph(Self.run("Déroulé", RunStyle(bold: true, caps: true, color: "667085", size: 16)), keepNext: true, spacing: "w:before=\"200\" w:after=\"80\""))
            xml.append(Self.table([cells], columns: widths, borders: Self.none, margins: (0, 0), height: 220))
            // Légende des types d’apprentissage.
            let legend = c.shares.map { share in
                Self.run("■ ", RunStyle(color: ExportContent.hex(ExportContent.color(share.type)), size: 22)) +
                    Self.run(share.label + " ", RunStyle(color: "475467", size: 18)) +
                    Self.run(ExportContent.clock(share.minutes), RunStyle(bold: true, size: 18)) +
                    Self.run("    ", RunStyle(size: 18))
            }.joined()
            xml.append(Self.paragraph(legend, spacing: "w:before=\"100\" w:after=\"160\""))
        }
        if !c.parts.isEmpty {
            // Vue d’ensemble des séances (trois colonnes : ignorée par l’import).
            let columns = [5600, 2638, 1400]
            let header = RunStyle(bold: true, caps: true, color: "667085", size: 15)
            let bottom = Self.line("bottom", "E4E7EC")
            var rows = [[Cell(xml: Self.paragraph(Self.run("Séance", header), spacing: "w:before=\"0\" w:after=\"0\""), width: columns[0], borders: bottom),
                         Cell(xml: Self.paragraph(Self.run("Activités", header), spacing: "w:before=\"0\" w:after=\"0\""), width: columns[1], borders: bottom),
                         Cell(xml: Self.paragraph(Self.run("Durée", header), spacing: "w:before=\"0\" w:after=\"0\"", align: "right"), width: columns[2], borders: bottom)]]
            for part in c.parts {
                let dots = part.steps.map { Self.run("■", RunStyle(color: ExportContent.hex(ExportContent.color($0.type)), size: 22)) }.joined(separator: Self.run(" ", RunStyle(size: 22)))
                rows.append([Cell(xml: Self.paragraph(Self.run("\(part.number). ", RunStyle(color: "667085")) + Self.run(part.title, RunStyle(bold: true)), spacing: "w:before=\"0\" w:after=\"0\""), width: columns[0], borders: bottom),
                             Cell(xml: Self.paragraph(dots.isEmpty ? Self.run("—", RunStyle(color: "98A2B3")) : dots, spacing: "w:before=\"0\" w:after=\"0\""), width: columns[1], borders: bottom),
                             Cell(xml: Self.paragraph(Self.run(ExportContent.clock(part.minutes)), spacing: "w:before=\"0\" w:after=\"0\"", align: "right"), width: columns[2], borders: bottom)])
            }
            xml.append(Self.table(rows, columns: columns, borders: Self.none, margins: (70, 60)))
            xml.append(Self.spacer())
        }

        for section in c.sections {
            xml.append(Self.paragraph(Self.run(section.title), style: "Heading1"))
            xml.append(markdown(section.markdown))
        }
        if !c.outcomes.isEmpty {
            xml.append(Self.paragraph(Self.run("Acquis d’apprentissage"), style: "Heading1"))
            xml.append(c.outcomes.map { Self.paragraph(inline($0), style: "ListParagraph", numbering: 1) }.joined())
        }

        for part in c.parts {
            xml.append(Self.paragraph(Self.run("\(part.number). ", RunStyle(color: "98A2B3")) + Self.run(part.title), style: "Heading1"))
            xml.append(Self.paragraph(Self.run(c.partSummary(part)), style: "Meta"))
            for (title, text) in [("Objectifs", part.objectives), ("Intentions pédagogiques", part.intentions)] where !text.isEmpty {
                xml.append(Self.paragraph(Self.run(title), style: "Heading3"))
                xml.append(markdown(text))
            }
            for step in part.steps {
                let color = ExportContent.color(step.type)
                let tint = ExportContent.hex(ExportContent.mix(color, 0.84)), deep = ExportContent.hex(ExportContent.mix(color, -0.55))
                let soft = ExportContent.hex(ExportContent.mix(color, 0.93))
                xml.append(Self.paragraph(Self.run(step.number + " ", RunStyle(color: deep)) + Self.run(step.typeLabel), style: "Heading2",
                                          border: "<w:left w:val=\"single\" w:sz=\"36\" w:space=\"8\" w:color=\"\(ExportContent.hex(color))\"/>",
                                          fill: tint))
                var facts = step.facts
                if !step.competencies.isEmpty { facts.append(.init(label: "Compétences", value: step.competencies.joined(separator: " ; "))) }
                xml.append(Self.fiche(facts, fill: soft))
                if !step.description.isEmpty {
                    xml.append(Self.paragraph(Self.run("Description"), style: "FieldLabel"))
                    xml.append(markdown(step.description))
                }
                if !step.instructions.isEmpty {
                    // Encadré des consignes : tableau d’une cellule, teinté de la couleur du type.
                    let inner = Self.paragraph(Self.run(c.instructionsLabel, RunStyle(color: deep)), style: "FieldLabel") + markdown(step.instructions)
                    xml.append(Self.spacer(6))
                    xml.append(Self.table([[Cell(xml: inner, width: Self.width, fill: tint)]], columns: [Self.width],
                                          borders: Self.line("left", ExportContent.hex(color), size: 24), margins: (60, 200), cantSplit: false))
                }
                if !step.notes.isEmpty {
                    xml.append(Self.paragraph(Self.run("Notes"), style: "FieldLabel"))
                    xml.append(markdown(step.notes, RunStyle(color: "475467")))
                }
                xml.append(Self.spacer(8))
            }
            if !part.notes.isEmpty {
                xml.append(Self.paragraph(Self.run("Notes de l’enseignant"), style: "Heading3"))
                xml.append(markdown(part.notes))
            }
        }
        xml.append(Self.paragraph(Self.run("Exporté depuis Scénarisation le \(date.formatted(.dateTime.day().month(.wide).year().locale(Locale(identifier: "fr_FR"))))"), style: "Meta"))
        return xml.joined()
    }

    static func document(_ scenario: Scenario, students: Bool, date: Date = Date()) throws -> Data {
        let builder = ExportWord(ExportContent(scenario, students: students))
        let body = builder.build(date: date)
        let w = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
        let r = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
        let document = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:document xmlns:w=\"\(w)\" xmlns:r=\"\(r)\"><w:body>\(body)" +
            "<w:sectPr><w:footerReference w:type=\"default\" r:id=\"rIdFooter\"/><w:pgSz w:w=\"11906\" w:h=\"16838\"/>" +
            "<w:pgMar w:top=\"1134\" w:right=\"1134\" w:bottom=\"1134\" w:left=\"1134\" w:header=\"567\" w:footer=\"567\" w:gutter=\"0\"/></w:sectPr></w:body></w:document>"

        let nums = (0...builder.orderedLists).map { index in
            index == 0 ? "<w:num w:numId=\"1\"><w:abstractNumId w:val=\"0\"/></w:num>"
                : "<w:num w:numId=\"\(index + 1)\"><w:abstractNumId w:val=\"1\"/><w:lvlOverride w:ilvl=\"0\"><w:startOverride w:val=\"1\"/></w:lvlOverride></w:num>"
        }.joined()
        let numbering = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:numbering xmlns:w=\"\(w)\">" +
            "<w:abstractNum w:abstractNumId=\"0\"><w:multiLevelType w:val=\"singleLevel\"/><w:lvl w:ilvl=\"0\"><w:start w:val=\"1\"/><w:numFmt w:val=\"bullet\"/><w:lvlText w:val=\"•\"/><w:lvlJc w:val=\"left\"/><w:pPr><w:ind w:left=\"357\" w:hanging=\"247\"/></w:pPr><w:rPr><w:color w:val=\"667085\"/></w:rPr></w:lvl></w:abstractNum>" +
            "<w:abstractNum w:abstractNumId=\"1\"><w:multiLevelType w:val=\"singleLevel\"/><w:lvl w:ilvl=\"0\"><w:start w:val=\"1\"/><w:numFmt w:val=\"decimal\"/><w:lvlText w:val=\"%1.\"/><w:lvlJc w:val=\"left\"/><w:pPr><w:ind w:left=\"357\" w:hanging=\"300\"/></w:pPr></w:lvl></w:abstractNum>" +
            nums + "</w:numbering>"

        let footer = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:ftr xmlns:w=\"\(w)\"><w:p><w:pPr><w:pStyle w:val=\"Meta\"/><w:tabs><w:tab w:val=\"right\" w:pos=\"\(width)\"/></w:tabs><w:spacing w:before=\"0\" w:after=\"0\"/></w:pPr>" +
            run(builder.content.title) + "<w:r><w:tab/></w:r><w:fldSimple w:instr=\" PAGE \"><w:r><w:t>1</w:t></w:r></w:fldSimple>" +
            run(" / ") + "<w:fldSimple w:instr=\" NUMPAGES \"><w:r><w:t>1</w:t></w:r></w:fldSimple></w:p></w:ftr>"

        let relationships = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">" +
            "<Relationship Id=\"rIdStyles\" Type=\"\(r)/styles\" Target=\"styles.xml\"/>" +
            "<Relationship Id=\"rIdNumbering\" Type=\"\(r)/numbering\" Target=\"numbering.xml\"/>" +
            "<Relationship Id=\"rIdSettings\" Type=\"\(r)/settings\" Target=\"settings.xml\"/>" +
            "<Relationship Id=\"rIdFooter\" Type=\"\(r)/footer\" Target=\"footer1.xml\"/>" +
            builder.links.enumerated().map { "<Relationship Id=\"rIdLink\($0.offset + 1)\" Type=\"\(r)/hyperlink\" Target=\"\(x($0.element))\" TargetMode=\"External\"/>" }.joined() +
            "</Relationships>"

        let settings = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:settings xmlns:w=\"\(w)\"><w:defaultTabStop w:val=\"708\"/><w:hyphenationZone w:val=\"425\"/><w:characterSpacingControl w:val=\"doNotCompress\"/>" +
            "<w:compat><w:compatSetting w:name=\"compatibilityMode\" w:uri=\"http://schemas.microsoft.com/office/word\" w:val=\"15\"/></w:compat></w:settings>"

        let core = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><cp:coreProperties xmlns:cp=\"http://schemas.openxmlformats.org/package/2006/metadata/core-properties\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:dcterms=\"http://purl.org/dc/terms/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">" +
            "<dc:title>\(x(builder.content.title))</dc:title><dc:creator>Scénarisation</dc:creator><dc:language>fr-FR</dc:language>" +
            "<dcterms:created xsi:type=\"dcterms:W3CDTF\">\(ISO8601DateFormatter().string(from: date))</dcterms:created></cp:coreProperties>"

        let main = "application/vnd.openxmlformats-officedocument.wordprocessingml"
        let types = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\">" +
            "<Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/><Default Extension=\"json\" ContentType=\"application/json\"/>" +
            "<Override PartName=\"/word/document.xml\" ContentType=\"\(main).document.main+xml\"/>" +
            "<Override PartName=\"/word/styles.xml\" ContentType=\"\(main).styles+xml\"/>" +
            "<Override PartName=\"/word/numbering.xml\" ContentType=\"\(main).numbering+xml\"/>" +
            "<Override PartName=\"/word/settings.xml\" ContentType=\"\(main).settings+xml\"/>" +
            "<Override PartName=\"/word/footer1.xml\" ContentType=\"\(main).footer+xml\"/>" +
            "<Override PartName=\"/docProps/core.xml\" ContentType=\"application/vnd.openxmlformats-package.core-properties+xml\"/></Types>"
        let package = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">" +
            "<Relationship Id=\"rId1\" Type=\"\(r)/officeDocument\" Target=\"word/document.xml\"/>" +
            "<Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties\" Target=\"docProps/core.xml\"/></Relationships>"

        return StoredZip.archive([
            ("[Content_Types].xml", types), ("_rels/.rels", package),
            ("word/document.xml", document), ("word/_rels/document.xml.rels", relationships),
            ("word/styles.xml", styles(w)), ("word/numbering.xml", numbering), ("word/settings.xml", settings),
            ("word/footer1.xml", footer), ("docProps/core.xml", core),
            // Copie exacte, liée à l’empreinte de document.xml ; Word l’ignore et l’abandonne s’il réenregistre le fichier.
            (ExportPayload.wordPath, try ExportPayload.encode(scenario, checksum: StoredZip.crc32(Data(document.utf8))))])
    }

    private static func styles(_ w: String) -> String {
        func paragraphStyle(_ id: String, _ name: String, next: String? = "Normal", pPr: String = "", rPr: String = "", outline: Int? = nil) -> String {
            "<w:style w:type=\"paragraph\" w:styleId=\"\(id)\"><w:name w:val=\"\(name)\"/><w:basedOn w:val=\"Normal\"/>" +
                (next.map { "<w:next w:val=\"\($0)\"/>" } ?? "") + "<w:qFormat/>" +
                "<w:pPr>\(pPr)\(outline.map { "<w:outlineLvl w:val=\"\($0)\"/>" } ?? "")</w:pPr><w:rPr>\(rPr)</w:rPr></w:style>"
        }
        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:styles xmlns:w=\"\(w)\">" +
            "<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii=\"Arial\" w:hAnsi=\"Arial\" w:eastAsia=\"Arial\" w:cs=\"Arial\"/><w:color w:val=\"1F2933\"/><w:sz w:val=\"21\"/><w:szCs w:val=\"21\"/><w:lang w:val=\"fr-FR\" w:eastAsia=\"en-US\" w:bidi=\"ar-SA\"/></w:rPr></w:rPrDefault>" +
            "<w:pPrDefault><w:pPr><w:spacing w:after=\"100\" w:line=\"284\" w:lineRule=\"auto\"/></w:pPr></w:pPrDefault></w:docDefaults>" +
            "<w:style w:type=\"paragraph\" w:default=\"1\" w:styleId=\"Normal\"><w:name w:val=\"Normal\"/><w:qFormat/><w:rPr><w:rFonts w:ascii=\"Arial\" w:hAnsi=\"Arial\" w:eastAsia=\"Arial\" w:cs=\"Arial\"/></w:rPr></w:style>" +
            paragraphStyle("Eyebrow", "Surtitre", pPr: "<w:spacing w:before=\"0\" w:after=\"40\"/>", rPr: "<w:b/><w:caps/><w:color w:val=\"667085\"/><w:spacing w:val=\"20\"/><w:sz w:val=\"17\"/><w:szCs w:val=\"17\"/>") +
            paragraphStyle("Title", "Title", next: "Subtitle", pPr: "<w:spacing w:before=\"0\" w:after=\"80\" w:line=\"240\" w:lineRule=\"auto\"/>", rPr: "<w:b/><w:color w:val=\"101828\"/><w:sz w:val=\"56\"/><w:szCs w:val=\"56\"/>") +
            paragraphStyle("Subtitle", "Subtitle", pPr: "<w:spacing w:before=\"0\" w:after=\"240\"/>", rPr: "<w:color w:val=\"475467\"/><w:sz w:val=\"23\"/><w:szCs w:val=\"23\"/>") +
            paragraphStyle("Heading1", "heading 1", pPr: "<w:keepNext/><w:keepLines/><w:pBdr><w:bottom w:val=\"single\" w:sz=\"12\" w:space=\"4\" w:color=\"1F2933\"/></w:pBdr><w:spacing w:before=\"480\" w:after=\"80\"/>", rPr: "<w:b/><w:color w:val=\"101828\"/><w:sz w:val=\"34\"/><w:szCs w:val=\"34\"/>", outline: 0) +
            paragraphStyle("Heading2", "heading 2", pPr: "<w:keepNext/><w:keepLines/><w:spacing w:before=\"280\" w:after=\"100\"/><w:ind w:left=\"120\" w:right=\"120\"/>", rPr: "<w:b/><w:color w:val=\"101828\"/><w:sz w:val=\"25\"/><w:szCs w:val=\"25\"/>", outline: 1) +
            paragraphStyle("Heading3", "heading 3", pPr: "<w:keepNext/><w:keepLines/><w:spacing w:before=\"220\" w:after=\"60\"/>", rPr: "<w:b/><w:caps/><w:color w:val=\"667085\"/><w:spacing w:val=\"10\"/><w:sz w:val=\"18\"/><w:szCs w:val=\"18\"/>", outline: 2) +
            paragraphStyle("Heading4", "heading 4", pPr: "<w:keepNext/><w:spacing w:before=\"160\" w:after=\"40\"/>", rPr: "<w:b/><w:sz w:val=\"22\"/><w:szCs w:val=\"22\"/>", outline: 3) +
            paragraphStyle("FieldLabel", "Intitulé de champ", pPr: "<w:keepNext/><w:spacing w:before=\"160\" w:after=\"40\"/>", rPr: "<w:b/><w:caps/><w:color w:val=\"667085\"/><w:spacing w:val=\"10\"/><w:sz w:val=\"16\"/><w:szCs w:val=\"16\"/>") +
            paragraphStyle("Meta", "Informations", pPr: "<w:spacing w:before=\"0\" w:after=\"160\"/>", rPr: "<w:color w:val=\"667085\"/><w:sz w:val=\"19\"/><w:szCs w:val=\"19\"/>") +
            paragraphStyle("Quote", "Quote", pPr: "<w:pBdr><w:left w:val=\"single\" w:sz=\"18\" w:space=\"8\" w:color=\"D0D5DD\"/></w:pBdr><w:ind w:left=\"200\"/>", rPr: "<w:i/><w:color w:val=\"475467\"/>") +
            paragraphStyle("ListParagraph", "List Paragraph", pPr: "<w:spacing w:after=\"40\"/><w:ind w:left=\"357\"/><w:contextualSpacing/>") +
            "<w:style w:type=\"character\" w:default=\"1\" w:styleId=\"DefaultParagraphFont\"><w:name w:val=\"Default Paragraph Font\"/><w:uiPriority w:val=\"1\"/><w:semiHidden/></w:style>" +
            "<w:style w:type=\"character\" w:styleId=\"Hyperlink\"><w:name w:val=\"Hyperlink\"/><w:basedOn w:val=\"DefaultParagraphFont\"/><w:rPr><w:color w:val=\"2F6FD6\"/><w:u w:val=\"single\"/></w:rPr></w:style>" +
            "<w:style w:type=\"table\" w:default=\"1\" w:styleId=\"TableNormal\"><w:name w:val=\"Normal Table\"/><w:semiHidden/><w:tblPr><w:tblInd w:w=\"0\" w:type=\"dxa\"/><w:tblCellMar><w:top w:w=\"0\" w:type=\"dxa\"/><w:left w:w=\"108\" w:type=\"dxa\"/><w:bottom w:w=\"0\" w:type=\"dxa\"/><w:right w:w=\"108\" w:type=\"dxa\"/></w:tblCellMar></w:tblPr></w:style>" +
            "</w:styles>"
    }
}
