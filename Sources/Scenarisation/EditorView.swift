import SwiftUI
#if os(macOS)
import AppKit
#endif
import UniformTypeIdentifiers

@MainActor
final class DocumentUndo: ObservableObject {
    func replace(_ value: Scenario, document: Binding<ScenarioDocument>, manager: UndoManager?) {
        let previous = document.wrappedValue.scenario
        guard previous != value else { return }
        manager?.registerUndo(withTarget: self) { [weak manager] target in
            MainActor.assumeIsolated { target.replace(previous, document: document, manager: manager) }
        }
        document.wrappedValue.scenario = value
    }
}

private struct UploadReadiness: Hashable {
    let pending: Bool
    let connected: Bool
    let hasContent: Bool
}

enum StudioPane: Hashable {
    case board, settings, analysis, chronology, assistant
}

extension ScenarioDocument {
    var initialPane: StudioPane {
        if startWithAI { return .assistant }
        return scenario.moments.isEmpty && scenario.name.isEmpty ? .settings : .board
    }
}

struct EditorView: View {
    @Binding var document: ScenarioDocument
    /// Emplacement du document : absent tant qu’il est sans titre.
    let fileURL: URL?
    @Environment(\.undoManager) private var undoManager
    @Environment(\.dismissWindow) private var dismissWindow
    @Environment(\.openWindow) private var openWindow
    @StateObject private var undo = DocumentUndo()
    @StateObject private var assistant = AssistantModel()
    @StateObject private var siteSearch = SiteSearchModel()
    @FocusState private var siteSearchFocused: Bool
    @ObservedObject private var siteAccount = SiteAccountModel.shared
    @State private var started: Bool
    @State private var pane: StudioPane
    @State private var showAssistant = false
    @State private var showModels = false
    @State private var showExport = false
    @State private var showImport = false
    @State private var showSync = false
    @State private var message: String?
    @State private var pendingReplacement: Scenario?
    @State private var focusMoment: String?
    @State private var deleteMoment: String?
    @State private var adopted = false
    @State private var syncCheckpoint: Scenario

    init(document: Binding<ScenarioDocument>, fileURL: URL? = nil) {
        _document = document
        self.fileURL = fileURL
        _syncCheckpoint = State(initialValue: document.wrappedValue.scenario)
        _started = State(initialValue: document.wrappedValue.enteredEditor)
        let arguments = ProcessInfo.processInfo.arguments
        let requested = ["board": StudioPane.board, "settings": .settings, "analysis": .analysis, "chronology": .chronology]
        let override = arguments.first { $0.hasPrefix("--pane=") }.flatMap { requested[String($0.dropFirst(7))] }
        _pane = State(initialValue: override ?? document.wrappedValue.initialPane)
        _showAssistant = State(initialValue: arguments.contains("--assistant"))
        _showModels = State(initialValue: arguments.contains("--models"))
        _showExport = State(initialValue: arguments.contains("--export"))
    }
    private var scenario: Binding<Scenario> {
        Binding(get: { document.scenario }, set: { undo.replace($0, document: $document, manager: undoManager) })
    }
    private var layout: String { document.scenario.meta["boardLayout"]?.string ?? "columns" }
    private var intentionsVisible: Bool { document.scenario.fields["intentionsCollapsed"] != .bool(true) }

