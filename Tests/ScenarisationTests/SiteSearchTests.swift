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

    @MainActor @Test(.timeLimit(.minutes(1))) func cancelledSearchCannotOverwriteNewPages() async throws {
        let engine = PausedSearch()
        let model = SiteSearchModel(engine: engine)
        var events = engine.events.makeAsyncIterator()
        model.query = "ancien"
        #expect(await events.next() == "pages:ancien")
        model.query = "nouveau"
        #expect(await events.next() == "pages:nouveau")
        engine.finishPages("nouveau")
        #expect(await events.next() == "scenarios:nouveau")
        // The old WebKit request completes despite cancellation, while the new request waits for scenarios.
        engine.finishPages("ancien")
        await Task.yield()
        engine.finishScenarios("nouveau")
        for _ in 0..<100 {
            if model.status != .searching { break }
            try await Task.sleep(for: .milliseconds(10))
        }
        #expect(model.results.map(\.title) == ["nouveau"])
        #expect(!model.canLoadMore)
    }

    @MainActor @Test(.timeLimit(.minutes(1))) func pageDetailsLoadOnlyAsRequested() async throws {
        let engine = PaginatedSearch()
        let model = SiteSearchModel(engine: engine)
        model.category = .help
        model.query = "cours"
        for _ in 0..<100 {
            if model.status != .searching { break }
            try await Task.sleep(for: .milliseconds(10))
        }
        #expect(engine.offsets == [0])
        #expect(model.results.count == 12)
        #expect(model.canLoadMore)
        model.loadMore()
        for _ in 0..<100 {
            if !model.loadingMore { break }
            try await Task.sleep(for: .milliseconds(10))
        }
        #expect(engine.offsets == [0, 12])
        #expect(model.results.count == 13)
        #expect(!model.canLoadMore)
    }
}

@MainActor private final class PaginatedSearch: SiteSearching {
    var offsets: [Int] = []
    func pages(_ query: String, offset: Int) async throws -> SiteSearchEngine.PageBatch {
        offsets.append(offset)
        return .init(pages: (offset..<min(offset + 12, 13)).map {
            SiteSearchPage(url: "help.php#\($0)", title: "Page \($0)", excerpt: "", sections: [])
        }, count: 13)
    }
    func scenarios(_ query: String, offset: Int) async throws -> PublicScenarioSearch { .init(results: [], count: 0) }
}

@MainActor private final class PausedSearch: SiteSearching {
    let events: AsyncStream<String>
    private let signal: AsyncStream<String>.Continuation
    private var pageWaiters: [String: CheckedContinuation<SiteSearchEngine.PageBatch, Error>] = [:]
    private var scenarioWaiters: [String: CheckedContinuation<PublicScenarioSearch, Error>] = [:]
    init() {
        let stream = AsyncStream<String>.makeStream()
        events = stream.stream; signal = stream.continuation
    }
    func pages(_ query: String, offset: Int) async throws -> SiteSearchEngine.PageBatch {
        try await withCheckedThrowingContinuation {
            pageWaiters[query] = $0; signal.yield("pages:" + query)
        }
    }
    func scenarios(_ query: String, offset: Int) async throws -> PublicScenarioSearch {
        try await withCheckedThrowingContinuation {
            scenarioWaiters[query] = $0; signal.yield("scenarios:" + query)
        }
    }
    func finishPages(_ query: String) {
        pageWaiters.removeValue(forKey: query)?.resume(returning: .init(pages: [
            SiteSearchPage(url: "help.php", title: query, excerpt: "", sections: [])
        ], count: 1))
    }
    func finishScenarios(_ query: String) {
        scenarioWaiters.removeValue(forKey: query)?.resume(returning: .init(results: [], count: 0))
    }
}
