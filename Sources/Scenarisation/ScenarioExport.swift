import SwiftUI
import UniformTypeIdentifiers

enum ScenarioFormat: String, CaseIterable, Identifiable {
    case json, markdown = "md", html, word = "docx", excel = "xlsx", csv
    var id: String { rawValue }
    var label: String {
        switch self { case .json: "JSON"; case .markdown: "Markdown"; case .html: "HTML"; case .word: "Word"; case .excel: "Excel"; case .csv: "CSV" }
    }
}

struct ScenarioExportView: View {
    let scenario: Scenario
    @Environment(\.dismiss) private var dismiss
    @State private var format = ScenarioFormat.json
    @State private var students = false
    @State private var selected: Set<String>
    @State private var error: String?
    init(scenario: Scenario) {
        self.scenario = scenario
        _selected = State(initialValue: Set(scenario.moments.map(\.id)))
    }
    var body: some View {
        VStack(spacing: 0) {
            Form {
                Section {
                    Picker("Contenu", selection: $students) {
                        Text("Enseignant").tag(false)
                        Text("Élève").tag(true)
                    }.pickerStyle(.segmented)
                    Picker("Format", selection: $format) { ForEach(ScenarioFormat.allCases) { Text($0.label).tag($0) } }
                } header: {
                    Text("Exporter le scénario")
                } footer: {
                    Text(students ? "Les moments, les activités et les consignes destinées aux élèves." : "Toutes les informations du scénario pédagogique.")
                }
                Section("Moments à exporter (\(selected.count)/\(scenario.moments.count))") {
                    ForEach(scenario.moments) { moment in
                        Toggle(moment["title"].isEmpty ? "Sans titre" : moment["title"], isOn: Binding(
                            get: { selected.contains(moment.id) }, set: { if $0 { selected.insert(moment.id) } else { selected.remove(moment.id) } }))
                    }
                }
                if let error { Section { Label(error, systemImage: "exclamationmark.triangle.fill").foregroundStyle(.red) } }
            }.formStyle(.grouped)
            HStack {
                Button("Fermer") { dismiss() }.keyboardShortcut(.cancelAction).studioButtons()
                Spacer()
                Button { export() } label: { ActionLabel(title: "Exporter…", icon: "export") }
                    .disabled(!scenario.moments.isEmpty && selected.isEmpty)
                    .keyboardShortcut(.defaultAction).studioButtons(prominent: true)
            }.padding([.horizontal, .bottom], 20).padding(.top, 4)
        }.frame(width: 540, height: 560)
    }
    private func export() {
        do {
            let data = try ScenarioExporter.data(scenario, format: format, students: students, moments: selected)
            let panel = NSSavePanel()
            panel.allowedContentTypes = [UTType(filenameExtension: format.rawValue) ?? .data]
            let filename = scenario.name.isEmpty ? "scenario" : scenario.name.replacingOccurrences(of: "/", with: "-")
            panel.nameFieldStringValue = filename + (students ? " — élèves" : "") + "." + format.rawValue
            guard panel.runModal() == .OK, let url = panel.url else { return }
            try data.write(to: url, options: .atomic)
            dismiss()
        } catch { self.error = error.localizedDescription }
    }
}

