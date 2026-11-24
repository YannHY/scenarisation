import Foundation
import Compression

enum ScenarioOfficeReader {
    static func xml(_ data: Data) throws -> XMLDocument {
        let text = try ScenarioImporter.text(data).uppercased()
        guard !text.contains("<!DOCTYPE"), !text.contains("<!ENTITY") else {
            throw ScenarioError.invalid("Les déclarations d’entités XML ne sont pas acceptées.")
        }
        do { return try XMLDocument(data: data, options: [.nodeLoadExternalEntitiesNever]) }
        catch { throw ScenarioError.invalid("Le document contient un fichier XML invalide.") }
    }
    static func children(_ node: XMLNode, _ name: String) -> [XMLNode] {
        (node.children ?? []).filter { $0.kind == .element && $0.localName == name }
    }
    static func descendants(_ node: XMLNode, _ name: String) -> [XMLNode] {
        (node.children ?? []).flatMap { child in
            (child.kind == .element && child.localName == name ? [child] : []) + descendants(child, name)
        }
    }
    static func attribute(_ node: XMLNode, _ name: String) -> String {
        (node as? XMLElement)?.attributes?.first { $0.localName == name }?.stringValue ?? ""
    }
    static func textRuns(_ node: XMLNode) -> String {
        descendants(node, "t").map { $0.stringValue ?? "" }.joined()
    }

    static func spreadsheet(_ data: Data) throws -> [[String]] {
        let files = try ImportZip.files(data)
        guard let workbookData = files["xl/workbook.xml"], let relationshipsData = files["xl/_rels/workbook.xml.rels"] else {
            throw ScenarioError.invalid("Ce fichier n’est pas un classeur Excel .xlsx reconnu.")
        }
        let workbook = try xml(workbookData); let relationships = try xml(relationshipsData)
        let shared: [String]
        if let data = files["xl/sharedStrings.xml"] {
            shared = descendants(try xml(data), "si").map(textRuns)
        } else { shared = [] }
        var materializedCells = 0
        var visitedRows = 0
        for sheet in descendants(workbook, "sheet") {
            let id = attribute(sheet, "id")
            guard let relation = descendants(relationships, "Relationship").first(where: { attribute($0, "Id") == id }),
                  attribute(relation, "Type").hasSuffix("/worksheet"), attribute(relation, "TargetMode") != "External" else { continue }
            let target = attribute(relation, "Target")
            let path = (target.hasPrefix("/") ? String(target.dropFirst()) : "xl/" + target) as NSString
            guard let sheetData = files[path.standardizingPath] else { continue }
            let document = try xml(sheetData)
            var rows: [[String]] = []
            for row in descendants(document, "sheetData").flatMap({ children($0, "row") }) {
                visitedRows += 1
                guard visitedRows <= 100_000 else { throw ScenarioError.invalid("Le classeur contient trop de lignes.") }
                var values: [String] = []
                for cell in children(row, "c") {
                    let reference = attribute(cell, "r")
                    var column = values.count
                    if !reference.isEmpty {
                        guard let letters = ImportValues.captures("^([A-Z]+)[1-9][0-9]*$", reference)?.first, letters.count <= 3 else {
                            throw ScenarioError.invalid("Référence de cellule Excel invalide.")
                        }
                        column = letters.uppercased().utf8.reduce(0) { $0 * 26 + Int($1) - 64 } - 1
                    }
                    guard column >= values.count, column < 4096 else { throw ScenarioError.invalid("Colonnes Excel invalides ou trop nombreuses.") }
                    materializedCells += column + 1 - values.count
                    guard materializedCells <= 1_000_000 else {
                        throw ScenarioError.invalid("Le classeur contient trop de cellules, y compris les cellules vides intermédiaires.")
                    }
                    while values.count <= column { values.append("") }
                    let type = attribute(cell, "t")
                    let raw = children(cell, "v").first?.stringValue ?? ""
                    if type == "s" {
                        guard let index = Int(raw), shared.indices.contains(index) else { throw ScenarioError.invalid("Texte partagé Excel introuvable.") }
                        values[column] = shared[index]
                    } else if type == "inlineStr" { values[column] = textRuns(cell) }
                    else if type == "e" { throw ScenarioError.invalid("Le classeur contient une cellule en erreur (\(reference)).") }
                    else { values[column] = raw }
                }
                if !values.allSatisfy({ ImportValues.clean($0).isEmpty }) { rows.append(values) }
            }
            // A workbook can have a cover sheet before the actual scenario sheet.
            if let header = rows.first {
                let normalized = Set(header.map(ImportValues.token))
                if (!normalized.isDisjoint(with: ["session_index", ImportValues.token("N° de séance")])) &&
                   (!normalized.isDisjoint(with: ["session_title", ImportValues.token("Titre de la séance")])) { return rows }
            }
        }
        throw ScenarioError.invalid("Aucune feuille ne contient les colonnes d’un scénario Scénarisation.")
    }

