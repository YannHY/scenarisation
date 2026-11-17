import SwiftUI
import WebKit

// Recherche sur scenarisation.eu, alignée sur le moteur du site (js/site-search.js) :
// les pages (aide, modèles, compétences, prompts, références…) viennent de l’index Pagefind publié avec le site,
// les scénarios partagés de `search_public_designs.php`. Garder les catégories et libellés alignés avec le site.

enum SiteSearchCategory: String, CaseIterable, Identifiable, Sendable {
    case all, help, models, scenarios, competencies, prompts, references, other

    var id: String { rawValue }
    var label: String {
        switch self {
        case .all: "Tout"
        case .help: "Aide"
        case .models: "Modèles"
        case .scenarios: "Scénarios partagés"
        case .competencies: "Compétences"
        case .prompts: "Prompts"
        case .references: "Références"
        case .other: "Autres pages"
        }
    }
    /// Pages du site rattachées à la catégorie.
    var files: [String] {
        switch self {
        case .all: []
        case .help: ["help.php"]
        case .models: ["models.php"]
        case .scenarios: ["view.php", "share.php"]
        case .competencies: ["competencies.php"]
        case .prompts: ["prompts.php"]
        case .references: ["bloom.php", "cadre-conversationnel.php", "learning-design.php", "competencies.php"]
        case .other: ["index.php", "about.php", "news.php", "conditions-utilisation.php", "mentions-legales.php",
                      "politique-confidentialite.php"]
        }
    }
    var searchesPages: Bool { self != .scenarios }
    var searchesScenarios: Bool { self == .all || self == .scenarios }
}

/// Une page trouvée par Pagefind, avec ses sections.
struct SiteSearchPage: Decodable, Equatable, Sendable {
    struct Section: Decodable, Equatable, Sendable {
        var url: String
        var title: String
        var excerpt: String
        var anchor: Bool
    }
    var url: String
    var title: String
    var excerpt: String
    var sections: [Section]
    /// Libellé imposé par un filtre (« AIAS » pour les sections de l’aide classées en références).
    var label: String? = nil

    private enum CodingKeys: String, CodingKey { case url, title, excerpt, sections }
}

/// Réponse de `search_public_designs.php`.
struct PublicScenarioSearch: Decodable, Equatable, Sendable {
    struct Item: Decodable, Equatable, Sendable {
        struct Meta: Decodable, Equatable, Sendable { var title: String? }
        var url: String
        var meta: Meta?
        var excerpt: String?
    }
    var results: [Item]
    var count: Int
}

struct SiteSearchResult: Identifiable, Equatable, Sendable {
    let id = UUID()
    var category: String
    var title: String
    /// Extrait HTML tel que renvoyé par le site (termes trouvés entre balises `<mark>`).
    var excerpt: String
    var url: URL
}

enum SiteSearch {
    static let site = URL(string: "https://scenarisation.eu/")!
    static let pageSize = 12
    static let categoryLabels: [String: String] = [
        "view.php": "Scénarios partagés", "share.php": "Scénarios partagés", "index.php": "Accueil",
        "about.php": "À propos", "news.php": "Nouveautés", "bloom.php": "Taxonomie de Bloom",
        "cadre-conversationnel.php": "Cadre conversationnel", "competencies.php": "Compétences", "help.php": "Aide",
        "learning-design.php": "Learning design", "conditions-utilisation.php": "Conditions et licences",
        "mentions-legales.php": "Informations légales", "models.php": "Modèles",
        "politique-confidentialite.php": "Confidentialité", "prompts.php": "Prompts"]

    static func filename(_ url: String) -> String {
        URL(string: url, relativeTo: site)?.absoluteURL.path.split(separator: "/").last.map(String.init) ?? "index.php"
    }
    static func categoryLabel(_ url: String) -> String { categoryLabels[filename(url)] ?? "Scénarisation" }

    /// Applique le filtre du site : une page hors catégorie n’est gardée que pour les sections AIAS de l’aide,
    /// rangées dans « Références ».
    static func filter(_ page: SiteSearchPage, category: SiteSearchCategory) -> SiteSearchPage? {
        let name = filename(page.url)
        if category == .all || category.files.contains(name) { return page }
        guard category == .references, name == "help.php" else { return nil }
        func isAIAS(_ title: String, _ excerpt: String) -> Bool {
            (title + " " + stripTags(excerpt)).range(of: #"\bAIAS\b|AI Assessment Scale"#,
                                                      options: [.regularExpression, .caseInsensitive]) != nil
        }
        let sections = page.sections.filter { isAIAS($0.title, $0.excerpt) }
        guard !sections.isEmpty || isAIAS(page.title, page.excerpt) else { return nil }
        var kept = page
        kept.sections = sections
        kept.label = "AIAS"
        return kept
    }

