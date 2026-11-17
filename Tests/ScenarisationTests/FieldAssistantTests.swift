import Foundation
import SwiftUI
import Testing
@testable import Scenarisation

@Suite("Aide IA dans les champs")
@MainActor
struct FieldAssistantTests {
    private let configuration = AIConfiguration(provider: "ollama", endpoint: "http://localhost:11434", model: "test")
    private func request(original: String = "Texte actuel", action: FieldAIAction = .write, instruction: String = "Une activité sur le conte") -> FieldAIRequest {
        FieldAIRequest(label: "Description de l’activité", purpose: .activityDescription, action: action,
            instruction: instruction, original: original, context: FieldAIContext())
    }
    private func reply(_ text: String) throws -> String {
        String(decoding: try JSONEncoder().encode(["text": text]), as: UTF8.self)
    }

    @Test func contextIncludesTheSelectedActivityWithoutSendingOtherMomentsOrNotes() throws {
        var scenario = Scenario.template()
        scenario.meta["name"] = .string("Le conte")
        scenario.meta["designers"] = .string("PRIVATE-CONTACT")
        scenario.meta["unknown"] = .string("PRIVATE-METADATA")
        scenario.moments[0]["title"] = "Découvrir les personnages"
        scenario.moments[0]["notes"] = "PRIVATE-NOTES"
        scenario.moments[0].activities[0]["description"] = "Comparer deux personnages"
        scenario.moments[0].activities[0]["type"] = "collaborate"
        scenario.moments[0].activities[0]["notes"] = "PRIVATE-ACTIVITY-NOTES"
        scenario.moments[1]["title"] = "PRIVATE-OTHER-MOMENT"
        let moment = scenario.moments[0]
        let activity = moment.activities[0]
        let context = FieldAIContext(scenario: scenario, moment: moment, activity: activity)
        let request = FieldAIRequest(label: "Consignes pour les élèves", purpose: .instructions, action: .write,
            instruction: "Avec une liste à puces", original: activity["instructions"], context: context)
        let payload = try JSONDecoder().decode(JSONValue.self, from: Data(request.user().utf8)).object
        #expect(payload["scenario"]?.object["name"] == .string("Le conte"))
        #expect(payload["moment"]?.object["title"] == .string("Découvrir les personnages"))
        #expect(payload["activity"]?.object["type"] == .string("Collaborer"))
        #expect(payload["activity"]?.object["duration"] == .number(activity.duration))
        #expect(payload["request"] == .string("Avec une liste à puces"))
        #expect(!(try request.user()).contains("PRIVATE-"))
        #expect(payload["moment"]?.object["activities"] == nil)
        let objectives = FieldAIContext(scenario: scenario, moment: moment).payload
        #expect(objectives["moment"]?.object["activities"]?.array.first?.object["description"] == .string("Comparer deux personnages"))
    }

    @Test func bloomHelpKeepsTheSelectedCategoryAndVerbAndReturnsOnlyTheFieldText() throws {
        let request = FieldAIRequest(label: "Acquis d’apprentissage", purpose: .outcome(category: "Analyser", verb: "Comparer"),
            action: .write, instruction: "Deux personnages", original: "", context: FieldAIContext())
        #expect(request.system.contains("Analyser") && request.system.contains("Comparer"))
        let expected = "Comparer **deux personnages**.\nJustifier avec un exemple."
        #expect(try FieldAIRequest.text(from: "```json\n" + reply(expected) + "\n```") == expected)
    }

