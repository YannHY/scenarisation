import Foundation
import Testing
@testable import Scenarisation

@Suite("Recherche sur le site")
struct SiteSearchTests {
    private func page(_ url: String, title: String = "Page", excerpt: String = "",
                      sections: [SiteSearchPage.Section] = []) -> SiteSearchPage {
        SiteSearchPage(url: url, title: title, excerpt: excerpt, sections: sections)
    }

    @Test func categoriesFollowTheSiteFilters() {
        #expect(SiteSearch.filter(page("/help.php"), category: .help) != nil)
        #expect(SiteSearch.filter(page("/help.php"), category: .models) == nil)
        #expect(SiteSearch.filter(page("/competencies.php"), category: .references) != nil)
        #expect(SiteSearch.filter(page("/news.php"), category: .other) != nil)
        #expect(SiteSearch.filter(page("/models.php"), category: .all) != nil)
        #expect(SiteSearchCategory.scenarios.searchesPages == false)
        #expect(SiteSearchCategory.help.searchesScenarios == false)
    }

    @Test func aiasHelpSectionsCountAsReferences() throws {
        let help = page("/help.php", sections: [
            .init(url: "/help.php#aias", title: "Échelle AIAS", excerpt: "", anchor: true),
            .init(url: "/help.php#cli", title: "Ligne de commande", excerpt: "", anchor: true)])
        let kept = try #require(SiteSearch.filter(help, category: .references))
        #expect(kept.sections.map(\.title) == ["Échelle AIAS"])
        #expect(kept.label == "AIAS")
        #expect(SiteSearch.filter(page("/help.php", excerpt: "Rien à voir"), category: .references) == nil)
    }

    @Test func resultsPointToTheBestMatchingSectionWithHighlight() throws {
        let help = page("/help.php", title: "Aide", sections: [
            .init(url: "/help.php#export", title: "Exporter un scénario", excerpt: "…", anchor: true),
            .init(url: "/help.php#moments", title: "Moments et activités", excerpt: "les <mark>moments</mark>", anchor: true)])
        let result = try #require(SiteSearch.result(help, query: "moments"))
        #expect(result.title == "Moments et activités")
        #expect(result.category == "Aide")
        #expect(result.url.absoluteString == "https://scenarisation.eu/help.php?highlight=moments#moments")
    }

    @Test func publicScenariosAreDecodedAndOffSiteLinksDropped() throws {
        let json = #"{"results":[{"url":"view.php?token=abc","meta":{"title":"L'Odyssée"},"excerpt":"L&#039;épopée","score":0},"#
            + #"{"url":"https://example.org/x","meta":{"title":"Ailleurs"}}],"count":2}"#
        let found = try JSONDecoder().decode(PublicScenarioSearch.self, from: Data(json.utf8))
        #expect(found.count == 2)
        let results = found.results.compactMap { SiteSearch.result($0, query: "épopée") }
        #expect(results.count == 1)
        #expect(results[0].category == "Scénarios partagés")
        #expect(results[0].url.host == "scenarisation.eu")
        #expect(results[0].url.query?.contains("token=abc") == true)
    }

    @Test func excerptsAreReadable() {
        let text = SiteSearch.excerpt("…L&#039;<mark>épopée</mark> [texte](https://exemple.org/doc) **Point** &amp; fin")
        #expect(String(text.characters) == "…L'épopée texte Point & fin")
        let marked = text.runs.filter { $0.inlinePresentationIntent == .stronglyEmphasized }
        #expect(marked.map { String(text[$0.range].characters) } == ["épopée"])
        #expect(SiteSearch.decodeEntities("&#x2019;&nbsp;&inconnu; &") == "’\u{00A0}&inconnu; &")
    }

    @MainActor @Test func shortQueriesOnlyInvite() {
        let model = SiteSearchModel()
        model.query = "a"
        #expect(model.isActive)
        #expect(model.status == .invitation)
        model.clear()
        #expect(!model.isActive)
    }
}
