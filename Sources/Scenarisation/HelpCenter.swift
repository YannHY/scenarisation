import SwiftUI
#if os(macOS)
import AppKit
#endif

// MARK: - Aide

enum HelpTopic: String, CaseIterable, Identifiable, Hashable {
    case start = "demarrer", learningDesign = "learning-design", moments, formatting = "mise-en-forme", latex,
         types, outcomes = "acquis", analysis = "analyse", files = "fichiers", assistant, shortcuts = "raccourcis", license = "licence",
         terms = "conditions-utilisation", privacy = "politique-confidentialite",
         learningDesignReference = "definition-learning-design", bloom, conversation = "cadre-conversationnel",
         saveShare = "sauvegarde-partage", importExport = "import-export", markdownImport = "markdown",
         createWithAI = "cli", improveWithAI = "enrichir-design-ia"
    var id: String { rawValue }
    var title: String {
        switch self {
        case .start: "Démarrer"; case .learningDesign: "Comprendre le learning design"; case .moments: "Moments et activités"
        case .formatting: "Mise en forme Markdown"; case .latex: "Formules LaTeX"; case .types: "Types d’apprentissage"
        case .outcomes: "Acquis et compétences"; case .analysis: "Analyse et chronologie"; case .files: "Enregistrer, importer, exporter"
        case .assistant: "Assistant IA"; case .shortcuts: "Raccourcis clavier"; case .license: "Licence et crédits"
        case .terms: "Conditions d’utilisation"; case .privacy: "Politique de confidentialité"
        case .learningDesignReference: "Qu’est-ce que le learning design ?"; case .bloom: "Taxonomie de Bloom"
        case .conversation: "Cadre conversationnel"; case .saveShare: "Sauvegarder, publier et réutiliser"
        case .importExport: "Formats d’import et d’export"; case .markdownImport: "Importer en Markdown"
        case .createWithAI: "Créer avec l’IA, la skill et le CLI"; case .improveWithAI: "Enrichir son scénario avec l’IA"
        }
    }
    /// Pages juridiques du site, affichées comme des documents séparés de l’aide.
    var isLegal: Bool { self == .terms || self == .privacy }
    static let mac: [HelpTopic] = [.start, .formatting, .latex, .files, .assistant, .shortcuts, .license]
    static let guide: [HelpTopic] = [.learningDesign, .moments, .types, .outcomes, .analysis, .saveShare, .importExport, .markdownImport, .createWithAI, .improveWithAI]
    static let references: [HelpTopic] = [.learningDesignReference, .bloom, .conversation]
    static var legal: [HelpTopic] { allCases.filter(\.isLegal) }

    var pageName: String {
        if Self.guide.contains(self) { return "help-site" }
        switch self {
        case .learningDesignReference: return "learning-design"
        case .bloom, .conversation, .terms, .privacy: return rawValue
        default: return "help"
        }
    }

    var anchor: String {
        switch self {
        case .learningDesign: "premiers-pas"; case .moments: "moments-activites"
        case .types: "types-apprentissage"; case .outcomes: "acquis-competences"; case .analysis: "vues-analyses"
        case .learningDesignReference, .bloom, .conversation, .terms, .privacy: "content"
        default: rawValue
        }
    }

    var symbol: String {
        switch self {
        case .start: "play.circle"; case .learningDesign: "lightbulb"; case .moments: "square.stack"; case .formatting: "textformat"
        case .latex: "sum"; case .types: "circle.hexagongrid"; case .outcomes: "target"; case .analysis: "chart.pie"
        case .files: "doc.on.doc"; case .assistant: "sparkles"; case .shortcuts: "command"; case .license: "c.circle"
        case .terms: "doc.text"; case .privacy: "hand.raised"
        case .learningDesignReference: "lightbulb"; case .bloom: "square.grid.3x2"; case .conversation: "bubble.left.and.bubble.right"
        case .saveShare: "square.and.arrow.up"; case .importExport: "arrow.left.arrow.right"; case .markdownImport: "text.document"
        case .createWithAI: "terminal"; case .improveWithAI: "text.magnifyingglass"
        }
    }
}

/// Rubrique affichée par la fenêtre d’aide, partagée par les menus et les boutons « Aide ».
@MainActor final class HelpCenter: ObservableObject {
    static let shared = HelpCenter()
    @Published var topic: HelpTopic = .start
    @Published var anchor = HelpTopic.start.anchor