    @Test func generationWaitsForInsertionAndTheSingleFieldChangeSupportsUndoAndRedo() async throws {
        var document = ScenarioDocument(scenario: .template(), enteredEditor: true)
        let original = document.scenario
        let binding = Binding(get: { document }, set: { document = $0 })
        let undo = DocumentUndo()
        let manager = UndoManager()
        let model = FieldAssistantModel(generate: { request, configuration, key in
            #expect(configuration.model == "test" && key == "configured-key")
            #expect(request.original == original.moments[0].activities[0]["description"])
            return try reply("Les élèves comparent deux personnages.")
        }, credential: { _ in "configured-key" })
        await model.request(request(original: original.moments[0].activities[0]["description"]), configuration: configuration)?.value
        #expect(document.scenario == original)
        #expect(model.canApply(to: original.moments[0].activities[0]["description"]))
        model.proposal = "Comparer deux personnages et justifier ses choix."
        var expected = original
        expected.moments[0].activities[0]["description"] = model.proposal
        manager.beginUndoGrouping()
        #expect(model.apply(to: document.scenario.moments[0].activities[0]["description"]) { value in
            var updated = document.scenario
            updated.moments[0].activities[0]["description"] = value
            undo.replace(updated, document: binding, manager: manager)
        })
        manager.endUndoGrouping()
        #expect(document.scenario == expected)
        #expect(model.proposal.isEmpty && model.baseline == nil)
        manager.undo()
        #expect(document.scenario == original)
        manager.redo()
        #expect(document.scenario == expected)
    }

    @Test func aChangedFieldCannotBeOverwrittenByAnOlderProposal() async throws {
        let model = FieldAssistantModel(generate: { _, _, _ in try reply("Nouvelle description") }, credential: { _ in "" })
        await model.request(request(original: "Version initiale"), configuration: configuration)?.value
        var field = "Modification manuelle"
        #expect(!model.canApply(to: field))
        #expect(!model.apply(to: field) { field = $0 })
        #expect(field == "Modification manuelle")
        model.proposal = "   "
        #expect(!model.canApply(to: "Version initiale"))
    }

    @Test func closingOrChangingTheServiceIgnoresLateResponsesAndAllowsANewRequest() async throws {
        var pending: CheckedContinuation<String, Never>?
        let (started, signal) = AsyncStream<Void>.makeStream()
        var calls = 0
        let model = FieldAssistantModel(generate: { _, configuration, _ in
            calls += 1
            if calls == 1 { return await withCheckedContinuation { pending = $0; signal.yield(()) } }
            #expect(configuration.model == "another-model")
            return try reply("Proposition récente")
        }, credential: { _ in "" })
        let first = model.request(request(), configuration: configuration)
        for await _ in started { break }
        #expect(model.busy && !model.canApply(to: "Texte actuel"))
        #expect(model.request(request(), configuration: configuration) == nil)
        model.reset()
        #expect(!model.busy && model.proposal.isEmpty && model.baseline == nil)
        await model.request(request(), configuration: AIConfiguration(provider: "ollama", endpoint: "http://localhost:11434", model: "another-model"))?.value
        pending?.resume(returning: try reply("Réponse ancienne à ignorer"))
        await first?.value
        #expect(calls == 2 && model.proposal == "Proposition récente" && !model.busy)
    }

    @Test func quickActionsNeedExistingTextAndGenerationNeedsARequest() async throws {
        var calls = 0
        let model = FieldAssistantModel(generate: { _, _, _ in calls += 1; return try reply("Texte reformulé") }, credential: { _ in "" })
        #expect(model.request(request(instruction: "  "), configuration: configuration) == nil)
        #expect(model.request(request(original: "  ", action: .rephrase), configuration: configuration) == nil)
        #expect(calls == 0)
        await model.request(request(action: .rephrase, instruction: ""), configuration: configuration)?.value
        #expect(calls == 1 && model.proposal == "Texte reformulé")
    }

    @Test(arguments: ["{}", "Texte sans protocole", "{\"text\":\"  \"}", "{\"text\":42}", "{\"kind\":\"proposal\",\"scenario\":{}}"])
    func invalidRepliesLeaveTheFieldUntouched(_ raw: String) async throws {
        let model = FieldAssistantModel(generate: { _, _, _ in raw }, credential: { _ in "" })
        await model.request(request(), configuration: configuration)?.value
        #expect(model.error != nil && model.proposal.isEmpty && !model.busy)
        #expect(!model.canApply(to: "Texte actuel"))
    }

    @Test func connectionErrorsAreShownAndRetryUsesTheSameRequest() async throws {
        var calls = 0
        let model = FieldAssistantModel(generate: { _, _, _ in
            calls += 1
            if calls == 1 { throw ScenarioError.invalid("Le serveur local est arrêté.") }
            return try reply("Proposition après reconnexion")
        }, credential: { _ in "" })
        await model.request(request(), configuration: configuration)?.value
        #expect(model.error?.contains("serveur local") == true && !model.busy && model.proposal.isEmpty)
        await model.request(request(), configuration: configuration)?.value
        #expect(calls == 2 && model.error == nil && model.proposal == "Proposition après reconnexion")
    }
}
