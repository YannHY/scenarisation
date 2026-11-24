import Foundation
import PDFKit
import UniformTypeIdentifiers
#if os(macOS)
import AppKit
#else
import UIKit
#endif

/// Document joint par l’enseignant à une demande de l’assistant. Seul son texte est transmis au modèle.
struct AssistantDocument: Identifiable, Codable, Equatable, Sendable {
    var id = UUID()
    let name: String
    let text: String
    /// Le texte a été raccourci pour tenir dans la demande.
    var truncated = false

    private enum CodingKeys: String, CodingKey { case name, text, truncated }
}

/// Extraction du texte des documents joints, sans exécuter de contenu ni charger de ressource externe.
enum AssistantDocumentReader {
    static let extensions = ["pdf", "docx", "pptx", "xlsx", "txt", "md", "markdown", "csv", "tsv", "json", "html", "htm", "rtf"]
    static var contentTypes: [UTType] {
        extensions.compactMap { UTType(filenameExtension: $0) } + [.plainText]
    }
    static let maximumFiles = 10
    static let fileLimit = 25_000_000
    /// Environ 15 000 mots par document et 50 000 pour l’ensemble : assez pour un programme ou un chapitre,
    /// sans dépasser la fenêtre des modèles en ligne courants.
    static let characterLimit = 100_000
    static let totalLimit = 300_000

    static func read(_ url: URL) throws -> AssistantDocument {
        let name = url.lastPathComponent
        let size = try url.resourceValues(forKeys: [.fileSizeKey]).fileSize ?? 0
        guard size <= fileLimit else { throw ScenarioError.invalid("« \(name) » dépasse 25 Mo.") }
        return try document(Data(contentsOf: url), name: name)
    }

    static func document(_ data: Data, name: String) throws -> AssistantDocument {
        guard data.count <= fileLimit else { throw ScenarioError.invalid("« \(name) » dépasse 25 Mo.") }
        let raw: String
        do { raw = try text(data, extension: (name as NSString).pathExtension.lowercased()) }
        catch let error as ScenarioError { throw ScenarioError.invalid("« \(name) » : \(error.localizedDescription)") }
        let cleaned = raw.replacingOccurrences(of: "[ \\t]+\\n", with: "\n", options: .regularExpression)
            .replacingOccurrences(of: "\\n{3,}", with: "\n\n", options: .regularExpression)
            .trimmingCharacters(in: .whitespacesAndNewlines)
        guard !cleaned.isEmpty else {
            throw ScenarioError.invalid("« \(name) » ne contient pas de texte lisible. Un PDF numérisé doit d’abord être converti en texte.")
        }
        guard cleaned.count > characterLimit else { return AssistantDocument(name: name, text: cleaned) }
        return AssistantDocument(name: name, text: String(cleaned.prefix(characterLimit)), truncated: true)
    }

    private static func text(_ data: Data, extension ext: String) throws -> String {
        switch ext {
        case "pdf": return try pdf(data)
        case "docx": return try word(data)
        case "pptx": return try slides(data)
        case "xlsx": return try ScenarioOfficeReader.spreadsheet(data).map { $0.joined(separator: "\t") }.joined(separator: "\n")
        case "html", "htm": return try html(ScenarioImporter.text(data))
        case "rtf": return try rtf(data)
        default: return try ScenarioImporter.text(data)
        }
    }

    private static func pdf(_ data: Data) throws -> String {
        guard let document = PDFDocument(data: data) else { throw ScenarioError.invalid("PDF illisible.") }
        guard !document.isLocked else { throw ScenarioError.invalid("PDF protégé par un mot de passe.") }
        return (0..<document.pageCount).compactMap { document.page(at: $0)?.string }.joined(separator: "\n\n")
    }

    private static func paragraphs(_ node: XMLNode) -> String {
        func content(_ node: XMLNode) -> String {
            if node.localName == "t" { return node.stringValue ?? "" }
            if ["br", "cr"].contains(node.localName ?? "") { return "\n" }
            if node.localName == "tab" { return "\t" }
            return (node.children ?? []).map(content).joined()
        }
        return ScenarioOfficeReader.descendants(node, "p").map(content).joined(separator: "\n")
    }

    private static func word(_ data: Data) throws -> String {
        let files = try ImportZip.files(data)
        guard let body = files["word/document.xml"] else { throw ScenarioError.invalid("Ce fichier n’est pas un document Word .docx reconnu.") }
        return paragraphs(try ScenarioOfficeReader.xml(body))
    }

    private static func slides(_ data: Data) throws -> String {
        let files = try ImportZip.files(data)
        func number(_ path: String) -> Int { Int(path.filter(\.isNumber)) ?? 0 }
        let paths = files.keys.filter { $0.hasPrefix("ppt/slides/slide") && $0.hasSuffix(".xml") }.sorted { number($0) < number($1) }
        guard !paths.isEmpty else { throw ScenarioError.invalid("Ce fichier n’est pas une présentation PowerPoint .pptx reconnue.") }
        return try paths.enumerated().map { index, path in
            "Diapositive \(index + 1)\n" + paragraphs(try ScenarioOfficeReader.xml(files[path]!))
        }.joined(separator: "\n\n")
    }

    private static func html(_ source: String) throws -> String {
        let source = source.replacingOccurrences(of: "(?i)<!doctype\\s+html\\s*>", with: "", options: .regularExpression)
        guard !source.uppercased().contains("<!DOCTYPE"), !source.uppercased().contains("<!ENTITY") else {
            throw ScenarioError.invalid("Ce document HTML contient des déclarations non prises en charge.")
        }
        let document = try XMLDocument(xmlString: source, options: [.documentTidyHTML, .nodeLoadExternalEntitiesNever])
        let blocks: Set<String> = ["p", "div", "li", "tr", "h1", "h2", "h3", "h4", "h5", "h6", "section", "article", "table", "ul", "ol", "blockquote", "pre"]
        func content(_ node: XMLNode) -> String {
            if node.kind == .text { return node.stringValue ?? "" }
            let name = (node.name ?? "").lowercased()
            if ["head", "style", "script", "noscript", "template"].contains(name) { return "" }
            if name == "br" { return "\n" }
            let inner = (node.children ?? []).map(content).joined()
            if name == "td" || name == "th" { return inner + "\t" }
            return blocks.contains(name) ? inner + "\n" : inner
        }
        let root: XMLNode? = ScenarioOfficeReader.descendants(document, "body").first ?? document.rootElement()
        return root.map(content) ?? ""
    }

    private static func rtf(_ data: Data) throws -> String {
        do { return try NSAttributedString(data: data, options: [.documentType: NSAttributedString.DocumentType.rtf], documentAttributes: nil).string }
        catch { throw ScenarioError.invalid("Document RTF illisible.") }
    }
}
