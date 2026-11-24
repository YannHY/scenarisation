import SwiftUI

@main
struct ScenarisationPadApp: App {
    @Environment(\.scenePhase) private var scenePhase
    @StateObject private var library = PadLibrary.shared

    var body: some Scene {
        WindowGroup {
            PadRootView()
                .onOpenURL { url in if url.isFileURL { library.importFile(url) } }
        }
        .commands { PadCommands() }
        .onChange(of: scenePhase) { _, phase in
            switch phase {
            case .active: SiteAccountModel.shared.startAutomaticRefresh(); library.reload()
            case .inactive, .background: library.session?.saveNow()
            @unknown default: break
            }
        }
    }
}

/// Bibliothèque, puis éditeur du scénario ouvert.
struct PadRootView: View {
    @ObservedObject private var library = PadLibrary.shared

    var body: some View {
        ZStack {
            if let session = library.session {
                PadEditorScreen(session: session)
                    .id(session.id)
                    .transition(.asymmetric(insertion: .move(edge: .trailing), removal: .move(edge: .trailing)).combined(with: .opacity))
            } else {
                PadHomeView()
                    .transition(.opacity)
            }
        }
        .animation(.snappy, value: library.session?.id)
#if DEBUG
        // Captures et essais : `--open-template=N` ouvre le modèle N de la bibliothèque dans un nouveau scénario.
        .task {
            let argument = ProcessInfo.processInfo.arguments.first { $0.hasPrefix("--open-template=") }
            guard let index = argument.flatMap({ Int($0.dropFirst(16)) }), library.session == nil,
                  case .success(let templates) = ScenarioTemplate.bundled, templates.indices.contains(index) else { return }
            library.create(templates[index].design)
        }
#endif
        .alert("Scénarisation", isPresented: Binding(get: { library.message != nil }, set: { if !$0 { library.message = nil } })) {
            Button("Fermer") { library.message = nil }
        } message: { Text(library.message ?? "") }
    }
}

/// Éditeur commun au Mac (`EditorView`), relié au fichier de la bibliothèque.
private struct PadEditorScreen: View {
    @ObservedObject var session: PadSession
    var body: some View {
        EditorView(document: $session.document, fileURL: session.url)
            .overlay(alignment: .bottom) {
                if let error = session.saveError {
                    Label("Enregistrement impossible : \(error)", systemImage: "exclamationmark.triangle")
                        .font(.callout).padding(.horizontal, 14).padding(.vertical, 9)
                        .glassCapsule().padding(.bottom, 16)
                }
            }
    }
}

/// Menus de l’iPad (barre des menus d’iPadOS et raccourcis du clavier physique).
struct PadCommands: Commands {
    @FocusedValue(\.editorActions) private var actions
    var body: some Commands {
        CommandGroup(after: .newItem) {
            Button("Bibliothèque") { PadPresenter.open("welcome") }.keyboardShortcut("0", modifiers: [.command, .shift])
            Button("Mon compte et mes scénarios…") { PadPresenter.open("site-account") }
        }
        CommandGroup(after: .sidebar) {
            Button("Afficher en liste") { actions?.setLayout("list") }.keyboardShortcut("1", modifiers: .command).disabled(actions == nil)
            Button("Afficher en colonnes") { actions?.setLayout("columns") }.keyboardShortcut("2", modifiers: .command).disabled(actions == nil)
            Button("Afficher en grille") { actions?.setLayout("grid") }.keyboardShortcut("3", modifiers: .command).disabled(actions == nil)
            Button("Assistant IA") { actions?.toggleAssistant() }.keyboardShortcut("i", modifiers: [.command, .option]).disabled(actions == nil)
        }
        CommandMenu("Scénario") {
            Button("Ajouter un moment") { actions?.addMoment() }.keyboardShortcut("n", modifiers: [.command, .shift]).disabled(actions == nil)
            Button("Importer un modèle…") { actions?.openModels() }.keyboardShortcut("m", modifiers: [.command, .shift]).disabled(actions == nil)
            Button("Importer un scénario…") { actions?.importFile() }.keyboardShortcut("i", modifiers: [.command, .shift]).disabled(actions == nil)
            Button("Exporter…") { actions?.exportScenario() }.keyboardShortcut("e", modifiers: [.command, .shift]).disabled(actions == nil)
            Divider()
            Button("Rechercher sur le site…") { actions?.searchSite() }.keyboardShortcut("f", modifiers: [.command, .option]).disabled(actions == nil)
        }
        CommandGroup(replacing: .help) {
            Button("Aide de Scénarisation") { HelpCenter.shared.select(.start); PadPresenter.open("help") }
                .keyboardShortcut("?", modifiers: .command)
            Button("Licence et crédits") { PadPresenter.open("license") }
        }
    }
}