    /// Mots normalisés comme sur le site : sans accents, en minuscules, d’au moins deux caractères.
    static func normalizedWords(_ value: String) -> [String] {
        value.folding(options: [.diacriticInsensitive, .caseInsensitive], locale: Locale(identifier: "fr"))
            .split { !($0.isLetter || $0.isNumber) }
            .map(String.init).filter { $0.count >= 2 }
    }

    /// Section dont le titre contient le plus de termes cherchés, à défaut la première.
    static func bestSection(_ page: SiteSearchPage, query: String) -> SiteSearchPage.Section? {
        let words = normalizedWords(query)
        var best: SiteSearchPage.Section?
        var bestScore = 0
        for section in page.sections {
            let title = Set(normalizedWords(section.title))
            let score = words.filter(title.contains).count
            if score > bestScore { best = section; bestScore = score }
        }
        return best ?? page.sections.first
    }

    static func result(_ page: SiteSearchPage, query: String) -> SiteSearchResult? {
        let section = bestSection(page, query: query)
        let anchored = section.map { $0.anchor || $0.url.contains("#") } ?? false
        let destination = section?.url.isEmpty == false ? section!.url : page.url
        guard let url = resolve(destination, highlight: query) else { return nil }
        let title = anchored && section?.title.isEmpty == false ? section!.title : page.title
        return SiteSearchResult(category: page.label ?? categoryLabel(page.url),
                                title: title.isEmpty ? "Page sans titre" : title,
                                excerpt: section?.excerpt ?? page.excerpt, url: url)
    }

    static func result(_ item: PublicScenarioSearch.Item, query: String) -> SiteSearchResult? {
        guard let url = resolve(item.url, highlight: query) else { return nil }
        let title = item.meta?.title ?? ""
        return SiteSearchResult(category: categoryLabel(item.url), title: title.isEmpty ? "Page sans titre" : title,
                                excerpt: item.excerpt ?? "", url: url)
    }

    /// Adresse absolue sur le site, avec le paramètre `highlight` qui surligne les termes sur la page ouverte.
    /// Tout lien sortant du site est écarté.
    static func resolve(_ link: String, highlight: String) -> URL? {
        guard let absolute = URL(string: link, relativeTo: site)?.absoluteURL,
              absolute.scheme == site.scheme, absolute.host == site.host,
              var components = URLComponents(url: absolute, resolvingAgainstBaseURL: false) else { return nil }
        components.queryItems = (components.queryItems ?? []).filter { $0.name != "highlight" }
            + [URLQueryItem(name: "highlight", value: highlight)]
        return components.url
    }

    // MARK: Extraits

    static func stripTags(_ html: String) -> String {
        html.replacingOccurrences(of: "<[^>]*>", with: "", options: .regularExpression)
    }