    var body: some View {
        Group {
            if started {
                studio
            } else {
                WelcomeView(create: { document.enteredEditor = true; started = true; pane = .settings },
                            models: { showModels = true }, open: { showImport = true }, createWithAI: {
                                document.enteredEditor = true; document.startWithAI = true
                                started = true; pane = .assistant; showAssistant = false
                            })
                    .background(StudioStyle.canvas)
            }
        }
        .environment(\.fieldAIScenario, document.scenario)
        .focusedSceneValue(\.editorActions, EditorActions(
            addMoment: addMoment, openModels: { showModels = true }, importFile: { showImport = true },
            exportScenario: { if started { showExport = true } }, toggleAssistant: {
                if started { if pane == .assistant { pane = .board } else { showAssistant.toggle() } }
            },
            setLayout: { mode in if started { pane = .board; scenario.meta["boardLayout"].wrappedValue = .string(mode) } },
            searchSite: { if started { siteSearchFocused = true } }))
        .sheet(isPresented: $showModels) {
            TemplateLibrary { design in
                showModels = false
                replaceOrConfirm(design)
            }
        }
        .sheet(isPresented: $showExport) { ScenarioExportView(scenario: document.scenario) }
        .sheet(isPresented: $showSync) {
            if let workspace = siteAccount.workspace {
                ScenarioSyncSheet(document: $document, workspace: workspace, accountModel: siteAccount)
            }
        }
        .fileImporter(isPresented: $showImport, allowedContentTypes: ScenarioImporter.contentTypes) { result in
            do {
                let url = try result.get()
                let access = url.startAccessingSecurityScopedResource()
                defer { if access { url.stopAccessingSecurityScopedResource() } }
                replaceOrConfirm(try ScenarioImporter.read(url))
            } catch { message = error.localizedDescription }
        }
        .confirmationDialog("Remplacer le scénario ouvert ?", isPresented: Binding(
            get: { pendingReplacement != nil }, set: { if !$0 { pendingReplacement = nil } })) {
                Button("Remplacer") {
                    if let next = pendingReplacement { scenario.wrappedValue = next; document.enteredEditor = true; started = true; pane = .board }
                    pendingReplacement = nil
                }
            } message: { Text("Vous pourrez revenir à la version précédente avec Édition → Annuler.") }
        .alert("Scénarisation", isPresented: Binding(get: { message != nil }, set: { if !$0 { message = nil } })) {
            Button("Fermer") { message = nil }
        } message: { Text(message ?? "") }
        .onDisappear { assistant.cancel() }
        .onAppear { if let fileURL, fileURL.pathExtension.lowercased() != "json" { adoptIntoFolder() } }
        .onChange(of: started) { _, entered in if entered && fileURL == nil { adoptIntoFolder() } }
        .onChange(of: pane) { _, value in if value == .assistant { showAssistant = false } }
        .task(id: UploadReadiness(pending: document.pendingUpload && document.syncIdentity == nil,
                                  connected: siteAccount.networkAvailable,
                                  hasContent: ScenarioFolder.hasContent(document.scenario))) {
            linkNewScenarioToAccount()
        }
        .background {
            if let workspace = siteAccount.workspace, document.syncIdentity?.accountKey == workspace.account.key {
                DocumentSyncObserver(document: $document, checkpoint: $syncCheckpoint, workspace: workspace) { message = $0 }
            }
        }
    }

    // MARK: Fenêtre principale

    private var studio: some View {
        NavigationSplitView {
            sidebar.navigationSplitViewColumnWidth(min: 210, ideal: 250, max: 340)
        } detail: {
            detail
                .background(StudioStyle.canvas)
                .navigationSubtitle("\(document.scenario.duration.formatted()) min · \(document.scenario.activityCount) activités")
#if !os(macOS)
                // Sur iPad, la barre d’outils appartient à la colonne de détail.
                .navigationTitle(document.scenario.name.isEmpty ? "Sans titre" : document.scenario.name)
                .navigationBarTitleDisplayMode(.inline)
                .toolbar { toolbarContent }
#endif
        }
        .inspector(isPresented: $showAssistant) {
            AssistantView(model: assistant, scenario: scenario)
                .inspectorColumnWidth(min: 320, ideal: 370, max: 520)
        }
#if os(macOS)
        .toolbar { toolbarContent }
#endif
        .onAppear {
            // Évite que le titre du scénario soit sélectionné d’office à l’ouverture.
            Task { @MainActor in
                try? await Task.sleep(for: .milliseconds(250))
                Platform.clearInitialFocus()
            }
#if os(macOS)
            // À l’ouverture depuis le Finder, l’accueil est présenté après le document : on le referme.
            Task { @MainActor in
                try? await Task.sleep(for: .milliseconds(350))
                dismissWindow(id: "welcome")
            }
#endif
        }
    }

