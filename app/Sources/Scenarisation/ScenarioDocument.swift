import SwiftUI
import UniformTypeIdentifiers

struct ScenarioDocument: FileDocument {
    static var readableContentTypes: [UTType] { ScenarioImporter.contentTypes }
    static var writableContentTypes: [UTType] { [.json] }
    var scenario: Scenario
    // A navigation state, never written to the scenario JSON.
    var enteredEditor: Bool
    var startWithAI: Bool
    var syncIdentity: SyncDocumentIdentity?
    /// Nouveau scénario à envoyer au compte dès qu’une connexion est disponible.
    var pendingUpload = false

    init(scenario: Scenario = .blank(), enteredEditor: Bool = false, startWithAI: Bool = false,
         syncIdentity: SyncDocumentIdentity? = nil) {
        self.scenario = scenario; self.enteredEditor = enteredEditor || startWithAI; self.startWithAI = startWithAI
        self.scenario.fields.removeValue(forKey: "_scenarisationMac")
        self.scenario.fields.removeValue(forKey: ScenarioFolder.assistantMarker)
        self.scenario.fields.removeValue(forKey: ScenarioFolder.uploadMarker)
        self.syncIdentity = syncIdentity
    }
    init(configuration: ReadConfiguration) throws {
        guard let data = configuration.file.regularFileContents else {
            throw ScenarioError.invalid("Impossible de lire ce document.")
        }
        let filename = configuration.file.preferredFilename ?? "scenario." + (configuration.contentType.preferredFilenameExtension ?? "json")
        try self.init(data: data, filename: filename)
    }
    /// Lecture d’un fichier de scénario, quel que soit le format reconnu par l’import.
    init(data: Data, filename: String) throws {
        scenario = try ScenarioImporter.decode(data, filename: filename)
        syncIdentity = Self.extractIdentity(from: &scenario)
        enteredEditor = true
        startWithAI = ScenarioFolder.extractAssistantStart(from: &scenario)
        pendingUpload = ScenarioFolder.extractPendingUpload(from: &scenario) && syncIdentity == nil
    }
    func fileWrapper(configuration: WriteConfiguration) throws -> FileWrapper {
        guard configuration.contentType == .json else {
            throw ScenarioError.invalid("Enregistrez le scénario importé au format JSON avec Fichier → Enregistrer sous.")
        }
        return FileWrapper(regularFileWithContents: try Self.data(scenario, identity: syncIdentity,
                                                                  pendingUpload: pendingUpload && syncIdentity == nil))
    }

    /// File-only metadata: neither an export nor an API payload contains the Mac's sync identity.
    static func data(_ scenario: Scenario, identity: SyncDocumentIdentity?, pendingUpload: Bool = false) throws -> Data {
        var file = scenario
        // Tant qu’il n’est pas lié au compte, le fichier garde sa demande d’envoi, même hors connexion.
        if pendingUpload { file.fields[ScenarioFolder.uploadMarker] = .bool(true) }
        if let identity {
            file.fields["_scenarisationMac"] = try JSONDecoder().decode(JSONValue.self, from: JSONEncoder().encode(identity))
        }
        return try file.encoded()
    }
    static func extractIdentity(from scenario: inout Scenario) -> SyncDocumentIdentity? {
        guard let value = scenario.fields.removeValue(forKey: "_scenarisationMac"),
              let data = try? JSONEncoder().encode(value) else { return nil }
        return try? JSONDecoder().decode(SyncDocumentIdentity.self, from: data)
    }
}

/// Actions de la fenêtre active, exposées aux menus de l’app (Scénario, Présentation).
struct EditorActions {
    var addMoment: () -> Void
    var openModels: () -> Void
    var importFile: () -> Void
    var exportScenario: () -> Void
    var toggleAssistant: () -> Void
    var setLayout: (String) -> Void
    var searchSite: () -> Void
}

extension FocusedValues {
    @Entry var editorActions: EditorActions?
}
