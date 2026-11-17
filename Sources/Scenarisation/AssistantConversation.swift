import Foundation

struct AssistantTurn: Codable, Equatable, Sendable {
    enum Role: String, Codable, Sendable { case user, assistant }
    let role: Role
    let content: String
}

struct AssistantReply: Sendable {
    let message: String
    let proposal: Scenario?
}

@MainActor
enum AssistantConversation {
    typealias Generate = @MainActor (String, String) async throws -> String
    static let maximumToolCalls = 6

    static func reply(scenario: Scenario, history: [AssistantTurn], configuration: AIConfiguration, key: String,
                      sessionConfiguration: URLSessionConfiguration = .ephemeral) async throws -> AssistantReply {
        try await reply(scenario: scenario, history: history) { system, user in
            try await AIClient.generate(system: system, user: user, configuration: configuration, key: key,
                sessionConfiguration: sessionConfiguration)
        }
    }

    static func reply(scenario: Scenario, history: [AssistantTurn], generate: Generate) async throws -> AssistantReply {
        var working = scenario
        var exchanges: [JSONValue] = []
        var rejected = false
        for turn in 0...maximumToolCalls {
            try Task.checkCancellation()
            let user = try context(scenario: working, history: history, exchanges: exchanges)
            let raw = try await generate(AssistantPrompt.dialogue, user)
            try Task.checkCancellation()
            let root = try object(from: raw)
            // Older/smaller models may still return a bare scenario.
            if root["kind"] == nil, root["meta"] != nil, root["sessions"] != nil {
                let result = try AssistantTools.validated(Scenario(fields: root), reference: scenario)
                return AssistantReply(message: summary(result), proposal: result)
            }
            switch root["kind"]?.string {
            case "message":
                let text = try message(root)
                // Smaller models tend to describe the repair of a rejected
                // proposal instead of sending it; ask once for the proposal.
                guard rejected, turn < maximumToolCalls else { return AssistantReply(message: text, proposal: nil) }
                rejected = false
                exchanges.append(exchange(root, result: .object(["error": .string(
                    "L’enseignant n’a reçu ni ce message ni la proposition rejetée. Fournis maintenant la proposition corrigée (kind=proposal avec scenario), ou ta question s’il manque vraiment une information.")])))
            case "proposal":
                let text = try message(root)
                do {
                    let proposed: Scenario
                    if let value = root["scenario"] {
                        guard case .object(let fields) = value else { throw ScenarioError.invalid("Le scénario proposé doit être un objet JSON.") }
                        proposed = Scenario(fields: fields)
                    } else { proposed = working }
                    let result = try AssistantTools.validated(proposed, reference: scenario)
                    return AssistantReply(message: text, proposal: result)
                } catch let error as ScenarioError {
                    // Return the actual validator result so the model can repair
                    // a proposal; nothing has touched the open document.
                    exchanges.append(exchange(root, result: .object(["error": .string(error.localizedDescription)])))
                    rejected = true
                }
            case "tool":
                guard turn < maximumToolCalls else { break }
                guard let name = root["name"], case .string(let name) = name,
                      case .object(let arguments) = root["arguments"] else { throw invalidReply() }
                let result: JSONValue
                do { result = try AssistantTools.run(name, arguments: arguments, scenario: &working, reference: scenario) }
                catch let error as ScenarioError { result = .object(["error": .string(error.localizedDescription)]) }
                exchanges.append(exchange(root, result: result))
            default: throw invalidReply()
            }
        }
        throw ScenarioError.invalid("L’assistant n’a pas terminé sa proposition dans le nombre d’étapes prévu. Précisez votre demande ou demandez une modification plus courte.")
    }

    private static func context(scenario: Scenario, history: [AssistantTurn], exchanges: [JSONValue]) throws -> String {
        let value = JSONValue.object([
            "conversation": .array(history.map { .object(["role": .string($0.role.rawValue), "content": .string($0.content)]) }),
            "scenario": .object(scenario.fields), "analysis": AssistantTools.analysis(scenario),
            "tool_exchanges": .array(exchanges)
        ])
        let data = try JSONEncoder().encode(value)
        guard data.count <= 20_000_000 else {
            throw ScenarioError.invalid("L’échange est trop volumineux. Commencez une nouvelle conversation ou réduisez le scénario.")
        }
        return String(decoding: data, as: UTF8.self)
    }

    private static func exchange(_ request: [String: JSONValue], result: JSONValue) -> JSONValue {
        .object(["assistant": .object(request), "result": result])
    }

    private static func object(from raw: String) throws -> [String: JSONValue] {
        var text = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        if text.hasPrefix("```") {
            var lines = text.components(separatedBy: "\n")
            guard lines.count >= 3, lines.last?.trimmingCharacters(in: .whitespaces) == "```" else { throw invalidReply() }
            lines.removeFirst(); lines.removeLast(); text = lines.joined(separator: "\n")
        }
        guard text.utf8.count <= 20_000_000 else { throw invalidReply() }
        if case .object(let root)? = try? JSONDecoder().decode(JSONValue.self, from: Data(text.utf8)) { return root }
        // Without a JSON mode, a local model may wrap its object in a sentence.
        guard let start = text.firstIndex(of: "{"), let end = text.lastIndex(of: "}"), start < end,
              case .object(let root)? = try? JSONDecoder().decode(JSONValue.self, from: Data(text[start...end].utf8)) else {
            throw invalidReply()
        }
        return root
    }

    private static func message(_ root: [String: JSONValue]) throws -> String {
        guard case .string(let value) = root["message"],
              !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else { throw invalidReply() }
        return value
    }

    private static func invalidReply() -> ScenarioError {
        .invalid("Le modèle n’a pas renvoyé un échange valide. Réessayez ou choisissez un autre modèle.")
    }

    private static func summary(_ scenario: Scenario) -> String {
        "Proposition prête : \(scenario.name), \(scenario.moments.count) moments, \(scenario.duration.formatted()) min."
    }
}