    private var sidebar: some View {
        List(selection: Binding<StudioPane?>(get: { pane }, set: { if let value = $0 { withAnimation(.snappy) { pane = value } } })) {
            if siteSearch.isActive {
                SiteSearchResults(model: siteSearch)
            } else {
                studioSections
            }
        }
        .listStyle(.sidebar)
        .safeAreaInset(edge: .top, spacing: 0) { SiteSearchBar(model: siteSearch, focused: $siteSearchFocused) }
        .confirmationDialog("Supprimer ce moment et ses activités ?", isPresented: Binding(
            get: { deleteMoment != nil }, set: { if !$0 { deleteMoment = nil } })) {
                Button("Supprimer", role: .destructive) {
                    scenario.wrappedValue.moments.removeAll { $0.id == deleteMoment }; deleteMoment = nil
                }
            }
        .safeAreaInset(edge: .bottom) {
            if !siteSearch.isActive {
                Button(action: addMoment) { Label("Ajouter un moment", systemImage: "plus").frame(maxWidth: .infinity) }
                    .studioButtons().controlSize(.large).padding(12)
            }
        }
    }

    @ViewBuilder private var studioSections: some View {
        Section("Scénario") {
            Label("Conception", systemImage: StudioStyle.symbols["board"]!).tag(StudioPane.board)
            Label("Paramètres", systemImage: StudioStyle.symbols["settings"]!).tag(StudioPane.settings)
            Label("Analyse", systemImage: StudioStyle.symbols["analysis"]!).tag(StudioPane.analysis)
            Label("Chronologie", systemImage: StudioStyle.symbols["chronology"]!).tag(StudioPane.chronology)
            Label("Assistant IA", systemImage: StudioStyle.symbols["wand"]!).tag(StudioPane.assistant)
        }
        Section("Moments") {
            if document.scenario.moments.isEmpty {
                Text("Aucun moment").foregroundStyle(.secondary).font(.callout)
            }
            ForEach(Array(document.scenario.moments.enumerated()), id: \.element.id) { index, moment in
                Button { reveal(moment.id) } label: { momentRow(index, moment) }
                    .buttonStyle(.plain)
                    .contextMenu {
                        Button("Afficher") { reveal(moment.id) }
                        Divider()
                        Button("Supprimer le moment…", role: .destructive) { deleteMoment = moment.id }
                    }
            }
        }
    }

    private func momentRow(_ index: Int, _ moment: Moment) -> some View {
        let dominant = Dictionary(grouping: moment.activities, by: { $0["type"] })
            .max { $0.value.reduce(0) { $0 + $1.duration } < $1.value.reduce(0) { $0 + $1.duration } }?.key ?? "undefined"
        return HStack(spacing: 9) {
            Text("\(index + 1)").font(.caption.weight(.bold)).foregroundStyle(.white)
                .frame(width: 22, height: 22)
                .background(StudioStyle.color(dominant), in: RoundedRectangle(cornerRadius: 6, style: .continuous))
            Text(moment["title"].isEmpty ? "Sans titre" : moment["title"]).lineLimit(1)
            Spacer(minLength: 4)
            Text("\(moment.duration.formatted()) min").font(.caption).foregroundStyle(.secondary)
        }.contentShape(Rectangle())
    }

    @ViewBuilder private var detail: some View {
        switch pane {
        case .board:
            ScrollViewReader { proxy in
                ScrollView {
                    VStack(alignment: .leading, spacing: 22) {
                        boardHeader
                        ScenarioBoard(scenario: scenario, focusMoment: $focusMoment,
                                      askAI: { showAssistant = true }, openModels: { showModels = true })
                    }.padding(.top, 22).padding(.bottom, 36)
                }
                .onChange(of: focusMoment) { _, id in
                    guard let id else { return }
                    withAnimation(.smooth) { proxy.scrollTo("moment-" + id, anchor: .top) }
                    Task { @MainActor in try? await Task.sleep(for: .milliseconds(500)); focusMoment = nil }
                }
            }
        case .settings:
            ParametersView(scenario: scenario)
        case .analysis:
            page { LearningAnalysis(scenario: document.scenario) }
        case .chronology:
            page { ChronologyView(scenario: scenario) { id in reveal(id) } }
        case .assistant:
            AssistantView(model: assistant, scenario: scenario, creationMode: document.startWithAI, onApplied: {
                document.startWithAI = false; pane = .board
            })
            .frame(maxWidth: 860)
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        }
    }