enum ScenarioExporter {
    static func scoped(_ source: Scenario, students: Bool, moments: Set<String>) -> Scenario {
        var result = source
        result.moments = source.moments.filter { moments.contains($0.id) }
        if students {
            var clean = Scenario.blank()
            clean.meta["name"] = .string(source.name)
            clean.moments = result.moments.map { moment in
                var copy = Moment.blank(title: moment["title"])
                copy["id"] = moment.id
                copy.activities = moment.activities.map { activity in
                    var item = Activity.blank(); item["id"] = activity.id
                    item.duration = activity.duration; item["type"] = activity["type"]
                    item["instructions"] = activity["instructions"]
                    return item
                }
                return copy
            }
            result = clean
        }
        return result
    }
    static func data(_ source: Scenario, format: ScenarioFormat, students: Bool, moments: Set<String>) throws -> Data {
        let scenario = scoped(source, students: students, moments: moments)
        switch format {
        case .json: return try scenario.encoded()
        case .markdown: return Data(markdown(scenario, students: students).utf8)
        case .html: return Data(html(scenario, students: students).utf8)
        case .csv:
            let text = rows(scenario, students: students).map { $0.map { "\"" + $0.replacingOccurrences(of: "\"", with: "\"\"") + "\"" }.joined(separator: ",") }.joined(separator: "\r\n")
            return Data(("\u{feff}" + text).utf8)
        case .word: return word(scenario, students: students)
        case .excel: return excel(scenario, students: students)
        }
    }
    static func markdown(_ scenario: Scenario, students: Bool) -> String {
        var parts = ["# " + scenario.name]
        if !students {
            for (key, title) in [("description", "Description"), ("command", "Commande institutionnelle"),
                ("teacherMaterials", "Matériel enseignant"), ("personas", "Objectifs")] {
                if let text = scenario.meta[key]?.string, !text.isEmpty { parts.append("## \(title)\n\n\(text)") }
            }
            let outcomes = scenario.meta["sliders"]?.array ?? []
            if !outcomes.isEmpty { parts.append("## Acquis d’apprentissage\n\n" + outcomes.map { "- " + ($0.object["text"]?.string ?? "") }.joined(separator: "\n")) }
        }
        for moment in scenario.moments {
            parts.append("## \(moment["title"])\n\nDurée : \(moment.duration.formatted()) min")
            if !students {
                if !moment["objectives"].isEmpty { parts.append("### Objectifs\n\n" + moment["objectives"]) }
                if !moment["intentions"].isEmpty { parts.append("### Intentions pédagogiques\n\n" + moment["intentions"]) }
            }
            for (index, activity) in moment.activities.enumerated() {
                parts.append("### Activité \(index + 1) — \(activity.duration.formatted()) min")
                if !students {
                    parts.append([Pedagogy.label(activity["type"], in: Pedagogy.types), Pedagogy.label(activity["groupMode"], in: Pedagogy.groups),
                        Pedagogy.label(activity["teachingMode"], in: Pedagogy.teaching), Pedagogy.label(activity["locationMode"], in: Pedagogy.locations),
                        Pedagogy.label(activity["evaluationMode"], in: Pedagogy.evaluations), "AIAS : " + activity.aiasSelection].joined(separator: " · "))
                    parts.append(activity["description"])
                }
                parts.append("**Consignes pour les élèves**\n\n" + activity["instructions"])
                if !students && !activity["notes"].isEmpty { parts.append("**Notes**\n\n" + activity["notes"]) }
            }
            if !students && !moment["notes"].isEmpty { parts.append("### Notes de l’enseignant\n\n" + moment["notes"]) }
        }
        return parts.joined(separator: "\n\n") + "\n"
    }
    static func xml(_ value: String) -> String {
        value.replacingOccurrences(of: "&", with: "&amp;").replacingOccurrences(of: "<", with: "&lt;")
            .replacingOccurrences(of: ">", with: "&gt;").replacingOccurrences(of: "\"", with: "&quot;")
            .unicodeScalars.filter { $0.value >= 32 || [9, 10, 13].contains($0.value) }.map(String.init).joined()
    }
    static func html(_ scenario: Scenario, students: Bool) -> String {
        // La structure (un paragraphe par bloc) reste celle que relit l’import HTML.
        let source = markdown(scenario, students: students)
        let body = source.components(separatedBy: "\n\n").map { block in
            for level in (1...3) {
                let prefix = String(repeating: "#", count: level) + " "
                if block.hasPrefix(prefix) {
                    let lines = block.components(separatedBy: "\n")
                    return "<h\(level)>\(xml(String(lines[0].dropFirst(prefix.count))))</h\(level)>" +
                        (lines.count > 1 ? "<p>\(xml(lines.dropFirst().joined(separator: "\n")))</p>" : "")
                }
            }
            return "<p>" + xml(block).replacingOccurrences(of: "\n", with: "<br>") + "</p>"
        }.joined(separator: "\n")
        // Les formules $…$ et $$…$$ restent dans le texte et sont composées à l’ouverture par KaTeX (MIT),
        // intégré seulement si le scénario en contient : le fichier reste autonome et hors ligne.
        let math = RichText.containsMath(source)
        return """
        <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>\(xml(scenario.name))</title>
        \(math ? KaTeX.head : "")
        <style>body{font:16px/1.6 system-ui,sans-serif;color:#222;max-width:950px;margin:48px auto;padding:0 28px}h1{font-size:32px}h2{margin-top:40px;border-top:1px solid #ddd;padding-top:24px}h3{margin-top:28px}p{white-space:pre-wrap}@media print{body{margin:0;max-width:none}h2,h3{break-after:avoid}}</style>
        </head><body><main>\(body)</main>\(math ? "<script>renderMath(document.body)</script>" : "")</body></html>
        """
    }

