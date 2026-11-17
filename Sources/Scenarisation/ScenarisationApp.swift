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
}

extension FocusedValues {
    @Entry var editorActions: EditorActions?
}

struct ScenarioCommands: Commands {
    @FocusedValue(\.editorActions) private var actions
    @Environment(\.openWindow) private var openWindow
    var body: some Commands {
        CommandGroup(after: .newItem) {
            Button("Accueil") { openWindow(id: "welcome") }.keyboardShortcut("0", modifiers: [.command, .shift])
            Button("Mon compte et mes scénarios…") { openWindow(id: "site-account") }
        }
        CommandGroup(after: .sidebar) {
            Divider()
            Button("Afficher en liste") { actions?.setLayout("list") }.keyboardShortcut("1", modifiers: .command).disabled(actions == nil)
            Button("Afficher en colonnes") { actions?.setLayout("columns") }.keyboardShortcut("2", modifiers: .command).disabled(actions == nil)
            Button("Afficher en grille") { actions?.setLayout("grid") }.keyboardShortcut("3", modifiers: .command).disabled(actions == nil)
            Divider()
            Button("Assistant IA") { actions?.toggleAssistant() }.keyboardShortcut("i", modifiers: [.command, .option]).disabled(actions == nil)
        }
        CommandMenu("Scénario") {
            Button("Ajouter un moment") { actions?.addMoment() }.keyboardShortcut("n", modifiers: [.command, .shift]).disabled(actions == nil)
            Divider()
            Button("Importer un modèle…") { actions?.openModels() }.keyboardShortcut("m", modifiers: [.command, .shift]).disabled(actions == nil)
            Button("Importer un scénario…") { actions?.importFile() }.keyboardShortcut("i", modifiers: [.command, .shift]).disabled(actions == nil)
            Divider()
            Button("Exporter…") { actions?.exportScenario() }.keyboardShortcut("e", modifiers: [.command, .shift]).disabled(actions == nil)
        }
    }
}

@main
struct ScenarisationApp: App {
    @NSApplicationDelegateAdaptor(StudioAppDelegate.self) private var appDelegate
    private let forcedDark = ProcessInfo.processInfo.arguments.contains("--dark")
    var body: some Scene {
        Window("Scénarisation", id: "welcome") {
            // Taille fixe : l’accueil est un bloc compact, l’agrandir n’apporte rien.
            // La barre de titre masquée ajoute 32 points : la fenêtre mesure 940 × 620.
            LaunchView().frame(width: 940, height: 588)
                .containerBackground(.regularMaterial, for: .window)
                .preferredColorScheme(forcedDark ? .dark : nil)
        }
        .windowStyle(.hiddenTitleBar)
        .windowResizability(.contentSize)
        .defaultLaunchBehavior(.presented)
        DocumentGroup(newDocument: ScenarioDocument()) { file in
            EditorView(document: file.$document, fileURL: file.fileURL)
                .frame(minWidth: 940, minHeight: 600)
                .preferredColorScheme(forcedDark ? .dark : nil)
        }
        .windowToolbarStyle(.unified)
        .defaultSize(width: 1320, height: 840)
        .defaultLaunchBehavior(.suppressed)
        .restorationBehavior(.disabled)
        .commands { ScenarioCommands(); HelpCommands() }
        Window("Aide de Scénarisation", id: "help") { HelpWindow() }
            .defaultSize(width: 1040, height: 720)
            .restorationBehavior(.disabled)
        Window("Licence et crédits", id: "license") { LicenseWindow() }
            .defaultSize(width: 720, height: 680)
            .windowResizability(.contentMinSize)
            .restorationBehavior(.disabled)
        Window("Mon compte et mes scénarios", id: "site-account") { SiteAccountView() }
            .defaultSize(width: 780, height: 640)
            .restorationBehavior(.disabled)
        Settings { AISettingsView() }
    }
}

@MainActor
final class StudioAppDelegate: NSObject, NSApplicationDelegate {
    // The launcher is the only initial window; documents are opened explicitly.
    func applicationShouldOpenUntitledFile(_ sender: NSApplication) -> Bool { false }
}