    /// Extrait lisible : entités décodées, balises retirées, liens et gras Markdown simplifiés,
    /// termes trouvés (`<mark>`) mis en évidence.
    static func excerpt(_ html: String) -> AttributedString {
        let cleaned = html
            .replacingOccurrences(of: #"\[([^\]]*)\]\([^)\s]*\)?"#, with: "$1", options: .regularExpression)
            .replacingOccurrences(of: "**", with: "")
            .replacingOccurrences(of: "\n", with: " ")
        var output = AttributedString()
        var highlighted = false
        var rest = Substring(cleaned)
        func append(_ text: Substring) {
            guard !text.isEmpty else { return }
            var run = AttributedString(decodeEntities(String(text)))
            if highlighted {
                run.inlinePresentationIntent = .stronglyEmphasized
                run.foregroundColor = .primary
            }
            output += run
        }
        while let open = rest.firstIndex(of: "<") {
            append(rest[..<open])
            guard let close = rest[open...].firstIndex(of: ">") else { rest = rest[open...]; break }
            let tag = rest[rest.index(after: open)..<close].trimmingCharacters(in: .whitespaces).lowercased()
            if tag == "mark" || tag.hasPrefix("mark ") { highlighted = true } else if tag == "/mark" { highlighted = false }
            rest = rest[rest.index(after: close)...]
        }
        append(rest)
        return output
    }

    static func decodeEntities(_ text: String) -> String {
        guard text.contains("&") else { return text }
        let named = ["amp": "&", "lt": "<", "gt": ">", "quot": "\"", "apos": "'", "nbsp": "\u{00A0}", "hellip": "…"]
        var output = ""
        var rest = Substring(text)
        while let amp = rest.firstIndex(of: "&") {
            output += rest[..<amp]
            let after = rest.index(after: amp)
            if let semi = rest[after...].prefix(10).firstIndex(of: ";") {
                let name = rest[after..<semi]
                var decoded: String?
                if name.hasPrefix("#x") || name.hasPrefix("#X") {
                    decoded = UInt32(name.dropFirst(2), radix: 16).flatMap(Unicode.Scalar.init).map { String(Character($0)) }
                } else if name.hasPrefix("#") {
                    decoded = UInt32(name.dropFirst()).flatMap(Unicode.Scalar.init).map { String(Character($0)) }
                } else {
                    decoded = named[String(name)]
                }
                if let decoded {
                    output += decoded
                    rest = rest[rest.index(after: semi)...]
                    continue
                }
            }
            output += "&"
            rest = rest[after...]
        }
        return output + rest
    }
}

// MARK: - Accès au site

/// Interroge le site. Pagefind n’existe qu’en JavaScript (WebAssembly) : une vue web invisible,
/// placée à l’origine du site et sans données de navigation persistantes, charge son index.
@MainActor
final class SiteSearchEngine: NSObject, WKNavigationDelegate {
    static let shared = SiteSearchEngine()

    enum Failure: LocalizedError {
        case unavailable
        var errorDescription: String? { "La recherche du site est indisponible pour le moment." }
    }

    private var webView: WKWebView?
    private var loading: [CheckedContinuation<Void, Error>] = []
    private var loaded = false
    private let session: URLSession = {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.timeoutIntervalForRequest = 15
        configuration.httpCookieStorage = nil
        configuration.urlCache = nil
        return URLSession(configuration: configuration)
    }()

    func scenarios(_ query: String, offset: Int) async throws -> PublicScenarioSearch {
        var components = URLComponents(url: SiteSearch.site.appendingPathComponent("search_public_designs.php"),
                                       resolvingAgainstBaseURL: false)!
        components.queryItems = [URLQueryItem(name: "q", value: query), URLQueryItem(name: "offset", value: String(offset))]
        var request = URLRequest(url: components.url!, cachePolicy: .reloadIgnoringLocalCacheData)
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse, http.statusCode == 200,
              http.value(forHTTPHeaderField: "Content-Type")?.lowercased().contains("json") == true else {
            throw Failure.unavailable
        }
        return try JSONDecoder().decode(PublicScenarioSearch.self, from: data)
    }

    func pages(_ query: String) async throws -> [SiteSearchPage] {
        try await prepare()
        guard let webView else { throw Failure.unavailable }
        let script = """
        if (!window.__siteSearch) {
          window.__siteSearch = import(new URL("pagefind/fr/pagefind.js", document.baseURI).href).then(async (pagefind) => {
            await pagefind.options({ excerptLength: 24 });
            await pagefind.init();
            return pagefind;
          });
          window.__siteSearch.catch(() => { window.__siteSearch = null; });
        }
        const pagefind = await window.__siteSearch;
        const search = await pagefind.search(query);
        const pages = await Promise.all(search.results.map((result) => result.data()));
        return JSON.stringify(pages.map((page) => ({
          url: String(page.url || ""),
          title: String((page.meta && page.meta.title) || ""),
          excerpt: String(page.excerpt || ""),
          sections: (Array.isArray(page.sub_results) ? page.sub_results : []).map((section) => ({
            url: String(section.url || ""),
            title: String(section.title || ""),
            excerpt: String(section.excerpt || ""),
            anchor: Boolean(section.anchor) || String(section.url || "").includes("#")
          }))
        })));
        """
        let value = try await webView.callAsyncJavaScript(script, arguments: ["query": query], contentWorld: .page)
        guard let json = value as? String, let data = json.data(using: .utf8) else { throw Failure.unavailable }
        return try JSONDecoder().decode([SiteSearchPage].self, from: data)
    }

    private func prepare() async throws {
        if loaded { return }
        if webView == nil {
            let configuration = WKWebViewConfiguration()
            configuration.websiteDataStore = .nonPersistent()
            let view = WKWebView(frame: .zero, configuration: configuration)
            view.navigationDelegate = self
            webView = view
            view.loadHTMLString("<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\"></head><body></body></html>",
                                baseURL: SiteSearch.site)
        }
        try await withCheckedThrowingContinuation { loading.append($0) }
    }

