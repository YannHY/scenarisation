import SwiftUI
#if os(macOS)
import AppKit
#endif
import WebKit

// MARK: - Markdown léger → HTML

/// Rendu Markdown du site (titres, gras, italique, listes, citations, liens) ; les formules
/// `$…$` et `$$…$$` sont protégées puis composées par KaTeX dans la vue web.
enum RichText {
    static func escape(_ value: String) -> String {
        value.replacingOccurrences(of: "&", with: "&amp;").replacingOccurrences(of: "<", with: "&lt;")
            .replacingOccurrences(of: ">", with: "&gt;").replacingOccurrences(of: "\"", with: "&quot;")
    }

    static func containsMath(_ text: String) -> Bool {
        text.range(of: #"\$[^$\n]+\$"#, options: .regularExpression) != nil || text.contains("$$")
    }

    private static let mathSpan = try! NSRegularExpression(pattern: #"\$\$[^$]+?\$\$|\$[^$\n]+?\$"#)
    private static let bold = try! NSRegularExpression(pattern: #"\*\*([^*\n]+)\*\*"#)
    private static let italic = try! NSRegularExpression(pattern: #"(^|[^*])\*([^*\n]+)\*"#)
    private static let code = try! NSRegularExpression(pattern: #"`([^`\n]+)`"#)
    private static let headingPattern = try! NSRegularExpression(pattern: #"^(#{1,6})\s+(.+)$"#)
    private static let bulletPattern = try! NSRegularExpression(pattern: #"^[-*+]\s+(.+)$"#)
    private static let numberPattern = try! NSRegularExpression(pattern: #"^\d+[.)]\s+(.+)$"#)
    private static let quotePattern = try! NSRegularExpression(pattern: #"^>\s?(.*)$"#)
    private static let link = try! NSRegularExpression(pattern: #"\[([^\]\n]+)\]\(((?:https?://|mailto:)[^\s)<]+)\)"#, options: .caseInsensitive)

    private static func replace(_ regex: NSRegularExpression, in text: String, with template: String) -> String {
        regex.stringByReplacingMatches(in: text, range: NSRange(text.startIndex..., in: text), withTemplate: template)
    }

    /// Mise en forme d’une ligne : les formules sont mises de côté pour que `*` ou `_` n’y soient pas interprétés.
    static func inline(_ text: String) -> String {
        var formulas: [String] = []
        let ns = text as NSString
        var protected = ""
        var cursor = 0
        for match in mathSpan.matches(in: text, range: NSRange(location: 0, length: ns.length)) {
            protected += ns.substring(with: NSRange(location: cursor, length: match.range.location - cursor))
            protected += "\u{E000}\(formulas.count)\u{E001}"
            formulas.append(ns.substring(with: match.range))
            cursor = match.range.location + match.range.length
        }
        protected += ns.substring(from: cursor)
        var html = escape(protected)
        html = replace(code, in: html, with: "<code>$1</code>")
        html = replace(bold, in: html, with: "<strong>$1</strong>")
        html = replace(italic, in: html, with: "$1<em>$2</em>")
        html = replace(link, in: html, with: "<a href=\"$2\">$1</a>")
        for (index, formula) in formulas.enumerated() {
            html = html.replacingOccurrences(of: "\u{E000}\(index)\u{E001}", with: escape(formula))
        }
        return html
    }

    /// Bloc d’un texte Markdown, tel que le site le découpe. Le HTML et l’aperçu natif partent de ce découpage.
    enum Block: Equatable {
        /// Lignes consécutives, séparées par de simples retours à la ligne.
        case paragraph([String])
        case heading(level: Int, text: String)
        case list(ordered: Bool, items: [String])
        case quote(String)
        /// Formule `$$ … $$` sur ses propres lignes ; `closed` est faux si le `$$` final manque.
        case math(String, closed: Bool)
    }

    static func blocks(_ markdown: String) -> [Block] {
        var blocks: [Block] = []
        var paragraph: [String] = []
        var list: (ordered: Bool, items: [String])?
        var mathBlock: [String]?
        func closeParagraph() { if !paragraph.isEmpty { blocks.append(.paragraph(paragraph)); paragraph = [] } }
        func closeList() { if let current = list { blocks.append(.list(ordered: current.ordered, items: current.items)); list = nil } }
        func add(_ item: String, ordered: Bool) {
            closeParagraph()
            if list?.ordered != ordered { closeList(); list = (ordered, []) }
            list!.items.append(item)
        }
        func match(_ regex: NSRegularExpression, _ line: String) -> [String]? {
            guard let result = regex.firstMatch(in: line, range: NSRange(line.startIndex..., in: line)) else { return nil }
            return (0..<result.numberOfRanges).map { Range(result.range(at: $0), in: line).map { String(line[$0]) } ?? "" }
        }
        for line in markdown.replacingOccurrences(of: "\r\n", with: "\n").components(separatedBy: "\n") {
            let trimmed = line.trimmingCharacters(in: .whitespaces)
            if mathBlock != nil {
                if trimmed == "$$" {
                    blocks.append(.math(mathBlock!.joined(separator: "\n"), closed: true))
                    mathBlock = nil
                } else { mathBlock!.append(line) }
                continue
            }
            if trimmed == "$$" { closeParagraph(); closeList(); mathBlock = []; continue }
            if trimmed.isEmpty { closeParagraph(); closeList(); continue }
            if let heading = match(headingPattern, trimmed) {
                closeParagraph(); closeList()
                blocks.append(.heading(level: max(2, min(4, heading[1].count)), text: heading[2]))
            } else if let item = match(bulletPattern, trimmed) {
                add(item[1], ordered: false)
            } else if let item = match(numberPattern, trimmed) {
                add(item[1], ordered: true)
            } else if let quote = match(quotePattern, trimmed) {
                closeParagraph(); closeList(); blocks.append(.quote(quote[1]))
            } else {
                closeList(); paragraph.append(line)
            }
        }
        closeParagraph(); closeList()
        if let mathBlock { blocks.append(.math(mathBlock.joined(separator: "\n"), closed: false)) }
        return blocks
    }

    static func html(_ markdown: String) -> String {
        var html: [String] = []
        for block in blocks(markdown) {
            switch block {
            case .paragraph(let lines):
                html.append("<p>" + lines.map(inline).joined(separator: "<br>") + "</p>")
            case .heading(let level, let text):
                html.append("<h\(level)>\(inline(text))</h\(level)>")
            case .list(let ordered, let items):
                let tag = ordered ? "ol" : "ul"
                html.append("<\(tag)>")
                html += items.map { "<li>\(inline($0))</li>" }
                html.append("</\(tag)>")
            case .quote(let text):
                html.append("<blockquote>\(inline(text))</blockquote>")
            case .math(let formula, closed: true):
                html.append("<div class=\"math-block\">$$\n" + escape(formula) + "\n$$</div>")
            case .math(let formula, closed: false):
                html.append("<p>$$<br>" + escape(formula).replacingOccurrences(of: "\n", with: "<br>") + "</p>")
            }
        }
        return html.joined(separator: "\n")
    }

    /// Mise en forme d’une ligne sans formule, pour l’aperçu natif : mêmes règles que `inline`
    /// (code, gras, italique, liens http, https ou mailto), sans HTML.
    static func attributed(_ text: String) -> AttributedString {
        let ns = text as NSString
        let whole = NSRange(location: 0, length: ns.length)
        // À position égale, le code l’emporte, puis le lien, le gras et l’italique.
        let candidates: [(NSRegularExpression, Int)] = [(code, 0), (link, 1), (bold, 2), (italicSpan, 3)]
        var best: (match: NSTextCheckingResult, kind: Int)?
        for (regex, kind) in candidates {
            guard let found = regex.firstMatch(in: text, range: whole) else { continue }
            if best == nil || found.range.location < best!.match.range.location { best = (found, kind) }
        }
        guard let (found, kind) = best else { return AttributedString(text) }
        var result = AttributedString(ns.substring(to: found.range.location))
        let inner = ns.substring(with: found.range(at: 1))
        switch kind {
        case 0:
            var part = AttributedString(inner); part.inlinePresentationIntent = .code; result += part
        case 1:
            var part = attributed(inner)
            part.link = URL(string: ns.substring(with: found.range(at: 2)))
            result += part
        default:
            var part = attributed(inner)
            let intent: InlinePresentationIntent = kind == 2 ? .stronglyEmphasized : .emphasized
            for run in part.runs {
                part[run.range].inlinePresentationIntent = (run.inlinePresentationIntent ?? []).union(intent)
            }
            result += part
        }
        result += attributed(ns.substring(from: found.range.location + found.range.length))
        return result
    }
    /// Italique sans le caractère qui précède (contrairement à `italic`, conçu pour un remplacement).
    private static let italicSpan = try! NSRegularExpression(pattern: #"(?<!\*)\*([^*\n]+)\*"#)

    /// Segment de texte mis en forme, pour les exports qui ne passent pas par le HTML (Word).
    struct Span: Equatable {
        var text: String
        var bold = false
        var italic = false
        var code = false
        /// Formule LaTeX, sans ses délimiteurs `$`.
        var math = false
        var link: String?
    }

    /// Découpe une ligne selon les mêmes règles que `inline` et `attributed`.
    static func spans(_ text: String) -> [Span] {
        let ns = text as NSString
        var result: [Span] = []
        var cursor = 0
        for match in mathSpan.matches(in: text, range: NSRange(location: 0, length: ns.length)) {
            result += styledSpans(ns.substring(with: NSRange(location: cursor, length: match.range.location - cursor)))
            let formula = ns.substring(with: match.range)
            let delimiter = formula.hasPrefix("$$") ? 2 : 1
            result.append(Span(text: String(formula.dropFirst(delimiter).dropLast(delimiter)), math: true))
            cursor = match.range.location + match.range.length
        }
        result += styledSpans(ns.substring(from: cursor))
        return result.filter { !$0.text.isEmpty }
    }

    private static func styledSpans(_ text: String) -> [Span] {
        guard !text.isEmpty else { return [] }
        let ns = text as NSString
        let whole = NSRange(location: 0, length: ns.length)
        let candidates: [(NSRegularExpression, Int)] = [(code, 0), (link, 1), (bold, 2), (italicSpan, 3)]
        var best: (match: NSTextCheckingResult, kind: Int)?
        for (regex, kind) in candidates {
            guard let found = regex.firstMatch(in: text, range: whole) else { continue }
            if best == nil || found.range.location < best!.match.range.location { best = (found, kind) }
        }
        guard let (found, kind) = best else { return [Span(text: text)] }
        var result = [Span(text: ns.substring(to: found.range.location))]
        let inner = ns.substring(with: found.range(at: 1))
        switch kind {
        case 0: result.append(Span(text: inner, code: true))
        case 1:
            let url = ns.substring(with: found.range(at: 2))
            result += styledSpans(inner).map { var span = $0; span.link = url; return span }
        default:
            result += styledSpans(inner).map { var span = $0; if kind == 2 { span.bold = true } else { span.italic = true }; return span }
        }
        return result + styledSpans(ns.substring(from: found.range.location + found.range.length))
    }
}

// MARK: - KaTeX embarqué

/// KaTeX 0.16 (MIT), embarqué pour composer les formules sans connexion.
/// Les polices sont intégrées en data URI : la page fonctionne sans accès aux fichiers.
enum KaTeX {
    static let directory: URL? = Bundle.module.url(forResource: "Web", withExtension: nil)?.appendingPathComponent("katex")

    private static func read(_ name: String) -> String {
        guard let url = directory?.appendingPathComponent(name) else { return "" }
        return (try? String(contentsOf: url, encoding: .utf8)) ?? ""
    }

    static let css: String = {
        let source = read("katex.min.css")
        guard let fonts = directory?.appendingPathComponent("fonts"),
              let regex = try? NSRegularExpression(pattern: #"src:url\(fonts/([A-Za-z0-9_-]+)\.woff2\)[^;}]*"#) else { return source }
        let ns = source as NSString
        var output = ""
        var cursor = 0
        for match in regex.matches(in: source, range: NSRange(location: 0, length: ns.length)) {
            output += ns.substring(with: NSRange(location: cursor, length: match.range.location - cursor))
            let name = ns.substring(with: match.range(at: 1))
            if let data = try? Data(contentsOf: fonts.appendingPathComponent(name + ".woff2")) {
                output += "src:url(data:font/woff2;base64,\(data.base64EncodedString())) format(\"woff2\")"
            } else {
                output += ns.substring(with: match.range)
            }
            cursor = match.range.location + match.range.length
        }
        return output + ns.substring(from: cursor)
    }()

    static let script: String = read("katex.min.js") + "\n" + read("auto-render.min.js")

    static let renderCall = """
    function renderMath(root){ if (window.renderMathInElement) renderMathInElement(root, {delimiters:[{left:"$$",right:"$$",display:true},{left:"$",right:"$",display:false}],throwOnError:false,strict:"ignore",trust:false}); }
    """

    /// Bloc à placer dans `<head>` d’une page autonome (export HTML, aide).
    static var head: String { "<style>\(css)</style><script>\(script)\n\(renderCall)</script>" }
}

// MARK: - Page web locale

enum RichPage {
    static let style = """
    :root{color-scheme:light dark}
    html,body{margin:0;padding:0;background:transparent}
    body{font:-apple-system-body;font-size:var(--size,14px);line-height:1.55;color:CanvasText;-webkit-font-smoothing:antialiased;word-wrap:break-word;padding:var(--pad,0)}
    body>*:first-child,#content>*:first-child{margin-top:0}body>*:last-child,#content>*:last-child{margin-bottom:0}
    body{display:flow-root}
    p{margin:0 0 .7em}h1,h2,h3,h4{line-height:1.25;margin:1.1em 0 .45em}h2{font-size:1.35em}h3{font-size:1.15em}h4{font-size:1em}
    ul,ol{margin:0 0 .7em;padding-left:1.4em}li{margin:.15em 0}
    blockquote{margin:0 0 .7em;padding:.1em .9em;border-left:3px solid color-mix(in srgb,CanvasText 22%,transparent);color:color-mix(in srgb,CanvasText 72%,transparent)}
    a{color:-apple-system-blue;text-decoration:none}a:hover{text-decoration:underline}
    code{font:.92em ui-monospace,SFMono-Regular,Menlo,monospace;background:color-mix(in srgb,CanvasText 8%,transparent);padding:.05em .3em;border-radius:4px}
    .math-block{margin:.6em 0;overflow-x:auto}.katex-display{margin:.4em 0}
    .empty{color:color-mix(in srgb,CanvasText 45%,transparent)}
    table{border-collapse:collapse;width:100%;margin:.4em 0 1em;font-size:.95em}
    th,td{text-align:left;vertical-align:top;padding:.45em .6em;border-bottom:1px solid color-mix(in srgb,CanvasText 12%,transparent)}
    th{font-weight:600;color:color-mix(in srgb,CanvasText 70%,transparent)}
    kbd{font:600 .85em -apple-system;border:1px solid color-mix(in srgb,CanvasText 20%,transparent);border-bottom-width:2px;border-radius:5px;padding:0 .35em;white-space:nowrap}
    """

    /// Une page sans formule n’embarque pas KaTeX (près de 700 Ko de script et de polices) :
    /// les nombreux aperçus d’un scénario se chargent ainsi beaucoup plus vite.
    static func needsMath(_ body: String) -> Bool { body.contains("$") }

    static func document(body: String, fontSize: CGFloat, padding: CGFloat, extraStyle: String = "", extraScript: String = "",
                         math: Bool = true) -> String {
        """
        <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">\(math ? KaTeX.head : "<script>\(KaTeX.renderCall)</script>")
        <style>\(style)\(extraStyle)</style></head>
        <body style="--size:\(Int(fontSize))px;--pad:\(Int(padding))px"><main id="content">\(body)</main>
        <script>
        function report(){ try { window.webkit.messageHandlers.size.postMessage(Math.ceil(document.body.getBoundingClientRect().height)); } catch(e){} }
        function setContent(html){ const c=document.getElementById('content'); c.innerHTML=html; document.dispatchEvent(new Event('rich-content-updated')); renderMath(c); report(); }
        function reveal(id){ const el=document.getElementById(id); if(!el) return; let p=el; while(p){ if(p.tagName==='DETAILS') p.open=true; p=p.parentElement; } const chapter=el.querySelector(':scope > details'); if(chapter) chapter.open=true; el.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'}); }
        renderMath(document.getElementById('content'));
        new ResizeObserver(report).observe(document.body); report();
        </script><script>\(extraScript)</script></body></html>
        """
    }
}

/// Vue WebKit qui affiche du HTML local (Markdown rendu, formules KaTeX, aide).
/// Les liens externes s’ouvrent dans le navigateur par défaut.
struct RichWebView {
    var body: String
    var fontSize: CGFloat = 14
    var padding: CGFloat = 0
    var extraStyle = ""
    var extraScript = ""
    /// Hauteur du contenu, pour les aperçus qui s’ajustent à leur texte.
    var contentHeight: Binding<CGFloat>? = nil
    /// Ancre vers laquelle faire défiler (aide).
    var anchor: String? = nil
    /// Renvoie true lorsqu’un lien est pris en charge dans l’application (aide locale).
    var onNavigate: ((URL) -> Bool)? = nil

    @MainActor func makeCoordinator() -> Coordinator { Coordinator() }

    @MainActor func makeWebView(_ coordinator: Coordinator) -> WKWebView {
        let configuration = WKWebViewConfiguration()
        configuration.userContentController.add(WeakScriptHandler(coordinator), name: "size")
#if os(macOS)
        // Les aperçus sont inertes à la souris (voir PassiveWebView) ; seule l’aide reste interactive.
        let view = contentHeight != nil ? PassiveWebView(frame: .zero, configuration: configuration)
            : WKWebView(frame: .zero, configuration: configuration)
#else
        let view = WKWebView(frame: .zero, configuration: configuration)
#endif
#if os(macOS)
        view.setValue(false, forKey: "drawsBackground")
        view.allowsMagnification = false
#else
        view.isOpaque = false
        view.backgroundColor = .clear
        view.scrollView.backgroundColor = .clear
        // Les aperçus prennent la hauteur de leur contenu : seule l’aide fait défiler sa page.
        view.scrollView.isScrollEnabled = contentHeight == nil
        view.scrollView.bounces = contentHeight == nil
#endif
        view.navigationDelegate = coordinator
        coordinator.parent = self
        coordinator.load(into: view)
        return view
    }

    @MainActor func updateWebView(_ view: WKWebView, coordinator: Coordinator) {
        coordinator.parent = self
        if coordinator.loadedStyle != "\(fontSize)|\(padding)|\(extraStyle)|\(extraScript)"
            || (!coordinator.loadedMath && RichPage.needsMath(body)) {
            coordinator.load(into: view)
        } else if coordinator.shownBody != body {
            if coordinator.ready {
                coordinator.shownBody = body
                view.evaluateJavaScript("setContent(\(Coordinator.jsString(body)))")
            }
        }
        if let anchor, anchor != coordinator.shownAnchor, coordinator.ready {
            coordinator.shownAnchor = anchor
            view.evaluateJavaScript("reveal(\(Coordinator.jsString(anchor)))")
        }
    }

    @MainActor private static func dismantleWebView(_ view: WKWebView) {
        view.configuration.userContentController.removeScriptMessageHandler(forName: "size")
    }

    @MainActor final class Coordinator: NSObject, WKNavigationDelegate, WKScriptMessageHandler {
        var parent: RichWebView?
        var shownBody = ""
        var shownAnchor: String?
        var loadedStyle = ""
        var loadedMath = false
        var ready = false

        static func jsString(_ value: String) -> String {
            let data = (try? JSONEncoder().encode([value])) ?? Data("[\"\"]".utf8)
            let array = String(decoding: data, as: UTF8.self)
            return String(array.dropFirst().dropLast())
        }

        func load(into view: WKWebView) {
            guard let parent else { return }
            ready = false
            shownBody = parent.body
            shownAnchor = nil
            loadedStyle = "\(parent.fontSize)|\(parent.padding)|\(parent.extraStyle)|\(parent.extraScript)"
            // L’aide garde KaTeX en permanence ; un aperçu ne le charge que s’il contient une formule.
            loadedMath = parent.contentHeight == nil || RichPage.needsMath(parent.body)
            view.loadHTMLString(RichPage.document(body: parent.body, fontSize: parent.fontSize, padding: parent.padding,
                                                  extraStyle: parent.extraStyle, extraScript: parent.extraScript,
                                                  math: loadedMath), baseURL: nil)
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            ready = true
            if let parent {
                if !loadedMath && RichPage.needsMath(parent.body) { load(into: webView); return }
                if parent.body != shownBody { shownBody = parent.body; webView.evaluateJavaScript("setContent(\(Self.jsString(parent.body)))") }
                if let anchor = parent.anchor { shownAnchor = anchor; webView.evaluateJavaScript("reveal(\(Self.jsString(anchor)))") }
            }
        }

        func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                     decisionHandler: @escaping @MainActor (WKNavigationActionPolicy) -> Void) {
            if action.navigationType == .linkActivated, let url = action.request.url {
                if parent?.onNavigate?(url) == true { decisionHandler(.cancel); return }
                if url.scheme == "about" { decisionHandler(.allow); return }
                Platform.open(url)
                decisionHandler(.cancel); return
            }
            decisionHandler(.allow)
        }

        func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
            guard let height = message.body as? Double, let binding = parent?.contentHeight else { return }
            let value = CGFloat(height)
            if abs(binding.wrappedValue - value) > 0.5 { binding.wrappedValue = value }
        }
    }
}

#if os(macOS)
extension RichWebView: NSViewRepresentable {
    func makeNSView(context: Context) -> WKWebView { makeWebView(context.coordinator) }
    func updateNSView(_ view: WKWebView, context: Context) { updateWebView(view, coordinator: context.coordinator) }
    static func dismantleNSView(_ view: WKWebView, coordinator: Coordinator) { dismantleWebView(view) }
}
#else
extension RichWebView: UIViewRepresentable {
    func makeUIView(context: Context) -> WKWebView { makeWebView(context.coordinator) }
    func updateUIView(_ view: WKWebView, context: Context) { updateWebView(view, coordinator: context.coordinator) }
    static func dismantleUIView(_ view: WKWebView, coordinator: Coordinator) { dismantleWebView(view) }
}
#endif

#if os(macOS)
/// Aperçu inerte à la souris. Sans cela, WebKit garde la molette (la page ne défile plus sous le pointeur)
/// et son suivi du survol neutralise ensuite toutes les bulles d’aide de la fenêtre.
/// Clics et défilement reviennent à SwiftUI ; l’éditeur agrandi s’ouvre d’un clic sur le texte.
final class PassiveWebView: WKWebView {
    override func hitTest(_ point: NSPoint) -> NSView? { nil }
    override func addTrackingArea(_ trackingArea: NSTrackingArea) {}
    override func updateTrackingAreas() {}
}
#endif

/// Évite le cycle de rétention entre WebKit et le coordinateur.
private final class WeakScriptHandler: NSObject, WKScriptMessageHandler {
    weak var target: (any WKScriptMessageHandler)?
    init(_ target: any WKScriptMessageHandler) { self.target = target }
    func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
        target?.userContentController(controller, didReceive: message)
    }
}

/// Aperçu Markdown/LaTeX qui prend la hauteur de son contenu.
/// Seuls les textes avec formules passent par WebKit (KaTeX) : une vue web par champ rendait
/// l’ouverture d’un grand scénario très lente (plusieurs centaines de vues pour 30 activités).
struct MarkdownPreview: View {
    let text: String
    var fontSize: CGFloat = 13.5
    var placeholder = "Rien à afficher."
    var body: some View {
        if text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
            Text(placeholder).font(.system(size: fontSize)).foregroundStyle(.tertiary)
                .frame(maxWidth: .infinity, alignment: .leading)
        } else if RichText.containsMath(text) {
            MathPreview(text: text, fontSize: fontSize)
        } else {
            NativeMarkdown(blocks: RichText.blocks(text), fontSize: fontSize)
        }
    }
}

private struct MathPreview: View {
    let text: String
    let fontSize: CGFloat
    @State private var height: CGFloat = 20
    var body: some View {
        RichWebView(body: RichText.html(text), fontSize: fontSize, contentHeight: $height)
            .frame(height: max(20, height))
    }
}

/// Rendu SwiftUI du Markdown du site, aligné sur la feuille de style des aperçus web (`RichPage.style`).
struct NativeMarkdown: View {
    let blocks: [RichText.Block]
    var fontSize: CGFloat = 13.5
    var body: some View {
        VStack(alignment: .leading, spacing: fontSize * 0.7) {
            ForEach(Array(blocks.enumerated()), id: \.offset) { _, block in view(block) }
        }
        .font(.system(size: fontSize))
        .lineSpacing(fontSize * 0.3)
        .frame(maxWidth: .infinity, alignment: .leading)
        .fixedSize(horizontal: false, vertical: true)
    }

    @ViewBuilder private func view(_ block: RichText.Block) -> some View {
        switch block {
        case .paragraph(let lines):
            Text(lines.map(RichText.attributed).joined(separator: "\n"))
        case .heading(let level, let text):
            Text(RichText.attributed(text))
                .font(.system(size: fontSize * (level == 2 ? 1.35 : level == 3 ? 1.15 : 1), weight: .semibold))
                .padding(.top, fontSize * 0.3)
        case .list(let ordered, let items):
            VStack(alignment: .leading, spacing: fontSize * 0.25) {
                ForEach(Array(items.enumerated()), id: \.offset) { index, item in
                    HStack(alignment: .firstTextBaseline, spacing: 6) {
                        Text(ordered ? "\(index + 1)." : "•").monospacedDigit().foregroundStyle(.secondary)
                            .frame(minWidth: fontSize * 0.9, alignment: .trailing)
                        Text(RichText.attributed(item))
                    }
                }
            }
        case .quote(let text):
            Text(RichText.attributed(text)).foregroundStyle(.secondary)
                .padding(.leading, 11)
                .overlay(alignment: .leading) { Capsule().fill(.quaternary).frame(width: 3) }
        case .math(let formula, _):
            // Jamais atteint en pratique : un texte avec formule passe par la vue web.
            Text("$$\n\(formula)\n$$").font(.system(size: fontSize, design: .monospaced))
        }
    }
}

private extension Array where Element == AttributedString {
    func joined(separator: String) -> AttributedString {
        var result = AttributedString()
        for (index, part) in enumerated() {
            if index > 0 { result += AttributedString(separator) }
            result += part
        }
        return result
    }
}
