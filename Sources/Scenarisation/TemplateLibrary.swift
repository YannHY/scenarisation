import SwiftUI
#if os(macOS)
import AppKit
#endif

struct ScenarioTemplate: Decodable, Identifiable, Sendable {
    struct Entry: Decodable, Sendable {
        let id: String
        let titleFr: String
        let summaryFr: String
        let family: String
        let familyLabelFr: String
        let keywords: String
        let minutes: Int
        let momentCount: Int
        let activityCount: Int
        let mode: String
        let placeholdersFr: [String]
    }
    let entry: Entry
    let design: Scenario
    var id: String { entry.id }
    static let bundled: Result<[ScenarioTemplate], Error> = Result {
        guard let url = Bundle.module.url(forResource: "models", withExtension: "json") else {
            throw ScenarioError.invalid("La bibliothèque de modèles est absente de l’application.")
        }
        return try JSONDecoder().decode([ScenarioTemplate].self, from: Data(contentsOf: url))
    }
}

struct WelcomeView: View {
    let create: () -> Void
    let models: () -> Void
    let open: () -> Void
    let createWithAI: () -> Void
    /// Présent sur l’accueil de l’app uniquement : ouvre le compte et les scénarios du site.
    var account: (() -> Void)? = nil
    var body: some View {
        // Un seul bloc compact, centré dans la fenêtre quelle que soit sa taille ;
        // côte à côte, ou l’un sous l’autre lorsque la largeur manque (iPad en portrait).
        ViewThatFits(in: .horizontal) {
            HStack(alignment: .center, spacing: 40) { brand.frame(width: 330); actions }.fixedSize()
            VStack(spacing: 28) { brand.frame(width: 330); actions }.fixedSize()
        }
        .padding(40)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .center)
        .ignoresSafeArea(.container, edges: .top)
#if os(macOS)
        .background(WelcomeInitialFocus().frame(width: 0, height: 0))
#endif
    }
    private var actions: some View {
        VStack(spacing: 12) {
            startRow("Créer un scénario", icon: "plus", tint: Color.accentColor,
                     description: "Partez d’une page blanche et donnez forme à votre scénario pédagogique.", perform: create)
            startRow("Importer un modèle", icon: "layers", tint: StudioStyle.color("practice"),
                     description: "Parcourez la bibliothèque, visualisez les modèles, puis adaptez celui qui vous convient.", perform: models)
            startRow("Ouvrir un scénario partagé", icon: "folder", tint: StudioStyle.color("read"),
                     description: "JSON, Markdown, Excel, CSV, Word, HTML ou LDJ.", perform: open)
            startRow("Créer avec l’IA", icon: "wand", tint: StudioStyle.color("discuss"),
                     description: "Construisez votre scénario en dialoguant avec l’assistant de votre choix.", perform: createWithAI)
            if let account {
                startRow("Mon compte et mes scénarios", icon: "account", tint: StudioStyle.color("collaborate"),
                         description: "Retrouvez sur \(Platform.thisDevice) les scénarios de votre compte scenarisation.eu.", perform: account)
            }
        }.frame(width: 400)
    }
    private var brand: some View {
        VStack(spacing: 18) {
            WelcomeIllustration().frame(width: 330, height: 238)
            VStack(spacing: 6) {
                Text("Scénarisation").font(.system(size: 36, weight: .bold))
                Text("Concevez, analysez et partagez\nvos scénarios pédagogiques.")
                    .font(.title3).foregroundStyle(.secondary).multilineTextAlignment(.center)
            }
            Text("Yann Houry & François Jourde").font(.caption).foregroundStyle(.tertiary).padding(.top, 14)
        }
    }
    private func startRow(_ title: String, icon: String, tint: Color, description: String,
                          perform: @escaping () -> Void) -> some View {
        Button(action: perform) {
            HStack(spacing: 14) {
                FA(name: icon, size: 18).foregroundStyle(.white).frame(width: 46, height: 46)
                    .background(LinearGradient(colors: [tint, tint.opacity(0.78)], startPoint: .top, endPoint: .bottom),
                                in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                    .shadow(color: tint.opacity(0.35), radius: 6, y: 3)
                VStack(alignment: .leading, spacing: 3) {
                    Text(title).font(.headline)
                    Text(description).font(.callout).foregroundStyle(.secondary)
                        .lineLimit(2).fixedSize(horizontal: false, vertical: true)
                }
                Spacer(minLength: 0)
                FA(name: "arrow", size: 11).foregroundStyle(.tertiary)
            }
            .padding(14).frame(maxWidth: .infinity, alignment: .leading)
            .glassSurface(radius: 20, interactive: true)
            .contentShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        }.buttonStyle(.plain).hoverLift()
    }
}


