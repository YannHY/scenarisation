import Foundation
import Testing
@testable import Scenarisation

@Suite("Fusion des modifications simultanées")
struct ScenarioMergeTests {
    private let base = Scenario.template()
    private func merge(_ local: Scenario, _ remote: Scenario) -> ScenarioMerge.Outcome {
        ScenarioMerge.merge(base: base, local: local, remote: remote)
    }
    private func activity(_ scenario: Scenario, _ id: String) -> Activity? {
        scenario.moments.flatMap(\.activities).first { $0.id == id }
    }
    private var firstActivity: String { base.moments[0].activities[0].id }

    @Test func differentFieldsOfTheSameActivityAreBothKept() throws {
        var local = base; local.moments[0].activities[0]["instructions"] = "Consigne du Mac"
        var remote = base; remote.moments[0].activities[0].duration = 25
        remote.meta["description"] = .string("Description du collègue")
        let outcome = merge(local, remote)
        #expect(outcome.conflicts.isEmpty)
        let merged = try #require(activity(outcome.scenario, firstActivity))
        #expect(merged["instructions"] == "Consigne du Mac" && merged.duration == 25)
        #expect(outcome.scenario.meta["description"]?.string == "Description du collègue")
        // Le résultat reste un scénario valide, identifiants uniques compris.
        _ = try Scenario.decode(outcome.scenario.encoded(), strict: true)
    }

    @Test func activitiesAddedOnBothSidesAreKeptInPlace() {
        var local = base; var mine = Activity.blank(); mine["description"] = "Ajout local"
        local.moments[0].activities.append(mine)
        var remote = base; var theirs = Activity.blank(); theirs["description"] = "Ajout distant"
        remote.moments[0].activities.insert(theirs, at: 0)
        let ids = merge(local, remote).scenario.moments[0].activities.map(\.id)
        #expect(ids == [theirs.id, firstActivity, mine.id])
    }

    @Test func anActivityMovedHereAndEditedThereAppearsOnceWithBothChanges() throws {
        var local = base
        let moved = local.moments[0].activities.removeFirst()
        local.moments[2].activities.append(moved)
        var remote = base; remote.moments[0].activities[0]["instructions"] = "Consigne revue"
        let merged = merge(local, remote).scenario
        #expect(merged.moments[0].activities.isEmpty)
        #expect(merged.moments[2].activities.map(\.id).last == firstActivity)
        #expect(merged.moments.flatMap(\.activities).filter { $0.id == firstActivity }.count == 1)
        #expect(activity(merged, firstActivity)?["instructions"] == "Consigne revue")
    }

    @Test func deletionsKeepItemsThatTheOtherSideModified() {
        var local = base; local.moments[0].activities.removeAll()
        var remote = base; remote.moments[0].activities[0]["notes"] = "Note ajoutée pendant ce temps"
        #expect(activity(merge(local, remote).scenario, firstActivity)?["notes"] == "Note ajoutée pendant ce temps")
        #expect(activity(merge(local, base).scenario, firstActivity) == nil)
        #expect(activity(merge(base, local).scenario, firstActivity) == nil)
    }

    @Test func aDeletedMomentReturnsWhenTheOtherSideAddedAnActivityToIt() {
        let momentID = base.moments[1].id
        var local = base; local.moments.remove(at: 1)
        var remote = base; var added = Activity.blank(); added["description"] = "Nouvelle activité"
        remote.moments[1].activities.append(added)
        let merged = merge(local, remote).scenario
        let restored = merged.moments.first { $0.id == momentID }
        #expect(restored?.activities.map(\.id) == [added.id])
        #expect(merged.moments.map(\.id) == base.moments.map(\.id))
    }

    @Test func remoteReorderingAndLocalEditsCombine() {
        var local = base; local.moments[0]["title"] = "Découvrir autrement"
        var remote = base; remote.moments.reverse()
        let merged = merge(local, remote).scenario
        #expect(merged.moments.map(\.id) == base.moments.reversed().map(\.id))
        #expect(merged.moments.last?["title"] == "Découvrir autrement")
    }

