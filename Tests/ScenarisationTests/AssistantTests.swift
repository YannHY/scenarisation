import Foundation
import Testing
@testable import Scenarisation

@Suite("Dialogue pédagogique et outils locaux")
@MainActor
struct AssistantTests {
    private let configuration = AIConfiguration(provider: "ollama", endpoint: "http://localhost:11434", model: "test")
    private func json(_ value: JSONValue) throws -> String { String(decoding: try JSONEncoder().encode(value), as: UTF8.self) }
    private func context(_ text: String) throws -> [String: JSONValue] { try JSONDecoder().decode(JSONValue.self, from: Data(text.utf8)).object }
    private func tool(_ name: String, _ arguments: [String: JSONValue] = [:]) throws -> String {
        try json(.object(["kind": .string("tool"), "name": .string(name), "arguments": .object(arguments)]))
    }

    @Test func guidedCreationStartsWithQuestionsWithoutCallingTheModel() async throws {
        var calls = 0
        let model = AssistantModel(respond: { _, history, _, _ in
            calls += 1
            #expect(history.first == AssistantTurn(role: .assistant, content: AssistantPlanning.welcome))
            #expect(history.last == AssistantTurn(role: .user, content: "Le conte, sixième en France, 55 minutes"))
            return AssistantReply(message: "Quelle modalité et quelle taille de groupe ?", proposal: nil)
        }, credential: { _ in "" })
        model.beginCreation(); model.beginCreation()
        #expect(calls == 0 && model.messages.count == 1 && model.proposal == nil)
        await model.send("Le conte, sixième en France, 55 minutes", scenario: .blank(), configuration: configuration)?.value
        #expect(calls == 1 && model.messages.count == 3)
    }

    @Test func creationCoversTheSameDiscoveryTopicsAsTheCLIWorkflow() throws {
        let guide = HelpCenter.page("help-site")
        let start = try #require(guide.range(of: "Questions indispensables :"))
        let end = try #require(guide.range(of: "Questions complémentaires", range: start.upperBound..<guide.endIndex))
        let questions = guide[start.upperBound..<end.lowerBound].split(separator: "\n").filter { $0.hasPrefix("- ") }
        #expect(questions.count == AssistantPlanning.requiredQuestions.count)
        for (cli, app) in zip(questions, AssistantPlanning.requiredQuestions) {
            let clean = String(cli.dropFirst(2)).trimmingCharacters(in: CharacterSet(charactersIn: " ;."))
            let prefix = clean.split(separator: " ").prefix(3).joined(separator: " ")
            #expect(app.localizedStandardContains(prefix))
        }
        #expect(AssistantPlanning.optionalQuestions.count == 3)
        #expect(AssistantPrompt.dialogue.contains(AssistantPlanning.instructions))
    }

    @Test func clarificationKeepsHistoryAndUsesTheLatestDocument() async throws {
        var calls = 0
        var updated = Scenario.blank()
        updated.meta["description"] = .string("Français, sixième")
        let model = AssistantModel(respond: { scenario, history, _, _ in
            calls += 1
            if calls == 1 {
                #expect(history == [AssistantTurn(role: .user, content: "Prépare une séance")])
                return AssistantReply(message: "Quel sujet et quelle durée ?", proposal: nil)
            }
            #expect(history.map(\.content) == ["Prépare une séance", "Quel sujet et quelle durée ?", "Le conte, 55 minutes"])
            #expect(scenario == updated)
            return AssistantReply(message: "Voici une progression adaptée.", proposal: Scenario.template())
        }, credential: { _ in "" })
        await model.send("Prépare une séance", scenario: .blank(), configuration: configuration)?.value
        #expect(model.proposal == nil && model.baseline == nil && model.error == nil)
        await model.send("Le conte, 55 minutes", scenario: updated, configuration: configuration)?.value
        #expect(model.baseline == updated && model.proposal != nil)
        #expect(model.messages.last?.text == "Voici une progression adaptée.")
        #expect(!model.busy)
    }

