import Testing
@testable import Scenarisation

@Suite struct RichTextTests {
    @Test func rendersSiteMarkdown() {
        let html = RichText.html("## Étapes\n- **lire** le *texte*\n- voir [doc](https://exemple.fr)\n\n1. un\n2. deux\n> à retenir")
        #expect(html.contains("<h2>Étapes</h2>"))
        #expect(html.contains("<ul>\n<li><strong>lire</strong> le <em>texte</em></li>"))
        #expect(html.contains("<a href=\"https://exemple.fr\">doc</a>"))
        #expect(html.contains("<ol>\n<li>un</li>\n<li>deux</li>\n</ol>"))
        #expect(html.contains("<blockquote>à retenir</blockquote>"))
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