    static func html(_ source: String) throws -> String {
        // Read a DOM without executing scripts or loading any external resources.
        let source = source.replacingOccurrences(of: "(?i)<!doctype\\s+html\\s*>", with: "", options: .regularExpression)
        guard !source.uppercased().contains("<!DOCTYPE"), !source.uppercased().contains("<!ENTITY") else {
            throw ScenarioError.invalid("Ce document HTML contient des déclarations non prises en charge.")
        }
        let document = try XMLDocument(xmlString: source, options: [.documentTidyHTML, .nodeLoadExternalEntitiesNever])
        var lines: [String] = []; var inMoment = false; var inActivity = false
        func content(_ node: XMLNode) -> String {
            if node.kind == .text { return node.stringValue ?? "" }
            if node.name?.lowercased() == "br" { return "\n" }
            if node.name?.lowercased() == "a", !attribute(node, "href").isEmpty {
                return "[" + (node.stringValue ?? "") + "](" + attribute(node, "href") + ")"
            }
            if node.name?.lowercased() == "annotation", attribute(node, "encoding") == "application/x-tex" {
                return "$" + (node.stringValue ?? "") + "$"
            }
            if node.name?.lowercased() == "math", let annotation = descendants(node, "annotation").first {
                return "$" + (annotation.stringValue ?? "") + "$"
            }
            return (node.children ?? []).map(content).joined()
        }
        func walk(_ node: XMLNode) {
            let name = (node.name ?? "").lowercased()
            if ["head", "style", "script", "noscript"].contains(name) { return }
            if ["h1", "h2", "h3", "h4"].contains(name) {
                let value = ImportValues.clean(content(node))
                if name == "h1" { lines.append("# " + value) }
                else if name == "h2" {
                    lines.append("## " + value)
                    inMoment = !["parametres", "seances", "consignes pour les eleves", "description", "commande institutionnelle", "materiel enseignant", "objectifs", "acquis d'apprentissage"].contains(ImportValues.token(value))
                    inActivity = false
                } else {
                    lines.append("### " + value)
                    inActivity = ImportValues.captures("^(?:\\d+\\.\\d+|Activit[eé]\\s+\\d+)", value) != nil
                }
                lines.append(""); return
            }
            // Titres internes d’un champ (exports de l’app) : ils restent du Markdown.
            if name == "h5" || name == "h6" { lines.append("#### " + ImportValues.clean(content(node))); return }
            if name == "p" || name == "li" || (name == "div" && attribute(node, "class") == "instructions") {
                // Structural list items contain headings; visit those individually.
                if ["h2", "h3", "h4", "p", "div"].contains(where: { !descendants(node, $0).isEmpty }) {
                    for child in node.children ?? [] { walk(child) }; return
                }
                let value = ImportValues.clean(content(node))
                if let strong = (node.children ?? []).first(where: { $0.name == "strong" }),
                   let label = strong.stringValue {
                    if ImportValues.clean(label) == "1 jour =" {
                        lines.append("- 1 jour = " + ImportValues.clean(String(value.dropFirst(label.count))))
                    } else if label.trimmingCharacters(in: .whitespaces).hasSuffix(":") {
                        let field = label.trimmingCharacters(in: .whitespacesAndNewlines).dropLast()
                        let rest = ImportValues.clean(String(value.dropFirst(label.count)))
                        if inMoment && !inActivity && ["objectifs", "choix pedagogiques", "notes"].contains(ImportValues.token(String(field))) {
                            lines.append("> \(field):"); lines.append(contentsOf: rest.components(separatedBy: "\n").map { "> " + $0 })
                        } else { lines.append("- \(field): \(rest)") }
                    } else if inActivity && ["consignes pour les eleves", "notes"].contains(ImportValues.token(label)) {
                        lines.append("**" + label + "**")
                        let rest = ImportValues.clean(String(value.dropFirst(label.count)))
                        if !rest.isEmpty { lines.append(rest) }
                    } else { lines.append((name == "li" ? "- " : "") + value) }
                } else { lines.append((name == "li" ? "- " : "") + value) }
                return
            }
            for child in node.children ?? [] { walk(child) }
        }
        if let body = descendants(document, "body").first { walk(body) }
        else if let root = document.rootElement() { walk(root) }
        return lines.joined(separator: "\n")
    }