    func select(_ topic: HelpTopic, anchor: String? = nil) {
        self.topic = topic
        self.anchor = anchor ?? topic.anchor
    }

    func show(_ topic: HelpTopic, _ openWindow: OpenWindowAction) {
        select(topic)
        openStudioWindow("help", openWindow)
    }

    static func resource(_ name: String) -> String {
        guard let url = Bundle.module.url(forResource: "Web", withExtension: nil)?.appendingPathComponent(name),
              let text = try? String(contentsOf: url, encoding: .utf8) else { return "" }
        return text
    }
    private static var pages: [String: String] = [:]
    static func page(_ name: String) -> String {
        if let cached = pages[name] { return cached }
        let text = resource(name + ".html")
        guard !text.isEmpty else { return "<p>Cette page est introuvable dans l’application.</p>" }
        pages[name] = text
        return text
    }
    static let html = page("help")
    static let terms = page(HelpTopic.terms.rawValue)
    static let privacy = page(HelpTopic.privacy.rawValue)

    static let style = resource("help-site.css") + resource("help.css") + resource("help-icons.css")
    static let script = resource("help.js")

    /// Les pages pédagogiques restent dans la fenêtre d’aide ; les autres liens ouvrent le navigateur.
    @discardableResult func navigate(_ url: URL) -> Bool {
        guard let destination = Self.destination(url) else { return false }
        select(destination.topic, anchor: destination.anchor)
        return true
    }

    static func destination(_ url: URL) -> (topic: HelpTopic, anchor: String)? {
        guard url.scheme == "https", url.host == "scenarisation.eu" else { return nil }
        let name = url.deletingPathExtension().lastPathComponent
        let fragment = url.fragment?.removingPercentEncoding ?? ""
        if name == "mac-help" { return (.start, HelpTopic.start.anchor) }
        if name == "help" {
            let target = fragment == "bonnes-pratiques" ? "enrichir-design-ia" : fragment
            // Une sous-section appartient au chapitre qui la contient, même sans entrée propre dans la barre latérale.
            let source = page("help-site")
            for topic in HelpTopic.guide {
                let start = "id=\"\(topic.anchor)\""
                guard let range = source.range(of: start) else { continue }
                let chapter = source[range.lowerBound...].components(separatedBy: "</article>")[0]
                if target == topic.anchor || chapter.contains("id=\"\(target)\"") {
                    return (topic, target)
                }
            }
            return (.learningDesign, HelpTopic.learningDesign.anchor)
        }
        let topic = HelpTopic.references.first { $0.pageName == name } ?? HelpTopic.legal.first { $0.pageName == name }
        guard let topic else { return nil }
        return (topic, fragment.isEmpty ? topic.anchor : fragment)
    }
}

struct HelpWindow: View {
    @ObservedObject private var center = HelpCenter.shared
    var body: some View {
        NavigationSplitView {
            List(selection: Binding(get: { center.topic }, set: { if let topic = $0 { center.select(topic) } })) {
                Section("Utiliser sur \(Platform.deviceName)") {
                    ForEach(HelpTopic.mac) { Label($0.title, systemImage: $0.symbol).tag($0) }
                }
                Section("Guide du site") {
                    ForEach(HelpTopic.guide) { Label($0.title, systemImage: $0.symbol).tag($0) }
                }
                Section("Repères pédagogiques") {
                    ForEach(HelpTopic.references) { Label($0.title, systemImage: $0.symbol).tag($0) }
                }
                Section("Informations légales") {
                    ForEach(HelpTopic.legal) { Label($0.title, systemImage: $0.symbol).tag($0) }
                }
            }
            .navigationSplitViewColumnWidth(min: 200, ideal: 230)
        } detail: {
            RichWebView(body: HelpCenter.page(center.topic.pageName), fontSize: 15, padding: 32,
                        extraStyle: HelpCenter.style, extraScript: HelpCenter.script,
                        anchor: center.anchor, onNavigate: { center.navigate($0) })
                .id(center.topic.pageName)
            .navigationTitle(center.topic.title)
        }
        .desktopFrame(minWidth: 820, minHeight: 540)
    }
}

// MARK: - Licence et crédits