    @Test func advicePreservesPendingProposalAndDocumentChangesBlockApplication() async throws {
        let source = Scenario.template()
        var proposed = source
        proposed.meta["name"] = .string("Proposition")
        var calls = 0
        let model = AssistantModel(respond: { scenario, _, _, _ in
            calls += 1
            if calls == 1 { return AssistantReply(message: "Proposition à examiner.", proposal: proposed) }
            #expect(scenario == proposed)
            return AssistantReply(message: "Le diagnostic permet de repérer les prérequis.", proposal: nil)
        }, credential: { _ in "" })
        await model.send("Adapte la séance", scenario: source, configuration: configuration)?.value
        await model.send("Pourquoi ce diagnostic ?", scenario: source, configuration: configuration)?.value
        #expect(model.proposal == proposed && model.baseline == source)
        var changed = source; changed.meta["name"] = .string("Modification manuelle")
        #expect(model.send("Continue", scenario: changed, configuration: configuration) == nil)
        #expect(calls == 2 && model.error != nil)
        model.discard()
        #expect(model.baseline == nil && model.proposal == nil)
    }

    @Test func aNewConversationIgnoresLateResponsesAndClearsHistory() async throws {
        var pending: CheckedContinuation<AssistantReply, Never>?
        let (started, signal) = AsyncStream<Void>.makeStream()
        var calls = 0
        let model = AssistantModel(respond: { _, history, _, _ in
            calls += 1
            if calls == 1 {
                return await withCheckedContinuation { pending = $0; signal.yield(()) }
            }
            #expect(history == [AssistantTurn(role: .user, content: "Nouvelle demande")])
            return AssistantReply(message: "Quel public ?", proposal: nil)
        }, credential: { _ in "" })
        let first = model.send("Ancienne demande", scenario: .blank(), configuration: configuration)
        for await _ in started { break }
        model.newConversation()
        await model.send("Nouvelle demande", scenario: .blank(), configuration: configuration)?.value
        pending?.resume(returning: AssistantReply(message: "Ancienne réponse", proposal: Scenario.template()))
        await first?.value
        #expect(model.messages.map(\.text) == ["Nouvelle demande", "Quel public ?"])
        #expect(model.proposal == nil && model.baseline == nil && !model.busy)
    }

