import Foundation
import Testing
@testable import Scenarisation

@Suite("Dossier des scénarios")
struct ScenarioFolderTests {
    private func named(_ name: String) -> Scenario {
        var scenario = Scenario.template(); scenario.meta["name"] = .string(name); return scenario
    }

    @Test func fileNamesAreSafeAndNeverEmpty() {
        #expect(ScenarioFolder.fileName(.blank()) == "Sans titre" && ScenarioFolder.fileName(named(" / ")) == "-" && ScenarioFolder.fileName(named("  ")) == "Sans titre")
        #expect(ScenarioFolder.fileName(named("  Le conte : début/fin  ")) == "Le conte - début-fin")
        #expect(ScenarioFolder.fileName(named("..caché")) == "caché")
        #expect(ScenarioFolder.fileName(named(String(repeating: "a", count: 200))).count == 80)
    }

    @Test func savingNeverOverwritesAndKeepsTheScenarioIntact() throws {
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: folder) }
        let scenario = named("Fractions")
        let first = try ScenarioFolder.save(scenario, in: folder)
        let second = try ScenarioFolder.save(scenario, startWithAI: true, in: folder)
        #expect(first.lastPathComponent == "Fractions.json" && second.lastPathComponent == "Fractions 2.json")
        var plain = try ScenarioImporter.read(first)
        #expect(!ScenarioFolder.extractAssistantStart(from: &plain) && ScenarioFolder.extractPendingUpload(from: &plain))
        #expect(plain == scenario)
        var assisted = try ScenarioImporter.read(second)
        #expect(ScenarioFolder.extractAssistantStart(from: &assisted) && ScenarioFolder.extractPendingUpload(from: &assisted))
        #expect(assisted == scenario)
        #expect(ScenarioDocument(scenario: try ScenarioImporter.read(second)).scenario == scenario)
    }

    @Test func aPendingUploadSurvivesUntilTheScenarioIsLinkedAndNeverReachesExports() throws {
        let scenario = named("Fractions")
        let pending = try ScenarioDocument.data(scenario, identity: nil, pendingUpload: true)
        var reread = try Scenario.decode(pending)
        #expect(ScenarioFolder.extractPendingUpload(from: &reread) && reread == scenario)
        let linked = try ScenarioDocument.data(scenario, identity: SyncDocumentIdentity(id: UUID(), accountKey: "compte"))
        #expect(!String(decoding: linked, as: UTF8.self).contains(ScenarioFolder.uploadMarker))
        #expect(!String(decoding: try ScenarioExporter.data(scenario, format: .json, students: false, moments: Set(scenario.moments.map(\.id))), as: UTF8.self)
            .contains(ScenarioFolder.uploadMarker))
        #expect(!ScenarioFolder.hasContent(.blank()))
        #expect(ScenarioFolder.hasContent(scenario))
        var untitledWithMoment = Scenario.blank(); untitledWithMoment.moments = [.blank()]
        #expect(ScenarioFolder.hasContent(untitledWithMoment))
    }
}
