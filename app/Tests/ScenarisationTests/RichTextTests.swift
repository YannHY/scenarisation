import Testing
import Foundation
import SwiftUI
import WebKit
@testable import Scenarisation

@Suite struct RichTextTests {
    @MainActor @Test(.timeLimit(.minutes(1))) func editsDuringInitialLoadReachTheDOM() async throws {
        var page = RichWebView(body: "<p>Ancien texte</p>", contentHeight: .constant(20))
        let coordinator = page.makeCoordinator()
        let view = page.makeWebView(coordinator)
        page.body = "<p>Nouveau texte</p>"
        page.updateWebView(view, coordinator: coordinator)
        for _ in 0..<1000 {
            if coordinator.ready { break }
            try await Task.sleep(for: .milliseconds(10))
        }
        #expect(coordinator.ready)
        let text = try await view.evaluateJavaScript("document.getElementById('content').textContent")
        #expect(text as? String == "Nouveau texte")
    }

    @Test func rendersSiteMarkdown() {
        let html = RichText.html("## Étapes\n- **lire** le *texte*\n- voir [doc](https://exemple.fr)\n\n1. un\n2. deux\n> à retenir")
        #expect(html.contains("<h2>Étapes</h2>"))
        #expect(html.contains("<ul>\n<li><strong>lire</strong> le <em>texte</em></li>"))
        #expect(html.contains("<a href=\"https://exemple.fr\">doc</a>"))
        #expect(html.contains("<ol>\n<li>un</li>\n<li>deux</li>\n</ol>"))
        #expect(html.contains("<blockquote>à retenir</blockquote>"))
    }

    @Test func nativePreviewUsesTheSameBlocksAsTheSite() {
        let blocks = RichText.blocks("Intro\nsuite\n## Étapes\n- un\n- deux\n1. premier\n> à retenir\n\nFin")
        #expect(blocks == [
            .paragraph(["Intro", "suite"]), .heading(level: 2, text: "Étapes"),
            .list(ordered: false, items: ["un", "deux"]), .list(ordered: true, items: ["premier"]),
            .quote("à retenir"), .paragraph(["Fin"]),
        ])
        #expect(RichText.blocks("# Titre")[0] == .heading(level: 2, text: "Titre"))
        #expect(RichText.blocks("$$\nx\n$$") == [.math("x", closed: true)])
    }

    @Test func nativeInlineFormattingFollowsTheSiteRules() {
        let text = RichText.attributed("Lire **le texte** et *vite*, `code *x*`, [**doc**](https://exemple.fr) ou [piège](javascript:alert(1))")
        #expect(String(text.characters) == "Lire le texte et vite, code *x*, doc ou [piège](javascript:alert(1))")
        func intent(of word: String) -> InlinePresentationIntent? {
            text.runs.first { String(text[$0.range].characters) == word }?.inlinePresentationIntent
        }
        #expect(intent(of: "le texte") == .stronglyEmphasized)
        #expect(intent(of: "vite") == .emphasized)
        #expect(intent(of: "code *x*") == .code)
        #expect(text.runs.contains { $0.link == URL(string: "https://exemple.fr") && $0.inlinePresentationIntent == .stronglyEmphasized })
        #expect(!text.runs.contains { $0.link?.scheme == "javascript" })
        // Comme sur le site : une étoile seule reste telle quelle, le gras ne contient pas d’étoile.
        #expect(String(RichText.attributed("3 * 4 = 12").characters) == "3 * 4 = 12")
        #expect(RichText.html("**le *grand* texte**") == "<p>**le <em>grand</em> texte**</p>")
        let nested = RichText.attributed("**le *grand* texte**")
        #expect(String(nested.characters) == "**le grand texte**")
    }

    @Test func escapesHTMLAndRejectsUnsafeLinks() {
        let html = RichText.html("<script>x</script> [clic](javascript:alert(1))")
        #expect(!html.contains("<script>"))
        #expect(!html.contains("href=\"javascript"))
    }

    @Test func protectsFormulasFromMarkdown() {
        let html = RichText.html("Soit $a*b*c$ et **gras**")
        #expect(html.contains("$a*b*c$"))
        #expect(html.contains("<strong>gras</strong>"))
        let block = RichText.html("Texte\n$$\n\\frac{a}{b} < 1\n$$")
        #expect(block.contains("<div class=\"math-block\">$$\n\\frac{a}{b} &lt; 1\n$$</div>"))
        #expect(RichText.containsMath("prix $x^2$"))
        #expect(!RichText.containsMath("10 $ seulement"))
    }

    @Test func previewPagesLoadKaTeXOnlyWhenNeeded() {
        let plain = RichPage.document(body: RichText.html("Lire **le texte**"), fontSize: 14, padding: 0,
                                      math: RichPage.needsMath(RichText.html("Lire **le texte**")))
        #expect(!plain.contains("@font-face"))
        #expect(plain.utf8.count < 20_000)
        #expect(plain.contains("function renderMath"))
        let formula = RichText.html("Soit $x^2$")
        #expect(RichPage.needsMath(formula))
        #expect(RichPage.document(body: formula, fontSize: 14, padding: 0, math: true).contains("@font-face"))
    }

    @Test func htmlExportEmbedsKaTeXOnlyWithMath() throws {
        var scenario = Scenario.blank()
        #expect(!ScenarioExporter.html(scenario, students: false).contains("katex"))
        scenario.meta["description"] = .string("Fonction $f(x) = ax + b$")
        let html = ScenarioExporter.html(scenario, students: false)
        #expect(html.contains("renderMathInElement"))
        #expect(html.contains("data:font/woff2;base64,"))
    }

    @Test func htmlExportWithFormulasStillImports() throws {
        var source = Scenario.template()
        source.moments[0].activities[0]["instructions"] = "Tracer $f(x) = ax + b$ puis :\n$$\n\\frac{a}{b}\n$$"
        let data = try ScenarioExporter.data(source, format: .html, students: false, moments: Set(source.moments.map(\.id)))
        let result = try ScenarioImporter.decode(data, filename: "scenario.html")
        #expect(result.activityCount == source.activityCount)
        #expect(result.moments[0].activities[0]["instructions"].contains("$f(x) = ax + b$"))
    }

    @Test @MainActor func helpAndLegalPagesAreBundled() {
        #expect(HelpCenter.html.contains("id=\"latex\""))
        #expect(HelpCenter.terms.contains("Conditions d’utilisation et licences"))
        #expect(HelpCenter.terms.contains("id=\"licences-reutilisation\""))
        #expect(HelpCenter.privacy.contains("Politique de confidentialité"))
        #expect(HelpCenter.privacy.contains("Dans Scénarisation pour Mac"))
        #expect(!HelpCenter.terms.contains("<?php"))
    }
}