    static func word(_ data: Data) throws -> String {
        let files = try ImportZip.files(data)
        guard let data = files["word/document.xml"] else { throw ScenarioError.invalid("Ce fichier n’est pas un document Word .docx reconnu.") }
        let document = try xml(data)
        guard let body = descendants(document, "body").first else { throw ScenarioError.invalid("Le document Word est vide.") }
        var lines: [String] = []; var inMoment = false; var inActivity = false
        func paragraph(_ node: XMLNode) -> String {
            func content(_ node: XMLNode) -> String {
                if node.localName == "t" { return node.stringValue ?? "" }
                if ["br", "cr"].contains(node.localName ?? "") { return "\n" }
                if node.localName == "tab" { return "\t" }
                return (node.children ?? []).map(content).joined()
            }
            return content(node)
        }
        // Intitulés des fiches d’activité des exports de l’app (ligne d’intitulés, puis ligne de valeurs).
        let ficheLabels: Set<String> = ["", "duree", "groupe", "enseignement", "rythme", "modalite", "evaluation", "aias", "competences"]
        func cellText(_ cell: XMLNode) -> String {
            ImportValues.clean(descendants(cell, "p").map { p in (!descendants(p, "numPr").isEmpty ? "- " : "") + paragraph(p) }.joined(separator: "\n"))
        }
        func field(_ label: String, _ value: String) {
            guard !label.isEmpty else { return }
            if inMoment && !inActivity {
                lines.append("> \(label):"); lines.append(contentsOf: value.components(separatedBy: "\n").map { "> " + $0 })
            } else { lines.append("- \(label): \(value)") }
            lines.append("")
        }
        func handle(_ child: XMLNode) {
            if child.localName == "p" {
                let value = paragraph(child)
                let style = descendants(child, "pStyle").first.map { attribute($0, "val") } ?? ""
                if style == "FieldLabel" { lines.append("- \(ImportValues.clean(value)):"); return }
                let size = descendants(child, "sz").first.map { attribute($0, "val") } ?? ""
                let heading: Int?
                switch style {
                case "Title": heading = 1
                case "Heading1", "Heading2", "Heading3":
                    if ["parametres", "seances", "consignes pour les eleves"].contains(ImportValues.token(value)) { heading = 2 }
                    else if ImportValues.captures("^\\d+\\.\\s", value) != nil { heading = 2; inMoment = true; inActivity = false }
                    else { heading = 3 }
                default: heading = size == "32" ? 1 : size == "26" ? 2 : size == "23" ? 3 : nil
                }
                if let heading {
                    lines.append(String(repeating: "#", count: heading) + " " + value)
                    if heading == 3 && ImportValues.captures("^(?:\\d+\\.\\d+|Activit[eé]\\s+\\d+)", value) != nil { inActivity = true }
                } else {
                    let list = !descendants(child, "numPr").isEmpty
                    lines.append((list ? "- " : "") + value)
                }
            } else if child.localName == "tbl" {
                let rows = children(child, "tr").map { children($0, "tc") }
                // Encadré d’une seule cellule (consignes) : son contenu se lit comme le corps du document.
                if !rows.isEmpty, rows.allSatisfy({ $0.count == 1 }) {
                    for cell in rows.flatMap({ $0 }) {
                        for node in cell.children ?? [] where node.localName == "p" || node.localName == "tbl" { handle(node) }
                    }
                    return
                }
                // Fiche : une ligne d’intitulés connus, puis la ligne de leurs valeurs.
                if rows.count % 2 == 0, stride(from: 0, to: rows.count, by: 2).allSatisfy({ index in
                    rows[index].allSatisfy { ficheLabels.contains(ImportValues.token(cellText($0))) }
                }) {
                    for index in stride(from: 0, to: rows.count, by: 2) {
                        for (label, value) in zip(rows[index], rows[index + 1]) { field(cellText(label), cellText(value)) }
                    }
                    return
                }
                // Paires intitulé / valeur, sur deux ou quatre colonnes.
                for cells in rows where cells.count % 2 == 0 {
                    for index in stride(from: 0, to: cells.count, by: 2) { field(cellText(cells[index]), cellText(cells[index + 1])) }
                }
            }
        }
        for child in body.children ?? [] { handle(child) }
        return lines.joined(separator: "\n")
    }
}