#if os(macOS)
/// Retirer une seule fois le focus initial, dans la fenêtre qui contient l’accueil.
/// Les contrôles conservent ensuite leur navigation et leur cadre de focus natifs.
private struct WelcomeInitialFocus: NSViewRepresentable {
    func makeNSView(context: Context) -> FocusView { FocusView() }
    func updateNSView(_ nsView: FocusView, context: Context) {}

    final class FocusView: NSView {
        private var reset = false

        override func viewDidMoveToWindow() {
            super.viewDidMoveToWindow()
            NotificationCenter.default.removeObserver(self)
            guard !reset, let window else { return }
            if window.isKeyWindow { clearInitialFocus(in: window) }
            else {
                NotificationCenter.default.addObserver(self, selector: #selector(becameKey(_:)),
                    name: NSWindow.didBecomeKeyNotification, object: window)
            }
        }

        @objc private func becameKey(_ notification: Notification) {
            guard let window = notification.object as? NSWindow else { return }
            clearInitialFocus(in: window)
        }

        private func clearInitialFocus(in window: NSWindow) {
            guard !reset else { return }
            reset = true
            NotificationCenter.default.removeObserver(self)
            // Laisser AppKit terminer la mise en place de ses premiers répondants.
            DispatchQueue.main.async { [weak self, weak window] in
                guard let self, let window, self.window === window, window.isKeyWindow else { return }
                window.makeFirstResponder(nil)
            }
        }
    }
}
#endif

struct TemplateLibrary: View {
    let use: (Scenario) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var search = ""
    @State private var family = ""
    @State private var duration = ""
    @State private var moments = ""
    @State private var mode = ""
    @State private var type = ""
    @State private var preview: ScenarioTemplate?

    private var filtersActive: Bool { ![family, duration, moments, mode, type].allSatisfy(\.isEmpty) }