    @Test func applyingAndDiscardingAreIncludedInTheNextExchange() async throws {
        var calls = 0
        let proposed = Scenario.template()
        let model = AssistantModel(respond: { _, history, _, _ in
            calls += 1
            if calls == 2 { #expect(history.contains { $0.content.hasPrefix("Proposition appliquée") }) }
            if calls == 3 { #expect(history.contains { $0.content.hasPrefix("Proposition écartée") }) }
            return AssistantReply(message: "Proposition préparée.", proposal: proposed)
        }, credential: { _ in "" })
        await model.send("Crée", scenario: .blank(), configuration: configuration)?.value
        model.applied()
        await model.send("Adapte", scenario: proposed, configuration: configuration)?.value
        model.discard()
        await model.send("Autre adaptation", scenario: proposed, configuration: configuration)?.value
        #expect(calls == 3)
    }

    @Test func targetedToolsPrepareAProposalWithoutRewritingHiddenFields() async throws {
        var source = Scenario.template()
        source.fields["customWebField"] = .object(["keep": .bool(true)])
        var moments = source.moments
        var activities = moments[0].activities
        activities[0].fields["customActivityField"] = .string("À conserver")
        moments[0].activities = activities; source.moments = moments
        var activity = Activity.blank()
        activity["type"] = "practice"; activity["teachingMode"] = "guided"
        activity["evaluationMode"] = "diagnostic"; activity.aiasSelection = "1"; activity.duration = 5
        activity["description"] = "Repérer les prérequis"
        activity["instructions"] = "Cite deux caractéristiques du conte et donne un exemple."
        let moment = JSONValue.object(["title": .string("Diagnostic"), "objectives": .string("Identifier les caractéristiques du conte"),
            "intentions": .string("Adapter l’étayage aux prérequis"), "activities": .array([.object(activity.fields)])])
        var calls = 0
        let reply = try await AssistantConversation.reply(scenario: source, history: [.init(role: .user, content: "Ajoute un diagnostic")]) { _, user in
            let data = try context(user)
            calls += 1
            switch calls {
            case 1: return try tool("add_moment", ["index": .number(0), "moment": moment])
            case 2:
                #expect(data["scenario"]?.object["sessions"]?.array.count == source.moments.count + 1)
                #expect(data["tool_exchanges"]?.array.last?.object["result"]?.object["moment_id"] != nil)
                return try tool("update_activity", ["activity_id": .string(activities[0].id),
                    "changes": .object(["instructions": .string("Observe l’exemple et formule une hypothèse.")])])
            default: return "{\"kind\":\"proposal\",\"message\":\"Diagnostic de 5 minutes ajouté avant la découverte, à examiner.\"}"
            }
        }
        let proposal = try #require(reply.proposal)
        #expect(calls == 3)
        #expect(proposal.duration == source.duration + 5)
        #expect(proposal.fields["customWebField"] == source.fields["customWebField"])
        #expect(proposal.moments[1].activities[0].fields["customActivityField"] == .string("À conserver"))
        #expect(proposal.moments[1].activities[0].id == activities[0].id)
        #expect(proposal.moments[0].id != moment.object["id"]?.string)
        #expect(source.moments.count == 3)
    }

    @Test func invalidToolResultsAreReturnedForCorrectionWithoutChangingTheCopy() async throws {
        let source = Scenario.template()
        var calls = 0
        let reply = try await AssistantConversation.reply(scenario: source, history: []) { _, user in
            calls += 1
            if calls == 1 {
                return try tool("update_activity", ["activity_id": .string(source.moments[0].activities[0].id),
                    "changes": .object(["duration": .number(-10)])])
            }
            let data = try context(user)
            #expect(data["scenario"] == .object(source.fields))
            #expect(data["tool_exchanges"]?.array.last?.object["result"]?.object["error"] != nil)
            return "{\"kind\":\"message\",\"message\":\"Quelle durée souhaitez-vous ?\"}"
        }
        #expect(reply.proposal == nil && calls == 2)
    }

    @Test func invalidProposalCanBeRepairedUsingTheValidatorFeedback() async throws {
        let source = Scenario.template()
        var invalid = source; var moments = invalid.moments
        var activities = moments[0].activities; activities[0].duration = -1
        moments[0].activities = activities; invalid.moments = moments
        var calls = 0
        let result = try await AssistantConversation.reply(scenario: source, history: []) { _, user in
            calls += 1
            if calls == 1 { return try json(.object(["kind": .string("proposal"), "message": .string("Séance"), "scenario": .object(invalid.fields)])) }
            #expect(try context(user)["tool_exchanges"]?.array.last?.object["result"]?.object["error"] != nil)
            return try json(.object(["kind": .string("proposal"), "message": .string("Durée corrigée."), "scenario": .object(source.fields)]))
        }
        #expect(result.proposal == source && calls == 2)
    }

    @Test func aCommentOnARejectedProposalIsSentBackOnceForTheProposal() async throws {
        let source = Scenario.template()
        var invalid = source; var moments = invalid.moments
        var activities = moments[0].activities; activities[0].duration = -1
        moments[0].activities = activities; invalid.moments = moments
        let comment = try json(.object(["kind": .string("message"), "message": .string("Je vais corriger la durée.")]))
        var calls = 0
        let repaired = try await AssistantConversation.reply(scenario: source, history: []) { _, user in
            calls += 1
            if calls == 1 { return try json(.object(["kind": .string("proposal"), "message": .string("Séance"), "scenario": .object(invalid.fields)])) }
            if calls == 2 { return comment }
            #expect(try context(user)["tool_exchanges"]?.array.count == 2)
            return try json(.object(["kind": .string("proposal"), "message": .string("Durée corrigée."), "scenario": .object(source.fields)]))
        }
        #expect(repaired.proposal == source && calls == 3)
        // A second comment is a real answer: the teacher receives it.
        calls = 0
        let question = try await AssistantConversation.reply(scenario: source, history: []) { _, _ in
            calls += 1
            return calls == 1 ? try json(.object(["kind": .string("proposal"), "message": .string("Séance"), "scenario": .object(invalid.fields)])) : comment
        }
        #expect(question.proposal == nil && question.message == "Je vais corriger la durée." && calls == 3)
    }

    @Test func aReplyWrappedInASentenceIsStillRead() async throws {
        let reply = try await AssistantConversation.reply(scenario: .blank(), history: []) { _, _ in
            "Voici ma réponse :\n{\"kind\":\"message\",\"message\":\"Quel niveau ?\"}\nBonne journée."
        }
        #expect(reply.message == "Quel niveau ?" && reply.proposal == nil)
    }

    @Test func repeatedToolCallsStopAtABoundedLimit() async throws {
        var calls = 0
        await #expect(throws: ScenarioError.self) {
            try await AssistantConversation.reply(scenario: .template(), history: []) { _, _ in
                calls += 1; return try tool("analyze")
            }
        }
        #expect(calls == AssistantConversation.maximumToolCalls + 1)
    }

    @Test(arguments: ["{}", "{\"kind\":\"message\",\"message\":\"\"}", "{\"kind\":\"tool\",\"name\":\"analyze\"}", "Texte sans protocole"])
    func malformedRepliesCannotBecomeProposals(_ text: String) async throws {
        await #expect(throws: ScenarioError.self) {
            try await AssistantConversation.reply(scenario: .blank(), history: []) { _, _ in text }
        }
    }

