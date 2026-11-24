import SwiftUI
import UniformTypeIdentifiers

struct LaunchView: View {
    @Environment(\.newDocument) private var newDocument
    @Environment(\.dismissWindow) private var dismissWindow
    @Environment(\.openWindow) private var openWindow
    @State private var showModels = false
    @State private var showImport = false
    @State private var error: String?

    var body: some View {
        WelcomeView(create: {
            var scenario = Scenario.blank()
            scenario.fields["topPanelCollapsed"] = .bool(false)
            open(scenario)
        }, models: { showModels = true }, open: { showImport = true }, createWithAI: {
            open(.blank(), startWithAI: true)
        }, account: { openWindow(id: "site-account") })
        .sheet(isPresented: $showModels) {
            TemplateLibrary { scenario in showModels = false; open(scenario) }
        }
        .fileImporter(isPresented: $showImport, allowedContentTypes: ScenarioImporter.contentTypes) { result in
            do {
                let url = try result.get()
                let access = url.startAccessingSecurityScopedResource()
                defer { if access { url.stopAccessingSecurityScopedResource() } }
                open(try ScenarioImporter.read(url))
            } catch { self.error = error.localizedDescription }
        }
        .alert("Impossible d’ouvrir le scénario", isPresented: Binding(get: { error != nil }, set: { if !$0 { error = nil } })) {
            Button("Fermer") { error = nil }
        } message: { Text(error ?? "") }
    }
    /// Le scénario devient aussitôt un fichier JSON du dossier choisi : macOS l’enregistre ensuite en continu.
    private func open(_ scenario: Scenario, startWithAI: Bool = false) {
        Task { @MainActor in
            guard let folder = ScenarioFolder.current ?? ScenarioFolder.choose() else {
                // Sans dossier, le scénario reste un document sans titre, à enregistrer soi-même.
                newDocument(ScenarioDocument(scenario: scenario, enteredEditor: true, startWithAI: startWithAI))
                dismissWindow(id: "welcome")
                return
            }
            do {
                let url = try ScenarioFolder.save(scenario, startWithAI: startWithAI, in: folder)
                _ = try await NSDocumentController.shared.openDocument(withContentsOf: url, display: true)
                dismissWindow(id: "welcome")
            } catch { self.error = error.localizedDescription }
        }
    }
}
