import AppKit
import Testing
import WebKit
@testable import Scenarisation

@Suite("Aide complète et navigation locale") @MainActor
struct HelpCenterTests {
    @Test func everyTopicHasABundledDestination() {
        let groups = HelpTopic.mac + HelpTopic.guide + HelpTopic.references + HelpTopic.legal
        #expect(Set(groups) == Set(HelpTopic.allCases))
        #expect(groups.count == Set(groups).count)
        for topic in groups {
            let html = HelpCenter.page(topic.pageName)
            #expect(!html.contains("Cette page est introuvable"))
            if topic.anchor != "content" { #expect(html.contains("id=\"\(topic.anchor)\"")) }
        }
        #expect(HelpTopic.guide.count == 10)
        #expect(HelpCenter.page("help-site").contains("id=\"markdown-libelles\""))
        #expect(HelpCenter.page("help-site").contains("id=\"cli-latex\""))
    }

    @Test func relatedPagesAndSubsectionsStayInTheHelpWindow() throws {
        let cases: [(String, HelpTopic, String)] = [
            ("learning-design.php", .learningDesignReference, "content"),
            ("bloom.php", .bloom, "content"),
            ("cadre-conversationnel.php#six-types", .conversation, "six-types"),
            ("help.php#taxonomie-bloom", .outcomes, "taxonomie-bloom"),
            ("help.php#markdown-structure", .markdownImport, "markdown-structure"),
            ("help.php#cli-latex", .createWithAI, "cli-latex"),
            ("help.php#bonnes-pratiques", .improveWithAI, "enrichir-design-ia"),
            ("conditions-utilisation.php#licences-reutilisation", .terms, "licences-reutilisation")
        ]
        for (path, topic, anchor) in cases {
            let route = try #require(HelpCenter.destination(URL(string: "https://scenarisation.eu/" + path)!))
            #expect(route.topic == topic)
            #expect(route.anchor == anchor)
        }
        #expect(HelpCenter.destination(URL(string: "https://www.ucl.ac.uk/learning-designer/")!) == nil)
        #expect(HelpCenter.destination(URL(string: "https://scenarisation.eu/models.php")!) == nil)
    }

    @Test func pedagogicalPagesContainTheirFullOfflineResources() {
        let bloom = HelpCenter.page("bloom")
        #expect(bloom.components(separatedBy: "class=\"bloom-verb\"").count - 1 == 80)
        #expect(HelpCenter.page("learning-design").contains("L'idée centrale"))
        let conversation = HelpCenter.page("cadre-conversationnel")
        #expect(conversation.components(separatedBy: "data:image/svg+xml;base64,").count - 1 == 9)
        for name in Set(HelpTopic.allCases.map(\.pageName)) {
            let html = HelpCenter.page(name)
            #expect(!html.contains("<script"))
            #expect(!html.contains("<iframe"))
            #expect(!html.contains("src=\"https://"))
        }
        #expect(HelpCenter.style.contains("data:font/woff2;base64,"))
        #expect(!HelpCenter.style.contains("@import"))
        #expect(!HelpCenter.resource("font-awesome-LICENSE.txt").isEmpty)
    }

    @Test(.timeLimit(.minutes(1))) func webKitPreservesCardsNumberingAndChapterNavigation() async throws {
        let page = HelpPageHarness(width: 1100)
        try await page.load("help-site")
        let result = try await page.inspect("""
        await document.fonts.ready;
        const cards = [...document.querySelectorAll('#premiers-pas .help-card')];
        const chapter = document.querySelector('#premiers-pas details');
        chapter.open = false;
        reveal('creer-premier-design');
        return {
          count: cards.length,
          columns: cards[1].offsetLeft > cards[0].offsetLeft,
          numbering: document.querySelector('#premiers-pas h3').textContent.trim().startsWith('01.1'),
          expanded: chapter.open,
          overflow: document.documentElement.scrollWidth > innerWidth,
          fonts: document.fonts.check('900 14px "Font Awesome 6 Free"')
        };
        """)
        #expect(result["count"] as? Int == 4)
        #expect(result["columns"] as? Bool == true)
        #expect(result["numbering"] as? Bool == true)
        #expect(result["expanded"] as? Bool == true)
        #expect(result["overflow"] as? Bool == false)
        #expect(result["fonts"] as? Bool == true)
        #expect(page.networkRequests.isEmpty)
        _ = try await page.inspect("scrollTo(0, document.getElementById('premiers-pas').offsetTop); return {};")
        try await page.snapshot("guide-large")
    }

    @Test(.timeLimit(.minutes(1)), arguments: ["help-site", "learning-design", "bloom", "cadre-conversationnel"])
    func narrowWindowDoesNotOverflow(_ name: String) async throws {
        let page = HelpPageHarness(width: 560)
        try await page.load(name)
        let result = try await page.inspect("""
        await document.fonts.ready;
        await Promise.all([...document.images].map(image => { image.loading = 'eager'; return image.decode(); }));
        return {overflow: document.documentElement.scrollWidth > innerWidth,
                images: [...document.images].every(image => image.complete && image.naturalWidth > 0)};
        """)
        #expect(result["overflow"] as? Bool == false)
        #expect(result["images"] as? Bool == true)
        #expect(page.networkRequests.isEmpty)
    }

    @Test(.timeLimit(.minutes(1)), arguments: [false, true])
    func bloomStaysLegibleInBothAppearances(_ dark: Bool) async throws {
        let page = HelpPageHarness(width: 1100)
        page.web.appearance = NSAppearance(named: dark ? .darkAqua : .aqua)
        try await page.load("bloom")
        let result = try await page.inspect("""
        await document.fonts.ready;
        return {dark: matchMedia('(prefers-color-scheme: dark)').matches,
                levels: document.querySelectorAll('.bloom-level').length,
                columns: getComputedStyle(document.querySelector('.bloom-grid')).gridTemplateColumns.split(' ').length,
                titleColor: getComputedStyle(document.querySelector('.bloom-level h2')).color};
        """)
        #expect(result["dark"] as? Bool == dark)
        #expect(result["levels"] as? Int == 6)
        #expect(result["columns"] as? Int == 3)
        #expect(result["titleColor"] as? String == "rgb(45, 36, 82)")
        try await page.snapshot(dark ? "bloom-sombre" : "bloom-clair")
    }
}

@MainActor private final class HelpPageHarness: NSObject, WKNavigationDelegate {
    let web: WKWebView
    var networkRequests: [URL] = []
    private var pending: CheckedContinuation<Void, any Error>?