struct LicenseWindow: View {
    @State private var showFullText = false
    @Environment(\.openWindow) private var openWindow
    private static let fullText: String? = Bundle.main.url(forResource: "LICENSE", withExtension: nil)
        .flatMap { try? String(contentsOf: $0, encoding: .utf8) }
    private static let katexLicense: String? = KaTeX.directory
        .flatMap { try? String(contentsOf: $0.appendingPathComponent("LICENSE"), encoding: .utf8) }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                HStack(spacing: 14) {
                    BrandMark().frame(width: 46, height: 46)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Scénarisation pour \(Platform.deviceName)").font(.title.bold())
                        Text("Version \(Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "de développement")")
                            .foregroundStyle(.secondary)
                    }
                }
                block("Crédits") {
                    Text("Inspiré de l’[UCL Learning Designer](https://www.ucl.ac.uk/learning-designer/) (UCL Knowledge Lab, UCL Institute of Education, 2013–2026). Conçu et développé par Yann Houry et François Jourde (2026), à partir du [travail de François Jourde](https://github.com/jourde/learning-designer-revised).")
                    Text("Code source du site : [github.com/YannHY/scenarisation](https://github.com/YannHY/scenarisation)")
                }
                block("Licence : CC BY-SA 4.0") {
                    Text("Sauf mention contraire, le code, les textes d’aide, les modèles génériques et les autres contenus pédagogiques originaux sont proposés sous licence [Creative Commons Attribution – Partage dans les mêmes conditions 4.0 International](https://creativecommons.org/licenses/by-sa/4.0/deed.fr). Vous pouvez les copier, les partager et les adapter, y compris à des fins commerciales, à condition :")
                    VStack(alignment: .leading, spacing: 4) {
                        ForEach(["de créditer Yann Houry et François Jourde – Scénarisation ;",
                                 "d’ajouter, dans la mesure du possible, un lien vers la ressource d’origine ;",
                                 "de mentionner la licence CC BY-SA 4.0 et d’ajouter un lien vers celle-ci ;",
                                 "d’indiquer clairement les modifications effectuées ;",
                                 "de diffuser toute adaptation sous la même licence."], id: \.self) { item in
                            Label { Text(item) } icon: { Image(systemName: "checkmark.circle").foregroundStyle(.tint) }
                        }
                    }
                    Text("« Adapté de Scénarisation, Yann Houry et François Jourde, sous licence CC BY-SA 4.0 – [lien vers la ressource d’origine]. Modifications : [description]. »")
                        .font(.callout).italic().padding(10).frame(maxWidth: .infinity, alignment: .leading)
                        .background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 9, style: .continuous))
                    if let text = Self.fullText {
                        DisclosureGroup("Texte intégral de la licence", isExpanded: $showFullText) {
                            Text(text).font(.system(size: 11, design: .monospaced)).textSelection(.enabled)
                                .frame(maxWidth: .infinity, alignment: .leading).padding(.top, 6)
                        }
                    } else {
                        Link("Lire le texte intégral de la licence", destination: URL(string: "https://creativecommons.org/licenses/by-sa/4.0/legalcode.fr")!)
                    }
                }
                block("Conditions et confidentialité") {
                    Text("Les conditions d’utilisation et la politique de confidentialité du site sont intégrées à l’aide. L’app \(Platform.deviceName) fonctionne sans compte ni serveur : vos scénarios restent dans vos fichiers, et seul l’assistant IA transmet le scénario, au service que vous choisissez.")
                    HStack(spacing: 10) {
                        Button("Conditions d’utilisation") { HelpCenter.shared.show(.terms, openWindow) }
                        Button("Politique de confidentialité") { HelpCenter.shared.show(.privacy, openWindow) }
                    }
                }
                block("Vos scénarios") {
                    Text("Les scénarios que vous créez restent sous votre responsabilité et sous la licence de votre choix ; Scénarisation ne revendique aucun droit sur ces productions. Avant de diffuser un scénario, vérifiez que vous possédez les droits nécessaires sur tout son contenu et qu’il ne contient aucune donnée personnelle ou relative à un élève.")
                }
                block("Composants et contenus tiers") {
                    Text("Ces éléments conservent leurs propres licences, qui ne sont pas modifiées par la licence CC BY-SA.")
                    VStack(alignment: .leading, spacing: 6) {
                        Text("• **KaTeX 0.16** — composition des formules, © Khan Academy et contributeurs, licence MIT.")
                        Text("• **Font Awesome Free 6.5.2** — icônes de l’aide, © Fonticons, Inc. : fontes sous SIL OFL 1.1, code sous MIT et icônes sous CC BY 4.0. Les licences sont embarquées avec les ressources de l’aide.")
                        Text("• **Référentiels de compétences** (Florimont, Socle commun, GreenComp, DigComp, CRCN, Pix, Pix IA, PER romand) — conditions et attributions de leurs éditeurs ; les liens source figurent dans les catalogues.")
                        Text("• **AI Assessment Scale 2.1** — échelle AIAS de Perkins, Furze, Roe et MacVaugh.")
                        Text("• **SF Symbols** — icônes fournies par \(Platform.deviceName == "Mac" ? "macOS" : "iPadOS").")
                    }
                    if let katex = Self.katexLicense {
                        DisclosureGroup("Licence MIT de KaTeX") {
                            Text(katex).font(.system(size: 11, design: .monospaced)).textSelection(.enabled)
                                .frame(maxWidth: .infinity, alignment: .leading).padding(.top, 6)
                        }
                    }
                }
            }
            .padding(28).frame(maxWidth: 720, alignment: .leading).frame(maxWidth: .infinity)
        }
        .desktopFrame(minWidth: 560, minHeight: 480)
    }

    private func block(_ title: String, @ViewBuilder content: () -> some View) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title).font(.title3.weight(.semibold))
            content()
        }
        .textSelection(.enabled)
    }
}

