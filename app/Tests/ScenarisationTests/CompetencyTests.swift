import Foundation
import Testing
@testable import Scenarisation

@Suite("Référentiels et domaines de compétences")
struct CompetencyTests {
    @Test func everyBundledCompetencyBelongsToAnExistingDomain() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        #expect(catalog.frameworks.allSatisfy { !$0.groups.isEmpty })
        for framework in catalog.frameworks {
            #expect(Set(framework.groups.map(\.id)).count == framework.groups.count)
        }
        let sections = catalog.sections(for: catalog.competencies)
        #expect(sections.count == 39)
        #expect(Set(sections.map(\.id)).count == sections.count)
        let grouped = sections.flatMap(\.competencies)
        #expect(grouped.count == 1198)
        #expect(Set(grouped.map(\.id)) == Set(catalog.competencies.map(\.id)))
        #expect(sections.allSatisfy { section in
            section.competencies.allSatisfy { $0.frameworkId == section.framework.id && $0.groupId == section.group.id }
        })
        let socle = catalog.sections(for: catalog.matching(frameworkID: "socle"))
        #expect(socle.map(\.group.id) == ["domaine-1", "domaine-2", "domaine-3", "domaine-4", "domaine-5"])
    }

    @Test func domainFiltersStayWithinTheirFramework() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        for framework in catalog.frameworks {
            for group in framework.groups {
                let items = catalog.matching(frameworkID: framework.id, groupID: group.id)
                #expect(!items.isEmpty)
                #expect(items.allSatisfy { $0.frameworkId == framework.id && $0.groupId == group.id })
            }
        }
        #expect(catalog.matching(frameworkID: "pix", groupID: "fondements").isEmpty)
    }

    @Test func searchFindsDomainsAliasesAndWordsInAnyOrder() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        let expected = Set(catalog.matching(frameworkID: "pix-ia", groupID: "enjeux").map(\.id))
        #expect(Set(catalog.matching(search: "  ENJEUX   PIX IA  ").map(\.id)) == expected)
        let accentless = catalog.matching(frameworkID: "greencomp", search: "durabilite VALEURS")
        #expect(!accentless.isEmpty)
        #expect(accentless.map(\.id) == catalog.matching(frameworkID: "greencomp", search: "durabilité valeurs").map(\.id))
        #expect(Set(catalog.matching(frameworkID: "greencomp", groupID: "valeurs").map(\.id)).isSubset(of: Set(accentless.map(\.id))))
        #expect(catalog.matching(frameworkID: "pix", search: "IA").isEmpty)
        #expect(catalog.matching(search: "A3").contains { $0.id == "competency:acquerir:3" })
        #expect(catalog.matching(search: "CS1.1.01").contains { $0.id == "competency:digcomp:CS1.1.01" })
        #expect(catalog.matching(frameworkID: "pix", search: "d’information").map(\.id) ==
                catalog.matching(frameworkID: "pix", search: "d'information").map(\.id))
        #expect(catalog.matching(search: " \n ").count == 1198)
        #expect(catalog.matching(search: "aucune-competence-ne-correspond-a-ce-texte").isEmpty)
    }

    @Test func selectionCanBeReviewedAcrossFrameworksAndFilteredWithoutChangingIt() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        let selected: Set<String> = ["competency:acquerir:1", "competency:pix-ia:1.1"]
        #expect(Set(catalog.matching(selectedIDs: selected).map(\.id)) == selected)
        #expect(catalog.matching(frameworkID: "pix-ia", selectedIDs: selected).map(\.id) == ["competency:pix-ia:1.1"])
        #expect(catalog.matching(search: "introuvable", selectedIDs: selected).isEmpty)
        #expect(catalog.matching(selectedIDs: []).isEmpty)
    }

    @Test func compactLabelsDistinguishFlorimontDomains() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        let acquire = try #require(catalog.competencies.first { $0.id == "competency:acquerir:1" })
        let deepen = try #require(catalog.competencies.first { $0.id == "competency:approfondir:1" })
        #expect(catalog.shortLabel(for: acquire) == "Acquérir 1")
        #expect(catalog.shortLabel(for: deepen) == "Approfondir 1")
        let digcomp = try #require(catalog.competencies.first { $0.frameworkId == "digcomp" })
        #expect(catalog.shortLabel(for: digcomp) == "DigComp 3.0 \(digcomp.code)")
        #expect(catalog.context(for: acquire) == "Florimont · Acquérir")
    }

    @Test func assistantCanDiscoverDomainsAndSearchWithinOne() throws {
        var scenario = Scenario.blank()
        let frameworks = try AssistantTools.run("catalog", arguments: ["topic": .string("frameworks")], scenario: &scenario, reference: scenario).object
        let pixIA = try #require(frameworks["items"]?.array.first { $0.object["id"] == .string("pix-ia") })
        #expect(pixIA.object["groups"]?.array.map { $0.object["id"]?.string ?? "" } == ["fondements", "usages", "enjeux"])
        let result = try AssistantTools.run("catalog", arguments: ["topic": .string("competencies"), "framework": .string("pix-ia"),
            "group": .string("enjeux"), "search": .string("enjeux")], scenario: &scenario, reference: scenario).object
        #expect(result["total"] == .number(5))
        #expect(result["items"]?.array.allSatisfy { $0.object["groupId"] == .string("enjeux") } == true)
        #expect(scenario == .blank())
        #expect(throws: ScenarioError.self) {
            try AssistantTools.run("catalog", arguments: ["topic": .string("competencies"), "framework": .string("pix"),
                "group": .string("enjeux")], scenario: &scenario, reference: scenario)
        }
    }
}
