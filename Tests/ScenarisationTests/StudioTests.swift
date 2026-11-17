import Foundation
import Testing
@testable import Scenarisation

@Suite("Modèles du site et exports")
struct StudioTests {
    @Test func newDocumentDoesNotBypassTheFourStartChoices() throws {
        #expect(!ScenarioDocument().enteredEditor)
        #expect(ScenarioDocument(scenario: .template(), enteredEditor: true).enteredEditor)
        let data = try ScenarioDocument(scenario: .template(), enteredEditor: true).scenario.encoded()
        #expect(!String(decoding: data, as: UTF8.self).contains("enteredEditor"))
        let assisted = ScenarioDocument(startWithAI: true)
        #expect(assisted.enteredEditor && assisted.initialPane == .assistant)
        #expect(ScenarioDocument(scenario: .template(), enteredEditor: true).initialPane == .board)
        let saved = String(decoding: try assisted.scenario.encoded(), as: UTF8.self)
        #expect(!saved.contains("startWithAI") && !saved.contains("enteredEditor"))
    }
    @Test func all28WebTemplatesLoadWithoutLosingTheirStructure() throws {
        let templates = try ScenarioTemplate.bundled.get()
        #expect(templates.count == 28)
        #expect(Set(templates.map { $0.entry.family }).count == 8)
        for item in templates {
            #expect(item.design.moments.count == item.entry.momentCount)
            #expect(item.design.activityCount == item.entry.activityCount)
            #expect(item.design.duration == Double(item.entry.minutes))
            #expect(try Scenario.decode(item.design.encoded(), strict: true) == item.design)
        }
        #expect(try BloomCategory.bundled.get().count == 6)
    }

    @Test func studentExportsRemoveAllTeacherOnlyContent() throws {
        var source = Scenario.template()
        source.meta["description"] = .string("PRIVATE-METADATA")
        source.meta["hidden"] = .string("PRIVATE-UNKNOWN")
        source.moments[0]["notes"] = "PRIVATE-NOTES"
        source.moments[0]["objectives"] = "PRIVATE-OBJECTIVES"
        source.moments[0].activities[0]["description"] = "PRIVATE-DESCRIPTION"
        source.moments[0].activities[0]["instructions"] = "PUBLIC-INSTRUCTIONS"
        let selected = Set([source.moments[0].id])
        for format in ScenarioFormat.allCases {
            let data = try ScenarioExporter.data(source, format: format, students: true, moments: selected)
            let text = String(decoding: data, as: UTF8.self)
            #expect(!text.contains("PRIVATE-"))
            #expect(text.contains("PUBLIC-INSTRUCTIONS"))
            #expect(!text.contains(source.moments[1]["title"]))
        }
        let result = try Scenario.decode(ScenarioExporter.data(source, format: .json, students: true, moments: selected))
        #expect(result.moments.count == 1)
        #expect(result.moments[0].activities[0]["description"].isEmpty)
    }

    @Test func teacherJSONPreservesHiddenFieldsAndScopesMoments() throws {
        var source = Scenario.template()
        source.fields["unexposed"] = .object(["value": .number(12)])
        let data = try ScenarioExporter.data(source, format: .json, students: false, moments: Set([source.moments[1].id]))
        let result = try Scenario.decode(data)
        #expect(result.fields["unexposed"] == source.fields["unexposed"])
        #expect(result.moments == [source.moments[1]])
    }

    @Test func chartsAreWeightedByDuration() {
        var scenario = Scenario.template()
        scenario.moments[0].activities[0].duration = 40
        let slices = LearningStatistics.slices(scenario, key: "type", choices: Pedagogy.types)
        #expect(slices.reduce(0) { $0 + $1.minutes } == 60)
        #expect(slices.first { $0.id == "read" }?.minutes == 40)
        #expect(LearningStatistics.aias(scenario).reduce(0) { $0 + $1.minutes } == 60)
    }

    @Test func spreadsheetRowsMatchSiteColumnCount() {
        let rows = ScenarioExporter.rows(.template(), students: false)
        #expect(rows.count == 4)
        #expect(rows.allSatisfy { $0.count == 33 })
        #expect(ScenarioExporter.column(27) == "AA")
        #expect(ScenarioExporter.column(33) == "AG")
        #expect(StoredZip.crc32(Data("123456789".utf8)) == 0xcbf43926)
    }

    @Test(arguments: [ScenarioFormat.word, .excel])
    func officeExportsHaveValidZipAndXML(_ format: ScenarioFormat) throws {
        var scenario = Scenario.template()
        scenario.meta["name"] = .string("Français & <maths> — évaluation")
        let data = try ScenarioExporter.data(scenario, format: format, students: false, moments: Set(scenario.moments.map(\.id)))
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: folder) }
        let url = folder.appendingPathComponent("scenario." + format.rawValue)
        try data.write(to: url)
        let process = Process()
        process.executableURL = URL(fileURLWithPath: "/usr/bin/unzip")
        process.arguments = ["-t", url.path]
        let pipe = Pipe(); process.standardOutput = pipe; process.standardError = pipe
        try process.run()
        let output = pipe.fileHandleForReading.readDataToEndOfFile()
        process.waitUntilExit()
        #expect(process.terminationStatus == 0, "\(String(decoding: output, as: UTF8.self))")

        // Parse every uncompressed XML part independently of the exporter.
        var offset = 0
        func u16(_ position: Int) -> Int { data.withUnsafeBytes { Int($0.loadUnaligned(fromByteOffset: position, as: UInt16.self).littleEndian) } }
        func u32(_ position: Int) -> UInt32 { data.withUnsafeBytes { $0.loadUnaligned(fromByteOffset: position, as: UInt32.self).littleEndian } }
        var count = 0
        while offset + 30 <= data.count && u32(offset) == 0x04034b50 {
            let nameLength = u16(offset + 26); let extra = u16(offset + 28)
            let size = Int(u32(offset + 18)); let start = offset + 30 + nameLength + extra
            let parser = XMLParser(data: data.subdata(in: start..<(start + size)))
            #expect(parser.parse(), "XML invalide : \(parser.parserError?.localizedDescription ?? "")")
            count += 1; offset = start + size
        }
        #expect(count >= 3)
    }
}