// MARK: - Menus

struct HelpCommands: Commands {
    @Environment(\.openWindow) private var openWindow
    var body: some Commands {
#if os(macOS)
        CommandGroup(replacing: .appInfo) {
            Button("À propos de Scénarisation") { AboutPanel.show() }
        }
#endif
        CommandGroup(replacing: .help) {
            Button("Aide de Scénarisation") { HelpCenter.shared.show(.start, openWindow) }
                .keyboardShortcut("?", modifiers: .command)
            Button("Comprendre le learning design") { HelpCenter.shared.show(.learningDesignReference, openWindow) }
            Button("Taxonomie de Bloom") { HelpCenter.shared.show(.bloom, openWindow) }
            Button("Mise en forme Markdown") { HelpCenter.shared.show(.formatting, openWindow) }
            Button("Formules LaTeX") { HelpCenter.shared.show(.latex, openWindow) }
            Button("Raccourcis clavier") { HelpCenter.shared.show(.shortcuts, openWindow) }
            Divider()
            Button("Licence et crédits") { openStudioWindow("license", openWindow) }
            Button("Conditions d’utilisation") { HelpCenter.shared.show(.terms, openWindow) }
            Button("Politique de confidentialité") { HelpCenter.shared.show(.privacy, openWindow) }
            Divider()
            Link("Code source de Scénarisation", destination: URL(string: "https://github.com/YannHY/scenarisation")!)
        }
    }
}

#if os(macOS)
@MainActor enum AboutPanel {
    static func show() {
        let credits = NSMutableAttributedString()
        let paragraph = NSMutableParagraphStyle()
        paragraph.alignment = .center
        let base: [NSAttributedString.Key: Any] = [.font: NSFont.systemFont(ofSize: 11), .foregroundColor: NSColor.secondaryLabelColor, .paragraphStyle: paragraph]
        func add(_ text: String, link: String? = nil) {
            var attributes = base
            if let link, let url = URL(string: link) { attributes[.link] = url }
            credits.append(NSAttributedString(string: text, attributes: attributes))
        }
        add("Outil de scénarisation pédagogique.\nInspiré de l’"); add("UCL Learning Designer", link: "https://www.ucl.ac.uk/learning-designer/")
        add(".\nConçu et développé par Yann Houry et François Jourde (2026).\nLicence "); add("CC BY-SA 4.0", link: "https://creativecommons.org/licenses/by-sa/4.0/deed.fr")
        add(" · "); add("Code source", link: "https://github.com/YannHY/scenarisation")
        add("\nFormules composées avec KaTeX (MIT).")
        NSApp.orderFrontStandardAboutPanel(options: [.credits: credits, .applicationName: "Scénarisation"])
        NSApp.activate()
    }
}
#endif
