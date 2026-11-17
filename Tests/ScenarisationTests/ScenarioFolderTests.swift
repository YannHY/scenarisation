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

    @Test func filesFollowTheTitleUntilTheTeacherRenamesThem() throws {
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: folder) }
        let untitled = try ScenarioFolder.save(.blank(), in: folder)
        #expect(untitled.lastPathComponent == "Sans titre.json")
        let renamed = ScenarioFolder.renamedURL(for: untitled, previousTitle: "", title: "Chanter, enchanter le monde")
        #expect(renamed?.lastPathComponent == "Chanter, enchanter le monde.json")
        let numbered = folder.appendingPathComponent("Sans titre 3.json")
        #expect(ScenarioFolder.renamedURL(for: numbered, previousTitle: "", title: "Poésie")?.lastPathComponent == "Poésie.json")
        // L’ancien titre est suivi ; un homonyme existant donne un nom numéroté.
        _ = try ScenarioFolder.save(named("Poésie : sonorités"), in: folder)
        let previous = folder.appendingPathComponent("Poésie.json")
        #expect(ScenarioFolder.renamedURL(for: previous, previousTitle: "Poésie", title: "Poésie : sonorités")?.lastPathComponent
                == "Poésie - sonorités 2.json")
        // Pas de renommage sans changement, vers « Sans titre » ou pour un nom choisi par l’enseignant.
        #expect(ScenarioFolder.renamedURL(for: previous, previousTitle: "Poésie", title: "Poésie") == nil)
        #expect(ScenarioFolder.renamedURL(for: previous, previousTitle: "Poésie", title: "") == nil)
        #expect(ScenarioFolder.renamedURL(for: folder.appendingPathComponent("Ma séquence.json"), previousTitle: "Poésie", title: "Chant") == nil)
        #expect(ScenarioFolder.renamedURL(for: folder.appendingPathComponent(UUID().uuidString + ".json"), previousTitle: "", title: "Chant") == nil)
        #expect(ScenarioFolder.renamedURL(for: folder.appendingPathComponent("Chant 2.json"), previousTitle: "Chant", title: "Chant") == nil)
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

    @Test func linkedFilesAreFoundByTheirAccountIdentity() throws {
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: folder) }
        let identity = SyncDocumentIdentity(id: UUID(), accountKey: "compte")
        let linked = folder.appendingPathComponent("Fractions.json")
        try ScenarioDocument.data(named("Fractions"), identity: identity).write(to: linked)
        try ScenarioDocument.data(named("Autre"), identity: SyncDocumentIdentity(id: UUID(), accountKey: "compte"))
            .write(to: folder.appendingPathComponent("Autre.json"))
        try ScenarioDocument.data(named("Même identité, autre compte"), identity: SyncDocumentIdentity(id: identity.id, accountKey: "autre"))
            .write(to: folder.appendingPathComponent("Autre compte.json"))
        _ = try ScenarioFolder.save(named("Non lié"), in: folder)
        try Data("pas du JSON".utf8).write(to: folder.appendingPathComponent("Notes.json"))
        #expect(ScenarioFolder.files(linkedTo: identity, in: folder).map(\.lastPathComponent) == ["Fractions.json"])
        #expect(ScenarioFolder.identity(of: linked) == identity)
    }
}