    static let headers = ["N° de séance", "Titre de la séance", "Objectifs de la séance", "Choix pédagogiques de la séance", "Notes de la séance",
        "N° d'activité", "Type d'apprentissage", "Durée (minutes)", "Organisation du groupe", "Enseignement", "Rythme", "Modalité", "Évaluation", "AIAS",
        "Description de l'activité", "Consignes pour les élèves", "Notes de l'activité", "Compétences", "Titre du scénario", "Mode du scénario", "Système scolaire",
        "Niveau", "Taille du groupe", "Concepteur(s)", "Enseignant(s)", "Durée prévue", "Durée conçue", "Heures par jour", "Description du scénario",
        "Commande institutionnelle", "Matériel enseignant", "Personas", "Objectifs / curseurs"]
    static func rows(_ scenario: Scenario, students: Bool) -> [[String]] {
        var result = [students ? ["Titre du scénario", "N° de séance", "Titre de la séance", "N° d’activité", "Consignes pour les élèves"] : headers]
        func meta(_ key: String) -> String {
            guard let value = scenario.meta[key] else { return "" }
            if case .number(let number) = value { return String(number) }
            return value.string
        }
        for (sessionIndex, moment) in scenario.moments.enumerated() {
            let activities = moment.activities.isEmpty ? [Activity.blank()] : moment.activities
            for (index, activity) in activities.enumerated() {
                if students { result.append([scenario.name, String(sessionIndex + 1), moment["title"], moment.activities.isEmpty ? "" : String(index + 1), activity["instructions"]]); continue }
                var activityRow = [String(sessionIndex + 1), moment["title"], moment["objectives"], moment["intentions"], moment["notes"], String(index + 1),
                    activity["type"], moment.activities.isEmpty ? "" : String(activity.duration), activity["groupMode"], activity["teachingMode"], activity["syncMode"],
                    activity["locationMode"], activity["evaluationMode"], activity.aiasSelection, activity["description"], activity["instructions"], activity["notes"],
                    (activity.fields["tools"]?.array ?? []).map(\.string).joined(separator: " | ")]
                if moment.activities.isEmpty { for column in 5..<18 { activityRow[column] = "" } }
                let planned = (scenario.meta["learningDays"]?.number ?? 0) * (scenario.meta["dayHours"]?.number ?? 7) * 60 +
                    (scenario.meta["learningHours"]?.number ?? 0) * 60 + (scenario.meta["learningMinutes"]?.number ?? 0)
                let metadata = [scenario.name, meta("modeDelivery"), meta("schoolSystem"), meta("schoolLevel"), meta("sizeClass"), meta("designers"), meta("trainers"),
                    "\(planned) min", "\(scenario.duration) min", meta("dayHours"), meta("description"), meta("command"), meta("teacherMaterials"), meta("personas"),
                    (scenario.meta["sliders"]?.array ?? []).map { $0.object["text"]?.string ?? "" }.joined(separator: " | ")]
                result.append(activityRow + metadata)
            }
        }
        return result
    }

