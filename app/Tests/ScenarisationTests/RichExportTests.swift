import Foundation
import Testing
@testable import Scenarisation

@Suite("Exports HTML et Word mis en page")
struct RichExportTests {
    private func sample() -> Scenario {
        var source = Scenario.template()
        source.meta["description"] = .string("Observer **l’eau**, puis *expliquer*.\n\n- une observation\n- une recherche")
        source.moments[0]["objectives"] = "Comparer."
        source.moments[0]["notes"] = "Préparer les documents."
        source.moments[0].activities[0]["instructions"] = "Lisez, puis répondez :\n\n1. Où va l’eau ?\n2. Pourquoi ?\n\nVoir [la fiche](https://exemple.fr)."
        source.moments[0].activities[0]["groupMode"] = "subgroups"
        source.moments[0].activities[0]["evaluationMode"] = "formative"
        source.moments[0].activities[0].aiasSelection = "3"
        source.moments[0].activities[0].duration = 12.5
        source.moments[1].activities[0]["notes"] = "Note d’activité."
        source.moments.append(.blank(title: "Prolongement"))
        return source
    }
    private func export(_ scenario: Scenario, _ format: ScenarioFormat, students: Bool = false) throws -> Data {
        try ScenarioExporter.data(scenario, format: format, students: students, moments: Set(scenario.moments.map(\.id)))
    }

    @Test func htmlRendersMarkdownAndTypeColors() throws {
        let page = String(decoding: try export(sample(), .html), as: UTF8.self)
        let html = String(try #require(ExportPayload.htmlBody(page)))
        #expect(html.contains("<strong>l’eau</strong>"))
        #expect(html.contains("<ol><li>Où va l’eau ?</li>"))
        #expect(html.contains("<a href=\"https://exemple.fr\">la fiche</a>"))
        #expect(!html.contains("**"))
        #expect(html.contains("--c:#4FD1C5"))
        #expect(html.contains("<strong>Groupe<span class=\"vh\"> :</span></strong> <span>Sous-groupes</span>"))
    }

    @Test func wordUsesStylesListsLinksAndColors() throws {
        let files = try ImportZip.files(try export(sample(), .word))
        let document = String(decoding: try #require(files["word/document.xml"]), as: UTF8.self)
        #expect(document.contains("<w:b/></w:rPr><w:t xml:space=\"preserve\">l’eau</w:t>"))
        #expect(document.contains("<w:pStyle w:val=\"Heading2\"/>"))
        #expect(document.contains("<w:numId w:val=\"2\"/>"))
        #expect(document.contains("<w:hyperlink r:id=\"rIdLink1\""))
        #expect(document.contains("w:fill=\"4FD1C5\""))
        #expect(!document.contains("**"))
        let relationships = String(decoding: try #require(files["word/_rels/document.xml.rels"]), as: UTF8.self)
        #expect(relationships.contains("Target=\"https://exemple.fr\" TargetMode=\"External\""))
        for name in ["word/styles.xml", "word/numbering.xml", "word/footer1.xml", "word/settings.xml", "docProps/core.xml", "word/document.xml"] {
            let parser = XMLParser(data: try #require(files[name]))
            #expect(parser.parse(), "\(name) : \(parser.parserError?.localizedDescription ?? "")")
        }
    }

    @Test(arguments: [ScenarioFormat.html, .word])
    func untouchedExportsReimportExactly(_ format: ScenarioFormat) throws {
        let source = sample()
        let result = try ScenarioImporter.decode(try export(source, format), filename: "scenario." + format.rawValue)
        #expect(result.moments == source.moments)
        #expect(result.meta["description"] == source.meta["description"])
    }

    /// Document retouché : la copie embarquée n’est plus valable, l’import relit le contenu affiché.
    @Test(arguments: [ScenarioFormat.html, .word])
    func editedExportsReimportFromVisibleContent(_ format: ScenarioFormat) throws {
        let source = sample()
        var data = try export(source, format)
        if format == .html {
            data = Data(String(decoding: data, as: UTF8.self).replacingOccurrences(of: "Préparer les documents.", with: "Documents modifiés.").utf8)
        } else {
            var files = try ImportZip.files(data)
            files["word/document.xml"] = Data(String(decoding: files["word/document.xml"]!, as: UTF8.self)
                .replacingOccurrences(of: "Préparer les documents.", with: "Documents modifiés.").utf8)
            data = StoredZip.archive(files.sorted { $0.key < $1.key }.map { ($0.key, String(decoding: $0.value, as: UTF8.self)) })
        }
        let result = try ScenarioImporter.decode(data, filename: "scenario." + format.rawValue)
        #expect(result.name == source.name)
        #expect(result.moments.map { $0["title"] } == source.moments.map { $0["title"] })
        #expect(result.moments.map(\.activities.count) == source.moments.map(\.activities.count))
        #expect(result.moments[0]["notes"] == "Documents modifiés.")
        #expect(result.moments[0]["objectives"] == "Comparer.")
        let first = result.moments[0].activities[0]
        #expect(first["type"] == "read")
        #expect(first.duration == 12.5)
        #expect(first["groupMode"] == "subgroups")
        #expect(first["teachingMode"] == "guided")
        #expect(first["evaluationMode"] == "formative")
        #expect(first.aiasSelection == "3")
        #expect(first["description"] == "Découvrir")
        #expect(first["instructions"].contains("Où va l’eau ?"))
        #expect(result.moments[1].activities[0]["type"] == "practice")
        #expect(result.moments[1].activities[0]["notes"] == "Note d’activité.")
        #expect(result.meta["description"]?.string.contains("une observation") == true)
    }

    @Test(arguments: [ScenarioFormat.html, .word])
    func editedStudentExportsKeepInstructions(_ format: ScenarioFormat) throws {
        let source = sample()
        var data = try export(source, format, students: true)
        if format == .html {
            data = Data(String(decoding: data, as: UTF8.self).replacingOccurrences(of: "</footer>", with: "</footer><!-- retouche -->").utf8)
        } else {
            var files = try ImportZip.files(data)
            files[ExportPayload.wordPath] = nil
            data = StoredZip.archive(files.sorted { $0.key < $1.key }.map { ($0.key, String(decoding: $0.value, as: UTF8.self)) })
        }
        let result = try ScenarioImporter.decode(data, filename: "scenario." + format.rawValue)
        #expect(result.moments.map(\.activities.count) == source.moments.map(\.activities.count))
        #expect(result.moments[2].activities[0]["instructions"] == source.moments[2].activities[0]["instructions"])
        #expect(result.moments[0].activities[0].duration == 12.5)
    }
}