    private func page<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView { content().padding(28).frame(maxWidth: 1000).frame(maxWidth: .infinity) }
    }

    private var boardHeader: some View {
        VStack(alignment: .leading, spacing: 10) {
            TextField("Nouveau scénario", text: Binding(get: { document.scenario.name },
                set: { scenario.meta["name"].wrappedValue = .string($0) }))
                .textFieldStyle(.plain).font(.system(size: 30, weight: .bold)).lineLimit(1)
            HStack(spacing: 18) {
                Label("\(document.scenario.duration.formatted()) min", systemImage: "clock")
                Label("\(document.scenario.moments.count) moments", systemImage: "square.stack")
                Label("\(document.scenario.activityCount) activités", systemImage: "list.bullet")
            }.font(.callout).foregroundStyle(.secondary)
            if document.scenario.activityCount > 0 {
                TypeStrip(activities: document.scenario.moments.flatMap(\.activities), height: 7).frame(maxWidth: 360)
            }
        }.padding(.horizontal, 28)
    }

    @ToolbarContentBuilder private var toolbarContent: some ToolbarContent {
        ToolbarItem(placement: .navigation) {
            Button { openStudioWindow("welcome", openWindow) } label: { Label("Accueil", systemImage: "house") }
                .help("Revenir à l’accueil : créer, importer, ouvrir ou créer avec l’IA (⇧⌘0)")
        }
#if !os(macOS)
        // Sur Mac, Édition → Annuler ; sur iPad, des boutons en plus du clavier et des gestes à trois doigts.
        ToolbarItemGroup(placement: .navigation) {
            Button { undoManager?.undo() } label: { Label("Annuler", systemImage: "arrow.uturn.backward") }
            Button { undoManager?.redo() } label: { Label("Rétablir", systemImage: "arrow.uturn.forward") }
        }
#endif
        if pane == .board {
            ToolbarItem(placement: .primaryAction) {
                Picker("Disposition", selection: Binding(get: { layout }, set: { scenario.meta["boardLayout"].wrappedValue = .string($0) })) {
                    Label("Liste", systemImage: "list.bullet").tag("list").help("Moments en liste (⌘1)")
                    Label("Colonnes", systemImage: "rectangle.split.3x1").tag("columns").help("Moments en colonnes (⌘2)")
                    Label("Grille", systemImage: "square.grid.2x2").tag("grid").help("Moments en grille (⌘3)")
                }.pickerStyle(.segmented).labelStyle(.iconOnly).help("Disposition des moments : liste (⌘1), colonnes (⌘2), grille (⌘3)")
            }
            ToolbarItem(placement: .primaryAction) {
                Toggle(isOn: Binding(get: { intentionsVisible }, set: { scenario.fields["intentionsCollapsed"].wrappedValue = .bool(!$0) })) {
                    Label("Intentions", systemImage: "eye")
                }.help("Afficher les intentions pédagogiques")
            }
            if #available(macOS 26, *) { ToolbarSpacer(.fixed, placement: .primaryAction) }
        }
        ToolbarItemGroup(placement: .primaryAction) {
            Button { showModels = true } label: { Label("Modèles", systemImage: "square.stack.3d.up") }
                .help("Importer un modèle (⇧⌘M)")
            Button { showImport = true } label: { Label("Importer", systemImage: "square.and.arrow.down") }
                .help("Importer un scénario JSON, Markdown, CSV, Excel, HTML, Word ou LDJ")
        }
        if #available(macOS 26, *) { ToolbarSpacer(.fixed, placement: .primaryAction) }
        ToolbarItem(placement: .primaryAction) {
            if pane == .assistant {
                Button { pane = .board } label: { Label("Voir le scénario", systemImage: StudioStyle.symbols["board"]!) }
            } else {
                Toggle(isOn: $showAssistant) { Label("Assistant IA", systemImage: "sparkles") }
                    .help("Afficher l’assistant IA (⌥⌘I)")
            }
        }
        ToolbarItem(placement: .primaryAction) {
            Button { showExport = true } label: { Label("Exporter", systemImage: "square.and.arrow.up") }
                .help("Exporter le scénario (⇧⌘E)")
        }
        ToolbarItem(placement: .primaryAction) {
            Button {
                if let workspace = siteAccount.workspace {
                    if let identity = document.syncIdentity, identity.accountKey != workspace.account.key {
                        message = SiteSyncError.wrongAccount.localizedDescription
                    } else { showSync = true }
                } else { openStudioWindow("site-account", openWindow) }
            } label: {
                if let workspace = siteAccount.workspace {
                    DocumentSyncLabel(identity: document.syncIdentity, workspace: workspace)
                } else {
                    Label("Mon compte", systemImage: "person.crop.circle")
                }
            }.help("Compte et synchronisation du scénario")
        }
    }

    // MARK: Actions

    private func addMoment() {
        guard started else { return }
        let moment = Moment.blank(title: "Moment \(document.scenario.moments.count + 1)")
        scenario.wrappedValue.moments.append(moment)
        reveal(moment.id)
    }
    private func reveal(_ id: String) {
        if pane == .board { focusMoment = id; return }
        withAnimation(.snappy) { pane = .board }
        Task { @MainActor in
            try? await Task.sleep(for: .milliseconds(80))
            focusMoment = id
        }
    }
    /// Un document sans titre ou ouvert depuis un autre format devient un fichier JSON du dossier des scénarios,
    /// que macOS enregistre ensuite en continu. Le fichier d’origine n’est pas modifié.
    private func adoptIntoFolder() {
#if os(macOS)
        guard !adopted else { return }
        adopted = true
        let documents = NSDocumentController.shared.documents
        let untitled = documents.filter { $0.fileURL == nil }
        let window = NSApp.keyWindow?.sheetParent ?? NSApp.keyWindow
        let original = fileURL.flatMap { url in documents.first { $0.fileURL == url } }
            ?? (untitled.count == 1 ? untitled.first : window?.windowController?.document as? NSDocument)
        Task { @MainActor in
            guard let folder = ScenarioFolder.current ?? ScenarioFolder.choose() else { return }
            do {
                let url = try ScenarioFolder.save(document.scenario, startWithAI: document.startWithAI, in: folder)
                _ = try await NSDocumentController.shared.openDocument(withContentsOf: url, display: true)
                if let original, original.fileURL == fileURL { original.close() }
            } catch { message = error.localizedDescription }
        }
#endif
    }
    /// Un nouveau scénario rejoint le compte dès qu’il a un contenu et qu’une connexion existe ;
    /// la synchronisation automatique le crée ensuite sur le site.
    private func linkNewScenarioToAccount() {
        guard started, document.pendingUpload, document.syncIdentity == nil, fileURL != nil,
              siteAccount.networkAvailable, let workspace = siteAccount.workspace,
              ScenarioFolder.hasContent(document.scenario) else { return }
        do {
            let identity = try workspace.addLocal(document.scenario)
            syncCheckpoint = document.scenario
            document.syncIdentity = identity
            document.pendingUpload = false
        } catch { message = error.localizedDescription }
    }
    private func replaceOrConfirm(_ next: Scenario) {
        if document.scenario.moments.isEmpty && (!started || document.scenario == .blank()) {
            scenario.wrappedValue = next; document.enteredEditor = true; started = true; pane = .board
        } else { pendingReplacement = next }
    }
}
