import SwiftUI

/// Accueil de l’iPad : les quatre entrées de l’accueil Mac et la bibliothèque des scénarios.
struct PadHomeView: View {
    @ObservedObject private var library = PadLibrary.shared
    @ObservedObject private var account = SiteAccountModel.shared
    @State private var showModels = false
    @State private var showImport = false
    @State private var search = ""
    @State private var pendingDelete: PadLibraryItem?

    private var filtered: [PadLibraryItem] {
        search.isEmpty ? library.items : library.items.filter { $0.title.localizedStandardContains(search) }
    }

    var body: some View {
        NavigationStack {
            Group {
                if library.items.isEmpty {
                    GeometryReader { geometry in
                        ScrollView { welcome.frame(minHeight: geometry.size.height) }
                    }
                } else {
                    ScrollView {
                        VStack(alignment: .leading, spacing: 28) {
                            actions
                            VStack(alignment: .leading, spacing: 14) {
                                Text("Mes scénarios").font(.title2.weight(.semibold))
                                if filtered.isEmpty {
                                    ContentUnavailableView.search(text: search).padding(.top, 30)
                                } else {
                                    LazyVGrid(columns: [GridItem(.adaptive(minimum: 260), spacing: 16, alignment: .top)], spacing: 16) {
                                        ForEach(filtered) { card($0) }
                                    }
                                }
                            }
                        }
                        .padding(24)
                    }
                    .searchable(text: $search, prompt: "Rechercher un scénario")
                }
            }
            .background(StudioStyle.canvas)
            // L’accueil vide porte déjà le nom de l’app : le titre n’apparaît qu’avec la bibliothèque.
            .navigationTitle(library.items.isEmpty ? "" : "Scénarisation")
            .toolbar { toolbar }
            .refreshable { library.reload() }
        }
        .onAppear { library.reload() }
        .sheet(isPresented: $showModels) {
            TemplateLibrary { scenario in showModels = false; library.create(scenario) }
        }
        .fileImporter(isPresented: $showImport, allowedContentTypes: ScenarioImporter.contentTypes) { result in
            switch result {
            case .success(let url): library.importFile(url)
            case .failure(let error): library.message = error.localizedDescription
            }
        }
        .confirmationDialog("Supprimer ce scénario de l’iPad ?", isPresented: Binding(
            get: { pendingDelete != nil }, set: { if !$0 { pendingDelete = nil } }), titleVisibility: .visible) {
                Button("Supprimer", role: .destructive) { if let item = pendingDelete { library.delete(item) }; pendingDelete = nil }
            } message: {
                Text(pendingDelete?.linked == true
                     ? "Le fichier est supprimé de l’iPad. Le scénario reste sur votre compte scenarisation.eu."
                     : "Le fichier est définitivement supprimé de l’iPad.")
            }
    }

    // MARK: Entrées

    private var welcome: some View {
        WelcomeView(create: createBlank, models: { showModels = true }, open: { showImport = true },
                    createWithAI: { library.create(.blank(), startWithAI: true) },
                    account: { PadPresenter.open("site-account") })
    }

    private var actions: some View {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 220), spacing: 12)], spacing: 12) {
            tile("Créer un scénario", icon: "plus", tint: .accentColor, perform: createBlank)
            tile("Importer un modèle", icon: "layers", tint: StudioStyle.color("practice")) { showModels = true }
            tile("Ouvrir un fichier", icon: "folder", tint: StudioStyle.color("read")) { showImport = true }
            tile("Créer avec l’IA", icon: "wand", tint: StudioStyle.color("discuss")) { library.create(.blank(), startWithAI: true) }
        }
    }

    private func tile(_ title: String, icon: String, tint: Color, perform: @escaping () -> Void) -> some View {
        Button(action: perform) {
            HStack(spacing: 12) {
                FA(name: icon, size: 16).foregroundStyle(.white).frame(width: 40, height: 40)
                    .background(LinearGradient(colors: [tint, tint.opacity(0.78)], startPoint: .top, endPoint: .bottom),
                                in: RoundedRectangle(cornerRadius: 11, style: .continuous))
                Text(title).font(.headline).foregroundStyle(.primary).lineLimit(1)
                Spacer(minLength: 0)
            }
            .padding(12).frame(maxWidth: .infinity, alignment: .leading)
            .glassSurface(radius: 18, interactive: true)
            .contentShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
        }
        .buttonStyle(.plain).hoverLift()
    }

    private func card(_ item: PadLibraryItem) -> some View {
        Button { library.open(item.url) } label: {
            VStack(alignment: .leading, spacing: 10) {
                HStack {
                    Text(item.modified.formatted(.relative(presentation: .named)))
                        .font(.caption.weight(.medium)).foregroundStyle(.secondary)
                    Spacer()
                    if item.linked {
                        Image(systemName: "icloud").font(.caption).foregroundStyle(.secondary)
                            .accessibilityLabel("Lié à votre compte")
                    }
                }
                Text(item.title).font(.headline).foregroundStyle(.primary).lineLimit(2, reservesSpace: true)
                    .multilineTextAlignment(.leading)
                Spacer(minLength: 2)
                TypeStrip(activities: item.activities)
                Text("\(item.minutes.formatted()) min · \(item.moments) moments · \(item.activities.count) activités")
                    .font(.caption).foregroundStyle(.tertiary)
            }
            .padding(16).frame(maxWidth: .infinity, minHeight: 150, alignment: .topLeading)
            .studioCard()
            .contentShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        }
        .buttonStyle(.plain).hoverLift()
        .contextMenu {
            Button { library.open(item.url) } label: { Label("Ouvrir", systemImage: "doc.text") }
            Button { library.duplicate(item) } label: { Label("Dupliquer", systemImage: "plus.square.on.square") }
            ShareLink(item: item.url) { Label("Partager le fichier JSON", systemImage: "square.and.arrow.up") }
            Divider()
            Button(role: .destructive) { pendingDelete = item } label: { Label("Supprimer…", systemImage: "trash") }
        }
    }

    @ToolbarContentBuilder private var toolbar: some ToolbarContent {
        ToolbarItemGroup(placement: .primaryAction) {
            Button { PadPresenter.open("site-account") } label: {
                Label(account.user == nil ? "Mon compte" : "Mes scénarios en ligne", systemImage: "person.crop.circle")
            }
            Button { PadPresenter.open("settings") } label: { Label("Réglages de l’assistant", systemImage: "gearshape") }
            Button { HelpCenter.shared.select(.start); PadPresenter.open("help") } label: { Label("Aide", systemImage: "questionmark.circle") }
        }
    }

    private func createBlank() {
        var scenario = Scenario.blank()
        scenario.fields["topPanelCollapsed"] = .bool(false)
        library.create(scenario)
    }
}