    private static let packageRelationships = "http://schemas.openxmlformats.org/package/2006/relationships"
    private static let documentRelationships = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    static func word(_ scenario: Scenario, students: Bool) -> Data {
        let paragraphs = markdown(scenario, students: students).components(separatedBy: "\n").map { line in
            let heading = line.hasPrefix("# ") ? 32 : line.hasPrefix("## ") ? 26 : line.hasPrefix("### ") ? 23 : 21
            let content = line.replacingOccurrences(of: "^#{1,3} ", with: "", options: .regularExpression)
            return "<w:p><w:pPr><w:spacing w:after=\"160\"/></w:pPr><w:r><w:rPr><w:sz w:val=\"\(heading)\"/>\(heading > 21 ? "<w:b/>" : "")</w:rPr><w:t xml:space=\"preserve\">\(xml(content))</w:t></w:r></w:p>"
        }.joined()
        let document = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body>\(paragraphs)<w:sectPr><w:pgSz w:w=\"11906\" w:h=\"16838\"/><w:pgMar w:top=\"1134\" w:right=\"1134\" w:bottom=\"1134\" w:left=\"1134\"/></w:sectPr></w:body></w:document>"
        return StoredZip.archive([
            ("[Content_Types].xml", "<?xml version=\"1.0\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/></Types>"),
            ("_rels/.rels", "<?xml version=\"1.0\"?><Relationships xmlns=\"\(packageRelationships)\"><Relationship Id=\"rId1\" Type=\"\(documentRelationships)/officeDocument\" Target=\"word/document.xml\"/></Relationships>"),
            ("word/document.xml", document)])
    }
    static func excel(_ scenario: Scenario, students: Bool) -> Data {
        let sheetRows = rows(scenario, students: students).enumerated().map { rowIndex, values in
            let cells = values.enumerated().map { index, value in
                "<c r=\"\(column(index + 1))\(rowIndex + 1)\" t=\"inlineStr\"><is><t xml:space=\"preserve\">\(xml(value))</t></is></c>"
            }.joined()
            return "<row r=\"\(rowIndex + 1)\">\(cells)</row>"
        }.joined()
        return StoredZip.archive([
            ("[Content_Types].xml", "<?xml version=\"1.0\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/><Override PartName=\"/xl/workbook.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml\"/><Override PartName=\"/xl/worksheets/sheet1.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/></Types>"),
            ("_rels/.rels", "<?xml version=\"1.0\"?><Relationships xmlns=\"\(packageRelationships)\"><Relationship Id=\"rId1\" Type=\"\(documentRelationships)/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>"),
            ("xl/workbook.xml", "<?xml version=\"1.0\"?><workbook xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" xmlns:r=\"\(documentRelationships)\"><sheets><sheet name=\"Scénario\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>"),
            ("xl/_rels/workbook.xml.rels", "<?xml version=\"1.0\"?><Relationships xmlns=\"\(packageRelationships)\"><Relationship Id=\"rId1\" Type=\"\(documentRelationships)/worksheet\" Target=\"worksheets/sheet1.xml\"/></Relationships>"),
            ("xl/worksheets/sheet1.xml", "<?xml version=\"1.0\"?><worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"><sheetData>\(sheetRows)</sheetData></worksheet>")])
    }
    static func column(_ value: Int) -> String {
        var n = value; var result = ""
        while n > 0 { n -= 1; result = String(UnicodeScalar(65 + n % 26)!) + result; n /= 26 }
        return result
    }
}

// A small standards-compliant ZIP writer for OOXML exports; no shell process or dependency.
enum StoredZip {
    static func archive(_ files: [(String, String)]) -> Data {
        var archive = Data(); var central = Data()
        for (name, text) in files {
            let nameData = Data(name.utf8); let content = Data(text.utf8)
            let offset = UInt32(archive.count); let checksum = crc32(content)
            archive.le(UInt32(0x04034b50)); archive.le(UInt16(20)); archive.le(UInt16(0x0800)); archive.le(UInt16(0))
            archive.le(UInt16(0)); archive.le(UInt16(33)); archive.le(checksum)
            archive.le(UInt32(content.count)); archive.le(UInt32(content.count)); archive.le(UInt16(nameData.count)); archive.le(UInt16(0))
            archive.append(nameData); archive.append(content)
            central.le(UInt32(0x02014b50)); central.le(UInt16(20)); central.le(UInt16(20)); central.le(UInt16(0x0800)); central.le(UInt16(0))
            central.le(UInt16(0)); central.le(UInt16(33)); central.le(checksum); central.le(UInt32(content.count)); central.le(UInt32(content.count))
            central.le(UInt16(nameData.count)); central.le(UInt16(0)); central.le(UInt16(0)); central.le(UInt16(0)); central.le(UInt16(0)); central.le(UInt32(0)); central.le(offset)
            central.append(nameData)
        }
        let centralOffset = UInt32(archive.count)
        archive.append(central)
        archive.le(UInt32(0x06054b50)); archive.le(UInt16(0)); archive.le(UInt16(0))
        archive.le(UInt16(files.count)); archive.le(UInt16(files.count)); archive.le(UInt32(central.count)); archive.le(centralOffset); archive.le(UInt16(0))
        return archive
    }
    static func crc32(_ data: Data) -> UInt32 {
        var crc: UInt32 = 0xffffffff
        for byte in data {
            crc ^= UInt32(byte)
            for _ in 0..<8 { crc = (crc >> 1) ^ (crc & 1 == 1 ? 0xedb88320 : 0) }
        }
        return crc ^ 0xffffffff
    }
}
private extension Data {
    mutating func le<T: FixedWidthInteger>(_ value: T) {
        var value = value.littleEndian
        Swift.withUnsafeBytes(of: &value) { append(contentsOf: $0) }
    }
}
