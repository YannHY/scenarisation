import AppKit
import SwiftUI
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

    static func html(_ markdown: String) -> String {
        var html: [String] = []
        var listType = ""
        var paragraph: [String] = []
        var mathBlock: [String]?
        func closeParagraph() {
            guard !paragraph.isEmpty else { return }
            html.append("<p>" + paragraph.map(inline).joined(separator: "<br>") + "</p>")
            paragraph = []
        }
        func closeList() { if !listType.isEmpty { html.append("</\(listType)>"); listType = "" } }
        func openList(_ type: String) {
            closeParagraph()
            if listType == type { return }
            closeList(); html.append("<\(type)>"); listType = type
        }
        func match(_ pattern: String, _ line: String) -> [String]? {
            guard let regex = try? NSRegularExpression(pattern: pattern),
                  let result = regex.firstMatch(in: line, range: NSRange(line.startIndex..., in: line)) else { return nil }
            return (0..<result.numberOfRanges).map { Range(result.range(at: $0), in: line).map { String(line[$0]) } ?? "" }
        }
        for line in markdown.replacingOccurrences(of: "\r\n", with: "\n").components(separatedBy: "\n") {
            let trimmed = line.trimmingCharacters(in: .whitespaces)
            if mathBlock != nil {
                if trimmed == "$$" {
                    html.append("<div class=\"math-block\">$$\n" + escape(mathBlock!.joined(separator: "\n")) + "\n$$</div>")
                    mathBlock = nil
                } else { mathBlock!.append(line) }
                continue
            }
            if trimmed == "$$" { closeParagraph(); closeList(); mathBlock = []; continue }
            if trimmed.isEmpty { closeParagraph(); closeList(); continue }
            if let heading = match(#"^(#{1,6})\s+(.+)$"#, trimmed) {
                closeParagraph(); closeList()
                let level = max(2, min(4, heading[1].count))
                html.append("<h\(level)>\(inline(heading[2]))</h\(level)>")
            } else if let item = match(#"^[-*+]\s+(.+)$"#, trimmed) {
                openList("ul"); html.append("<li>\(inline(item[1]))</li>")
            } else if let item = match(#"^\d+[.)]\s+(.+)$"#, trimmed) {
                openList("ol"); html.append("<li>\(inline(item[1]))</li>")
            } else if let quote = match(#"^>\s?(.*)$"#, trimmed) {
                closeParagraph(); closeList(); html.append("<blockquote>\(inline(quote[1]))</blockquote>")
            } else {
                closeList(); paragraph.append(line)
            }
        }
        closeParagraph(); closeList()
        if let mathBlock { html.append("<p>$$<br>" + escape(mathBlock.joined(separator: "\n")).replacingOccurrences(of: "\n", with: "<br>") + "</p>") }
        return html.joined(separator: "\n")
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
    body>*:first-child{margin-top:0}body>*:last-child{margin-bottom:0}
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

    static func document(body: String, fontSize: CGFloat, padding: CGFloat, extraStyle: String = "", extraScript: String = "") -> String {
        """
        <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">\(KaTeX.head)
        <style>\(style)\(extraStyle)</style></head>
        <body style="--size:\(Int(fontSize))px;--pad:\(Int(padding))px"><main id="content">\(body)</main>
        <script>
        function report(){ try { window.webkit.messageHandlers.size.postMessage(Math.ceil(document.documentElement.scrollHeight)); } catch(e){} }
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
struct RichWebView: NSViewRepresentable {
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

    func makeCoordinator() -> Coordinator { Coordinator() }

    func makeNSView(context: Context) -> WKWebView {
        let configuration = WKWebViewConfiguration()
        configuration.userContentController.add(WeakScriptHandler(context.coordinator), name: "size")
        let view = WKWebView(frame: .zero, configuration: configuration)
        view.setValue(false, forKey: "drawsBackground")
        view.navigationDelegate = context.coordinator
        view.allowsMagnification = false
        context.coordinator.parent = self
        context.coordinator.load(into: view)
        return view
    }

    func updateNSView(_ view: WKWebView, context: Context) {
        let coordinator = context.coordinator
        coordinator.parent = self
        if coordinator.loadedStyle != "\(fontSize)|\(padding)|\(extraStyle)|\(extraScript)" {
            coordinator.load(into: view)
        } else if coordinator.shownBody != body {
            coordinator.shownBody = body
            if coordinator.ready {
                view.evaluateJavaScript("setContent(\(Coordinator.jsString(body)))")
            }
        }
        if let anchor, anchor != coordinator.shownAnchor, coordinator.ready {
            coordinator.shownAnchor = anchor
            view.evaluateJavaScript("reveal(\(Coordinator.jsString(anchor)))")
        }
    }

    static func dismantleNSView(_ view: WKWebView, coordinator: Coordinator) {
        view.configuration.userContentController.removeScriptMessageHandler(forName: "size")
    }

    @MainActor final class Coordinator: NSObject, WKNavigationDelegate, WKScriptMessageHandler {
        var parent: RichWebView?
        var shownBody = ""
        var shownAnchor: String?
        var loadedStyle = ""
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
            view.loadHTMLString(RichPage.document(body: parent.body, fontSize: parent.fontSize, padding: parent.padding,
                                                  extraStyle: parent.extraStyle, extraScript: parent.extraScript), baseURL: nil)
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            ready = true
            if let parent {
                if parent.body != shownBody { shownBody = parent.body; webView.evaluateJavaScript("setContent(\(Self.jsString(parent.body)))") }
                if let anchor = parent.anchor { shownAnchor = anchor; webView.evaluateJavaScript("reveal(\(Self.jsString(anchor)))") }
            }
        }

        func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                     decisionHandler: @escaping @MainActor (WKNavigationActionPolicy) -> Void) {
            if action.navigationType == .linkActivated, let url = action.request.url {
                if parent?.onNavigate?(url) == true { decisionHandler(.cancel); return }
                if url.scheme == "about" { decisionHandler(.allow); return }
                NSWorkspace.shared.open(url)
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

/// Évite le cycle de rétention entre WebKit et le coordinateur.
private final class WeakScriptHandler: NSObject, WKScriptMessageHandler {
    weak var target: (any WKScriptMessageHandler)?
    init(_ target: any WKScriptMessageHandler) { self.target = target }
    func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
        target?.userContentController(controller, didReceive: message)
    }
}

/// Aperçu Markdown/LaTeX qui prend la hauteur de son contenu.
struct MarkdownPreview: View {
    let text: String
    var fontSize: CGFloat = 13.5
    var placeholder = "Rien à afficher."
    @State private var height: CGFloat = 20
    var body: some View {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        RichWebView(body: trimmed.isEmpty ? "<p class=\"empty\">\(RichText.escape(placeholder))</p>" : RichText.html(text),
                    fontSize: fontSize, contentHeight: $height)
            .frame(height: max(20, height))
    }
}