    init(width: CGFloat) {
        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .nonPersistent()
        web = WKWebView(frame: NSRect(x: 0, y: 0, width: width, height: 900), configuration: configuration)
        super.init()
        web.navigationDelegate = self
    }

    func load(_ name: String) async throws {
        let html = RichPage.document(body: HelpCenter.page(name), fontSize: 15, padding: 32,
                                     extraStyle: HelpCenter.style, extraScript: HelpCenter.script)
        try await withCheckedThrowingContinuation { continuation in
            pending = continuation
            web.loadHTMLString(html, baseURL: nil)
        }
    }

    func inspect(_ script: String) async throws -> [String: Any] {
        let value = try await web.callAsyncJavaScript(script, arguments: [:], in: nil, contentWorld: .page)
        return try #require(value as? [String: Any])
    }

    func snapshot(_ name: String) async throws {
        let image = try await web.takeSnapshot(configuration: nil)
        let data = try #require(image.tiffRepresentation)
        let bitmap = try #require(NSBitmapImageRep(data: data))
        let png = try #require(bitmap.representation(using: .png, properties: [:]))
        let directory = URL(fileURLWithPath: FileManager.default.currentDirectoryPath).appendingPathComponent(".build/help-previews")
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        try png.write(to: directory.appendingPathComponent(name + ".png"))
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        pending?.resume()
        pending = nil
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: any Error) {
        pending?.resume(throwing: error)
        pending = nil
    }

    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                 decisionHandler: @escaping @MainActor (WKNavigationActionPolicy) -> Void) {
        if let url = action.request.url, ["https", "http"].contains(url.scheme) {
            networkRequests.append(url)
            decisionHandler(.cancel)
        } else { decisionHandler(.allow) }
    }
}