    @Test func catalogsUseBundledIdentifiersAndSupportPagination() throws {
        let catalog = try CompetencyCatalog.bundled.get()
        let framework = try #require(catalog.frameworks.first { framework in catalog.competencies.filter { $0.frameworkId == framework.id }.count > 40 })
        var scenario = Scenario.blank()
        let first = try AssistantTools.run("catalog", arguments: ["topic": .string("competencies"), "framework": .string(framework.id)], scenario: &scenario, reference: scenario).object
        let next = try #require(first["nextOffset"])
        let second = try AssistantTools.run("catalog", arguments: ["topic": .string("competencies"), "framework": .string(framework.id), "offset": next], scenario: &scenario, reference: scenario).object
        let ids = (first["items"]?.array ?? []).map { $0.object["id"]?.string ?? "" }
        let nextIDs = (second["items"]?.array ?? []).map { $0.object["id"]?.string ?? "" }
        #expect(ids.count == 40 && Set(ids).isDisjoint(with: nextIDs))
        #expect(ids.allSatisfy { id in catalog.competencies.contains { $0.id == id && $0.frameworkId == framework.id } })
        #expect(scenario == .blank())
    }

    @Test func analysisReportsDurationsInsteadOfInventingAPedagogicalScore() {
        var source = Scenario.template()
        source.meta["learningHours"] = .number(1); source.meta["learningMinutes"] = .number(5)
        let analysis = AssistantTools.analysis(source).object
        #expect(analysis["minutes"] == .number(30))
        #expect(analysis["planned_minutes"] == .number(65))
        #expect(analysis["minutes_by_type"]?.object["practice"] == .number(10))
    }

