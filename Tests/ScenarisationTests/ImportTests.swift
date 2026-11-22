import Foundation
import Testing
import UniformTypeIdentifiers
@testable import Scenarisation

@Suite("Import des scénarios du site")
struct ImportTests {
    private func fixture(_ name: String, _ ext: String) throws -> Data {
        let url = try #require(Bundle.module.url(forResource: name, withExtension: ext, subdirectory: "Fixtures"))
        return try Data(contentsOf: url)
    }
    private func load(_ name: String, _ ext: String) throws -> Scenario {
        try ScenarioImporter.decode(fixture(name, ext), filename: name + "." + ext)
    }

    @Test(arguments: ["json", "md", "csv", "xlsx", "html", "docx"])
    func originalTeacherExports(_ ext: String) throws {
        let source = try load("site-teacher", "json")
        let result = try load("site-teacher", ext)
        #expect(result.name == source.name)
        #expect(result.moments.map { $0["title"] } == source.moments.map { $0["title"] })
        #expect(result.activityCount == 3)
        #expect(result.duration == 42.5)
        #expect(result.moments[2].activities.isEmpty)
        for key in ["description", "command", "teacherMaterials", "personas", "modeDelivery", "schoolSystem", "schoolLevel",
                    "sizeClass", "designers", "trainers", "dayHours", "learningDays", "learningHours", "learningMinutes"] {
            #expect(result.meta[key] == source.meta[key], "\(ext), métadonnée \(key)")
        }
        #expect(result.meta["sliders"]?.array.first?.object["verb"]?.string == "Expliquer")
        #expect(result.meta["sliders"]?.array.first?.object["text"]?.string == "le cycle de l’eau")
        for (actual, expected) in zip(result.moments, source.moments) {
            for key in ["objectives", "intentions", "notes"] { #expect(actual[key] == expected[key], "\(ext), séance \(key)") }
            for (activity, original) in zip(actual.activities, expected.activities) {
                for key in ["type", "groupMode", "teachingMode", "syncMode", "locationMode", "evaluationMode", "description", "instructions"] {
                    #expect(activity[key] == original[key], "\(ext), activité \(key) : \(activity[key])")
                }
                #expect(activity.aiasSelection == original.aiasSelection)
            }
        }
    }

    @Test(arguments: ["json", "md", "csv", "xlsx", "html", "docx"])
    func originalStudentExports(_ ext: String) throws {
        let result = try load("site-student", ext)
        let source = try load("site-teacher", "json")
        #expect(result.name == source.name)
        #expect(result.moments.map { $0["title"] } == source.moments.map { $0["title"] })
        #expect(result.activityCount == 3)
        #expect(result.moments[2].activities.isEmpty)
        #expect(result.moments.flatMap(\.activities).map { $0["instructions"] } == source.moments.flatMap(\.activities).map { $0["instructions"] })
        #expect(result.moments.flatMap(\.activities).allSatisfy { $0["description"].isEmpty })
        #expect(result.duration == 30) // No durations are present in these exports.
        #expect(try Scenario.decode(result.encoded(), strict: true).activityCount == 3)
    }

    @Test(arguments: ScenarioFormat.allCases)
    func nativeExportsCanBeImported(_ format: ScenarioFormat) throws {
        var source = Scenario.template()
        source.meta["description"] = .string("Description\navec accents & <chevrons>.")
        source.moments[0]["objectives"] = "Objectif\nmultiligne."
        source.moments[0]["intentions"] = "Un choix pédagogique."
        source.moments[0]["notes"] = "À préparer."
        source.moments[0].activities[0]["instructions"] = "Consigne\n- avec une liste\n- et des guillemets : « oui »"
        source.moments[0].activities[0]["notes"] = "Note d’activité."
        source.moments[0].activities[0].aiasSelection = "4"
        source.moments.append(.blank(title: "Prolongement"))
        let data = try ScenarioExporter.data(source, format: format, students: false, moments: Set(source.moments.map(\.id)))
        let result = try ScenarioImporter.decode(data, filename: "scenario." + format.rawValue.uppercased())
        #expect(result.name == source.name)
        #expect(result.activityCount == 3)
        #expect(result.moments.count == 4)
        #expect(result.moments[3].activities.isEmpty)
        #expect(result.duration == source.duration)
        #expect(result.meta["description"] == source.meta["description"])
        #expect(result.moments[0]["objectives"] == source.moments[0]["objectives"])
        #expect(result.moments[0]["intentions"] == source.moments[0]["intentions"])
        #expect(result.moments[0]["notes"] == source.moments[0]["notes"])
        #expect(result.moments[0].activities[0]["instructions"] == source.moments[0].activities[0]["instructions"])
        #expect(result.moments[0].activities[0]["notes"] == source.moments[0].activities[0]["notes"])
        #expect(result.moments[0].activities[0].aiasSelection == "4")
    }

    @Test(arguments: [",", ";", "\t"])
    func csvHandlesSeparatorsQuotedMultilineAndOrdering(_ separator: String) throws {
        let rows = [
            ["session_index", "session_title", "activity_index", "duration_minutes", "activity_instructions"],
            ["2", "Même titre", "2.2", "5,5", "Ligne 1\nLigne 2 ; avec , et \"citation\""],
            ["1", "Même titre", "1.1", "3", "Premier"],
            ["2", "Même titre", "2.1", "2", "Avant"],
            ["3", "Sans activité", "", "", ""]]
        let text = rows.map { $0.map { "\"" + $0.replacingOccurrences(of: "\"", with: "\"\"") + "\"" }.joined(separator: separator) }.joined(separator: "\r\n")
        let result = try ScenarioImporter.decode(Data(("\u{feff}" + text).utf8), filename: "test.csv")
        #expect(result.moments.count == 3)
        #expect(result.activityCount == 3)
        #expect(result.duration == 10.5)
        #expect(result.moments[0].activities[0]["instructions"] == "Premier")
        #expect(result.moments[1].activities.map { $0["instructions"] } == ["Avant", rows[1][4]])
    }

    @Test func legacyLdjMigratesAndValidates() throws {
        let data = Data(#"""
        {"name":"Ancien scénario","description":"Introduction","topic":"L’eau","aims":"Comprendre","author":"Yann",
         "groupSize":24,"learningTime":"1 j 2 h 30 min","modeOfDelivery":"online",
         "outcomes":[{"verb":"Expliquer","details":"le cycle"}],
         "activities":[{"title":"Observer","groupSize":24,"teachingMethod":"Recherche","notes":"Note",
           "resources":["https://example.com"],"slas":[{"type":"Investigation","duration":"15","groupSize":4,
           "groupSizeSameAsSession":false,"tutorAvailable":false,"syncActivity":false,"onlineActivity":true,
           "assessmentType":"Formative","description":"Rechercher","studentInstructions":"Comparez.","resources":["Une carte"]}]}]}
        """#.utf8)
        for ext in ["json", "ldj"] {
            let result = try ScenarioImporter.decode(data, filename: "ancien." + ext)
            #expect(result.name == "Ancien scénario")
            #expect(result.meta["learningMinutes"]?.number == 30)
            #expect(result.meta["modeDelivery"]?.string == "online")
            #expect(result.meta["sliders"]?.array.first?.object["verb"]?.string == "Expliquer")
            let activity = try #require(result.moments.first?.activities.first)
            #expect(activity["type"] == "investigate")
            #expect(activity["groupMode"] == "subgroups")
            #expect(activity["teachingMode"] == "independent")
            #expect(activity["syncMode"] == "async")
            #expect(activity["locationMode"] == "online")
            #expect(activity["instructions"] == "Comparez.")
            #expect(result.moments[0]["notes"].contains("https://example.com"))
            #expect(try Scenario.decode(result.encoded(), strict: true).duration == 15)
        }
    }

    @Test(arguments: [("site-teacher-shared", "xlsx"), ("site-teacher-deflated", "docx")])
    func compressedOfficeFilesAndSharedStrings(_ fixtureName: String, _ ext: String) throws {
        let result = try load(fixtureName, ext)
        let source = try load("site-teacher", "json")
        #expect(result.name == source.name)
        #expect(result.moments.count == 3)
        #expect(result.activityCount == 3)
        #expect(result.duration == 42.5)
        #expect(result.moments[2].activities.isEmpty)
        #expect(result.meta["description"] == source.meta["description"])
        #expect(result.moments[0].activities[0]["instructions"] == source.moments[0].activities[0]["instructions"])
        #expect(result.moments[0].activities[0]["teachingMode"] == "directed")
        #expect(result.moments[0].activities[1].aiasSelection == "not_applicable")
    }

    @Test func utf16AndMarkdownFencesPreserveTheContent() throws {
        let source = "# Un scénario\r\n\r\n## 1. Un moment\r\n### 1.1 Produire\r\n- Durée: 5 min\r\n- Consignes pour les élèves: Lire ce code.\r\n```markdown\r\n## Ce titre appartient au code\r\n### Activité 2\r\n```"
        let data = try #require(source.data(using: .utf16))
        let result = try ScenarioImporter.decode(data, filename: "utf16.MARKDOWN")
        #expect(result.moments.count == 1)
        #expect(result.activityCount == 1)
        #expect(result.moments[0].activities[0]["instructions"].contains("## Ce titre appartient au code"))
    }

    @Test(arguments: ["\"session_index,session_title\n1,Test", "one,two\n1,2", "session_index,session_title,duration_minutes\n1,Test,-3",
                      "session_index,session_title\n1,\"Test\"oops", "session_index,session_title\n1,Test,extra"])
    func malformedCsvIsRejected(_ text: String) {
        #expect(throws: ScenarioError.self) { try ScenarioImporter.decode(Data(text.utf8), filename: "bad.csv") }
    }

    @Test func malformedFilesAndOversizedDocumentsAreRejected() throws {
        for ext in ["json", "ldj", "md", "csv", "xlsx", "docx", "html", "pdf", "xls"] {
            #expect(throws: (any Error).self) { try ScenarioImporter.decode(Data("not a scenario".utf8), filename: "bad." + ext) }
        }
        #expect(throws: ScenarioError.self) { try ScenarioImporter.decode(Data(repeating: 32, count: 20_000_001), filename: "large.md") }
        let archive = StoredZip.archive([("xl/workbook.xml", "<!DOCTYPE x [<!ENTITY y SYSTEM 'file:///etc/passwd'>]><x>&y;</x>"), ("xl/_rels/workbook.xml.rels", "<Relationships/>")])
        #expect(throws: ScenarioError.self) { try ScenarioImporter.decode(archive, filename: "entities.xlsx") }
        let valid = try fixture("site-teacher", "xlsx")
        for length in [0, 21, 100, valid.count - 1] {
            #expect(throws: ScenarioError.self) { try ScenarioImporter.decode(Data(valid.prefix(length)), filename: "truncated.xlsx") }
        }
        var corrupt = valid; corrupt[100] ^= 1
        #expect(throws: ScenarioError.self) { try ScenarioImporter.decode(corrupt, filename: "corrupt.xlsx") }
    }

    @Test func sparseSpreadsheetCannotAmplifyIntoMillionsOfCells() throws {
        let rows = (1...245).map { "<row><c r=\"FAN\($0)\"><v>1</v></c></row>" }.joined()
        let archive = StoredZip.archive([
            ("xl/workbook.xml", "<workbook><sheet id=\"1\"/></workbook>"),
            ("xl/_rels/workbook.xml.rels", "<Relationships><Relationship Id=\"1\" Type=\"/worksheet\" Target=\"worksheets/sheet1.xml\"/></Relationships>"),
            ("xl/worksheets/sheet1.xml", "<worksheet><sheetData>\(rows)</sheetData></worksheet>")
        ])
        do {
            _ = try ScenarioOfficeReader.spreadsheet(archive)
            Issue.record("Le budget global de cellules aurait dû interrompre l’import.")
        } catch {
            #expect(error.localizedDescription.contains("trop de cellules"))
        }
    }

    @Test func allEntryPointsAdvertiseTheSameFormatsAndWriteJSON() {
        for ext in ScenarioImporter.extensions {
            #expect(ScenarioDocument.readableContentTypes.contains { $0 == UTType(filenameExtension: ext) })
        }
        #expect(ScenarioDocument.writableContentTypes == [.json])
    }

    @Test func competencyLabelsAndShortCodesResolveToOriginalIdentifiers() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        let first = try #require(catalog.competencies.first)
        let second = catalog.competencies[2]
        let values = ImportValues.tools("\(first.code). \(first.labelFr), \(second.code). \(second.labelFr)")
        #expect(values == .array([.string(first.id), .string(second.id)]))
        let aliases = ImportValues.tools("Acquérir-I-1;A3;unknown-competency")
        #expect(aliases == .array([.string(first.id), .string(second.id), .string("unknown-competency")]))
    }
}