/// Bounded ZIP reader for OOXML. Entries are read in memory, never extracted.
/// Supports the site's stored entries and Excel/Word's raw DEFLATE entries.
enum ImportZip {
    static func files(_ data: Data) throws -> [String: Data] {
        func invalid() -> ScenarioError { .invalid("Archive Office invalide, corrompue ou non prise en charge.") }
        func number(_ offset: Int, _ count: Int) throws -> Int {
            guard offset >= 0, offset <= data.count - count else { throw invalid() }
            return (0..<count).reduce(0) { $0 | (Int(data[offset + $1]) << (8 * $1)) }
        }
        guard data.count >= 22 else { throw invalid() }
        var end: Int?
        for offset in stride(from: data.count - 22, through: max(0, data.count - 65_557), by: -1) {
            if try number(offset, 4) == 0x06054b50, try offset + 22 + number(offset + 20, 2) == data.count { end = offset; break }
        }
        guard let end, try number(end + 4, 2) == 0, try number(end + 6, 2) == 0,
              try number(end + 8, 2) == number(end + 10, 2) else { throw invalid() }
        let count = try number(end + 10, 2); let centralSize = try number(end + 12, 4)
        var offset = try number(end + 16, 4)
        guard count > 0, count <= 4096, offset + centralSize == end else { throw invalid() }
        var files: [String: Data] = [:]; var totalSize = 0
        for _ in 0..<count {
            guard try number(offset, 4) == 0x02014b50 else { throw invalid() }
            let flags = try number(offset + 8, 2); let method = try number(offset + 10, 2)
            let checksum = try number(offset + 16, 4); let size = try number(offset + 20, 4)
            let expanded = try number(offset + 24, 4); let nameLength = try number(offset + 28, 2)
            let extra = try number(offset + 30, 2); let comment = try number(offset + 32, 2)
            let local = try number(offset + 42, 4)
            guard flags & 1 == 0, [0, 8].contains(method), expanded <= ScenarioImporter.limit,
                  offset + 46 + nameLength + extra + comment <= end else { throw invalid() }
            totalSize += expanded
            guard totalSize <= 80_000_000 else { throw ScenarioError.invalid("Le contenu décompressé du fichier Office dépasse 80 Mo.") }
            guard let name = String(data: data.subdata(in: (offset + 46)..<(offset + 46 + nameLength)), encoding: .utf8), files[name] == nil else { throw invalid() }
            guard try number(local, 4) == 0x04034b50, try number(local + 8, 2) == method else { throw invalid() }
            let start = try local + 30 + number(local + 26, 2) + number(local + 28, 2)
            guard start >= 0, start <= data.count, size <= data.count - start, start + size <= end - centralSize else { throw invalid() }
            let compressed = data.subdata(in: start..<(start + size))
            let contents: Data
            if method == 0 {
                guard size == expanded else { throw invalid() }
                contents = compressed
            } else if expanded == 0 { contents = Data() }
            else {
                guard size > 0 else { throw invalid() }
                var output = Data(count: expanded + 1)
                let capacity = output.count
                let decoded = output.withUnsafeMutableBytes { destination in
                    compressed.withUnsafeBytes { source in
                        compression_decode_buffer(destination.bindMemory(to: UInt8.self).baseAddress!, capacity,
                                                  source.bindMemory(to: UInt8.self).baseAddress!, size, nil, COMPRESSION_ZLIB)
                    }
                }
                guard decoded == expanded else { throw invalid() }
                output.count = expanded; contents = output
            }
            guard Int(StoredZip.crc32(contents)) == checksum else { throw invalid() }
            files[name] = contents
            offset += 46 + nameLength + extra + comment
        }
        guard offset == end else { throw invalid() }
        return files
    }
}