    @Test func bloomOutcomesUseCatalogVerbsAndKeepPreviousAcquisitions() throws {
        let reference = Scenario.template(); var scenario = reference
        let found = try AssistantTools.run("catalog", arguments: ["topic": .string("bloom"), "search": .string("Paraphraser")],
            scenario: &scenario, reference: reference).object["items"]?.array ?? []
        #expect(found.contains { $0.object["id"] == .string("comprendre") })
        let outcome = try AssistantTools.run("add_outcome", arguments: ["category": .string("comprendre"), "verb": .string("Expliquer"),
            "text": .string("Expliquer les caractéristiques du conte à partir d’un exemple.")], scenario: &scenario, reference: reference).object
        let values = scenario.meta["sliders"]?.array ?? []
        #expect(values.count == 1 && values[0].object["id"] == outcome["outcome_id"])
        #expect(try AssistantTools.validated(scenario, reference: reference) == scenario)
        let saved = scenario
        #expect(throws: ScenarioError.self) {
            try AssistantTools.run("add_outcome", arguments: ["category": .string("comprendre"), "verb": .string("Inventé"), "text": .string("Test")],
                scenario: &scenario, reference: reference)
        }
        #expect(scenario == saved)
    }

    @Test func inventedCompetenciesAndSchoolLevelsAreRejectedButLegacyFieldsSurvive() throws {
        var source = Scenario.template()
        var moments = source.moments; var activities = moments[0].activities
        activities[0].fields["tools"] = .array([.string("legacy:custom")])
        moments[0].activities = activities; source.moments = moments
        #expect(try AssistantTools.validated(source, reference: source) == source)
        var invalid = source; var changed = moments; var changedActivities = changed[0].activities
        changedActivities[0].fields["tools"] = .array([.string("competency:invented")])
        changed[0].activities = changedActivities; invalid.moments = changed
        #expect(throws: ScenarioError.self) { try AssistantTools.validated(invalid, reference: source) }
        invalid = source; invalid.meta["schoolSystem"] = .string("invented")
        #expect(throws: ScenarioError.self) { try AssistantTools.validated(invalid, reference: source) }
        invalid = source; invalid.meta["sliders"] = .string("Acquis sans structure")
        #expect(throws: ScenarioError.self) { try AssistantTools.validated(invalid, reference: source) }
        invalid.meta["sliders"] = .array([.object(["category": .string("comprendre"), "verb": .string("Inventé"), "text": .string("Un acquis")])])
        #expect(throws: ScenarioError.self) { try AssistantTools.validated(invalid, reference: source) }
    }

    @Test func toolsCannotRunShellCommandsOrChangeActivityIdentifiers() throws {
        let reference = Scenario.template(); var scenario = reference
        #expect(throws: ScenarioError.self) { try AssistantTools.run("shell", arguments: ["command": .string("echo test")], scenario: &scenario, reference: reference) }
        #expect(throws: ScenarioError.self) {
            try AssistantTools.run("update_activity", arguments: ["activity_id": .string(reference.moments[0].activities[0].id),
                "changes": .object(["id": .string("replacement")])], scenario: &scenario, reference: reference)
        }
        #expect(scenario == reference)
    }

    @Test func attachedDocumentsReachTheModelAndStayInTheConversation() async throws {
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: folder) }
        let notes = folder.appendingPathComponent("programme.md")
        try Data("# Le conte\n\nLire et raconter un conte en sixième.".utf8).write(to: notes)
        let empty = folder.appendingPathComponent("vide.txt")
        try Data("  \n".utf8).write(to: empty)
        var received: [[AssistantTurn]] = []
        let model = AssistantModel(respond: { _, history, _, _ in
            received.append(history)
            return AssistantReply(message: "Combien de temps ?", proposal: nil)
        }, credential: { _ in "" })
        model.attach([notes, empty])
        #expect(model.attachments.map(\.name) == ["programme.md"])
        #expect(model.error?.contains("vide.txt") == true)
        await model.send("", scenario: .blank(), configuration: configuration)?.value
        #expect(model.attachments.isEmpty)
        #expect(model.messages.first?.attachments == ["programme.md"])
        let first = try #require(received.first?.last)
        #expect(first.content == "Voici mes documents.")
        #expect(first.documents?.first?.text.contains("raconter un conte") == true)
        await model.send("55 minutes", scenario: .blank(), configuration: configuration)?.value
        #expect(received.last?.first?.documents?.count == 1)

        var sent = ""
        _ = try await AssistantConversation.reply(scenario: .blank(), history: received.last ?? []) { _, user in
            sent = user
            return #"{"kind":"message","message":"OK"}"#
        }
        let conversation = try context(sent)["conversation"]
        guard case .array(let turns)? = conversation, case .object(let turn) = turns.first,
              case .array(let documents)? = turn["documents"], case .object(let document) = documents.first else {
            Issue.record("Les documents joints manquent dans la demande."); return
        }
        #expect(document["name"] == .string("programme.md"))
    }

    @Test func documentReaderExtractsOfficeAndTruncatesLongTexts() throws {
        let word = try Data(contentsOf: try #require(Bundle.module.url(forResource: "site-student", withExtension: "docx", subdirectory: "Fixtures")))
        #expect(!(try AssistantDocumentReader.document(word, name: "cours.docx").text.isEmpty))
        let long = String(repeating: "a", count: AssistantDocumentReader.characterLimit + 10)
        let document = try AssistantDocumentReader.document(Data(long.utf8), name: "long.txt")
        #expect(document.truncated && document.text.count == AssistantDocumentReader.characterLimit)
        #expect(throws: ScenarioError.self) { try AssistantDocumentReader.document(Data("<p> </p>".utf8), name: "vide.html") }
    }
}
