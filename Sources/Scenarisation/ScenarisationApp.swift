import SwiftUI

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
            Divider()
            Button("Rechercher sur le site…") { actions?.searchSite() }.keyboardShortcut("f", modifiers: [.command, .option]).disabled(actions == nil)
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
    func applicationDidFinishLaunching(_ notification: Notification) {
        SiteAccountModel.shared.startAutomaticRefresh()
    }
}