    @Test func textsMergeByLineThenByWord() {
        let text = "Lire le document.\nRépondre aux questions.\nComparer en binôme."
        var start = base; start.moments[0].activities[0]["instructions"] = text
        func edited(_ value: String) -> Scenario { var s = start; s.moments[0].activities[0]["instructions"] = value; return s }
        func merged(_ a: String, _ b: String) -> (String?, [String]) {
            let outcome = ScenarioMerge.merge(base: start, local: edited(a), remote: edited(b))
            return (activity(outcome.scenario, firstActivity)?["instructions"], outcome.conflicts)
        }
        // Lignes différentes.
        #expect(merged("Lire attentivement le document.\nRépondre aux questions.\nComparer en binôme.",
                       "Lire le document.\nRépondre aux questions.\nComparer en groupe.").0
                == "Lire attentivement le document.\nRépondre aux questions.\nComparer en groupe.")
        // Même ligne, mots différents.
        #expect(merged("Lire attentivement le document.\nRépondre aux questions.\nComparer en binôme.",
                       "Lire le document imprimé.\nRépondre aux questions.\nComparer en binôme.").0
                == "Lire attentivement le document imprimé.\nRépondre aux questions.\nComparer en binôme.")
        // Ajouts à la fin des deux côtés : les deux sont gardés.
        #expect(merged(text + "\nPrésenter.", text + "\nRédiger une synthèse.").0
                == text + "\nPrésenter.\nRédiger une synthèse.")
        // Même mot changé des deux côtés : la version locale l’emporte et le champ est signalé.
        let conflict = merged("Lire le roman.\nRépondre aux questions.\nComparer en binôme.",
                              "Lire le poème.\nRépondre aux questions.\nComparer en binôme.")
        #expect(conflict.0 == "Lire le roman.\nRépondre aux questions.\nComparer en binôme.")
        #expect(conflict.1 == ["/activities/\(firstActivity)/instructions"])
    }

    @Test func viewSettingsStayPersonal() {
        var local = base; local.meta["boardLayout"] = .string("grid"); local.fields["intentionsCollapsed"] = .bool(true)
        var remote = base; remote.meta["boardLayout"] = .string("list"); remote.meta["name"] = .string("Titre du collègue")
        remote.fields["intentionsCollapsed"] = .bool(false); remote.moments[0].fields["notesExpanded"] = .bool(true)
        let merged = merge(local, remote).scenario
        #expect(merged.meta["boardLayout"]?.string == "grid" && merged.fields["intentionsCollapsed"] == .bool(true))
        #expect(merged.moments[0].fields["notesExpanded"] == base.moments[0].fields["notesExpanded"])
        #expect(merged.name == "Titre du collègue")
        #expect(ScenarioMerge.sameContent(merged, { var s = remote; s.meta["boardLayout"] = .string("grid")
            s.fields["intentionsCollapsed"] = .bool(true); s.moments[0].fields["notesExpanded"] = .bool(false); return s }()))
        // Une annulation, en revanche, rétablit aussi l’affichage.
        let undone = ScenarioMerge.merge(base: base, local: base, remote: remote, keepLocalViewState: false).scenario
        #expect(undone == remote)
    }

    @Test func competenciesAndUnknownFieldsMergeWithoutLoss() {
        var start = base; start.moments[0].activities[0].fields["tools"] = .array([.string("A"), .string("B")])
        var local = start; local.moments[0].activities[0].fields["tools"] = .array([.string("A"), .string("B"), .string("C")])
        local.fields["futureWebField"] = .object(["keep": .bool(true)])
        var remote = start; remote.moments[0].activities[0].fields["tools"] = .array([.string("B"), .string("D")])
        let merged = ScenarioMerge.merge(base: start, local: local, remote: remote).scenario
        #expect(activity(merged, firstActivity)?.fields["tools"] == .array([.string("B"), .string("C"), .string("D")]))
        #expect(merged.fields["futureWebField"] == .object(["keep": .bool(true)]))
    }

    @Test func orderMergeInsertsAdditionsAfterTheirPredecessor() {
        #expect(ScenarioMerge.order(base: ["a", "b", "c"], local: ["a", "x", "b", "c"], remote: ["c", "b", "a"],
                                    keep: ["a", "b", "c", "x"]) == ["c", "b", "a", "x"])
        #expect(ScenarioMerge.order(base: ["a", "b"], local: ["a", "b", "x"], remote: ["y", "a", "b"],
                                    keep: ["a", "b", "x", "y"]) == ["y", "a", "b", "x"])
    }

    @Test func changedFocusNamesTheEditedItemAndField() {
        var edited = base; edited.moments[1].activities[0]["instructions"] = "Nouvelle consigne"
        #expect(ScenarioMerge.changedFocus(from: base, to: edited) == "activity:\(base.moments[1].activities[0].id):instructions")
        var titled = base; titled.moments[2]["title"] = "Bilan"
        #expect(ScenarioMerge.changedFocus(from: base, to: titled) == "moment:\(base.moments[2].id):title")
        var named = base; named.meta["name"] = .string("Autre titre")
        #expect(ScenarioMerge.changedFocus(from: base, to: named) == "scenario::name")
        var layout = base; layout.meta["boardLayout"] = .string("grid")
        #expect(ScenarioMerge.changedFocus(from: base, to: layout) == nil)
    }

    @Test func undoRevertsOnlyItsOwnChange() {
        // Annuler une saisie ne retire pas ce qu’un collègue a ajouté depuis.
        var typed = base; typed.moments[0]["title"] = "Titre saisi"
        var current = typed; current.moments[1].activities[0]["notes"] = "Note du collègue"
        let undone = ScenarioMerge.merge(base: typed, local: current, remote: base, keepLocalViewState: false).scenario
        #expect(undone.moments[0]["title"] == base.moments[0]["title"])
        #expect(undone.moments[1].activities[0]["notes"] == "Note du collègue")
    }

    @Test func theCursorStaysInPlaceWhenAColleagueEditsTheField() {
        let text = "Lire le document. Répondre aux questions."
        let cursor = NSRange(location: 25, length: 0) // dans « Répondre »
        #expect(TextSelectionShift.adjusted(cursor, from: text, to: "Lire attentivement le document. Répondre aux questions.")
                == NSRange(location: 39, length: 0))
        #expect(TextSelectionShift.adjusted(cursor, from: text, to: text + " Comparer.") == cursor)
        #expect(TextSelectionShift.adjusted(NSRange(location: 5, length: 2), from: text, to: "") == NSRange(location: 0, length: 0))
    }
}