    var body: some View {
        NavigationStack {
            Group {
                switch ScenarioTemplate.bundled {
                case .failure(let error):
                    ContentUnavailableView("Modèles indisponibles", systemImage: "exclamationmark.triangle",
                                           description: Text(error.localizedDescription))
                case .success(let templates):
                    if let preview { previewPage(preview) } else { gallery(templates).searchable(text: $search, prompt: "Rechercher un modèle") }
                }
            }
            .navigationTitle(preview?.entry.titleFr ?? "Modèles")
            .navigationSubtitle(preview?.entry.familyLabelFr ?? "")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } }
                if let preview {
                    ToolbarItem(placement: .navigation) {
                        Button { withAnimation(.snappy) { self.preview = nil } } label: { Label("Tous les modèles", systemImage: "chevron.left") }
                    }
                    ToolbarItem(placement: .confirmationAction) {
                        Button("Importer ce modèle") { use(preview.design) }.keyboardShortcut(.defaultAction)
                    }
                }
            }
        }
        .desktopFrame(minWidth: 820, idealWidth: 980, minHeight: 560, idealHeight: 700)
    }

    private func previewPage(_ item: ScenarioTemplate) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                Text(item.entry.summaryFr).font(.title3).foregroundStyle(.secondary).fixedSize(horizontal: false, vertical: true)
                HStack(spacing: 10) {
                    stat("\(item.entry.minutes) min", icon: "clock")
                    stat("\(item.entry.momentCount) moments", icon: "moment")
                    stat("\(item.entry.activityCount) activités", icon: "list")
                }
                TypeStrip(activities: item.design.moments.flatMap(\.activities), height: 8)
                ScenarioSummary(title: "Aperçu du scénario", scenario: item.design)
                    .padding(20).studioCard()
            }.padding(24).frame(maxWidth: 820).frame(maxWidth: .infinity)
        }
    }
    private func stat(_ text: String, icon: String) -> some View {
        Label { Text(text) } icon: { FA(name: icon, size: 11) }
            .font(.callout.weight(.medium)).padding(.horizontal, 12).padding(.vertical, 7).glassCapsule()
    }

    private func gallery(_ templates: [ScenarioTemplate]) -> some View {
        let filtered = templates.filter(matches)
        return ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                filters(templates)
                if filtered.isEmpty {
                    ContentUnavailableView("Aucun modèle", systemImage: "magnifyingglass",
                                           description: Text("Aucun modèle ne correspond à ces filtres."))
                        .padding(.top, 40)
                } else {
                    LazyVGrid(columns: [GridItem(.adaptive(minimum: 270), spacing: 16, alignment: .top)], spacing: 16) {
                        ForEach(filtered) { item in modelCard(item) }
                    }
                }
            }.padding(24)
        }
        .navigationSubtitle("\(filtered.count) modèles")
    }

    private func filters(_ templates: [ScenarioTemplate]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                chip("Famille", selection: $family,
                     options: Dictionary(grouping: templates, by: { $0.entry.family }).values.compactMap(\.first)
                        .sorted { $0.entry.familyLabelFr < $1.entry.familyLabelFr }.map { Choice($0.entry.family, $0.entry.familyLabelFr) })
                chip("Durée", selection: $duration, options: [Choice("short", "30 min ou moins"), Choice("lesson", "31 à 60 min"), Choice("long", "Plus d’une heure")])
                chip("Moments", selection: $moments, options: [Choice("few", "1 à 3"), Choice("medium", "4 à 5"), Choice("many", "6 et plus")])
                chip("Diffusion", selection: $mode, options: [Choice("onsite", "Présentiel"), Choice("online", "Distanciel"), Choice("hybrid", "Hybride")])
                chip("Apprentissage", selection: $type, options: Pedagogy.types)
                if filtersActive {
                    Button("Réinitialiser") { withAnimation(.snappy) { family = ""; duration = ""; moments = ""; mode = ""; type = "" } }
                        .buttonStyle(.borderless).foregroundStyle(.secondary)
                }
            }.padding(.vertical, 2).padding(.horizontal, 1)
        }
    }
    private func chip(_ title: String, selection: Binding<String>, options: [Choice]) -> some View {
        let current = options.first { $0.id == selection.wrappedValue }
        return Menu {
            Picker(title, selection: selection) {
                Text("Tous").tag("")
                ForEach(options) { Text($0.label).tag($0.id) }
            }.pickerStyle(.inline).labelsHidden()
        } label: {
            Text(current.map { "\(title) : \($0.label)" } ?? title)
        }
        .menuStyle(.button).studioButtons().tint(current == nil ? nil : Color.accentColor).fixedSize()
    }
    private func matches(_ item: ScenarioTemplate) -> Bool {
        let e = item.entry
        return (search.isEmpty || (e.titleFr + " " + e.summaryFr + " " + e.keywords).localizedStandardContains(search)) &&
            (family.isEmpty || family == e.family) && (mode.isEmpty || mode == e.mode) &&
            (duration.isEmpty || (duration == "short" && e.minutes <= 30) || (duration == "lesson" && (31...60).contains(e.minutes)) || (duration == "long" && e.minutes > 60)) &&
            (moments.isEmpty || (moments == "few" && e.momentCount <= 3) || (moments == "medium" && (4...5).contains(e.momentCount)) || (moments == "many" && e.momentCount >= 6)) &&
            (type.isEmpty || item.design.moments.flatMap(\.activities).contains { $0["type"] == type })
    }
    private func modelCard(_ item: ScenarioTemplate) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(item.entry.familyLabelFr).font(.caption.weight(.semibold)).foregroundStyle(.secondary).lineLimit(1)
                Spacer(minLength: 6)
                Text("\(item.entry.minutes) min").font(.caption.weight(.medium)).foregroundStyle(.secondary)
            }
            Text(item.entry.titleFr).font(.headline).lineLimit(2, reservesSpace: true)
            Text(item.entry.summaryFr).font(.callout).foregroundStyle(.secondary).lineLimit(3, reservesSpace: true)
            Spacer(minLength: 2)
            TypeStrip(activities: item.design.moments.flatMap(\.activities))
            HStack {
                Text("\(item.entry.momentCount) moments · \(item.entry.activityCount) activités")
                    .font(.caption).foregroundStyle(.tertiary)
                Spacer()
                Button("Importer") { use(item.design) }.studioButtons().controlSize(.small)
            }
        }
        .padding(16).frame(maxWidth: .infinity, minHeight: 210, alignment: .topLeading)
        .studioCard().hoverLift()
        .contentShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .onTapGesture { withAnimation(.snappy) { preview = item } }
        .accessibilityElement(children: .contain)
        .accessibilityAddTraits(.isButton)
        .accessibilityLabel(item.entry.titleFr)
        .accessibilityAction(named: "Visualiser") { preview = item }
    }
}