    private func finishLoading(_ error: Error?) {
        if error == nil { loaded = true } else { webView = nil }
        let waiting = loading
        loading = []
        for continuation in waiting {
            if let error { continuation.resume(throwing: error) } else { continuation.resume() }
        }
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) { finishLoading(nil) }
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) { finishLoading(error) }
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        finishLoading(error)
    }
    /// Seule la page vide initiale est chargée ; aucune autre navigation n’est suivie.
    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                 decisionHandler: @escaping @MainActor (WKNavigationActionPolicy) -> Void) {
        let url = action.request.url
        let allowed = action.targetFrame?.isMainFrame == true
            && url?.host == SiteSearch.site.host && url?.path == SiteSearch.site.path
        decisionHandler(allowed ? .allow : .cancel)
    }
}

// MARK: - État de la recherche

@MainActor
final class SiteSearchModel: ObservableObject {
    enum Status: Equatable {
        case invitation, searching
        case done(count: Int, partial: Bool)
    }

    @Published var query = "" { didSet { if oldValue != query { schedule() } } }
    @Published var category = SiteSearchCategory.all { didSet { if oldValue != category { schedule() } } }
    @Published private(set) var results: [SiteSearchResult] = []
    @Published private(set) var status = Status.invitation
    @Published private(set) var canLoadMore = false
    @Published private(set) var loadingMore = false

    private let engine: SiteSearchEngine
    private var pages: [SiteSearchResult] = []
    private var scenarioCount = 0
    private var nextOffset = 0
    private var task: Task<Void, Never>?

    init(engine: SiteSearchEngine = .shared) { self.engine = engine }

    /// La recherche remplace le contenu de la barre latérale dès qu’un texte est saisi.
    var isActive: Bool { !query.isEmpty }
    var trimmedQuery: String { String(query.trimmingCharacters(in: .whitespacesAndNewlines).prefix(200)) }

    var statusText: String {
        switch status {
        case .invitation: return "Saisissez au moins deux caractères pour lancer la recherche."
        case .searching: return "Recherche en cours…"
        case let .done(count, partial):
            let partialNote = "Une partie de la recherche est indisponible ; les résultats disponibles sont affichés."
            if count == 0 { return partial ? partialNote : "Aucun résultat pour « \(trimmedQuery) »." }
            let summary = count == 1 ? "1 résultat" : "\(count.formatted()) résultats"
            return partial ? summary + " — " + partialNote : summary
        }
    }

    func clear() { query = "" }

    func loadMore() {
        guard canLoadMore, !loadingMore else { return }
        let query = trimmedQuery, category = category, offset = nextOffset
        loadingMore = true
        task = Task { await run(query, category: category, offset: offset) }
    }

    private func schedule() {
        task?.cancel()
        results = []
        pages = []
        canLoadMore = false
        loadingMore = false
        let query = trimmedQuery, category = category
        guard query.count >= 2 else { status = .invitation; return }
        status = .searching
        task = Task {
            try? await Task.sleep(for: .milliseconds(180))
            guard !Task.isCancelled else { return }
            await run(query, category: category, offset: 0)
        }
    }

    private func run(_ query: String, category: SiteSearchCategory, offset: Int) async {
        let engine = engine
        var partial = false
        if offset == 0 && category.searchesPages {
            do {
                pages = try await engine.pages(query)
                    .compactMap { SiteSearch.filter($0, category: category) }
                    .compactMap { SiteSearch.result($0, query: query) }
            } catch { partial = true; pages = [] }
        }
        guard !Task.isCancelled else { return }
        var scenarios: [SiteSearchResult] = []
        if category.searchesScenarios {
            do {
                let found = try await engine.scenarios(query, offset: offset)
                scenarioCount = found.count
                scenarios = found.results.compactMap { SiteSearch.result($0, query: query) }
            } catch { partial = true; if offset == 0 { scenarioCount = 0 } }
        } else { scenarioCount = 0 }
        guard !Task.isCancelled else { return }
        let slice = pages.dropFirst(offset).prefix(SiteSearch.pageSize)
        results += scenarios + slice
        nextOffset = offset + SiteSearch.pageSize
        canLoadMore = nextOffset < max(pages.count, scenarioCount)
        loadingMore = false
        if offset == 0 || partial { status = .done(count: pages.count + scenarioCount, partial: partial) }
    }
}
